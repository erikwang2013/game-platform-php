# H1 抽取共享层方案 — platform-common 作为唯一事实源

- 日期：2026-08-31
- 范围：`packages/platform-common` + `admin/` + `service/`
- 状态：方案设计，未开始实施

---

## 1. 目标与范围

### 目标

消除 admin（端口 8787）与 service（端口 8788）两个 webman v2 应用之间的代码漂移，让 `packages/platform-common`（composer 名 `erik/platform-common`，PSR-4 `common\` → `src/`）成为跨端共享逻辑的**唯一事实源**。

判定"完成"的行为标准：

- 同一份业务逻辑在两个应用中**只存在一份源码**；
- 任一端修改共享逻辑，另一端无需二次改动即生效；
- 不再出现"admin 端改了、service 端没跟上"的静默分叉。

### 范围内

1. 跨端重复的 Service 类；
2. 跨端重复的 Eloquent Model 类；
3. admin 侧被共享 Service 反向引用的 common 辅助类；
4. 调用方引用的同步更新。

### 范围外（明确不做）

- service 独占、admin 无对应方的 Service（`AchievementService`、`PushService`、`RiskService`、`VerificationService`）—— 无重复即无漂移，留在 service；
- admin 独占的后台业务 Service（用户/角色/权限/报表等）—— 单端存在，不迁移；
- 各应用 `support/bootstrap/*`（Eloquent Capsule 引导）—— 属框架引导，必须留在宿主应用；
- 数据库 schema 变更 —— `install/install.sql` 已单库共表，无需改动。

---

## 2. 现状分析

### 2.1 已迁移到 platform-common 的类

`packages/platform-common/src/`：

| 文件 | namespace | 说明 |
|---|---|---|
| `service/DepositLogService.php` | `common\service` | 充值流水落库 |
| `service/GameDashboardService.php` | `common\service` | 游戏运营看板聚合 |
| `service/GamePlayLogService.php` | `common\service` | 游戏行为日志写入 `game_game_play_log` |
| `service/ProbabilityService.php` | `common\service` | 概率统计 |
| `CircuitBreaker.php` | `common` | 熔断器 |
| `Retry.php` | `common` | 重试包装 |

### 2.2 共享 Service 的调用方

grep `DepositLogService|ProbabilityService|GameDashboardService|GamePlayLogService`：

- `admin/app/admin/controller/AnalyticsController.php`
- `admin/tests/PlatformCommonTest.php`
- `admin/tests/ClickHouseServiceTest.php`
- `service/app/api/v1/controller/PaymentController.php`
- `service/app/api/v1/controller/GameController.php`
- `service/app/api/v1/controller/DepositController.php`

### 2.3 已确认的漂移事实

**关键事实 A：两个应用连同一个数据库。** `admin/config/database.php` 与 `service/config/database.php` 使用同一组环境变量（`DB_HOST`/`DB_DATABASE`/`DB_PREFIX`），库名 `game-platform`、表前缀 `game_`。不存在"admin 一套库、service 一套库"。因此共享层要解决的**不是** DB 连接问题，而是 **Model 类的两份副本**问题。

**关键事实 B：已迁移的共享 Service 依赖宿主应用的 namespace。** 例如 `GamePlayLogService` 里写的是：

```php
use app\common\SnowflakeService;
use app\model\GamePlayLog;
```

`app\model\GamePlayLog` 在两个应用里各有一份，类名相同、表相同，因此恰好能跑。这是**隐式耦合**：一旦两份 Model 分叉，共享 Service 在某一端就会悄悄改变行为。这是本次抽取要消除的核心风险。

### 2.4 仍重复的 Service 清单（`admin/app/service/` × `service/app/service/`）

5 个同名单文件，逐字节比对结果：

| Service | 文件路径 | 比对结果 | 差异原因 |
|---|---|---|---|
| `VipService` | `admin/app/service/VipService.php` / `service/app/service/VipService.php` | **完全一致** | — |
| `TranslationService` | `admin/app/service/TranslationService.php` / `service/app/service/TranslationService.php` | **完全一致** | — |
| `LeaderboardService` | `admin/app/service/LeaderboardService.php` / `service/app/service/LeaderboardService.php` | **完全一致** | — |
| `NotificationService` | `admin/app/service/NotificationService.php` / `service/app/service/NotificationService.php` | **已漂移** | service 端多两行：`PushService::send(...)` 推送通知；admin 端缺 `PushService`（该类只在 `service/app/service/` 存在） |
| `PayoutService` | `admin/app/service/PayoutService.php` / `service/app/service/PayoutService.php` | **已漂移** | service 端用 `FeatureFlag::isEnabled('provider_mock')` + `\app\event\EventBus::emit('withdraw.completed', ...)`；admin 端用私有 `mockEnabled()` 读 `PlatformConfig`。**`FeatureFlag` 与 `app\event\EventBus` 均只存在于 service 端** |

结论：3 个可以零风险直接搬；2 个需要先处理 service 端独占依赖，否则搬过去 admin 端跑不起来。

### 2.5 仍重复的 Model 清单

`admin/app/model/` 与 `service/app/model/` 共 **38 个同名文件**：

```
Achievement, Announcement, CdnProvider, CountryConfig, Coupon, DepositOrder,
DeviceToken, ExchangeRecord, ExpLog, GameCategory, GameCurrency, Game,
GamePlayLog, Language, Leaderboard, Message, Notification, PaymentMethod,
PlatformConfig, Referral, RiskLog, RiskRule, Ticket, TicketReply,
TournamentEntry, Tournament, Transaction, Translation, UserAchievement,
UserCoupon, UserGameWallet, UserIdentity, UserOauth, User, UserSession,
UserVip, UserWallet, VipLevel, WithdrawLimit, WithdrawOrder
```

其中 **4 个已漂移**：`CountryConfig`、`DepositOrder`、`PaymentMethod`、`UserWallet`（逐字段比对见 §7 步骤 4）。其余 34 个完全一致。

### 2.6 共享 Service 反向依赖的 admin-only 辅助类

位于 `admin/app/common/`：`SnowflakeService`、`HashidsService`、`EncryptionService`、`CdnProbeService`。其中 `SnowflakeService` 已被共享的 `GamePlayLogService` 引用 —— 也就是说**共享包目前依赖 admin 应用目录**，方向是反的。

### 2.7 重复的测试

`admin/tests/PayoutServiceTest.php` 与 `service/tests/PayoutServiceTest.php` 同名并存；`admin/tests/LeaderboardServiceTest.php`、`NotificationServiceTest.php`、`PayoutServiceTest.php` 对应 admin 端 5 个 Service。迁移后需要合并或改指向。

---

## 3. 迁移策略

按"风险递增 + 依赖顺序"分 5 批，每批独立可回滚。**不做一次性大迁移。**

### 批次 0：解依赖（前置）

- 目标：让 `FeatureFlag` 和事件出口不再阻塞 `PayoutService` 迁移。
- **A. 迁 `FeatureFlag` 到 `common\service`**：它只读配置、无宿主依赖，最该共享。admin 端现有的私有 `mockEnabled()` 一并替换。
- **B. 事件出口统一走 `common\service\EventPublisher`**（采纳 H2 方案，替代原 `class_exists` 守卫提案）：

**接缝必须三参带 `$eventId`** —— 这是幂等的来源，对应 H2 Outbox 表的 `uk_event_id` 唯一键。砍掉 eventId，Outbox 就没有生产者喂它了。

```php
// packages/platform-common/src/service/EventPublisher.php
class EventPublisher
{
    private static $publisher = null;

    /** 共享层唯一事件出口；未注册时 no-op */
    public static function push(string $event, string $eventId, array $payload = []): void
    {
        if (self::$publisher === null) return;
        (self::$publisher)($event, $eventId, $payload);
    }

    public static function setPublisher(callable $publisher): void
    {
        self::$publisher = $publisher;
    }
}
```

**归属划分（关键）**：

| 谁 | 做什么 |
|---|---|
| **H1 批次 0** | 只加 `EventPublisher` 类本体（默认 no-op），**不含任何 `setPublisher()` 调用** |
| **H2 步骤 2** | 同时做两件事：新增 `EventBus::push(string $event, string $eventId, array $payload)`（三参、走 Outbox）+ service 端 `EventPublisher::setPublisher([EventBus::class, 'push'])` |

H2 的两件事在同一个提交里，不存在中间状态。这样 H1 批次 0 不依赖任何尚不存在的方法；H2 步骤 2 落地前后，service 侧要么 no-op（等同现状，无回退）、要么走 Outbox，没有静默降级窗口。

> **为什么注册不能放在 H1**：注册 `[EventBus::class, 'push']` 在 H2 步骤 2 之前必 fatal；若为过渡临时改注册 `emit()`，会静默落到 Redis Pub/Sub 而绕过 Outbox —— 缺陷原样保留。两种接缝都是错的：fatal 至少部署时就炸，静默降级会在生产慢慢丢钱。
>
> **实测依据**：`service/app/event/EventBus.php:23` 的 `emit(string $event, array $payload = [])` 只做 `Redis::publish(CHANNEL, $message)`，无 Outbox 写入、无 eventId —— 纯 Pub/Sub 尽力投递。这就是 H2 要替换掉的那条路径。全仓 7 处 `emit()` 调用无一例 `push()`，`push()` 目前不存在。
>
> **本节两次修正记录**：原提案 `class_exists` 守卫（被 H2 以"静默吞事件"否决）→ 我第一次修正为两参 `emit()` 接缝（被 H2 以"静默绕过 Outbox"否决，且我误判 fatal 更诚实）→ 现为三参接缝 + 注册归属 H2。前两版均已废弃。

**拒绝 `class_exists` 守卫的理由**（已采纳）：admin 端 `grep EventBus admin/app` 零命中，守卫在 admin 永远走 no-op 分支；而 admin `PayoutService::markCompleted()` 是活路径（`admin/app/service/PayoutService.php:29/79/118` 三处调用，与 service 端 `:28/78/117` 一一对应）。`class_exists` 把运行环境探测写进业务代码且静默吞掉事件 —— 这正是 `WebhookController` 允许订阅 `deposit.completed`/`risk.alert` 却全仓零生产者那个缺陷的复发形式。

**no-op 在 `withdraw.completed` 上可接受的依据**：两个下游都不要求它 —— `AchievementService` 的进度从 DB 表重算，而 admin 端 `markCompleted()` 仍写共享库的订单状态，成就进度不受影响；`WebhookController` 本就是尽力投递。admin 侧日后若真要对外通知，注册发布器即可，不用改共享代码。

**no-op 不允许静默**（H2 追加约束）：在 `service/app/process/Monitor.php` 加一条对账巡检，把"事件丢失"从看不见变成看得见：

```sql
SELECT wo.id, wo.status, wo.updated_at
FROM game_withdraw_order wo
LEFT JOIN game_event_outbox eo ON eo.event_id = CONCAT('withdraw_', wo.id, '_completed')
WHERE wo.status = 'completed' AND eo.id IS NULL;
```

无结果 = 两侧生产者都在；有结果 = 某一侧静默跳过。注意表名：Eloquent Model 里是 `withdraw_order`，`config/database.php` 有 `'prefix' => 'game_'`，原生 SQL 必须写全名 `game_withdraw_order`。放在 service 侧 Monitor（service 拥有支付与 outbox），admin 侧 Monitor 不重复跑。

- 原选项 B（service 向 admin 对齐、删掉 `FeatureFlag` 与事件发送）**不推荐**：丢失 `withdraw.completed`，影响 H2 事件可靠性与 H3 对账。

### 批次 1：3 个完全一致的 Service（零风险，先立样板）

`VipService`、`TranslationService`、`LeaderboardService`。三方一致，直接搬，无行为变更。

### 批次 2：2 个已漂移的 Service

`NotificationService`（依赖 `PushService`）、`PayoutService`（依赖 `FeatureFlag` + `EventBus`）。
- `NotificationService`：共享层写"落库 + 站内信"核心，推送发送抽成可选钩子；service 端调用方补 `PushService::send`，admin 端不推。
- `PayoutService`：按批次 0 决策执行。

### 批次 3：admin-only 辅助类反向入池

`SnowflakeService`、`HashidsService`、`EncryptionService` 迁到 `common\`（无 Webman/App 依赖）。`CdnProbeService` 若仅 admin 用则保留 admin。此批解决"共享包依赖 admin 应用目录"的反向依赖。

### 批次 4：Model 收敛到 `common\model\`

先修 4 个漂移 Model（逐字段比对后以 service 端为准或取并集），再把 38 个共表 Model 迁入 `common\model\`。admin 独占表 Model 留 admin：`AdminUser`、`AdminRole`、`AdminPermission`、`OperationLog`、`StatDaily`、`SystemConfig`、`Ticket`/`TicketReply`（若 service 已有则迁）、`User2FA`（service 独占）。

### 批次 5：清理

删除 `admin/app/service/` 下已迁空的重复文件；合并重复测试；更新 `admin/common/` 与 `service/common/` 的 README。

### 依赖顺序（关键路径）

```
批次0 (FeatureFlag/EventBus)
   └─> 批次2 (PayoutService, NotificationService)
