<?php

namespace Mvonline\Locker\Locks;

/**
 * Fair lock implementation with FIFO ordering.
 * Prevents starvation using a queue-based approach.
 *
 * @package Mvonline\Locker\Locks
 */
class FairLock extends AbstractLock
{
    /**
     * Get the lock type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'fair';
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

        $queueKey = $this->getQueueKey();
        $lockKey = $this->getLockKey();
        $positionKey = $this->getPositionKey();

        // Add ourselves to the queue
        $queue = $this->cache->get($queueKey, []);
        if (!is_array($queue)) {
            $queue = [];
        }

        // Check if already in queue
        if (in_array($this->owner, $queue)) {
            // Check if it's our turn
            $position = array_search($this->owner, $queue);
            if ($position === 0 && !$this->cache->has($lockKey)) {
                // It's our turn and lock is available
                array_shift($queue);
                $this->cache->put($queueKey, $queue, $this->ttl);
                $this->cache->put($lockKey, $this->owner, $this->ttl);
                $this->markAcquired();
                return true;
            }
            $this->dispatchFailed('Already in queue, waiting for turn');
            return false;
        }

        // Add to end of queue
        $queue[] = $this->owner;
        $this->cache->put($queueKey, $queue, $this->ttl);
        $this->cache->put($positionKey, count($queue) - 1, $this->ttl);

        // If queue was empty and lock is available, acquire immediately
        if (count($queue) === 1 && !$this->cache->has($lockKey)) {
            array_shift($queue);
            $this->cache->put($queueKey, $queue, $this->ttl);
            $this->cache->put($lockKey, $this->owner, $this->ttl);
            $this->markAcquired();
            return true;
        }

        $this->dispatchFailed('Added to queue, waiting for turn');
        return false;
    }

    /**
     * Release the lock and notify next in queue.
     *
     * @return bool
     */
    public function release(): bool
    {
        if (!$this->acquired) {
            return false;
        }

        $lockKey = $this->getLockKey();
        $queueKey = $this->getQueueKey();
        $positionKey = $this->getPositionKey();

        // Verify ownership
        $currentOwner = $this->cache->get($lockKey);
        if ($currentOwner !== $this->owner) {
            $this->acquired = false;
            return false;
        }

        // Release lock
        $this->cache->forget($lockKey);
        $this->cache->forget($positionKey);

        // Next in queue can now acquire
        $queue = $this->cache->get($queueKey, []);
        if (is_array($queue) && !empty($queue)) {
            // The next owner will acquire on their next attempt
        }

        $this->markReleased();
        return true;
    }

    /**
     * Get the cache key for the lock.
     *
     * @return string
     */
    protected function getLockKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:fair:{$key}";
    }

    /**
     * Get the cache key for the queue.
     *
     * @return string
     */
    protected function getQueueKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:fair:{$key}:queue";
    }

    /**
     * Get the cache key for position.
     *
     * @return string
     */
    protected function getPositionKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:fair:{$key}:position:{$this->owner}";
    }
}

