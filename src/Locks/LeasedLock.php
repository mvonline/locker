<?php

namespace Mvonline\Locker\Locks;

/**
 * Leased lock implementation.
 * Hard TTL expiration with explicit renewal required.
 * Guarantees eventual release even if process crashes.
 *
 * @package Mvonline\Locker\Locks
 */
class LeasedLock extends AbstractLock
{
    protected float $leaseExpiresAt = 0.0;

    /**
     * Get the lock type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'leased';
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function acquire(): bool
    {
        if ($this->acquired) {
            // Check if lease is still valid
            if ($this->isLeaseValid()) {
                return true;
            }
            // Lease expired, mark as not acquired
            $this->acquired = false;
        }

        $key = $this->getCacheKey();
        $acquired = $this->cache->add($key, $this->owner, $this->ttl);

        if ($acquired) {
            $this->leaseExpiresAt = microtime(true) + $this->ttl;
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
        
        // Verify ownership
        $currentOwner = $this->cache->get($key);
        if ($currentOwner !== $this->owner) {
            $this->acquired = false;
            return false;
        }

        $released = $this->cache->forget($key);

        if ($released) {
            $this->leaseExpiresAt = 0.0;
            $this->markReleased();
        }

        return $released;
    }

    /**
     * Check if the lock is currently acquired.
     *
     * @return bool
     */
    public function isAcquired(): bool
    {
        if (!$this->acquired) {
            return false;
        }

        // Check if lease is still valid
        if (!$this->isLeaseValid()) {
            $this->acquired = false;
            return false;
        }

        return true;
    }

    /**
     * Explicitly renew the lease.
     *
     * @return bool
     */
    public function renew(): bool
    {
        if (!$this->acquired) {
            return false;
        }

        if (!$this->isLeaseValid()) {
            $this->acquired = false;
            return false;
        }

        $key = $this->getCacheKey();
        
        // Verify ownership
        $currentOwner = $this->cache->get($key);
        if ($currentOwner !== $this->owner) {
            $this->acquired = false;
            return false;
        }

        // Extend lease
        $extended = $this->cache->put($key, $this->owner, $this->ttl);
        
        if ($extended) {
            $this->leaseExpiresAt = microtime(true) + $this->ttl;
            $this->dispatchExtended($this->ttl);
        }

        return $extended;
    }

    /**
     * Check if the lease is still valid.
     *
     * @return bool
     */
    protected function isLeaseValid(): bool
    {
        if ($this->leaseExpiresAt === 0.0) {
            return false;
        }

        return microtime(true) < $this->leaseExpiresAt;
    }

    /**
     * Get the cache key for the lock.
     *
     * @return string
     */
    protected function getCacheKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:leased:{$key}";
    }
}

