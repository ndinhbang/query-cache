<?php

namespace Ndinhbang\QueryCache;

use BadMethodCallException;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\NoLock;
use Illuminate\Cache\RedisTagSet;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;
use Psr\SimpleCache\InvalidArgumentException;

class CacheAwareConnectionProxy extends Connection
{
    /**
     * Create a new Cache Aware Connection Proxy instance.
     *
     * @param ConnectionInterface $connection
     * @param Repository $repository
     * @param DateTimeInterface|\DateInterval|int|null $ttl
     * @param int $lockWait
     * @param string $cachePrefix
     * @param array $tagNames
     * @param Relation|null $relation
     */
    public function __construct(
        public ConnectionInterface                        $connection,
        protected Repository                              $repository,
        protected array                                   $tagNames,
        protected DateTimeInterface|DateInterval|int|null $ttl,
        protected int                                     $lockWait,
        protected string                                  $cachePrefix,
        protected ?Relation                               $relation = null,
    )
    {
        if (empty($tagNames)) {
            throw new \InvalidArgumentException('Query cache tag name must be provided.');
        }
    }

    /**
     * Create a new CacheAwareProxy instance.
     *
     * @param ConnectionInterface $connection
     * @param DateTimeInterface|\DateInterval|int|null $ttl
     * @param array $tagNames
     * @param int $wait
     * @param string|null $store
     * @param Relation|null $relation
     * @param string|null $cachePrefix
     * @return static
     */
    public static function createNewInstance(
        ConnectionInterface                     $connection,
        DateTimeInterface|DateInterval|int|null $ttl,
        array                                   $tagNames,
        int                                     $wait,
        ?string                                 $store,
        ?Relation                               $relation = null,
        ?string                                 $cachePrefix = null
    ): static
    {
        return new static(
            $connection,
            static::store($store, (bool)$wait),
            $tagNames,
            $ttl,
            $wait,
            $cachePrefix ?: config('query-cache.prefix'),
            $relation,
        );
    }

    /**
     * Run a select statement against the database.
     *
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return array
     * @throws InvalidArgumentException
     */
    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        // Create the unique hash for the query to avoid any duplicate query.
        $computedKey = $this->getQueryHash($query, $bindings);

        // We will use the prefix to operate on the cache directly.
        $key = $this->cachePrefix . ':' . $computedKey;

        if (!is_null($results = $this->retrieveResultsFromCache($key))) {
            return $results;
        }

        return $this
            ->retrieveLock($key)
            ->block($this->lockWait, function () use ($query, $bindings, $useReadPdo, $key): array {

                if (!is_null($results = $this->retrieveResultsFromCache($key))) {
                    return $results;
                }

                $results = $this->connection->select($query, $bindings, $useReadPdo);

                if ($this->ttl) {
                    $this->repository->getStore()->forever($key, $results);
                } else {
                    $this->repository->getStore()->put($key, $results, $this->ttl);
                }
                // We will tag the key, use the tags for removing key later
                $this->tags($this->getAllTagNames())->addEntry($key, $this->ttl ?: -1);

                return $results;
            });
    }

    /**
     * @return array
     */
    protected function getAllTagNames(): array
    {
        $names = $this->tagNames;
        if (is_null($this->relation)) {
            return $names;
        }
        $names[] = $this->relation->getRelated()->getTable();
        if ($this->relation instanceof HasManyThrough) {
            $names[] = $this->relation->getThroughParent()->getTable();
        }

        return array_unique($names);
    }

    /**
     * @param array $names
     * @return \Illuminate\Cache\RedisTagSet
     */
    protected function tags(array $names): RedisTagSet
    {
        return new RedisTagSet($this->repository->getStore(), $names);
    }

    /**
     * Run a select statement and return a single result.
     *
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function selectOne($query, $bindings = [], $useReadPdo = true)
    {
        $records = $this->select($query, $bindings, $useReadPdo);

        return array_shift($records);
    }

    /**
     * Hashes the incoming query for using as cache key.
     *
     * @param string $query
     * @param array $bindings
     * @return string
     */
    protected function getQueryHash(string $query, array $bindings): string
    {
        return rtrim(base64_encode(md5($this->connection->getDatabaseName() . $query . implode('', $bindings), true)), '=');
    }

    /**
     * Retrieves the lock to use before getting the results.
     *
     * @param string $key
     * @return \Illuminate\Contracts\Cache\Lock
     */
    protected function retrieveLock(string $key): Lock
    {
        if (!$this->lockWait) {
            return new NoLock($key, $this->lockWait);
        }

        return $this->repository->getStore()->lock($key, $this->lockWait);
    }

    /**
     * Retrieve the results from the cache.
     *
     * @param string $key
     * @return array|null
     *
     * @throws InvalidArgumentException
     */
    protected function retrieveResultsFromCache(string $key): ?array
    {
        // If the ttl is negative, regenerate the results.
        if (is_int($this->ttl) && $this->ttl < 1) {
            return null;
        }

        return $this->repository->get($key);
    }

    /**
     * Gets the timestamp for the expiration time.
     *
     * @param \DateInterval|DateTimeInterface|int $expiration
     * @return int
     */
    protected function getTimestamp(DateInterval|DateTimeInterface|int $expiration): int
    {
        if ($expiration instanceof DateTimeInterface) {
            return $expiration->getTimestamp();
        }

        if ($expiration instanceof DateInterval) {
            return now()->add($expiration)->getTimestamp();
        }

        return now()->addRealSeconds($expiration)->getTimestamp();
    }

    /**
     * Pass-through all properties to the underlying connection.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->connection->{$name};
    }

    /**
     * Pass-through all properties to the underlying connection.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     * @noinspection MagicMethodsValidityInspection
     */
    public function __set(string $name, mixed $value): void
    {
        $this->connection->{$name} = $value;
    }

    /**
     * Pass-through all method calls to the underlying connection.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->connection->{$method}(...$parameters);
    }

    /**
     * Returns the store to se for caching.
     *
     * @param string|null $store
     * @param bool $lockable
     * @return Repository
     */
    protected static function store(?string $store, bool $lockable): Repository
    {
        $repository = cache()->store($store ?: config('query-cache.store'));

        if (!$repository->supportsTags()) {
            throw new BadMethodCallException('This cache store does not support tagging.');
        }

        if ($lockable && !$repository->getStore() instanceof LockProvider) {
            $store = $store ?: cache()->getDefaultDriver();

            throw new LogicException("The [$store] cache does not support atomic locks.");
        }

        return $repository;
    }
}
