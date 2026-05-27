# ClickHouse 服务使用指南

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

所有服务位于 `common/service/`，静态方法调用，自动连接 ClickHouse。

---

## 1. 基础连接

```php
use Erikwang2013\ClickHouse\Webman\ClickHouseService;

// 原生 SQL
$r = ClickHouseService::query('SELECT count() FROM erik_game_play_log');

// Query Builder
$count = ClickHouseService::table('erik_game_play_log')->where('game_id', 5)->count();
```

---

## 2. GamePlayLogService — 行为日志双写

```php
use common\service\GamePlayLogService;

// 单条
GamePlayLogService::write(
    userId: 1001, gameId: 5, action: 'launch',
    detail: ['version' => '1.2.3'],
    ipAddress: '192.168.1.1', userAgent: 'Mozilla/5.0',
);

// 批量
GamePlayLogService::writeBatch([
    ['user_id' => 1, 'game_id' => 5, 'action' => 'spin', 'detail' => ['result' => 'win']],
    ['user_id' => 2, 'game_id' => 5, 'action' => 'bet', 'detail' => ['amount' => 100]],
]);
```

已接入：`GameController::launch()` 写入 `action=launch`。  
扩展：在游戏回调中写入 `spin`/`bet`/`win`/`exit` 等动作。

## 3. DepositLogService — 充值交易双写

```php
use common\service\DepositLogService;

// 充值记录
DepositLogService::logDeposit(1001, $userId, '10.00', 'USD', 'pending', 'stripe');

// 交易流水
DepositLogService::logTransaction(2001, $userId, 'deposit', '10.0000', '100.5000', 'deposit_order', 1001);

// 收入概览
$r = DepositLogService::revenueOverview(7);  // {total, count, avg}

// 充值转化率（按游戏）
$c = DepositLogService::conversionByGame(30);
// [{game_id: 5, players: 200, depositors: 50, conversion: 0.25}, ...]
```

已接入：`DepositController::create()` / `PaymentController::callback()` / `ExchangeController`。

## 4. ProbabilityService — 概率计算

```php
use common\service\ProbabilityService;

// 条件概率 P(充值 | 玩过X)
$p = ProbabilityService::conditional(
    ['table' => 'erik_deposit_log', 'alias' => 'user_id', 'where' => ['status' => 'completed']],
    ['table' => 'erik_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 5]],
);

// 联合概率 P(A ∩ B)
$p = ProbabilityService::joint(
    ['table' => 'erik_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 5]],
    ['table' => 'erik_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 8]],
);
```

## 5. RecommendService — 游戏推荐

```php
use common\service\RecommendService;

RecommendService::alsoPlayed(5, 10);        // 玩过X的还玩了...
RecommendService::trending(168, 10);        // 7天热门排行
RecommendService::forUser($userId, 10);     // 个性化推荐
RecommendService::gameAffinity(5, 8);       // 游戏关联度 0.0-1.0
```

## 6. RiskClickHouseService — 增强风控

```php
use common\service\RiskClickHouseService;

RiskClickHouseService::detectHighFrequency(5, 30);    // 5min >30次操作
RiskClickHouseService::detectMultiAccount(24, 3);      // 同IP≥3账号
RiskClickHouseService::detectIpHopping(1, 3);          // 1h≥3 IP切换
RiskClickHouseService::detectAnomalousGames(24);       // 行为分布异常
RiskClickHouseService::assessUser($userId);            // 评分 0-100
```

## 7. AntiCheatService — 反作弊

```php
use common\service\AntiCheatService;

AntiCheatService::detectBotPattern(30, 20);           // 脚本检测
AntiCheatService::detect24HourActivity(24, 18);        // 24h挂机
AntiCheatService::detectDensityAnomaly(10, 100);       // 密度异常
AntiCheatService::detectAccountFarming(60, 5);         // 羊毛党
AntiCheatService::assessUser($userId);                 // 综合评分 0-100
```

