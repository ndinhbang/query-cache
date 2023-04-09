<?php

namespace Ndinhbang\QueryCache\Traits;

use Ndinhbang\QueryCache\Observers\FlushQueryCacheObserver;

trait QueryCacheable
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootQueryCacheable(): void
    {
        /** @var \Illuminate\Database\Eloquent\Model $this */
        static::observe(
            static::getFlushQueryCacheObserver()
        );
    }

    /**
     * Get the observer class name that will observe the changes
     * and will invalidate the cache upon database change.
     *
     * @return string
     */
    protected static function getFlushQueryCacheObserver()
    {
        return FlushQueryCacheObserver::class;
    }

    /**
     * @return array
     */
    protected function getCacheTagsToInvalidateOnUpdate(): array
    {
        return [
            $this->getTable(),
            $this->getTable() . ':' . $this->getRouteKeyName()
        ];
    }

}