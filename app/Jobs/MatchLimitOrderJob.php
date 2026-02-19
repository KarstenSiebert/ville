<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\MarketLimitOrder;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\LimitOrderMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Services\LimitOrderMatchingService as MatchingService;

class MatchLimitOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public MarketLimitOrder $order)
    {
        //
    }

    public function handle()
    {
        // \App\Http\Services\LimitOrderMatchingService::match($this->order);
        MatchingService::match(order: $this->order);
    }

}