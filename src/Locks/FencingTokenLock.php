<?php

namespace Mvonline\Locker\Locks;

/**
 * Fencing token lock implementation.
 * Provides monotonic token per acquisition to prevent split-brain writes.
 *
 * @package Mvonline\Locker\Locks
 */
class FencingTokenLock extends AbstractLock
{
    protected int $token = 0;

    /**
     * Get the lock type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'fencing';
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
        $tokenKey = $this->getTokenKey();

        // Acquire the lock
        $acquired = $this->cache->add($key, $this->owner, $this->ttl);

        if ($acquired) {
            // Generate and store monotonic token
            $currentToken = (int) $this->cache->get($tokenKey, 0);
            $this->token = $currentToken + 1;
            $this->cache->put($tokenKey, $this->token, $this->ttl);
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
            $this->markReleased();
        }

        return $released;
    }

    /**
     * Get the current fencing token.
     *
     * @return int
     */
    public function getToken(): int
    {
        return $this->token;
    }

    /**
     * Get the cache key for the lock.
     *
     * @return string
     */
    protected function getCacheKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:fencing:{$key}";
    }

    /**
     * Get the cache key for the token counter.
     *
     * @return string
     */
    protected function getTokenKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:fencing:{$key}:token";
    }
}

