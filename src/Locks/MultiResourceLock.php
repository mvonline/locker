<?php

namespace Mvonline\Locker\Locks;

use Mvonline\Locker\Contracts\LockContract;

/**
 * Multi-resource lock implementation.
 * Atomic multi-lock acquisition with lexicographical sorting and rollback on failure.
 *
 * @package Mvonline\Locker\Locks
 */
class MultiResourceLock extends AbstractLock
{
    protected array $locks = [];
    protected array $acquiredKeys = [];

    /**
     * Get the lock type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'multi';
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

        // Ensure keys are sorted lexicographically to prevent deadlocks
        $keys = is_array($this->key) ? $this->key : [$this->key];
        sort($keys);

        // Try to acquire all locks
        foreach ($keys as $key) {
            $lock = new SafeLock($this->cache, $this->events, $key, $this->ttl, $this->owner);
            
            if ($lock->acquire()) {
                $this->locks[$key] = $lock;
                $this->acquiredKeys[] = $key;
            } else {
                // Rollback: release all acquired locks
                $this->rollback();
                $this->dispatchFailed("Failed to acquire lock for key: {$key}");
                return false;
            }
        }

        $this->markAcquired();
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

        $allReleased = true;

        // Release all locks in reverse order
        foreach (array_reverse($this->acquiredKeys) as $key) {
            if (isset($this->locks[$key])) {
                if (!$this->locks[$key]->release()) {
                    $allReleased = false;
                }
            }
        }

        $this->locks = [];
        $this->acquiredKeys = [];
        $this->markReleased();

        return $allReleased;
    }

    /**
     * Rollback all acquired locks.
     *
     * @return void
     */
    protected function rollback(): void
    {
        foreach (array_reverse($this->acquiredKeys) as $key) {
            if (isset($this->locks[$key])) {
                $this->locks[$key]->release();
            }
        }

        $this->locks = [];
        $this->acquiredKeys = [];
    }
}