## 8. RetentionService — 留存分析

```php
use common\service\RetentionService;

RetentionService::cohortRetention(30);      // D1/D7/D30 队列留存
RetentionService::retentionByGame(30);      // 按游戏对比
RetentionService::retentionByRegion(30);    // 按地域对比
RetentionService::churnRate(30, 7);         // 流失率
```

## 9. GameDashboardService — 游戏看板

```php
use common\service\GameDashboardService;

GameDashboardService::overview(1);               // 今日概览 {plays, players, games}
GameDashboardService::gameRanking(7);            // 7天排行
GameDashboardService::dauTrend(30);              // 30天DAU趋势
GameDashboardService::hourlyTrend(5, 7);         // 时段热力图
GameDashboardService::actionDistribution(5, 24); // 行为分布
```

## 10. RateLimitDashboardService — 限流看板

```php
use common\service\RateLimitDashboardService;

RateLimitDashboardService::topIps(24);               // IP排行
RateLimitDashboardService::requestTrend(7);           // 请求趋势
RateLimitDashboardService::actionBreakdown(24);       // 操作分布
RateLimitDashboardService::suspiciousIps(24, 100);    // 可疑IP
```

## 11. SmartCouponService — 智能优惠券

```php
use common\service\SmartCouponService;

SmartCouponService::userActivityProfile($userId);      // 活跃度画像
SmartCouponService::detectChurnRisk(7);                 // 流失风险用户
SmartCouponService::retentionRecommendations(7);        // 挽留建议+金额+优先级
SmartCouponService::gameEngagement();                    // 游戏参与度
```

## 12. UserProfileService — 用户画像

```php
use common\service\UserProfileService;

$p = UserProfileService::getProfile($userId);
// tags: ['weekly_active', 'multi_game', 'regular', 'stable_ip', 'evening_player']
// metrics: {total_actions, active_days, games_played, ip_count, peak_hour}
// preferences: [{game_id, plays, pct}, ...]

UserProfileService::getMetrics($userId);
UserProfileService::getPreferences($userId, 5);
UserProfileService::batchMetrics([1001, 1002, 1003]);
```

## 13. AbTestService — A/B 实验

```php
use common\service\AbTestService;

// 分桶
$v = AbTestService::assign('new_ui', $userId, ['control' => 50, 'treatment' => 50]);
// 'control' or 'treatment'

AbTestService::report(7);        // 按游戏指标对比
AbTestService::comparePeriods(   // 时期对比
    '2026-05-01', '2026-05-07',
    '2026-05-08', '2026-05-14',
);
```

## 14. WebSocket 实时推送

```js
const ws = new WebSocket('ws://localhost:8789');
ws.onmessage = (e) => {
    const m = JSON.parse(e.data);
    // m.type: 'leaderboard' | 'risk_alert' | 'game_events' | 'overview'
};
ws.send(JSON.stringify({action: 'leaderboard'}));
```

| 推送 | 间隔 | 内容 |
|------|------|------|
| leaderboard | 30s | 1h活跃排行 |
| risk_alert | 30s | 高频/多账号/IP跳变 |
| game_events | 5s | 1min事件流 |
| overview | 30s | DAU/活跃游戏/操作量 |

## 15. 后台看板 API

所有端点位于 `admin/app/admin/controller/AnalyticsController.php`：

```
GET /admin/analytics/overview
GET /admin/analytics/game-ranking     ?days=7
GET /admin/analytics/dau-trend        ?days=30
GET /admin/analytics/hourly-trend     ?game_id=5
GET /admin/analytics/retention        ?days=30
GET /admin/analytics/trending         ?hours=168
GET /admin/analytics/top-ips          ?hours=24
GET /admin/analytics/suspicious-ips   ?hours=24&threshold=100
GET /admin/analytics/risk-alerts
GET /admin/analytics/anti-cheat
GET /admin/analytics/user-profile     ?user_id=1001
GET /admin/analytics/churn-risk       ?days=7
```
