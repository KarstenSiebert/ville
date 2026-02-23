<?php

namespace App\Http\Middleware;

use DB;
use Closure;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Market;
use App\Models\Publisher;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMobileClient
{
    public function handle(Request $request, Closure $next): Response
    {        
        $marketId = $request->input('market_id') ? $request->input('market_id') : null;

        if (!$marketId) {
            return  response()->json(['message' => 'Missing market id'], 401);
        }

        $market = Market::find($marketId);

        if (empty($market)) {
            return  response()->json(['message' => 'Market not found'], 401);
        }
        
        $publisher = Publisher::find($market->publisher_id);

        if (empty($publisher)) {
            return  response()->json(['message' => 'Operator not found'], 401);
        }

        $request->merge(['publisher' => $publisher]);
                
        $publicId = $request->input('public_id') ? $request->input('public_id') : null;

        $deviceId = $request->input('device_id') ? $request->input('device_id') : null;

        if (empty($deviceId) || empty($publicId)) {
            return  response()->json(['message' => 'Device or public id missing'], 401);
        }
                 
        $shadowUser = DB::transaction(function () use ($publisher, $publicId, $deviceId) {
                
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

            Wallet::create([
                'user_id'          => $shadow->id,
                'parent_wallet_id' => null,
                'address'          => 'avaaddr1' . bin2hex(random_bytes(27)),
                'type'             => 'available',
                'user_type'        => 'SHADOW',
            ]);

            return $shadow;
        });
        
        $request->merge(['shadow_user' => $shadowUser]);
      
        return $next($request);
    }

}
