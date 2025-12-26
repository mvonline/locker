<?php

namespace Mvonline\Locker\Tests\Unit;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;

class MultiResourceLockTest extends TestCase
{
    /** @test */
    public function it_can_acquire_multiple_resources_atomically()
    {
        $lock = Locker::lock(['resource-1', 'resource-2'])
            ->type('multi')
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock->isAcquired());
        $this->assertTrue($lock->release());
    }

    /** @test */
    public function it_rolls_back_on_failure()
    {
        // Acquire first resource
        $lock1 = Locker::lock('resource-1')
            ->type('safe')
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock1->isAcquired());

        // Try to acquire multi-resource lock (should fail because resource-1 is locked)
        try {
            $lock2 = Locker::lock(['resource-1', 'resource-2'])
                ->type('multi')
                ->ttl(10)
                ->acquire();
            $this->fail('Expected LockAcquisitionException');
        } catch (\Mvonline\Locker\Exceptions\LockAcquisitionException $e) {
            // Expected
            $this->assertTrue(true);
        }

        // Release first lock
        $lock1->release();

        // Now should succeed
        $lock3 = Locker::lock(['resource-1', 'resource-2'])
            ->type('multi')
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock3->isAcquired());
        $lock3->release();
    }

    /** @test */
    public function it_can_execute_callback_with_multi_resource_lock()
    {
        $executed = false;

        Locker::lock(['resource-1', 'resource-2'])
            ->type('multi')
            ->ttl(10)
            ->run(function() use (&$executed) {
                $executed = true;
            });

        $this->assertTrue($executed);
    }
}

