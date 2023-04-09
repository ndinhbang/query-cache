<?php

namespace Ndinhbang\QueryCache;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ndinhbang\QueryCache\Skeleton\SkeletonClass
 */
class QueryCacheFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'query-cache';
    }
}