批次1 (3 个一致 Service)          ← 可与批次0 并行
   └─> 批次5 (清理/测试合并)
批次3 (admin-only 辅助类)         ← 可与批次1 并行
   └─> 批次4 (Model 收敛)         ← 最后，影响面最大
```

---

## 4. 命名空间约定

| 目录 | namespace | 放什么 |
|---|---|---|
| `packages/platform-common/src/` | `common\` | 纯工具类（`CircuitBreaker`、`Retry`、`SnowflakeService`、`HashidsService`、`EncryptionService`）—— 不引用宿主 `app\*` |
| `packages/platform-common/src/service/` | `common\service\` | 跨端业务 Service |
| `packages/platform-common/src/model/` | `common\model\` | 跨端共表 Eloquent Model |

约束：

1. `common\`（根）**不得**引用任何 `app\*`；这是"是否该进共享层"的判定线。
2. `common\service\` **可以**引用 `common\model\`，但在批次 4 完成前允许临时引用 `app\model\*`（现状即如此）。
3. `common\service\` **不得**反向依赖宿主应用目录（`admin/app/common`、`service/app/event`）。需要宿主能力的，通过参数注入或 `class_exists` 守卫。
4. 类名保持文件同名（PSR-4 大小写敏感，Linux 部署下 `use` 大小写不一致会直接 fatal）。

---

## 5. 具体迁移步骤

以 `VipService` 为样板，每个 Service 执行同一套 6 步：

**步骤 1 — 移动文件**

```bash
git mv admin/app/service/VipService.php packages/platform-common/src/service/VipService.php
```

**步骤 2 — 改 namespace**

```php
namespace common\service;
```

**步骤 3 — 调整内部 `use`**

`app\model\*` 暂时保留（两应用同名同表，行为不变）；批次 4 再统一改成 `common\model\*`。`app\common\SnowflakeService` 在批次 3 后改为 `common\SnowflakeService`。

**步骤 4 — 改调用方 import**

全局替换调用方的 `use app\service\VipService;` → `use common\service\VipService;`，以及所有 `new VipService` / `::class` 引用。

```bash
grep -rln "app\\\\service\\\\VipService\|use .*\\\\VipService" admin/ service/ --include="*.php"
```

**步骤 5 — 删除 service 端重复副本**

```bash
git rm service/app/service/VipService.php
```

注意：若 service 端副本与 admin 端不一致（`NotificationService`、`PayoutService`），必须先按 §2.4 差异原因做语义合并，**不能**直接删除任何一方。

**步骤 6 — 重建 autoload + 跑测试**

```bash
cd admin   && composer dump-autoload
cd service && composer dump-autoload
bash tests/api/run_all.sh
```

path repository 指向 `../packages/platform-common`，无需 `composer update`。

---

## 6. 数据访问适配

### 6.1 DB 连接：无需适配

两个应用连同一个 `game-platform` 库、同一组 `game_` 表、同一套环境变量。共享 Service 直接沿用 `support\Db` 与 `support\Redis`，不涉及多数据源路由。

**不要在共享层写 `Db::connect()`**：当前全项目没有多连接调用，引入即过度设计。

### 6.2 Eloquent 引导：宿主负责，共享层只出类

两个应用各自用 `support/bootstrap/Database.php` 里的 `Capsule::setAsGlobal()` + `bootEloquent()` 初始化 Eloquent，且全局单例。因此 `common\model\X` 只要可被 autoload，在任一端都直接可用 —— **Model 迁移不需要动任何引导代码**。

### 6.3 Model 放置：迁入 `common\model\`，按表归属划分

- **共表 Model**（38 个）→ `common\model\`；
- **admin 独占表**（`game_admin_user`、`game_admin_role`、`game_admin_permission`、`game_admin_user_role`、`game_admin_role_permission`、`game_operation_log`、`game_stat_daily`、`game_system_config`）→ 留 `admin/app/model/`；
- **service 独占表**（`game_platform_revenue`、`game_referral_reward`、`game_referral_commission`、`game_user_2fa`、`game_friend`）→ 留 `service/app/model/`。

### 6.4 Model 漂移处理顺序

`CountryConfig`、`DepositOrder`、`PaymentMethod`、`UserWallet` 四个漂移文件，处理规则：

1. 逐字段 `diff`，列出字段级差异；
2. 以 `install/install.sql` 的实际列定义为裁判 —— Model 字段必须与 DDL 对齐；
3. 两端都在用的字段取并集，仅一端新增的字段需确认 DDL 是否已加列，DDL 未加则视为该端脏 Model，删除该字段。

### 6.5 表前缀

`prefix => 'game_'` 由各宿主应用配置，`Model::$table` 保持不带前缀的裸表名（如 `'deposit_order'`），与现状一致。

---

## 7. 回归测试策略

### 7.1 迁移前（建立基线）

```bash
bash tests/api/run_all.sh        # admin + service API 黑盒，必须全绿并记录结果
cd admin  && ./vendor/bin/phpunit
cd service && ./vendor/bin/phpunit
```

基线未全绿时**不要开始迁移** —— 否则无法区分失败是迁移引入还是既有问题。

### 7.2 每个 Service 迁移后

1. `php -l` 检查被改文件语法；
2. `grep` 确认无残留旧引用：`grep -rn "app\\\\service\\\\" admin/ service/ --include="*.php"`；
3. `bash tests/api/run_all.sh`；
4. 跑相关单测：`VipService` → `admin/tests/LeaderboardServiceTest.php` 等对应文件。

### 7.3 测试文件本身的处理

- `admin/tests/PayoutServiceTest.php` 与 `service/tests/PayoutServiceTest.php` 重名且各自断言本地类。迁移后**合并为一份**，断言 `common\service\PayoutService`，放入 `admin/tests/`（platform-common 的既有测试都在这，见 `PlatformCommonTest.php`）。
- 保留 `admin/tests/PlatformCommonTest.php` 作为共享层的守门测试，每批次追加用例。

### 7.4 手工冒烟（API 黑盒覆盖不到的）

- admin：登录 → 打开运营看板（走 `AnalyticsController` + `GameDashboardService`）；
- service：触发一次充值回调（走 `DepositController` + `DepositLogService`）、一次提现（走 `PaymentController` + `PayoutService`，验证 `withdraw.completed` 事件仍发出）。

### 7.5 漂移守卫（防回归）

批次 5 增加一条 CI 检查：对共享 Service 名单跑 `diff -q`，任一端残留同名文件即失败。

```bash
for f in VipService TranslationService LeaderboardService NotificationService PayoutService; do
  test ! -f "admin/app/service/$f.php" || { echo "DRIFT: $f in admin"; exit 1; }
  test ! -f "service/app/service/$f.php" || { echo "DRIFT: $f in service"; exit 1; }
