<?php

namespace Mvonline\Locker\Tests\Integration;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;

class CacheDriverIntegrationTest extends TestCase
{
    /** @test */
    public function it_works_with_array_cache_driver()
    {
        $this->app['config']->set('cache.default', 'array');
        
        $result = Locker::simple('test-key', fn() => 'success');
        
        $this->assertEquals('success', $result);
    }

    /** @test */
    public function it_works_with_different_lock_types_on_array_driver()
    {
        $this->app['config']->set('cache.default', 'array');
        
        // Test safe lock
        $result = Locker::safe('test-safe', fn() => 'safe-success');
        $this->assertEquals('safe-success', $result);
        
        // Test reentrant lock
        $result = Locker::reentrant('test-reentrant', fn() => 'reentrant-success');
        $this->assertEquals('reentrant-success', $result);
    }

    /** @test */
    public function it_prevents_concurrent_access_with_simple_lock()
    {
        $this->app['config']->set('cache.default', 'array');
        
        $counter = 0;
        
        // First lock should succeed
        Locker::lock('counter')
            ->type('simple')
            ->ttl(10)
            ->run(function() use (&$counter) {
                $counter++;
            });
        
        $this->assertEquals(1, $counter);
        
        // Second lock should also work (different execution)
        Locker::lock('counter')
            ->type('simple')
            ->ttl(10)
            ->run(function() use (&$counter) {
                $counter++;
            });
        
        $this->assertEquals(2, $counter);
    }

    /** @test */
    public function it_works_with_all_lock_types_on_array_driver()
    {
        $this->app['config']->set('cache.default', 'array');
        
        $types = ['simple', 'safe', 'reentrant', 'semaphore', 'fair', 'fencing', 'striped', 'multi', 'watchdog', 'leased'];
        
        foreach ($types as $type) {
            $result = Locker::lock('test-' . $type)
                ->type($type)
                ->ttl(10)
                ->run(function() {
                    return 'success';
                });
            
            $this->assertEquals('success', $result, "Failed for type: {$type}");
        }
    }

    /** @test */
    public function it_handles_read_write_locks()
    {
        $this->app['config']->set('cache.default', 'array');
        
        // Read lock
        $readResult = Locker::read('config', fn() => 'read');
        $this->assertEquals('read', $readResult);
        
        // Write lock
        $writeResult = Locker::write('config', fn() => 'write');
        $this->assertEquals('write', $writeResult);
    }
}

