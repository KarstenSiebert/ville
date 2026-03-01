<?php

namespace App\Http\Services;

use DB;
use App\Models\User;
use App\Models\Market;
use App\Models\Wallet;
use App\Models\Transfer;
use App\Models\Publisher;
use App\Models\MarketTrade;
use App\Models\TokenWallet;
use App\Models\OutcomeToken;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MarketSettlementService
{
    use AuthorizesRequests;

    public function cancelMarket(Market $market, $api = false)
    {
        if (!$api && (empty($market) || !$this->authorize('cancel', $market))) {
            return;
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

            $marketWallet = $market->wallet;
            $baseToken = $market->baseToken;
                            
            $buyTransfers = Transfer::where('to_wallet_id', $marketWallet->id)
                ->whereNotNull('from_wallet_id')
                ->where('token_id', $baseToken->id)
                ->where('note', 'PBUY')
                ->lockForUpdate()
                ->get();
            
            if ($buyTransfers->isEmpty()) {
                $market->update(['status' => 'CANCELED', 'canceled_at' => now(), 'cancel_reason' => 'No trades']);
                return;
            }
            
            $refunds = $buyTransfers->groupBy('from_wallet_id');

            foreach ($refunds as $userWalletId => $transfers) {
                $totalRefund = $transfers->sum('quantity');
            
                $userWallet = Wallet::find($userWalletId);
            
                if (!$userWallet) continue;
                        
                if ($totalRefund > 0) {

                    Transfer::execute($marketWallet, $userWallet, $baseToken, $totalRefund, 'internal', 0, 'REFUND', false);
                
                    MarketTrade::create([
                        'market_id'         => $market->id,
                        'user_id'           => $userWallet->user_id,
                        'outcome_id'        => null,
                        'share_amount'      => 0,
                        'price_paid'        => $totalRefund,                    
                        'price_numerator'   => 0,
                        'price_denominator' => 0,
                        'tx_type'           => 'CANCEL'
                    ]);

                    // Send the promotion value back to the publisher

                    /*
                    $promotion = (int) max($market->b / $market->max_subscribers, 0);

                    $sum = bcmul($promotion, bcpow("10", (string) $baseToken->decimals));

                    $available = $userWallet->tokenWallets()->where('token_id', $baseToken->id)->sum(DB::raw('quantity - reserved_quantity'));
                                        
                    $promotion = (int) max((int) min($sum, $available), 0);

                    if ($promotion > 0) {
                    
                        $publisher = Publisher::with('user.avaWallet')->where('id', $market->publisher_id)->first();
                
                        if ($publisher) {           
                            $pubWallet = $publisher->user->avaWallet;

                            Transfer::execute($userWallet, $pubWallet, $baseToken, $promotion, 'internal', 0, 'RETURN PROMO', false);

                            MarketTrade::create([
                                'market_id'         => $market->id,
                                'user_id'           => $pubWallet->user_id,
                                'outcome_id'        => null,
                                'share_amount'      => 0,
                                'price_paid'        => $promotion,                            
                                'price_numerator'   => 0,
                                'price_denominator' => 0,
                                'tx_type'           => 'CANCEL'
                            ]);
                        }
                    }
                    */               
                }
            }
                
            foreach ($market->outcomes as $outcome) {
                $outcomeToken = $outcome->outcomeToken?->token;
            
                if (!$outcomeToken) continue;

                 $userTokenWallets = TokenWallet::where('token_id', $outcomeToken->id)
                        ->whereIn('wallet_id', $buyTransfers->pluck('from_wallet_id'))
                        ->lockForUpdate()
                        ->get();

                foreach ($userTokenWallets as $userTokenWallet) {
                    $userWallet = $userTokenWallet->wallet;
                
                    $userTokenAmount = $userTokenWallet->quantity;

                    if (!$userWallet) continue;
                        
                    if ($userTokenAmount > 0) {
                        Transfer::execute($userWallet, $marketWallet, $outcomeToken, $userTokenAmount, 'internal', 0, 'REFUND-SHARE-RETURN', false);

                        $userTokenWallet->update(['quantity' => 0, 'reserved_quantity' => 0]);

                        MarketTrade::create([
                            'market_id'         => $market->id,
                            'user_id'           => $userWallet->user_id,
                            'outcome_id'        => $outcome->id,
                            'share_amount'      => $userTokenAmount,
                            'price_paid'        => 0,                            
                            'price_numerator'   => 0,
                            'price_denominator' => 0,
                            'tx_type'           => 'CANCEL'
                        ]);
                    }
                }

                /**
                 * 
                 * If the token has been sold or move to another wallet, it will be burnt anyway.
                 * Otherwise the user would still have (own) the token of the canceled market.
                 * 
                 */
                                
                $movedShareTokenWallets = TokenWallet::where('token_id', $outcomeToken->id)                                        
                                        ->where('wallet_id', '<>', $marketWallet->id)
                                        ->where('quantity', '>', 0)
                                        ->lockForUpdate()
                                        ->get();

                foreach($movedShareTokenWallets as $movedShareTokenWallet) {
                    $movedShareWallet = $movedShareTokenWallet->wallet;

                    $movedShareAmount = $movedShareTokenWallet->quantity;

                    if (!$movedShareWallet) continue;

                    if ($movedShareAmount > 0) {
                        Transfer::execute($movedShareWallet, $marketWallet, $outcomeToken, $movedShareAmount, 'internal', 0, 'REFUND-MOVED-RETURN', false);

                        $movedShareTokenWallet->update(['quantity' => 0, 'reserved_quantity' => 0]);

                        MarketTrade::create([
                            'market_id'         => $market->id,
                            'user_id'           => $movedShareWallet->user_id,
                            'outcome_id'        => $outcome->id,
                            'share_amount'      => $movedShareAmount,
                            'price_paid'        => 0,                        
                            'price_numerator'   => 0,
                            'price_denominator' => 0,
                            'tx_type'           => 'CANCEL'
                        ]);
                    }
                }

                $marketTokenWallet = TokenWallet::where('wallet_id', $marketWallet->id)->where('token_id', $outcomeToken->id)->lockForUpdate()->first();
                
                $marketTokenAmount = $marketTokenWallet?->quantity ?? 0;

                if ($marketTokenAmount > 0) {
                    Transfer::execute($marketWallet, null, $outcomeToken, $marketTokenAmount, 'internal', 0, 'SHARE-BURN', false);

                    $marketTokenWallet->update(['quantity' => 0, 'reserved_quantity' => 0]);

                    MarketTrade::create([
                        'market_id'         => $market->id,
                        'user_id'           => null,
                        'outcome_id'        => $outcome->id,
                        'share_amount'      => $marketTokenAmount,
                        'price_paid'        => 0,
                        'price_numerator'   => 0,
                        'price_denominator' => 0,                        
                        'tx_type'           => 'CANCEL'
                    ]);
                }
            }
            
            $market->update(['status' => 'CANCELED', 'canceled_at' => now(), 'cancel_reason' => 'Admin canceled']);
        });
    }

    public function settleMarket(Request $request)
    {    
        $market = Market::findOrFail($request->id);

        if (empty($market) || !$this->authorize('settle', $market)) {
            return;
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

            $marketWallet = $market->wallet;
            $baseToken = $market->baseToken;

            // Gewinner-Outcome
            $winningOutcome = $market->winningOutcome;

            if (!$winningOutcome) {
                throw new \Exception('No resolved outcome found.');
            }

            $winningOutcomeToken = $winningOutcome->outcomeToken->token;
                
            // Alle Wallets mit Gewinner-Token
            $winnerWallets = TokenWallet::where('token_id', $winningOutcomeToken->id)
                ->where('quantity', '>', 0)
                ->lockForUpdate()
                ->get();

            if ($winnerWallets->isEmpty()) {                
                throw new \Exception('No winning token holders found.');
            }

            // Gesamtmenge an Winner-Shares
            $totalWinningShares = $winnerWallets->sum('quantity');

            if ($totalWinningShares <= 0) {
                throw new \Exception('Total winning shares is zero.');
            }
                
            // Pool im Market Wallet (Base Token)
            $baseTokenWallet = TokenWallet::where('wallet_id', $marketWallet->id)
                ->where('token_id', $baseToken->id)
                ->lockForUpdate()
                ->first();

            if (!$baseTokenWallet || $baseTokenWallet->quantity <= 0) {
                throw new \Exception('Market pool is empty.');
            }

            // $pool = $baseTokenWallet->quantity;

            // NEU !!! Der Liquidity Provider bekommt seine Einlagen zurück.

            $pool = $baseTokenWallet->quantity - $market->liquidity_b;
                
            $creatorWallet = $marketWallet->parentAvailableWallet();

            if ($creatorWallet && ($market->liquidity_b > 0)) {
                Transfer::execute($marketWallet, $creatorWallet, $baseToken, $market->liquidity_b, 'internal', 0, 'RETURN', false);
                                
                MarketTrade::create([
                    'market_id'         => $market->id,
                    'user_id'           => $creatorWallet->user_id,
                    'outcome_id'        => null,
                    'share_amount'      => 0,
                    'price_paid'        => $market->liquidity_b,
                    'price_numerator'   => 0,
                    'price_denominator' => 0,
                    'tx_type'           => 'SETTLE'                    
                ]);
            }

            // Verteile Pool anteilig
            foreach ($winnerWallets as $winnerTokenWallet) {
                $userWallet = $winnerTokenWallet->wallet;

                $userShare = $winnerTokenWallet->quantity / $totalWinningShares;
            
                $payout = (int) floor($userShare * $pool);

                if ($payout > 0) {
                    Transfer::execute($marketWallet, $userWallet, $baseToken, $payout, 'internal', 0, 'PAYOUT', false);
                
                     MarketTrade::create([
                        'market_id'         => $market->id,
                        'user_id'           => $userWallet->user_id,
                        'outcome_id'        => null,
                        'share_amount'      => 0,                    
                        'price_paid'        => $payout,
                        'price_numerator'   => 0,
                        'price_denominator' => 0,
                        'tx_type'           => 'SETTLE'
                    ]);
                }
            }
              
            $outcomeTokens = $market->outcomes->pluck('outcomeToken.token_id')->filter()->all();

            /*
            $outcomeTokens = $market->outcomes
                ->map(fn($outcome) => optional($outcome->outcomeToken)->token)
                ->filter()
                ->values()
                ->all();
            */

            $allTokenWallets = TokenWallet::whereIn('token_id', $outcomeTokens)
                ->where('quantity', '>', 0)
                ->lockForUpdate()
                ->get();

            foreach ($allTokenWallets as $tokenWallet) {
                $quantity = $tokenWallet->quantity;
    
                if ($quantity <= 0) continue;

                $shareToken = $tokenWallet->token;
             
                Transfer::execute($tokenWallet->wallet, $marketWallet, $shareToken, $quantity, 'internal', 0, 'RETURN_SHARE', false);
                    
                $tokenWallet->update(['quantity' => 0]);
            }
            
            $marketTokenWallets = TokenWallet::where('wallet_id', $marketWallet->id)->whereIn('token_id', $outcomeTokens)
                ->where('quantity', '>', 0)
                ->lockForUpdate()
                ->get();

            foreach ($marketTokenWallets as $marketTokenWallet) {
                $quantity = $marketTokenWallet->quantity;
    
                if ($quantity <= 0) continue;
                
                $shareToken = $marketTokenWallet->token;

                Transfer::execute($marketTokenWallet->wallet, null, $shareToken, $quantity, 'internal', 0, 'BURN_SHARE', false);
                    
                $marketTokenWallet->update(['quantity' => 0]);
            }

            // Markt als settled markieren
            $market->update(['status' => 'SETTLED', 'resolved_outcome_id' => $winningOutcome->id, 'settled_at' => now()]);

            $remaining = TokenWallet::where('wallet_id', $marketWallet->id)
                ->where('token_id', $baseToken->id)
                ->value('quantity');

            $adminWallet = Wallet::where('user_id', 1)->where('type', 'available')->first();

            if ($adminWallet) {
            
                if ($remaining > 0) {                
                    Transfer::execute($marketWallet, $adminWallet, $baseToken, $remaining, 'internal', 0, 'ADJUST', false);

                    MarketTrade::create([
                        'market_id'         => $market->id,
                        'user_id'           => $adminWallet->user_id,
                        'outcome_id'        => null,
                        'share_amount'      => 0,
                        'price_paid'        => $remaining,
                        'price_numerator'   => 0,
                        'price_denominator' => 0,
                        'tx_type'           => 'ADJUST'
                    ]);                   
                }

                $feeTransfers = Transfer::where('to_wallet_id', $adminWallet->id)
                    ->whereNotNull('from_wallet_id')
                    ->where('token_id', $baseToken->id)
                    ->where('note', 'FEE-'.$market->id)
                    ->lockForUpdate()
                    ->get();
                                            
                $totalFees = $feeTransfers->sum('quantity');

                // Currently fixed 70% of fees go to Market Creator

                // $creatorShare = (int) round($totalFees * 0.7);

                // 100% go to Publisher / Market Creator

                $creatorShare = $totalFees;
                                
                if ($creatorShare > 0) {
                    Transfer::execute($adminWallet, $creatorWallet, $baseToken, $creatorShare, 'internal', 0, 'CREATOR-FEE-'.$market->id, false);
                
                    MarketTrade::create([
                        'market_id'         => $market->id,
                        'user_id'           => $creatorWallet->user_id,
                        'outcome_id'        => null,
                        'share_amount'      => 0,
                        'price_paid'        => $creatorShare,                        
                        'price_numerator'   => 0,
                        'price_denominator' => 0,
                        'tx_type'           => 'ADJUST'
                    ]);
                }
            }
        });
    }

    public function resolveMarket(Market $market, int $winningOutcomeId): void
    {
        if ($market->status !== 'CLOSED') {
            throw new \DomainException(__('market_not_closed'));
        }

        if (!$market->outcomes()->where('id', $winningOutcomeId)->exists()) {
            throw new \DomainException(__('invalid_outcome'));
        }

        $market->update(['status' => 'RESOLVED', 'winning_outcome_id' => $winningOutcomeId, 'resolved_at' => now()]);
    }

    public function closeMarket(Market $market)
    {     
        if ($market->status !== 'OPEN') {
            throw new \DomainException(__('market_not_open'));
        }

        $market->update(['status' => 'CLOSED']);
    }

    public function winners(Request $request, $id)
    {        
        $publisher = $request->publisher;

        $market = Market::where('id', $id)->where('publisher_id', $publisher->id)->first();

        if (empty($market)) return response()->json();

        $marketWallet = $market->wallet;
        $baseToken = $market->baseToken;

        $winningOutcome = $market->winningOutcome ?? null;

        if (!$winningOutcome) {            
            return response()->json(['error' =>  __('no_winning_outcome')]);
        }
        
        $winningOutcomeToken = $winningOutcome->outcomeToken->token;

        // $winnerWallets = TokenWallet::with('user')->get(); <= through relation
        
        $winnerWallets = TokenWallet::with('wallet.user')
            ->where('token_id', $winningOutcomeToken->id)
            ->where('quantity', '>', 0)
            ->lockForUpdate()
            ->get();
                    
        if ($winnerWallets->isEmpty()) {
            return response()->json(['error' =>  __('no_winning_wallets')]);
        }
        
        $totalWinningShares = $winnerWallets->sum('quantity');

        if ($totalWinningShares <= 0) {
            return response()->json(['error' =>  __('no_winning_wallet_shares')]);
        }
        
        $baseTokenWallet = TokenWallet::where('wallet_id', $marketWallet->id)
            ->where('token_id', $baseToken->id)        
            ->first();
        
        $pool = $baseTokenWallet->quantity - $market->liquidity_b;

        $winners = [];

        foreach ($winnerWallets as $winnerTokenWallet) {

            $userName = $winnerTokenWallet->wallet->user->name;

            // $userName = $winnerTokenWallet->user->name; <= through relation
            
            $userWallet = $winnerTokenWallet->wallet;                    

            $userShare = $winnerTokenWallet->quantity / $totalWinningShares;            
            
            $payout = (int) floor($userShare * $pool);
            
            $winners[] = ['user' => $userName, 'payout' => $payout];
        }

        return response()->json($winners);
    }

}
