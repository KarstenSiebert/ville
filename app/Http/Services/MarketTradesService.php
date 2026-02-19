<?php

namespace App\Http\Services;

use DB;
use Carbon\Carbon;
use App\Models\Market;
use App\Http\Services\OutcomeService;

class MarketTradesService
{    
    public function get(Market $market, ?string $startDate, string $range = '1H')
    {
        $marketId = $market->id ?? 0;

        $data = $this->getMarketChartData($marketId, $startDate, $range);

        $tradesGrouped = $data->groupBy('time_bucket');

        $outcomeService = app(OutcomeService::class);

        $trades = $tradesGrouped->map(function ($rows, $time) use ($outcomeService, $market) {

            $opinions = [];
        
            $processed = $rows->map(function ($r) use ($outcomeService, $market, &$opinions) {
                $outcomeId = $r->outcome_id;

                $calc = $outcomeService->calculateLmsrPrice($market, $outcomeId, $r->volume, 1.0);

                $opinions[$outcomeId] = $calc['after_probs'];

                return [
                    'time'           => $r->time_bucket,
                    'price'          => round($r->price, 4),
                    'market_opinion' => $calc['after_probs'],
                    'volume'         => (int) $r->volume,
                    'outcome_id'     => $outcomeId,
                ];
            });
        
            $sum = array_sum($opinions);

            if ($sum > 1.0) {
                $processed = $processed->map(function ($item) use ($sum) {
                    $item['market_opinion'] = round($item['market_opinion'] / $sum, 4);
                
                    return $item;
                });
            }

            return $processed;
        });
    
        $tradesByOutcome = [];
    
        foreach ($trades as $bucket) {
    
            foreach ($bucket as $trade) {
                $outcomeId = $trade['outcome_id'];
            
                if (!isset($tradesByOutcome[$outcomeId])) {
                    $tradesByOutcome[$outcomeId] = [];
                }
            
                $tradesByOutcome[$outcomeId][] = [
                    'outcome_id'     => $outcomeId,
                    'time'           => $trade['time'],
                    'price'          => $trade['price'],
                    'market_opinion' => $trade['market_opinion'],
                    'volume'         => $trade['volume'],
                ];
            }
        }

        return [
            'range'  => $range,
            'trades' => $tradesByOutcome
        ];
    }

    private function getMarketChartData(int $marketId, ?string $startDate = null, string $range = '1H')
    {   
        $resolution = match ($range) {
            '1H' => 'minute',
            '6H' => 'minute',
            '1D' => 'hour',
            '1W' => 'hour',
            '1M' => 'day',
            'ALL' => 'day',
            default => 'hour'
        };

        $from = match ($range) {
            '1H'  => now()->subHour(),
            '6H'  => now()->subHours(6),            
            '1D'  => now()->subHours(24),            
            '1W'  => now()->subWeek(),
            '1M'  => now()->subMonth(),
            'ALL' => null,
            default => now()->subHour(),
        };

        $query = DB::table('market_trades')->select(['market_trades.outcome_id',
                    DB::raw("DATE_TRUNC('$resolution', market_trades.created_at) as time_bucket"),
                    DB::raw('AVG(market_trades.price_paid::float / market_trades.share_amount) as price'),
                    DB::raw('SUM(market_trades.share_amount) as volume'),
                    DB::raw('SUM(market_trades.share_amount * (market_trades.price_paid::float / market_trades.share_amount)) / SUM(market_trades.share_amount) as market_opinion')
            ])
            ->where('market_trades.market_id', $marketId)
            ->whereIn('market_trades.tx_type', ['BUY', 'SELL']);

        if ($startDate) {
            // $query->where('market_trades.created_at', '>=', $startDate);            

            $startDateBuffered = Carbon::parse($startDate)->subMinute();
  
            $query->where('market_trades.created_at', '>=', $startDateBuffered);

        } else if ($from) {
            $query->where('market_trades.created_at', '>=', $from);
        }
                
        return $query->groupBy('time_bucket', 'market_trades.outcome_id')
                     ->orderBy('time_bucket')
                     ->get();
    }

}
