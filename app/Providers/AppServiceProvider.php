<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\CouponEventSubscriber::class,
                \App\Console\Commands\CouponIndexSync::class,
                \App\Console\Commands\CouponSystemMonitor::class,
                \App\Console\Commands\CouponSystemSetup::class,
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Schedule recurring tasks
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $schedule->command('coupon:cleanup-expired')->daily();
            $schedule->command('coupon:sync --full')->weekly();
            $schedule->command('coupon:monitor consistency-check')->daily();
            $schedule->command('coupon:monitor maintenance')->weekly();
        });
    }
}
