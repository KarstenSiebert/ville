<?php

namespace App\Http\Middleware;

use DB;
use Auth;
use Closure;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Market;
use App\Models\Transfer;
use App\Models\Publisher;
use Illuminate\Http\Request;
use App\Notifications\NewUserRegistered;
use Symfony\Component\HttpFoundation\Response;

class VerifyMobileClient
{
    public function handle(Request $request, Closure $next): Response
    {        
        $publicId = $request->header('X-SHADOW') ?? null;

        $deviceId = $request->header('X-DEVICE') ?? null;

        $marketId = $request->header('X-SMARKT') ?? null;

        $language = $request->header('X-User-Locale') ?? null;

        $supported = ['de', 'zh', 'en', 'es', 'fr', 'jp', 'bg', 'cz', 'dk', 'ee', 'fi', 'gr', 'hr', 'hu', 'ie', 'ir', 'it', 'lt', 'lv', 'mt', 'nl', 'pl', 'pt', 'ro', 'ru', 'sa', 'se', 'sk', 'sl', 'ua'];
    
        if (!in_array($language, $supported)) {
            $language = 'en';
        }

        if (isset($language) && in_array($language, $supported)) {
            app()->setLocale($language);            
        }
                
        $request->merge(['locale' => $language]);

        if (empty($marketId)) {
            $marketId = $request->input('market_id') ?? null;
        }
        
        if (!$marketId) {
            return  response()->json(['message' => 'Missing market id'], 401);
        }

        $market = Market::withCount('subscribers')->find($marketId);

        if (empty($market)) {
            return  response()->json(['message' => 'Market not found'], 401);
        }
        
        $publisher = Publisher::find($market->publisher_id);

        if (empty($publisher)) {
            return  response()->json(['message' => 'Operator not found'], 401);
        }

        $request->merge(['publisher' => $publisher]);
                
        if (empty($publicId)) {
            $publicId = $request->input('public_id') ?? null;
        }

        if (empty($deviceId)) {
            $deviceId = $request->input('device_id') ?? null;
        }
        
        if (empty($deviceId) || empty($publicId)) {
            return  response()->json(['message' => 'Device or public id missing'], 401);
        }

        $shadowUser = DB::transaction(function () use ($publisher, $market, $publicId, $deviceId) {
    
            // Check for existence of user

            $shadow = User::where('device_id', $deviceId)->where('publisher_id', $publisher->id)->first();

            $isNewUser = false;

            if (!$shadow) {

                try {
                    $shadow = User::create([
                        'external_user_id'  => null,
                        'publisher_id'      => $publisher->id,
                        'name'              => strval($deviceId),
                        'type'              => 'SHADOW',
                        'device_id'         => $deviceId,
                        'public_id'         => $publicId                        
                    ]);

                } catch (\Illuminate\Database\QueryException $e) {
    
                }

                Wallet::create([
                    'user_id'          => $shadow->id,
                    'parent_wallet_id' => null,
                    'address'          => 'avaaddr1' . bin2hex(random_bytes(27)),
                    'type'             => 'available',
                    'user_type'        => 'SHADOW',
                ]);

                $isNewUser = true;
            }

            // Check for existance of subscription
            
            $isNewSubscriber = !$market->subscribers()->where('user_id', $shadow->id)->exists();

            if ($isNewSubscriber) {
                $market->subscribers()->attach($shadow->id);
            }

            // Only transfer to users, if they have been created and new to the market

            if ($isNewUser || $isNewSubscriber) {

                if ($market->max_subscribers && $market->max_subscribers > $market->subscribers()->count()) {

                    $marketWallet = Wallet::find($market->wallet_id);                    
                    $shadowWallet = Wallet::where('user_id', $shadow->id)->where('user_type', 'SHADOW')->where('type', 'available')->first();
                    
                    $baseToken    = $market->baseToken;

                    $userValue = intval(floor($market->b / $market->max_subscribers));

                    $sum = bcmul($userValue, bcpow("10", (string) $baseToken->decimals));

                    if (!empty($marketWallet) && !empty($shadowWallet) && ($sum > 0)) {
                        Transfer::execute($marketWallet, $shadowWallet, $baseToken, $sum, 'internal', 0, 'FIRST MARKET ACCESS', false);
                    }
                }
            }            

            return $shadow;
        });
                 
        $request->merge(['shadow_user' => $shadowUser]);

        return $next($request);
    }

}
