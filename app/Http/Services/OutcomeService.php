<?php

namespace App\Http\Services;

use Carbon\Carbon;
use App\Models\Market;
use App\Models\Wallet;
use App\Models\OutcomeToken;

class OutcomeService
{
    public function get(Market $market, array $inputAmounts = []): array
    {        
        $result = [];

        $prices = [];
        
        $q = $this->getOutcomeQuantities($market);

        foreach ($market->outcomes as $outcome) {
            $buyAmount = isset($inputAmounts[$outcome->id]) ? (int)$inputAmounts[$outcome->id] : 0;
                             
            $calc = $this->calculateLmsrPrice($market, $outcome->id, $buyAmount);

            $prices[$outcome->id] = $calc;
        }        
                
        $result = ['liquidity' => $this->getLiquidity($market), 'b' => $market->b, 'prices' => $prices, 'outcomes' => $q];
        
        return $result;
    }
   
    public function calculateLmsrPrice(Market $market, int $outcomeId, int $buyAmount = 0): array
    {
        $baseToken = $market->baseToken;
        $decimals  = $baseToken->decimals;

        if ($buyAmount == 0) {

            if ($baseToken->fingerprint == 'asset1xhl76ah9cgj4cw8ystsuk5he3ltdr4f85vs3zu') {
                $buyAmount = 1;
            
            } else if ($baseToken->fingerprint == 'asset108xu02ckwrfc8qs9d97mgyh4kn8gdu9w8f5sxk') {
                $buyAmount = 1000;
            
            } else if ($baseToken->fingerprint == 'asset17q7r59zlc3dgw0venc80pdv566q6yguw03f0d9') {
                $buyAmount = 10000;
            
            } else {                
                $buyAmount = 1;
            } 
        }
        
        $qReal = $this->getOutcomeQuantities($market);
        
        $buyShare = (float) $buyAmount;
        
        $liquidity = $this->getLiquidity($market);
        
        $totalShares = array_sum($qReal);
        
        $maxQ = max(array_map(fn($id) => $id == $outcomeId ? $qReal[$id] + $buyShare : $qReal[$id], array_keys($qReal)));
        
        $minBPrice = max(1.0, $buyShare / 20.0);
        
        $bPrice = max($totalShares * 0.15, $minBPrice);
        
        $qNormalized = array_map(fn($q) => (float) $q, $qReal);

        $sumBefore = 0.0;
        $sumAfter  = 0.0;

        foreach ($qNormalized as $id => $qty) {            
            $qtyAfter = ($id == $outcomeId) ? $qty + $buyShare : $qty;

            $sumBefore += exp(($qty - $maxQ) / $bPrice);
            $sumAfter  += exp(($qtyAfter - $maxQ) / $bPrice);
        }

        $logSumBefore = $maxQ / $bPrice + log($sumBefore);
        $logSumAfter  = $maxQ / $bPrice + log($sumAfter);

        $priceReal = $bPrice * ($logSumAfter - $logSumBefore);
        
        // $bProb = max($liquidityNormalized * 0.15, 1.0);

        $lmsrLiquidityFactor = 0.005;

        $bProb = max($liquidity * $lmsrLiquidityFactor, 1.0);

        $probsBefore = $this->calculateLmsrProbabilities($qNormalized, $bProb);

        $qAfter = $qNormalized;

        $qAfter[$outcomeId] += $buyShare;

        $probsAfter = $this->calculateLmsrProbabilities($qAfter, $bProb);
        
        if ($decimals == 0) {
            $priceDisplay = max(ceil($priceReal), 1);

        } else {
            $priceDisplay = round($priceReal, $decimals);
        }        

        $chance = ($probsBefore[$outcomeId] > 0) ? $priceDisplay / $probsBefore[$outcomeId] : 1;        

        // $totalValue = max($buyAmount * $priceDisplay, $baseToken->min_price);

        $totalValue = max($priceDisplay, $baseToken->min_price);
        
        return [                        
            'price' => $priceDisplay,
            'realPrice' => $priceReal,
            'min_price' => $baseToken->min_price ?? 1,
            'total_value' => $totalValue,
            'amount' => $buyAmount,            
            'before_probs' => $probsBefore[$outcomeId],
            'after_probs'  => $probsAfter[$outcomeId],
            'chance' => round($chance, 6)            
        ];
    }
    
    private function calculateLmsrProbabilities(array $q, float $b): array
    {
        $maxQ = max($q);

        $exp = [];
        $sum = 0;

        foreach ($q as $id => $qty) {
            $exp[$id] = exp(($qty - $maxQ) / $b);
            $sum += $exp[$id];
        }

        $probs = [];

        foreach ($exp as $id => $value) {
            $probs[$id] = $value / $sum;
        }

        return $probs;
    }
    
    private function getOutcomeQuantities(Market $market): array
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

    public function prices($marketIds): array
    {
        $markets = Market::with(['baseToken','outcomes.outcomeToken'])
            ->whereIn('id', $marketIds)
            ->get();

        $result = [];

        foreach ($markets as $market) {
            $prices = [];            
        
            $q = $this->getOutcomeQuantities($market);
            
            foreach ($market->outcomes as $outcome) {
                $prices[$outcome->id] = $this->calculateLmsrPrice($market, $outcome->id, 0);
            }

            $result[$market->id] = [
                'liquidity' => $this->getLiquidity($market),
                'b' => $market->b,
                'prices' => $prices,
                'outcomes' => $q
            ];
        }

        return $result;
    }

    public function getLiquidity(Market $market) : int
    {
        $baseToken = $market->baseToken;
            
        $marketWallet = Wallet::findOrFail($market->wallet_id);

        $baseTokenWallet = $marketWallet->tokenWallets->firstWhere('token_id', $baseToken->id);
        
        // Liquidity is the real value (not the token value including decimals)

        $liquidity = bcdiv($baseTokenWallet?->quantity, bcpow("10", (string) $baseToken->decimals, 0), $baseToken->decimals);

        return $liquidity;
    }

}