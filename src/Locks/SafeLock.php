<?php

namespace Mvonline\Locker\Locks;

use Mvonline\Locker\Exceptions\LockOwnershipException;

/**
 * Safe lock implementation using unique owner token (UUID).
 * Release compares owner token before deletion to prevent accidental unlock.
 *
 * @package Mvonline\Locker\Locks
 */
class SafeLock extends AbstractLock
{
    /**
     * Get the lock type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'safe';
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
        $acquired = $this->cache->add($key, $value, $this->ttl);

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
     * @throws LockOwnershipException
     */
    public function release(): bool
    {
        if (!$this->acquired) {
            return false;
        }

        $key = $this->getCacheKey();
        $storedOwner = $this->cache->get($key);

        // Verify ownership before releasing
        if ($storedOwner !== $this->owner) {
            $this->markReleased(); // Mark as released locally even if cache release fails
            throw new LockOwnershipException(
                "Lock ownership mismatch. Expected '{$this->owner}', got '{$storedOwner}'"
            );
        }

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
        return "lock:safe:{$key}";
    }
}

