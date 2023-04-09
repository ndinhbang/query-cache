<?php

namespace Ndinhbang\QueryCache\Observers;

use Ndinhbang\QueryCache\Facades\QueryCache;
use Illuminate\Database\Eloquent\Model;

class FlushQueryCacheObserver
{
    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    public bool $afterCommit = true;

    /**
     * Handle the Model "created" event.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function created(Model $model): void
    {
        $this->invalidateCache($model);
    }

    /**
     * Handle the Model "updated" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function updated(Model $model): void
    {
        $this->invalidateCache($model);
    }

    /**
     * Handle the Model "deleted" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function deleted(Model $model): void
    {
        $this->invalidateCache($model);
    }

    /**
     * Handle the Model "forceDeleted" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function forceDeleted(Model $model): void
    {
        $this->invalidateCache($model);
    }

    /**
     * Handle the Model "restored" event.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function restored(Model $model): void
    {
        $this->invalidateCache($model);
    }

    /**
     * Invalidate the cache for a model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return void
     *
     */
    protected function invalidateCache(Model $model): void
    {
        QueryCache::forget(
            $model->getCacheTagsToInvalidateOnUpdate()
        );
    }
}
