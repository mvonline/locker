# Locker - Distributed Locking Framework for Laravel

A comprehensive, cache-driver-agnostic distributed locking framework for Laravel that extends Laravel's cache abstraction while remaining compatible with all native cache drivers.

## Features

- **12 Lock Types**: Simple, Safe, Redlock, Reentrant, Read-Write, Semaphore, Fair, Fencing Token, Striped, Multi-Resource, Watchdog, and Leased locks
- **Cache Driver Agnostic**: Works with Redis, Database, File, Memcached, and Array cache drivers
- **Fluent API**: Chainable, intuitive interface
- **Event System**: Comprehensive event dispatching for lock lifecycle
- **Automatic Release**: Locks are automatically released after callback execution
- **Blocking & Non-blocking**: Configurable retry logic with exponential backoff
- **Fully Tested**: Comprehensive test suite targeting 100% coverage

## Installation

```bash
composer require mvonline/locker
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=locker-config
```

## Quick Start

### Basic Usage

```php
use Mvonline\Locker\Facades\Locker;

// Simple lock with callback
Locker::lock('user-update-123')
    ->type('simple')
    ->ttl(10)
    ->run(fn () => User::find(123)->update($data));

// Blocking lock
Locker::lock('payment-process')
    ->type('safe')
    ->ttl(30)
    ->block(5)
    ->run(fn () => processPayment());
```

### Quick Helpers

```php
// Simple lock
Locker::simple('key', fn() => { /* work */ });

// Safe lock
Locker::safe('key', fn() => { /* work */ });

// Reentrant lock
Locker::reentrant('key', fn() => { /* work */ });

// Semaphore (5 concurrent)
Locker::semaphore('key', 5, fn() => { /* work */ });

// Read lock
Locker::read('key', fn() => { /* read */ });

// Write lock
Locker::write('key', fn() => { /* write */ });
```

### Using the HasLocks Trait

```php
use Mvonline\Locker\Traits\HasLocks;

class OrderProcessor
{
    use HasLocks;

    public function processOrder()
    {
        $this->lockResource('order-'.$this->id)
             ->type('reentrant')
             ->ttl(30)
             ->run(fn() => {
                 // critical section
             });
    }

    // Or use the simpler helper
    public function updateOrder()
    {
        $this->withLock('order-'.$this->id, fn() => {
            // protected code
        }, type: 'reentrant', ttl: 60);
    }
}
```

## Lock Types

### 1. Simple Lock
Basic atomic lock with no ownership validation.

```php
Locker::lock('resource')->type('simple')->ttl(10)->run(fn() => {});
```

### 2. Safe Lock
Lock with unique owner token to prevent accidental unlock.

```php
Locker::lock('resource')
    ->type('safe')
    ->owner(auth()->id())
    ->ttl(30)
    ->run(fn() => {});
```

### 3. Redlock (Redis-only)
Distributed lock using Redis Redlock algorithm with quorum.

```php
Locker::lock('resource')
    ->type('redlock')
    ->ttl(60)
    ->run(fn() => {});
```

### 4. Reentrant Lock
Same owner can re-acquire the lock multiple times.

```php
Locker::lock('resource')
    ->type('reentrant')
    ->owner(auth()->id())
    ->ttl(30)
    ->run(fn() => {
        // Can acquire same lock again inside
        Locker::lock('resource')
            ->type('reentrant')
            ->owner(auth()->id())
            ->run(fn() => {});
    });
```

### 5. Read-Write Lock
Multiple readers or exclusive writer.

```php
// Multiple readers allowed
Locker::read('config', fn() => readConfig());

// Exclusive writer
Locker::write('config', fn() => updateConfig($data));
```

### 6. Semaphore Lock
Allows N concurrent holders.

```php
Locker::lock('api-calls')
    ->type('semaphore')
    ->permits(10)
    ->acquire(1)
    ->block(2)
    ->run(fn() => callExternalApi());
```

### 7. Fair Lock
FIFO ordering prevents starvation.

```php
Locker::lock('resource')
    ->type('fair')
    ->ttl(30)
    ->run(fn() => {});
```

### 8. Fencing Token Lock
Monotonic token per acquisition prevents split-brain writes.

```php
Locker::lock('resource')
    ->type('fencing')
    ->ttl(30)
    ->run(function($token) {
        // Use $token for ordering operations
    });
```

### 9. Striped Lock
Hash-based sharding reduces contention.

```php
Locker::lock('resource')
    ->type('striped')
    ->shardCount(16)
    ->ttl(30)
    ->run(fn() => {});
```

### 10. Multi-Resource Lock
Atomic multi-lock acquisition with deadlock prevention.

```php
Locker::lock(['account-1', 'account-2'])
    ->type('multi')
    ->ttl(30)
    ->run(fn() => transferMoney());
```

### 11. Watchdog Lock
Auto-renewal of TTL before expiration.

```php
Locker::lock('video-processing')
    ->type('watchdog')
    ->ttl(60)
    ->renewEvery(15)
    ->run(fn() => processLargeVideo());
```

### 12. Leased Lock
Hard TTL expiration with explicit renewal required.

```php
$lock = Locker::lock('resource')
    ->type('leased')
    ->ttl(30)
    ->acquire();

try {
    doWork();
    $lock->renew(); // Extend lease
} finally {
    $lock->release();
}
```

## Manual Lock Control

```php
$lock = Locker::lock('resource')
    ->type('safe')
    ->ttl(30)
    ->acquire();

try {
    // Do work
} finally {
    $lock->release();
}
```

## Blocking with Retry

```php
Locker::lock('resource')
    ->type('safe')
    ->ttl(30)
    ->block(5) // Wait up to 5 seconds
    ->run(fn() => {});
```

## Events

The package dispatches events for lock lifecycle:

- `LockAcquired`: When a lock is successfully acquired
- `LockReleased`: When a lock is released
- `LockFailed`: When lock acquisition fails
- `LockTimeout`: When lock acquisition times out
- `LockExtended`: When a lock's TTL is extended

Listen to events:

```php
use Mvonline\Locker\Events\LockAcquired;

Event::listen(LockAcquired::class, function ($event) {
    Log::info("Lock acquired: {$event->key} by {$event->owner}");
});
```

## Status & Admin

```php
// Check if locked
Locker::isLocked('key');
Locker::isLocked('key', 'simple');

// Force release (use with caution)
Locker::forceRelease('key');
```

## Exceptions

The package throws custom exceptions:

- `LockAcquisitionException`: When lock acquisition fails
- `LockReleaseException`: When lock release fails
- `LockTimeoutException`: When lock acquisition times out
- `UnsupportedDriverException`: When lock type is not supported by driver
- `LockOwnershipException`: When lock ownership validation fails

## Cache Drivers

The package works with all Laravel cache drivers:

- **Redis**: Full support for all lock types including Redlock
- **Database**: Full support for all lock types
- **File**: Full support for all lock types
- **Memcached**: Full support for all lock types
- **Array**: Full support (for testing only)

## Testing

```bash
composer test
```

## Requirements

- PHP 8.1+
- Laravel 10+, 11+, or 12+

## License

MIT

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

