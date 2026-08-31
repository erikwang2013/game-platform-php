# H3 对账 / 结算模块方案

> 作者：规划 agent（H3）
> 日期：2026-08-31
> 状态：设计评审稿
> 依赖：现有 `game_deposit_order` / `game_withdraw_order` / `game_transaction` / `game_payment_method`

---

## 1. 目标与范围

### 目标

把"我们记了多少钱"和"网关结算了多少钱"放在一张表里比一比，让差异能定位到**具体一笔**，而不是月底才发现"对不上"。

- 自动拉取（或人工上传）各支付网关对账单
- 与本地订单逐笔匹配
- 差异分类、定位、可处理、可导出
- 提现侧对账：核对"我们标记打款成功"与"渠道实际到账"

### 范围内

| 项 | 包含 |
|----|------|
| 充值对账 | 网关已结算明细 ↔ `game_deposit_order` |
| 提现对账 | 本地 `payout_status=success` ↔ 渠道打款明细 |
| 差异处理 | 标记已处理 / 忽略 / 补单（补登交易流水） |
| 定时任务 | 日终自动跑 |
| 管理端 | 触发、查询、导出 |

### 范围外（明确不做）

- **资金分账 / 分润计算**：已有 `game_platform_revenue`，分账逻辑不在本模块
- **退款对账**：当前 `DepositOrder` 无退款状态机，等退款功能落地后再加
- **多币种汇率重估**：只做"同币种金额比较"，不换算
- **实时对账**：只做 T+1 日终批处理。实时对账需要常驻轮询，成本与价值不匹配

---

## 2. 现状分析

### 2.1 已有能力（读码确认）

| 资产 | 位置 | 现状 |
|------|------|------|
| 网关接口 | `service/app/payment/PaymentGatewayInterface.php` | 只有 `createPayment()` + `verifyCallback()`，**无任何对账单/查询能力** |
| 网关工厂 | `service/app/payment/GatewayFactory.php` | `match` 硬编码 16 个 provider |
| 充值订单 | `game_deposit_order` | 有 `transaction_id`、`amount`、`status`、`paid_at`，可对账 |
| 提现订单 | `game_withdraw_order` | 有 `payout_batch_id` / `payout_item_id` / `payout_status`，可对账 |
| 平台流水 | `game_transaction` | `ref_type`/`ref_id` 可回溯单据，重复入账检测依据 |
| 报表能力 | `admin/.../ReportController.php` | 只汇总本地订单，不接触网关数据 |
| 定时进程 | `service/app/process/` | `EventConsumer` / `Monitor` / 两个 WebSocket，`Timer::add` 模式成熟 |

### 2.2 关键缺口

1. **接口层没有"拉对账单"的能力**——`PaymentGatewayInterface` 只覆盖支付创建与回调验签。
2. **订单表没有 `provider` 字段**——`game_deposit_order` 只有 `payment_method_id`，要对账必须 JOIN `game_payment_method` 取 `provider`。这是本模块唯一的 schema 前置需求（见 §4.4，不改表也能跑，只是每次对账多一次 JOIN）。
3. **`transaction_id` 的语义不统一**——法币网关是渠道流水号，`NOWPayments` / `CoinbaseCommerce` 是链上 `txHash`。两种匹配策略必须分开。
4. **无对账痕迹**——`status=confirmed` 就是终态，没有"被核对过"的证据。

### 2.3 网关数量校正

任务背景写"18 个支付网关"。实测 `service/app/payment/` 下 18 个文件 = **16 个网关实现** + `PaymentGatewayInterface` + `GatewayFactory`。后续文档以 **16 个网关** 为准。

---

## 3. 对账架构设计

### 3.1 分层与职责

```
admin/ReconciliationController      触发 + 查询 + 导出（薄层，复用 BaseController）
        │
service/app/payment/
  StatementSourceInterface          拉对账单（可选接口，只有支持自动拉取的网关实现）
  StatementSourceResolver           provider → StatementSource（拿不到返回 null → 走人工 CSV）
        │
service/app/service/
  ReconciliationService             编排：建批次 → 取明细 → 标准化 → 匹配 → 落差异
  StatementNormalizer               网关原生字段 → 统一结构（纯函数）
  StatementCsvParser                人工上传 CSV → 统一结构（按 provider 配置列映射）
  ReconciliationMatcher             统一结构 ↔ 本地订单 → 差异分类（纯函数）
        │
service/app/process/
  ReconciliationWorker              Timer::add 日终触发（webman process）
```

