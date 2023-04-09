<?php

namespace Ndinhbang\QueryCache;

use Illuminate\Cache\RedisTagSet;
use Illuminate\Contracts\Cache\Factory as CacheContract;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Config\Repository as ConfigContract;

class QueryCache
{
    /**
     * Create a new Cache Query instance.
     *
     * @param  \Illuminate\Contracts\Cache\Factory  $cache
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @param  string|null  $store
     */
    public function __construct(
        protected CacheContract $cache,
        protected ConfigContract $config,
        public ?string $store = null,
    ) {
        $this->store ??= $this->config->get('query-cache.store');
    }

    /**
     * Retrieves the repository using the set store name.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    protected function repository(): Repository
    {
        return $this->cache->store($this->store);
    }

    /**
     * Changes the cache store to work with.
     *
     * @param  string  $store
     * @return $this
     */
    public function store(string $store): static
    {
        $this->store = $store;

        return $this;
    }

    /**
     * @param array $names
     * @return \Illuminate\Cache\RedisTagSet
     */
    protected function tags(array $names): RedisTagSet
    {
        return new RedisTagSet($this->repository()->getStore(), $names);
    }

    /**
     * Forgets a query using the user key used to persist it.
     *
     * @param  array  $tags
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function forget(array $tags): bool
    {
        $store = $this->repository()->getStore();
        $tagset = $this->tags($tags);

        $entries = $tagset->entries()
            ->map(fn (string $key) => $store->getPrefix().$key)
            ->chunk(1000);

        // remove tag items
        foreach ($entries as $cacheKeys) {
            $store->connection()->del(...$cacheKeys);
        }

        // remove tags
        $tagset->flush();

        return true;
    }
}
