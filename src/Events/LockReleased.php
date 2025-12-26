<?php

namespace Mvonline\Locker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a lock is released.
 *
 * @package Mvonline\Locker\Events
 */
class LockReleased
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string|array $key The lock key(s)
     * @param string $type The lock type
     * @param string|null $owner The lock owner identifier
     * @param float $heldFor The duration the lock was held in seconds
     */
    public function __construct(
        public readonly string|array $key,
        public readonly string $type,
        public readonly ?string $owner,
        public readonly float $heldFor
    ) {
    }
}

