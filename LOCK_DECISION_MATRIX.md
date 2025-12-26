# Lock Decision Matrix

Use this matrix to choose the right lock type for your use case.

## Quick Reference

| Lock Type | Use Case | Ownership | Reentrant | Concurrent | Driver Support |
|-----------|----------|-----------|-----------|------------|-----------------|
| **Simple** | Basic locking, single process | No | No | No | All |
| **Safe** | Prevent accidental unlock | Yes | No | No | All |
| **Redlock** | Distributed systems, Redis | Yes | No | No | Redis only |
| **Reentrant** | Nested operations, same owner | Yes | Yes | No | All |
| **Read-Write** | Multiple readers, exclusive writer | Yes | No | Readers: Yes | All |
| **Semaphore** | Rate limiting, concurrency control | Yes | No | Yes (N permits) | All |
| **Fair** | FIFO ordering, prevent starvation | Yes | No | No | All |
| **Fencing Token** | Distributed systems, ordering | Yes | No | No | All |
| **Striped** | High contention, sharding | Yes | No | No | All |
| **Multi-Resource** | Multiple resources, deadlock prevention | Yes | No | No | All |
| **Watchdog** | Long-running tasks, auto-renewal | Yes | No | No | All |
| **Leased** | Hard expiration, explicit renewal | Yes | No | No | All |

## Detailed Use Cases

### Simple Lock
**When to use:**
- Basic mutual exclusion
- Single process/thread scenarios
- Simple resource protection
- Testing and development

**Example:**
```php
Locker::simple('cache-warm', fn() => warmCache());
```

### Safe Lock
**When to use:**
- Multi-process environments
- Need to prevent accidental unlock by other processes
- Critical operations where ownership matters

**Example:**
```php
Locker::safe('payment-'.$orderId, fn() => processPayment())
    ->owner(auth()->id());
```

### Redlock
**When to use:**
- Distributed systems with multiple Redis instances
- High availability requirements
- Need quorum-based consensus
- Redis-only environments

**Example:**
```php
Locker::lock('distributed-task')
    ->type('redlock')
    ->ttl(60)
    ->run(fn() => processDistributedTask());
```

### Reentrant Lock
**When to use:**
- Nested function calls
- Recursive operations
- Same owner needs multiple acquisitions
- Complex call hierarchies

**Example:**
```php
Locker::reentrant('order-'.$id, function() {
    // Can call other functions that acquire same lock
    $this->validateOrder();
    $this->processPayment();
});
```

### Read-Write Lock
**When to use:**
- Multiple readers, single writer pattern
- Read-heavy workloads
- Configuration management
- Caching scenarios

**Example:**
```php
// Multiple readers
Locker::read('config', fn() => $config = getConfig());

// Exclusive writer
Locker::write('config', fn() => updateConfig($data));
```

### Semaphore Lock
**When to use:**
- Rate limiting
- Concurrency control
- API throttling
- Resource pooling

**Example:**
```php
Locker::semaphore('api-calls', 10, fn() => callAPI());
```

### Fair Lock
**When to use:**
- Need FIFO ordering
- Prevent starvation
- Fair resource allocation
- Queue-like behavior

**Example:**
```php
Locker::lock('job-queue')
    ->type('fair')
    ->ttl(30)
    ->run(fn() => processJob());
```

### Fencing Token Lock
**When to use:**
- Distributed systems
- Need monotonic ordering
- Prevent split-brain scenarios
- Database replication

**Example:**
```php
Locker::lock('database-write')
    ->type('fencing')
    ->ttl(30)
    ->run(function($token) {
        // Use $token to order writes
        writeWithToken($token, $data);
    });
```

### Striped Lock
**When to use:**
- High contention scenarios
- Need to reduce lock contention
- Hash-based sharding
- Performance optimization

**Example:**
```php
Locker::lock('user-'.$userId)
    ->type('striped')
    ->shardCount(16)
    ->ttl(30)
    ->run(fn() => updateUser());
```

### Multi-Resource Lock
**When to use:**
- Need to lock multiple resources atomically
- Deadlock prevention
- Transaction-like behavior
- Account transfers

**Example:**
```php
Locker::lock(['account-1', 'account-2'])
    ->type('multi')
    ->ttl(30)
    ->run(fn() => transferMoney());
```

### Watchdog Lock
**When to use:**
- Long-running tasks
- Need automatic TTL renewal
- Background jobs
- Video/image processing

**Example:**
```php
Locker::lock('video-processing')
    ->type('watchdog')
    ->ttl(300)
    ->renewEvery(60)
    ->run(fn() => processVideo());
```

### Leased Lock
**When to use:**
- Hard expiration requirements
- Need explicit renewal
- Guarantee eventual release
- Crash-safe scenarios

**Example:**
```php
$lock = Locker::lock('long-task')
    ->type('leased')
    ->ttl(60)
    ->acquire();

try {
    while ($workRemaining) {
        doWork();
        $lock->renew(); // Extend lease
    }
} finally {
    $lock->release();
}
```

