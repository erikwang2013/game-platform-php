# 中优先级（M）扩展方案设计

- 日期：2026-08-31
- 项目：全球游戏聚合平台（PHP / webman v2，admin + service 双应用）
- 范围：M1 统一钱包 · M2 Provider 接入规范 · M3 运营活动引擎 · M4 社交拉新 · M5 多游戏聚合 · M6 风控可视化
- 优先级：中（H1–H5 之后实施，M6 显式依赖 H4/H5）

---

## 0. 现状盘点（代码实证）

设计前已核对的关键事实，后续方案均以此为前提：

| 项 | 现状 | 位置 |
|---|---|---|
| 平台币钱包 | `game_user_wallet`：balance / frozen_balance / total_earned / total_spent / version，`uk_user_id` | install.sql:156 |
| 游戏币钱包 | `game_user_game_wallet`：balance / frozen_balance，`uk_user_game_currency` | install.sql:174 |
| 平台流水 | `game_transaction`：type ∈ deposit/withdraw/exchange_in/exchange_out/game_earn/game_spend，**无 game_id / currency_id 列** | install.sql:311 |
| 钱包写方法 | 仅 `UserWallet::addBalance/deductBalance`（5 次重试乐观锁 + `lockForUpdate`）；`UserGameWallet` **无任何方法** | service/app/model/UserWallet.php:43 |
| 冻结列 | 两张钱包表都有 `frozen_balance`，**代码里没有任何锁/解锁调用** | 全仓 grep 无命中 |
| Provider | `ProviderFactory::create(Game)` match `game.type` → `self` / `third_party`；接口 6 方法 | service/app/provider/ |
| 支付网关 | `GatewayFactory::resolve(string)` match，**实际注册 16 个**（stripe…toss） | service/app/payment/GatewayFactory.php:14 |
| 功能开关 | `FeatureFlag` 复用 `platform_config` group=`feature`，支持 crc32 稳定分桶灰度 | service/app/service/FeatureFlag.php |
| 事件 | `EventBus` Redis Pub/Sub，**单一全局 channel** `platform:events`，emit 吞异常 | service/app/event/EventBus.php |
| 风控 | `game_risk_rule`（type/config/action/priority）+ `game_risk_log`（rule_id/context/result） | install.sql:562 |
| 分析库 | ClickHouse 已有 `game_game_play_log`（含 ip_address/user_agent）、`game_deposit_log` | install/clickhouse.sql |

**三个必须在 M1 一并处理的既有缺陷**（不是新需求，是设计前提）：

1. **精度不一致**：`UserWallet::addBalance` 用 `bcadd(..., 4)`，`SelfProvider` 用 `bcadd(..., 8)`，而两张钱包表都是 `DECIMAL(18,4)` → 游戏币按 8 位计算后写入被截断。
2. **`SelfProvider::bet()` 查钱包少一列**：按 `(user_id, game_id)` 查询，漏了 `currency_id`；一游戏多币种时取错行。而 `getBalance()` 带了 currency_id，两处不一致。
3. **游戏币无流水**：`game_transaction` 只能记平台币，ProviderController 只写 `GamePlayLog`。游戏币收支目前**不可对账**（这也是 H3 对账模块的输入缺口）。

---

## M1 — 统一钱包 API（Unified Wallet）

### 目标

把「平台币」和「游戏币」收敛到**一套写路径 + 一套流水**，补齐锁/解锁能力，消除精度与查错行缺陷。不新建钱包表、不新建流水表。

### 现状

单钱包 vs 多钱包：现状是**多钱包存储、单钱包接口**。

- 存储层已经是双表：平台币 1 个账户/人；游戏币 N 个账户/人（每游戏每币种一个）。这是正确的物理设计，不应合并。
- 接口层只有 `UserWallet` 有方法，`UserGameWallet` 裸奔；所有游戏币写操作散落在 `SelfProvider`（bet/settle/refund）里手写字段赋值。
- `frozen_balance` 两表都有但无人使用 → 提现、下注、对账冻结三件事**都没法做**。

结论：问题不在表数量，在缺少统一的「账户寻址 + 事务内变更 + 流水」原语。

### 方案设计

**核心原语只有一个**：`Wallet::mutate(scope, delta, ref)`。余额变更、冻结、解冻、对账修正全部走它。

```
service/app/service/WalletService.php   ← 唯一入口（新增）
```

接口（5 个方法，不做钱包「引擎」）：

```php
// scope 二选一：平台币 = WalletScope::platform()；游戏币 = WalletScope::game($gameId, $currencyId)
WalletService::balance(int $userId, WalletScope $s): string
WalletService::mutate(int $userId, WalletScope $s, string $delta,
                      string $type, string $refType, int $refId, string $remark = ''): bool
WalletService::lock(int $userId, WalletScope $s, string $amount, string $refType, int $refId): bool
WalletService::unlock(int $userId, WalletScope $s, string $amount): bool
WalletService::ledger(int $userId, WalletScope $s, string $cursor, int $limit = 20): array
```

`mutate` 内部做四件事，缺一不可：

1. `SELECT ... FOR UPDATE` 取账户行（不存在则创建，事务内 upsert）；
2. `balance + delta`，拒绝负余额；`version++` 乐观锁兜底；
3. 写 `game_transaction`（同事务）——**流水与余额同事务**，这是修复缺陷 3 的关键；
4. `emit('wallet.mutated', [...])` 给 EventBus（成就/风控订阅）。

