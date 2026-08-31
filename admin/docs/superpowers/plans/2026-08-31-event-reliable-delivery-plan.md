# H2 事件可靠投递方案设计

日期：2026-08-31
范围：`service/app/event/EventBus.php`、`service/app/process/EventConsumer.php` 及其上下游
结论先行：**推荐方案 C（混合）—— 关键事件走 Outbox 表 + 轮询消费，非关键事件保留现有 Pub/Sub。** 不引入 Redis Stream。

---

## 1. 目标与范围

**目标**：支付、提现、风控类关键事件不因消费进程重启/宕机而丢失。

**范围内**：
- EventBus 生产端改造（新增可靠投递入口，保留原方法）
- EventConsumer 消费端改造（进度记录、断点续传、幂等）
- 新增 Outbox 表 + 迁移 SQL
- 死信处理

**范围外**（另有工单）：
- Webhook 签名字段（现无签名，属安全工单）
- 事件类型契约治理（见 §2 缺陷 4）

---

## 2. 现状分析：Pub/Sub 模式可靠性缺陷清单

代码事实（非推测）：

| # | 缺陷 | 位置 | 影响 |
|---|------|------|------|
| 1 | **Pub/Sub 不持久化**。消费进程重启窗口内的事件全部丢失 | `EventBus.php:32` `Redis::publish()` | 支付/提现事件丢失 = 对账不平、用户投诉 |
| 2 | **`emit()` 在 `Db::commit()` 之后调用**。业务已落库，进程在 commit 与 publish 之间崩溃，事件静默丢失 | `WithdrawController.php:229/234`、`ExchangeController.php:297/300` | **比缺陷 1 更根本**：换成 Redis Stream 的 XADD 同样是"事务外写"，同样丢 |
| 3 | **`emit()` 吞掉所有异常**。`catch (\Throwable) { Log::warning }`，不阻断主流程 | `EventBus.php:34-39` | 失败无重试、无告警、无补偿；监控只能看到日志 |
| 4 | **消费端订阅的事件无人发布**。`WebhookController.php:34` 允许订阅 `deposit.completed`、`risk.alert`、`user.registered`、`user.vip_upgraded`；`AchievementService.php:20` 文档引用 `deposit.completed`、`user.login`；全仓 grep `EventBus::emit` **仅 5 处**，这些事件零生产者 | `WebhookController.php:34` vs 实际 emit 清单 | 用户订阅了 `deposit.completed` 永远收不到 → 对外契约已破 |
| 5 | **消费端头阻塞**。`WebhookController::deliver()` 每发一次 webhook 最多阻塞 5s（`timeout => 5`），单个慢端点会卡住整个事件循环 | `WebhookController.php:95` + `EventConsumer.php:27` | 慢 webhook 导致后续所有事件延迟，表现为"丢事件" |
| 6 | **消费端无重试、无死信**。`dispatch()` 的三处 `catch (\Throwable)` 全部只 `Log::warning` 后返回 | `EventConsumer.php:53/61/67` | 瞬时故障（DB 抖动、外网超时）即永久丢失 |
| 7 | **无消费进度记录**。Pub/Sub 消费完即丢弃，无法重放 | 全局 | 出问题时无法回放补账 |
| 8 | 次要：`metrics:event_emit_total` / `metrics:event_consume_total` 无 TTL，`Redis::incr` 单调增长 | `EventBus.php:110-116` | 内存缓慢泄漏 |

**现有 emit 清单（全量，共 5 处）**：

| 事件 | 位置 | 是否在事务内 |
|------|------|------------|
| `withdraw.completed` | `PayoutService.php:188` | 否（`save()` 后） |
| `exchange.completed` | `ExchangeController.php:300` | 否（`Db::commit()` 后） |
| `withdraw.applied` | `WithdrawController.php:234` | 否（`Db::commit()` 后） |
| `referral.applied` | `ReferralController.php:220` | — |
| `game.played` | `GameController.php:198` | — |

**注意**：最关键的充值到账路径 `PaymentController.php:126-181`（`Db::beginTransaction()` → 订单 confirmed → `UserWallet::addBalance()` → 写流水 → `Db::commit()`）**完全没有 emit**。充值到账事件当前不存在。

---

## 3. 事件分类

