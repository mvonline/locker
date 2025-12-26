<?php

namespace Mvonline\Locker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when lock acquisition times out.
 *
 * @package Mvonline\Locker\Events
 */
class LockTimeout
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string|array $key The lock key(s)
     * @param string $type The lock type
     */
    public function __construct(
        public readonly string|array $key,
        public readonly string $type
    ) {
    }
}

