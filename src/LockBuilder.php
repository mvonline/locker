<?php

namespace Mvonline\Locker;

use Closure;
use Mvonline\Locker\Contracts\LockContract;
use Mvonline\Locker\Exceptions\LockAcquisitionException;
use Mvonline\Locker\Exceptions\LockTimeoutException;
use Illuminate\Support\Str;

/**
 * Fluent builder for creating and configuring locks.
 *
 * @package Mvonline\Locker
 */
class LockBuilder
{
    protected string|array $key;
    protected string $type = 'simple';
    protected int $ttl = 60;
    protected ?int $blockTimeout = null;
    protected ?string $owner = null;
    protected int $permits = 1;
    protected ?int $renewEvery = null;
    protected int $shardCount = 16;
    protected ?Closure $onAcquired = null;
    protected ?Closure $onFailed = null;
    protected array $options = [];

    /**
     * Create a new LockBuilder instance.
     *
     * @param LockerManager $manager The locker manager
     * @param string|array $key The lock key(s)
     */
    public function __construct(
        protected LockerManager $manager,
        string|array $key
    ) {
        $this->key = $key;
    }

    /**
     * Set the lock key(s).
     *
     * @param string|array $key
     * @return $this
     */
    public function lock(string|array $key): self
    {
        $this->key = $key;
        return $this;
    }

    /**
     * Set the lock type.
     *
     * @param string $type
     * @return $this
     */
    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Set the TTL (time to live) in seconds.
     *
     * @param int $seconds
     * @return $this
     */
    public function ttl(int $seconds): self
    {
        $this->ttl = $seconds;
        return $this;
    }

    /**
     * Set blocking timeout in seconds.
     *
     * @param int $seconds
     * @return $this
     */
    public function block(int $seconds): self
    {
        $this->blockTimeout = $seconds;
        return $this;
    }

    /**
     * Set the lock owner identifier.
     *
     * @param string|int|null $owner
     * @return $this
     */
    public function owner(string|int|null $owner): self
    {
        $this->owner = $owner !== null ? (string) $owner : null;
        return $this;
    }

    /**
     * Set the number of permits (for semaphore locks).
     *
     * @param int $n
     * @return $this
     */
    public function permits(int $n): self
    {
        $this->permits = $n;
        $this->options['permits'] = $n;
        return $this;
    }

    /**
     * Set renewal interval for watchdog locks.
     *
     * @param int $seconds
     * @return $this
     */
    public function renewEvery(int $seconds): self
    {
        $this->renewEvery = $seconds;
        $this->options['renewEvery'] = $seconds;
        return $this;
    }

    /**
     * Set shard count for striped locks.
     *
     * @param int $count
     * @return $this
     */
    public function shardCount(int $count): self
    {
        $this->shardCount = $count;
        $this->options['shardCount'] = $count;
        return $this;
    }

    /**
     * Set callback to execute when lock is acquired.
     *
     * @param Closure $callback
     * @return $this
     */
    public function onAcquired(Closure $callback): self
    {
        $this->onAcquired = $callback;
        return $this;
    }

    /**
     * Set callback to execute when lock acquisition fails.
     *
     * @param Closure $callback
     * @return $this
     */
    public function onFailed(Closure $callback): self
    {
        $this->onFailed = $callback;
        return $this;
    }

    /**
     * Attempt to acquire the lock.
     *
     * @param int $permits Number of permits to acquire (for semaphore)
     * @return LockContract
     * @throws LockAcquisitionException
     */
    public function acquire(int $permits = 1): LockContract
    {
        $lock = $this->createLock();

        // Set acquire permits for semaphore locks
        if ($this->type === 'semaphore' && method_exists($lock, 'setAcquirePermits')) {
            $lock->setAcquirePermits($permits);
        }

        if ($this->blockTimeout !== null) {
            $acquired = $this->acquireWithBlocking($lock, $permits);
        } else {
            $acquired = $lock->acquire();
        }

        if (!$acquired) {
            if ($this->onFailed) {
                ($this->onFailed)($lock);
            }

            if ($this->blockTimeout !== null) {
                throw new LockTimeoutException(
                    "Failed to acquire lock '{$this->getKeyString()}' within {$this->blockTimeout} seconds"
                );
            }

            throw new LockAcquisitionException(
                "Failed to acquire lock '{$this->getKeyString()}'"
            );
        }

        if ($this->onAcquired) {
            ($this->onAcquired)($lock);
        }

        return $lock;
    }

    /**
     * Acquire lock with blocking and exponential backoff.
     *
     * @param LockContract $lock
     * @param int $permits (unused, kept for compatibility)
     * @return bool
     */
    protected function acquireWithBlocking(LockContract $lock, int $permits): bool
    {
        $startTime = microtime(true);
        $attempt = 0;
        $baseDelay = 0.01; // 10ms base delay

        while (true) {
            if ($lock->acquire()) {
                return true;
            }

            $elapsed = microtime(true) - $startTime;
            if ($elapsed >= $this->blockTimeout) {
                return false;
            }

            // Exponential backoff with jitter
            $delay = min($baseDelay * pow(2, $attempt), 1.0); // Max 1 second
            $jitter = (mt_rand(0, 100) / 1000); // 0-100ms jitter
            usleep((int) (($delay + $jitter) * 1000000));

            $attempt++;
        }
    }

    /**
     * Execute a callback while holding the lock.
     *
     * @param Closure $callback
     * @return mixed
     * @throws LockAcquisitionException
     * @throws LockTimeoutException
     */
    public function run(Closure $callback): mixed
    {
        $lock = $this->acquire();

        try {
            // For fencing token locks, pass the token to the callback
            if ($this->type === 'fencing' && method_exists($lock, 'getToken')) {
                return $callback($lock->getToken());
            }

            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release(): bool
    {
        // This method is typically called on the lock instance itself
        // But we can create a temporary lock to release if needed
        $lock = $this->createLock();
        return $lock->release();
    }

    /**
     * Create the lock instance.
     *
     * @return LockContract
     */
    protected function createLock(): LockContract
    {
        // Handle semaphore permits
        if ($this->type === 'semaphore') {
            $this->options['permits'] = $this->permits;
        }

        return $this->manager->create(
            $this->key,
            $this->type,
            $this->ttl,
            $this->owner,
            $this->options
        );
    }

    /**
     * Get the key as a string for error messages.
     *
     * @return string
     */
    protected function getKeyString(): string
    {
        return is_array($this->key) ? implode(', ', $this->key) : $this->key;
    }
}