**设计取舍**：不新建 Service 目录之外的抽象层，不引新的依赖包。`PaymentGatewayInterface` 保持不动——它语义清晰（支付创建 + 回调验签），把"对账拉取"塞进去会让每个网关被迫实现一个它支持不了的接口。

### 3.2 统一对账单明细结构

沿用现有 `PaymentGatewayInterface` 的 `@return array{...}` 风格，不引入 DTO：

```php
/**
 * 标准化后的对账单明细（充值方向）
 *
 * @return array{
 *   statement_id: string,   网关侧明细ID
 *   transaction_id: string, 网关交易ID（法币=流水号，crypto=txHash）
 *   order_no: string,       商户订单号（网关透传我方 order_no，可为空）
 *   amount: string,         网关结算金额（网关币种，DECIMAL 字符串）
 *   currency: string,       网关币种
 *   settled_at: string,     结算时间 ISO-8601，含时区
 *   status: string,         settled / pending / refunded / failed
 *   gross_amount: string,   总额（含手续费，可为空）
 *   fee_amount: string,     手续费（可为空）
 *   raw: array              网关原始行，原样落库供追溯
 * }
 */
```

### 3.3 对账流程

```
触发（cron / 手动）
  │
  ├─ 1. 建批次  game_reconciliation_batch (status=pending)
  │
  ├─ 2. 取明细
  │      provider ∈ 自动拉取白名单 → StatementSource->fetch($from, $to)
  │      否则                    → 人工上传 CSV → StatementCsvParser
  │      统一经 StatementNormalizer 转 §3.2 结构
  │      落 game_reconciliation_statement（审计用，原始 raw 一并存）
  │
  ├─ 3. 取本地订单
  │      充值：deposit_order JOIN payment_method，按 provider + paid_at 落在批次窗口
  │      提现：withdraw_order WHERE payout_status='success'
  │
  ├─ 4. 匹配（ReconciliationMatcher）
  │      匹配键优先级：transaction_id → order_no → (amount + user 窗口，仅兜底)
  │
  ├─ 5. 差异分类（见 §3.4），差异落 game_reconciliation_diff
  │      匹配成功的明细只更新 batch 汇总计数，不落表（见 §4.2 说明）
  │
  └─ 6. batch.status = done，写汇总指标
```

### 3.4 差异类型

| 编码 | 差异类型 | 判定条件 | 严重度 | 建议动作 |
|------|----------|----------|--------|----------|
| `AMOUNT_MISMATCH` | 金额不一致 | 同一 `transaction_id`，`abs(gateway.amount - local.amount) > 0.01` | high | 核对费率/汇损，人工裁定 |
| `STATUS_MISMATCH` | 状态不一致 | 网关 `settled` 但本地 `status` 不在 `(paid, confirmed)` | critical | 补登 + 补发游戏币 |
| `LOCAL_ONLY` | 本地有网关无 | 本地 `confirmed`，对账单无对应明细 | critical | 资金未到账，联系网关 |
| `GATEWAY_ONLY` | 网关有本地无 | 对账单有明细，本地无匹配订单 | critical | 补单（§6.4 补单） |
| `DUPLICATE_CREDIT` | 重复入账 | 本地订单 1 笔，但 `game_transaction` 中同 `ref_id` 的 deposit 流水 > 1 笔 | critical | 冲正多余流水 + 扣回余额 |
| `TIME_ONLY` | 仅时间不一致 | 金额/状态一致，但 `abs(settled_at - paid_at) > 24h` | low | 观察，通常无需处理 |
| `PAYOUT_UNCONFIRMED` | 提现打款未确认 | 本地 `payout_status=success` 但渠道对账单无记录 | high | 联系渠道核实 |
| `CURRENCY_MISMATCH` | 币种不一致 | 网关币种 ≠ 本地 `currency` | medium | 核对支付方式配置 |

**重复入账检测依据**：`game_transaction` 的 `ref_type` / `ref_id` 索引。

```sql
SELECT ref_id, COUNT(*) c
FROM game_transaction
WHERE ref_type = 'deposit_order' AND created_at BETWEEN ? AND ?
GROUP BY ref_id HAVING COUNT(*) > 1;
```

