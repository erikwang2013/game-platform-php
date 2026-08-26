# Guia de uso do serviço ClickHouse
<!-- lang-nav -->

Languages: [中文](CLICKHOUSE_USAGE.md) · [English](CLICKHOUSE_USAGE.en.md) · [한국어](CLICKHOUSE_USAGE.ko.md) · [Русский](CLICKHOUSE_USAGE.ru.md) · [Deutsch](CLICKHOUSE_USAGE.de.md) · [Français](CLICKHOUSE_USAGE.fr.md) · [Español](CLICKHOUSE_USAGE.es.md) · **Português** · [हिन्दी](CLICKHOUSE_USAGE.hi.md) · [العربية](CLICKHOUSE_USAGE.ar.md) · [বাংলা](CLICKHOUSE_USAGE.bn.md) · [Bahasa Indonesia](CLICKHOUSE_USAGE.id.md) · [日本語](CLICKHOUSE_USAGE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Todos os serviços ficam em `common/service/`, com chamada de métodos estáticos.

## 1. Conexão básica

```php
use Erikwang2013\ClickHouse\Webman\ClickHouseService;
$r = ClickHouseService::query('SELECT count() FROM erik_game_play_log');
```

## 2. ProbabilityService

```php
use common\service\ProbabilityService;

// Probabilidade condicional P(A | B)
ProbabilityService::conditional(
    ['table' => 'erik_deposit_log', 'alias' => 'user_id', 'where' => ['status' => 'confirmed']],
    ['table' => 'erik_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 5]],
);

// Probabilidade conjunta P(A ∩ B)
ProbabilityService::joint(
    ['table' => 'erik_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 5]],
    ['table' => 'erik_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 8]],
);
```

Evento: `['table' => 'nome da tabela', 'alias' => 'campo de relação', 'where' => [...], 'whereRaw' => '...']`

## 3. GamePlayLogService

```php
use common\service\GamePlayLogService;
GamePlayLogService::write(userId: $uid, gameId: $gid, action: 'launch', ip: $ip, ua: $ua);
```

Já conectado ao `GameController::launch()`.

## 4. DepositLogService

```php
use common\service\DepositLogService;
DepositLogService::log($orderId, $userId, '10.00', 'USD', 'pending');
DepositLogService::revenueOverview(7);       // {total, count, avg}
DepositLogService::conversionByGame(30);     // [{game_id, players, depositors, conversion}, ...]
```

Já conectado ao `DepositController::create()` e `PaymentController::callback()`.

## 5. GameDashboardService

```php
use common\service\GameDashboardService;
GameDashboardService::overview(1);              // {plays, players, games}
GameDashboardService::gameRanking(7);           // ranking
GameDashboardService::dauTrend(30);             // tendência de DAU
GameDashboardService::hourlyTrend(5, 7);        // faixas de horário
GameDashboardService::actionDistribution(5, 24); // distribuição de ações
```

## 6. APIs do dashboard do backend

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
