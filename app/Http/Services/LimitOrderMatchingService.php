<?php

namespace App\Http\Services;

use DB;
use Exception;
use App\Models\Market;
use App\Models\Wallet;
use App\Models\Outcome;
use App\Models\Transfer;
use App\Models\MarketTrade;
use App\Models\TokenWallet;
use App\Models\OutcomeToken;
use App\Models\MarketLimitOrder;

class LimitOrderMatchingService
{
    public static function match(?MarketLimitOrder $order = null, ?int $marketOutcomeId = null): void
    {
        if ($order) {
            $marketId  = $order->market_id;
            $outcomeId = $order->outcome_id ? $order->outcome_id : null;
    
        } else if ($marketOutcomeId) {
            $marketId  = Outcome::find($marketOutcomeId)->value('market_id');
            $outcomeId = $marketOutcomeId;
        
        } else {
            throw new \Exception('Entweder order (aus order) oder marketOutcomeId (aus buy) muss übergeben werden.');
        }
        
        DB::transaction(function () use ($marketId, $outcomeId) {

            // 🔒 Market sperren
            $market = Market::lockForUpdate()->findOrFail($marketId);

            $baseToken = $market->baseToken;
           
            if (!$baseToken) {
                throw new Exception('Market has no base token');
            }

            $baseTokenId = $baseToken->id;

            $decimals = $baseToken->decimals;

            // dd($baseTokenId, $marketId);

            // 📥 Buy Orders laden (OPEN + PARTIAL)
            $buyOrders = MarketLimitOrder::where('market_id', $marketId)
                ->when($outcomeId, fn ($q) => $q->where('outcome_id', $outcomeId))
                ->where('type', strtoupper('buy'))
                ->whereIn('status', ['OPEN', 'PARTIAL'])
                ->orderByDesc('limit_price')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get()
                ->values();

            // dd($buyOrders);

            // 📤 Sell Orders laden (OPEN + PARTIAL)
            $sellOrders = MarketLimitOrder::where('market_id', $marketId)
                ->when($outcomeId, fn ($q) => $q->where('outcome_id', $outcomeId))
                ->where('type', strtoupper('sell'))
                ->whereIn('status', ['OPEN', 'PARTIAL'])
                ->orderBy('limit_price')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get()
                ->values();

            // dd($sellOrders);

            if ($buyOrders->isEmpty() || $sellOrders->isEmpty()) {
                return;
            }

            // 🔑 Outcome → Token Mapping
            $outcomeTokenMap = OutcomeToken::whereIn(
                    'outcome_id',
                    $sellOrders->pluck('outcome_id')->unique()
                )
                ->pluck('token_id', 'outcome_id');

            // dd($outcomeTokenMap);

            // 👛 Wallets vorladen
            $userIds = $buyOrders->pluck('user_id')
                ->merge($sellOrders->pluck('user_id'))
                ->unique();

            // dd($userIds);

            $wallets = Wallet::whereIn('user_id', $userIds)
                ->whereNotNull('parent_wallet_id')
                ->where('type', 'available')
                ->get()
                ->keyBy('user_id');

            // dd($wallets);

            // 🪙 Alle relevanten TokenWallets sperren
            $tokenIds = $outcomeTokenMap->values()->push($baseTokenId)->unique();

            // dd($tokenIds);

            $tokenWallets = TokenWallet::whereIn('wallet_id', $wallets->pluck('id'))
                ->whereIn('token_id', $tokenIds)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn ($tw) => $tw->wallet_id . ':' . $tw->token_id);

            // dd($tokenWallets);

            // 👉 Pointer-basiertes Matching
            $i = 0; // Buy-Pointer
            $j = 0; // Sell-Pointer

            while ($i < $buyOrders->count() && $j < $sellOrders->count()) {

                $buy  = $buyOrders[$i];
                $sell = $sellOrders[$j];

                $buyRemaining  = $buy->share_amount - $buy->filled;
                $sellRemaining = $sell->share_amount - $sell->filled;

                // ❌ Preis passt nicht → Buy kann nie matchen
                if ($buy->limit_price < $sell->limit_price) {
                    $i++;
                    continue;
                }

                // 🔹 Skip vollständig gefüllte Orders
                if ($buyRemaining <= 0) {
                    $i++;
                    continue;
                }
                if ($sellRemaining <= 0) {
                    $j++;
                    continue;
                }

                $shareTokenId = $outcomeTokenMap[$sell->outcome_id] ?? null;
                if (!$shareTokenId) {
                    $j++;
                    continue;
                }

                $buyerWallet  = $wallets[$buy->user_id]  ?? null;
                $sellerWallet = $wallets[$sell->user_id] ?? null;

                if (!$buyerWallet || !$sellerWallet) {
                    throw new Exception('Wallet nicht gefunden');
                }

                $buyerBasePivot   = $tokenWallets[$buyerWallet->id . ':' . $baseTokenId] ?? null;
                $sellerSharePivot = $tokenWallets[$sellerWallet->id . ':' . $shareTokenId] ?? null;

                if (!$buyerBasePivot || !$sellerSharePivot) {
                    // ⚠️ Kein Match möglich, aber Pointer nicht bewegen
                    break;
                }

                $availableForOrder = max($buy->share_amount * $buy->limit_price - $buy->filled * $sell->limit_price, 0);

                // Berechne maximal handelbare Menge
                $tradeShares = min(
                    $buyRemaining,
                    $sellRemaining,
                    $sellerSharePivot->reserved_quantity,
                    intdiv($availableForOrder, $sell->limit_price)
                );

                if ($tradeShares <= 0) {
                    $i++;
                    continue;
                }

                $baseCost = $tradeShares * $sell->limit_price;

                $buy->spent_amount += $baseCost;
                
                // 🔄 Share Token Transfer
                $sellerSharePivot->quantity = max(0, $sellerSharePivot->quantity - $tradeShares);
                $sellerSharePivot->reserved_quantity = max(0, $sellerSharePivot->reserved_quantity - $tradeShares);                
                $sellerSharePivot->quantity_version++;
                $sellerSharePivot->save();

                $buyerShareKey = $buyerWallet->id . ':' . $shareTokenId;
                $buyerSharePivot = $tokenWallets[$buyerShareKey]
                    ??= TokenWallet::create([
                        'wallet_id' => $buyerWallet->id,
                        'token_id' => $shareTokenId,
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'quantity_version' => 0,
                    ]);

                $buyerSharePivot->quantity += $tradeShares;
                $buyerSharePivot->quantity_version++;
                $buyerSharePivot->save();

                // 🔄 Base Token Transfer                
                $buyerBasePivot->quantity = max(0, $buyerBasePivot->quantity - $baseCost);
                $buyerBasePivot->reserved_quantity = max(0, $buyerBasePivot->reserved_quantity - $baseCost);
                $buyerBasePivot->quantity_version++;
                $buyerBasePivot->save();

                $sellerBaseKey = $sellerWallet->id . ':' . $baseTokenId;
                $sellerBasePivot = $tokenWallets[$sellerBaseKey]
                    ??= TokenWallet::create([
                        'wallet_id' => $sellerWallet->id,
                        'token_id' => $baseTokenId,
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'quantity_version' => 0,
                    ]);

                $sellerBasePivot->quantity += $baseCost;
                $sellerBasePivot->quantity_version++;
                $sellerBasePivot->save();

                // 📦 Orders updaten
                $buy->filled  += $tradeShares;
                $sell->filled += $tradeShares;

                $buy->status  = $buy->filled  >= $buy->share_amount  ? 'FILLED' : 'PARTIAL';
                $sell->status = $sell->filled >= $sell->share_amount ? 'FILLED' : 'PARTIAL';

                $buy->save();
                $sell->save();

                $hash = bin2hex(random_bytes(32));

                // 🧾 Transfers
                Transfer::create([
                    'type' => 'internal',
                    'from_wallet_id' => $sellerWallet->id,
                    'to_wallet_id' => $buyerWallet->id,
                    'token_id' => $shareTokenId,
                    'quantity' => $tradeShares,
                    'fee' => 0,
                    'tx_hash' => $hash,
                    'status' => 'completed',
                    'note' => 'LIMIT ORDER: SHARE TOKEN',
                    'receiver_address' => null
                ]);
                
                Transfer::create([
                    'type' => 'internal',
                    'from_wallet_id' => $buyerWallet->id,
                    'to_wallet_id' => $sellerWallet->id,
                    'token_id' => $baseTokenId,
                    'quantity' => $baseCost,
                    'fee' => 0,
                    'tx_hash' => $hash,
                    'status' => 'completed',
                    'note' => 'LIMIT ORDER: BASE TOKEN',
                    'receiver_address' => null
                ]);

                // $realBaseCost =  bcdiv($baseCost, bcpow("10", (string) $decimals, $decimals));

                $realBaseCost = (int) ($baseCost / $tradeShares);

                $denominator = 1000000;
                $numerator   = bcmul((string) $realBaseCost, (string) $denominator);

                MarketTrade::create([
                    'market_id'         => $market->id,
                    'user_id'           => $buy->user_id,
                    'outcome_id'        => $sell->outcome_id,
                    'share_amount'      => $tradeShares,
                    'price_paid'        => 0,
                    'price_numerator'   => $numerator,
                    'price_denominator' => $denominator,                  
                    'tx_type'           => 'BUY',
                    'tx_hash'           => $hash,
                ]);

                MarketTrade::create([
                    'market_id'         => $market->id,
                    'user_id'           => $sell->user_id,
                    'outcome_id'        => $sell->outcome_id,
                    'share_amount'      => $tradeShares,
                    'price_paid'        => 0,
                    'price_numerator'   => $numerator,
                    'price_denominator' => $denominator,                   
                    'tx_type'           => 'SELL',
                    'tx_hash'           => $hash,
                ]);

                // 👉 Pointer nur bei FILLED weiter
                if ($buy->filled >= $buy->share_amount) {
                    $i++;

                    $excess = max(($buy->share_amount * $buy->limit_price) - $buy->spent_amount, 0);

                    if ($excess > 0) {
                        $buyerBasePivot->reserved_quantity -= $excess;
                        $buyerBasePivot->quantity_version++;
                        $buyerBasePivot->save();
                    }                    
                }
                if ($sell->filled >= $sell->share_amount) {
                    $j++;                    
                }
            }
        });
    }

}

