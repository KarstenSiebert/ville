<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarketLimitOrderController;

Route::middleware('auth', 'verified')->group(function () {
    Route::resource('orders', MarketLimitOrderController::class)->except(['show']);

    Route::get('/markets/{market}/orderbook', [MarketLimitOrderController::class, 'orderbook'])->name('markets.orderbook');;
});
