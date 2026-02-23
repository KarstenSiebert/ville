<?php

use Illuminate\Http\Request;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use App\Http\Middleware\VerifyPublisher;
use App\Exceptions\WrongMethodException;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\VerifyMobileClient;
use App\Http\Middleware\HandleInertiaRequests;
use Spatie\Permission\Middleware\RoleMiddleware;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['locale', 'appearance', 'sidebar_state']);

        $middleware->web(append: [
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->group('api', [
            // EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,             
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,            
            'verify.publisher' => VerifyPublisher::class,
            'verify.mobileclient' => VerifyMobileClient::class,
        ]);
        
        $middleware->validateCsrfTokens(except: [
            'https://www.tokenville.fun/api/*',
            'https://www.accesspay.net/api/*',
        ]);     
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //    
    })->create();
