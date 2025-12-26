<?php

namespace Mvonline\Locker\Tests\Unit;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;

class SemaphoreLockTest extends TestCase
{
    /** @test */
    public function it_allows_multiple_concurrent_holders()
    {
        $permits = 3;
        $acquired = 0;

        // Acquire all permits
        for ($i = 0; $i < $permits; $i++) {
            $lock = Locker::lock('test-key')
                ->type('semaphore')
                ->permits($permits)
                ->ttl(10)
                ->acquire();

            if ($lock->isAcquired()) {
                $acquired++;
            }
        }

        $this->assertEquals($permits, $acquired);
    }

    /** @test */
    public function it_prevents_exceeding_permits()
    {
        $permits = 2;

        // Acquire first permit
        $lock1 = Locker::lock('test-key')
            ->type('semaphore')
            ->permits($permits)
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock1->isAcquired());

        // Acquire second permit
        $lock2 = Locker::lock('test-key')
            ->type('semaphore')
            ->permits($permits)
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock2->isAcquired());

        // Try to acquire third permit (should fail)
        try {
            $lock3 = Locker::lock('test-key')
                ->type('semaphore')
                ->permits($permits)
                ->ttl(10)
                ->acquire();
            $this->fail('Expected LockAcquisitionException');
        } catch (\Mvonline\Locker\Exceptions\LockAcquisitionException $e) {
            // Expected
            $this->assertTrue(true);
        }

        $lock1->release();
        $lock2->release();
    }

    /** @test */
    public function it_can_release_permits()
    {
        $permits = 2;

        $lock1 = Locker::lock('test-key')
            ->type('semaphore')
            ->permits($permits)
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock1->isAcquired());
        $this->assertTrue($lock1->release());

        // Now should be able to acquire again
        $lock2 = Locker::lock('test-key')
            ->type('semaphore')
            ->permits($permits)
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock2->isAcquired());
    }

    /** @test */
    public function it_can_execute_callback_with_semaphore()
    {
        $result = null;

        Locker::semaphore('test-key', 5, function() use (&$result) {
            $result = 'executed';
        });

        $this->assertEquals('executed', $result);
    }
}

