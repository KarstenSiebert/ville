<?php

namespace App\Http\Controllers;

use DB;
use Str;
use Storage;
use Exception;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Market;
use App\Models\MarketDetail;
use Illuminate\Http\Request;
use App\Models\MarketLimitOrder;
use App\Http\Services\LimitOrderService;
use App\Http\Services\MarketTradesService;

class MarketDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $id)
    {  
        if (empty($id)) {
             return redirect('dashboard');
        }
        
        $market = Market::with(['baseToken:id,name,decimals,token_type,fingerprint,logo_url', 'outcomes:id,market_id,name,link,logo_url'])
                ->where('markets.id', $id)
                ->first();

        if (empty($market)) {
             return redirect('dashboard');
        }

        $orderTable = $market->allow_limit_orders ? app(LimitOrderService::class)->get($market) : [];        
        
        return Inertia::render('marketdetails/MarketDetail', [
            'market' => $market,
            'orderTable' => $orderTable
        ]);
    }
    
    public function trades(Request $request, Market $market)
    {
        $startDate = $request->query('start_date');
        $range     = $request->query('range', '1H');

        $trades = app(MarketTradesService::class)->get($market, $startDate, $range);

        return response()->json($trades);
    }
    
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(MarketDetail $marketDetail)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MarketDetail $marketDetail)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MarketDetail $marketDetail)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MarketDetail $marketDetail)
    {
        //
    }
}