`lock` / `unlock` 实现：`lock` = `balance -= n; frozen_balance += n`；`unlock` 反向。两者都写流水（type=`lock`/`unlock`）。这是把死列激活的唯一方式。

精度统一：**代码统一到 scale 8**，表结构改 `DECIMAL(20,8)`。理由：`SelfProvider` 已按 8 位运算，收窄到 4 会丢钱；放宽是纯加法变更，不影响现有数据。

同时修掉缺陷 2：`mutate`/`balance` 统一按 `(user_id, game_id, currency_id)` 三元定位。

### 数据模型

只改一张表，加三列（纯加法迁移）：

```sql
ALTER TABLE `game_user_wallet`      MODIFY `balance` DECIMAL(20,8) UNSIGNED NOT NULL DEFAULT 0.00000000,
                                   MODIFY `frozen_balance` DECIMAL(20,8) UNSIGNED NOT NULL DEFAULT 0.00000000,
                                   MODIFY `total_earned`  DECIMAL(20,8) UNSIGNED NOT NULL DEFAULT 0.00000000,
                                   MODIFY `total_spent`   DECIMAL(20,8) UNSIGNED NOT NULL DEFAULT 0.00000000;
ALTER TABLE `game_user_game_wallet` MODIFY `balance` DECIMAL(20,8) UNSIGNED NOT NULL DEFAULT 0.00000000,
                                   MODIFY `frozen_balance` DECIMAL(20,8) UNSIGNED NOT NULL DEFAULT 0.00000000;

-- 单一流水表扩展到双币种，不建第二张流水表
ALTER TABLE `game_transaction`
    ADD COLUMN `scope`      VARCHAR(16)  NOT NULL DEFAULT 'platform' AFTER `type`,
    ADD COLUMN `game_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0       AFTER `scope`,
    ADD COLUMN `currency_id` BIGINT UNSIGNED NOT NULL DEFAULT 0      AFTER `game_id`,
    ADD INDEX `idx_user_scope` (`user_id`, `scope`);
```

`type` 枚举扩展：`lock` / `unlock` / `reconcile`（后者给 H3 对账修正用）。

### API 设计

对账是 M1 的验收核心，不是附属品：

```
GET  /api/v1/wallet/balance            ?scope=platform | game&game_id=&currency_id=
POST /api/v1/wallet/transfer           {to_user_id, amount, scope, memo}   # 内部转账，双边同事务
POST /api/v1/admin/reconcile/run       {game_id?, since, until, dry_run}
GET  /api/v1/admin/reconcile/report    ?game_id=&date=
```

对账算法（单轮，不做增量位点，ponytail: 全量扫描 24h 窗口，量大了再改成水位线增量）：

```
对每个钱包账户：
  expect = 期初余额 + SUM(game_transaction.amount WHERE ref_type IN 平台/游戏来源)
  actual = 当前 balance + frozen_balance
  |expect - actual| > 0.00000001 → 记入差异清单
