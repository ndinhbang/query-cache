<?php

namespace Ndinhbang\QueryCache\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ndinhbang\QueryCache\QueryCache store(string $store)
 * @method static bool forget(array $keys)
 * @method static \Ndinhbang\QueryCache\QueryCache getFacadeRoot()
 */
class QueryCache extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \Ndinhbang\QueryCache\QueryCache::class;
    }
}
