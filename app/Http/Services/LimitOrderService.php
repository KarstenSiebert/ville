<?php

namespace App\Http\Services;

use DB;
use App\Models\Token;
use App\Models\Market;
use App\Models\Wallet;
use App\Models\Transfer;
use App\Models\MarketTrade;
use App\Models\TokenWallet;
use App\Models\OutcomeToken;
use App\Models\MarketLimitOrder;
use App\Domain\Orders\OrderReserveService;

class LimitOrderService
{
    public function get(Market $market)
    {              
        $outcomes = $market->outcomes()->get();

        $orderTable = [];

        foreach ($outcomes as $outcome) {
     
            $buyOrders = MarketLimitOrder::where('market_id', $market->id)
                ->where('outcome_id', $outcome->id)
                ->where('type', strtoupper('buy'))
                ->where(function ($query) {
                    $query->where('status', 'OPEN')
                        ->orWhere(function ($q) {
                            $q->where('status', 'PARTIAL')
                                ->where('filled', '>=', 0);
                        });
                })
                ->orderByDesc('limit_price')
                ->get(['id', 'limit_price', DB::raw("CASE WHEN status = 'PARTIAL' THEN share_amount - filled ELSE share_amount END as share_amount")                    
                ]);

            $buyPrices = MarketLimitOrder::where('market_id', $market->id)
                ->where('outcome_id', $outcome->id)
                ->where('type', strtoupper('buy'))
                ->where(function($query) {
                    $query->where('status', 'OPEN')
                        ->orWhere(function($query) {
                            $query->where('status', 'PARTIAL')
                                    ->where('filled', '>=', 0);
                          });
                })
                ->whereColumn('share_amount', '>', 'filled')
                ->pluck('limit_price')
                ->toArray();
            
    
            $sellOrders = MarketLimitOrder::where('market_id', $market->id)
                ->where('outcome_id', $outcome->id)
                ->where('type', strtoupper('sell'))
                ->where(function ($query) {
                    $query->where('status', 'OPEN')
                        ->orWhere(function ($q) {
                            $q->where('status', 'PARTIAL')
                                ->where('filled', '>=', 0);
                        });
                })
                ->orderByDesc('limit_price')
                ->get(['id', 'limit_price', DB::raw("CASE WHEN status = 'PARTIAL' THEN share_amount - filled ELSE share_amount END as share_amount")                  
                ]);

            $sellPrices = MarketLimitOrder::where('market_id', $market->id)
                ->where('outcome_id', $outcome->id)
                ->where('type', strtoupper('sell'))
                ->where(function($query) {
                    $query->where('status', 'OPEN')
                          ->orWhere(function($query) {
                              $query->where('status', 'PARTIAL')
                                    ->where('filled', '>=', 0);
                        });
                })
                ->whereColumn('share_amount', '>', 'filled')
                ->pluck('limit_price')
                ->toArray();               

            $allPrices = array_merge($buyPrices, $sellPrices);

            $mid = count($allPrices) > 0 ? round(array_sum($allPrices) / count($allPrices), 2) : 0;

            // dd($buyOrders, $buyPrices, $sellOrders, $sellPrices, $allPrices, $mid);
                        
            $table = [];

            if ($mid) {

                $percentRange = range(10, -10, -1);

                $counter = 0;
            
                foreach ($percentRange as $p) {
                    $m = $p.'%';
                
                    if ($p == 0) {
                        $m = 'Mid';

                         $limit_price = (int) $mid;
                    
                    } else {
                        $limit_price = (int) ($mid * (1 + $counter / 100));
                    }

                    $counter++;
                
                    $table[$p] = ['limit_price' => $limit_price,'buy' => 0, 'p' => $m, 'sell' => 0];
                }

                foreach ($buyOrders as $order) {
                    $percent = round(($order['limit_price'] - $mid) / $mid * 100);
                    
                    $table[$percent]['limit_price'] = $order['limit_price'];                                    

                    if (isset($table[$percent]) && isset($table[$percent]['buy'])) {
                        $table[$percent]['buy'] += $order['share_amount'];
                    }
                }

                foreach ($sellOrders as $order) {
                    $percent = round(($order['limit_price'] - $mid) / $mid * 100);
                
                    $table[$percent]['limit_price'] = $order['limit_price'];

                    if (isset($table[$percent]) && isset($table[$percent]['sell'])) {
                        $table[$percent]['sell'] += $order['share_amount'];                    
                    }
                }                        
            }
                        
            $orderTable[$outcome->id] = array_values(array_slice($table, 0, 21));
       }
        
        return ['orderTable' => $orderTable];
    }
    
