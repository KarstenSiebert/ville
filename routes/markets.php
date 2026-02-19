<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\MarketAdminController;
use App\Http\Controllers\MarketDetailController;

Route::middleware('auth', 'verified')->group(function () {
    Route::resource('markets', MarketController::class)->except(['show']);

    Route::post('/markets/{market}/buy', [MarketController::class, 'buy'])->name('markets.buy');

    Route::post('/markets/{market}/price', [MarketController::class, 'price'])->name('markets.price');    

    Route::post('/markets/{market}/limit-order', [MarketController::class, 'order'])->name('markets.order');

    Route::post('/markets/{market}/orders', [MarketController::class, 'orders'])->name('markets.orders');

    Route::post('/markets/{market}/trades', [MarketDetailController::class, 'trades'])->name('marketdetails.trades');    
    
    Route::get('/markets/{market}/full', [MarketController::class, 'fullData'])->name('markets.full');

    Route::prefix('admin')->group(function () {
        Route::post('/markets/{market}/close', [MarketAdminController::class, 'close'])->name('admin.markets.close');

        Route::post('/markets/{market}/resolve', [MarketAdminController::class, 'resolve'])->name('admin.markets.resolve');
        
        Route::post('/markets/{market}/cancel', [MarketAdminController::class, 'cancel'])->name('admin.markets.cancel');
    });

});

Route::post('/markets/prices', [MarketController::class, 'prices'])->name('markets.prices');
