# Snowflake PHP

基于 Twitter Snowflake 算法的分布式唯一 ID 生成器，兼容 Laravel、Webman、ThinkPHP、Hyperf 框架。

## 项目说明

Snowflake PHP 无需中心协调节点即可生成 64 位、k-ordered、全局唯一的 ID。每个 ID 由时间戳、数据中心 ID、工作节点 ID 和序列号组合而成——单节点每毫秒可生成数千个 ID，无需数据库往返。

核心特性：

- **纯 PHP，零依赖** — 无需扩展或外部服务
- **可插拔序列号策略** — 内置顺序递增和随机两种策略，支持自定义
- **灵活的位分配** — 可调整时间戳/节点/数据中心/序列号的位数以适应业务规模
- **时钟回拨容忍** — 可配置的 NTP 校时容忍窗口
- **框架无关**，提供 Laravel、ThinkPHP、Webman、Hyperf 的一流适配器
- **ID 解析** — 可将生成的 ID 反向分解为时间戳、节点、序列号等成分

## 环境要求

- PHP >= 8.0
- 64 位系统（64 位整数运算所必需）

## 安装

```bash
composer require erikwang2013/snowflake-php
```

## 快速开始

```php
use Snowflake\Snowflake;

$snowflake = new Snowflake();
$id = $snowflake->id();          // 例如 508047278033704960
$id = $snowflake->nextId();      // id() 的别名
```

指定 worker ID 和 datacenter ID：

```php
$snowflake = new Snowflake(workerId: 5, datacenterId: 3);
$id = $snowflake->id();
```

## 配置说明

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `epoch` | int | `1704067200000` | 自定义起始时间戳（毫秒），默认 2024-01-01 UTC |
| `worker_id` | int | `0` | 工作节点标识 |
| `datacenter_id` | int | `0` | 数据中心标识 |
| `worker_bits` | int | `5` | Worker ID 占用的位数 |
| `datacenter_bits` | int | `5` | Datacenter ID 占用的位数 |
| `sequence_bits` | int | `12` | 序列号占用的位数 |
| `sequence_resolver` | string | `SequentialSequenceResolver` | 序列号策略的完整类名 |
| `clock_tolerance_ms` | int | `0` | 允许的时钟回拨最大值（毫秒），0 为严格模式 |

### 位分配

默认布局（63 数据位 + 1 符号位 = 64 位）：

```
| 保留(1) |     时间戳(41)     | 数据中心(5) | 工作节点(5) | 序列号(12) |
```

默认起始时间下的最大可用年限：约 69 年（至 2093 年）。

### 通过配置数组创建

```php
$snowflake = Snowflake::fromConfig([
    'worker_id' => 1,
    'datacenter_id' => 2,
    'epoch' => 1704067200000,
]);
$id = $snowflake->id();
```

## 框架集成

### Laravel

包已支持 Laravel 自动发现。安装后：

1. 发布配置文件（可选）：
```bash
php artisan vendor:publish --tag=snowflake-config
```

2. 在 `.env` 中配置环境变量：
```env
SNOWFLAKE_WORKER_ID=1
SNOWFLAKE_DATACENTER_ID=1
```

3. 使用 Facade 或依赖注入：
```php
// Facade
use Snowflake;
$id = Snowflake::id();

// 依赖注入
use Snowflake\Snowflake;

class OrderController
{
    public function store(Snowflake $snowflake)
    {
        $orderId = $snowflake->id();
    }
}

// 容器访问
$id = app('snowflake')->id();
$id = app(Snowflake::class)->id();
```

### Webman

1. 将插件配置复制到项目中：
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/Webman/config/app.php \
   config/plugin/erikwang2013/snowflake-php/app.php
```

2. 在 `process.php` 或启动文件中注册单例：
```php
use Snowflake\Snowflake;

