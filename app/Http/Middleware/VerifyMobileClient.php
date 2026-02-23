<?php

namespace App\Http\Middleware;

use DB;
use Auth;
use Closure;
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
                
            $existingShadow = User::where('name', $deviceId)->where('device_id', $deviceId)->where('publisher_id', $publisher->id)->first();

            if ($existingShadow) {
                return $existingShadow;
            }
                                
            $shadow = User::create([
                'external_user_id' => null,
                'publisher_id'     => $publisher->id,
                'name'             => strval($deviceId),
                'type'             => 'SHADOW',
                'device_id'        => $deviceId,
                'public_id'        => $publicId
            ]);

            $shadowWallet = Wallet::create([
                'user_id'          => $shadow->id,
                'parent_wallet_id' => null,
                'address'          => 'avaaddr1' . bin2hex(random_bytes(27)),
                'type'             => 'available',
                'user_type'        => 'SHADOW',
            ]);

            $admin = User::where('id', 1)->first();

            if ($admin && $shadow) {
                $admin->notify(new NewUserRegistered($shadow));
            }

            $market->subscribers()->syncWithoutDetaching([$shadow->id]);

            // Trasfer token values to subscribers

            $marketWallet = Wallet::find($market->wallet_id); 

            $baseToken = $market->baseToken;

            if ($market->max_subscribers && ($market->max_subscribers > $market->subscribers_count)) {
                
                // b is given in standard numbers, not including decimals
                
                $userValue = Intval(floor($market->b / $market->max_subscribers));

                // tokens (sum) are transferred including their decimals

                $sum = bcmul($userValue, bcpow("10", (string) $baseToken->decimals));
            
                Transfer::execute($marketWallet, $shadowWallet, $baseToken, $sum, 'internal', 0, 'NEW SUBSCRIBER', false);                
            }

            return $shadow;
        });

        $request->merge(['shadow_user' => $shadowUser]);

        return $next($request);
    }

}