    public function orders($publisherId, $marketId, $userId)
    {              
        $orders = MarketLimitOrder::with('market')
            ->where('market_limit_orders.user_id', $userId)
            ->whereHas('market', function ($q) use ($publisherId, $marketId) {
                $q->where('publisher_id', $publisherId)
                ->where('id', $marketId);
            })
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
                    
        return ['orders' => $orders];
    }

    public function create($validated, $publisherId, $marketId, $userId)
    {                  
        $buyAmount = 1;

        $market = Market::findOrFail($marketId);

        $baseToken = $market->baseToken;

        if ($baseToken->fingerprint == 'asset1xhl76ah9cgj4cw8ystsuk5he3ltdr4f85vs3zu') {
            $buyAmount = 1;
            
        } else if ($baseToken->fingerprint == 'asset108xu02ckwrfc8qs9d97mgyh4kn8gdu9w8f5sxk') {
            $buyAmount = 1000;
            
        } else if ($baseToken->fingerprint == 'asset17q7r59zlc3dgw0venc80pdv566q6yguw03f0d9') {
            $buyAmount = 10000;            
        }

        // $buyAmount = 1;

        if ($validated['amount'] < $buyAmount) {
            return ['error' => 'Amount is too low.'];
        }
        
        $price = Intval(bcmul($validated['price'] * $buyAmount, bcpow("10", (string) $baseToken->decimals)));

        $priceProShareInt = (int) ceil($price / $buyAmount);
        
        if ($market->status !== 'OPEN') {
            return ['error' => 'Market is not open for trading.'];
        }
        
        $baseTokenId = $baseToken->id;

        $isBuy = strtoupper($validated['side']) === 'BUY';
      
        $tokenId = $isBuy ? $baseTokenId : OutcomeToken::where('outcome_id', $validated['outcome_id'])->value('token_id');
                
        $limitOrder = DB::transaction(function () use ($userId, $tokenId, $baseTokenId, $marketId, $validated, $buyAmount, $priceProShareInt, $isBuy) {
            
            $usrWallet = Wallet::where('user_id', $userId)->where('type', 'available')->whereNull('deleted_at')->firstOrFail();        
            
            $tokenWallet = TokenWallet::where('wallet_id', $usrWallet->id)->where('token_id', $tokenId)->where('status', 'active')->first();
        
            if (empty($tokenWallet)) {
                throw new \Exception('No wallet with that token');
            }

            $available = max($tokenWallet->quantity - $tokenWallet->reserved_quantity, 0);
            
            $reserveAmount = $isBuy ? $priceProShareInt * $validated['amount'] : $validated['amount'];

            if ($available < $reserveAmount) {
                throw new \Exception('Insufficient available balance');
            }
            
            $tokenWallet->reserved_quantity += $reserveAmount;
            $tokenWallet->quantity_version  += 1;
                    
            $tokenWallet->save();
                    
            $limitOrder = new MarketLimitOrder();

            $limitOrder->user_id       = $userId;
            $limitOrder->market_id     = $marketId;
            $limitOrder->outcome_id    = $validated['outcome_id'];
            $limitOrder->base_token_id = $baseTokenId;
            $limitOrder->type          = strtoupper($validated['side']);
            $limitOrder->limit_price   = $priceProShareInt ? $priceProShareInt : $validated['price'];
            $limitOrder->share_amount  = $validated['amount'];            
            $limitOrder->status        = 'OPEN';
            $limitOrder->valid_until   = $validated['expire_date'];
                                    
            $limitOrder->save();

            return $limitOrder;
        });        
        
        return ['orders' => $limitOrder->id ?? null];
    }