| 事件 | 可靠性等级 | 理由 |
|------|-----------|------|
| `deposit.completed`（充值到账） | **关键** | 资金入账，丢失 = 对账不平 |
| `withdraw.applied` / `withdraw.completed` | **关键** | 资金流出，丢失 = 打款记录与用户认知不一致 |
| `exchange.completed` | **关键** | 平台币↔游戏币兑换，涉及资产变动 |
| `risk.alert` | **关键** | 需持久留痕供审计 |
| `user.vip_upgraded` / `user.registered` | 中（本次不做） | 有下游表可重算，丢失可修复 |
| `referral.applied` | 非关键 | 推荐奖励可重算，丢失影响小 |
| `game.played` | 非关键 | 最高频事件（每次游戏会话），且在 `GameController` 热路径上；成就进度本就是查表重算 |

**分类原则**：只要"资产变动"或"外部可见"就走可靠通道；能由下游表重算的走 fire-and-forget。

---

## 4. 技术方案对比

| 维度 | A. Redis Stream | B. Outbox 纯模式 | C. 混合（推荐） |
|------|----------------|-----------------|----------------|
| 新增基础设施 | 无（`webman/redis` 即 `illuminate/redis`，原生支持 `xAdd/xReadGroup/xAck/xClaim`） | 无 | 无 |
| **事务原子性** | **❌ 不解决**。`XADD` 仍需在 MySQL 事务外执行，缺陷 2 原样保留 | ✅ 事件行与业务行同一事务提交 | ✅ 关键事件同 B |
| Redis 宕机影响 | 高：AOF `everysec` 下仍可能丢 1s；RDB 模式丢更多 | 无：关键路径不经 Redis | 低：仅非关键事件受影响 |
| 写入成本 | 低（Redis 内存写） | 关键事件 +1 DB 写 | 仅关键事件 +1 DB 写 |
| 复杂度 | 中（Stream + consumer group + XACK/XCLAIM） | 中（轮询 + 状态机） | 中（两路径共用 dispatch） |
| 重放能力 | ✅（Stream 天然保留） | ✅（按 `occurred_at` 重扫） | ✅ |

**推荐理由（C，且刻意不上 Stream）**：

1. **缺陷 2 决定了不能选 A**。现有 4 个关键 emit 全部在 `Db::commit()` 之后。Stream 的 `XADD` 一样是事务外写，崩溃窗口原样存在。只有把事件行写进同一事务（Outbox）才能闭合这个洞。这是本次改造的核心收益，比"换 Pub/Sub 为 Stream"重要得多。
2. **不上 Stream 省掉一个故障域**。Outbox + 轮询后，关键路径只依赖 MySQL——而资金数据本来就在 MySQL，不新增依赖。Redis 挂掉不影响关键事件。
3. **不上 Stream 省掉一套 consumer group 运维**（XACK/XCLAIM 卡住消息回收）。
4. **保留 Pub/Sub 给 `game.played`**：它在最高频请求路径上，为它引入 DB 写会把延迟加到最热路径。现有 5 个 emit 里 4 个是低频资金类，混合的边界几乎不用维护。
5. **纯 B（全部事件走 Outbox）不选**：唯一区别就是 `game.played` 也要写 DB。等实测证明 Outbox 轮询能扛住 `game.played` 量级，再考虑全量收敛（见 §8 风险 5）。

---

## 5. 详细设计

### 5.1 EventBus 改造

**原则：`emit()` 一行不改**（保护 `game.played` 热路径），新增可靠入口。

```php
// app/event/EventBus.php 新增

const RELIABLE_EVENTS = [
    'deposit.completed', 'withdraw.applied', 'withdraw.completed',
    'exchange.completed', 'risk.alert',
];

/**
 * 可靠投递：写入 Outbox 表。
 * - 调用方已在事务内 → 加入该事务（业务行与事件行同提交）
 * - 调用方不在事务内 → 自动包裹事务
 * 必须把 push() 移到 Db::commit() 之前调用。
 */
public static function push(string $event, string $eventId, array $payload = []): void
{
    if (Db::transactionLevel() > 0) {
        self::insertOutbox($event, $eventId, $payload);
    } else {
        Db::transaction(static fn () => self::insertOutbox($event, $eventId, $payload));
    }
}

private static function insertOutbox(string $event, string $eventId, array $payload): void
{
    $row = new EventOutbox();
    $row->id = SnowflakeService::generate();
    $row->event_id = $eventId;        // 幂等键，UNIQUE
    $row->event = $event;
    $row->payload = $payload;         // JSON 列
    $row->occurred_at = date('Y-m-d H:i:s');
    $row->status = EventOutbox::STATUS_PENDING;
    $row->save();
}
```

