<?php

namespace Ndinhbang\QueryCache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\ServiceProvider;
use Ndinhbang\QueryCache\Console\Commands\QueryCache\Forget;

class QueryCacheServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        HasManyThrough::macro('getThroughParent', function () {
            /** @var \Illuminate\Database\Eloquent\Relations\HasManyThrough $this */
            return $this->throughParent;
        });

        if (! Builder::hasMacro('cache')) {
            Builder::macro('cache', $this->macro());
        }

        if (! EloquentBuilder::hasGlobalMacro('cache')) {
            EloquentBuilder::macro('cache', $this->eloquentMacro());
        }

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

    /**
     * Creates a macro for the base Query Builder.
     *
     * @return \Closure
     */
    protected function macro(): Closure
    {
        return function (
            DateTimeInterface|DateInterval|int|bool|null $ttl = 60,
            string|array $key = [],
            string $store = null,
            int $wait = 0,
            Relation $relation = null,
        ): Builder {
            /** @var \Illuminate\Database\Query\Builder $this */

            // Avoid re-wrapping the connection into another proxy.
            if ($this->connection instanceof CacheAwareConnectionProxy) {
                $this->connection = $this->connection->connection;
            }

            $this->connection = CacheAwareConnectionProxy::createNewInstance(
                $this->connection,
                $ttl === false ? -1 : $ttl,
                $key,
                $wait,
                $store,
                relation: $relation,
            );

            return $this;
        };
    }

    /**
     * Creates a macro for the base Query Builder.
     *
     * @return \Closure
     */
    protected function eloquentMacro(): Closure
    {
        return function (
            DateTimeInterface|DateInterval|int|bool|null $ttl = 60,
            string|array $key = [],
            string $store = null,
            int $wait = 0,
            Relation $relation = null,
        ): EloquentBuilder {
            /** @var \Illuminate\Database\Eloquent\Builder $this */
            $this->getQuery()->cache($ttl, $key ?? $this->getModel()->getTable(), $store, $wait, $relation);

            // This global scope is responsible for caching eager loaded relations.
            $this->withGlobalScope(
                Scopes\CacheRelations::class,
                new Scopes\CacheRelations(
                    $ttl === false ? -1 : $ttl,
                    $key,
                    $store,
                    $wait,
                )
            );

            return $this;
        };
    }
}
