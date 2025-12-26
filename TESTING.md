# Running Tests

## Prerequisites

1. PHP 8.1 or higher
2. Composer installed

## Setup

1. Install dependencies:
```bash
composer install
```

## Running Tests

### Run all tests
```bash
composer test
```

Or directly with PHPUnit:
```bash
vendor/bin/phpunit
```

### Run specific test suite

**Unit tests only:**
```bash
vendor/bin/phpunit --testsuite Unit
```

**Integration tests only:**
```bash
vendor/bin/phpunit --testsuite Integration
```

### Run a specific test file
```bash
vendor/bin/phpunit tests/Unit/SimpleLockTest.php
```

### Run a specific test method
```bash
vendor/bin/phpunit --filter it_can_acquire_and_release_a_simple_lock
```

### Run with coverage report
```bash
composer test:coverage
```

Or:
```bash
vendor/bin/phpunit --coverage-html coverage
```

The coverage report will be generated in the `coverage/` directory. Open `coverage/index.html` in your browser to view it.

### Run tests with verbose output
```bash
vendor/bin/phpunit --verbose
```

### Run tests and stop on first failure
```bash
vendor/bin/phpunit --stop-on-failure
```

## Test Structure

```
tests/
├── TestCase.php          # Base test case
├── Unit/                 # Unit tests
│   └── SimpleLockTest.php
└── Integration/          # Integration tests
```

## Writing Tests

### Example Unit Test

```php
<?php

namespace Mvonline\Locker\Tests\Unit;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;

class MyLockTest extends TestCase
{
    /** @test */
    public function it_can_acquire_a_lock()
    {
        $lock = Locker::lock('test-key')
            ->type('simple')
            ->ttl(10)
            ->acquire();

        $this->assertTrue($lock->isAcquired());
    }
}
```

### Example Integration Test

```php
<?php

namespace Mvonline\Locker\Tests\Integration;

use Mvonline\Locker\Tests\TestCase;
use Mvonline\Locker\Facades\Locker;

class CacheDriverTest extends TestCase
{
    /** @test */
    public function it_works_with_redis_driver()
    {
        $this->app['config']->set('cache.default', 'redis');
        
        $result = Locker::simple('test', fn() => 'success');
        
        $this->assertEquals('success', $result);
    }
}
```

## Testing with Different Cache Drivers

To test with different cache drivers, override the configuration in your test:

```php
protected function defineEnvironment($app): void
{
    $app['config']->set('cache.default', 'redis');
    // or 'database', 'file', 'memcached', 'array'
}
```

## Continuous Integration

For CI/CD pipelines, you can run tests with:

```bash
composer install --no-interaction --prefer-dist
composer test
```

## Troubleshooting

### Issue: "Class not found"
- Run `composer dump-autoload` to regenerate autoload files

### Issue: "PHPUnit not found"
- Make sure you've run `composer install` (not `composer install --no-dev`)
- Check that `vendor/bin/phpunit` exists

### Issue: Tests fail with cache errors
- Make sure your test environment is properly configured
- Check that the cache driver is available (for Redis, ensure Redis is running)

