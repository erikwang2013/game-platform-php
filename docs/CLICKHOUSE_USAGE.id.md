# Panduan Penggunaan Layanan ClickHouse
<!-- lang-nav -->

Languages: [中文](CLICKHOUSE_USAGE.md) · [English](CLICKHOUSE_USAGE.en.md) · [한국어](CLICKHOUSE_USAGE.ko.md) · [Русский](CLICKHOUSE_USAGE.ru.md) · [Deutsch](CLICKHOUSE_USAGE.de.md) · [Français](CLICKHOUSE_USAGE.fr.md) · [Español](CLICKHOUSE_USAGE.es.md) · [Português](CLICKHOUSE_USAGE.pt.md) · [हिन्दी](CLICKHOUSE_USAGE.hi.md) · [العربية](CLICKHOUSE_USAGE.ar.md) · [বাংলা](CLICKHOUSE_USAGE.bn.md) · **Bahasa Indonesia** · [日本語](CLICKHOUSE_USAGE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Semua layanan berada di `common/service/`, dipanggil sebagai metode statis.

## 1. Koneksi Dasar

```php
use Erikwang2013\ClickHouse\Webman\ClickHouseService;
$r = ClickHouseService::query('SELECT count() FROM erik_game_play_log');
```

## 2. ProbabilityService

```php
use common\service\ProbabilityService;

// Probabilitas bersyarat P(A | B)
ProbabilityService::conditional(
    ['table' => 'erik_deposit_log', 'alias' => 'user_id', 'where' => ['status' => 'confirmed']],
    ['table' => 'erik_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 5]],
);

// Probabilitas gabungan P(A ∩ B)
ProbabilityService::joint(
    ['table' => 'erik_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 5]],
    ['table' => 'erik_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 8]],
);
```

Event: `['table' => 'nama tabel', 'alias' => 'kolom relasi', 'where' => [...], 'whereRaw' => '...']`

## 3. GamePlayLogService

```php
use common\service\GamePlayLogService;
GamePlayLogService::write(userId: $uid, gameId: $gid, action: 'launch', ip: $ip, ua: $ua);
```

Sudah terhubung ke `GameController::launch()`.

## 4. DepositLogService

```php
use common\service\DepositLogService;
DepositLogService::log($orderId, $userId, '10.00', 'USD', 'pending');
DepositLogService::revenueOverview(7);       // {total, count, avg}
DepositLogService::conversionByGame(30);     // [{game_id, players, depositors, conversion}, ...]
```

Sudah terhubung ke `DepositController::create()` dan `PaymentController::callback()`.

## 5. GameDashboardService

```php
use common\service\GameDashboardService;
GameDashboardService::overview(1);              // {plays, players, games}
GameDashboardService::gameRanking(7);           // peringkat
GameDashboardService::dauTrend(30);             // tren DAU
GameDashboardService::hourlyTrend(5, 7);        // rentang waktu
GameDashboardService::actionDistribution(5, 24); // distribusi perilaku
```

## 6. API Dasbor Backend

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
