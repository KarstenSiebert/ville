<?php

namespace App\Http\Middleware;

use DB;
use Closure;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Publisher;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPublisher
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-KEY');

        $timestamp = $request->header('X-TIMESTAMP');
        
        $signature = $request->header('X-SIGNATURE');

        if (!$apiKey || !$timestamp || !$signature) {
            return response()->json(['message' => 'Missing authentication headers'], 401);
        }
        
        $publisher = Publisher::where('api_key', $apiKey)->first();
        
        if (!$publisher || !$publisher->active) {
            return response()->json(['message' => 'Invalid or inactive API key'], 401);
        }
        
        if (abs(time() - (int) $timestamp) > 300) {
            // return response()->json(['message' => 'Timestamp expired'], 401);
        }

        $body = $request->all();

        if (!empty($body)) {
            ksort($body);
        
            $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES);

        } else {
            $jsonBody = '';
        }

        $method = strtoupper($request->method());

        $path = '/' . ltrim($request->path(), '/');

        $payload = $method . $path . $jsonBody . $timestamp;

        $expectedSignature = hash_hmac('sha256', $payload, $publisher->secret_key);

        if (!hash_equals($expectedSignature, $signature)) {
            // return response()->json(['message' => 'Invalid signature'], 401);
        }        

        $externalId = intval($request->input('external_user_id'));

        $request->merge(['publisher' => $publisher]);
        
        // Requests without an external_user_id are publisher requests

        $publisherId = $publisher->id;

        if ($externalId) {

            $shadowUser = DB::transaction(function () use ($externalId, $publisher) {
                
                $existingShadow = User::where('external_user_id', $externalId)->where('publisher_id', $publisher->id)->first();

                if ($existingShadow) {
                    return $existingShadow;
                }
        
                $currentCount = User::where('publisher_id', $publisher->id)->where('type', 'SHADOW')->count();

                if ($currentCount >= $publisher->max_shadows) {
                    return response()->json(['message' => 'Reached maximum number of users']);
                }
                        
                $shadow = User::create([
                    'external_user_id' => $externalId,
                    'publisher_id'     => $publisher->id,
                    'name'             => strval($externalId),
                    'type'             => 'SHADOW',
                    'device_id'        => null,
                    'public_id'        => null
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
        }

        return $next($request);
    }

}
