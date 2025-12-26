<?php

namespace Mvonline\Locker\Locks;

/**
 * Read-Write lock implementation.
 * Multiple readers, exclusive writer, writer preference.
 *
 * @package Mvonline\Locker\Locks
 */
class ReadWriteLock extends AbstractLock
{
    protected string $mode;

    /**
     * Create a new read-write lock instance.
     *
     * @param \Illuminate\Contracts\Cache\Repository $cache
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     * @param string|array $key
     * @param int $ttl
     * @param string|null $owner
     * @param string $mode 'read' or 'write'
     */
    public function __construct($cache, $events, $key, $ttl, ?string $owner = null, string $mode = 'read')
    {
        parent::__construct($cache, $events, $key, $ttl, $owner);
        $this->mode = $mode;
    }

    /**
     * Get the lock type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->mode === 'write' ? 'write' : 'read';
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

        if ($this->mode === 'read') {
            return $this->acquireRead();
        } else {
            return $this->acquireWrite();
        }
    }

    /**
     * Acquire read lock.
     *
     * @return bool
     */
    protected function acquireRead(): bool
    {
        $writeKey = $this->getWriteKey();
        $readKey = $this->getReadKey();
        $ownerKey = $this->getOwnerKey();

        // Check if there's an active writer
        if ($this->cache->has($writeKey)) {
            $this->dispatchFailed('Write lock is active');
            return false;
        }

        // Increment reader count
        $count = $this->cache->increment($readKey, 1);
        if ($count === false) {
            $this->cache->put($readKey, 1, $this->ttl);
            $count = 1;
        } else {
            $this->cache->put($readKey, $count, $this->ttl);
        }

        // Track this owner
        $owners = $this->cache->get($ownerKey, []);
        if (!is_array($owners)) {
            $owners = [];
        }
        $owners[] = $this->owner;
        $this->cache->put($ownerKey, $owners, $this->ttl);

        $this->markAcquired();
        return true;
    }

    /**
     * Acquire write lock.
     *
     * @return bool
     */
    protected function acquireWrite(): bool
    {
        $writeKey = $this->getWriteKey();
        $readKey = $this->getReadKey();

        // Check if there are active readers
        $readCount = (int) $this->cache->get($readKey, 0);
        if ($readCount > 0) {
            $this->dispatchFailed('Read locks are active');
            return false;
        }

        // Check if there's an active writer
        if ($this->cache->has($writeKey)) {
            $this->dispatchFailed('Write lock is already active');
            return false;
        }

        // Acquire write lock
        $acquired = $this->cache->add($writeKey, $this->owner, $this->ttl);
        
        if ($acquired) {
            $ownerKey = $this->getOwnerKey();
            $this->cache->put($ownerKey, $this->owner, $this->ttl);
            $this->markAcquired();
        } else {
            $this->dispatchFailed('Write lock acquisition failed');
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

        if ($this->mode === 'read') {
            return $this->releaseRead();
        } else {
            return $this->releaseWrite();
        }
    }

    /**
     * Release read lock.
     *
     * @return bool
     */
    protected function releaseRead(): bool
    {
        $readKey = $this->getReadKey();
        $ownerKey = $this->getOwnerKey();

        // Remove owner from list
        $owners = $this->cache->get($ownerKey, []);
        if (is_array($owners)) {
            $owners = array_filter($owners, fn($o) => $o !== $this->owner);
            if (empty($owners)) {
                $this->cache->forget($ownerKey);
            } else {
                $this->cache->put($ownerKey, $owners, $this->ttl);
            }
        }

        // Decrement reader count
        $count = max(0, ($this->cache->decrement($readKey, 1) ?? 0) - 1);
        
        if ($count <= 0) {
            $this->cache->forget($readKey);
        } else {
            $this->cache->put($readKey, $count, $this->ttl);
        }

        $this->markReleased();
        return true;
    }

    /**
     * Release write lock.
     *
     * @return bool
     */
    protected function releaseWrite(): bool
    {
        $writeKey = $this->getWriteKey();
        $ownerKey = $this->getOwnerKey();

        // Verify ownership
        $currentOwner = $this->cache->get($writeKey);
        if ($currentOwner !== $this->owner) {
            $this->acquired = false;
            return false;
        }

        $this->cache->forget($writeKey);
        $this->cache->forget($ownerKey);
        $this->markReleased();
        return true;
    }

    /**
     * Get the cache key for write lock.
     *
     * @return string
     */
    protected function getWriteKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:readwrite:{$key}:write";
    }

    /**
     * Get the cache key for read count.
     *
     * @return string
     */
    protected function getReadKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:readwrite:{$key}:read";
    }

    /**
     * Get the cache key for owners.
     *
     * @return string
     */
    protected function getOwnerKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:readwrite:{$key}:owners";
    }
}

