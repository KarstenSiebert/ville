<?php

namespace App\Domain\Orders;

use App\Models\TokenWallet;
use App\Models\MarketLimitOrder;

class OrderReserveService
{
    public static function releaseRemainingReserve(MarketLimitOrder $order, TokenWallet $tokenWallet): void 
    {
        if (empty($tokenWallet)) {
            return;
        }

        $remainingShares = max($order->share_amount - $order->filled, 0);

        if ($remainingShares <= 0) {
            return;
        }

        $isBuy = strtoupper($order->type) === 'BUY';
        
        $reservedToRelease = $isBuy ? (int) ceil($remainingShares * $order->limit_price) : $remainingShares;
        
        $tokenWallet->reserved_quantity = max($tokenWallet->reserved_quantity - $reservedToRelease, 0);

        $tokenWallet->quantity_version++;
        
        $tokenWallet->save();
    }

}