差异处理：dry_run 只出报告；dry_run=false 写 type=reconcile 的修正流水（不改 balance，留痕给审计）
```

### 迁移方案

四步，每步可独立回滚，无需停机：

1. **D1 结构**：执行上面的 ALTER（MODIFY + ADD COLUMN 全部加法，线上可安全执行）。
2. **D2 双写**：`SelfProvider` 的 bet/settle/refund 改为调 `WalletService::mutate`。此时 `game_transaction` 开始同时有 platform/game 两类流水，`scope` 默认值兜住存量。
3. **D3 修缺陷**：`UserWallet::addBalance` 内部委托给 `WalletService::mutate`（保留静态方法签名，调用方零改动）；修 `SelfProvider::bet()` 的 currency_id。
4. **D4 回填对账**：跑一次 `reconcile/run?dry_run=true`，确认差异清单为空或可解释；之后每 6h 定时跑。

`UserWallet::addBalance` 保留签名是关键——PayoutService 等存量调用方不需要动。

### 验收标准

- [ ] 游戏币 bet → settle → refund 全流程，`game_transaction` 中出现对应流水行，`scope='game'`。
- [ ] 并发 50 线程对同一账户同时 bet，最终余额 = 初始 - Σbet，无超扣、无丢更新（压测脚本断言）。
- [ ] 锁/解锁：`lock(100)` 后 `balance` 减 100、`frozen_balance` 加 100，`unlock` 后恢复。
- [ ] 0.00000001 级别的金额（8 位小数）能正确存储与结算，不被截断。
- [ ] 一游戏两币种：同一用户两币种余额互不串扰（回归缺陷 2）。
- [ ] `reconcile/run` dry_run 返回差异清单，余额与流水求和一致时清单为空。

---

## M2 — Provider 接入规范文档

### 目标

把「怎么接一个新支付网关」「怎么接一个新游戏」写成可照着执行的文档，让接入成本从「读源码」降到「照清单打勾」。纯文档产出，不改代码。

### 现状

两套工厂模式已稳定存在，但只体现在代码里，无文档：

- **GatewayFactory**：`match ($provider)` 硬编码 16 个分支，每个网关一个类实现 `PaymentGatewayInterface`。
- **ProviderFactory**：`match ($game->type)` 按 `self`/`third_party` 分流，`GameProvider` 抽象类提供 6 个抽象方法 + `signRequest()` + `config()`。
- 已有的横切能力没被记录：`CircuitBreaker::call('provider:'.$name, ...)` 熔断、`Retry::isRetryable()` 重试分类、`FeatureFlag::isEnabled('provider_mock')` 本地 mock。
- 签名协议已固化但无文档：HMAC-SHA256，签名串 `game_id:ts:METHOD:path:bodyJson`，headers `X-Game-Id / X-Timestamp / X-Signature`。

### 方案设计

文档结构（一份文件，不拆章节文件）：`admin/docs/provider-onboarding.md`

三块内容：

**1. 现有扩展模式总结**（一页图 + 表）

| 层 | 入口 | 策略 | 失败语义 |
|---|---|---|---|
| 支付 | `GatewayFactory::resolve()` | `match` 硬分支 + `PaymentGatewayInterface` | 抛 `InvalidArgumentException` |
| 游戏 | `ProviderFactory::create(Game)` | `match` `game.type` + `GameProvider` 抽象类 | 同上 |

指出两个已知短板并在指南里给出规避：`match` 新增分支要改工厂（不是 SPI 自动发现，可接受——16 个网关不值得引入反射发现）；`self`/`third_party` 只有两种 type，接入 H5 内嵌游戏需要第三种 type（见 M5）。

**2. 接口约定**（直接抄代码，不重写）

- 支付网关必实现方法清单 + 每个方法的输入/输出契约。
- 游戏 Provider 6 方法签名 + 返回数组约定字段：`success` / `transaction_id` / `balance_after` / `win_amount` / `already_processed` / `error`。
- 幂等约定：`round_id` 是唯一幂等键；`settle`/`refund` 重复调用必须返回 `already_processed=true` 且**不重复入账**（SelfProvider 已按此实现，指南要求第三方游戏严格遵守）。
- 签名协议全文 + curl 示例 + 一个可运行的验签脚本（`scripts/verify-signature.php`）。

**3. 测试策略**

- 网关：`provider_mock` flag 开 → 返回 `['success'=>true]`，用于本地联调与 CI。
- 回调：签名校验失败一律 401，不写任何单据；重放窗口由 `X-Timestamp` 控制（**当前代码未校验时间戳窗口**，指南里作为必做检查项标出）。
- 合同测试：每个新接入需提交一份 contract test，断言 6 方法的返回结构字段齐全。

### 接入新支付网关 Step-by-Step

1. 建 `service/app/payment/<Vendor>Gateway.php`，实现 `PaymentGatewayInterface`，类名 PascalCase，构造注入配置。
2. 配置来源：`PlatformConfig` 读 `<vendor>.api_key` / `<vendor>.webhook_secret` / `<vendor>.base_url`（不新增 env 文件）。
3. 在 `GatewayFactory::resolve()` 的 `match` 里加一行 `'<vendor>' => new <Vendor>Gateway(),`。
4. 加 `PaymentMethod` 记录（管理端支付方式列表）+ 路由 `/api/v1/payment/webhook/<vendor>`。
5. webhook 验签 → 幂等落单（订单号唯一键）→ 回调 `DepositOrder` 状态机 → `emit('payment.confirmed')`。
6. 合同测试 + `provider_mock` 联调通过。

**共 6 步，唯一需要改存量文件的是第 3 步（1 行）。**

### 接入新游戏 Provider Step-by-Step

1. 建 `game_game` 记录：`type='third_party'`、`api_endpoint`、`api_secret`、`provider_config`（JSON，放厂商特有字段，如 `notify_url`、`region`）。
2. 若协议是标准 HTTP 回调 → 直接用 `ThirdPartyProvider`，**零新代码**。
3. 若协议非标准（字段名不同、签名算法不同、需要轮询而非回调）→ 建 `service/app/provider/<Vendor>Provider.php` 继承 `GameProvider`，只重写差异方法。
4. 若游戏类型不属于 self/third_party（如内嵌 H5、Unity SDK）→ 见 M5，需扩展 `ProviderFactory` 的 type。
5. 本地用 `provider_mock` on 验证 `ProviderController` 全链路，再切真实 endpoint。
6. 提交 contract test + 联调对账单。

### 验收标准

- [ ] 文档覆盖两套工厂、两个接口、签名协议、幂等约定、熔断重试 mock 三个横切能力。
- [ ] 照文档接入一个假网关，除 `GatewayFactory` 加 1 行外不改其他存量代码。
- [ ] 照文档接入一个假游戏（非标准签名），只新增 1 个 Provider 类。
- [ ] `scripts/verify-signature.php` 可独立运行，能验出篡改与过期时间戳。
- [ ] 每个步骤都是可勾选的，无「参考源码自行实现」这类模糊表述。

---

## M3 — 运营活动引擎

### 目标

把签到、每日任务、转盘、限时活动做成**配置驱动**，运营在管理端建活动不需要发版；奖励发放复用 M1 钱包与已有成就体系。

### 现状

`game_coupon` / `game_tournament` / `game_achievement` 三套并存，各自硬编码：

- 优惠券：`type=fixed/rate`，`conditions` 字段在模型 fillable 里但 **SQL 里不存在**（模型与表不一致，顺手修）。
- 锦标赛：`type` 字段有但无枚举约束，`prize_pool`/`entry_fee` 用 `UserWallet` 手工结算。
- 共同短板：**没有「周期性目标 + 进度累加 + 达标发奖」这个抽象**，签到和每日任务想做就得写死。
- 可复用的两个现成组件：`FeatureFlag::inRollout()`（灰度放量）、`EventBus`（成就引擎已在消费）。

### 方案设计

**一个活动表 + 一个参与表 + 一个发奖表**。不建 `activity_config` 表——配置就是 JSON 列，活动类型才 4 种，拆表是过度设计。

架构：

```
AdminConfig(JSON) ──> game_activity (定义)
                          │  管理端读写，Redis 缓存 5min
