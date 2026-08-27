# ClickHouse 서비스 사용 가이드
<!-- lang-nav -->

Languages: [中文](CLICKHOUSE_USAGE.md) · [English](CLICKHOUSE_USAGE.en.md) · **한국어** · [Русский](CLICKHOUSE_USAGE.ru.md) · [Deutsch](CLICKHOUSE_USAGE.de.md) · [Français](CLICKHOUSE_USAGE.fr.md) · [Español](CLICKHOUSE_USAGE.es.md) · [Português](CLICKHOUSE_USAGE.pt.md) · [हिन्दी](CLICKHOUSE_USAGE.hi.md) · [العربية](CLICKHOUSE_USAGE.ar.md) · [বাংলা](CLICKHOUSE_USAGE.bn.md) · [Bahasa Indonesia](CLICKHOUSE_USAGE.id.md) · [日本語](CLICKHOUSE_USAGE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

모든 서비스는 `common/service/`에 있으며 정적 메서드로 호출합니다.

## 1. 기본 연결

```php
use Erikwang2013\ClickHouse\Webman\ClickHouseService;
$r = ClickHouseService::query('SELECT count() FROM game_game_play_log');
```

## 2. ProbabilityService

```php
use common\service\ProbabilityService;

// 조건부 확률 P(A | B)
ProbabilityService::conditional(
    ['table' => 'game_deposit_log', 'alias' => 'user_id', 'where' => ['status' => 'confirmed']],
    ['table' => 'game_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 5]],
);

// 결합 확률 P(A ∩ B)
ProbabilityService::joint(
    ['table' => 'game_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 5]],
    ['table' => 'game_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 8]],
);
```

이벤트: `['table' => '테이블명', 'alias' => '연관 필드', 'where' => [...], 'whereRaw' => '...']`

## 3. GamePlayLogService

```php
use common\service\GamePlayLogService;
GamePlayLogService::write(userId: $uid, gameId: $gid, action: 'launch', ip: $ip, ua: $ua);
```

`GameController::launch()`에 연결되어 있습니다.

## 4. DepositLogService

```php
use common\service\DepositLogService;
DepositLogService::log($orderId, $userId, '10.00', 'USD', 'pending');
DepositLogService::revenueOverview(7);       // {total, count, avg}
DepositLogService::conversionByGame(30);     // [{game_id, players, depositors, conversion}, ...]
```

`DepositController::create()`와 `PaymentController::callback()`에 연결되어 있습니다.

## 5. GameDashboardService

```php
use common\service\GameDashboardService;
GameDashboardService::overview(1);              // {plays, players, games}
GameDashboardService::gameRanking(7);           // 랭킹
GameDashboardService::dauTrend(30);             // DAU 추세
GameDashboardService::hourlyTrend(5, 7);        // 시간대
GameDashboardService::actionDistribution(5, 24); // 행동 분포
```

## 6. 백오피스 대시보드 API

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
