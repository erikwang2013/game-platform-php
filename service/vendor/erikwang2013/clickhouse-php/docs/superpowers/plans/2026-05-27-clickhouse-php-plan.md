# ClickHouse PHP 客户端插件 - 实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 从零构建 `erikwang2013/clickhouse-php` 全功能 ClickHouse PHP 客户端包，支持 HTTP+Native TCP 双协议，深度适配 Laravel/ThinkPHP/Webman/Hyperf 四大框架。

**Architecture:** 单体包架构，核心层（Client/Query/Schema/Migration/ORM/Pool/Transport）完全独立于框架，通过各框架适配层桥接。PHP 8.1+, PSR-4 autoloading, PHPUnit 测试。

**Tech Stack:** PHP 8.1, Guzzle 7.x, PHPUnit 10.x, PSR-3 Logger, Swoole (可选), Workerman (可选)

---

## 文件结构总览

```
clickhouse-php/
├── composer.json
├── .gitignore
├── phpunit.xml
├── README.md
├── src/
│   ├── ClickHouse.php                    # 静态入口门面
│   ├── Exceptions/
│   │   ├── ClickHouseException.php
│   │   ├── ConnectionException.php
│   │   ├── QueryException.php
│   │   ├── TimeoutException.php
│   │   └── PoolException.php
│   ├── Support/
│   │   ├── Config.php
│   │   ├── Arr.php
│   │   └── Str.php
│   ├── Transport/
│   │   ├── TransportInterface.php
│   │   ├── HttpTransport.php
│   │   └── TcpTransport.php
│   ├── Client/
│   │   ├── ClientInterface.php
│   │   ├── HttpClient.php
│   │   ├── NativeClient.php
│   │   └── Manager.php
│   ├── Query/
│   │   ├── Builder.php
│   │   ├── Grammar.php
│   │   ├── Expression.php
│   │   └── Result.php
│   ├── Schema/
│   │   ├── Builder.php
│   │   ├── Blueprint.php
│   │   ├── Column.php
│   │   └── Grammar.php
│   ├── Migration/
│   │   ├── Migration.php
│   │   ├── Migrator.php
│   │   └── Repository.php
│   ├── ORM/
│   │   ├── Model.php
│   │   └── Collection.php
│   ├── Pool/
│   │   ├── PoolInterface.php
│   │   ├── NoPool.php
│   │   ├── SwoolePool.php
│   │   ├── SwowPool.php
│   │   └── WorkermanPool.php
│   ├── Laravel/
│   │   ├── ClickHouseServiceProvider.php
│   │   ├── Facades/ClickHouse.php
│   │   ├── Console/
│   │   │   ├── TableListCommand.php
│   │   │   ├── MigrationInstallCommand.php
│   │   │   └── MigrationRunCommand.php
│   │   └── config/clickhouse.php
│   ├── ThinkPHP/
│   │   ├── ClickHouseService.php
│   │   ├── Facade.php
│   │   ├── command/ClickHouse.php
│   │   └── config/clickhouse.php
│   ├── Webman/
│   │   ├── ClickHouseService.php
│   │   ├── Install.php
│   │   └── config/clickhouse.php
│   └── Hyperf/
│       ├── ClickHouseConnection.php
│       ├── ConfigProvider.php
│       ├── Pool/ClickHousePool.php
│       ├── Pool/PoolFactory.php
│       ├── Command/ClickHouseCommand.php
│       └── config/clickhouse.php
└── tests/
    ├── Client/
    │   ├── HttpClientTest.php
    │   ├── ManagerTest.php
    │   └── NativeClientTest.php
    ├── Query/
    │   └── BuilderTest.php
    ├── Schema/
    │   └── BuilderTest.php
    ├── ORM/
    │   └── ModelTest.php
    └── Pool/
        └── PoolTest.php
```

---

### Task 1: 基础骨架

**Files:**
- Create: `composer.json`
- Create: `.gitignore`
- Create: `phpunit.xml`
- Create: `src/Exceptions/ClickHouseException.php`
- Create: `src/Exceptions/ConnectionException.php`
- Create: `src/Exceptions/QueryException.php`
- Create: `src/Exceptions/TimeoutException.php`
- Create: `src/Exceptions/PoolException.php`
- Create: `src/Support/Config.php`
- Create: `src/Support/Arr.php`
- Create: `src/Support/Str.php`
- Create: `tests/Support/ConfigTest.php`

- [ ] **Step 1: 编写 composer.json**

```json
{
    "name": "erikwang2013/clickhouse-php",
    "description": "A full-featured PHP ClickHouse client with HTTP and Native TCP support, query builder, schema builder, migration system, and ORM.",
    "type": "library",
    "license": "MIT",
    "keywords": ["clickhouse", "database", "client", "php"],
    "authors": [
        {
            "name": "erikwang2013"
        }
    ],
    "require": {
        "php": ">=8.1",
        "guzzlehttp/guzzle": "^7.0",
        "psr/log": "^3.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "mockery/mockery": "^1.5"
    },
    "suggest": {
        "laravel/framework": "For Laravel integration (^10.0|^11.0)",
        "topthink/framework": "For ThinkPHP integration (^8.0)",
        "workerman/workerman": "For Webman integration (^4.0)",
        "hyperf/framework": "For Hyperf integration (^3.0)"
    },
    "autoload": {
        "psr-4": {
            "Erikwang2013\\ClickHouse\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Erikwang2013\\ClickHouse\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Erikwang2013\\ClickHouse\\Laravel\\ClickHouseServiceProvider"
            ]
        }
    },
    "scripts": {
        "test": "phpunit",
        "test-coverage": "phpunit --coverage-text"
    },
    "config": {
        "sort-packages": true
    },
    "minimum-stability": "stable"
}
```

- [ ] **Step 2: 编写 .gitignore**

```
vendor/
composer.lock
.phpunit.result.cache
.DS_Store
.idea/
.vscode/
*.swp
*.swo
```

- [ ] **Step 3: 编写 phpunit.xml**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
    <php>
        <env name="CLICKHOUSE_HOST" value="localhost"/>
        <env name="CLICKHOUSE_PORT" value="8123"/>
        <env name="CLICKHOUSE_USER" value="default"/>
        <env name="CLICKHOUSE_PASS" value=""/>
        <env name="CLICKHOUSE_DB" value="default"/>
    </php>
</phpunit>
```

- [ ] **Step 4: 创建异常类**

创建 `src/Exceptions/ClickHouseException.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Exceptions;

class ClickHouseException extends \RuntimeException
{
}
```

创建 `src/Exceptions/ConnectionException.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Exceptions;

class ConnectionException extends ClickHouseException
{
}
```

创建 `src/Exceptions/QueryException.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Exceptions;

class QueryException extends ClickHouseException
{
    public function __construct(
        string $message,
        private readonly string $sql,
        private readonly array $bindings = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }
}
```

创建 `src/Exceptions/TimeoutException.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Exceptions;

class TimeoutException extends ConnectionException
{
}
```

创建 `src/Exceptions/PoolException.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Exceptions;

class PoolException extends ClickHouseException
{
}
```

- [ ] **Step 5: 创建 Support 工具类**

创建 `src/Support/Config.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Support;

class Config
{
    public function __construct(
        private readonly array $config
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    public function all(): array
    {
        return $this->config;
    }
}
```

创建 `src/Support/Arr.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Support;

class Arr
{
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (!str_contains($key, '.')) {
            return $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    public static function only(array $array, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                $result[$key] = $array[$key];
            }
        }
        return $result;
    }
}
```

创建 `src/Support/Str.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Support;

class Str
{
    public static function snake(string $value): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($value)));
    }
}
```

- [ ] **Step 6: 编写 ConfigTest**

创建 `tests/Support/ConfigTest.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Tests\Support;

use Erikwang2013\ClickHouse\Support\Arr;
use Erikwang2013\ClickHouse\Support\Config;
use Erikwang2013\ClickHouse\Support\Str;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testGetTopLevelKey(): void
    {
        $config = new Config(['host' => 'localhost', 'port' => 8123]);
        $this->assertSame('localhost', $config->get('host'));
        $this->assertSame(8123, $config->get('port'));
    }

    public function testGetDefaultValue(): void
    {
        $config = new Config([]);
        $this->assertSame('default', $config->get('missing', 'default'));
    }

    public function testGetNestedKey(): void
    {
        $config = new Config(['connections' => ['default' => ['host' => 'localhost']]]);
        $this->assertSame('localhost', $config->get('connections.default.host'));
    }
}
```

- [ ] **Step 7: 安装依赖并运行测试**

```bash
cd /home/wwwroot/erikwang2013/clickhouse-php && composer install
```

```bash
cd /home/wwwroot/erikwang2013/clickhouse-php && vendor/bin/phpunit
```

Expected: 3 tests PASS

- [ ] **Step 8: Commit**

```bash
git add composer.json .gitignore phpunit.xml src/Exceptions/ src/Support/ tests/
git commit -m "feat: initialize project skeleton with exceptions and support classes"
```

---

### Task 2: Transport 传输层

**Files:**
- Create: `src/Transport/TransportInterface.php`
- Create: `src/Transport/HttpTransport.php`
- Create: `tests/Transport/HttpTransportTest.php`

- [ ] **Step 1: 创建 TransportInterface**

创建 `src/Transport/TransportInterface.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Transport;

interface TransportInterface
{
    public function send(string $sql, array $bindings = []): mixed;
    public function close(): void;
}
```

- [ ] **Step 2: 创建 HttpTransport**

创建 `src/Transport/HttpTransport.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Transport;

