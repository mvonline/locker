<?php

namespace Mvonline\Locker\Locks;

/**
 * Semaphore lock implementation.
 * Allows N concurrent holders, uses atomic increment/decrement.
 *
 * @package Mvonline\Locker\Locks
 */
class SemaphoreLock extends AbstractLock
{
    protected int $maxPermits;
    protected int $acquirePermits = 1;

    /**
     * Create a new semaphore lock instance.
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
        $this->maxPermits = $options['permits'] ?? 1;
        $this->acquirePermits = $options['acquirePermits'] ?? 1;
    }

    /**
     * Set the number of permits to acquire.
     *
     * @param int $permits
     * @return $this
     */
    public function setAcquirePermits(int $permits): self
    {
        $this->acquirePermits = $permits;
        return $this;
    }

    /**
     * Get the lock type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'semaphore';
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function acquire(): bool
    {
        $permits = $this->acquirePermits;
        $countKey = $this->getCountKey();
        $ownerKey = $this->getOwnerKey();

        // Get current count
        $currentCount = (int) $this->cache->get($countKey, 0);

        // Check if we can acquire
        if ($currentCount + $permits > $this->maxPermits) {
            $this->dispatchFailed("Not enough permits available. Requested: {$permits}, Available: " . ($this->maxPermits - $currentCount));
            return false;
        }

        // Atomically increment
        $newCount = $this->cache->increment($countKey, $permits);
        if ($newCount === false) {
            $this->cache->put($countKey, $permits, $this->ttl);
            $newCount = $permits;
        } else {
            $this->cache->put($countKey, $newCount, $this->ttl);
        }

        // Track this owner's permits
        $ownerPermits = (int) $this->cache->get($ownerKey, 0);
        $this->cache->put($ownerKey, $ownerPermits + $permits, $this->ttl);

        if (!$this->acquired) {
            $this->markAcquired();
        }

        return true;
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

        $permits = $this->acquirePermits;

        $countKey = $this->getCountKey();
        $ownerKey = $this->getOwnerKey();

        // Check owner's permits
        $ownerPermits = (int) $this->cache->get($ownerKey, 0);
        if ($ownerPermits < $permits) {
            return false;
        }

        // Decrement
        $newOwnerPermits = max(0, $ownerPermits - $permits);
        $this->cache->put($ownerKey, $newOwnerPermits, $this->ttl);

        $currentCount = (int) $this->cache->get($countKey, 0);
        $newCount = max(0, $currentCount - $permits);
        
        if ($newCount === 0) {
            $this->cache->forget($countKey);
            $this->cache->forget($ownerKey);
            $this->markReleased();
        } else {
            $this->cache->put($countKey, $newCount, $this->ttl);
        }

        return true;
    }

    /**
     * Get the cache key for the permit count.
     *
     * @return string
     */
    protected function getCountKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:semaphore:{$key}:count";
    }

    /**
     * Get the cache key for the owner's permits.
     *
     * @return string
     */
    protected function getOwnerKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:semaphore:{$key}:owner:{$this->owner}";
    }
}

