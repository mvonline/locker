<?php

namespace Mvonline\Locker\Tests\Integration;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Traits\HasLocks;

class TestClass
{
    use HasLocks;

    public $id = 123;
}

class HasLocksTraitTest extends TestCase
{
    /** @test */
    public function it_provides_lock_resource_method()
    {
        $testClass = new TestClass();
        
        $result = null;
        $testClass->lockResource('resource-' . $testClass->id, function() use (&$result) {
            $result = 'executed';
        }, 'simple', 10);

        $this->assertEquals('executed', $result);
    }

    /** @test */
    public function it_provides_with_lock_method()
    {
        $testClass = new TestClass();
        
        $result = null;
        $testClass->withLock('resource-' . $testClass->id, function() use (&$result) {
            $result = 'executed';
        }, type: 'simple', ttl: 10);

        $this->assertEquals('executed', $result);
    }

    /** @test */
    public function it_provides_lock_builder_method()
    {
        $testClass = new TestClass();
        
        $builder = $testClass->lockBuilder('test-key');
        $this->assertInstanceOf(\Mvonline\Locker\LockBuilder::class, $builder);
        
        $lock = $builder->type('simple')->ttl(10)->acquire();
        $this->assertTrue($lock->isAcquired());
        $this->assertTrue($lock->release());
    }
}

