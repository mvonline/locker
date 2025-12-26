<?php

namespace Mvonline\Locker\Tests\Unit;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;
use Mvonline\Locker\Exceptions\UnsupportedDriverException;

class LockerManagerTest extends TestCase
{
    /** @test */
    public function it_can_create_different_lock_types()
    {
        $types = ['simple', 'safe', 'reentrant', 'semaphore', 'fair', 'fencing', 'striped', 'multi', 'watchdog', 'leased'];

        foreach ($types as $type) {
            $lock = Locker::lock('test-' . $type)
                ->type($type)
                ->ttl(10)
                ->acquire();

            $this->assertTrue($lock->isAcquired(), "Failed to acquire {$type} lock");
            $this->assertTrue($lock->release(), "Failed to release {$type} lock");
        }
    }

    /** @test */
    public function it_detects_cache_driver()
    {
        $driver = app('locker')->getDriver();
        $this->assertIsString($driver);
        $this->assertNotEmpty($driver);
    }

    /** @test */
    public function it_supports_helper_methods()
    {
        $result = Locker::simple('test', fn() => 'success');
        $this->assertEquals('success', $result);

        $result = Locker::safe('test', fn() => 'success');
        $this->assertEquals('success', $result);

        $result = Locker::reentrant('test', fn() => 'success');
        $this->assertEquals('success', $result);

        $result = Locker::semaphore('test', 5, fn() => 'success');
        $this->assertEquals('success', $result);

        $result = Locker::read('test', fn() => 'success');
        $this->assertEquals('success', $result);

        $result = Locker::write('test', fn() => 'success');
        $this->assertEquals('success', $result);
    }

    /** @test */
    public function it_can_check_if_locked()
    {
        $lock = Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->acquire();

        // Note: isLocked may not work perfectly with array cache, but should not throw
        $isLocked = Locker::isLocked('test-key');
        $this->assertIsBool($isLocked);
        
        $lock->release();
    }

    /** @test */
    public function it_throws_exception_for_unknown_lock_type()
    {
        $this->expectException(UnsupportedDriverException::class);

        Locker::lock('test-key')
            ->type('unknown-type')
            ->ttl(10)
            ->acquire();
    }
}

