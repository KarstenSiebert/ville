<?php

namespace App\Http\Controllers;

use DB;
use Inertia\Inertia;
use App\Models\Token;
use App\Models\Market;
use App\Models\Wallet;
use App\Models\TokenWallet;
use App\Models\OutcomeToken;
use Illuminate\Http\Request;
use App\Models\MarketLimitOrder;
use App\Http\Services\LimitOrderService;
use App\Domain\Orders\OrderReserveService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MarketLimitOrderController extends Controller
{
    use AuthorizesRequests;

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $order = MarketLimitOrder::find($id);

        if (!empty($order) && $this->authorize('delete', $order)) {
            
            DB::transaction(function () use ($order) {
            
                $usrWalletId = Wallet::where('user_id', $order->user_id)->where('type', 'available')->whereNull('deleted_at')->value('id');

                if ($order->market && $order->market->baseToken) {
                    $baseTokenId = $order->market->baseToken->id;
                
                } else {                
                    $baseTokenId = $order->base_token_id ?? null;
                }

                $isBuy = strtoupper($order->type) === 'BUY';
      
                // $tokenId = $isBuy ? $baseTokenId : OutcomeToken::where('outcome_id', $order->outcome_id)->value('token_id');
                
                $tokenId = $isBuy ? Token::withTrashed()->find($baseTokenId)?->id : OutcomeToken::withTrashed()->where('outcome_id', $order->outcome_id)->value('token_id');

                $tokenWallet = TokenWallet::where('wallet_id', $usrWalletId)->where('token_id', $tokenId)->where('status', 'active')->first();
                                
                if (!empty($tokenWallet) && in_array($order->status, ['OPEN', 'PARTIAL', 'CANCELED', 'EXPIRED'])) {
                    OrderReserveService::releaseRemainingReserve($order, $tokenWallet);
                }
                
                $order->delete();                
            });

            return redirect()->back()->with('success', __('limit_order_removed_successfully'));

        }

        return redirect()->back()->with('success', __('limit_not_removed'));
    }
  
    public function index(Request $request)
    {
        $search = $request->input('search', null);
    
        $page = (int) $request->input('page', 1);
    
        $perPage = 10;

        $user = auth()->user();

        $orders = MarketLimitOrder::with([
                'market' => function ($q) {
                    $q->withTrashed();
                },
                'outcome' => function ($q) {
                    $q->withTrashed();
                }
            ])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(function ($order) {
                            
                return [
                    'id' => $order->id,
                    'limit_price' => $order->limit_price,
                    'share_amount' => $order->share_amount,

                    'filled' => $order->filled,

                    'status' => $order->status,
                    'type' => $order->type,

                    'decimals' => $order->market?->baseToken->decimals,
                    'base_token_name' => $order->market?->baseToken->name,

                    'valid_until' => $order->valid_until ? $order->valid_until : '',

                    'market_id' => $order->market?->id,
                    'market_title' => $order->market?->title,
                    'market_logo_url' => $order->market?->logo_url,
                    'outcome_name' => $order->outcome?->name,
                    'outcome_logo_url' => $order->outcome?->logo_url
                ];

        });

        // dd($orders);

        if ($orders->count()) {

            $paginated = new LengthAwarePaginator(
                    $orders->forPage($page, $perPage)->values(),
                    $orders->count(),
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => $request->query()]
                );
        
        } else {
            $paginated = new LengthAwarePaginator([], 0, $perPage, $page);            
        }

        return Inertia::render('orders/Orders', [
            'orders' => [
                'data' => $paginated->items(),
                'links' => $paginated->linkCollection()->map(function($link){
                    if ($link['label'] === '&laquo; Previous') $link['label'] = 'Prev';
                    if ($link['label'] === 'Next &raquo;') $link['label'] = 'Next';
                    return $link;
                }),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ]
            ]
        ]);
    }

    public function orderbook(Market $market)
    {                
        $orderbook = $market?->allow_limit_orders ? app(LimitOrderService::class)->get($market) : [];

        return response()->json($orderbook);
    }

}