done
```

---

## 8. 验收标准

"完成"的明确定义：

1. `packages/platform-common/src/service/` 包含 9 个 Service：`DepositLogService`、`GameDashboardService`、`GamePlayLogService`、`ProbabilityService`、`VipService`、`TranslationService`、`LeaderboardService`、`NotificationService`、`PayoutService`；
2. `admin/app/service/` 与 `service/app/service/` 中不再存在上述 9 个同名文件；
3. `common\service\*` 与 `common\` 下无任何 `app\*` 的 `use` 语句（`common\service` 对 `app\model` 的引用在批次 4 后清零）；
4. 38 个共表 Model 位于 `common\model\`，`admin/app/model/` 与 `service/app/model/` 不再重复；4 个漂移 Model 已与 `install/install.sql` DDL 对齐；
5. `bash tests/api/run_all.sh` 全绿，退出码 0；
6. admin 与 service 两侧 phpunit 套件全绿；
7. §7.5 的漂移守卫脚本通过；
8. `grep -rn "app\\\\service\\\\" admin/ service/ --include="*.php"` 无输出。

---

## 9. 风险与缓解

| 风险 | 影响 | 缓解 |
|---|---|---|
| **`PayoutService` 迁移后 `withdraw.completed` 静默丢失** | H2 事件可靠性、H3 对账断链 | 统一走三参 `common\service\EventPublisher::push($event, $eventId, $payload)`。H1 批次 0 只加类本体（默认 no-op，不注册）；`EventBus::push()` + `setPublisher()` 由 H2 步骤 2 同一提交落地（§3 批次 0），无中间状态、无静默降级窗口。no-op 不算静默：`service/app/process/Monitor.php` 对账巡检 LEFT JOIN outbox 表，缺失行可直接查出 |
| **`PushService` 只在 service 端，`NotificationService` 迁后 admin 调用报错** | admin 通知功能崩溃 | 推送发送抽成可选钩子，共享层核心只做落库 + 站内信；admin 端不注册推送钩子 |
| **半迁移状态（文件已搬但调用方未改完）** | 生产 fatal `Class not found` | 每批"移动 → 改引用 → 删副本"在同一 commit 内完成；每批独立提交，可单独 revert |
| **Model 迁移破坏 Eloquent 全局绑定** | 全部数据库操作失败 | 先小范围验证：批次 4 先迁 1 个 Model（如 `GamePlayLog`）并跑全量 API 测试，确认后再批量；Capsule 是宿主引导的全局单例，理论上 Model 只需可 autoload |
| **Model 漂移字段删除导致数据静默丢失** | 写入失败或字段被丢弃 | 以 `install/install.sql` DDL 为唯一裁判，不凭主观判断删字段；DDL 有列才保留 Model 字段 |
| **PSR-4 大小写不一致** | Linux 部署下 fatal | 改 namespace 时同步检查所有 `use` 语句大小写，`composer dump-autoload --optimize` 后跑 `tests/api/run_all.sh` 验证 |
| **composer autoload 缓存未刷新** | 改动不生效，误判"通过" | 每批结束显式 `composer dump-autoload`；CI 中加一步 |
| **两个应用未同时部署新版本** | 一端用旧类、一端用新类 | 共享层是 path repository，两应用同仓同发；部署顺序 service → admin，共享包源码同仓原子生效 |
| **测试断言本地类，迁移后全部失效** | phpunit 大面积红 | 迁移前先把重名测试合并（§7.3），避免批次 2 结束时面对 10+ 个失败测试 |

### 回滚策略

每个批次一个独立 commit，回滚即 `git revert <batch-commit>`，无跨批次纠缠。关键路径上的前置依赖（批次 0）单独 commit，便于定位。

若某批在生产出问题且无法快速修复：**先 revert 该批**，不要尝试热修。共享层是代码组织调整，不涉及数据迁移，revert 无损。

---

## 附：建议的执行节奏

1 天建基线 + 批次 0 + 批次 1（零风险，建立迁移肌肉记忆）；
1 天批次 2（两处需语义合并，风险最高）；
半天批次 3；
1 天批次 4（先 1 个 Model 试点，再批量）；
半天批次 5 + 漂移守卫入 CI。

总计约 4–5 个工作日，每批独立可回滚。
