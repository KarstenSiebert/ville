<?php

namespace App\Http\Controllers;

use Auth;
use Inertia\Inertia;
use App\Models\Market;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;

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

        $user = $request->shadow_user;
      
        $market = Market::with(['baseToken:id,name,decimals,token_type,fingerprint,logo_url', 'outcomes:id,market_id,name,link,logo_url'])
                ->where('markets.id', $id)
                ->first();

        if (empty($market)) {
             return redirect('dashboard');
        }
        
        return Inertia::render('api/MarketDetail', [
            'market' => $market,            
        ]);
    }

    public function webview(Request $request, $id)
    {
        $shadowUser = $request->shadow_user;
        
        $loginUrl = URL::temporarySignedRoute('webview.login', now()->addMinutes(5), ['user' => $shadowUser->id, 'market' => $id]);

        return  response()->json(['access' => $loginUrl], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); 
    }

}
