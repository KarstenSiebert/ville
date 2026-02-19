<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DepositController;

Route::middleware('auth', 'verified')->group(function () {
    Route::resource('deposits', DepositController::class)->except(['show']);

    Route::post('/deposits/create', [DepositController::class, 'create'])->name('deposits.build');    

    Route::get('/deposits/publisher/{publisher}', [DepositController::class, 'index'])->name('deposits.publisher');

    Route::post('/deposits/publisher/{publisher}', [DepositController::class, 'store'])->name('deposits.publisher.store');

    Route::post('/deposits/publisher/create/{publisher}', [DepositController::class, 'create'])->name('deposits.publisher.build');

    Route::get('/deposits/publisher/create/{publisher}', [DepositController::class, 'index'])->name('deposits.publisher.index');
});