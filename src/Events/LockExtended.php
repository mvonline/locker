<?php

namespace Mvonline\Locker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a lock's TTL is extended.
 *
 * @package Mvonline\Locker\Events
 */
class LockExtended
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string|array $key The lock key(s)
     * @param string $type The lock type
     * @param int $additionalTime The additional time added in seconds
     */
    public function __construct(
        public readonly string|array $key,
        public readonly string $type,
        public readonly int $additionalTime
    ) {
    }
}

