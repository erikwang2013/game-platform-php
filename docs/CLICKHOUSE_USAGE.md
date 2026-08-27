# ClickHouse 服务使用指南
<!-- lang-nav -->

Languages: **中文** · [English](CLICKHOUSE_USAGE.en.md) · [한국어](CLICKHOUSE_USAGE.ko.md) · [Русский](CLICKHOUSE_USAGE.ru.md) · [Deutsch](CLICKHOUSE_USAGE.de.md) · [Français](CLICKHOUSE_USAGE.fr.md) · [Español](CLICKHOUSE_USAGE.es.md) · [Português](CLICKHOUSE_USAGE.pt.md) · [हिन्दी](CLICKHOUSE_USAGE.hi.md) · [العربية](CLICKHOUSE_USAGE.ar.md) · [বাংলা](CLICKHOUSE_USAGE.bn.md) · [Bahasa Indonesia](CLICKHOUSE_USAGE.id.md) · [日本語](CLICKHOUSE_USAGE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

所有服务位于 `common/service/`，静态方法调用。

## 1. 基础连接

```php
use Erikwang2013\ClickHouse\Webman\ClickHouseService;
$r = ClickHouseService::query('SELECT count() FROM game_game_play_log');
```

## 2. ProbabilityService

```php
use common\service\ProbabilityService;

// 条件概率 P(A | B)
ProbabilityService::conditional(
    ['table' => 'game_deposit_log', 'alias' => 'user_id', 'where' => ['status' => 'confirmed']],
    ['table' => 'game_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 5]],
);

// 联合概率 P(A ∩ B)
ProbabilityService::joint(
    ['table' => 'game_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 5]],
    ['table' => 'game_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 8]],
);
```

事件: `['table' => '表名', 'alias' => '关联字段', 'where' => [...], 'whereRaw' => '...']`

## 3. GamePlayLogService

```php
use common\service\GamePlayLogService;
GamePlayLogService::write(userId: $uid, gameId: $gid, action: 'launch', ip: $ip, ua: $ua);
```

已接入 `GameController::launch()`。

## 4. DepositLogService

```php
use common\service\DepositLogService;
DepositLogService::log($orderId, $userId, '10.00', 'USD', 'pending');
DepositLogService::revenueOverview(7);       // {total, count, avg}
DepositLogService::conversionByGame(30);     // [{game_id, players, depositors, conversion}, ...]
```

已接入 `DepositController::create()` 和 `PaymentController::callback()`。

## 5. GameDashboardService

```php
use common\service\GameDashboardService;
GameDashboardService::overview(1);              // {plays, players, games}
GameDashboardService::gameRanking(7);           // 排行
GameDashboardService::dauTrend(30);             // DAU 趋势
GameDashboardService::hourlyTrend(5, 7);        // 时段
GameDashboardService::actionDistribution(5, 24); // 行为分布
```

## 6. 后台看板 API

```
GET /admin/analytics/overview
GET /admin/analytics/game-ranking        ?days=7
GET /admin/analytics/dau-trend           ?days=30
GET /admin/analytics/hourly-trend        ?game_id=<hashid>
GET /admin/analytics/action-distribution  ?game_id=<hashid>&hours=24
GET /admin/analytics/revenue             ?days=7
GET /admin/analytics/conversion          ?days=30
GET /admin/analytics/probability         ?game_a=<hashid>&game_b=<hashid>
```