    public function cancel($publisherId, $userId, $marketId, $orderId)
    {              
        $order = MarketLimitOrder::with('market.baseToken')
            ->where('market_limit_orders.id', $orderId)
            ->where('market_limit_orders.user_id', $userId)
            ->whereHas('market', function ($q) use ($publisherId, $marketId) {
                $q->where('publisher_id', $publisherId)
                ->where('id', $marketId);
            })
            ->first();
      
        if (!empty($order)) {
            
            DB::transaction(function () use ($order) {
            
                $usrWalletId = Wallet::where('user_id', $order->user_id)->where('type', 'available')->whereNull('deleted_at')->value('id');

                if ($order->market && $order->market->baseToken) {
                    $baseTokenId = $order->market->baseToken->id;
                
                } else {                
                    $baseTokenId = $order->base_token_id ?? null;
                }

                $isBuy = strtoupper($order->type) === 'BUY';
                      
                $tokenId = $isBuy ? Token::withTrashed()->find($baseTokenId)?->id : OutcomeToken::withTrashed()->where('outcome_id', $order->outcome_id)->value('token_id');

                $tokenWallet = TokenWallet::where('wallet_id', $usrWalletId)->where('token_id', $tokenId)->where('status', 'active')->first();
                                
                if (!empty($tokenWallet) && in_array($order->status, ['OPEN', 'PARTIAL', 'CANCELED', 'EXPIRED'])) {
                    OrderReserveService::releaseRemainingReserve($order, $tokenWallet);
                }
                
                $order->delete();
            });

            return ['success' => 'Canceled order '.$order->id];
        }
 
        return ['error' => 'order_not_found'];
    }
    
    public function buy($validated, $publisherId, $marketId, $userId)
    {
        $buyAmount = $validated['buy_amount'];

        $outcomeId = $validated['outcome_id'];

        $price = $validated['price'];

        $market = Market::findOrFail($marketId);

        $baseToken = $market->baseToken;

        $decimals  = $baseToken->decimals;
    
        $marketWallet = Wallet::find($market->wallet_id); 

        if (($buyAmount < $market->min_trade_amount) || ($buyAmount > $market->max_trade_amount)) {

            $liquidity = app(OutcomeService::class)->getLiquidity($market);
            
            return ['liquidity' => $liquidity,  'price' => [], 'outcomes' => []];
        }    
        
        if (empty($market)) {            
            $liquidity = app(OutcomeService::class)->getLiquidity($market);

            return ['liquidity' => $liquidity,  'price' => [], 'outcomes' => []];
        }
               
        $currentPriceFloat = round($this->getCurrentOutcomePrice($market, $outcomeId, $buyAmount), $decimals);

        $currentPriceInt = (int) ceil($currentPriceFloat * pow(10, $decimals));

        $priceFloat = $price;
        
        $priceInt = (int) ceil($priceFloat * pow(10, $decimals));
        
        // Price changed, we currently fo not have slippage

        if ($priceInt < $currentPriceInt) {            
            $q = $this->getOutcomeQuantities($market); 

            $liquidity = app(OutcomeService::class)->getLiquidity($market);
            
            $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);
            
            return ['liquidity' => $liquidity, 'price' => $calc, 'outcomes' => $q];

        } else {
            // $priceInt = $currentPriceInt;            
        }   
        
        $userWallet  = Wallet::where('user_id', $userId)->where('type', 'available')->first();

        $adminWallet = Wallet::where('user_id', 1)->where('type', 'available')->first(); 

