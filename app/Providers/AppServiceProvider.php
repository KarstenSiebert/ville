<?php

namespace App\Providers;

use Auth;
use Session;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Family;
use Illuminate\Http\Request;
use App\Models\MarketLimitOrder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use App\Observers\MarketLimitOrderObserver;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
         Inertia::share([
            'flash' => function () {
                return [
                    'success' => Session::get('success'),
                    'error' => Session::get('error'),
                ];
            },

            'jsPermissions' => function () {
                $user = Auth::user();
        
                if (!$user) {
                    return ['roles' => [], 'permissions' => []];
                }

                $roles = $user->roles->pluck('name')->toArray();
                $permissions = $user->getAllPermissions()->pluck('name')->toArray();

                return [
                    'roles' => $roles,
                    'permissions' => $permissions,
                ];
            },
            
            'locale' => fn() => app()->getLocale(),
        ]);
        
        RateLimiter::for('api', function (Request $request) {
            return [
                Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()),
                // Limit::perMinute(10)->by($request->input('email')),
            ];
        });   

        RateLimiter::for('publisher-api', function (Request $request) {
            $operatorApiKey = $request->header('X-KEY');

            return [
                Limit::perMinute(60)->by($operatorApiKey ?: $request->ip()),
            ];
        });

        RateLimiter::for('mobileclient-api', function (Request $request) {
            $operatorApiKey = $request->header('X-KEY');

            return [
                Limit::perMinute(60)->by($operatorApiKey ?: $request->ip()),
            ];
        });

        Gate::define('admin', fn (User $user) => $user->hasRole('admin'));      
        
        MarketLimitOrder::observe(MarketLimitOrderObserver::class);
    }
    
}