## Performance Considerations

| Lock Type | Performance | Complexity | Overhead |
|-----------|------------|------------|----------|
| Simple | ⭐⭐⭐⭐⭐ | ⭐ | Low |
| Safe | ⭐⭐⭐⭐ | ⭐⭐ | Low |
| Redlock | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | High |
| Reentrant | ⭐⭐⭐⭐ | ⭐⭐⭐ | Medium |
| Read-Write | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | Medium |
| Semaphore | ⭐⭐⭐⭐ | ⭐⭐⭐ | Medium |
| Fair | ⭐⭐⭐ | ⭐⭐⭐⭐ | Medium |
| Fencing Token | ⭐⭐⭐⭐ | ⭐⭐ | Low |
| Striped | ⭐⭐⭐⭐⭐ | ⭐⭐ | Low |
| Multi-Resource | ⭐⭐⭐ | ⭐⭐⭐⭐ | Medium |
| Watchdog | ⭐⭐⭐ | ⭐⭐⭐⭐ | Medium |
| Leased | ⭐⭐⭐⭐ | ⭐⭐⭐ | Medium |

## Driver Compatibility

| Lock Type | Redis | Database | File | Memcached | Array |
|-----------|-------|----------|------|-----------|-------|
| Simple | ✅ | ✅ | ✅ | ✅ | ✅ |
| Safe | ✅ | ✅ | ✅ | ✅ | ✅ |
| Redlock | ✅ | ❌ | ❌ | ❌ | ❌ |
| Reentrant | ✅ | ✅ | ✅ | ✅ | ✅ |
| Read-Write | ✅ | ✅ | ✅ | ✅ | ✅ |
| Semaphore | ✅ | ✅ | ✅ | ✅ | ✅ |
| Fair | ✅ | ✅ | ✅ | ✅ | ✅ |
| Fencing Token | ✅ | ✅ | ✅ | ✅ | ✅ |
| Striped | ✅ | ✅ | ✅ | ✅ | ✅ |
| Multi-Resource | ✅ | ✅ | ✅ | ✅ | ✅ |
| Watchdog | ✅ | ✅ | ✅ | ✅ | ✅ |
| Leased | ✅ | ✅ | ✅ | ✅ | ✅ |

## Decision Tree

```
Need distributed lock with Redis?
├─ Yes → Redlock
└─ No
   ├─ Need multiple concurrent holders?
   │  ├─ Yes → Semaphore
   │  └─ No
   │     ├─ Need multiple readers?
   │     │  ├─ Yes → Read-Write Lock
   │     │  └─ No
   │     │     ├─ Need reentrant?
   │     │     │  ├─ Yes → Reentrant Lock
   │     │     │  └─ No
   │     │     │     ├─ Need FIFO ordering?
   │     │     │     │  ├─ Yes → Fair Lock
   │     │     │     │  └─ No
   │     │     │     │     ├─ Need multiple resources?
   │     │     │     │     │  ├─ Yes → Multi-Resource Lock
   │     │     │     │     │  └─ No
   │     │     │     │     │     ├─ Need auto-renewal?
   │     │     │     │     │     │  ├─ Yes → Watchdog Lock
   │     │     │     │     │     │  └─ No
   │     │     │     │     │     │     ├─ Need hard expiration?
   │     │     │     │     │     │     │  ├─ Yes → Leased Lock
   │     │     │     │     │     │     │  └─ No
   │     │     │     │     │     │     │     ├─ Need fencing token?
   │     │     │     │     │     │     │     │  ├─ Yes → Fencing Token Lock
   │     │     │     │     │     │     │     │  └─ No
   │     │     │     │     │     │     │     │     ├─ High contention?
   │     │     │     │     │     │     │     │     │  ├─ Yes → Striped Lock
   │     │     │     │     │     │     │     │     │  └─ No
   │     │     │     │     │     │     │     │     │     ├─ Need ownership validation?
   │     │     │     │     │     │     │     │     │     │  ├─ Yes → Safe Lock
   │     │     │     │     │     │     │     │     │     │  └─ No → Simple Lock
```

## Best Practices

1. **Always set appropriate TTL**: Ensure locks expire even if process crashes
2. **Use Safe Lock for multi-process**: Prevents accidental unlock
3. **Use Reentrant for nested calls**: Avoid deadlocks in complex code
4. **Use Read-Write for read-heavy**: Improves concurrency
5. **Use Semaphore for rate limiting**: Control concurrent access
6. **Use Multi-Resource for transactions**: Atomic multi-lock acquisition
7. **Use Watchdog for long tasks**: Auto-renewal prevents expiration
8. **Use Leased for critical operations**: Hard expiration guarantees release
9. **Monitor lock events**: Track lock acquisition and release
10. **Test with your cache driver**: Verify behavior with your setup

