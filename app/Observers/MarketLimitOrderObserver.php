<?php

namespace App\Observers;

use App\Models\MarketLimitOrder;
use App\Jobs\MatchLimitOrderJob;
use Illuminate\Support\Facades\DB;

class MarketLimitOrderObserver
{
    /**
     * Handle the MarketLimitOrder "created" event.
     */
    public function created(MarketLimitOrder $marketLimitOrder): void
    {
        DB::afterCommit(function () use ($marketLimitOrder) {            
            (new MatchLimitOrderJob($marketLimitOrder))->handle();

            // später einfach: MatchLimitOrderJob::dispatch($order)->afterCommit();
        });
    }
     
    /**
     * Handle the MarketLimitOrder "updated" event.
     */
    public function updated(MarketLimitOrder $marketLimitOrder): void
    {
        //
    }

    /**
     * Handle the MarketLimitOrder "deleted" event.
     */
    public function deleted(MarketLimitOrder $marketLimitOrder): void
    {
        //
    }

    /**
     * Handle the MarketLimitOrder "restored" event.
     */
    public function restored(MarketLimitOrder $marketLimitOrder): void
    {
        //
    }

    /**
     * Handle the MarketLimitOrder "force deleted" event.
     */
    public function forceDeleted(MarketLimitOrder $marketLimitOrder): void
    {
        //
    }
}