Worker::$container->add(Snowflake::class, function () {
    return Snowflake::fromConfig(
        config('plugin.erikwang2013.snowflake-php.app.snowflake')
    );
});
```

3. 使用：
```php
$id = Worker::$container->get(Snowflake::class)->id();
```

### ThinkPHP 6+

1. 复制配置文件到项目：
```bash
cp vendor/erikwang2013/snowflake-php/src/Adapters/ThinkPHP/config/snowflake.php \
   config/snowflake.php
```

2. 在 `app/service.php` 中注册服务：
```php
return [
    \Snowflake\Adapters\ThinkPHP\Service::class,
];
```

3. 使用：
```php
// 容器
$id = app('snowflake')->id();

// 依赖注入
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

1. 发布配置：
```bash
php bin/hyperf.php vendor:publish erikwang2013/snowflake-php
```

2. 在 `config/autoload/dependencies.php` 中注册 DI 绑定：
```php
use Snowflake\Snowflake;

return [
    Snowflake::class => function () {
        return Snowflake::fromConfig(config('snowflake'));
    },
];
```

3. 通过构造函数注入使用：
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

## ID 解析

将 Snowflake ID 分解为各个组成部分：

```php
$id = $snowflake->id();

// 实例方法（使用当前实例的位分配）
$parsed = $snowflake->parseId($id);
// [
//     'timestamp_ms' => 1736380800123,
//     'datetime'     => '2025-01-09 00:00:00.123',
//     'worker_id'    => 5,
//     'datacenter_id' => 3,
//     'sequence'     => 42,
// ]

// 静态方法（使用默认位分配）
$parsed = Snowflake::parse($id, $epoch);
```

## 序列号策略

内置两种实现：

### SequentialSequenceResolver（默认）

经典的 Snowflake 行为。每个毫秒序列号从 0 开始顺序递增，保证单节点内 ID 严格单调递增。

```php
use Snowflake\Resolvers\SequentialSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new SequentialSequenceResolver()
);
```

### RandomSequenceResolver

每个毫秒从随机位置开始。ID 不易被猜测（防止遍历攻击），但同一毫秒内的 ID 不保证单调递增。

```php
use Snowflake\Resolvers\RandomSequenceResolver;

$snowflake = new Snowflake(
    sequenceResolver: new RandomSequenceResolver()
);
```

### 自定义策略

实现 `Snowflake\Contracts\SequenceResolver` 接口：

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

## 异常处理

| 异常类 | 触发条件 |
|--------|----------|
| `InvalidWorkerIdException` | Worker ID 超出 `2^worker_bits - 1` |
| `InvalidDatacenterIdException` | Datacenter ID 超出 `2^datacenter_bits - 1` |
| `ClockDriftException` | 系统时钟回拨超过容忍值 |
| `TimestampOverflowException` | 时间戳偏移超过最大值（epoch 已耗尽） |
| `SnowflakeException` | 所有包异常的基类 |

## 分布式部署

在多服务器或进程部署时，确保每个实例使用唯一的 `(datacenter_id, worker_id)` 组合：

```php
// 从环境变量、主机名哈希或服务发现中获取
$workerId = (int) getenv('WORKER_ID');
$datacenterId = (int) getenv('DC_ID');

$snowflake = new Snowflake(
    workerId: $workerId,
    datacenterId: $datacenterId
);
```

默认 5+5 位分配可支持 32 个数据中心 × 32 个工作节点 = 1024 个独立节点。

如需更多节点，调整位分配：

```php
// 10 worker 位 = 1024 个节点，0 datacenter 位 = 单数据中心
$snowflake = new Snowflake(
    workerId: $workerId,
    workerBits: 10,
    datacenterBits: 0
);
```

## 性能

现代硬件典型吞吐量：**~50 万 ID/秒**（单进程）。

ID 生成完全在进程内完成，无需外部依赖。主要开销来自 PHP 的 `microtime()` 调用和整数位运算，均为 O(1)。

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
