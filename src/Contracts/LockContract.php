<?php

namespace Mvonline\Locker\Contracts;

/**
 * Contract that all lock implementations must adhere to.
 *
 * @package Mvonline\Locker\Contracts
 */
interface LockContract
{
    /**
     * Attempt to acquire the lock.
     *
     * @return bool True if lock was acquired, false otherwise
     */
    public function acquire(): bool;

    /**
     * Release the lock.
     *
     * @return bool True if lock was released, false otherwise
     */
    public function release(): bool;

    /**
     * Check if the lock is currently acquired.
     *
     * @return bool True if lock is acquired, false otherwise
     */
    public function isAcquired(): bool;

    /**
     * Get the owner identifier of the lock.
     *
     * @return string|null The owner identifier or null if not set
     */
    public function getOwner(): ?string;
}

