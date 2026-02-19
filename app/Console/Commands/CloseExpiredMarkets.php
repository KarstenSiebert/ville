<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Market;
use Illuminate\Console\Command;

class CloseExpiredMarkets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'markets:close-expired-markets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close all open but expired markets';

    /**
     * Execute the console command.
     */
    public function handle()
    {    
        Market::where('status', 'OPEN')->where('close_time', '<', Carbon::now())->update(['status' => 'CLOSED']);
        
        $this->info('Closed expired markets.');        
    }
}
