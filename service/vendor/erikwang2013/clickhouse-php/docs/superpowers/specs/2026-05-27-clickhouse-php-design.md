# ClickHouse PHP 客户端插件 - 设计文档

## 概述

`erikwang2013/clickhouse-php` 是一个 PHP ClickHouse 客户端 Composer 包，支持 HTTP 和 Native TCP 双协议，提供查询构建器、Schema Builder、迁移系统和 ORM，并深度适配 Laravel、ThinkPHP、Webman、Hyperf 四个框架。

## 技术决策

| 决策项 | 选择 |
|--------|------|
| 架构方案 | 单体包 + 框架适配器内嵌 |
| PHP 版本 | >= 8.1 |
| 传输协议 | HTTP + Native TCP 双协议 |
| HTTP 客户端 | Guzzle |
| 协程支持 | 统一 PoolInterface，四种框架各自实现 |
| 配置方式 | 框架原生配置格式 |

## 目录结构

```
clickhouse-php/
├── composer.json
├── .gitignore
├── README.md
│
├── src/
│   ├── ClickHouse.php              # 门面入口类
│   ├── Client/
│   │   ├── ClientInterface.php      # 客户端接口
│   │   ├── HttpClient.php           # HTTP 客户端
│   │   ├── NativeClient.php         # Native TCP 协议客户端
│   │   └── Manager.php              # 客户端管理器（多连接）
│   │
│   ├── Query/
│   │   ├── Builder.php              # 查询构建器（链式调用）
│   │   ├── Grammar.php              # SQL 语法生成
│   │   ├── Result.php               # 查询结果集
│   │   └── Expression.php           # 原生表达式
│   │
│   ├── Schema/
│   │   ├── Builder.php              # Schema 构建器
│   │   ├── Blueprint.php            # 表结构蓝图
│   │   ├── Column.php               # 列定义
│   │   └── Grammar.php              # DDL 语法生成
│   │
│   ├── Migration/
│   │   ├── Migration.php            # 迁移基类
│   │   ├── Migrator.php             # 迁移执行器
│   │   └── Repository.php           # 迁移记录存储
│   │
│   ├── ORM/
│   │   ├── Model.php                # ActiveRecord 基类
│   │   ├── Collection.php           # 模型集合
│   │   └── Relations/               # 关联关系（后续扩展）
│   │
│   ├── Pool/
│   │   ├── PoolInterface.php        # 连接池接口
│   │   ├── SwoolePool.php           # Swoole 协程连接池
│   │   ├── SwowPool.php             # Swow 协程连接池
│   │   ├── WorkermanPool.php        # Workerman 连接池
│   │   └── NoPool.php               # 无连接池（传统模式）
│   │
│   ├── Transport/
│   │   ├── TransportInterface.php   # 传输层接口
│   │   ├── HttpTransport.php        # HTTP 传输实现
│   │   └── TcpTransport.php         # TCP 传输实现
│   │
│   ├── Support/
│   │   ├── Config.php               # 配置管理
│   │   ├── Arr.php                  # 数组工具
│   │   └── Str.php                  # 字符串工具
│   │
│   ├── Laravel/
│   │   ├── ClickHouseServiceProvider.php
│   │   ├── Facades/ClickHouse.php
│   │   ├── Console/
│   │   │   ├── TableListCommand.php
│   │   │   ├── MigrationInstallCommand.php
│   │   │   └── MigrationRunCommand.php
│   │   └── config/clickhouse.php
│   │
│   ├── ThinkPHP/
│   │   ├── ClickHouseService.php
│   │   ├── Facade.php
│   │   ├── command/ClickHouse.php
│   │   └── config/clickhouse.php
│   │
│   ├── Webman/
│   │   ├── ClickHouseService.php
│   │   ├── Install.php
│   │   └── config/clickhouse.php
│   │
│   └── Hyperf/
│       ├── ClickHouseConnection.php
│       ├── ConfigProvider.php
│       ├── Pool/ClickHousePool.php
│       ├── Pool/PoolFactory.php
│       ├── Command/ClickHouseCommand.php
│       └── config/clickhouse.php
│
└── tests/
    ├── Client/
    ├── Query/
    ├── Schema/
    └── ORM/
```

