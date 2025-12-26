<?php

namespace Mvonline\Locker\Tests\Integration;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;
use Mvonline\Locker\Events\LockAcquired;
use Mvonline\Locker\Events\LockReleased;
use Mvonline\Locker\Events\LockFailed;
use Illuminate\Support\Facades\Event;

class EventTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    /** @test */
    public function it_dispatches_lock_acquired_event()
    {
        Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->acquire();

        Event::assertDispatched(LockAcquired::class, function ($event) {
            return $event->key === 'test-key' && $event->type === 'simple';
        });
    }

    /** @test */
    public function it_dispatches_lock_released_event()
    {
        $lock = Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->acquire();

        $lock->release();

        Event::assertDispatched(LockReleased::class, function ($event) {
            return $event->key === 'test-key' && $event->type === 'simple';
        });
    }

    /** @test */
    public function it_dispatches_lock_failed_event()
    {
        // Acquire lock first
        $lock1 = Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->acquire();

        // Try to acquire again (should fail)
        try {
            Locker::lock('test-key')
                ->type('simple')
                ->ttl(10)
                ->acquire();
        } catch (\Exception $e) {
            // Expected
        }

        Event::assertDispatched(LockFailed::class);

        $lock1->release();
    }

    /** @test */
    public function it_dispatches_events_when_using_run()
    {
        Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->run(function() {
                // Do work
            });

        Event::assertDispatched(LockAcquired::class);
        Event::assertDispatched(LockReleased::class);
    }
}

