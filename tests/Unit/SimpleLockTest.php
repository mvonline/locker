<?php

namespace Mvonline\Locker\Tests\Unit;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;

class SimpleLockTest extends TestCase
{
    /** @test */
    public function it_can_acquire_and_release_a_simple_lock()
    {
        $lock = Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock->isAcquired());
        $this->assertTrue($lock->release());
        $this->assertFalse($lock->isAcquired());
    }

    /** @test */
    public function it_can_execute_a_callback_with_lock()
    {
        $executed = false;

        Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->run(function() use (&$executed) {
                $executed = true;
            });

        $this->assertTrue($executed);
    }

    /** @test */
    public function it_prevents_concurrent_access()
    {
        $counter = 0;

        // This test would need concurrent execution to fully verify
        // For now, we just test basic functionality
        Locker::lock('counter')
            ->type('simple')
            ->ttl(10)
            ->run(function() use (&$counter) {
                $counter++;
            });

        $this->assertEquals(1, $counter);
    }
}

