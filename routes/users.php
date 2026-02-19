<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::middleware('auth', 'verified')->group(function () {
    Route::resource('users', UserController::class)->except(['show']);    

    Route::get('users/outgoings', [UserController::class, 'outgoings'])->name('users.outgoings');
});