<?php

namespace Mvonline\Locker\Facades;

use Illuminate\Support\Facades\Facade;
use Mvonline\Locker\LockBuilder;

/**
 * Facade for the Locker service.
 *
 * @method static LockBuilder lock(string|array $key)
 * @method static mixed simple(string $key, \Closure $callback)
 * @method static mixed safe(string $key, \Closure $callback)
 * @method static mixed reentrant(string $key, \Closure $callback)
 * @method static mixed semaphore(string $key, int $permits, \Closure $callback)
 * @method static mixed read(string $key, \Closure $callback)
 * @method static mixed write(string $key, \Closure $callback)
 * @method static bool isLocked(string $key, ?string $type = null)
 * @method static bool forceRelease(string $key)
 *
 * @package Mvonline\Locker\Facades
 */
class Locker extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'locker';
    }
}

