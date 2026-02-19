<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;

Route::middleware('auth', 'verified')->group(function () {
    Route::resource('analytics', AnalyticsController::class);

    Route::post('/analytics/{market}/active', [AnalyticsController::class, 'active'])->name('analytics.active');
});