        if (empty($marketWallet) || empty($userWallet) || empty($adminWallet)) {
            $q = $this->getOutcomeQuantities($market);
            
            $liquidity = app(OutcomeService::class)->getLiquidity($market);

            $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);            

            return ['liquidity' => $liquidity, 'price' => $calc, 'outcomes' =>$q];
        }

        $userTokenWallet = TokenWallet::firstOrCreate(
            ['wallet_id' => $userWallet->id, 'token_id' => $baseToken->id],
            ['quantity' => 0, 'reserved_quantity' => 0]
        );

        $available = $userTokenWallet->quantity - $userTokenWallet->reserved_quantity;
        
        if ($available < $priceInt) {
            $q = $this->getOutcomeQuantities($market); 

            $liquidity = app(OutcomeService::class)->getLiquidity($market);
            
            $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);

            return ['liquidity' => $liquidity, 'price' => $calc, 'outcomes' =>$q];
        }

        $outcome = $market->outcomes()->with('outcomeToken.token')->find($outcomeId);

        if (empty($outcome)) {
            $q = $this->getOutcomeQuantities($market);

            $liquidity = app(OutcomeService::class)->getLiquidity($market);
            
            $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);

            return ['liquidity' => $liquidity, 'price' => $calc, 'outcomes' => $q];
        }

        $token = $outcome?->outcomeToken?->token;

        DB::transaction(function () use ($userWallet, $marketWallet, $adminWallet, $baseToken, $priceInt, $token, $buyAmount, $marketId, $outcomeId) {
                                
            Transfer::execute($userWallet, $marketWallet, $baseToken, $priceInt, 'internal', 0, 'PBUY', false);

            Transfer::execute(null, $marketWallet, $token, $buyAmount, 'internal', 0, 'PBUY', false);

            Transfer::execute($marketWallet, $userWallet, $token, $buyAmount, 'internal', 0, 'PBUY', false);

            $denominator = 1000000;                        
            $numerator   = (int) (($denominator * $priceInt) / $buyAmount);

            $tradeData = [
                'market_id'         => $marketId,
                'user_id'           => $userWallet->user_id,
                'outcome_id'        => $outcomeId,
                'share_amount'      => $buyAmount,
                'price_paid'        => 0,
                'price_numerator'   => $numerator,
                'price_denominator' => $denominator,
                'tx_type'           => 'BUY'
            ];

            MarketTrade::create($tradeData);
        });
        
        $q = $this->getOutcomeQuantities($market); 

        $liquidity = app(OutcomeService::class)->getLiquidity($market);
             
        // The price has changed. We check the orderbook for open orders.

        app(LimitOrderMatchingService::class)->match(marketOutcomeId: $outcomeId);

        // Return all updated data

        $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);

        $outcomes = app(OutcomeService::class)->get($market);
            
        return ['liquidity' => $liquidity, 'price' => $calc, 'outcomes' => $outcomes];
    }
    
    private function getCurrentOutcomePrice($market, $outcomeId, $buyAmount)
    {
        $calc = app(OutcomeService::class)->calculateLmsrPrice($market, $outcomeId, $buyAmount);        

        return $calc['price'];
    }

    public function getOutcomeQuantities(Market $market): array
    {
        $outcomes = $market->outcomes()
            ->with(['outcomeToken.tokenWallet' => function ($q) use ($market) {
                $q->where('wallet_id', $market->wallet_id);
            }])
            ->get();
                
        $q = [];

        foreach ($outcomes as $outcome) {
            $tokenId = $outcome->outcomeToken->token_id;
        
            $q[$outcome->id] = OutcomeToken::query()
                ->join('token_wallet', 'outcome_tokens.token_id', '=', 'token_wallet.token_id')
                ->where('outcome_tokens.token_id', $tokenId)
                ->where('token_wallet.status', 'active')
                ->sum('token_wallet.quantity');
        }
        
        return $q;
    }

}