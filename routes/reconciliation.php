<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WalletReconciliationController;

Route::middleware('auth', 'verified')->group(function () {
    Route::resource('reconciliation', WalletReconciliationController::class);  
});