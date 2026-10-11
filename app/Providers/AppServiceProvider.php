<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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
        Model::preventLazyLoading(! app()->isProduction());

        RateLimiter::for('login', static fn (Request $request): Limit => Limit::perMinute(10)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('id-verification', static fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(60)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