**`eventId` 生成规则**（幂等的唯一来源，必须稳定可复现）：
- `deposit.completed` → `"deposit_" . $order->id`
- `withdraw.applied` / `withdraw.completed` → `"withdraw_" . $order->id . "_" . $order->status`
- `exchange.completed` → `"exchange_" . $record->id`

**调用方改动示例**（`PaymentController.php`，补上当前缺失的充值事件）：

```php
// 原代码：Db::beginTransaction(); ... Db::commit(); 后无 emit
// 改后：commit() 之前插入
    EventBus::push('deposit.completed', 'deposit_' . $order->id, [
        'user_id' => $order->user_id,
        'order_id' => $order->id,
        'platform_amount' => $order->platform_amount,
        'transaction_id' => $transactionId,
    ]);

Db::commit();   // 业务行 + 事件行一起提交
```

### 5.2 消费端改造

`EventConsumer` 职责改为 Outbox 轮询消费；`dispatch()` 抽成静态方法，与 Pub/Sub 路径共用。

```php
// app/process/EventConsumer.php 改造

public function onWorkerStart(): void
{
    Log::info('EventConsumer started (outbox polling)');
    while (true) {
        try {
            self::drainBatch();
        } catch (\Throwable $e) {
            Log::error('EventConsumer drain failed: ' . $e->getMessage());
            usleep(2_000_000);
        }
        usleep(500_000);   // 0.5s 轮询间隔
    }
}

private static function drainBatch(): void
{
    // 按 occurred_at 取一批待消费事件，行锁保证单批独占
    $rows = EventOutbox::where('status', EventOutbox::STATUS_PENDING)
        ->where('attempts', '<', self::MAX_ATTEMPTS)   // 3
        ->orderBy('occurred_at')                       // 断点续传 + 批内顺序
        ->limit(50)
        ->lockForUpdate()
        ->get();

    foreach ($rows as $row) {
        $row->attempts = $row->attempts + 1;
        self::dispatch($row->event, $row->payload, $row->event_id);

        $row->status = EventOutbox::STATUS_CONSUMED;
        $row->consumed_at = date('Y-m-d H:i:s');
        $row->save();
    }
}

// 公开静态：Pub/Sub 路径与 Outbox 路径共用，避免 dispatch 逻辑两份
public static function dispatch(string $event, array $payload, ?string $eventId = null): void
{
    $ctx = ['event' => $event, 'event_id' => $eventId, 'timestamp' => time()];
    try { AchievementService::handle($event, $payload); }
    catch (\Throwable $e) { Log::warning('achievement failed: ' . $e->getMessage(), $ctx); }

    try { WebhookController::dispatch($event, $payload, $eventId); }
    catch (\Throwable $e) { Log::error('webhook failed: ' . $e->getMessage(), $ctx); }
}
```

**消费重试与死信**（状态机）：

```
0 pending → 消费成功 → 2 consumed
0 pending → 消费异常 → attempts+1
  attempts < 3 → 保持 pending，下个批次重试
  attempts >= 3 → 3 failed（死信），不再消费
```

`dispatch()` 内部已分 try/catch 隔离两个下游，因此 `status=failed` 只会在 `drainBatch()` 外层抛出时产生。为保证"下游失败 → 计入重试"，`dispatch()` 对**关键事件**的下游异常需向上抛（见 §8 风险 1）。

### 5.3 表结构

