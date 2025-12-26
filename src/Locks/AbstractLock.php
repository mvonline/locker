<?php

namespace Mvonline\Locker\Locks;

use Mvonline\Locker\Contracts\LockContract;
use Mvonline\Locker\Events\LockAcquired;
use Mvonline\Locker\Events\LockExtended;
use Mvonline\Locker\Events\LockFailed;
use Mvonline\Locker\Events\LockReleased;
use Mvonline\Locker\Events\LockTimeout;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Str;

/**
 * Abstract base class for all lock implementations.
 *
 * @package Mvonline\Locker\Locks
 */
abstract class AbstractLock implements LockContract
{
    protected bool $acquired = false;
    protected ?string $owner = null;
    protected float $acquiredAt = 0.0;

    /**
     * Create a new lock instance.
     *
     * @param CacheRepository $cache The cache repository
     * @param EventDispatcher $events The event dispatcher
     * @param string|array $key The lock key(s)
     * @param int $ttl Time to live in seconds
     * @param string|null $owner The lock owner identifier
     */
    public function __construct(
        protected CacheRepository $cache,
        protected EventDispatcher $events,
        protected string|array $key,
        protected int $ttl,
        ?string $owner = null
    ) {
        $this->owner = $owner ?? $this->generateOwner();
    }

    /**
     * Generate a unique owner identifier.
     *
     * @return string
     */
    protected function generateOwner(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Get the lock key(s).
     *
     * @return string|array
     */
    public function getKey(): string|array
    {
        return $this->key;
    }

    /**
     * Get the lock type.
     *
     * @return string
     */
    abstract public function getType(): string;

    /**
     * Get the TTL.
     *
     * @return int
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * Get the owner identifier.
     *
     * @return string|null
     */
    public function getOwner(): ?string
    {
        return $this->owner;
    }

    /**
     * Check if the lock is currently acquired.
     *
     * @return bool
     */
    public function isAcquired(): bool
    {
        return $this->acquired;
    }

    /**
     * Dispatch lock acquired event.
     *
     * @return void
     */
    protected function dispatchAcquired(): void
    {
        $this->events->dispatch(new LockAcquired(
            $this->key,
            $this->getType(),
            $this->owner,
            $this->ttl
        ));
    }

    /**
     * Dispatch lock released event.
     *
     * @return void
     */
    protected function dispatchReleased(): void
    {
        $heldFor = $this->acquiredAt > 0 ? microtime(true) - $this->acquiredAt : 0.0;
        $this->events->dispatch(new LockReleased(
            $this->key,
            $this->getType(),
            $this->owner,
            $heldFor
        ));
    }

    /**
     * Dispatch lock failed event.
     *
     * @param string $reason
     * @return void
     */
    protected function dispatchFailed(string $reason): void
    {
        $this->events->dispatch(new LockFailed(
            $this->key,
            $this->getType(),
            $reason
        ));
    }

    /**
     * Dispatch lock timeout event.
     *
     * @return void
     */
    protected function dispatchTimeout(): void
    {
        $this->events->dispatch(new LockTimeout(
            $this->key,
            $this->getType()
        ));
    }

    /**
     * Dispatch lock extended event.
     *
     * @param int $additionalTime
     * @return void
     */
    protected function dispatchExtended(int $additionalTime): void
    {
        $this->events->dispatch(new LockExtended(
            $this->key,
            $this->getType(),
            $additionalTime
        ));
    }

    /**
     * Mark the lock as acquired.
     *
     * @return void
     */
    protected function markAcquired(): void
    {
        $this->acquired = true;
        $this->acquiredAt = microtime(true);
        $this->dispatchAcquired();
    }

    /**
     * Mark the lock as released.
     *
     * @return void
     */
    protected function markReleased(): void
    {
        $this->acquired = false;
        $this->dispatchReleased();
    }
}