---

## 4. 数据模型变更

### 4.1 新增表

#### 4.1.1 `game_reconciliation_batch` — 对账批次表

```sql
CREATE TABLE IF NOT EXISTS `game_reconciliation_batch` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `batch_no` VARCHAR(40) NOT NULL COMMENT '批次号 RC{provider}{YYYYMMDD}{seq}',
    `direction` VARCHAR(10) NOT NULL COMMENT '方向: deposit=充值 withdraw=提现',
    `provider` VARCHAR(50) NOT NULL COMMENT '网关标识: stripe/paypal/... 或 csv=人工上传',
    `date_start` DATE NOT NULL COMMENT '对账窗口起始日（网关结算日）',
    `date_end` DATE NOT NULL COMMENT '对账窗口结束日',
    `source` VARCHAR(20) NOT NULL DEFAULT 'api' COMMENT '明细来源: api=自动拉取 csv=人工上传',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '状态: pending=进行中 done=完成 failed=失败',
    `error_msg` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '失败原因',
    `gateway_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '网关明细笔数',
    `local_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '本地订单笔数',
    `matched_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '匹配成功笔数',
    `diff_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '差异笔数',
    `gateway_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '网关结算总额',
    `local_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '本地订单总额',
    `amount_gap` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '资金差额 = gateway_amount - local_amount',
    `triggered_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '触发人ID（0=定时任务）',
    `file_path` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '人工上传的原始文件路径',
    `started_at` DATETIME DEFAULT NULL COMMENT '开始时间',
    `finished_at` DATETIME DEFAULT NULL COMMENT '结束时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_batch_no` (`batch_no`),
    KEY `idx_provider_date` (`provider`, `date_start`),
    KEY `idx_status` (`status`),
    KEY `idx_direction_date` (`direction`, `date_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='对账批次表';
```

#### 4.1.2 `game_reconciliation_statement` — 对账单明细表

存网关侧原始明细（含 `raw`），**这是对账的审计依据**——人工 CSV 上传后无法重新拉取，必须留底。

```sql
CREATE TABLE IF NOT EXISTS `game_reconciliation_statement` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `batch_id` BIGINT UNSIGNED NOT NULL COMMENT '批次ID',
    `provider` VARCHAR(50) NOT NULL COMMENT '网关标识',
    `statement_id` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '网关侧明细ID',
    `transaction_id` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '网关交易ID',
    `order_no` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '商户订单号（透传我方 order_no）',
    `amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '网关结算金额',
    `currency` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '网关币种',
    `gross_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '总额（含手续费）',
    `fee_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '手续费',
    `status` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '网关状态: settled/pending/refunded/failed',
    `settled_at` DATETIME DEFAULT NULL COMMENT '网关结算时间（已归一化到东八区）',
    `local_order_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '匹配到的本地订单ID（0=未匹配）',
    `matched` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否匹配: 0=否 1=是',
    `diff_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联差异ID（0=无差异）',
    `raw` JSON DEFAULT NULL COMMENT '网关原始行',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_batch_id` (`batch_id`),
    KEY `idx_transaction_id` (`transaction_id`(64)),
    KEY `idx_order_no` (`order_no`),
    KEY `idx_local_order_id` (`local_order_id`),
    KEY `idx_matched` (`matched`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='对账单明细表';
```

#### 4.1.3 `game_reconciliation_diff` — 差异表

```sql
CREATE TABLE IF NOT EXISTS `game_reconciliation_diff` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `batch_id` BIGINT UNSIGNED NOT NULL COMMENT '批次ID',
    `diff_type` VARCHAR(30) NOT NULL COMMENT '差异类型: 见§3.4 编码表',
    `severity` VARCHAR(10) NOT NULL DEFAULT 'medium' COMMENT '严重度: low/medium/high/critical',
    `provider` VARCHAR(50) NOT NULL COMMENT '网关标识',
    `local_order_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '本地订单ID（0=无）',
    `local_order_no` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '本地订单号',
    `statement_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '对账明细ID（0=无）',
    `gateway_transaction_id` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '网关交易ID',
    `local_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '本地金额',
    `gateway_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '网关金额',
    `amount_gap` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '金额差 = gateway_amount - local_amount',
    `local_status` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '本地状态',
    `gateway_status` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '网关状态',
    `detail` JSON DEFAULT NULL COMMENT '差异详情（如重复入账的流水ID列表）',
    `resolve_status` VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT '处理状态: open=未处理 resolving=处理中 resolved=已处理 ignored=已忽略',
    `resolve_action` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '处理动作: adjust=调整 credit=补单 ignore=忽略 refund=冲正',
    `resolve_note` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '处理附注',
    `resolved_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '处理人ID（0=系统）',
    `resolved_at` DATETIME DEFAULT NULL COMMENT '处理时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_batch_id` (`batch_id`),
    KEY `idx_resolve_status` (`resolve_status`),
    KEY `idx_diff_type` (`diff_type`),
    KEY `idx_severity` (`severity`),
    KEY `idx_local_order_id` (`local_order_id`),
    KEY `idx_provider_date` (`provider`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='对账差异表';
```

### 4.2 为什么不存"匹配成功"记录

**只落差异，不落匹配成功。** 原因：

- 对账单明细表已经存了每一笔网关原始数据（含 `local_order_id` / `matched` 标记），要回溯"某笔订单对过账没"直接查 statement 表即可，不需要第三份数据。
- 匹配成功占对账结果的绝大多数（健康系统下 >99%）。存下来是纯粹的存储浪费。

`ponytail:` 只落差异的取舍——若合规要求"每笔匹配成功的留痕"，加一张 `game_reconciliation_match` 表即可，statement 表的 `local_order_id` / `matched` 字段已为此预留，改动局限在 `ReconciliationService` 一处。

### 4.3 幂等与重跑

`game_reconciliation_batch` 无唯一约束防重（`batch_no` 的 `seq` 递增）。重跑同一窗口的代价是 statement 明细重复落库，但**差异表可重复**：重跑时先按 `(batch.provider, date_start, date_end, direction)` 查是否已有 `done` 批次，有则 `ReconciliationService` 直接返回该批次并返回 `already_done` 提示，不重建。差异的"已处理"状态因此不会因重跑丢失。

### 4.4 不改动 `game_deposit_order`

对账需要的 `provider` 通过 `payment_method_id → game_payment_method.provider` 取。每次对账多一次 JOIN，日终批处理场景下完全可接受。**不加列**，避免给 44 张表之一加字段的迁移成本。

若后续对账量上来（单网关单日 >10 万笔），再把 `provider` 冗余进 `game_deposit_order` 并加索引——升级路径明确，现在不做。

---

## 5. 管理端 API 设计

新增 `admin/app/admin/controller/ReconciliationController.php`，挂现有中间件链：

```
Cors → SecurityFilter → RateLimit → AdminAuth → AdminPermission → OperationLog → Controller
```

路由注册在 `admin/config/route.php` 的 `/admin` 分组内。

### 5.1 接口清单

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/reconciliation/batches` | 批次列表（分页，按 `provider` / `direction` / `status` / 日期过滤） |
| POST | `/admin/reconciliation/trigger` | 手动触发对账 |
| GET | `/admin/reconciliation/batches/{id}` | 批次详情（汇总指标 + 状态） |
| GET | `/admin/reconciliation/batches/{id}/summary` | 批次汇总视图（按差异类型 / 严重度聚合） |
| GET | `/admin/reconciliation/diffs` | 差异列表（分页，跨批次，支持 `resolve_status` 过滤） |
| POST | `/admin/reconciliation/diffs/{id}/resolve` | 处理差异 |
| GET | `/admin/reconciliation/diffs/export` | 差异导出 CSV（UTF-8 BOM，与 `ReportController` 一致） |
| POST | `/admin/reconciliation/statement/upload` | 人工上传 CSV 对账单 |
| GET | `/admin/reconciliation/providers` | 网关对账单能力清单（哪些能自动拉取、哪些需人工） |

### 5.2 关键接口契约

**触发对账**

```http
POST /admin/reconciliation/trigger
Body: {
  "provider": "stripe",          // 必填，或 "all"
  "direction": "deposit",        // deposit | withdraw
  "date_start": "2026-08-30",    // 网关结算日窗口
  "date_end": "2026-08-30"
}
```

响应：`{ "batch_id": 123, "status": "done" | "already_done" | "queued" }`

**日期校验**：复用 `ReportController::normalizeDateRange` 的 90 天上限与 `date('Y-m-d', $s) !== $start` 非法日期拦截。这个私有方法应该提到公共工具类，两个控制器共用——**本次不重构**，先在 `ReconciliationController` 内复制同一逻辑，等第三个控制器需要时再抽。

**处理差异**

```http
POST /admin/reconciliation/diffs/{id}/resolve
Body: {
  "action": "adjust | credit | ignore | refund",
  "note": "与 Stripe 客服确认，汇率舍入导致 0.02 差异"
}
```

- `adjust`：登记差额为可接受（记入 `PlatformRevenue` 的 `deposit_fee` 类，或纯标注）
- `credit`：**补单**——本地无订单但网关已结算，创建 `DepositOrder(status=confirmed)` + 补写 `game_transaction` + 加游戏币余额。**必须包在事务里**
- `ignore`：标记忽略，不进差异报表
- `refund`：冲正——`DUPLICATE_CREDIT` 用，扣回多余余额 + 冲正流水

`credit` / `refund` 涉及资金变动，`OperationLog` 中间件会自动记录，满足审计。

### 5.3 权限

复用现有 RBAC。新增权限码：

| 权限码 | 说明 |
|--------|------|
| `reconciliation.view` | 查看批次与差异 |
| `reconciliation.trigger` | 触发对账 |
| `reconciliation.resolve` | 处理差异（含补单/冲正，高危） |

`resolve` 权限单独设，与 `view` 分离——看差异和动钱是两件事。

---

## 6. 定时任务设计

### 6.1 实现方式

`service/app/process/ReconciliationWorker.php`，注册到 `service/config/process.php`：

```php
'reconciliation-worker' => [
    'handler' => app\process\ReconciliationWorker::class,
    'count' => 1,   // 单进程，避免并发重复跑
    'reloadable' => false,
],
```

**为什么不用系统 cron**：进程内 `Timer::add` 与现有 `EventConsumer` / `Monitor` 模式一致，无需额外部署步骤，日志进 webman 日志。单进程 `count=1` 天然防并发。

### 6.2 调度逻辑

```php
class ReconciliationWorker
{
    public function onWorkerStart(Worker $worker): void
    {
        // 每小时检查一次是否到了日终对账时间（默认 02:30 东八区）
        Timer::add(3600, function () {
            if ($this->isDue()) {
                $this->runDaily();
            }
        });
    }

    private function isDue(): bool
    {
        $tz = new \DateTimeZone('Asia/Shanghai');
        $now = (new \DateTime('now', $tz))->format('H:i');
        $schedule = config('reconciliation.schedule', '02:30');
        return $now === $schedule;
    }
}
```

**为什么不是 `cron: "0 30 2 * * *"` 精确匹配**：进程启动时机不一定恰好落在 02:30 整，小时粒度检查 + 幂等跳过（§4.3）比精确匹配更稳。

### 6.3 配置项

新增 `service/config/reconciliation.php`：

```php
<?php
return [
    // 日终对账触发时间（东八区，H:i 格式）
    'schedule' => '02:30',

    // 对账窗口回看天数：默认补昨日，网关延迟大的可设 2
    'lookback_days' => 1,

    // 金额容差（低于此值的差额不记差异，避免汇率舍入噪音）
    'amount_tolerance' => '0.01',

    // 支持自动拉取对账单的网关；其余走人工 CSV 上传
    'auto_providers' => ['stripe', 'paystack', 'mercadopago', 'paypal'],

    // 手动触发单次对账的最大窗口（天）
    'max_range_days' => 90,

    // 单批次 statement 明细行数上限（防异常大文件打爆内存）
    'max_statement_rows' => 200000,
];
```

### 6.4 日终流程

1. 遍历 `PaymentMethod::where('status', 1)->distinct('provider')` 拿到启用中的网关
2. `provider ∈ auto_providers` → 自动拉取；否则**跳过**（人工上传，不阻塞）
3. 窗口 = `[今天 - lookback_days - 1, 今天 - lookback_days]`（默认昨日整天）
4. 逐网关建批次、跑匹配、落差异
5. 全部失败不影响其它网关——单网关异常只把该批次标 `failed`，继续下一个
6. 结束时若存在 `critical` 差异，写 `game_notification`（type=payment）提醒运营

---

## 7. 各网关对账单获取方案

### 7.1 能力矩阵

> **重要说明**：下表"获取方式"基于通用知识整理，**全部端点在实施前必须逐一核实网关官方文档**。搜索验证在本次规划阶段不可用（搜索引擎降级），因此标注了置信度，请以实际文档为准。端点写错的成本是"实现了个拿不到数据的对账模块"，核实这一步不能省。

| # | 网关 (provider) | 获取方式 | 置信度 | 建议 |
|---|-----------------|----------|--------|------|
| 1 | `stripe` | `GET /v1/balance_transactions?start=...&end=...`，支持 `payout` 过滤；Dashboard 可导出 CSV | 高 | **自动拉取（首批）** |
| 2 | `paystack` | `GET /v1/transactions?from=...&to=...`，官方支持 CSV 导出 | 高 | **自动拉取（首批）** |
| 3 | `mercadopago` | `GET /v1/statements?filters=...`，官方 Statement API | 高 | **自动拉取（首批）** |
| 4 | `paypal` | Reporting API `GET /v1/reporting/balance-transaction-list`；需单独申请 Reporting 权限范围 | 中 | **自动拉取（需申请权限）** |
| 5 | `coinbase` | `GET /orders` 订单列表（OAuth scope `orders:read`）；**无独立结算对账单**，用订单列表替代 | 中 | 自动拉取（降级为订单列表核对） |
| 6 | `toss` | Reconciliation API（`POST /v1/reconciliations` 生成 → 轮询状态 → 下载 URL） | 中 | 自动拉取（形态是"生成任务+下载"，非直接列表） |
| 7 | `gcash` | B2B 对账单下载（需确认是否有开放 API 端点） | 低 | **实施前核实**；默认走人工 CSV |
| 8 | `kakaopay` | `v1/kakaopay/settlement` 仅查结算**状态**，明细需 Partner 后台下载 | 低 | **人工 CSV**（后台下载 XLSX） |
| 9 | `mpesa` | Daraja/门户下载交易报告，无标准开放对账 API | 低 | **人工 CSV** |
| 10 | `paytm` | 商户端报告下载（`downloadReports` 类端点，需 merchant key + MD5 签名） | 低 | 自动拉取候选，**实施前核实** |
| 11 | `skrill` | Partner 门户 Download Statement（CSV/XLS） | 高 | **人工 CSV** |
| 12 | `neteller` | Business Portal Payout Report（CSV） | 高 | **人工 CSV** |
| 13 | `paysafecard` | 门户 Statement 下载 | 中 | **人工 CSV** |
| 14 | `astropay` | 门户/邮件发送对账单（CSV） | 中 | **人工 CSV** |
| 15 | `paypay` | PayPay 商户后台结算报告（无结算明细开放 API） | 中 | **人工 CSV** |
| 16 | `nowpayments` | 无对账单 API，`/v2/payment/{id}` 可单笔查询；后台 CSV + 邮件 | 高 | **人工 CSV**（单笔查询可做兜底校验） |

### 7.2 汇总

| 类别 | 网关 | 数量 |
|------|------|------|
| **可自动拉取（首批实现）** | stripe, paystack, mercadopago | 3 |
| **可自动拉取（需额外权限/核实）** | paypal, coinbase, toss, paytm | 4 |
| **实施前必须核实** | gcash, kakaopay | 2 |
| **人工 CSV 上传** | skrill, neteller, paysafecard, astropay, paypay, nowpayments, mpesa | 7 |

**结论**：约 1/4 网关能自动拉取。这个比例正常——结算对账单 API 普遍是给企业商户开放的，小商户只能走门户下载。所以**人工 CSV 上传通道必须做得和自动拉取一样顺**，不能是二等公民。

### 7.3 实现形态：可选接口

```php
namespace app\payment;

/**
 * 对账单数据源（可选接口，仅支持自动拉取的网关实现）。
 * 不实现此接口的网关走人工 CSV 上传（StatementCsvParser）。
 */
interface StatementSourceInterface
{
    /**
     * 拉取 [from, to) 区间的对账明细。
     *
     * @param \DateTimeImmutable $from 窗口起始（UTC）
     * @param \DateTimeImmutable $to   窗口结束（UTC）
     * @return iterable<array{statement_id, transaction_id, order_no, amount, currency,
     *                              settled_at, status, gross_amount, fee_amount, raw}>
     */
    public function fetch(\DateTimeImmutable $from, \DateTimeImmutable $to): iterable;
}
```

解析器（对齐 `GatewayFactory` 的 `match` 风格）：

```php
namespace app\payment;

class StatementSourceResolver
{
    /** @return StatementSourceInterface|null null=该网关需人工上传 CSV */
    public static function resolve(string $provider): ?StatementSourceInterface
    {
        return match ($provider) {
            'stripe'      => new StripeStatementSource(),
            'paystack'    => new PaystackStatementSource(),
            'mercadopago' => new MercadoPagoStatementSource(),
            // 后续按 §7.2 第二批追加：paypal / coinbase / toss / paytm
            default => null,
        };
    }
}
```

### 7.4 CSV 上传：按 provider 配置列映射

不同网关 CSV 列名完全不一致（Skrill 是 "Amount (GBP)"，Neteller 是 "Transaction Amount"，AstroPay 是日文列头）。**不要为每个网关写一个 Parser 类**——用配置映射：

```php
// service/config/statement_csv.php
return [
    'skrill' => [
        'file_type' => 'csv', 'delimiter' => ',', 'encoding' => 'UTF-8',
        'columns' => [
            'transaction_id' => 'Transaction ID',
            'amount'         => 'Amount',
            'currency'       => 'Currency',
            'settled_at'     => 'Date',
            'order_no'       => 'Reference ID',
            'status'         => 'Status',
        ],
        'status_map' => ['Credited' => 'settled', 'Pending' => 'pending'],
    ],
    // ...
];
```

`StatementCsvParser` 读配置、按列映射、按 `status_map` 归一化状态。**新接一个 CSV 网关 = 加一段配置，不改代码**。

---

## 8. 验收标准

### 8.1 功能验收

对同一批订单跑对账，逐项验证：

| # | 验收项 | 验证方法 | 通过标准 |
|---|--------|----------|----------|
| 1 | 全匹配场景 | 构造网关明细与本地订单完全一致的窗口 | `diff_count = 0`，`matched_count = 本地笔数` |
| 2 | 金额不一致可定位 | 改一笔网关明细金额 +0.50 | 出现 1 条 `AMOUNT_MISMATCH`，`local_order_no` 能定位到具体订单 |
| 3 | 状态不一致可定位 | 网关 `settled`，本地保持 `pending` | 出现 `STATUS_MISMATCH`，severity=critical |
| 4 | 本地有网关无 | 从窗口内删一笔网关明细 | 出现 `LOCAL_ONLY` |
| 5 | 网关有本地无 | 加一笔网关明细，本地无对应订单 | 出现 `GATEWAY_ONLY`，可执行 `credit` 补单 |
| 6 | 重复入账 | 同一订单写两条 deposit 流水 | 出现 `DUPLICATE_CREDIT`，`detail` 内含两条流水 ID |
| 7 | 提现打款未确认 | 本地 `payout_status=success`，渠道无记录 | 出现 `PAYOUT_UNCONFIRMED` |
| 8 | 差异处理 | 对一条差异点 `resolve` | `resolve_status=open→resolved`，`resolved_by` / `resolved_at` 落库，`OperationLog` 有记录 |
| 9 | 补单正确性 | 对 `GATEWAY_ONLY` 执行 `credit` | 订单 + 流水 + 余额三者一致，事务内完成，中途失败无脏数据 |
| 10 | 差异导出 | 导出 CSV | UTF-8 BOM，Excel 打开无乱码，列含差异类型/金额/状态/处理状态 |
| 11 | 幂等重跑 | 同一窗口触发两次 | 第二次返回 `already_done`，`resolved` 状态不丢失 |
| 12 | 容差生效 | 差额 0.005 < tolerance 0.01 | 不产生差异 |
| 13 | 人工 CSV | 上传 Skrill CSV | 正确解析、匹配、落差异 |
| 14 | 单网关失败隔离 | 让 stripe 拉取抛异常 | stripe 批次 `failed` + `error_msg`，其它网关批次正常 `done` |

### 8.2 报表验收

- 批次列表可按 `provider` / `direction` / `status` / 日期过滤，分页正常
- 批次详情展示 `gateway_amount` / `local_amount` / `amount_gap` 三行核心数字
- 差异列表支持跨批次查询未处理差异（`resolve_status=open`），critical 优先排序

### 8.3 性能验收

- 单网关单日 5 万笔明细，日终对账在 5 分钟内完成
- 匹配算法不产生 N+1 查询（本地订单一次性按 ID/transaction_id 批量查出，内存中哈希匹配）

---

## 9. 风险与缓解

| # | 风险 | 影响 | 缓解 |
|---|------|------|------|
| 1 | **时区差异** | 网关按结算日切日（UTC），本地按 `paid_at` 切日，同一笔订单可能落在两个窗口 → 假 `LOCAL_ONLY` + 假 `GATEWAY_ONLY` | 统一以东八区窗口查询，但**窗口向两侧各扩 12h**；匹配键以 `transaction_id` 为准而非日期，日期只用于圈定扫描范围 |
| 2 | **网关对账单延迟** | 昨日对账单今天还没生成，拉取返回空 → 误判为本地有网关无 | 默认 `lookback_days = 1` 实际对前天；拉取返回 0 笔且本地有订单时，批次标 `pending` 并**次日重试**，不直接判差异 |
| 3 | **对账单格式不一致** | 列名/分隔符/编码（Skrill 用 GBP 列名，Neteller 列名不同，部分网关是 `;` 分隔 + GBK 编码） | §7.4 配置映射，不写死解析逻辑；解析失败整批落 `failed` + `error_msg`，不产生半成品差异 |
| 4 | **`transaction_id` 语义混用** | crypto 网关是链上 txHash，法币网关是流水号；txHash 全局唯一但渠道可能返回不同格式 | 匹配键分层：法币网关 `transaction_id` → `order_no`；crypto 网关只用 `transaction_id`，且**规范化为小写**再比 |
| 5 | **费率变动导致系统性金额差** | 手续费口径变化后天天出 `AMOUNT_MISMATCH`，噪音淹没真问题 | 金额比较用 `amount_tolerance`（§6.3）；对账单同时拉 `gross_amount` / `fee_amount`，**手续费差与到账额差分开统计** |
| 6 | **人工 CSV 上传可信度** | 上传的是伪造文件，差异被"处理"掉 | `statement.raw` 全量留底；`resolve` 权限与 `view` 分离（§5.3）；`OperationLog` 记录处理人 |
| 7 | **补单操作误用** | 对 `GATEWAY_ONLY` 错误补单 = 无授权造钱 | `credit` 动作要求附注非空；高危，`reconciliation.resolve` 权限单独授权；`OperationLog` + 事后审计 |
| 8 | **对账单 API 限流/失效** | 网关调整 API 后日终任务静默失败 | 批次 `failed` 必写 `error_msg`；critical 差异与 failed 批次都写 `game_notification` 通知运营；不静默吞异常 |
| 9 | **`payment_method.provider` 配置漂移** | 运营把 provider 填成 `stripe `（带空格）或 `stripe-` 后，网关匹配不上 → 该支付方式的订单静默逃过对账 | 对账启动时校验每个启用的 `provider` 是否 ∈ `GatewayFactory` 支持列表，不在列表内记 `failed` 批次并通知 |
| 10 | **提现侧渠道分散** | `withdraw_order.method` 是 `paypal/bank/crypto` 三类，bank 走线下转账，**根本没有渠道对账单** | 提现对账只覆盖 `method=paypal`（有 `payout_batch_id` / `payout_item_id` 可查）；`bank` / `crypto` 标记为"人工核对"，不进自动差异 |

---

## 附录 A：实施顺序

| 阶段 | 内容 | 产出 |
|------|------|------|
| P1 | 3 张表 SQL + 模型 + `StatementSourceInterface` / `Resolver` | 骨架可跑空批次 |
| P2 | `ReconciliationService` + `Matcher`（充值方向，内存哈希匹配） | 可对账单网关 |
| P3 | Stripe 自动拉取 + CSV 配置解析器（Skrill 首发） | 首批自动 + 人工通道 |
| P4 | 管理端 9 个接口 + 差异处理 + 导出 | 可用 |
| P5 | `ReconciliationWorker` 进程 + 通知 | 日终自动跑 |
| P6 | 提现侧对账（PayPal）+ 第二批网关（Paystack / MercadoPago / PayPal / Coinbase / Toss） | 覆盖主要营收 |

P1-P4 完成即有可用能力（手动触发 + 单网关），P5 之后才进"无人值守"。按此顺序能在最短时间内拿到价值，而不是等全量网关核实完才动工。