EventBus ──> ActivityProgressListener (消费事件累加进度)
                          │
                          v
game_activity_participation (进度) ──达标──> ActivityRewardService ──> M1 WalletService::mutate
                          │                                      └──> AchievementService::grant (复用)
                          v
game_activity_reward_log (发奖留痕，幂等键)
```

**活动类型用策略类，不写 switch 大杂烩**：

```php
// service/app/activity/ActivityHandlerInterface.php
interface ActivityHandlerInterface {
    public function canJoin(int $userId, Activity $a): bool;
    public function onProgress(int $userId, Activity $a, array $event): ?array; // 返回 [target, current]
    public function defaultConfig(): array;
}
```

4 个实现：`SignInHandler`、`DailyTaskHandler`、`SpinHandler`、`FlashHandler`。工厂 `ActivityHandlerFactory` match `activity.type` —— 与 `ProviderFactory` 同构，保持全仓风格一致。

`SpinHandler` 的抽奖不走「概率计算」，走 `config.pools[{reward, weight}]` 加权随机 + 奖池余量扣减（`SELECT ... FOR UPDATE` 扣 `stock`），防超发。

**事件接入**（这是与 H2 的接口点）：M3 消费的 EventBus 事件先定义为：`user.deposited`、`game.round_settled`、`user.registered`、`user.signin`。M3 只消费不生产；进度累加幂等键 = `activity_id + user_id + period_key`。

**周期键** `period_key`：`YYYY-MM-DD`（每日）、`YYYY-Www`（周）、`all`（一次性）。它是幂等的核心，也是管理端「补发奖」的抓手。

### 数据模型

```sql
-- 活动定义（配置驱动，运营在管理端建）
CREATE TABLE `game_activity` (
    `id` BIGINT NOT NULL,
    `type` VARCHAR(30) NOT NULL COMMENT 'signin/daily_task/spin/flash',
    `name` VARCHAR(100) NOT NULL,
    `game_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=全平台',
    `config` JSON NOT NULL COMMENT '目标/周期/奖池/概率/门槛，按 type 定义 schema',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=禁用 1=启用 2=已结束',
    `start_at` DATETIME DEFAULT NULL,
    `end_at` DATETIME DEFAULT NULL,
    `rollout_percent` INT UNSIGNED NOT NULL DEFAULT 100 COMMENT '配合 FeatureFlag::inRollout',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_dates` (`status`, `start_at`, `end_at`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 参与记录（进度）
CREATE TABLE `game_activity_participation` (
    `id` BIGINT NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `activity_id` BIGINT UNSIGNED NOT NULL,
    `period_key` VARCHAR(16) NOT NULL COMMENT 'YYYY-MM-DD / YYYY-Www / all',
    `current` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '当前进度',
    `target` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '目标值（快照，活动改配置不影响历史）',
    `status` VARCHAR(20) NOT NULL DEFAULT 'progressing' COMMENT 'progressing/completed/rewarded',
    `completed_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_activity_period` (`user_id`, `activity_id`, `period_key`),
    KEY `idx_activity_status` (`activity_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 发奖记录（幂等留痕）
CREATE TABLE `game_activity_reward_log` (
    `id` BIGINT NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `activity_id` BIGINT UNSIGNED NOT NULL,
    `participation_id` BIGINT UNSIGNED NOT NULL,
    `period_key` VARCHAR(16) NOT NULL,
    `reward_type` VARCHAR(20) NOT NULL COMMENT 'platform_coin/game_coin/vip_exp/coupon/achievement',
    `reward_ref` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '券ID/成就ID/游戏ID+币种',
    `amount` DECIMAL(20,8) NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/succeeded/failed/refunded',
    `fail_reason` VARCHAR(255) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_idempotent` (`participation_id`, `reward_type`, `reward_ref`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

幂等设计要点：`participation` 的 unique key 保证「同用户同活动同周期只有一条进度」；`reward_log` 的 unique key 保证「同一条进度的同一类奖只发一次」；两者都是**用唯一键防重，不靠应用层加锁**。这是整个引擎的可靠性基础。

顺带修：`game_coupon` 补 `conditions` JSON 列（模型已声明但表里缺）。

### API 设计

C 端（用户）：

```
GET  /api/v1/activities/list            ?game_id=      # 只返进行中 + 命中灰度的活动
GET  /api/v1/activities/{id}             # 定义 + 我的进度 + 下一档奖
POST /api/v1/activities/{id}/checkin     # 签到（或通用：POST /progress，上报事件由后端映射）
POST /api/v1/activities/{id}/spin        # 转盘专用（其余类型不用）
GET  /api/v1/activities/progress         # 汇总各活动进度，C 端首页用
```

管理端：

```
POST/PUT/GET  /api/v1/admin/activities[/{id}]          # 建/改/查活动（config JSON 按 type 做 schema 校验）
POST          /api/v1/admin/activities/{id}/reward/resend  {user_id?, period_key?}  # 补发
GET           /api/v1/admin/activities/{id}/stats         # 参与人数/完成率/发奖总额
```

`config` 按 type 的 schema（写进 M2 文档同级的 `admin/docs/activity-config-schema.md`）：

- signin：`{cycle:7|30, rewards:[{day, reward}], max_streak_bonus}`
- daily_task：`{tasks:[{event, target, reward}], reset_hour}`
- spin：`{pools:[{reward_type, reward_ref, amount, weight, stock}], daily_free}`
- flash：`{window_minutes, stock, early_bird_rule}`

### 与 EventBus + 成就的集成

- **消费**：新增 `service/app/process/ActivityConsumer.php`，订阅 `platform:events`，按 `event` 前缀路由到对应 handler。注意 EventBus 是**单一全局 channel**——所有消费者都收全部消息，这里做前缀过滤即可，不要改 EventBus 结构（改 channel 拓扑是 H2 的活）。
- **发放**：发奖统一走 M1 的 `WalletService::mutate`（平台币/游戏币）或 `AchievementService::grant`（成就）。活动引擎**自己不直接改余额**，保证资金入口唯一。
- **回流**：发奖成功后 `emit('activity.rewarded', ...)`，成就引擎可把它作为达成条件之一（如「完成 3 个活动」）。

### 验收标准

- [ ] 管理端创建 4 种类型活动各一个，不发版即生效（config JSON 校验通过）。
- [ ] 同用户同活动同周期重复上报事件，`participation.current` 只累加一次（幂等断言）。
- [ ] 发奖重试 3 次，`reward_log` 只出现 1 条 succeeded（不重发）。
- [ ] 转盘 10 万次抽奖，各奖品实际发放量与权重偏差 < 2%，且不超发 `stock`。
- [ ] `rollout_percent=0` 时 C 端列表完全不返回该活动；`=100` 时全量返回。
- [ ] 发奖金额与 M1 `game_transaction` 流水完全对得上（type 区分活动发奖）。
- [ ] 活动配置热更新（改 target）只影响新周期，历史 `participation.target` 快照不变。

---

## M4 — 社交 / 拉新深化

### 目标

在好友/聊天/推荐之上补齐**组队、公会、邀请裂变、分享激励**，复用已有推荐与活动引擎，不重建激励体系。

### 现状

- `game_referral`：`referrer_id / referred_id / code`，`uk_referred_id`（每人只有一个推荐人，无法做多级）。
- `game_referral_reward` + `game_referral_commission`：奖励与佣金已在。
- `game_friend`、`game_message`：好友与聊天已在。
- 缺口：无组队、无公会、无裂变层级、无分享追踪。

### 方案设计

**核心决策：组队和公会是同一个形状**——「一组人 + 成员角色 + 归属游戏」。合并成一张 `game_group` + 一张 `game_group_member`，用 `type` 区分。省掉 4 张表，管理端也能复用同一套页面。

```
组队（type=team）：1 局游戏 N 人，短时，解散快
公会（type=guild）：跨游戏，长期，有等级/公告
```

裂变不复用 `game_referral` 的树（`uk_referred_id` 约束死了一对一），改为**活动引擎驱动**：M3 的 `flash` 类型加一个 `invite` 子模式，用 `period_key` 做窗口，用 `reward_log` 做幂等发奖。分享追踪只需一个短码表。

### 数据模型

```sql
CREATE TABLE `game_group` (
    `id` BIGINT NOT NULL,
    `type` VARCHAR(16) NOT NULL COMMENT 'team / guild',
    `name` VARCHAR(100) NOT NULL,
    `game_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'team 必填；guild 0=跨游戏',
    `owner_id` BIGINT UNSIGNED NOT NULL,
    `level` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '公会等级；team 恒为 1',
    `xp` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '公会经验',
    `member_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '冗余计数，避免 COUNT 查询',
    `announcement` TEXT COMMENT '公会公告；team 留空',
    `expire_at` DATETIME DEFAULT NULL COMMENT 'team 到期自动解散',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=正常 0=解散',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type_game` (`type`, `game_id`),
    KEY `idx_type_level` (`type`, `level` DESC),
    KEY `idx_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `game_group_member` (
    `id` BIGINT NOT NULL,
    `group_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role` VARCHAR(16) NOT NULL DEFAULT 'member' COMMENT 'owner/admin/member/guest',
    `contrib` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '贡献值（公会排行榜用）',
    `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `left_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_group_user` (`group_id`, `user_id`),
    KEY `idx_user_role` (`user_id`, `role`),
    KEY `idx_group_contrib` (`group_id`, `contrib` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 分享短码（裂变追踪），短生命周期，可归档
CREATE TABLE `game_share_link` (
    `id` BIGINT NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `activity_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联 M3 活动，0=无活动',
    `short_code` VARCHAR(12) NOT NULL,
    `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
    `conversions` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '点击后成功注册数',
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_short_code` (`short_code`),
    KEY `idx_user` (`user_id`),
    KEY `idx_activity` (`activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`left_at` 软删除而非删行：公会历史记录（谁在什么时间在过）是可分析资产。

### API 设计

C 端：

```
POST /api/v1/groups                  {type, name, game_id}      # 建团/建会
GET  /api/v1/groups/{id}
GET  /api/v1/groups/{id}/members     ?sort=contrib
POST /api/v1/groups/{id}/join        / POST /{id}/leave
PUT  /api/v1/groups/{id}/role        {user_id, role}            # 仅 owner/admin
POST /api/v1/shares                  {activity_id?}             # 生成短码
POST /api/v1/shares/visit            {short_code}               # 落地页点击上报（匿名可访问）
```

管理端：

```
GET  /api/v1/admin/groups            ?type=&game_id=&status=
GET  /api/v1/admin/groups/{id}/audit                       # 成员变动流水
GET  /api/v1/admin/share/stats       ?activity_id=&from=&to=  # 裂变漏斗：分享→点击→转化
```

权限规则（写进验收）：team 任意成员可解散（`expire_at` 到期自动）；guild 仅 owner 可解散，转让 owner 需 `PUT /{id}/transfer`（不在本期，标注为后续）。

### 验收标准

- [ ] team 与 guild 走同一张表同一套 CRUD，管理端一套页面能看两种类型。
- [ ] 加入/退出并发安全：`uk_group_user` 防止同一用户在同组出现两条有效记录。
- [ ] `member_count` 与 `game_group_member` 实际数量在并发加退 100 次后一致（定时校正任务兜底）。
- [ ] team 到期 `expire_at` 后自动置 `status=0`，成员关系保留可查。
- [ ] 短码分享落地页匿名可访问，不泄露分享者信息（只记 user_id 到内部表）。
- [ ] 裂变转化数与 `game_referral` 新增行数、M3 `reward_log` 成功数三者对得上。
- [ ] 公会排行榜按 `contrib DESC` 查询命中 `idx_group_contrib` 索引（EXPLAIN 验证）。

---

## M5 — 游戏内容扩充（多游戏聚合）

### 目标

从 `game/xiaoxiaole` 一个自研游戏扩展到多游戏聚合，建立统一接入层。核心问题：**接入形态不止「自研服务」和「HTTP 第三方」两种**。

### 现状

- `game_game.type` 目前两值：`self`（`SelfProvider`，本地直接操作 `game_user_game_wallet`）与 `third_party`（`ThirdPartyProvider`，Guzzle + 熔断 + 重试）。
- `SelfProvider::getBalance/bet/settle/refund/rollback` 全部落库到 `game_user_game_wallet`，幂等靠 `GamePlayLog.round_id`。
- `ThirdPartyProvider` 的 HTTP 路径与 header 约定已固定，`provider_config` JSON 承接厂商差异。
- 唯一自研游戏 `game/xiaoxiaole` 是 TS 工程（有 `src/runtime/EventLog.ts`），说明自研游戏有自己的事件上报通路，但**不走 Provider 接口**。

### 方案设计

**接入形态三分法**（这是 M5 的核心决策，其余都是附属）：

| type | 归属 | 资金归属 | 结算方式 |
|---|---|---|---|
| `self` | 平台自研服务端 | 平台 `game_user_game_wallet` | 直接写库（现状） |
| `embedded` | H5/Unity SDK 嵌在平台页面 | **平台**（新增，关键区别） | SDK 直连 `/api/v1/game/*`，同 `self` 写库 |
| `third_party` | 外部服务 | 第三方自己 | HTTP 回调 + 轮询对账 |

`embedded` 是这次扩充的**新类型**，也是唯一需要动的地方。理由：H5 游戏嵌在平台里，用户余额必须由平台掌握才能防作弊（否则前端可改）、才能与 M1 钱包对账、才能接入 M6 风控。资金归属不同，不能复用 `third_party`。

实现上 `embedded` **不新建 Provider 类**——它就是 `SelfProvider`，因为资金路径完全一致。区分点在 `ProviderController` 入口：`embedded` 类型走 SDK 接口（前端签名），`self` 类型走服务端内部调用。

```php
// ProviderFactory 最小改动
return match ($game->type) {
    'self', 'embedded' => new SelfProvider($game),
    'third_party'      => new ThirdPartyProvider($game),
    default => throw new \InvalidArgumentException("Unknown game type: {$game->type}"),
};
```

**统一接入层的实际形态**（不新建「网关抽象」，直接复用现有接口）：

- 接入协议层：`PaymentGatewayInterface`（钱）+ `GameProvider`（游戏）—— 已是全平台唯一两个接入接口，不需要第三个。
- 协议差异靠 `provider_config` JSON 消化，不靠新抽象。
- 自研游戏的 `EventLog.ts` 上报统一映射成 `game.round_settled` EventBus 事件 → 同时喂给 M3 活动引擎与 H5 反作弊。**这是自研游戏接入的统一面**。

配套新增：`game_game` 加 `sdk_version` / `platform`（h5/unity/web）/ `region` 三列，供 CDN 与多端渲染分流（配合已有 CDN 模块）。

### 数据模型

```sql
ALTER TABLE `game_game`
    MODIFY `type` VARCHAR(20) NOT NULL DEFAULT 'self' COMMENT 'self/embedded/third_party',
    ADD COLUMN `sdk_version` VARCHAR(20) NULL COMMENT '自研/嵌入式游戏 SDK 版本',
    ADD COLUMN `platform`    VARCHAR(20) NOT NULL DEFAULT 'h5' COMMENT 'h5/unity/web/native',
    ADD COLUMN `region`      VARCHAR(10) NOT NULL DEFAULT 'global' COMMENT '配合 CountryConfig 分流';
```

其余表不动。`game_play_log` 已有 `round_id`/`bet_amount`/`win_amount`，聚合层不需要新流水表——走 M1 的 `game_transaction`。

### API 设计

统一游戏接入层（对 C 端和自研/嵌入 SDK 同一套）：

```
GET  /api/v1/games                    # 游戏列表（按 region/platform 过滤，走 CDN）
GET  /api/v1/games/{id}/info          # 含 sdk_version，前端据此加载对应 SDK
POST /api/v1/game/bet                 # 已存在，SDK 与后端共用
POST /api/v1/game/settle
POST /api/v1/game/refund
POST /api/v1/game/rollback
GET  /api/v1/game/balance             # 多游戏聚合余额，一次返回全部游戏币
GET  /api/v1/game/session             # 签发 SDK 会话（含签名，TTL 5min）
```

`/game/session` 是关键新增：SDK 不自带密钥，由平台签发短期签名会话，防前端伪造。

管理端：

```
POST/PUT/GET /api/v1/admin/games[/{id}]         # type 增加 embedded 选项
POST         /api/v1/admin/games/{id}/test      # 用 provider_mock 验证配置连通性
```

### 验收标准

- [ ] `embedded` 类型游戏与 `self` 共用 `SelfProvider`，无新增 Provider 类。
- [ ] `/api/v1/game/session` 签发的会话过期后调用 `/game/bet` 被拒（503 前签名校验 401）。
- [ ] 一游戏两币种 + 两平台端（h5/unity），余额与流水均正确隔离。
- [ ] `provider_mock` on 时三种 type 全部可本地联调（third_party 走 mock，self/embedded 走真实写库但可关）。
- [ ] `ThirdPartyProvider` 熔断触发后，同厂商其他游戏的请求同样被熔断（`CircuitBreaker::call('provider:'.$name)` 按名分组，验证行为符合预期）。
- [ ] `region`/`platform` 过滤在 `/api/v1/games` 命中索引，1 万游戏规模下 < 50ms。
- [ ] 自研游戏事件经 `game.round_settled` 能同时被 M3 活动引擎与风控消费（端到端验证）。

---

## M6 — 风控 / 反作弊运营可视化

### 目标

把 H4（风控纵深）与 H5（反作弊）的产出变成**可看、可查、可追溯**的运营大盘。M6 只读不写业务数据——它是 H4/H5 的呈现层。

### 现状

- `game_risk_rule`（配置）+ `game_risk_log`（命中）已在 MySQL，`RiskService` 已按 priority 执行规则。
- ClickHouse 已有 `game_game_play_log`（**含 ip_address / user_agent**）与 `game_deposit_log`，按 `toYYYYMM` 分区、`(user_id, created_at)` 排序 —— 这是大盘的数据地基，且**不应去 MySQL 上做聚合**。
- 缺口：无聚合视图、无关联图谱、无异常用户队列、无管理端页面。
- 依赖：H4 需补齐 `risk_log` 的处置结果回写（`result` 字段已在），H5 需产出设备指纹与异常判定标记。

### 方案设计

**三层，每层只做一件事：**

```
MySQL (risk_log / risk_rule)          ← H4 写，明细留痕
      │  H2 可靠投递 / 定时同步
      v
ClickHouse (risk_hit_di)              ← 本方案唯一新增表，分区聚合
      │
      v
Admin API + 大盘页面（Flutter Web）
```

为什么不直接在 MySQL 上做大盘：`risk_log` 是按命中写入的事实表，量大后 MySQL 聚合会把主库拖垮；ClickHouse 已在项目里，`game_game_play_log` 已证明这条通路可行。**大盘只查 ClickHouse，明细钻取回 MySQL。**

关联图谱不做图数据库：用 ClickHouse 的窗口查询近似（同 IP 同设备指纹的账户聚类），MySQL 里补一张轻量关联表存已确认的团伙。图查询在团伙规模（千级）下不是瓶颈。

### 数据模型

```sql
-- ClickHouse：风控命中日报，运营大盘主数据源
CREATE TABLE IF NOT EXISTS game_risk_hit_di (
    id UInt64,
    user_id UInt64,
    rule_id UInt64,
    rule_type String,          -- ip_blacklist/amount_anomaly/frequency/velocity/device_fingerprint
    action String,             -- log/warn/block
    result String,             -- passed/blocked/manual_review
    ip_address String DEFAULT '',
    device_fp String DEFAULT '',        -- H5 产出
    amount String DEFAULT '0',
    country_code String DEFAULT '',
    created_at DateTime DEFAULT now()
) ENGINE = MergeTree()
  PARTITION BY toYYYYMMDD(created_at)
  ORDER BY (rule_type, created_at, user_id);
```

```sql
-- MySQL：已确认的关联团伙（人工判定后写入，非自动）
CREATE TABLE `game_risk_cluster` (
    `id` BIGINT NOT NULL,
    `name` VARCHAR(100) NOT NULL COMMENT '团伙名称（人工命名）',
    `type` VARCHAR(30) NOT NULL COMMENT 'same_ip/same_device/same_pay_account/manual',
    `fingerprint` VARCHAR(64) NOT NULL COMMENT '聚类依据值',
    `user_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=观察中 2=已处置 0=误判',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### API 设计

管理端（全部只读，除标注的处置类）：

```
-- 趋势大盘
GET /api/v1/admin/risk/overview         ?from=&to=&group_by=day|hour
GET /api/v1/admin/risk/hit-trend        ?rule_type=&from=&to=
GET /api/v1/admin/risk/action-distribution                        # log/warn/block 占比
GET /api/v1/admin/risk/rule-performance                           # 每规则命中率/误判率

-- 关联图谱
GET /api/v1/admin/risk/clusters         ?type=&status=
GET /api/v1/admin/risk/clusters/{id}/members
POST /api/v1/admin/risk/clusters/detect                # 触发一次聚类检测（同IP/同指纹 top N）
POST /api/v1/admin/risk/clusters/{id}                  # [写] 人工确认团伙
PUT  /api/v1/admin/risk/clusters/{id}/status           # [写] 标记误判/已处置

-- 异常用户
GET /api/v1/admin/risk/users            ?risk_score_min=&from=&to=&limit=
GET /api/v1/admin/risk/users/{id}/timeline                          # 该用户风控事件时间线
POST /api/v1/admin/risk/users/{id}/hold                             # [写] 冻结账户（调 M1 lock）
```

`/risk/users/{id}/timeline` 是排查入口：拉 MySQL `risk_log` + ClickHouse `play_log` 做单用户合并时间线，这是运营实际会用最多的接口。

前端页面（Flutter Web，4 个页签）：

1. **总览**：命中趋势折线（按 rule_type 分色）、动作分布饼图、误判率指标卡。
2. **图谱**：聚类列表 + 成员钻取 + 指纹分布。
3. **异常用户**：列表 + 时间线抽屉。
4. **规则效果**：每规则命中率/阻断率/误判率，支持一键禁用（已有 `risk_rule.status`）。

### 验收标准

- [ ] 大盘所有聚合查询**不查 MySQL 业务表**（EXPLAIN / 慢查询日志验证零命中）。
- [ ] 7 天趋势、24h 趋势在 100 万条 `risk_hit_di` 下响应 < 2s（ClickHouse 分区裁剪生效）。
- [ ] 聚类检测能找出「同 IP 下 ≥ 5 个账户」与「同设备指纹 ≥ 3 个账户」两类候选。
- [ ] 单用户时间线合并风控事件与游戏行为，时间有序、事件类型可区分。
- [ ] `hold` 操作调用 M1 `WalletService::lock` 冻结余额，且写 `risk_log` 留痕（`action=block`）。
- [ ] 规则效果页显示的误判率 = 人工标记「误判」的 cluster 数 / 该规则总命中数，口径在页面上写明。
- [ ] 无写权限的运营角色只能看不能 hold/确认（权限矩阵验证）。

---

## 实施顺序与依赖

```
M2（文档，纯产出，无依赖）      ← 先做，为 M5 的 type 扩展与 M3 的 config schema 定契约
M1（统一钱包，结构性）          ← 先做，M3/M6 的发奖与冻结都依赖它
        │
        ├─> M3（活动引擎，依赖 M1 发奖 + EventBus）
        ├─> M5（多游戏，依赖 M1 流水 + M2 规范）
        └─> M4（社交，弱依赖 M3 做裂变，可并行）
M6（风控可视化，依赖 H4 + H5 落地 + M1 冻结能力）← 最后
```

M1 是全部分支点，必须先落地。M4 与 M3/M5 可并行。

## 风险与缓解

| 风险 | 缓解 |
|---|---|
| M1 精度变更影响存量余额计算 | `DECIMAL(20,8)` 是纯放宽，存量 4 位数据读回无损；上线前跑一次 reconcile dry_run 留基线 |
| M1 改 `game_transaction` 加列影响 H3 对账模块 | 三列为加法且有默认值；提前与 H3 对齐 `scope` 枚举（platform/game + lock/unlock/reconcile） |
| M3 活动引擎消费 EventBus 单 channel，消息风暴 | 前缀过滤 + 消费端异步写库；EventBus 拓扑改造归 H2，M3 不做 |
| M3 转盘超发 | `SELECT ... FOR UPDATE` 扣 `stock` + 奖池余量为 0 时该奖池跳过（不报错） |
| M4 组队/公会合并成一张表，未来公会特性膨胀 | `type` 区分 + 公会专属列（level/xp/announcement）已就位；真到膨胀再拆 guild 表 |
| M5 `embedded` 与 `self` 共用 Provider 导致入口混淆 | 入口区分在 `ProviderController`（SDK 签名 vs 内部调用），不在 Provider 类；写入 M2 文档 |
| M6 依赖 H4/H5 未完成 | M6 的 API 与页面可先按契约开发，用 mock 数据联调；数据源表 `risk_hit_di` 先建后填 |
| 全平台缺 CI 覆盖率 | 每个 M 的验收标准都是可断言的，建议先补测试骨架再接新功能 |

## 不做的事（明确边界）

- **不合并两张钱包表**：物理分离正确，统一在接口层。
- **不引入 SPI/反射式 Provider 发现**：16 个网关的 `match` 分支成本可忽略，反射发现增加启动期不确定性。
- **不建新流水表**：`game_transaction` 加三列即可覆盖平台币与游戏币。
- **不新建 `activity_config` 表**：配置用 JSON 列，4 种活动类型不值得拆表。
- **不建图数据库做关联图谱**：千级团伙规模下 ClickHouse 窗口查询够用。
- **不改造 EventBus 拓扑**：多 channel / DLQ / ack 是 H2 的范围，M3 只做消费者侧过滤。
- **不做 guild 转让 owner / 公会商店**：标注为后续，本期不做。
