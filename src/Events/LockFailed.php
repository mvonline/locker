<?php

namespace Mvonline\Locker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when lock acquisition fails.
 *
 * @package Mvonline\Locker\Events
 */
class LockFailed
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string|array $key The lock key(s)
     * @param string $type The lock type
     * @param string $reason The reason for failure
     */
    public function __construct(
        public readonly string|array $key,
        public readonly string $type,
        public readonly string $reason
    ) {
    }
}

