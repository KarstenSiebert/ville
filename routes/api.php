<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cookie;
use App\Http\Controllers\MarketController;
use App\Http\Services\MarketSettlementService;
use App\Http\Controllers\Api\ApiUserController;
use App\Http\Controllers\MarketDetailController;
use App\Http\Controllers\Api\ApiOrderController;
use App\Http\Controllers\Api\ApiMarketController;
use App\Http\Controllers\Api\ApiPublisherController;
use App\Http\Controllers\Api\ApiMobileClientController;

Route::middleware(['verify.publisher', 'throttle:publisher-api'])->group(function () {
    
    // Get the wallet and token values of a publisher
    Route::get('/operators/wallet', [ApiPublisherController::class, 'wallet'])->name('api.operators.wallet');
    Route::post('/operators/wallet', function () { return response()->json(null, 405); });

    // Transfer funds to user
    Route::post('/operators/transfer', [ApiPublisherController::class, 'transfer'])->name('api.operators.transfer');
    Route::get('/operators/transfer', function () { return response()->json(null, 405); });

    // Chargeback funds from user
    Route::post('/operators/chargeback', [ApiPublisherController::class, 'chargeback'])->name('api.operators.chargeback');
    Route::get('/operators/chargeback', function () { return response()->json(null, 405); });

    // Get all markets of an operator
    Route::get('/markets', [ApiMarketController::class, 'index'])->name('api.markets');
    
    // Store a market of an operator
    Route::post('/markets', [ApiMarketController::class, 'store'])->name('api.markets.store');

    // Get one specific market of an operator
    Route::get('/markets/{id}', [ApiMarketController::class, 'market'])->name('api.markets.market');
    Route::post('/markets/{id}', function () { return response()->json(null, 405); })->where('id', '[0-9]+');
    
    // Cancel one specific market of an operator
    Route::post('/markets/{id}/cancel', [ApiMarketController::class, 'cancel'])->name('api.markets.cancel');
    Route::get('/markets/{id}/cancel', function () { return response()->json(null, 405); })->where('id', '[0-9]+');

    // Resolve one specific market of an operator
    Route::post('/markets/{id}/resolve', [ApiMarketController::class, 'resolve'])->name('api.markets.resolve');
    Route::get('/markets/{id}/resolve', function () { return response()->json(null, 405); })->where('id', '[0-9]+');

    // Place prediction orders of a user of an operator
    Route::post('/markets/{id}/full', [ApiMarketController::class, 'full'])->name('api.markets.full');
    Route::get('/markets/{id}/full', function () { return response()->json(null, 405); })->where('id', '[0-9]+');

    // Get all users of a market, or a specific user
    Route::get('/users', [ApiUserController::class, 'index'])->name('api.users');
    Route::post('/users', function () { return response()->json(null, 405); });

    // Get the wallet and token values of a user of an operator
    Route::get('/users/wallet', [ApiUserController::class, 'wallet'])->name('api.users.wallet');
    Route::post('/users/wallet', function () { return response()->json(null, 405); });

    // Get the orders of a user of an operator
    Route::get('/orders', [ApiOrderController::class, 'index'])->name('api.orders');
    
    // Place the orders of a user of an operator
    Route::post('/orders', [ApiOrderController::class, 'store'])->name('api.orders.store');

    // Get one specific order of a user of an operator
    Route::get('/orders/{id}', [ApiOrderController::class, 'order'])->name('api.orders.order');
    Route::post('/orders/{id}', function () { return response()->json(null, 405); })->where('id', '[0-9]+');

    // Cancel one specific user orders of an operator
    Route::post('/orders/{id}/cancel', [ApiOrderController::class, 'cancel'])->name('api.orders.cancel');
    Route::get('/orders/{id}/cancel', function () { return response()->json(null, 405); })->where('id', '[0-9]+');

    // Place prediction orders of a user of an operator
    Route::post('/orders/buy', [ApiOrderController::class, 'buy'])->name('api.orders.buy');
    Route::get('/orders/buy', function () { return response()->json(null, 405); });

    // Place prediction orders of a user of an operator
    Route::post('/orders/price', [ApiOrderController::class, 'price'])->name('api.orders.price');
    Route::get('/orders/price', function () { return response()->json(null, 405); });

    Route::get('/markets/{id}/winners',  [MarketSettlementService::class, 'winners'])->name('api.markets.winners');
});

Route::middleware(['verify.mobileclient', 'throttle:mobileclient-api'])->group(function () {

    Route::post('/webview/{market}', [ApiMobileClientController::class, 'webview'])->name('api.webview');

    Route::post('/deposit/{market}', [ApiMobileClientController::class, 'deposit'])->name('api.deposit');
       
});






