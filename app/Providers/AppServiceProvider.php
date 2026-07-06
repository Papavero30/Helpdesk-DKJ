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
        // Resend free tier allows max 2 requests/second. Queued email notifications
        // use the RateLimited('resend') middleware so the queue worker never bursts
        // past this limit (jobs that would exceed it are released back and retried).
        RateLimiter::for('resend', function () {
            return Limit::perSecond(2);
        });
    }
}
