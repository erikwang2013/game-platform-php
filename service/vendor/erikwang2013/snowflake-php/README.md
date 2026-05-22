# Snowflake PHP

A distributed unique ID generator based on Twitter's Snowflake algorithm, compatible with Laravel, Webman, ThinkPHP, and Hyperf.

> 中文文档请参阅 [README.zh-CN.md](README.zh-CN.md)

## About

Snowflake PHP generates 64-bit, k-ordered, globally unique IDs without requiring a central coordinator. Each ID is composed of a timestamp, datacenter ID, worker ID, and sequence number — allowing tens of thousands of IDs per second per node with no database round-trips.

Key features:

- **Pure PHP, zero dependencies** — no extensions or external services required
- **Pluggable sequence resolvers** — built-in sequential and random strategies, or bring your own
- **Flexible bit allocation** — adjust timestamp/worker/datacenter/sequence bits to fit your scale
- **Clock drift tolerance** — configurable tolerance window for NTP adjustments
- **Framework agnostic** with first-class adapters for Laravel, ThinkPHP, Webman, and Hyperf
- **ID parsing** — decompose generated IDs back into timestamp, node, and sequence components

## Requirements

- PHP >= 8.0
- 64-bit system (required for native 64-bit integer operations)

## Installation

```bash
composer require erikwang2013/snowflake-php
```

## Quick Start

```php
use Snowflake\Snowflake;

$snowflake = new Snowflake();
$id = $snowflake->id();          // e.g. 508047278033704960
$id = $snowflake->nextId();      // alias for id()
```

With custom worker and datacenter IDs:

```php
$snowflake = new Snowflake(workerId: 5, datacenterId: 3);
$id = $snowflake->id();
```

## Configuration Reference

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `epoch` | int | `1704067200000` | Custom epoch in ms (default: 2024-01-01 UTC) |
| `worker_id` | int | `0` | Worker/node identifier |
| `datacenter_id` | int | `0` | Datacenter identifier |
| `worker_bits` | int | `5` | Bits for worker ID |
| `datacenter_bits` | int | `5` | Bits for datacenter ID |
| `sequence_bits` | int | `12` | Bits for sequence number |
| `sequence_resolver` | string | `SequentialSequenceResolver` | FQCN of SequenceResolver |
| `clock_tolerance_ms` | int | `0` | Max backward clock drift (0 = strict) |

### Bit Layout

Default layout (63 data bits + 1 sign bit = 64 bits total):

```
| reserved(1) |  timestamp(41)   | datacenter(5) | worker(5) | sequence(12) |
```

Maximum lifespan with default epoch: ~69 years (until ~2093).

### Using Configuration Array

```php
$snowflake = Snowflake::fromConfig([
    'worker_id' => 1,
    'datacenter_id' => 2,
    'epoch' => 1704067200000,
]);
$id = $snowflake->id();
```

## Framework Integration

### Laravel

The package supports Laravel auto-discovery. After installation:

1. Publish the config (optional):
```bash
php artisan vendor:publish --tag=snowflake-config
```

2. Configure environment variables in `.env`:
```env
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

3. Use the Facade or dependency injection:
```php
// Facade
use Snowflake;
$id = Snowflake::id();

// Dependency injection
use Snowflake\Snowflake;

class OrderController
{
    public function store(Snowflake $snowflake)
    {
        $orderId = $snowflake->id();
    }
}

// Container access
$id = app('snowflake')->id();
$id = app(Snowflake::class)->id();
```

### Webman

1. Copy the plugin config to your project:
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/Webman/config/app.php \
   config/plugin/erikwang2013/snowflake-php/app.php
```

2. Register a singleton in `process.php` or bootstrap:
```php
use Snowflake\Snowflake;

Worker::$container->add(Snowflake::class, function () {
    return Snowflake::fromConfig(
        config('plugin.erikwang2013.snowflake-php.app.snowflake')
    );
});
```

3. Usage:
```php
$id = Worker::$container->get(Snowflake::class)->id();
```

### ThinkPHP 6+

1. Copy the config file to your project:
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/ThinkPHP/config/snowflake.php \
   config/snowflake.php
