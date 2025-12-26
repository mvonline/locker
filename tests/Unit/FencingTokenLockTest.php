<?php

namespace Mvonline\Locker\Tests\Unit;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;

class FencingTokenLockTest extends TestCase
{
    /** @test */
    public function it_generates_monotonic_tokens()
    {
        $token1 = null;
        $token2 = null;

        Locker::lock('test-key')
            ->type('fencing')
            ->ttl(10)
            ->run(function($token) use (&$token1) {
                $token1 = $token;
            });

        Locker::lock('test-key')
            ->type('fencing')
            ->ttl(10)
            ->run(function($token) use (&$token2) {
                $token2 = $token;
            });

        $this->assertNotNull($token1);
        $this->assertNotNull($token2);
        $this->assertIsInt($token1);
        $this->assertIsInt($token2);
        $this->assertGreaterThan($token1, $token2);
    }

    /** @test */
    public function it_can_acquire_and_release_fencing_lock()
    {
        $lock = Locker::lock('test-key')
            ->type('fencing')
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock->isAcquired());
        $this->assertTrue($lock->release());
    }

    /** @test */
    public function it_provides_token_to_callback()
    {
        $receivedToken = null;

        Locker::lock('test-key')
            ->type('fencing')
            ->ttl(10)
            ->run(function($token) use (&$receivedToken) {
                $receivedToken = $token;
            });

        $this->assertNotNull($receivedToken);
        $this->assertIsInt($receivedToken);
        $this->assertGreaterThan(0, $receivedToken);
    }
}

