<?php

namespace App\Console\Commands;

use DB;
use Carbon\Carbon;
use App\Models\Wallet;
use App\Models\TokenWallet;
use Illuminate\Console\Command;
use App\Models\MarketLimitOrder;

class CloseExpiredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'markets:close-expired-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close all open but expired orders';

    /**
     * Execute the console command.
     */
    public function handle()
    {            
        $orders = MarketLimitOrder::where('status', 'OPEN')->where('valid_until', '<', Carbon::now())->get();

        foreach($orders as $order) {
                     
            DB::transaction(function () use ($order) {
            
                $usrWalletId = Wallet::where('user_id', $order->user_id)->where('type', 'available')->whereNull('deleted_at')->value('id');

                if ($usrWalletId) {
                    $baseTokenId = $order->market->baseToken->id;

                    $tokenWallet = TokenWallet::where('wallet_id', $usrWalletId)->where('token_id', $baseTokenId)->where('status', 'active')->first();

                    if ($tokenWallet) {
                        $tokenWallet->reserved_quantity = max($tokenWallet->reserved_quantity - $order->limit_price, 0);
                        $tokenWallet->quantity_version += 1;
                                            
                        $tokenWallet->save();
                    }
                }

                // $order->update(['status' => 'EXPIRED']);
            });
        }
        
        // We let the DB make all the work instead of many single updates in the loop

        MarketLimitOrder::where('status', 'OPEN')->where('valid_until', '<', Carbon::now())->update(['status' => 'EXPIRED']);
        
        $this->info('Closed expired orders.');        
    }
}
