<?php

namespace Mvonline\Locker\Locks;

use Mvonline\Locker\Exceptions\UnsupportedDriverException;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

/**
 * Redlock implementation (Redis-only).
 * Uses Redis Redlock quorum algorithm with multiple Redis connections.
 *
 * @package Mvonline\Locker\Locks
 */
class Redlock extends AbstractLock
{
    protected array $redisConnections = [];
    protected float $clockDriftFactor = 0.01;
    protected int $quorum;

    /**
     * Create a new Redlock instance.
     *
     * @param \Illuminate\Contracts\Cache\Repository $cache
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     * @param string|array $key
     * @param int $ttl
     * @param string|null $owner
     * @param array $options
     */
    public function __construct($cache, $events, $key, $ttl, ?string $owner = null, array $options = [])
    {
        parent::__construct($cache, $events, $key, $ttl, $owner);

        // Verify Redis driver
        $store = $cache->getStore();
        if (!$store instanceof \Illuminate\Cache\RedisStore) {
            throw new UnsupportedDriverException('Redlock requires Redis cache driver');
        }

        // Get Redis connections
        $cacheFactory = app(CacheFactory::class);
        $connections = $options['connections'] ?? ['default'];
        
        foreach ($connections as $connection) {
            $this->redisConnections[] = $cacheFactory->store($connection);
        }

        $this->clockDriftFactor = $options['clock_drift_factor'] ?? 0.01;
        $this->quorum = $options['quorum'] ?? (int) floor(count($this->redisConnections) / 2) + 1;
    }

    /**
     * Get the lock type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'redlock';
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function acquire(): bool
    {
        if ($this->acquired) {
            return true;
        }

        $key = $this->getCacheKey();
        $value = $this->owner;
        $startTime = microtime(true);
        $acquiredCount = 0;

        // Try to acquire on all Redis instances
        foreach ($this->redisConnections as $redisCache) {
            $store = $redisCache->getStore();
            if ($store instanceof \Illuminate\Cache\RedisStore) {
                $redis = $store->connection();
                $result = $redis->set($key, $value, 'EX', $this->ttl, 'NX');
                
                if ($result) {
                    $acquiredCount++;
                }
            }
        }

        // Calculate validity time
        $drift = ($this->ttl * $this->clockDriftFactor) + 0.002; // Add 2ms
        $validityTime = $this->ttl - (microtime(true) - $startTime) - $drift;

        // Check quorum
        if ($acquiredCount >= $this->quorum && $validityTime > 0) {
            $this->markAcquired();
            return true;
        }

        // Release any acquired locks
        $this->release();

        $this->dispatchFailed("Failed to acquire quorum. Acquired: {$acquiredCount}, Required: {$this->quorum}");
        return false;
    }

    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release(): bool
    {
        if (!$this->acquired) {
            return false;
        }

        $key = $this->getCacheKey();
        $releasedCount = 0;

        // Release on all Redis instances
        foreach ($this->redisConnections as $redisCache) {
            $store = $redisCache->getStore();
            if ($store instanceof \Illuminate\Cache\RedisStore) {
                $redis = $store->connection();
                
                // Lua script to ensure we only delete if value matches
                $script = "
                    if redis.call('get', KEYS[1]) == ARGV[1] then
                        return redis.call('del', KEYS[1])
                    else
                        return 0
                    end
                ";
                
                $result = $redis->eval($script, 1, $key, $this->owner);
                if ($result) {
                    $releasedCount++;
                }
            }
        }

        $this->markReleased();
        return $releasedCount > 0;
    }

    /**
     * Get the cache key for the lock.
     *
     * @return string
     */
    protected function getCacheKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:redlock:{$key}";
    }
}

