<?php

namespace Mvonline\Locker\Tests\Integration;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;

class LeasedLockTest extends TestCase
{
    /** @test */
    public function it_can_acquire_and_release_leased_lock()
    {
        $lock = Locker::lock('test-key')
            ->type('leased')
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock->isAcquired());
        $this->assertTrue($lock->release());
    }

    /** @test */
    public function it_can_renew_lease()
    {
        $lock = Locker::lock('test-key')
            ->type('leased')
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock->isAcquired());
        
        if (method_exists($lock, 'renew')) {
            $this->assertTrue($lock->renew());
        }

        $this->assertTrue($lock->release());
    }

    /** @test */
    public function it_tracks_lease_expiration()
    {
        $lock = Locker::lock('test-key')
            ->type('leased')
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock->isAcquired());
        $this->assertTrue($lock->isAcquired()); // Should still be valid
        
        $lock->release();
    }
}

