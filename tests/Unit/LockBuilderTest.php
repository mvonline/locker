<?php

namespace Mvonline\Locker\Tests\Unit;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;
use Mvonline\Locker\Exceptions\LockTimeoutException;
use Mvonline\Locker\Exceptions\LockAcquisitionException;

class LockBuilderTest extends TestCase
{
    /** @test */
    public function it_supports_fluent_api()
    {
        $lock = Locker::lock('test-key')
            ->type('simple')
            ->ttl(30)
            ->owner('owner-123')
            ->acquire();

        $this->assertTrue($lock->isAcquired());
        $this->assertEquals('owner-123', $lock->getOwner());
    }

    /** @test */
    public function it_supports_blocking()
    {
        // This test verifies blocking works (though in single-threaded test it may acquire immediately)
        $lock = Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->block(1)
            ->acquire();

        $this->assertTrue($lock->isAcquired());
    }

    /** @test */
    public function it_executes_callback_with_run()
    {
        $result = null;

        Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->run(function() use (&$result) {
                $result = 'executed';
            });

        $this->assertEquals('executed', $result);
    }

    /** @test */
    public function it_returns_callback_result()
    {
        $result = Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->run(function() {
                return 'result';
            });

        $this->assertEquals('result', $result);
    }

    /** @test */
    public function it_supports_on_acquired_callback()
    {
        $acquired = false;

        Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->onAcquired(function($lock) use (&$acquired) {
                $acquired = $lock->isAcquired();
            })
            ->acquire();

        $this->assertTrue($acquired);
    }

    /** @test */
    public function it_supports_on_failed_callback()
    {
        // Acquire lock first
        $lock1 = Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->acquire();

        $failed = false;

        // Try to acquire again (should fail)
        try {
            Locker::lock('test-key')
                ->type('simple')
                ->ttl(10)
                ->onFailed(function() use (&$failed) {
                    $failed = true;
                })
                ->acquire();
        } catch (LockAcquisitionException $e) {
            // Expected
        }

        $lock1->release();
        $this->assertTrue($failed);
    }

    /** @test */
    public function it_supports_semaphore_permits()
    {
        $lock = Locker::lock('test-key')
            ->type('semaphore')
            ->permits(5)
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock->isAcquired());
    }

    /** @test */
    public function it_supports_striped_shard_count()
    {
        $lock = Locker::lock('test-key')
            ->type('striped')
            ->shardCount(32)
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock->isAcquired());
    }

    /** @test */
    public function it_supports_watchdog_renew_every()
    {
        $lock = Locker::lock('test-key')
            ->type('watchdog')
            ->ttl(60)
            ->renewEvery(15)
            ->acquire();

        $this->assertTrue($lock->isAcquired());
    }
}

