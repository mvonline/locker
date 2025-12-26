<?php

namespace Mvonline\Locker\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a lock is successfully acquired.
 *
 * @package Mvonline\Locker\Events
 */
class LockAcquired
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string|array $key The lock key(s)
     * @param string $type The lock type
     * @param string|null $owner The lock owner identifier
     * @param int $duration The lock duration in seconds
     */
    public function __construct(
        public readonly string|array $key,
        public readonly string $type,
        public readonly ?string $owner,
        public readonly int $duration
    ) {
    }
}

