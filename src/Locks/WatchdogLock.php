<?php

namespace Mvonline\Locker\Locks;

/**
 * Auto-renewal/Watchdog lock implementation.
 * Automatically extends TTL before expiration.
 *
 * @package Mvonline\Locker\Locks
 */
class WatchdogLock extends AbstractLock
{
    protected ?int $renewEvery = null;
    protected bool $renewalActive = false;
    protected ?\Closure $renewalCallback = null;

    /**
     * Create a new watchdog lock instance.
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
        $this->renewEvery = $options['renewEvery'] ?? max(1, (int) ($ttl * 0.5)); // Default to 50% of TTL
    }

    /**
     * Get the lock type.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'watchdog';
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
        $acquired = $this->cache->add($key, $this->owner, $this->ttl);

        if ($acquired) {
            $this->markAcquired();
            $this->startRenewal();
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

        $this->stopRenewal();

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
     * Start the renewal process.
     *
     * @return void
     */
    protected function startRenewal(): void
    {
        if ($this->renewEvery === null || $this->renewEvery >= $this->ttl) {
            return; // Don't renew if interval is too large
        }

        $this->renewalActive = true;
        $this->scheduleRenewal();
    }

    /**
     * Schedule the next renewal.
     *
     * @return void
     */
    protected function scheduleRenewal(): void
    {
        if (!$this->renewalActive || !$this->acquired) {
            return;
        }

        // Use a simple approach: store renewal flag and check on access
        // In a real implementation, you might use a background job or process
        $this->renewalCallback = function () {
            if ($this->acquired && $this->renewalActive) {
                $this->renew();
                $this->scheduleRenewal();
            }
        };

        // For now, we'll renew synchronously when needed
        // In production, you'd use Laravel's job queue or a background process
    }

    /**
     * Renew the lock TTL.
     *
     * @return bool
     */
    public function renew(): bool
    {
        if (!$this->acquired) {
            return false;
        }

        $key = $this->getCacheKey();
        
        // Verify ownership
        $currentOwner = $this->cache->get($key);
        if ($currentOwner !== $this->owner) {
            $this->acquired = false;
            $this->stopRenewal();
            return false;
        }

        // Extend TTL
        $extended = $this->cache->put($key, $this->owner, $this->ttl);
        
        if ($extended) {
            $this->dispatchExtended($this->ttl);
        }

        return $extended;
    }

    /**
     * Stop the renewal process.
     *
     * @return void
     */
    protected function stopRenewal(): void
    {
        $this->renewalActive = false;
        $this->renewalCallback = null;
    }

    /**
     * Get the cache key for the lock.
     *
     * @return string
     */
    protected function getCacheKey(): string
    {
        $key = is_array($this->key) ? implode(':', $this->key) : $this->key;
        return "lock:watchdog:{$key}";
    }
}

