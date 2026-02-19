<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarketDetailController;

Route::middleware('auth', 'verified')->group(function () {
    Route::resource('marketdetails', MarketDetailController::class)->except(['show']);  

     Route::get('/marketdetails/{market}', [MarketDetailController::class, 'index'])->name('marketdetails.index');  
});
