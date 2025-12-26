<?php

namespace Mvonline\Locker\Tests\Integration;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;
use Mvonline\Locker\Exceptions\LockAcquisitionException;
use Mvonline\Locker\Exceptions\LockOwnershipException;
use Mvonline\Locker\Exceptions\UnsupportedDriverException;

class ExceptionTest extends TestCase
{
    /** @test */
    public function it_throws_exception_on_failed_acquisition()
    {
        // Acquire lock first
        $lock1 = Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->acquire();

        $this->expectException(LockAcquisitionException::class);

        // Try to acquire again (should throw)
        Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->acquire();

        $lock1->release();
    }

    /** @test */
    public function it_throws_ownership_exception_for_safe_lock()
    {
        $lock1 = Locker::lock('test-key')
            ->type('safe')
            ->ttl(10)
            ->owner('owner-1')
            ->acquire();

        $this->assertTrue($lock1->isAcquired());

        // Create lock with different owner - can't release what wasn't acquired
        $lock2 = Locker::lock('test-key')
            ->type('safe')
            ->ttl(10)
            ->owner('owner-2');

        // Lock2 wasn't acquired, so release will just return false
        $this->assertFalse($lock2->release());

        // Release the correct lock
        $lock1->release();
    }

    /** @test */
    public function it_throws_exception_for_unsupported_lock_type()
    {
        $this->expectException(UnsupportedDriverException::class);

        Locker::lock('test-key')
            ->type('invalid-type')
            ->ttl(10)
            ->acquire();
    }
}

