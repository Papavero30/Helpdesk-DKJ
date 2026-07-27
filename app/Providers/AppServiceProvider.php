<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Queued email notifications use the RateLimited('mail') middleware so the
        // queue worker never bursts past what the configured SMTP host accepts
        // (jobs that would exceed it are released back and retried, not dropped).
        RateLimiter::for('mail', function () {
            return Limit::perSecond(config('mail.rate_limit_per_second', 10));
        });
    }
}
