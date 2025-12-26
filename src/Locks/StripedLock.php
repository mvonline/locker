<?php

namespace Mvonline\Locker\Locks;

/**
 * Striped/Sharded lock implementation.
 * Uses hash-based shard selection to reduce contention.
 *
 * @package Mvonline\Locker\Locks
 */
class StripedLock extends AbstractLock
{
    protected int $shardCount;
    protected int $shardIndex;
    protected SimpleLock $shardLock;

    /**
     * Create a new striped lock instance.
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
        $this->shardCount = $options['shardCount'] ?? 16;
        $this->shardIndex = $this->calculateShardIndex($key);
        $this->shardLock = new SimpleLock($cache, $events, $this->getShardKey(), $ttl, $owner);
    }

    /**
     * Get the lock type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'striped';
    }

    /**
     * Calculate the shard index for a given key.
     *
     * @param string|array $key
     * @return int
     */
    protected function calculateShardIndex(string|array $key): int
    {
        $keyString = is_array($key) ? implode(':', $key) : $key;
        return abs(crc32($keyString)) % $this->shardCount;
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

        $acquired = $this->shardLock->acquire();
        
        if ($acquired) {
            $this->acquired = true;
            $this->acquiredAt = microtime(true);
            $this->dispatchAcquired();
        } else {
            $this->dispatchFailed('Shard lock acquisition failed');
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

        $released = $this->shardLock->release();
        
        if ($released) {
            $this->markReleased();
        }

        return $released;
    }

    /**
     * Get the shard key.
     *
     * @return string
     */
    protected function getShardKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:striped:{$key}:shard:{$this->shardIndex}";
    }
}