use Erikwang2013\ClickHouse\Exceptions\ConnectionException;
use Erikwang2013\ClickHouse\Exceptions\QueryException;
use Erikwang2013\ClickHouse\Support\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

class HttpTransport implements TransportInterface
{
    private Client $httpClient;

    public function __construct(
        private readonly Config $config,
    ) {
        $this->httpClient = new Client([
            'base_uri' => sprintf(
                'http://%s:%d/',
                $this->config->get('host', 'localhost'),
                $this->config->get('port', 8123),
            ),
            'headers' => [
                'X-ClickHouse-User' => $this->config->get('username', 'default'),
                'X-ClickHouse-Key' => $this->config->get('password', ''),
                'X-ClickHouse-Database' => $this->config->get('database', 'default'),
                'Content-Type' => 'text/plain',
            ],
            'timeout' => $this->config->get('timeout', 30),
            'http_errors' => false,
        ]);
    }

    public function send(string $sql, array $bindings = []): mixed
    {
        $sql = $this->bindParams($sql, $bindings);

        try {
            $response = $this->httpClient->post('', ['body' => $sql . ' FORMAT JSON']);
        } catch (ConnectException $e) {
            throw new ConnectionException(
                sprintf('ClickHouse connection failed: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode !== 200) {
            throw new QueryException(
                sprintf('ClickHouse query error [%d]: %s', $statusCode, $body),
                $sql,
                $bindings,
                $statusCode,
            );
        }

        $decoded = json_decode($body, true);
        return $decoded['data'] ?? $decoded;
    }

    public function close(): void
    {
    }

    private function bindParams(string $sql, array $bindings): string
    {
        if (empty($bindings)) {
            return $sql;
        }

        $index = 0;
        return preg_replace_callback('/\?/', function () use (&$index, $bindings) {
            $value = $bindings[$index++] ?? '';
            return $this->quoteValue($value);
        }, $sql);
    }

    private function quoteValue(mixed $value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return "'" . addcslashes((string) $value, "\\'") . "'";
    }
}
```

- [ ] **Step 3: 编写 HttpTransportTest**

创建 `tests/Transport/HttpTransportTest.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Tests\Transport;

use Erikwang2013\ClickHouse\Support\Config;
use Erikwang2013\ClickHouse\Transport\HttpTransport;
use PHPUnit\Framework\TestCase;

class HttpTransportTest extends TestCase
{
    public function testBindParamsReplacesPlaceholders(): void
    {
        $config = new Config([
            'host' => 'localhost',
            'port' => 8123,
            'username' => 'default',
            'password' => '',
            'database' => 'default',
        ]);

        $transport = new HttpTransport($config);

        $ref = new \ReflectionMethod($transport, 'bindParams');
        $result = $ref->invoke($transport, 'SELECT * FROM t WHERE id = ? AND name = ?', [1, 'test']);

        $this->assertSame("SELECT * FROM t WHERE id = 1 AND name = 'test'", $result);
    }

    public function testBindParamsQuotesNull(): void
    {
        $config = new Config([
            'host' => 'localhost', 'port' => 8123,
            'username' => 'default', 'password' => '', 'database' => 'default',
        ]);
        $transport = new HttpTransport($config);

        $ref = new \ReflectionMethod($transport, 'bindParams');
        $result = $ref->invoke($transport, 'SELECT * FROM t WHERE col = ?', [null]);

        $this->assertSame('SELECT * FROM t WHERE col = NULL', $result);
    }
}
```

- [ ] **Step 4: 运行测试**

```bash
vendor/bin/phpunit tests/Transport/
```

Expected: 2 tests PASS

- [ ] **Step 5: Commit**

```bash
git add src/Transport/ tests/Transport/
git commit -m "feat: add transport layer with HTTP transport implementation"
```

---

### Task 3: ClientInterface + HttpClient

**Files:**
- Create: `src/Client/ClientInterface.php`
- Create: `src/Client/HttpClient.php`
- Create: `src/Query/Result.php`
- Create: `tests/Client/HttpClientTest.php`

- [ ] **Step 1: 创建 ClientInterface**

创建 `src/Client/ClientInterface.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Client;

use Erikwang2013\ClickHouse\Query\Result;

interface ClientInterface
{
    public function query(string $sql, array $bindings = []): Result;
    public function select(string $sql, array $bindings = []): array;
    public function insert(string $table, array $data): int;
    public function ping(): bool;
}
```

- [ ] **Step 2: 创建 Result 类**

创建 `src/Query/Result.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Query;

class Result implements \IteratorAggregate, \Countable, \ArrayAccess
{
    public function __construct(
        private readonly array $data,
        private readonly int $rowCount = 0,
        private readonly ?array $meta = null,
    ) {
        $this->rowCount = $rowCount ?: count($data);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->data);
    }

    public function count(): int
    {
        return $this->rowCount;
    }

    public function first(): mixed
    {
        return $this->data[0] ?? null;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function column(string $name): array
    {
        return array_column($this->data, $name);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Result is read-only');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Result is read-only');
    }
}
```

- [ ] **Step 3: 创建 HttpClient**

创建 `src/Client/HttpClient.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Client;

use Erikwang2013\ClickHouse\Query\Result;
use Erikwang2013\ClickHouse\Support\Config;
use Erikwang2013\ClickHouse\Transport\TransportInterface;

class HttpClient implements ClientInterface
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly Config $config,
    ) {
    }

    public function query(string $sql, array $bindings = []): Result
    {
        $result = $this->transport->send($sql, $bindings);

        if (is_array($result)) {
            if (isset($result['rows'])) {
                return new Result($result['rows'], $result['rows_before_limit_at_least'] ?? 0, $result['meta'] ?? null);
            }
            return new Result($result);
        }

        return new Result([]);
    }

    public function select(string $sql, array $bindings = []): array
    {
        return $this->query($sql, $bindings)->toArray();
    }

    public function insert(string $table, array $data): int
    {
        if (empty($data)) {
            return 0;
        }

        $columns = array_keys($data[0] ?? $data);
        $values = [];

        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $row) {
                $escaped = array_map(fn($v) => $this->escape($v), array_values($row));
                $values[] = '(' . implode(', ', $escaped) . ')';
            }
        } else {
            $escaped = array_map(fn($v) => $this->escape($v), array_values($data));
            $values[] = '(' . implode(', ', $escaped) . ')';
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $table,
            implode(', ', array_map(fn($c) => "`$c`", $columns)),
            implode(', ', $values),
        );

        $this->query($sql);
        return count($values);
    }

    public function ping(): bool
    {
        try {
            $this->transport->send('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function escape(mixed $value): string
    {
        if (is_null($value)) return 'NULL';
        if (is_int($value) || is_float($value)) return (string) $value;
        if (is_bool($value)) return $value ? '1' : '0';
        return "'" . addcslashes((string) $value, "\\'") . "'";
    }
}
```

- [ ] **Step 4: 编写 HttpClientTest**

创建 `tests/Client/HttpClientTest.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Tests\Client;

use Erikwang2013\ClickHouse\Client\HttpClient;
use Erikwang2013\ClickHouse\Support\Config;
use Erikwang2013\ClickHouse\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;
use Mockery;

class HttpClientTest extends TestCase
{
    public function testSelectReturnsArray(): void
    {
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')
            ->with('SELECT * FROM test', [])
            ->andReturn(['rows' => [['id' => 1]]]);

        $config = new Config(['database' => 'default']);
        $client = new HttpClient($transport, $config);

        $result = $client->select('SELECT * FROM test');
        $this->assertSame([['id' => 1]], $result);
    }

    public function testInsertGeneratesSql(): void
    {
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->once()->andReturn(['rows' => []]);

        $config = new Config(['database' => 'default']);
        $client = new HttpClient($transport, $config);

        $count = $client->insert('test', ['name' => 'foo', 'value' => 42]);
        $this->assertSame(1, $count);
    }

    public function testInsertBatchReturnsCount(): void
    {
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->once()->andReturn(['rows' => []]);

        $config = new Config(['database' => 'default']);
        $client = new HttpClient($transport, $config);

        $rows = [
            ['name' => 'a', 'value' => 1],
            ['name' => 'b', 'value' => 2],
        ];
        $count = $client->insert('test', $rows);
        $this->assertSame(2, $count);
    }

    public function testPingReturnsTrueOnSuccess(): void
    {
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('send')->with('SELECT 1', [])->andReturn(['rows' => [[1]]]);

        $config = new Config(['database' => 'default']);
        $client = new HttpClient($transport, $config);

        $this->assertTrue($client->ping());
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
```

- [ ] **Step 5: 运行测试**

```bash
vendor/bin/phpunit tests/Client/
```

Expected: 4 tests PASS

- [ ] **Step 6: Commit**

```bash
git add src/Client/ src/Query/Result.php tests/Client/
git commit -m "feat: add ClientInterface, HttpClient, and Result class"
```

---

### Task 4: Manager 多连接管理器

**Files:**
- Create: `src/Client/Manager.php`
- Create: `src/Pool/PoolInterface.php`
- Create: `src/Pool/NoPool.php`
- Create: `src/Transport/TcpTransport.php` (骨架)
- Create: `tests/Client/ManagerTest.php`

- [ ] **Step 1: 创建 PoolInterface**

创建 `src/Pool/PoolInterface.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Pool;

use Erikwang2013\ClickHouse\Client\ClientInterface;

interface PoolInterface
{
    public function get(): ClientInterface;
    public function put(ClientInterface $client): void;
    public function stats(): array;
    public function close(): void;
}
```

- [ ] **Step 2: 创建 NoPool**

创建 `src/Pool/NoPool.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Pool;

use Erikwang2013\ClickHouse\Client\ClientInterface;

class NoPool implements PoolInterface
{
    public function __construct(
        private readonly \Closure $factory,
    ) {
    }

    public function get(): ClientInterface
    {
        return ($this->factory)();
    }

    public function put(ClientInterface $client): void
    {
    }

    public function stats(): array
    {
        return ['active' => 0, 'idle' => 0, 'total' => 0];
    }

    public function close(): void
    {
    }
}
```

- [ ] **Step 3: 创建 TcpTransport 骨架**

创建 `src/Transport/TcpTransport.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Transport;

use Erikwang2013\ClickHouse\Exceptions\QueryException;
use Erikwang2013\ClickHouse\Support\Config;

class TcpTransport implements TransportInterface
{
    private mixed $socket = null;

    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function send(string $sql, array $bindings = []): mixed
    {
        throw new QueryException(
            'Native TCP transport not yet implemented. Use HTTP driver.',
            $sql,
            $bindings,
        );
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }
}
```

- [ ] **Step 4: 创建 Manager**

创建 `src/Client/Manager.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Client;

use Erikwang2013\ClickHouse\Exceptions\ConnectionException;
use Erikwang2013\ClickHouse\Pool\NoPool;
use Erikwang2013\ClickHouse\Pool\PoolInterface;
use Erikwang2013\ClickHouse\Support\Config;
use Erikwang2013\ClickHouse\Transport\HttpTransport;
use Erikwang2013\ClickHouse\Transport\TcpTransport;
use Erikwang2013\ClickHouse\Transport\TransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Manager
{
    private array $connections = [];
    private array $pools = [];
    private string $defaultConnection;

    public function __construct(
        private readonly array $config,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->defaultConnection = $config['default'] ?? 'default';
    }

    public function connection(?string $name = null): ClientInterface
    {
        $name ??= $this->defaultConnection;

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        if (isset($this->pools[$name])) {
            return $this->pools[$name]->get();
        }

        return $this->connections[$name] = $this->make($name);
    }

    public function setPool(string $name, PoolInterface $pool): void
    {
        $this->pools[$name] = $pool;
    }

    private function make(string $name): ClientInterface
    {
        $connections = $this->config['connections'] ?? [];

        if (!isset($connections[$name])) {
            throw new ConnectionException("ClickHouse connection [{$name}] not configured.");
        }

        $connConfig = new Config($connections[$name]);
        $transport = $this->createTransport($connConfig);

        return new HttpClient($transport, $connConfig);
    }

    private function createTransport(Config $config): TransportInterface
    {
        $driver = $config->get('driver', 'http');

        return match ($driver) {
            'native', 'tcp' => new TcpTransport($config),
            default => new HttpTransport($config),
        };
    }
}
```

- [ ] **Step 5: 编写 ManagerTest**

创建 `tests/Client/ManagerTest.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Tests\Client;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Client\Manager;
use Erikwang2013\ClickHouse\Exceptions\ConnectionException;
use PHPUnit\Framework\TestCase;

class ManagerTest extends TestCase
{
    private array $config = [
        'default' => 'clickhouse',
        'connections' => [
            'clickhouse' => [
                'driver' => 'http',
                'host' => 'localhost',
                'port' => 8123,
                'username' => 'default',
                'password' => '',
                'database' => 'default',
            ],
            'native' => [
                'driver' => 'tcp',
                'host' => 'localhost',
                'port' => 9000,
                'username' => 'default',
                'password' => '',
                'database' => 'default',
            ],
        ],
    ];

    public function testConnectionReturnsClient(): void
    {
        $manager = new Manager($this->config);
        $client = $manager->connection();
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testConnectionReturnsSameInstance(): void
    {
        $manager = new Manager($this->config);
        $a = $manager->connection('clickhouse');
        $b = $manager->connection('clickhouse');
        $this->assertSame($a, $b);
    }

    public function testUnknownConnectionThrows(): void
    {
        $manager = new Manager($this->config);
        $this->expectException(ConnectionException::class);
        $manager->connection('nonexistent');
    }

    public function testDefaultConnection(): void
    {
        $manager = new Manager($this->config);
        $client = $manager->connection();
        $this->assertInstanceOf(ClientInterface::class, $client);
    }
}
```

- [ ] **Step 6: 运行测试**

```bash
vendor/bin/phpunit tests/Client/ManagerTest.php
```

Expected: 4 tests PASS

- [ ] **Step 7: Commit**

```bash
git add src/Client/Manager.php src/Pool/ src/Transport/TcpTransport.php tests/Client/ManagerTest.php
git commit -m "feat: add connection manager with multi-connection and pool support"
```

---

### Task 5: Query Builder 查询构建器

**Files:**
- Create: `src/Query/Expression.php`
- Create: `src/Query/Grammar.php`
- Create: `src/Query/Builder.php`
- Create: `src/ClickHouse.php`
- Create: `tests/Query/BuilderTest.php`

- [ ] **Step 1: 创建 Expression**

创建 `src/Query/Expression.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Query;

class Expression
{
    public function __construct(
        private readonly string $value,
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 2: 创建 Grammar**

创建 `src/Query/Grammar.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Query;

class Grammar
{
    public function compileSelect(Builder $builder): string
    {
        if (empty($builder->columns)) {
            $builder->select('*');
        }

        $sql = 'SELECT ' . implode(', ', $builder->columns);
        $sql .= ' FROM ' . $builder->from;

        return $this->compileWheres($builder, $sql)
            . $this->compileGroups($builder)
            . $this->compileOrders($builder)
            . $this->compileLimit($builder);
    }

    public function compileInsert(Builder $builder, array $data): string
    {
        $columns = array_keys($data[0] ?? $data);
        $values = [];

        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $row) {
                $escaped = array_map(fn($v) => $this->quote($v), array_values($row));
                $values[] = '(' . implode(', ', $escaped) . ')';
            }
        } else {
            $escaped = array_map(fn($v) => $this->quote($v), array_values($data));
            $values[] = '(' . implode(', ', $escaped) . ')';
        }

        return sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $builder->from,
            implode(', ', array_map(fn($c) => "`$c`", $columns)),
            implode(', ', $values),
        );
    }

    public function compileDelete(Builder $builder): string
    {
        $sql = 'ALTER TABLE ' . $builder->from . ' DELETE';
        return $this->compileWheres($builder, $sql);
    }

    private function compileWheres(Builder $builder, string $sql): string
    {
        if (empty($builder->wheres)) {
            return $sql;
        }

        $clauses = [];
        foreach ($builder->wheres as $where) {
            [$type, $column, $operator, $value, $boolean] = $where;

            if ($type === 'raw') {
                $clauses[] = ($boolean === 'or' ? 'OR ' : '') . $column;
                continue;
            }

            $prefix = empty($clauses) ? '' : ($boolean === 'or' ? 'OR ' : 'AND ');

            if ($type === 'basic') {
                $clauses[] = $prefix . $column . ' ' . $operator . ' ' . $this->quote($value);
            } elseif ($type === 'in') {
                $values = implode(', ', array_map(fn($v) => $this->quote($v), (array) $value));
                $not = $operator === 'not in' ? 'NOT ' : '';
                $clauses[] = $prefix . $column . ' ' . $not . 'IN (' . $values . ')';
            } elseif ($type === 'between') {
                $clauses[] = $prefix . $column . ' BETWEEN ' . $this->quote($value[0]) . ' AND ' . $this->quote($value[1]);
            } elseif ($type === 'null') {
                $not = $operator === 'not null' ? 'NOT ' : '';
                $clauses[] = $prefix . $column . ' IS ' . $not . 'NULL';
            }
        }

        return $sql . ' WHERE ' . implode(' ', $clauses);
    }

    private function compileGroups(Builder $builder): string
    {
        if (empty($builder->groups)) {
            return '';
        }
        return ' GROUP BY ' . implode(', ', $builder->groups);
    }

    private function compileOrders(Builder $builder): string
    {
        if (empty($builder->orders)) {
            return '';
        }
        $orders = [];
        foreach ($builder->orders as [$column, $direction]) {
            $orders[] = $column . ' ' . $direction;
        }
        return ' ORDER BY ' . implode(', ', $orders);
    }

    private function compileLimit(Builder $builder): string
    {
        $sql = '';
        if ($builder->limit !== null) {
            $sql .= ' LIMIT ' . $builder->limit;
        }
        if ($builder->offset !== null) {
            $sql .= ' OFFSET ' . $builder->offset;
        }
        return $sql;
    }

    public function quote(mixed $value): string
    {
        if ($value instanceof Expression) {
            return $value->getValue();
        }
        if (is_null($value)) return 'NULL';
        if (is_int($value) || is_float($value)) return (string) $value;
        if (is_bool($value)) return $value ? '1' : '0';
        return "'" . addcslashes((string) $value, "\\'") . "'";
    }
}
```

- [ ] **Step 3: 创建 Builder**

创建 `src/Query/Builder.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Query;

use Erikwang2013\ClickHouse\Client\ClientInterface;

class Builder
{
    public array $columns = [];
    public string $from = '';
    public array $wheres = [];
    public array $orders = [];
    public array $groups = [];
    public ?int $limit = null;
    public ?int $offset = null;
    public array $bindings = [];

    public function __construct(
        private readonly ClientInterface $client,
        private readonly Grammar $grammar = new Grammar(),
    ) {
    }

    public function table(string $table): static
    {
        $this->from = $table;
        return $this;
    }

    public function from(string $table): static
    {
        return $this->table($table);
    }

    public function select(string|array $columns = ['*']): static
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    public function selectRaw(string $expression): static
    {
        $this->columns[] = $expression;
        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }
        $this->wheres[] = ['basic', $column, $operator, $value, $boolean];
        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }
        return $this->where($column, $operator, $value, 'or');
    }

    public function whereIn(string $column, array $values): static
    {
        $this->wheres[] = ['in', $column, 'in', $values, 'and'];
        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        $this->wheres[] = ['in', $column, 'not in', $values, 'and'];
        return $this;
    }

    public function whereBetween(string $column, array $values): static
    {
        $this->wheres[] = ['between', $column, 'between', $values, 'and'];
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = ['null', $column, 'null', null, 'and'];
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = ['null', $column, 'not null', null, 'and'];
        return $this;
    }

    public function whereRaw(string $sql, string $boolean = 'and'): static
    {
        $this->wheres[] = ['raw', $sql, null, null, $boolean];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orders[] = [$column, strtoupper($direction)];
        return $this;
    }

    public function groupBy(string ...$columns): static
    {
        $this->groups = array_merge($this->groups, $columns);
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    public function get(): Result
    {
        $sql = $this->grammar->compileSelect($this);
        return $this->client->query($sql, $this->bindings);
    }

    public function first(): mixed
    {
        return $this->limit(1)->get()->first();
    }

    public function count(): int
    {
        $this->columns = ['count(*) as aggregate'];
        $result = $this->get();
        return (int) ($result->first()['aggregate'] ?? 0);
    }

    public function sum(string $column): float
    {
        $this->columns = ["sum($column) as aggregate"];
        $result = $this->get();
        return (float) ($result->first()['aggregate'] ?? 0);
    }

    public function avg(string $column): float
    {
        $this->columns = ["avg($column) as aggregate"];
        $result = $this->get();
        return (float) ($result->first()['aggregate'] ?? 0);
    }

    public function min(string $column): mixed
    {
        $this->columns = ["min($column) as aggregate"];
        return $this->get()->first()['aggregate'] ?? null;
    }

    public function max(string $column): mixed
    {
        $this->columns = ["max($column) as aggregate"];
        return $this->get()->first()['aggregate'] ?? null;
    }

    public function insert(array $data): int
    {
        $sql = $this->grammar->compileInsert($this, $data);
        $this->client->query($sql);
        return isset($data[0]) && is_array($data[0]) ? count($data) : 1;
    }

    public function delete(): int
    {
        $sql = $this->grammar->compileDelete($this);
        $result = $this->client->query($sql);
        return $result->count();
    }

    public function toSql(): string
    {
        return $this->grammar->compileSelect($this);
    }
}
```

- [ ] **Step 4: 创建入口类 ClickHouse**

创建 `src/ClickHouse.php`:
```php
<?php

namespace Erikwang2013\ClickHouse;

use Erikwang2013\ClickHouse\Client\Manager;
use Erikwang2013\ClickHouse\Query\Builder;
use Erikwang2013\ClickHouse\Schema\Builder as SchemaBuilder;

class ClickHouse
{
    protected static ?Manager $manager = null;

    public static function setManager(Manager $manager): void
    {
        static::$manager = $manager;
    }

    public static function getManager(): Manager
    {
        return static::$manager;
    }

    public static function connection(?string $name = null): Builder
    {
        $client = static::$manager->connection($name);
        return new Builder($client);
    }

    public static function table(string $table, ?string $connection = null): Builder
    {
        return static::connection($connection)->table($table);
    }

    public static function schema(): SchemaBuilder
    {
        return new SchemaBuilder(static::$manager->connection());
    }

    public static function query(string $sql, array $bindings = []): Query\Result
    {
        return static::$manager->connection()->query($sql, $bindings);
    }
}
```

- [ ] **Step 5: 编写 BuilderTest**

创建 `tests/Query/BuilderTest.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Tests\Query;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Query\Builder;
use Erikwang2013\ClickHouse\Query\Expression;
use Erikwang2013\ClickHouse\Query\Result;
use PHPUnit\Framework\TestCase;
use Mockery;

class BuilderTest extends TestCase
{
    private function createBuilder(): Builder
    {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('query')->andReturn(new Result([]));
        return new Builder($client);
    }

    public function testBasicSelectSql(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs')->where('level', 'error')->limit(10);

        $sql = $builder->toSql();
        $this->assertStringContainsString('SELECT * FROM logs', $sql);
        $this->assertStringContainsString("WHERE level = 'error'", $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testWhereInSql(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs')->whereIn('level', ['error', 'warn']);
        $sql = $builder->toSql();
        $this->assertStringContainsString("level IN ('error', 'warn')", $sql);
    }

    public function testWhereBetweenSql(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs')->whereBetween('date', ['2024-01-01', '2024-01-31']);
        $sql = $builder->toSql();
        $this->assertStringContainsString("WHERE date BETWEEN '2024-01-01' AND '2024-01-31'", $sql);
    }

    public function testWhereNullSql(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs')->whereNull('deleted_at');
        $sql = $builder->toSql();
        $this->assertStringContainsString('WHERE deleted_at IS NULL', $sql);
    }

    public function testOrderByAndGroupBy(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs')->groupBy('level')->orderBy('count', 'DESC');
        $sql = $builder->toSql();
        $this->assertStringContainsString('GROUP BY level', $sql);
        $this->assertStringContainsString('ORDER BY count DESC', $sql);
    }

    public function testInsertSql(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs');
        $sql = (new \Erikwang2013\ClickHouse\Query\Grammar())->compileInsert($builder, [
            ['name' => 'test', 'value' => 42],
        ]);
        $this->assertStringContainsString('INSERT INTO logs', $sql);
        $this->assertStringContainsString("'test'", $sql);
        $this->assertStringContainsString('42', $sql);
    }

    public function testExpressionNotQuoted(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs')->where('date', '>=', new Expression('today()'));
        $sql = $builder->toSql();
        $this->assertStringContainsString('WHERE date >= today()', $sql);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
```

- [ ] **Step 6: 运行测试**

```bash
vendor/bin/phpunit tests/Query/
```

Expected: 7 tests PASS

- [ ] **Step 7: Commit**

```bash
git add src/Query/ src/ClickHouse.php tests/Query/
git commit -m "feat: add query builder with chainable API and SQL grammar"
```

---

### Task 6: Schema Builder

**Files:**
- Create: `src/Schema/Column.php`
- Create: `src/Schema/Blueprint.php`
- Create: `src/Schema/Grammar.php`
- Create: `src/Schema/Builder.php`
- Create: `tests/Schema/BuilderTest.php`

- [ ] **Step 1: 创建 Column**

创建 `src/Schema/Column.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Schema;

class Column
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $modifiers = [],
    ) {
    }

    public function toSql(): string
    {
        $sql = "`{$this->name}` {$this->type}";
        foreach ($this->modifiers as $modifier) {
            $sql .= ' ' . $modifier;
        }
        return $sql;
    }
}
```

- [ ] **Step 2: 创建 Blueprint**

创建 `src/Schema/Blueprint.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Schema;

class Blueprint
{
    public array $columns = [];
    private ?string $engine = null;
    private ?string $partitionBy = null;
    private array $orderBy = [];
    private ?string $primaryKey = null;
    private ?string $sampleBy = null;
    private ?string $ttl = null;
    private array $settings = [];

    public function string(string $name): Column { return $this->addColumn($name, 'String'); }
    public function fixedString(string $name, int $length): Column { return $this->addColumn($name, "FixedString($length)"); }
    public function int8(string $name): Column { return $this->addColumn($name, 'Int8'); }
    public function int16(string $name): Column { return $this->addColumn($name, 'Int16'); }
    public function int32(string $name): Column { return $this->addColumn($name, 'Int32'); }
    public function int64(string $name): Column { return $this->addColumn($name, 'Int64'); }
    public function uint8(string $name): Column { return $this->addColumn($name, 'UInt8'); }
    public function uint16(string $name): Column { return $this->addColumn($name, 'UInt16'); }
    public function uint32(string $name): Column { return $this->addColumn($name, 'UInt32'); }
    public function uint64(string $name): Column { return $this->addColumn($name, 'UInt64'); }
    public function float32(string $name): Column { return $this->addColumn($name, 'Float32'); }
    public function float64(string $name): Column { return $this->addColumn($name, 'Float64'); }
    public function decimal(string $name, int $precision, int $scale): Column { return $this->addColumn($name, "Decimal($precision, $scale)"); }
    public function date(string $name): Column { return $this->addColumn($name, 'Date'); }
    public function dateTime(string $name): Column { return $this->addColumn($name, 'DateTime'); }
    public function dateTime64(string $name, int $precision = 3): Column { return $this->addColumn($name, "DateTime64($precision)"); }
    public function uuid(string $name): Column { return $this->addColumn($name, 'UUID'); }
    public function bool(string $name): Column { return $this->addColumn($name, 'Bool'); }
    public function array(string $name, string $type): Column { return $this->addColumn($name, "Array($type)"); }
    public function nullable(string $name, string $type): Column { return $this->addColumn($name, "Nullable($type)"); }
    public function lowCardinality(string $name, string $type): Column { return $this->addColumn($name, "LowCardinality($type)"); }

    public function engine(string $engine): static { $this->engine = $engine; return $this; }
    public function partitionBy(string $expression): static { $this->partitionBy = $expression; return $this; }
    public function orderBy(array $columns): static { $this->orderBy = $columns; return $this; }
    public function primaryKey(string $expression): static { $this->primaryKey = $expression; return $this; }
    public function sampleBy(string $expression): static { $this->sampleBy = $expression; return $this; }
    public function ttl(string $expression): static { $this->ttl = $expression; return $this; }
    public function settings(array $settings): static { $this->settings = $settings; return $this; }

    public function getEngine(): ?string { return $this->engine; }
    public function getPartitionBy(): ?string { return $this->partitionBy; }
    public function getOrderBy(): array { return $this->orderBy; }
    public function getPrimaryKey(): ?string { return $this->primaryKey; }
    public function getSampleBy(): ?string { return $this->sampleBy; }
    public function getTtl(): ?string { return $this->ttl; }
    public function getSettings(): array { return $this->settings; }

    private function addColumn(string $name, string $type, array $modifiers = []): Column
    {
        $column = new Column($name, $type, $modifiers);
        $this->columns[] = $column;
        return $column;
    }
}
```

- [ ] **Step 3: 创建 Schema Grammar**

创建 `src/Schema/Grammar.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Schema;

class Grammar
{
    public function compileCreate(string $table, Blueprint $blueprint): string
    {
        $columns = array_map(fn(Column $c) => $c->toSql(), $blueprint->columns);
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $table . ' (' . implode(', ', $columns) . ')';
        $sql .= ' ENGINE = ' . $blueprint->getEngine();

        if ($partitionBy = $blueprint->getPartitionBy()) {
            $sql .= ' PARTITION BY ' . $partitionBy;
        }
        if ($orderBy = $blueprint->getOrderBy()) {
            $sql .= ' ORDER BY (' . implode(', ', $orderBy) . ')';
        }
        if ($primaryKey = $blueprint->getPrimaryKey()) {
            $sql .= ' PRIMARY KEY ' . $primaryKey;
        }
        if ($sampleBy = $blueprint->getSampleBy()) {
            $sql .= ' SAMPLE BY ' . $sampleBy;
        }
        if ($ttl = $blueprint->getTtl()) {
            $sql .= ' TTL ' . $ttl;
        }
        if ($settings = $blueprint->getSettings()) {
            $pairs = [];
            foreach ($settings as $k => $v) {
                $pairs[] = "$k = $v";
            }
            $sql .= ' SETTINGS ' . implode(', ', $pairs);
        }
        return $sql;
    }

    public function compileDrop(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $table;
    }

    public function compileAlter(string $table, Blueprint $blueprint): string
    {
        $columns = array_map(fn(Column $c) => 'ADD COLUMN ' . $c->toSql(), $blueprint->columns);
        return 'ALTER TABLE ' . $table . ' ' . implode(', ', $columns);
    }

    public function compileTableExists(string $table): string
    {
        return "EXISTS TABLE $table";
    }

    public function compileTableList(string $database = 'default'): string
    {
        return "SHOW TABLES FROM $database";
    }

    public function compileTableInfo(string $table): string
    {
        return "DESCRIBE TABLE $table";
    }
}
```

- [ ] **Step 4: 创建 Schema Builder**

创建 `src/Schema/Builder.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Schema;

use Erikwang2013\ClickHouse\Client\ClientInterface;

class Builder
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly Grammar $grammar = new Grammar(),
    ) {
    }

    public function create(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint();
        $callback($blueprint);

        if (empty($blueprint->columns)) {
            return;
        }

        $sql = $this->grammar->compileCreate($table, $blueprint);
        $this->client->query($sql);
    }

    public function drop(string $table): void
    {
        $this->client->query($this->grammar->compileDrop($table));
    }

    public function alter(string $table, \Closure $callback): void
    {
        $blueprint = new Blueprint();
        $callback($blueprint);
        $this->client->query($this->grammar->compileAlter($table, $blueprint));
    }

    public function hasTable(string $table): bool
    {
        try {
            $this->client->query($this->grammar->compileTableExists($table));
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getTables(string $database = 'default'): array
    {
        return $this->client->select($this->grammar->compileTableList($database));
    }

    public function getTableInfo(string $table): array
    {
        return $this->client->select($this->grammar->compileTableInfo($table));
    }
}
```

- [ ] **Step 5: 编写测试**

创建 `tests/Schema/BuilderTest.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Tests\Schema;

use Erikwang2013\ClickHouse\Schema\Blueprint;
use Erikwang2013\ClickHouse\Schema\Grammar;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    public function testCreateTableSql(): void
    {
        $grammar = new Grammar();
        $blueprint = new Blueprint();
        $blueprint->date('date');
        $blueprint->string('level');
        $blueprint->engine('MergeTree')
            ->partitionBy('toYYYYMM(date)')
            ->orderBy(['date', 'level']);

        $sql = $grammar->compileCreate('logs', $blueprint);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS logs', $sql);
        $this->assertStringContainsString('ENGINE = MergeTree', $sql);
        $this->assertStringContainsString('PARTITION BY toYYYYMM(date)', $sql);
        $this->assertStringContainsString('ORDER BY (date, level)', $sql);
    }

    public function testDropTableSql(): void
    {
        $grammar = new Grammar();
        $this->assertSame('DROP TABLE IF EXISTS logs', $grammar->compileDrop('logs'));
    }

    public function testAllColumnTypes(): void
    {
        $blueprint = new Blueprint();
        $blueprint->int32('id');
        $blueprint->string('name');
        $blueprint->float64('score');
        $blueprint->dateTime('created_at');
        $blueprint->nullable('description', 'String');
        $blueprint->bool('active');

        $this->assertCount(6, $blueprint->columns);
        $this->assertSame('Int32', $blueprint->columns[0]->type);
        $this->assertSame('Nullable(String)', $blueprint->columns[4]->type);
        $this->assertSame('Bool', $blueprint->columns[5]->type);
    }
}
```

- [ ] **Step 6: 运行测试**

```bash
vendor/bin/phpunit tests/Schema/
```

Expected: 3 tests PASS

- [ ] **Step 7: Commit**

```bash
git add src/Schema/ tests/Schema/
git commit -m "feat: add schema builder with ClickHouse engine support"
```

---

### Task 7: Migration 迁移系统

**Files:**
- Create: `src/Migration/Repository.php`
- Create: `src/Migration/Migration.php`
- Create: `src/Migration/Migrator.php`

- [ ] **Step 1: 创建 Repository**

创建 `src/Migration/Repository.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Migration;

use Erikwang2013\ClickHouse\Client\ClientInterface;

class Repository
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $table = 'migrations',
    ) {
    }

    public function createRepository(): void
    {
        $this->client->query("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                migration String,
                batch UInt32,
                executed_at DateTime DEFAULT now()
            ) ENGINE = MergeTree()
            ORDER BY migration
        ");
    }

    public function getMigrations(): array
    {
        return $this->client->select("SELECT migration FROM {$this->table} ORDER BY migration");
    }

    public function getLastBatch(): int
    {
        $result = $this->client->select("SELECT max(batch) as batch FROM {$this->table}");
        return (int) ($result[0]['batch'] ?? 0);
    }

    public function log(string $migration, int $batch): void
    {
        $this->client->insert($this->table, ['migration' => $migration, 'batch' => $batch]);
    }

    public function delete(string $migration): void
    {
        $this->client->query("ALTER TABLE {$this->table} DELETE WHERE migration = ?", [$migration]);
    }

    public function getMigrationsByBatch(int $batch): array
    {
        return $this->client->select(
            "SELECT migration FROM {$this->table} WHERE batch = ? ORDER BY migration DESC",
            [$batch],
        );
    }
}
```

- [ ] **Step 2: 创建 Migration 基类**

创建 `src/Migration/Migration.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Migration;

use Erikwang2013\ClickHouse\Schema\Builder;

abstract class Migration
{
    protected Builder $schema;

    public function setSchema(Builder $schema): void
    {
        $this->schema = $schema;
    }

    abstract public function up(): void;

    public function down(): void
    {
    }
}
```

- [ ] **Step 3: 创建 Migrator**

创建 `src/Migration/Migrator.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Migration;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Schema\Builder;

class Migrator
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly Repository $repository,
        private readonly string $path,
    ) {
    }

    public function install(): void
    {
        $this->repository->createRepository();
    }

    public function run(): array
    {
        $migrations = $this->loadMigrations();
        $ran = array_column($this->repository->getMigrations(), 'migration');
        $pending = array_diff($migrations, $ran);

        if (empty($pending)) {
            return [];
        }

        $batch = $this->repository->getLastBatch() + 1;
        $run = [];

        foreach ($pending as $file) {
            $migration = $this->resolve($file);
            $migration->up();
            $this->repository->log($file, $batch);
            $run[] = $file;
        }

        return $run;
    }

    public function rollback(?int $steps = null): array
    {
        $batch = $this->repository->getLastBatch();
        $migrations = $this->repository->getMigrationsByBatch($batch);

        if ($steps !== null) {
            $migrations = array_slice($migrations, 0, $steps);
        }

        $rolledBack = [];
        foreach ($migrations as $row) {
            $file = $row['migration'];
            $migration = $this->resolve($file);
            $migration->down();
            $this->repository->delete($file);
            $rolledBack[] = $file;
        }

        return $rolledBack;
    }

    public function refresh(): void
    {
        $this->rollback();
        $this->run();
    }

    private function loadMigrations(): array
    {
        $files = glob($this->path . '/*.php');
        return array_map(fn($f) => basename($f, '.php'), $files);
    }

    private function resolve(string $file): Migration
    {
        $path = $this->path . '/' . $file . '.php';
        require_once $path;

        $class = preg_replace('/^\d+_/', '', $file);
        $class = str_replace('_', '', ucwords($class, '_'));

        $instance = new $class();
        $instance->setSchema(new Builder($this->client));

        return $instance;
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Migration/
git commit -m "feat: add migration system with run/rollback/refresh"
```

---

### Task 8: ORM (ActiveRecord Model)

**Files:**
- Create: `src/ORM/Collection.php`
- Create: `src/ORM/Model.php`
- Create: `tests/ORM/ModelTest.php`

- [ ] **Step 1: 创建 Collection**

创建 `src/ORM/Collection.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\ORM;

class Collection implements \IteratorAggregate, \Countable, \ArrayAccess
{
    public function __construct(
        private readonly array $items,
    ) {
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function last(): mixed
    {
        return $this->items[count($this->items) - 1] ?? null;
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    public function filter(callable $callback): static
    {
        return new static(array_values(array_filter($this->items, $callback)));
    }

    public function pluck(string $column): array
    {
        return array_column($this->items, $column);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Collection is read-only');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Collection is read-only');
    }
}
```

- [ ] **Step 2: 创建 Model**

创建 `src/ORM/Model.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\ORM;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Query\Builder;

abstract class Model
{
    protected string $table = '';
    protected string $connection = 'default';
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    protected function newQuery(): Builder
    {
        $manager = ClickHouse::getManager();
        $client = $manager->connection($this->connection);
        return (new Builder($client))->table($this->getTable());
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function save(): void
    {
        $this->newQuery()->insert($this->attributes);
    }

    public static function query(): Builder
    {
        return (new static())->newQuery();
    }

    public static function find(int|string $id): ?static
    {
        return static::where('id', $id)->first();
    }

    public static function all(): Collection
    {
        $rows = static::query()->get()->toArray();
        return new Collection(array_map(fn($row) => new static($row), $rows));
    }

    public static function insert(array $data): int
    {
        return static::query()->insert($data);
    }

    public static function where(string $column, mixed $operator = null, mixed $value = null): Builder
    {
        return static::query()->where($column, $operator, $value);
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        return static::query()->$method(...$args);
    }
}
```

- [ ] **Step 3: 编写 ModelTest**

创建 `tests/ORM/ModelTest.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Tests\ORM;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Client\Manager;
use Erikwang2013\ClickHouse\ORM\Collection;
use PHPUnit\Framework\TestCase;
use Mockery;

class TestModel extends \Erikwang2013\ClickHouse\ORM\Model
{
    protected string $table = 'test_table';
}

class ModelTest extends TestCase
{
    public function testModelGetTable(): void
    {
        $model = new TestModel();
        $this->assertSame('test_table', $model->getTable());
    }

    public function testModelAttributes(): void
    {
        $model = new TestModel(['name' => 'foo', 'value' => 42]);
        $this->assertSame('foo', $model->name);
        $this->assertSame(42, $model->value);
    }

    public function testCollectionFirst(): void
    {
        $collection = new Collection([['a' => 1], ['a' => 2]]);
        $this->assertSame(['a' => 1], $collection->first());
        $this->assertSame(['a' => 2], $collection->last());
        $this->assertCount(2, $collection);
    }

    public function testCollectionPluck(): void
    {
        $collection = new Collection([['name' => 'a'], ['name' => 'b']]);
        $this->assertSame(['a', 'b'], $collection->pluck('name'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
```

- [ ] **Step 4: 运行测试**

```bash
vendor/bin/phpunit tests/ORM/
```

Expected: 4 tests PASS

- [ ] **Step 5: Commit**

```bash
git add src/ORM/ tests/ORM/
git commit -m "feat: add ORM with ActiveRecord model and collection"
```

---

### Task 9: Pool 连接池完整实现

**Files:**
- Modify: `src/Pool/NoPool.php` (完善)
- Create: `src/Pool/SwoolePool.php`
- Create: `src/Pool/SwowPool.php`
- Create: `src/Pool/WorkermanPool.php`
- Create: `tests/Pool/PoolTest.php`

- [ ] **Step 1: 完善 NoPool**

重写 `src/Pool/NoPool.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Pool;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Exceptions\PoolException;

class NoPool implements PoolInterface
{
    private int $activeCount = 0;

    public function __construct(
        private readonly \Closure $factory,
        private readonly array $config = [],
    ) {
    }

    public function get(): ClientInterface
    {
        $maxActive = $this->config['max_connections'] ?? PHP_INT_MAX;

        if ($this->activeCount >= $maxActive) {
            throw new PoolException('NoPool: maximum connections exceeded');
        }

        $this->activeCount++;
        return ($this->factory)();
    }

    public function put(ClientInterface $client): void
    {
        $this->activeCount = max(0, $this->activeCount - 1);
    }

    public function stats(): array
    {
        return [
            'active' => $this->activeCount,
            'idle' => 0,
            'total' => $this->activeCount,
        ];
    }

    public function close(): void
    {
        $this->activeCount = 0;
    }
}
```

- [ ] **Step 2: 创建 SwoolePool**

创建 `src/Pool/SwoolePool.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Pool;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Exceptions\PoolException;
use Swoole\Coroutine\Channel;

class SwoolePool implements PoolInterface
{
    private Channel $channel;
    private int $activeCount = 0;
    private int $minConnections;
    private int $maxConnections;
    private float $connectionTimeout;

    public function __construct(
        private readonly \Closure $factory,
        private readonly array $config = [],
    ) {
        $this->minConnections = $config['min_connections'] ?? 2;
        $this->maxConnections = $config['max_connections'] ?? 16;
        $this->connectionTimeout = $config['connection_timeout'] ?? 5.0;

        $this->channel = new Channel($this->maxConnections);

        for ($i = 0; $i < $this->minConnections; $i++) {
            $this->channel->push(($this->factory)(), $this->connectionTimeout);
            $this->activeCount++;
        }
    }

    public function get(): ClientInterface
    {
        $client = $this->channel->pop($this->connectionTimeout);

        if ($client === false) {
            if ($this->activeCount < $this->maxConnections) {
                $client = ($this->factory)();
                $this->activeCount++;
            } else {
                throw new PoolException('SwoolePool: connection pool exhausted');
            }
        }

        return $client;
    }

    public function put(ClientInterface $client): void
    {
        $this->channel->push($client, $this->connectionTimeout);
    }

    public function stats(): array
    {
        $stats = $this->channel->stats();
        return [
            'active' => $this->activeCount,
            'idle' => $stats['queue_num'],
            'total' => $this->activeCount,
        ];
    }

    public function close(): void
    {
        $this->channel->close();
    }
}
```

- [ ] **Step 3: 创建 SwowPool**

创建 `src/Pool/SwowPool.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Pool;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Exceptions\PoolException;
use Swow\Channel;

class SwowPool implements PoolInterface
{
    private Channel $channel;
    private int $activeCount = 0;
    private int $minConnections;
    private int $maxConnections;
    private float $connectionTimeout;

    public function __construct(
        private readonly \Closure $factory,
        private readonly array $config = [],
    ) {
        $this->minConnections = $config['min_connections'] ?? 2;
        $this->maxConnections = $config['max_connections'] ?? 16;
        $this->connectionTimeout = $config['connection_timeout'] ?? 5.0;

        $this->channel = new Channel($this->maxConnections);

        for ($i = 0; $i < $this->minConnections; $i++) {
            $this->channel->push(($this->factory)(), (int) ($this->connectionTimeout * 1000));
            $this->activeCount++;
        }
    }

    public function get(): ClientInterface
    {
        $client = $this->channel->pop((int) ($this->connectionTimeout * 1000));

        if ($client === false) {
            if ($this->activeCount < $this->maxConnections) {
                $client = ($this->factory)();
                $this->activeCount++;
            } else {
                throw new PoolException('SwowPool: connection pool exhausted');
            }
        }

        return $client;
    }

    public function put(ClientInterface $client): void
    {
        $this->channel->push($client, (int) ($this->connectionTimeout * 1000));
    }

    public function stats(): array
    {
        return [
            'active' => $this->activeCount,
            'idle' => $this->channel->getLength(),
            'total' => $this->activeCount,
        ];
    }

    public function close(): void
    {
        $this->channel->close();
    }
}
```

- [ ] **Step 4: 创建 WorkermanPool**

创建 `src/Pool/WorkermanPool.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Pool;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Exceptions\PoolException;
use Workerman\Coroutine\Channel;

class WorkermanPool implements PoolInterface
{
    private Channel $channel;
    private int $activeCount = 0;
    private int $minConnections;
    private int $maxConnections;
    private float $connectionTimeout;

    public function __construct(
        private readonly \Closure $factory,
        private readonly array $config = [],
    ) {
        $this->minConnections = $config['min_connections'] ?? 1;
        $this->maxConnections = $config['max_connections'] ?? 8;
        $this->connectionTimeout = $config['connection_timeout'] ?? 5.0;

        $this->channel = new Channel($this->maxConnections);

        for ($i = 0; $i < $this->minConnections; $i++) {
            $this->channel->push(($this->factory)(), $this->connectionTimeout);
            $this->activeCount++;
        }
    }

    public function get(): ClientInterface
    {
        $client = $this->channel->pop($this->connectionTimeout);

        if ($client === false) {
            if ($this->activeCount < $this->maxConnections) {
                $client = ($this->factory)();
                $this->activeCount++;
            } else {
                throw new PoolException('WorkermanPool: connection pool exhausted');
            }
        }

        return $client;
    }

    public function put(ClientInterface $client): void
    {
        $this->channel->push($client, $this->connectionTimeout);
    }

    public function stats(): array
    {
        return [
            'active' => $this->activeCount,
            'idle' => $this->channel->isEmpty() ? 0 : $this->activeCount,
            'total' => $this->activeCount,
        ];
    }

    public function close(): void
    {
        $this->channel->close();
    }
}
```

- [ ] **Step 5: 编写 PoolTest**

创建 `tests/Pool/PoolTest.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Tests\Pool;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Pool\NoPool;
use PHPUnit\Framework\TestCase;
use Mockery;

class PoolTest extends TestCase
{
    public function testNoPoolGetsClient(): void
    {
        $client = Mockery::mock(ClientInterface::class);
        $pool = new NoPool(fn() => $client);

        $this->assertSame($client, $pool->get());
        $this->assertSame(['active' => 1, 'idle' => 0, 'total' => 1], $pool->stats());
    }

    public function testNoPoolPutDecrementsCount(): void
    {
        $client = Mockery::mock(ClientInterface::class);
        $pool = new NoPool(fn() => $client);

        $c = $pool->get();
        $this->assertSame(1, $pool->stats()['active']);
        $pool->put($c);
        $this->assertSame(0, $pool->stats()['active']);
    }

    public function testNoPoolMaxConnections(): void
    {
        $client = Mockery::mock(ClientInterface::class);
        $pool = new NoPool(fn() => $client, ['max_connections' => 2]);
        $pool->get();
        $pool->get();

        $this->expectException(\Erikwang2013\ClickHouse\Exceptions\PoolException::class);
        $pool->get();
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
```

- [ ] **Step 6: 运行测试**

```bash
vendor/bin/phpunit tests/Pool/
```

Expected: 3 tests PASS

- [ ] **Step 7: Commit**

```bash
git add src/Pool/ tests/Pool/
git commit -m "feat: add connection pool implementations (NoPool, Swoole, Swow, Workerman)"
```

---

### Task 10: Laravel 适配

**Files:**
- Create: `src/Laravel/config/clickhouse.php`
- Create: `src/Laravel/ClickHouseServiceProvider.php`
- Create: `src/Laravel/Facades/ClickHouse.php`
- Create: `src/Laravel/Console/TableListCommand.php`
- Create: `src/Laravel/Console/MigrationInstallCommand.php`
- Create: `src/Laravel/Console/MigrationRunCommand.php`

- [ ] **Step 1: 创建配置**

创建 `src/Laravel/config/clickhouse.php`:
```php
<?php

return [
    'default' => env('CLICKHOUSE_CONNECTION', 'default'),
    'connections' => [
        'default' => [
            'driver' => env('CLICKHOUSE_DRIVER', 'http'),
            'host' => env('CLICKHOUSE_HOST', 'localhost'),
            'port' => env('CLICKHOUSE_PORT', 8123),
            'database' => env('CLICKHOUSE_DB', 'default'),
            'username' => env('CLICKHOUSE_USER', 'default'),
            'password' => env('CLICKHOUSE_PASS', ''),
            'timeout' => env('CLICKHOUSE_TIMEOUT', 30),
        ],
    ],
    'migrations' => [
        'path' => database_path('clickhouse-migrations'),
        'table' => 'clickhouse_migrations',
    ],
    'pool' => [
        'min_connections' => env('CLICKHOUSE_POOL_MIN', 1),
        'max_connections' => env('CLICKHOUSE_POOL_MAX', 8),
        'connection_timeout' => env('CLICKHOUSE_POOL_TIMEOUT', 5),
    ],
    'query_log' => env('CLICKHOUSE_QUERY_LOG', false),
];
```

- [ ] **Step 2: 创建 ServiceProvider**

创建 `src/Laravel/ClickHouseServiceProvider.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Laravel;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Client\Manager;
use Illuminate\Support\ServiceProvider;

class ClickHouseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/clickhouse.php', 'clickhouse',
        );

        $this->app->singleton('clickhouse', function ($app) {
            $config = $app['config']['clickhouse'];
            $logger = $config['query_log'] ? $app['log'] : null;
            $manager = new Manager($config, $logger);
            ClickHouse::setManager($manager);
            return $manager;
        });

        $this->app->alias('clickhouse', Manager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/clickhouse.php' => config_path('clickhouse.php'),
        ], 'clickhouse-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\TableListCommand::class,
                Console\MigrationInstallCommand::class,
                Console\MigrationRunCommand::class,
            ]);
        }
    }
}
```

- [ ] **Step 3: 创建 Facade**

创建 `src/Laravel/Facades/ClickHouse.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class ClickHouse extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'clickhouse';
    }
}
```

- [ ] **Step 4: 创建 Artisan 命令**

创建 `src/Laravel/Console/TableListCommand.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Laravel\Console;

use Erikwang2013\ClickHouse\ClickHouse;
use Illuminate\Console\Command;

class TableListCommand extends Command
{
    protected $signature = 'clickhouse:table-list {database? : Database name}';
    protected $description = 'List all tables in ClickHouse';

    public function handle(): int
    {
        $database = $this->argument('database') ?? 'default';
        $tables = ClickHouse::schema()->getTables($database);
        $this->table(['Table'], array_map(fn($t) => [$t['name'] ?? $t], $tables));
        return 0;
    }
}
```

创建 `src/Laravel/Console/MigrationInstallCommand.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Laravel\Console;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Migration\Repository;
use Illuminate\Console\Command;

class MigrationInstallCommand extends Command
{
    protected $signature = 'clickhouse:migration:install';
    protected $description = 'Create the ClickHouse migration repository';

    public function handle(): int
    {
        $config = config('clickhouse.migrations');
        $repository = new Repository(
            ClickHouse::getManager()->connection(),
            $config['table'] ?? 'clickhouse_migrations',
        );
        $repository->createRepository();
        $this->info('ClickHouse migration table created successfully.');
        return 0;
    }
}
```

创建 `src/Laravel/Console/MigrationRunCommand.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Laravel\Console;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Migration\Migrator;
use Erikwang2013\ClickHouse\Migration\Repository;
use Illuminate\Console\Command;

class MigrationRunCommand extends Command
{
    protected $signature = 'clickhouse:migration:run';
    protected $description = 'Run pending ClickHouse migrations';

    public function handle(): int
    {
        $config = config('clickhouse.migrations');
        $repository = new Repository(
            ClickHouse::getManager()->connection(),
            $config['table'] ?? 'clickhouse_migrations',
        );
        $migrator = new Migrator(ClickHouse::getManager()->connection(), $repository, $config['path']);
        $run = $migrator->run();

        if (empty($run)) {
            $this->info('No pending migrations.');
        } else {
            foreach ($run as $migration) {
                $this->line("  <info>Migrated:</info> $migration");
            }
        }
        return 0;
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Laravel/
git commit -m "feat: add Laravel adapter with ServiceProvider, Facade, and Artisan commands"
```

---

### Task 11: ThinkPHP 适配

**Files:**
- Create: `src/ThinkPHP/config/clickhouse.php`
- Create: `src/ThinkPHP/ClickHouseService.php`
- Create: `src/ThinkPHP/Facade.php`
- Create: `src/ThinkPHP/command/ClickHouse.php`

- [ ] **Step 1: 创建配置**

创建 `src/ThinkPHP/config/clickhouse.php`:
```php
<?php

return [
    'default' => 'clickhouse',
    'connections' => [
        'clickhouse' => [
            'driver' => 'http',
            'host' => 'localhost',
            'port' => 8123,
            'database' => 'default',
            'username' => 'default',
            'password' => '',
            'timeout' => 30,
        ],
    ],
    'migrations' => [
        'path' => '',
        'table' => 'clickhouse_migrations',
    ],
    'pool' => [
        'min_connections' => 1,
        'max_connections' => 8,
        'connection_timeout' => 5,
    ],
    'query_log' => false,
];
```

- [ ] **Step 2: 创建 Service**

创建 `src/ThinkPHP/ClickHouseService.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\ThinkPHP;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Client\Manager;
use think\Service;

class ClickHouseService extends Service
{
    public function register(): void
    {
        $this->app->bind('clickhouse', function () {
            $config = $this->app->config->get('clickhouse', []);
            $manager = new Manager($config);
            ClickHouse::setManager($manager);
            return $manager;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                command\ClickHouse::class,
            ]);
        }
    }
}
```

- [ ] **Step 3: 创建 Facade**

创建 `src/ThinkPHP/Facade.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\ThinkPHP;

use think\Facade;

/**
 * @method static \Erikwang2013\ClickHouse\Query\Builder connection(?string $name = null)
 * @method static \Erikwang2013\ClickHouse\Query\Builder table(string $table, ?string $connection = null)
 * @method static \Erikwang2013\ClickHouse\Query\Result query(string $sql, array $bindings = [])
 */
class Facade extends Facade
{
    protected static function getFacadeClass(): string
    {
        return 'clickhouse';
    }
}
```

- [ ] **Step 4: 创建命令**

创建 `src/ThinkPHP/command/ClickHouse.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\ThinkPHP\command;

use Erikwang2013\ClickHouse\ClickHouse;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class ClickHouse extends Command
{
    protected function configure(): void
    {
        $this->setName('clickhouse:table-list')
            ->setDescription('List all tables in ClickHouse');
    }

    protected function execute(Input $input, Output $output): void
    {
        $tables = ClickHouse::schema()->getTables();
        $output->writeln('<info>ClickHouse Tables:</info>');
        foreach ($tables as $table) {
            $output->writeln('  ' . ($table['name'] ?? $table));
        }
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add src/ThinkPHP/
git commit -m "feat: add ThinkPHP adapter with Service, Facade, and console command"
```

---

### Task 12: Webman 适配

**Files:**
- Create: `src/Webman/config/clickhouse.php`
- Create: `src/Webman/ClickHouseService.php`
- Create: `src/Webman/Install.php`

- [ ] **Step 1: 创建配置**

创建 `src/Webman/config/clickhouse.php`:
```php
<?php

return [
    'default' => 'clickhouse',
    'connections' => [
        'clickhouse' => [
            'driver' => 'http',
            'host' => getenv('CLICKHOUSE_HOST') ?: 'localhost',
            'port' => getenv('CLICKHOUSE_PORT') ?: 8123,
            'database' => getenv('CLICKHOUSE_DB') ?: 'default',
            'username' => getenv('CLICKHOUSE_USER') ?: 'default',
            'password' => getenv('CLICKHOUSE_PASS') ?: '',
            'timeout' => 30,
        ],
    ],
    'migrations' => [
        'path' => '',
        'table' => 'clickhouse_migrations',
    ],
    'pool' => [
        'driver' => 'workerman',
        'min_connections' => 1,
        'max_connections' => 8,
        'connection_timeout' => 5,
    ],
    'query_log' => false,
];
```

- [ ] **Step 2: 创建 ClickHouseService**

创建 `src/Webman/ClickHouseService.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Webman;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Client\Manager;

class ClickHouseService
{
    private static ?Manager $manager = null;

    public static function instance(): Manager
    {
        if (self::$manager === null) {
            $config = config('plugin.erikwang2013.clickhouse-php.app', []);
            $manager = new Manager($config);
            ClickHouse::setManager($manager);
            self::$manager = $manager;
        }
        return self::$manager;
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return ClickHouse::$method(...$arguments);
    }
}
```

- [ ] **Step 3: 创建 Install 类**

创建 `src/Webman/Install.php`:
```php
<?php

namespace Erikwang2013\ClickHouse\Webman;

class Install
{
    public static function install(): void
    {
    }

    public static function configPath(): string
    {
        return __DIR__ . '/config/clickhouse.php';
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Webman/
git commit -m "feat: add Webman adapter with plugin auto-loading"
```

---

### Task 13: Hyperf 适配

**Files:**
- Create: `src/Hyperf/config/clickhouse.php`
- Create: `src/Hyperf/ClickHouseConnection.php`
- Create: `src/Hyperf/ConfigProvider.php`
- Create: `src/Hyperf/Pool/ClickHousePool.php`
- Create: `src/Hyperf/Pool/PoolFactory.php`
- Create: `src/Hyperf/Command/ClickHouseCommand.php`

- [ ] **Step 1: 创建配置**

创建 `src/Hyperf/config/clickhouse.php`:
```php
<?php

declare(strict_types=1);

return [
    'default' => 'clickhouse',
    'connections' => [
        'clickhouse' => [
            'driver' => 'http',
            'host' => env('CLICKHOUSE_HOST', 'localhost'),
            'port' => (int) env('CLICKHOUSE_PORT', 8123),
            'database' => env('CLICKHOUSE_DB', 'default'),
            'username' => env('CLICKHOUSE_USER', 'default'),
            'password' => env('CLICKHOUSE_PASS', ''),
            'timeout' => (int) env('CLICKHOUSE_TIMEOUT', 30),
        ],
    ],
    'migrations' => [
        'path' => BASE_PATH . '/database/clickhouse-migrations',
        'table' => 'clickhouse_migrations',
    ],
    'pool' => [
        'min_connections' => (int) env('CLICKHOUSE_POOL_MIN', 2),
        'max_connections' => (int) env('CLICKHOUSE_POOL_MAX', 16),
        'connection_timeout' => (float) env('CLICKHOUSE_POOL_TIMEOUT', 5.0),
    ],
    'query_log' => (bool) env('CLICKHOUSE_QUERY_LOG', false),
];
```

- [ ] **Step 2: 创建 ClickHouseConnection**

创建 `src/Hyperf/ClickHouseConnection.php`:
```php
<?php

declare(strict_types=1);

namespace Erikwang2013\ClickHouse\Hyperf;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Query\Builder;
use Erikwang2013\ClickHouse\Query\Result;
use Erikwang2013\ClickHouse\Hyperf\Pool\PoolFactory;

class ClickHouseConnection
{
    private ?ClientInterface $client = null;

    public function __construct(
        private readonly PoolFactory $poolFactory,
    ) {
    }

    public function connection(string $name = 'default'): ClientInterface
    {
        if ($this->client === null) {
            $pool = $this->poolFactory->getPool($name);
            $this->client = $pool->get();
        }
        return $this->client;
    }

    public function table(string $table): Builder
    {
        return (new Builder($this->connection()))->table($table);
    }

    public function query(string $sql, array $bindings = []): Result
    {
        return $this->connection()->query($sql, $bindings);
    }

    public function release(): void
    {
        if ($this->client !== null) {
            $pool = $this->poolFactory->getPool('default');
            $pool->put($this->client);
            $this->client = null;
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
```

- [ ] **Step 3: 创建 PoolFactory 和 ClickHousePool**

创建 `src/Hyperf/Pool/PoolFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Erikwang2013\ClickHouse\Hyperf\Pool;

use Erikwang2013\ClickHouse\Pool\PoolInterface;
use Erikwang2013\ClickHouse\Pool\SwoolePool;
use Erikwang2013\ClickHouse\Support\Config as ClickHouseConfig;
use Erikwang2013\ClickHouse\Transport\HttpTransport;

class PoolFactory
{
    private array $pools = [];

    public function __construct(
        private readonly array $config,
    ) {
    }

    public function getPool(string $name = 'default'): PoolInterface
    {
        if (!isset($this->pools[$name])) {
            $connectionConfig = $this->config['connections'][$name]
                ?? throw new \RuntimeException("ClickHouse connection [{$name}] not configured.");

            $poolConfig = $this->config['pool'] ?? [];

            $this->pools[$name] = new ClickHousePool(
                function () use ($connectionConfig) {
                    $cfg = new ClickHouseConfig($connectionConfig);
                    return new \Erikwang2013\ClickHouse\Client\HttpClient(
                        new HttpTransport($cfg),
                        $cfg,
                    );
                },
                $poolConfig,
            );
        }
        return $this->pools[$name];
    }
}
```

创建 `src/Hyperf/Pool/ClickHousePool.php`:
```php
<?php

declare(strict_types=1);

namespace Erikwang2013\ClickHouse\Hyperf\Pool;

use Erikwang2013\ClickHouse\Pool\SwoolePool;

class ClickHousePool extends SwoolePool
{
}
```

- [ ] **Step 4: 创建 ConfigProvider 和 Command**

创建 `src/Hyperf/ConfigProvider.php`:
```php
<?php

declare(strict_types=1);

namespace Erikwang2013\ClickHouse\Hyperf;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                ClickHouseConnection::class => function ($container) {
                    $config = $container->get(\Hyperf\Contract\ConfigInterface::class);
                    return new ClickHouseConnection(
                        new Pool\PoolFactory($config->get('clickhouse', [])),
                    );
                },
            ],
            'commands' => [
                Command\ClickHouseCommand::class,
            ],
            'publish' => [
                [
                    'id' => 'clickhouse-config',
                    'description' => 'ClickHouse configuration',
                    'source' => __DIR__ . '/config/clickhouse.php',
                    'destination' => BASE_PATH . '/config/autoload/clickhouse.php',
                ],
            ],
        ];
    }
}
```

创建 `src/Hyperf/Command/ClickHouseCommand.php`:
```php
<?php

declare(strict_types=1);

namespace Erikwang2013\ClickHouse\Hyperf\Command;

use Erikwang2013\ClickHouse\Hyperf\ClickHouseConnection;
use Hyperf\Command\Command;

class ClickHouseCommand extends Command
{
    protected ?string $name = 'clickhouse:table-list';
    protected string $description = 'List all tables in ClickHouse';

    public function __construct(
        private readonly ClickHouseConnection $clickhouse,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $tables = $this->clickhouse->connection()->select('SHOW TABLES');
        $this->info('ClickHouse Tables:');
        foreach ($tables as $table) {
            $this->line('  ' . ($table['name'] ?? reset($table)));
        }
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Hyperf/
git commit -m "feat: add Hyperf adapter with SwoolePool, DI, and ConfigProvider"
```

---

### Task 14: 最终验证

- [ ] **Step 1: 运行全量测试**

```bash
vendor/bin/phpunit
```

Expected: ALL tests PASS

- [ ] **Step 2: 验证自动加载**

```bash
composer dump-autoload --optimize
```

- [ ] **Step 3: 最终提交**

```bash
git add -A
git commit -m "chore: final verification, all tests passing"
```

---

## 实施说明

1. 任务顺序是强依赖的，必须按 Task 1 → 14 顺序执行
2. TcpTransport 在后续版本实现，Task 4 只需骨架
3. 框架适配任务 (10-13) 可任意顺序执行，互相独立
4. Swoole/Swow/Workerman 池不需要在 CI 中运行，单元测试只测 NoPool
5. 每个 Commit 的 diff 保持在 10 个文件以内
