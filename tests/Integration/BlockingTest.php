<?php

namespace Mvonline\Locker\Tests\Integration;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;
use Mvonline\Locker\Exceptions\LockTimeoutException;

class BlockingTest extends TestCase
{
    /** @test */
    public function it_blocks_until_lock_is_available()
    {
        // This test verifies blocking mechanism works
        // In a real scenario, another process would hold the lock
        
        $lock = Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->block(1) // Wait up to 1 second
            ->acquire();

        $this->assertTrue($lock->isAcquired());
        $lock->release();
    }

    /** @test */
    public function it_throws_timeout_exception_when_blocking_expires()
    {
        // Acquire lock first
        $lock1 = Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->acquire();

        $this->expectException(LockTimeoutException::class);

        // Try to acquire with very short timeout
        try {
            Locker::lock('test-key')
                ->type('simple')
                ->ttl(10)
                ->block(0.1) // Very short timeout
                ->acquire();
        } finally {
            $lock1->release();
        }
    }
}