```sql
-- install/migrations/2026_08_31_event_outbox.sql
-- 用法: mysql -uUSER -p game_platform < install/migrations/2026_08_31_event_outbox.sql
-- 同时合并进 install/install.sql

CREATE TABLE IF NOT EXISTS `game_event_outbox` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `event_id` VARCHAR(96) NOT NULL COMMENT '业务幂等键，如 deposit_123，唯一',
    `event` VARCHAR(64) NOT NULL COMMENT '事件类型',
    `payload` JSON NOT NULL COMMENT '事件负载',
    `occurred_at` DATETIME NOT NULL COMMENT '业务发生时间（用于顺序与重放）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=pending 1=dispatched 2=consumed 3=failed',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '消费尝试次数',
    `last_error` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '最近一次错误信息',
    `consumed_at` DATETIME DEFAULT NULL COMMENT '消费完成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_event_id` (`event_id`),
    KEY `idx_status_occurred` (`status`, `occurred_at`),
    KEY `idx_event` (`event`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='可靠事件投递表（Outbox）';
```

**设计要点**：
- `uk_event_id` **同时**解决两个问题：生产端重复写入（`INSERT` 冲突即丢弃，幂等）、消费端重复消费（消费前查状态）。一个索引两用。
- `idx_status_occurred` 直接对应轮询查询，避免全表扫。
- **不建独立死信表**。`status=3` 就是死信队列，一张表足够，少一个需要运维的对象。
- `dispatched` 状态本版不用（单进程轮询消费，无需派发/消费两段）。字段保留，为将来拆成"派发进程 + 消费进程"留位。

### 5.4 死信处理

- `status=3` 的记录永不再消费，人工介入。
- **v1 不做管理后台界面**，提供两条现成路径：
  ```sql
  -- 查看死信
  SELECT event, event_id, payload, last_error, attempts, occurred_at
  FROM game_event_outbox WHERE status = 3 ORDER BY occurred_at DESC LIMIT 50;

  -- 人工修复后重放
  UPDATE game_event_outbox
  SET status = 0, attempts = 0, last_error = ''
  WHERE status = 3 AND event_id = '<id>';
  ```
- `Monitor.php` 增加一条巡检：`status=3` 数量 > 0 或 `status=0 且 occurred_at < NOW() - INTERVAL 5 MINUTE` 时告警。
- 保留 `consumed` 行 7 天后可清理（重放窗口之外的消费记录无价值），清理由定时任务执行。

---

## 6. 迁移步骤

**核心策略：双写不改语义，逐事件灰度，任一步可回滚。**

| 步骤 | 动作 | 回滚方式 |
|------|------|---------|
| 1 | 执行 `2026_08_31_event_outbox.sql` 建表 + 合并进 `install.sql` | 空表，`DROP TABLE` |
| 2 | `EventBus` 新增 `push()` / `insertOutbox()`，`EventOutbox` model。`emit()` 不动 | 删新增代码 |
| 3 | `EventConsumer` 改造为 Outbox 轮询，`dispatch()` 静态化。原 `EventBus::subscribe()` 保留不删 | 恢复 `subscribe()` 循环 |
| 4 | `process.php` 加第二个进程跑原 Pub/Sub 路径（承接 `game.played`/`referral.applied`） | 改回 `count: 1` |
| 5 | 逐事件迁移，**每次一个**：先 `PaymentController` 补 `deposit.completed`，再 `PayoutService` → `WithdrawController` → `ExchangeController`。每步把 emit 移进事务并改为 `push()` | 单个文件 revert |
| 6 | 灰度观察 48h：比对 `game_event_outbox` 行数与业务订单数 | — |
| 7 | 补 `risk.alert` 生产者（`RiskService::check()` 命中时 `push()`） | revert |
| 8 | 稳定后再议：是否收敛非关键事件、是否上 Stream | — |

**迁移期关键约束**：`event-consumer` 进程在步骤 3 后只消费 Outbox，Pub/Sub 由新进程消费。两条路径都调同一个静态 `dispatch()`，不存在"两套 dispatch"的漂移风险。

---

## 7. 验收标准

1. **主验收（进程重启丢事件）**：停掉 `event-consumer` 进程 → 发起 N 笔真实充值回调（触发 `deposit.completed`）→ 等待 N 笔订单 `status=confirmed` → 启动进程。断言：`game_event_outbox` 中 N 条 `deposit.*` 记录全部 `status=2`，且对应成就进度已更新。**全部 N 条被消费，0 丢失。**
2. **提交与发布原子性**：在 `PaymentController` 的 `Db::commit()` 处注入异常（事务回滚）。断言：订单未 confirmed，`game_event_outbox` 中**无**对应 `deposit.*` 记录（不会有关联丢失后的"幽灵事件"）。
3. **重复消费幂等**：手工对同一订单再触发一次回调。断言：`uk_event_id` 拒绝重复插入，`game_event_outbox` 中该 `event_id` 仅 1 条，成就/钱包无二次变更。
4. **死信**：令 webhook 端点连续返回 500。断言：该事件 `attempts` 递增到 3 后 `status=3`，且不再被重试；其余事件不受影响。
5. **头阻塞消除**：注册一个 sleep 8s 的 webhook 端点。断言：后续事件消费延迟不因该端点而无限累积（对比改造前行为）。
6. **契约修复**：注册 `deposit.completed` webhook，完成一笔充值。断言：端点收到请求（改造前永远收不到）。

---

## 8. 风险与缓解

| # | 风险 | 缓解 |
|---|------|------|
| 1 | **重复消费**：`drainBatch()` 取到行、`dispatch()` 成功、`save()` 之前进程崩溃 → 下次批次重复消费 | ① 消费端以 `status=2` 为完成标记，重复触发时 `AchievementService::evaluate()` 对已 `completed` 的成就直接返回；② `WebhookController::dispatch()` 收到的 payload 携带 `event_id`，**下游必须按 `event_id` 去重**——webhook 侧目前无幂等，这是本次必须补的点；③ 若某下游不幂等，优先把它改成幂等而非依赖消息系统只投递一次 |
| 2 | **乱序**：`orderBy('occurred_at')` + `limit(50)` 仅保证批内顺序 | 单事件内部无跨事件顺序依赖（充值、提现互不依赖）；若将来出现依赖（如"提现必须在充值之后"），按 `user_id` 分区或引入版本号 |
| 3 | **Redis 宕机** | 关键路径不经 Redis（Outbox 直接读 MySQL），Redis 挂掉仅影响 `game.played`/`referral.applied` 等非关键事件——**这是选 C 而非 A 的直接收益** |
| 4 | **MySQL 成为单点** | 资金数据本就在 MySQL，不新增依赖；且 Outbox 表与业务行同库同事务，故障语义一致 |
| 5 | **表膨胀** | `idx_created_at` + 定时清理 7 天前 `status=2` 记录。若清理跟不上，可先按 `occurred_at` 归档到 `_archive` 表 |
| 6 | **轮询延迟**（0.5s 批次 + `limit 50`） | 当前下游都是"查表重算"（成就进度）或"尽力投递"（webhook 5s 超时），对亚秒延迟不敏感。**若将来需要实时推送（如排行榜增量、聊天），再上 Redis Stream 做加速通道**，Outbox 表仍是权威源，Stream 只是通知器 |
| 7 | **单消费者吞吐瓶颈** | `FOR UPDATE` 行锁 + 进程 `count: 1`。扩容时把 `count` 调大即可并行抢锁（注意：`lockForUpdate()` 在 MySQL 8 / Illuminate 较新版本可加 `skipLocked()` 进一步消除锁等待，需先确认 MySQL 版本 ≥ 5.7.8） |
| 8 | **`dispatch()` 吞异常导致死信判定失真** | 现状 `dispatch()` 内层已 try/catch，异常不会冒泡到 `drainBatch()`，`status=failed` 永不会触发。改造时**关键事件的下游异常必须向上抛**，否则第 4 条验收标准不成立 |
| 9 | **指标键无 TTL** | `metrics:event_*_total` 加 `EXPIRE` 或改用带日期的 key，非本次主线，顺手修 |

---

## 附：本次改造顺带暴露的独立缺陷（建议单独开单）

1. **`PaymentController` 完全不发 `deposit.completed`** —— 本次补上，属最高优先级。
2. **`risk.alert` 零生产者** —— `RiskService::check()` 命中后只 `Log::warning`（`PaymentController.php:187` 甚至注释"MVP: log warning but do NOT reverse the credit"）。需补生产者 + 人工审核队列。
3. **Webhook 无签名** —— `WebhookController::deliver()` 直接 `POST json`，接收方无法验证来源，且无重试与签名。属安全工单。
4. **webhook 消费无幂等** —— 见风险 1，必须补 `event_id` 去重。

---

## 9. 跨任务约束：与 H1 共享层抽取的边界

`PayoutService` 属 H1 批次 2（admin/service 已漂移），迁移到 `common\service` 会影响 `withdraw.completed` 的生产者。三个已确认事实：

1. **admin 与 service 共用同一 MySQL**（两边 `config/database.php` 均为 `getenv('DB_DATABASE') ?: 'game-platform'`）。Outbox 表对两侧都可达，admin 技术上完全有能力可靠投递。
2. **admin 端 `PayoutService::markCompleted()` 是活路径**（被 `admin/app/service/PayoutService.php:29/79/118` 调用），非死代码。它当前不 emit 任何事件。
3. admin 端无 `EventBus`（`grep EventBus admin/app` 零命中）。

### 约束：不接受 `class_exists('app\event\EventBus')` 守卫

`class_exists()` 守卫等于把运行环境探测写进业务代码，且**静默吞掉关键事件**——正是本方案要修掉的失败模式（§2 缺陷 4：消费者订阅的事件无人发布）。今天它对 `withdraw.completed` 恰好无害（见下），但同一守卫日后被套用到真正关键的事件上就会复发缺陷 4。

### 替代：共享层用已注册发布器，默认 no-op

```php
// common/service/EventPublisher.php —— 共享层唯一事件出口
class EventPublisher
{
    private static $publisher = null;

    public static function push(string $event, string $eventId, array $payload = []): void
    {
        if (self::$publisher === null) return;   // admin 未注册 → no-op
        (self::$publisher)($event, $eventId, $payload);
    }

    public static function setPublisher(callable $publisher): void
    {
        self::$publisher = $publisher;
    }
}
```

- **接缝签名必须带 `$eventId`（三参）**。理由见下"两种错误接缝"。
- service 端注册 `[EventBus::class, 'push']`（§5.1 新增的三参方法，非现有 `emit()`）。
- admin 端不注册 → no-op。
- 共享代码只调 `EventPublisher::push()`，**禁止直接调 `EventBus::push()`**，接缝只有一处。

### 两种错误的接缝签名（都不可接受）

H1 最初提案曾把接缝对齐成现有 `emit(string $event, array $payload)`（两参）。两种后果：

| 接缝签名 | 注册目标 | 后果 |
|---------|---------|------|
| 两参 | `EventBus::push`（三参，`$eventId` 无默认值） | **Fatal**：`ArgumentCountError: Too few arguments to function push(), 1 passed ... and at least 2 expected`。已实测复现。 |
| 两参 | `EventBus::emit`（两参，Pub/Sub） | **更糟，不报错**：共享层的"可靠投递"调用静默落到 Pub/Sub，Outbox 完全被绕过 → 缺陷 1、2 原样保留。这正是 H2 要修的失败模式，只是换了个入口。 |

**注册动作归属 H2 步骤 2，不归属 H1 批次 0。** 原因：H2 步骤 2 才创建 `EventBus::push()`。若 H1 先注册 `[EventBus::class, 'push']`，在 H2 步骤 2 落地之前该调用直接 fatal；若临时改注册 `emit()` 过渡，则进入上表第二行的静默降级窗口（提现事件退回 Pub/Sub）。

正确归属：
- **H1 批次 0 只加 `EventPublisher` 类本体**（默认 no-op，不含任何注册调用）。
- **H2 步骤 2 同时**：新增 `EventBus::push()` + 在 service 端注册 `[EventBus::class, 'push']`。
- 两者在同一提交里，不存在中间状态。H1 先落地时 service 侧只是 no-op 一段（等同现状，无回退），H2 步骤 2 落地后立即生效。

### 为什么 no-op 在 `withdraw.completed` 上可接受

两个下游都不要求它：`AchievementService` 进度从 DB 表重算（admin 端 `markCompleted()` 仍写共享库订单状态，成就进度照旧正确）；`WebhookController` 本就是尽力投递。若 admin 侧日后需要通知外部 webhook，只需注册发布器，无需改共享代码。

### 不要让 no-op 静默

Outbox 表让"事件丢失"可检测，应利用它而不是隐藏。`Monitor.php` 增加对账巡检：

```sql
-- 任一完成提现若共享库无对应事件记录，说明该侧没发事件
SELECT wo.id, wo.status, wo.updated_at
FROM game_withdraw_order wo
LEFT JOIN game_event_outbox eo ON eo.event_id = CONCAT('withdraw_', wo.id, '_completed')
WHERE wo.status = 'completed' AND eo.id IS NULL;
```

无结果 = 全部事件都有生产者；有结果 = 某一侧静默跳过。no-op 从"看不见"变成"看得到"。

### 与 H2 迁移步骤的顺序关系

两批次先后均可，接缝相同：
- **H1 先落地**：H2 步骤 5 改为修改共享文件内的 `EventPublisher::push()` 调用；`withdraw.completed` 仅 service 侧可靠（admin no-op，已证明可接受）。
- **H2 先落地**：H1 迁移的是一个已用 `push()` 的文件，仍需要上述接缝。

