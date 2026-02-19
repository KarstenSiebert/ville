<?php

namespace App\Http\Controllers\Api;

use App\Models\Market;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Services\OutcomeService;
use App\Http\Services\LimitOrderService;
use App\Http\Services\MarketTradeService;
use Illuminate\Validation\ValidationException;

class ApiOrderController extends Controller
{
    public function index(Request $request)
    {        
        $suserId = $request->shadow_user->id;

        $publisherId = $request->publisher->id;

        $marketId = $request->input('market_id');
                
        $orders = app(LimitOrderService::class)->orders($publisherId, $marketId, $suserId);
        
        return response()->json($orders);
    }

    public function store(Request $request)
    {        
        $validated = $request->validate([            
            'outcome_id'  => ['required', 'integer', 'exists:outcomes,id'],            
            'side'        => ['required', 'string', 'in:buy,sell,BUY,SELL'],
            'price'       => ['required', 'numeric', 'gt:0'],
            'amount'      => ['required', 'numeric', 'min:1'],
            'expire'      => ['required', 'string', 'in:GTC,GTD'],                        
            'expire_date' => ['nullable', 'required_if:expire,GTD', 'date_format:Y-m-d\TH:i', 'after_or_equal:now']            
        ]);
        
        $suserId = $request->shadow_user->id;

        $publisherId = $request->publisher->id;

        $marketId = $request->input('market_id');
                
        $orders = app(LimitOrderService::class)->create($validated, $publisherId, $marketId, $suserId);
        
        return response()->json($orders);
    }
    
    public function cancel(Request $request, $id)
    {
        $suserId = $request->shadow_user->id;

        $publisherId = $request->publisher->id;

        $marketId = $request->input('market_id');
                
        $orders = app(LimitOrderService::class)->cancel($publisherId, $suserId, $marketId, $id);
        
        return response()->json($orders);
    }

    public function buy(Request $request)
    {
        $validated = $request->validate([
            'outcome_id' => ['required', 'integer'],
            'buy_amount' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0.000001'],
        ]);
        
        $suserId = $request->shadow_user->id;

        $publisherId = $request->publisher->id;

        $marketId = $request->input('market_id');
                
        $orders = app(LimitOrderService::class)->buy($validated, $publisherId, $marketId, $suserId);

        return response()->json($orders);
    }

    public function price(Request $request)
    {        
        $request->validate([
            'outcome_id' => ['required', 'integer'],
            'buy_amount' => ['required', 'integer', 'min:0'],
        ]);

        $marketId = $request->input('market_id') ?? null;
        
        $outcomeId = $request->input('outcome_id') ?? null;

        $buyAmount = $request->input('buy_amount') ?? null;

        $market = Market::find($marketId);

        $calc = [];

        if (!empty($market) && $outcomeId && $buyAmount) {
            $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);            
        }
            
        return response()->json($calc);
    }

}
