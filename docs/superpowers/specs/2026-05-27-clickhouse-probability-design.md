# ClickHouse 接入 & 概率计算服务

**日期**: 2026-05-27  
**状态**: approved

## 1. 概述

为游戏聚合平台接入 ClickHouse OLAP 数据库，并提供联合概率与条件概率计算服务，支撑用户行为分析、推荐系统和数据看板。

## 2. ClickHouse 接入

### 2.1 选型

使用 `erikwang2013/clickhouse-php` 包，已内置 webman 支持：
- HTTP/TCP 双传输协议，默认 HTTP:8123
- Workerman 连接池
- Query Builder + 原生 SQL
- Schema Builder

### 2.2 配置

`plugin/erikwang2013/clickhouse-php/app.php`：

```php
[
    'default' => 'clickhouse',
    'connections' => [
        'clickhouse' => [
            'driver' => 'http',
            'host' => env('CLICKHOUSE_HOST', 'localhost'),
            'port' => env('CLICKHOUSE_PORT', 8123),
            'database' => env('CLICKHOUSE_DB', 'default'),
            'username' => env('CLICKHOUSE_USER', 'default'),
            'password' => env('CLICKHOUSE_PASS', ''),
            'timeout' => 30,
        ],
    ],
    'pool' => [
        'driver' => 'workerman',
        'min_connections' => 1,
        'max_connections' => 8,
    ],
]
```

### 2.3 使用入口

```php
use Erikwang2013\ClickHouse\Webman\ClickHouseService;

// 原生 SQL
ClickHouseService::query('SELECT count() FROM erik_game_play_log');

// Query Builder
ClickHouseService::table('erik_game_play_log')->where('game_id', 5)->count();
```

## 3. 概率计算服务

### 3.1 位置

`common/service/ProbabilityService.php`

### 3.2 API

```php
class ProbabilityService
{
    // 联合概率 P(A ∩ B)
    public function joint(array $eventA, array $eventB, ?string $baseTable = null): float

    // 条件概率 P(A | B) = P(A ∩ B) / P(B)
    public function conditional(array $eventA, array $eventB, ?string $baseTable = null): float
}
```

### 3.3 SQL 生成逻辑

联合概率查询模式：
```sql
SELECT countIf(A条件 AND B条件) / count(*) AS probability
FROM base_table
```

条件概率查询模式：
```sql
SELECT countIf(A条件 AND B条件) / nullIf(countIf(B条件), 0) AS probability
FROM base_table
```

### 3.4 使用示例

```php
$prob = new ProbabilityService();

// P(充值 | 玩过游戏X)
$prob->conditional(
    ['table' => 'deposit_orders', 'alias' => 'id', 'where' => ['status' => 'paid']],
    ['table' => 'game_play_logs', 'alias' => 'id', 'where' => ['game_id' => 5]],
);

// P(玩过A ∩ 玩过B)，基于全体用户
$prob->joint(
    ['table' => 'game_play_logs', 'alias' => 'id', 'where' => ['game_id' => 5]],
    ['table' => 'game_play_logs', 'alias' => 'id', 'where' => ['game_id' => 8]],
    'users',
);
```

## 4. 文件变更清单

| 文件 | 操作 |
|------|------|
| `service/composer.json` | 添加 `erikwang2013/clickhouse-php` 依赖 |
| `service/.env` | 添加 ClickHouse 环境变量 |
| `service/config/plugin/erikwang2013/clickhouse-php/app.php` | 连接配置 |
| `common/service/ProbabilityService.php` | 新建概率计算服务 |

## 5. 注意事项

- ClickHouse 不支持事务，写入是最终一致性
- 条件概率分母为 0 时返回 0
- 连接池在 webman 多进程下每个进程独立维护
