<?php

namespace Mvonline\Locker\Tests\Integration;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;

class StripedLockTest extends TestCase
{
    /** @test */
    public function it_can_acquire_striped_lock()
    {
        $lock = Locker::lock('test-key')
            ->type('striped')
            ->shardCount(16)
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock->isAcquired());
        $this->assertTrue($lock->release());
    }

    /** @test */
    public function it_uses_different_shards_for_different_keys()
    {
        // Different keys should hash to different shards
        $lock1 = Locker::lock('key-1')
            ->type('striped')
            ->shardCount(16)
            ->ttl(10)
            ->acquire();

        $lock2 = Locker::lock('key-2')
            ->type('striped')
            ->shardCount(16)
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock1->isAcquired());
        $this->assertTrue($lock2->isAcquired());

        $lock1->release();
        $lock2->release();
    }
}

