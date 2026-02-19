<?php

namespace App\Http\Services;

use DB;
use Carbon\Carbon;
use App\Models\Market;
use App\Http\Services\OutcomeService;

class AnalyticsService
{    
    public function get(Market $market, ?string $startDate, string $range = '1H')
    {
        $marketId = $market->id ?? 0;
    
        $data = $this->getActiveUsersChartData($marketId, $startDate, $range);
        
        $usersGrouped = $data->groupBy('time_bucket');
        
        $activeUsers = $data->map(function($row) {
            return [
                'time' => $row->time_bucket,
                'active_users' => (int) $row->active_users,
            ];
        })->values();
            
        return [
            'range' => $range,
            'users' => $activeUsers
        ];
    }

    private function getActiveUsersChartData(int $marketId, ?string $startDate = null, string $range = '1H')
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

        $query = DB::table('market_trades')->select([
                    DB::raw("DATE_TRUNC('$resolution', market_trades.created_at) as time_bucket"),
                    DB::raw('COUNT(DISTINCT market_trades.user_id) as active_users')
                ])
                ->where('market_trades.market_id', $marketId)
                ->whereIn('market_trades.tx_type', ['BUY', 'SELL'])
                ->whereNotNull('market_trades.user_id');

        if ($startDate) {
            $startDateBuffered = Carbon::parse($startDate)->subMinute();

            $query->where('market_trades.created_at', '>=', $startDateBuffered);
    
            } else if ($from) {
                $query->where('market_trades.created_at', '>=', $from);
            }

        return $query->groupBy('time_bucket')
                 ->orderBy('time_bucket')
                 ->get();
    }

}
