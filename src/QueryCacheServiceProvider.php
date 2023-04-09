<?php

namespace Ndinhbang\QueryCache;

use Illuminate\Support\ServiceProvider;
use Ndinhbang\QueryCache\Console\Commands\QueryCache\Forget;

class QueryCacheServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('query-cache.php'),
            ], 'config');

            // Registering package commands.
             $this->commands([
                 Forget::class
             ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'query-cache');
    }
}
