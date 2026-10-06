<?php

namespace Vipertecpro\AppAnalytics;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Vipertecpro\AppAnalytics\Events\RemoteConfigFetched;

class AppAnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/app-analytics.php', 'app-analytics');

        $this->app->singleton(Analytics::class, function ($app) {
            return new Analytics(
                logger: $app->make(LoggerInterface::class),
                logCalls: (bool) $app['config']->get('app-analytics.analytics.log_in_debug', false),
            );
        });

        $this->app->singleton(RemoteConfig::class, function ($app) {
            return new RemoteConfig(
                defaults: (array) $app['config']->get('app-analytics.remote_config.defaults', []),
                minimumFetchInterval: (int) $app['config']->get('app-analytics.remote_config.minimum_fetch_interval', 3600),
            );
        });
    }

    public function boot(): void
    {
        // New values were activated on the device: read them again.
        Event::listen(RemoteConfigFetched::class, fn () => $this->app->make(RemoteConfig::class)->refresh());

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/app-analytics.php' => config_path('app-analytics.php'),
            ], 'app-analytics');
        }
    }
}