```

2. Register the service in `app/service.php`:
```php
return [
    \Snowflake\Adapters\ThinkPHP\Service::class,
];
```

3. Usage:
```php
// Container
$id = app('snowflake')->id();

// Dependency injection
use Snowflake\Snowflake;

class IndexController
{
    public function index(Snowflake $snowflake)
    {
        $id = $snowflake->id();
    }
}

// Facade
use Snowflake\Adapters\ThinkPHP\Facade;
$id = Facade::id();
```

### Hyperf

1. Publish the config:
```bash
php bin/hyperf.php vendor:publish erikwang2013/snowflake-php
```

2. Register the DI binding in `config/autoload/dependencies.php`:
```php
use Snowflake\Snowflake;

return [
    Snowflake::class => function () {
        return Snowflake::fromConfig(config('snowflake'));
    },
];
```

3. Usage via constructor injection:
```php
use Snowflake\Snowflake;

class OrderService
{
    public function __construct(private Snowflake $snowflake) {}

    public function create(): int
    {
        return $this->snowflake->id();
    }
}
```

## ID Parsing

Decompose a Snowflake ID into its components:

```php
$id = $snowflake->id();

// Using the instance (respects custom bit layout)
$parsed = $snowflake->parseId($id);
// [
//     'timestamp_ms' => 1736380800123,
//     'datetime'     => '2025-01-09 00:00:00.123',
//     'worker_id'    => 5,
//     'datacenter_id' => 3,
//     'sequence'     => 42,
// ]

// Static method (uses default bit layout)
$parsed = Snowflake::parse($id, $epoch);
```

## Sequence Resolvers

Two built-in implementations:

### SequentialSequenceResolver (default)

Classic Snowflake behavior. Sequence starts at 0 each millisecond and increments sequentially. Guarantees monotonically increasing IDs within a single node.

```php
use Snowflake\Resolvers\SequentialSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new SequentialSequenceResolver()
);
```

### RandomSequenceResolver

Starts each millisecond with a random sequence number. Makes IDs less predictable (prevents enumeration) but IDs within the same millisecond are not monotonic.

```php
use Snowflake\Resolvers\RandomSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new RandomSequenceResolver()
);
```

### Custom Resolver

Implement `Snowflake\Contracts\SequenceResolver`:

```php
use Snowflake\Contracts\SequenceResolver;

class RedisSequenceResolver implements SequenceResolver
{
    public function next(int $timestamp, int $maxSequence): ?int
    {
        $key = "snowflake:seq:{$timestamp}";
        $seq = redis()->incr($key);
        redis()->expire($key, 1);

        if ($seq > $maxSequence) {
            return null;
        }

        return $seq - 1;
    }
}
```

## Exception Handling

| Exception | When |
|-----------|------|
| `InvalidWorkerIdException` | Worker ID exceeds `2^worker_bits - 1` |
| `InvalidDatacenterIdException` | Datacenter ID exceeds `2^datacenter_bits - 1` |
| `ClockDriftException` | System clock moved backwards beyond tolerance |
| `TimestampOverflowException` | Epoch has been exhausted (lifespan ended) |
| `SnowflakeException` | Base exception for all package exceptions |

## Distributed Deployment

When running across multiple servers or processes, ensure each instance uses a unique `(datacenter_id, worker_id)` pair:

```php
// Read from environment, hostname hash, or service discovery
$workerId = (int) getenv('WORKER_ID');
$datacenterId = (int) getenv('DC_ID');

$snowflake = new Snowflake(
    workerId: $workerId,
    datacenterId: $datacenterId
);
```

With the default 5+5 bit layout, you can support up to 32 datacenters × 32 workers = 1024 unique nodes.

To support more workers, adjust bit allocation:

```php
// 10 worker bits = 1024 workers, 0 datacenter bits = single DC
$snowflake = new Snowflake(
    workerId: $workerId,
    workerBits: 10,
    datacenterBits: 0
);
```

## Performance

Typical throughput on modern hardware: **~500,000 IDs/second** (single process).

IDs are generated purely in-process with no external dependencies. The primary bottleneck is PHP's `microtime()` call and integer bit operations, both of which are O(1).

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
