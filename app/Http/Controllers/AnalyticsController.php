<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Market;
use Illuminate\Http\Request;
use App\Http\Services\AnalyticsService;

class AnalyticsController extends Controller
{
    public function show(Request $request, $id)
    {  
        $market = Market::with(['baseToken:id,name,decimals,token_type,fingerprint,logo_url', 'outcomes:id,market_id,name,logo_url'])
                ->where('markets.id', $id)
                ->first();

        if (empty($market)) {
             return redirect('dashboard');
        }
        
        return Inertia::render('analytics/Analytics', [
            'market' => $market
        ]);
    }

    public function active(Request $request, Market $market)
    {
        $startDate = $request->query('start_date');
        $range     = $request->query('range', '1H');

        $trades = app(AnalyticsService::class)->get($market, $startDate, $range);

        return response()->json($trades);
    }
    
}
