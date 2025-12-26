<?php

namespace Mvonline\Locker\Locks;

/**
 * Simple lock implementation using atomic add operation.
 * No retries, no ownership validation, TTL required.
 *
 * @package Mvonline\Locker\Locks
 */
class SimpleLock extends AbstractLock
{
    /**
     * Get the lock type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'simple';
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
        $acquired = $this->cache->add($key, 1, $this->ttl);

        if ($acquired) {
            $this->markAcquired();
        } else {
            $this->dispatchFailed('Lock already held');
        }

        return $acquired;
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
        $released = $this->cache->forget($key);

        if ($released) {
            $this->markReleased();
        }

        return $released;
    }

    /**
     * Get the cache key for the lock.
     *
     * @return string
     */
    protected function getCacheKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:simple:{$key}";
    }
}

