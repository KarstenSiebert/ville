<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArchiveController;

Route::middleware('auth', 'verified')->group(function () {
    Route::resource('archives', ArchiveController::class)->except(['show']);
});
