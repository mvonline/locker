<?php

namespace Mvonline\Locker\Locks;

/**
 * Reentrant lock implementation.
 * Same owner can re-acquire, uses acquisition counter.
 * Release decrements counter, unlock only at zero.
 *
 * @package Mvonline\Locker\Locks
 */
class ReentrantLock extends AbstractLock
{
    protected int $count = 0;

    /**
     * Get the lock type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'reentrant';
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function acquire(): bool
    {
        $key = $this->getCacheKey();
        $ownerKey = $this->getOwnerKey();

        // Check if we already own the lock
        $currentOwner = $this->cache->get($key);
        
        if ($currentOwner === $this->owner) {
            // Re-acquisition: increment counter
            $this->count = $this->cache->increment($ownerKey, 1) ?? 1;
            $this->cache->put($ownerKey, $this->count, $this->ttl);
            $this->acquired = true;
            return true;
        }

        // Try to acquire the lock
        $acquired = $this->cache->add($key, $this->owner, $this->ttl);
        
        if ($acquired) {
            // Set initial count
            $this->count = 1;
            $this->cache->put($ownerKey, $this->count, $this->ttl);
            $this->markAcquired();
        } else {
            $this->dispatchFailed('Lock already held by different owner');
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
        $ownerKey = $this->getOwnerKey();

        // Verify ownership
        $currentOwner = $this->cache->get($key);
        if ($currentOwner !== $this->owner) {
            $this->acquired = false;
            return false;
        }

        // Decrement counter
        $this->count = max(0, ($this->cache->decrement($ownerKey, 1) ?? 0) - 1);
        
        if ($this->count <= 0) {
            // Fully release the lock
            $this->cache->forget($key);
            $this->cache->forget($ownerKey);
            $this->markReleased();
            return true;
        }

        // Update counter
        $this->cache->put($ownerKey, $this->count, $this->ttl);
        return true;
    }

    /**
     * Get the cache key for the lock.
     *
     * @return string
     */
    protected function getCacheKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:reentrant:{$key}";
    }

    /**
     * Get the cache key for the owner counter.
     *
     * @return string
     */
    protected function getOwnerKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:reentrant:{$key}:owner:{$this->owner}";
    }
}

