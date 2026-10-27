<?php

namespace App\Providers;

use App\Services\NeonPostgresConnector;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\PostgresConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        DB::extend('pgsql', static function (array $config, string $name): PostgresConnection {
            $connector = new NeonPostgresConnector;
            $config['name'] = $name;
            $config['prefix'] ??= '';

            return new PostgresConnection(
                $connector->connect($config),
                $config['database'],
                $config['prefix'] ?? '',
                $config
            );
        });

        Model::preventLazyLoading(! app()->isProduction());

        RateLimiter::for('login', static fn (Request $request): Limit => Limit::perMinute(10)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('id-verification', static fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(60)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('rrs-chat', static fn (Request $request): Limit => Limit::perMinute(10)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
