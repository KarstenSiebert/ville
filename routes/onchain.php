<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OnChainController;

Route::middleware('auth', 'verified')->group(function () {
    Route::resource('onchain', OnChainController::class)->except(['show']);

    Route::post('onchain/create', [OnChainController::class, 'create'])->name('onchain.build');

    Route::post('onchain/confirm', [OnChainController::class, 'confirm'])->name('onchain.confirm');
});