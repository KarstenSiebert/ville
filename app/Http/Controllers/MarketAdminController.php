<?php

namespace App\Http\Controllers;

use DB;
use App\Models\Market;
use App\Models\Wallet;
use App\Models\TokenWallet;
use App\Models\OutcomeToken;
use Illuminate\Http\Request;
use App\Http\Services\MarketSettlementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MarketAdminController extends Controller
{
    use AuthorizesRequests;

    public function close(Market $market)
    {     
        if (!empty($market) && $this->authorize('close', $market)) {
        
            if ($market->status !== 'OPEN') {
                return back()->with('error', __('market_not_open'));
            }

            DB::transaction(function () use ($market) {

                foreach ($market->limitOrders()->whereIn('status', ['OPEN','PARTIAL'])->get() as $order) {

                    $wallet = Wallet::where('user_id', $order->user_id)
                        ->where('type', 'available')
                        ->whereNull('deleted_at')
                        ->first();

                    $tokenId = strtoupper($order->type) === 'BUY' ? $market->baseToken->id : OutcomeToken::where('outcome_id', $order->outcome_id)->value('token_id');

                    $tokenWallet = TokenWallet::where('wallet_id', $wallet->id)
                        ->where('token_id', $tokenId)
                        ->where('status', 'active')
                        ->first();

                    app(\App\Domain\Orders\OrderReserveService::class)->releaseRemainingReserve($order, $tokenWallet);

                    $order->status = 'CANCELED';

                    $order->save();
                }

                $market->update(['status' => 'CLOSED']);
            });
           
            return back()->with('success', __('market_closed'));
        }

        return back()->with('error', __('market_not_closed'));
    }

    public function resolve(Request $request, Market $market)
    {        
        if (!empty($market) && $this->authorize('settle', $market)) {
      
            if ($market->status !== 'CLOSED') {                                
                return back()->with('error', __('market_not_closed'));
            }

            $request->validate([
                'winning_outcome_id' => ['required', 'exists:outcomes,id']
            ]);

            try {
                app(MarketSettlementService::class)->resolveMarket($market, $request->winning_outcome_id);
                
                return back()->with('success', __('market_resolved_successfully'));
                    
            } catch (\DomainException $e) {                                
                return back()->with('error', __('market_not_resolved'));
            }
        }
        
        return back()->with('error', __('market_not_resolved'));
    }

    public function cancel(Market $market)
    {
        if (!empty($market) && $this->authorize('cancel', $market)) {

            if (!in_array($market->status, ['OPEN', 'CLOSED'])) {
                return back()->with('error', __('market_not_cancelable'));
            }

            try {
                app(MarketSettlementService::class)->cancelMarket($market);

                return back()->with('success', __('market_canceled'));
               
            } catch (\DomainException $e) {
                return response()->json(['error' => __('market_not_canceled')]);
            }  
        }

        return back()->with('error', __('market_not_canceled'));
    }

}
