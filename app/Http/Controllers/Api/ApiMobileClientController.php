<?php

namespace App\Http\Controllers\Api;

use Auth;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Market;
use App\Models\TokenWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiMobileClientController;

class ApiMobileClientController extends Controller
{
    public function index(Request $request)
    {
        $shadowId = $request->shadow_user?->id ?? null;

        return response()->json(['hallo' => $request->public_id]);
    }

    public function detail(Request $request, $id)
    {  
        if (empty($id)) {
             return redirect('dashboard');
        }
        
        $user = auth()->user();

        if (!$user) {
            return redirect('login');
        }

        $market = Market::with(['baseToken:id,name,decimals,token_type,fingerprint,logo_url', 'outcomes:id,market_id,name,link,logo_url'])
                ->where('markets.id', $id)
                ->first();

        if (empty($market)) {
             return redirect('dashboard');
        }        
                
        return Inertia::render('api/MarketDetail', [
            'market' => $market,
            'tokenValue' => $this->getAvailableTokens($market, $user),
        ]);
    }

    public function webview(Request $request, $id)
    {
        $shadowUser = $request->shadow_user;

        $loginUrl = URL::temporarySignedRoute('webview.login', now()->addMinutes(60), ['user' => $shadowUser->id, 'market' => $id]);

        return  response()->json(['access' => $loginUrl], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); 
    }

    private function getAvailableTokens(Market $market, User $user)
    {
        $baseToken = $market->baseToken;

        $wallet = TokenWallet::where('wallet_id', $user?->avaWallet?->id)->where('token_id', $baseToken->id)->first();

        $tokenValue = $wallet?->available ?? 0;
                
        $availableTokens = bcdiv($tokenValue, bcpow("10", (string) $baseToken->decimals, $baseToken->decimals), $baseToken->decimals);

        return $availableTokens;
    }

}
