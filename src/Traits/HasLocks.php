<?php

namespace Mvonline\Locker\Traits;

use Mvonline\Locker\LockBuilder;
use Mvonline\Locker\Facades\Locker;

/**
 * Trait that provides lock functionality to classes.
 *
 * @package Mvonline\Locker\Traits
 */
trait HasLocks
{
    /**
     * Lock a resource and execute a callback.
     *
     * @param string|array $key The lock key(s)
     * @param \Closure $callback The callback to execute
     * @param string $type The lock type
     * @param int $ttl Time to live in seconds
     * @return mixed
     */
    public function lockResource(
        string|array $key,
        \Closure $callback,
        string $type = 'simple',
        int $ttl = 60
    ): mixed {
        return Locker::lock($key)
            ->type($type)
            ->ttl($ttl)
            ->run($callback);
    }

    /**
     * Execute a callback with a lock.
     *
     * @param string $resource The resource key
     * @param \Closure $callback The callback to execute
     * @param string $type The lock type
     * @param int $ttl Time to live in seconds
     * @return mixed
     */
    public function withLock(
        string $resource,
        \Closure $callback,
        string $type = 'simple',
        int $ttl = 60
    ): mixed {
        return $this->lockResource($resource, $callback, $type, $ttl);
    }

    /**
     * Get a lock builder for a resource.
     *
     * @param string|array $key The lock key(s)
     * @return LockBuilder
     */
    public function lockBuilder(string|array $key): LockBuilder
    {
        return Locker::lock($key);
    }
}

