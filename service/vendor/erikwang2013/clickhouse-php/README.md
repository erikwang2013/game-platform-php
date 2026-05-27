# clickhouse-php

PHP ClickHouse 客户端，支持 HTTP 与 Native TCP 双协议，内置查询构建器、Schema Builder、迁移系统和 ORM，深度适配 Laravel、ThinkPHP、Webman、Hyperf。

[English Documentation](README_EN.md)

## 安装

```bash
composer require erikwang2013/clickhouse-php
```

## 快速开始

### 独立使用

```php
use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Client\Manager;

// 配置
$config = [
    'default' => 'clickhouse',
    'connections' => [
        'clickhouse' => [
            'driver'   => 'http',        // http 或 native
            'host'     => 'localhost',
            'port'     => 8123,          // HTTP 端口, Native 用 9000
            'database' => 'default',
            'username' => 'default',
            'password' => '',
            'timeout'  => 30,
        ],
    ],
];

// 初始化
$manager = new Manager($config);
ClickHouse::setManager($manager);

// 查询
$rows = ClickHouse::table('logs')
    ->where('date', '>=', '2024-01-01')
    ->whereIn('level', ['error', 'warn'])
    ->orderBy('timestamp', 'desc')
    ->limit(100)
    ->get();

foreach ($rows as $row) {
    echo $row['message'];
}

// 原生 SQL
$result = ClickHouse::query('SELECT count(*) AS cnt FROM logs WHERE date = ?', ['2024-01-01']);
echo $result->first()['cnt'];

// 聚合
$count = ClickHouse::table('logs')->where('level', 'error')->count();
$avgDuration = ClickHouse::table('logs')->avg('duration');
```

### 插入数据

```php
// 单行
ClickHouse::table('logs')->insert([
    'date'      => '2024-01-01',
    'level'     => 'info',
    'message'   => 'hello',
    'duration'  => 12.5,
]);

// 批量
ClickHouse::table('logs')->insert([
    ['date' => '2024-01-01', 'level' => 'info',  'message' => 'a', 'duration' => 1.2],
    ['date' => '2024-01-02', 'level' => 'error', 'message' => 'b', 'duration' => 3.4],
]);
```

### 建表 (Schema Builder)

```php
ClickHouse::schema()->create('logs', function ($table) {
    $table->date('date');
    $table->dateTime('timestamp');
    $table->string('level');
    $table->string('message');
    $table->float64('duration');
    $table->engine('MergeTree')
          ->partitionBy('toYYYYMM(date)')
          ->orderBy(['date', 'timestamp', 'level']);
});

// 删除表
ClickHouse::schema()->drop('logs');

// 修改表
ClickHouse::schema()->alter('logs', function ($table) {
    $table->nullable('source', 'String');
});
```

### 数据迁移

创建迁移文件（如 `2026_05_27_000000_create_logs_table.php`）：

```php
use Erikwang2013\ClickHouse\Migration\Migration;

class CreateLogsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('logs', function ($table) {
            $table->date('date');
            $table->string('level');
            $table->engine('MergeTree')
                  ->partitionBy('toYYYYMM(date)')
                  ->orderBy(['date', 'level']);
        });
    }

    public function down(): void
    {
        $this->schema->drop('logs');
    }
}
```

运行迁移：

```php
use Erikwang2013\ClickHouse\Migration\Migrator;
use Erikwang2013\ClickHouse\Migration\Repository;

$client = ClickHouse::getManager()->connection();
$repository = new Repository($client);
$migrator = new Migrator($client, $repository, '/path/to/migrations');

$migrator->install();   // 创建迁移记录表
$migrator->run();       // 执行待迁移
$migrator->rollback();  // 回滚上一批
$migrator->refresh();   // 回滚后重新执行
```

### ORM

```php
use Erikwang2013\ClickHouse\ORM\Model;

class Log extends Model
{
    protected string $table = 'logs';
    protected string $connection = 'default';
}

// 查询
$logs = Log::where('level', 'error')
    ->orderBy('timestamp', 'desc')
    ->limit(50)
    ->get();

// 查找单条
$log = Log::find(123);

// 聚合
$total = Log::where('date', '>=', '2024-01-01')->count();

// 批量插入
Log::insert([
    ['date' => '2024-01-01', 'level' => 'info'],
    ['date' => '2024-01-02', 'level' => 'error'],
]);
```

### 多连接

```php
$config = [
    'default' => 'default',
    'connections' => [
        'default' => ['driver' => 'http', 'host' => 'ch1.example.com', 'port' => 8123],
        'analytics' => ['driver' => 'http', 'host' => 'ch2.example.com', 'port' => 8123],
    ],
];

$manager = new Manager($config);

// 使用指定连接
ClickHouse::connection('analytics')->table('events')->get();
ClickHouse::table('events', 'analytics')->get();
```

## 框架集成

### Laravel

配置文件自动发布，Composer 自动发现 ServiceProvider。

```bash
php artisan vendor:publish --tag=clickhouse-config
```

```php
use Erikwang2013\ClickHouse\Laravel\Facades\ClickHouse;

ClickHouse::table('logs')->where('level', 'error')->get();
ClickHouse::connection('native')->select('SELECT * FROM logs LIMIT 10');
```