## 核心组件设计

### 1. 客户端层 (Client)

#### ClientInterface
```php
interface ClientInterface
{
    public function query(string $sql, array $bindings = []): Result;
    public function insert(string $table, array $data): int;
    public function select(string $sql, array $bindings = []): array;
    public function ping(): bool;
}
```

#### HttpClient
- 通过 ClickHouse HTTP 接口（默认端口 8123）通信
- 使用 Guzzle 作为 HTTP 客户端
- 支持压缩 (gzip) 和 Keep-Alive
- 默认驱动

#### NativeClient
- 通过 ClickHouse Native TCP 协议（默认端口 9000）通信
- 纯 PHP socket 实现二进制协议编解码
- 高性能场景首选

#### Manager
- 管理多个 ClickHouse 连接实例
- 支持运行时切换连接：`$manager->connection('logs')`
- 连接懒加载，首次使用时才建立连接

### 2. 查询构建器 (Query Builder)

```php
$result = ClickHouse::table('logs')
    ->where('date', '>=', '2024-01-01')
    ->whereIn('level', ['error', 'warn'])
    ->orderBy('timestamp', 'desc')
    ->limit(100)
    ->get();
```

支持的操作符：
- `where`, `orWhere`, `whereIn`, `whereNotIn`, `whereBetween`, `whereNull`, `whereNotNull`
- `orderBy`, `groupBy`, `having`, `limit`, `offset`
- `join`, `leftJoin`, `rightJoin`
- `select`, `selectRaw`, `from`
- 聚合：`count()`, `sum()`, `avg()`, `min()`, `max()`
- INSERT：`insert()`, `insertBatch()`

### 3. Schema Builder

```php
ClickHouse::schema()->create('logs', function (Blueprint $table) {
    $table->date('date');
    $table->dateTime('timestamp');
    $table->string('level');
    $table->string('message');
    $table->float64('duration');
    $table->engine('MergeTree')
          ->partitionBy('toYYYYMM(date)')
          ->orderBy(['date', 'timestamp', 'level']);
});
```

支持的 ClickHouse 列类型：
- `string()`, `fixedString(length)`
- `int8()`, `int16()`, `int32()`, `int64()`
- `uint8()`, `uint16()`, `uint32()`, `uint64()`
- `float32()`, `float64()`
- `decimal(precision, scale)`
- `date()`, `dateTime()`, `dateTime64(precision)`
- `uuid()`, `bool()`
- `array(type)`, `nullable(type)`, `lowCardinality(type)`

支持的引擎配置：引擎类型、PARTITION BY、ORDER BY、PRIMARY KEY、SAMPLE BY、TTL、SETTINGS

### 4. Migration（迁移系统）

```php
class CreateLogsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('logs', function (Blueprint $table) {
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

- 迁移文件名按时间戳命名
- 迁移记录存储在 ClickHouse 的 `migrations` 表中
- 支持 `up()`, `down()`, `refresh()`, `rollback()`

### 5. ORM (ActiveRecord)

```php
class Logs extends Model
{
    protected string $table = 'logs';
    protected string $connection = 'default';
}

