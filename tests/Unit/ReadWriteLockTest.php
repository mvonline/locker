<?php

namespace Mvonline\Locker\Tests\Unit;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;

class ReadWriteLockTest extends TestCase
{
    /** @test */
    public function it_allows_multiple_readers()
    {
        $readCount = 0;

        // First reader
        Locker::read('test-key', function() use (&$readCount) {
            $readCount++;
        });

        // Second reader
        Locker::read('test-key', function() use (&$readCount) {
            $readCount++;
        });

        $this->assertEquals(2, $readCount);
    }

    /** @test */
    public function it_prevents_write_when_readers_exist()
    {
        // Acquire read lock
        $readLock = Locker::lock('test-key')
            ->type('read')
            ->ttl(10)
            ->acquire();

        $this->assertTrue($readLock->isAcquired());

        // Try to acquire write lock (should fail)
        try {
            $writeLock = Locker::lock('test-key')
                ->type('write')
                ->ttl(10)
                ->acquire();
            $this->fail('Expected LockAcquisitionException');
        } catch (\Mvonline\Locker\Exceptions\LockAcquisitionException $e) {
            // Expected
            $this->assertTrue(true);
        }

        // Release read lock
        $readLock->release();

        // Now write should succeed
        $writeLock2 = Locker::lock('test-key')
            ->type('write')
            ->ttl(10)
            ->acquire();

        $this->assertTrue($writeLock2->isAcquired());
        $writeLock2->release();
    }

    /** @test */
    public function it_prevents_read_when_writer_exists()
    {
        // Acquire write lock
        $writeLock = Locker::lock('test-key')
            ->type('write')
            ->ttl(10)
            ->acquire();

        $this->assertTrue($writeLock->isAcquired());

        // Try to acquire read lock (should fail)
        try {
            $readLock = Locker::lock('test-key')
                ->type('read')
                ->ttl(10)
                ->acquire();
            $this->fail('Expected LockAcquisitionException');
        } catch (\Mvonline\Locker\Exceptions\LockAcquisitionException $e) {
            // Expected
            $this->assertTrue(true);
        }

        $writeLock->release();
    }

    /** @test */
    public function it_allows_exclusive_writer()
    {
        $writeExecuted = false;

        Locker::write('test-key', function() use (&$writeExecuted) {
            $writeExecuted = true;
        });

        $this->assertTrue($writeExecuted);
    }
}

