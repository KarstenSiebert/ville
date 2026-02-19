<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarketTradeController;

Route::middleware('auth', 'verified')->group(function () {
    Route::resource('trades', MarketTradeController::class)->except(['show']);
});
