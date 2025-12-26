<?php

namespace Mvonline\Locker;

use Mvonline\Locker\Contracts\LockContract;
use Mvonline\Locker\Exceptions\UnsupportedDriverException;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;

/**
 * Manager class responsible for creating lock instances.
 *
 * @package Mvonline\Locker
 */
class LockerManager
{
    /**
     * Create a new LockerManager instance.
     *
     * @param CacheFactory $cache The cache factory
     * @param EventDispatcher $events The event dispatcher
     * @param string|null $connection The cache connection name
     */
    public function __construct(
        protected CacheFactory $cache,
        protected EventDispatcher $events,
        protected ?string $connection = null
    ) {
    }

    /**
     * Get the cache repository.
     *
     * @return CacheRepository
     */
    public function getCache(): CacheRepository
    {
        return $this->connection
            ? $this->cache->store($this->connection)
            : $this->cache->store();
    }

    /**
     * Get the event dispatcher.
     *
     * @return EventDispatcher
     */
    public function getEvents(): EventDispatcher
    {
        return $this->events;
    }

    /**
     * Get the cache driver name.
     *
     * @return string
     */
    public function getDriver(): string
    {
        $store = $this->getCache()->getStore();
        
        return match (true) {
            $store instanceof \Illuminate\Cache\RedisStore => 'redis',
            $store instanceof \Illuminate\Cache\DatabaseStore => 'database',
            $store instanceof \Illuminate\Cache\FileStore => 'file',
            $store instanceof \Illuminate\Cache\MemcachedStore => 'memcached',
            $store instanceof \Illuminate\Cache\ArrayStore => 'array',
            default => 'unknown',
        };
    }

    /**
     * Check if the driver supports a specific lock type.
     *
     * @param string $type The lock type
     * @return bool
     */
    public function supports(string $type): bool
    {
        $driver = $this->getDriver();

        // Redlock requires Redis
        if ($type === 'redlock' && $driver !== 'redis') {
            return false;
        }

        // Most locks work with all drivers, but some advanced features may be limited
        return true;
    }

    /**
     * Create a lock instance.
     *
     * @param string|array $key The lock key(s)
     * @param string $type The lock type
     * @param int $ttl Time to live in seconds
     * @param string|null $owner The lock owner identifier
     * @param array $options Additional options
     * @return LockContract
     * @throws UnsupportedDriverException
     */
    public function create(
        string|array $key,
        string $type,
        int $ttl,
        ?string $owner = null,
        array $options = []
    ): LockContract {
        if (!$this->supports($type)) {
            throw new UnsupportedDriverException(
                "Lock type '{$type}' is not supported by driver '{$this->getDriver()}'"
            );
        }

        $cache = $this->getCache();
        $events = $this->getEvents();

        return match ($type) {
            'simple' => new Locks\SimpleLock($cache, $events, $key, $ttl, $owner),
            'safe' => new Locks\SafeLock($cache, $events, $key, $ttl, $owner),
            'redlock' => new Locks\Redlock($cache, $events, $key, $ttl, $owner, $options),
            'reentrant' => new Locks\ReentrantLock($cache, $events, $key, $ttl, $owner),
            'read', 'write' => new Locks\ReadWriteLock($cache, $events, $key, $ttl, $owner, $type),
            'semaphore' => new Locks\SemaphoreLock($cache, $events, $key, $ttl, $owner, $options),
            'fair' => new Locks\FairLock($cache, $events, $key, $ttl, $owner),
            'fencing' => new Locks\FencingTokenLock($cache, $events, $key, $ttl, $owner),
            'striped' => new Locks\StripedLock($cache, $events, $key, $ttl, $owner, $options),
            'multi' => new Locks\MultiResourceLock($cache, $events, $key, $ttl, $owner),
            'watchdog' => new Locks\WatchdogLock($cache, $events, $key, $ttl, $owner, $options),
            'leased' => new Locks\LeasedLock($cache, $events, $key, $ttl, $owner),
            default => throw new UnsupportedDriverException("Unknown lock type: {$type}"),
        };
    }

    /**
     * Create a lock builder instance.
     *
     * @param string|array $key The lock key(s)
     * @return LockBuilder
     */
    public function lock(string|array $key): LockBuilder
    {
        return new LockBuilder($this, $key);
    }

    /**
     * Quick helper: simple lock with callback.
     *
     * @param string $key
     * @param \Closure $callback
     * @return mixed
     */
    public function simple(string $key, \Closure $callback): mixed
    {
        return $this->lock($key)->type('simple')->run($callback);
    }

    /**
     * Quick helper: safe lock with callback.
     *
     * @param string $key
     * @param \Closure $callback
     * @return mixed
     */
    public function safe(string $key, \Closure $callback): mixed
    {
        return $this->lock($key)->type('safe')->run($callback);
    }

    /**
     * Quick helper: reentrant lock with callback.
     *
     * @param string $key
     * @param \Closure $callback
     * @return mixed
     */
    public function reentrant(string $key, \Closure $callback): mixed
    {
        return $this->lock($key)->type('reentrant')->run($callback);
    }

    /**
     * Quick helper: semaphore lock with callback.
     *
     * @param string $key
     * @param int $permits
     * @param \Closure $callback
     * @return mixed
     */
    public function semaphore(string $key, int $permits, \Closure $callback): mixed
    {
        return $this->lock($key)->type('semaphore')->permits($permits)->run($callback);
    }

    /**
     * Quick helper: read lock with callback.
     *
     * @param string $key
     * @param \Closure $callback
     * @return mixed
     */
    public function read(string $key, \Closure $callback): mixed
    {
        return $this->lock($key)->type('read')->run($callback);
    }

    /**
     * Quick helper: write lock with callback.
     *
     * @param string $key
     * @param \Closure $callback
     * @return mixed
     */
    public function write(string $key, \Closure $callback): mixed
    {
        return $this->lock($key)->type('write')->run($callback);
    }

    /**
     * Check if a lock is currently held.
     *
     * @param string $key
     * @param string|null $type
     * @return bool
     */
    public function isLocked(string $key, ?string $type = null): bool
    {
        $type = $type ?? 'simple';
        $lock = $this->create($key, $type, 60);
        return $lock->isAcquired() || $this->getCache()->has($this->getLockCacheKey($key, $type));
    }

    /**
     * Force release a lock (use with caution).
     *
     * @param string $key
     * @return bool
     */
    public function forceRelease(string $key): bool
    {
        // Try to release common lock types
        $types = ['simple', 'safe', 'reentrant', 'read', 'write'];
        
        foreach ($types as $type) {
            $cacheKey = $this->getLockCacheKey($key, $type);
            if ($this->getCache()->has($cacheKey)) {
                $this->getCache()->forget($cacheKey);
                return true;
            }
        }

        return false;
    }

    /**
     * Get the cache key for a lock type.
     *
     * @param string $key
     * @param string $type
     * @return string
     */
    protected function getLockCacheKey(string $key, string $type): string
    {
        return "lock:{$type}:{$key}";
    }
}

