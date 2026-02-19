<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->scheduleCommands();
    }

    protected function scheduleCommands(): void
    {
        $schedule = $this->app->make(Schedule::class);

        $schedule->command('markets:close-expired-orders')->everyMinute();

        $schedule->command('queue:work --stop-when-empty')->everyMinute();

        $schedule->command('markets:close-expired-markets')->hourly();

        $schedule->command('cache:clear')->dailyAt('00:10');
    }
}
