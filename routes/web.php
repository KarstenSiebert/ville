<?php

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cookie;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\DashboardController;
use App\Http\Services\MarketSettlementService;

Route::get('/', function () {    
    return redirect('dashboard');    
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('transactions', [TransferController::class, 'checkTransactions'])->name('transactions');

Route::get('readwallets', [TransferController::class, 'readDepositWallets'])->name('readwallets');

Route::get('sendassets', [TransferController::class, 'readReservedWallets'])->name('sendassets');

Route::post('language', function (Request $request) {

    $locale = strtolower(substr($request->input('locale'), 0, 2));

    if ($request->header('X-User-Locale')) {
        $locale = $request->header('X-User-Locale');
    }

    $locale = strtolower(substr($request->input('locale'), 0, 2));
        
    $supported = ['de', 'zh', 'en', 'es', 'fr', 'jp', 'bg', 'cz', 'dk', 'ee', 'fi', 'gr', 'hr', 'hu', 'ie', 'ir', 'it', 'lt', 'lv', 'mt', 'nl', 'pl', 'pt', 'ro', 'ru', 'sa', 'se', 'sk', 'sl', 'ua'];
    
    if (!in_array($locale, $supported)) {
        $locale = 'en';
    }

    $cookie = null;

    if (isset($locale) && in_array($locale, $supported)) {
        app()->setLocale($locale);

        $cookie = Cookie::make('locale', $locale, 60*24*365, '/', null, false, false);
    }
    
    $request->session()->put('locale', $locale);

    if ($cookie) {        
        return redirect()->back()->withCookie($cookie);
    } 

    return redirect()->back();
});

Route::get('settlemarket', [MarketSettlementService::class, 'settleMarket'])->name('settlemarket');

require __DIR__.'/users.php';
require __DIR__.'/orders.php';
require __DIR__.'/markets.php';
require __DIR__.'/onchain.php';
require __DIR__.'/history.php';
require __DIR__.'/settings.php';
require __DIR__.'/deposits.php';
require __DIR__.'/archives.php';
require __DIR__.'/analytics.php';
require __DIR__.'/publishers.php';
require __DIR__.'/markettrades.php';
require __DIR__.'/marketdetails.php';
require __DIR__.'/reconciliation.php';