Artisan 命令：

```bash
php artisan clickhouse:table-list
php artisan clickhouse:migration:install
php artisan clickhouse:migration:run
```

### ThinkPHP

在 `app/service.php` 中注册服务：

```php
return [
    \Erikwang2013\ClickHouse\ThinkPHP\ClickHouseService::class,
];
```

```php
use think\facade\ClickHouse;

ClickHouse::table('logs')->get();
```

```bash
php think clickhouse:table-list
```

### Webman

Webman 自动加载插件配置，无需手动配置。

```php
use Erikwang2013\ClickHouse\Webman\ClickHouse;

ClickHouse::table('logs')->where('level', 'error')->get();
```

### Hyperf

通过 `ConfigProvider` 自动发现，支持依赖注入和协程连接池。

```bash
php bin/hyperf.php vendor:publish erikwang2013/clickhouse-php
```

```php
use Erikwang2013\ClickHouse\Hyperf\ClickHouseConnection;

class LogController
{
    public function __construct(
        private ClickHouseConnection $clickhouse
    ) {}

    public function index()
    {
        return $this->clickhouse->table('logs')->get();
    }
}
```

## 查询构建器参考

| 方法 | 说明 |
|------|------|
| `table($name)` / `from($name)` | 指定表名 |
| `select([...])` / `selectRaw($expr)` | SELECT 列 |
| `where($col, $op, $val)` | 条件 (2 参数时 `$op` 默认 `=`) |
| `orWhere($col, $op, $val)` | OR 条件 |
| `whereIn($col, $arr)` / `whereNotIn($col, $arr)` | IN / NOT IN |
| `whereBetween($col, [$min, $max])` | BETWEEN |
| `whereNull($col)` / `whereNotNull($col)` | IS NULL / IS NOT NULL |
| `whereRaw($sql)` | 原生 WHERE |
| `orderBy($col, $dir)` | 排序 (默认 ASC) |
| `groupBy(...$cols)` | 分组 |
| `limit($n)` / `offset($n)` | 分页 |
| `count()` / `sum($col)` / `avg($col)` / `min($col)` / `max($col)` | 聚合 |
| `insert($data)` | 插入 (单行或批量) |
| `delete()` | 删除 |
| `get()` | 执行查询，返回 Result |
| `first()` | 返回第一条 |
| `toSql()` | 获取生成的 SQL |

## Schema 列类型

| 方法 | ClickHouse 类型 |
|------|----------------|
| `string($name)` | String |
| `fixedString($name, $len)` | FixedString(N) |
| `int8/16/32/64($name)` | Int8/16/32/64 |
| `uint8/16/32/64($name)` | UInt8/16/32/64 |
| `float32($name)` / `float64($name)` | Float32 / Float64 |
| `decimal($name, $p, $s)` | Decimal(P, S) |
| `date($name)` | Date |
| `dateTime($name)` | DateTime |
| `dateTime64($name, $p)` | DateTime64(P) |
| `uuid($name)` | UUID |
| `bool($name)` | Bool |
| `array($name, $type)` | Array(T) |
| `nullable($name, $type)` | Nullable(T) |
| `lowCardinality($name, $type)` | LowCardinality(T) |

## 配置参考

```php
[
    'default' => 'clickhouse',
    'connections' => [
        'clickhouse' => [
            'driver'   => 'http',     // http | native
            'host'     => 'localhost',
            'port'     => 8123,       // HTTP 8123, Native 9000
            'database' => 'default',
            'username' => 'default',
            'password' => '',
            'timeout'  => 30,
        ],
    ],
    'pool' => [
        'min_connections'    => 2,
        'max_connections'    => 16,
        'connection_timeout' => 5.0,
    ],
    'query_log' => false,
];
```

## 环境变量

| 变量 | 默认值 | 说明 |
|------|--------|------|
| `CLICKHOUSE_HOST` | localhost | 主机地址 |
| `CLICKHOUSE_PORT` | 8123 | HTTP 端口 |
| `CLICKHOUSE_DB` | default | 数据库名 |
| `CLICKHOUSE_USER` | default | 用户名 |
| `CLICKHOUSE_PASS` | — | 密码 |
| `CLICKHOUSE_TIMEOUT` | 30 | 连接超时(秒) |
| `CLICKHOUSE_DRIVER` | http | 驱动类型 |
| `CLICKHOUSE_POOL_MIN` | 2 | 最小连接数 |
| `CLICKHOUSE_POOL_MAX` | 16 | 最大连接数 |

## 异常处理

```php
use Erikwang2013\ClickHouse\Exceptions\{
    ClickHouseException,
    ConnectionException,
    QueryException,
    TimeoutException,
    PoolException,
};

try {
    ClickHouse::table('logs')->get();
} catch (ConnectionException | TimeoutException $e) {
    // 连接或超时问题
} catch (QueryException $e) {
    echo $e->getMessage();
    echo $e->getSql();      // 原始 SQL
} catch (ClickHouseException $e) {
    // 其他异常
}
```

## 许可证

MIT License.  Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
