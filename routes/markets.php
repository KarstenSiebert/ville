<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\MarketAdminController;
use App\Http\Controllers\MarketDetailController;
use App\Http\Controllers\Api\ApiMobileClientController;

Route::middleware('auth', 'verified')->group(function () {
    Route::resource('markets', MarketController::class)->except(['show']);

    Route::post('/markets/{market}/buy', [MarketController::class, 'buy'])->name('markets.buy');

    Route::post('/markets/{market}/price', [MarketController::class, 'price'])->name('markets.price');    

    Route::post('/markets/{market}/limit-order', [MarketController::class, 'order'])->name('markets.order');

    Route::post('/markets/{market}/orders', [MarketController::class, 'orders'])->name('markets.orders');
    
    Route::post('/markets/{market}/trades', [MarketDetailController::class, 'trades'])->name('marketdetails.trades');    
    
    Route::get('/markets/{market}/full', [MarketController::class, 'fullData'])->name('markets.full');

    Route::get('/markets/{market}/qrcode', [MarketController::class, 'qrcode'])->name('markets.qrcode');

    Route::prefix('admin')->group(function () {
        Route::post('/markets/{market}/close', [MarketAdminController::class, 'close'])->name('admin.markets.close');

        Route::post('/markets/{market}/resolve', [MarketAdminController::class, 'resolve'])->name('admin.markets.resolve');
        
        Route::post('/markets/{market}/cancel', [MarketAdminController::class, 'cancel'])->name('admin.markets.cancel');
    });

});

Route::get('/clients/{market}', [ApiMobileClientController::class, 'detail'])->name('clients.detail');

Route::get('webview-login', function (Request $request) {

 \Log::error('WEBVIEW DEBUG', [
        'full_url' => $request->fullUrl(),
        'path' => $request->path(),
        'route_name' => optional($request->route())->getName(),
        'middleware' => optional($request->route())->gatherMiddleware(),
        'user_agent' => $request->userAgent(),
    ]);


        if (! $request->hasValidSignature()) {
             abort(403);
        }

        $user = \App\Models\User::findOrFail($request->user);
                
        Auth::login($user, true);

        if ($request->user != $user->id) {
           abort(403);
        }

        $id = $request->market;
        
        return redirect('clients/'.$id);

})->name('webview.login');
   
Route::post('/markets/prices', [MarketController::class, 'prices'])->name('markets.prices');