Logs::where('level', 'error')->orderBy('timestamp', 'desc')->limit(10)->get();
$log = Logs::find($id);
Logs::insert(['date' => '2024-01-01', 'level' => 'info']);
```

- ClickHouse 是 OLAP 数据库，以批量写入为主
- ORM 层不推荐频繁单行 UPDATE/DELETE
- 支持批量 insert 优化

### 6. 连接池 (Pool)

```php
interface PoolInterface
{
    public function get(): ClientInterface;
    public function put(ClientInterface $client): void;
    public function stats(): array;
}
```

四种实现：

| 实现 | 适用框架 | 底层机制 |
|------|---------|---------|
| SwoolePool | Hyperf | Swoole\Coroutine\Channel |
| SwowPool | Hyperf (Swow) | Swow\Channel |
| WorkermanPool | Webman | Workerman\Channel |
| NoPool | Laravel, ThinkPHP | 每次新建连接 |

连接池配置：最大连接数、最小空闲连接数、连接超时、空闲超时、心跳检测间隔

### 7. 传输层 (Transport)

```php
interface TransportInterface
{
    public function send(string $sql, array $bindings = []): mixed;
    public function close(): void;
}
```

- `HttpTransport` — 封装 Guzzle 发送 HTTP 请求到 ClickHouse
- `TcpTransport` — 封装 PHP socket，实现 ClickHouse Native Protocol
- 传输层独立于客户端，可单独测试

## 框架适配详情

### Laravel

**配置：** `config/clickhouse.php`，返回 PHP 数组

**注册方式：** Composer `extra.laravel.providers` 自动发现

**使用：**
```php
use Erikwang2013\ClickHouse\Laravel\Facades\ClickHouse;
ClickHouse::table('logs')->where(...)->get();
ClickHouse::connection('native')->select('SELECT * FROM logs LIMIT 10');
```

**Artisan 命令：**
- `clickhouse:table-list` — 列出所有表
- `clickhouse:migration:install` — 创建迁移表
- `clickhouse:migration:run` — 执行迁移
- `clickhouse:migration:rollback` — 回滚迁移

### ThinkPHP

**配置：** `config/clickhouse.php`，TP 原生配置格式

**注册方式：** 在 TP 的 `app/service.php` 中注册服务

**使用：**
```php
use think\facade\ClickHouse;
ClickHouse::query('SELECT * FROM logs');
```

**TP 命令：** `php think clickhouse:table-list`

### Webman

**配置：** `config/plugin/erikwang2013/clickhouse-php/app.php`
- Webman 启动时自动加载插件配置

**使用：**
```php
use Erikwang2013\ClickHouse\Webman\ClickHouse;
ClickHouse::table('logs')->get();
```

- 常驻进程，使用 WorkermanPool 复用连接

### Hyperf

**配置：** 通过 `ConfigProvider.php` 返回配置数组

**使用（依赖注入）：**
```php
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

- 协程连接池 + 依赖注入 + ConfigProvider 自动发现

### 框架适配对比

| 特性 | Laravel | ThinkPHP | Webman | Hyperf |
|------|---------|----------|--------|--------|
| 配置格式 | config/clickhouse.php | config/clickhouse.php | config/plugin/xxx/app.php | ConfigProvider |
| 连接池 | NoPool | NoPool | WorkermanPool | SwoolePool/SwowPool |
| 注册方式 | Composer auto-discover | 手动注册服务 | 插件自动加载 | ConfigProvider |
| 调用方式 | Facade | Facade | 静态代理 | DI 注入 |
| 命令行 | Artisan | think 命令 | — | hyperf 命令 |

## 错误处理

```php
namespace Erikwang2013\ClickHouse\Exceptions;

class ClickHouseException extends \RuntimeException {}
class ConnectionException extends ClickHouseException {}       // 连接失败
class QueryException extends ClickHouseException {            // 查询错误
    private string $sql;
    private array $bindings;
    public function getSql(): string;
}
class TimeoutException extends ConnectionException {}         // 超时
class PoolException extends ClickHouseException {}            // 连接池耗尽
```

- 所有异常继承统一基类 `ClickHouseException`
- QueryException 包含原始 SQL 和绑定值用于调试

## 日志

- 使用 PSR-3 Logger Interface
- 框架适配层自动注入框架的 Logger 实例
- 可配置查询日志（记录 SQL 和执行时间）

## 测试策略

- **单元测试：** PHPUnit，核心组件独立测试（不依赖真实 ClickHouse）
- **集成测试：** 需要 Docker ClickHouse 容器，测试实际连接和查询
- **各框架测试：** 在框架的 test harness 中测试适配器
- 最低覆盖率目标：核心组件 80%

## 实施顺序

1. 基础骨架：composer.json、命名空间、目录结构
2. 核心 Client 层：ClientInterface、HttpClient、Transport
3. Manager：多连接管理器
4. Query Builder：链式查询
5. NativeClient：Native TCP 协议实现
6. Schema Builder：DDL 构建
7. Migration：迁移系统
8. ORM：ActiveRecord
9. Pool：连接池抽象及四种实现
10. Laravel 适配
11. ThinkPHP 适配
12. Webman 适配
13. Hyperf 适配
