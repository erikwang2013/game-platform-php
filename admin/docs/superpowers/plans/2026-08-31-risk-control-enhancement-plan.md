# H4 风控纵深方案

> 目标：在现有 `RiskService` 基础上增加设备指纹 / IP 信誉 / 设备-账号关联图谱 / 提现频率异常四个维度，覆盖提现与充值环节。

## 1. 目标与范围

**范围内**
- `RiskService` 扩展 4 类新规则类型，保持 `check()` 签名向后兼容
- 提现申请（`WithdrawController::apply`）接入风控 —— 当前完全缺失
- 充值回调（`PaymentController`）风控从"只记日志"改为"阻断 + 转人工审核"
- 新增 3 张表 + `risk_log`/`risk_rule` 字段修补
- 管理端风控规则配置、事件列表、设备/IP 黑名单、关联图谱查询

**范围外**
- 支付渠道侧风控（渠道回调签名、对账）—— 属于 H3
- 游戏内反作弊 —— 属于 H5
- 实时图数据库 / 流式特征计算引擎（当前数据量不需要，见 §3.3 天花板说明）

**前置依赖**：H1 共享层落地后，本文新增模型可从 `service/app/model/` + `admin/app/model/` 双份收敛到 `common/model/`。当前 `common/` 目录尚不存在，H4 沿用现有 `RiskRule`/`RiskLog` 双份模型约定，不做收敛。

---

## 2. 现状分析

### 2.1 现有规则（`install/install.sql` 种子数据，共 4 条）

| id | 名称 | type | config | action | priority |
|---|---|---|---|---|---|
| ...0001 | IP黑名单检测 | `ip_blacklist` | `{"blacklist":[]}` | block | 100 |
| ...0002 | 单笔大额充值预警 | `amount_anomaly` | `{"min_amount":"5000","currency":"USD"}` | warn | 50 |
| ...0003 | 高频提现检测 | `frequency` | `{"window_minutes":60,"max_count":5}` | warn | 50 |
| ...0004 | 短时多账号检测 | `velocity` | `{"window_minutes":10,"max_accounts":3,"same_ip":true}` | block | 80 |

### 2.2 调用点（全项目唯一 1 处）

`service/app/api/v1/controller/PaymentController.php:179` —— 充值回调成功后调用，`checkType='deposit'`。

### 2.3 覆盖缺口与既有缺陷

1. **提现零覆盖**：`WithdrawController::apply` 完全不调用 `RiskService`。资金流出方向无风控。
2. **充值风控不生效**：`PaymentController` 命中 `block` 后仅 `Log::warning`，不回滚入账、不入人工审核队列（代码注释自认 `MVP: log warning but do NOT reverse the credit`）。
3. **velocity 规则失效（真 bug）**：`$ip = $context['ip'] ?? ''` 读取后从未用于 where 条件。实际执行的是 `RiskLog` 在时间窗口内的全平台 `distinct(user_id)` 计数，与"同 IP 多账号"无关。`whereNotNull('context')` 是无效条件。
4. **frequency 反馈自激（真 bug）**：统计对象是 `RiskLog` 而非业务事实表。每次命中都会写入 `RiskLog`，自身放大计数 —— 一次命中后下一次必然命中，且正常业务事件（真实提现）根本不参与计数。
5. **`risk_log.result` 溢出**：DDL 为 `VARCHAR(20)`，注释语义 `passed/blocked/manual_review`；代码却把 message 长文本（如 `Frequency limit exceeded: 5 in 60min (max 5)`）写入该列，静默截断。
6. **`log()` 静默吞异常**：`catch (\Throwable $e) {}` 无任何 `Log::error`，风控日志丢失不可观测。
7. **主键碰撞风险**：`intval(date('YmdHis') . random_int(10000,99999))` 每秒 9 万次可能碰撞，碰撞异常又被吞掉。`SnowflakeService` 就在 `app\common`（同 app 内），注释声称"避免跨模块依赖"不成立。
8. **高优先级空转**：`ip_blacklist` 规则 priority=100 + action=block，但种子黑名单为空数组，永远不命中。
9. **只处理首条命中规则**：无多规则叠加、无风险评分，首条命中即 return，后续命中信息丢失。
10. **schema 注释漂移**：`risk_rule.type` 注释已声明 `device_fingerprint`，但 `RiskService::evaluateRule` 无对应 case。

### 2.4 可复用资产

- `RateLimit` 中间件的 Redis Lua 滑动窗口 + fail-closed 模式（提现频率检测直接复用）
- `SnowflakeService::generate()`（`service/app/common/`，`admin/config/snowflake.php` 已有配置）
- `Erikwang2013\Encryptable\Encryptable` cast（`WithdrawOrder.account_info` 已用）
- `User.last_login_ip`、`UserSession.{ip,device,user_agent,location}` —— 设备指纹的数据来源已存在，无需前端改造
- 管理端 `AdminJwtAuth` + `AdminPermission` 中间件 + `BaseController::encodeIds()` hashid 编码

---

## 3. 风控维度设计

新增统一数据入口 `FingerprintContext::build(Request $request, int $userId): array`，一次性填充 `fp_hash`/`ip_hash`/`ua_hash` 等派生字段，供所有规则共享，避免每条规则重复查库。

### 3.1 设备指纹 `device_fingerprint`

**数据来源**：`$request->userAgent()` + 真实 IP + `Accept-Language` + `Accept-Encoding`。均为现有中间件可取字段，无需前端改造。

**采集逻辑**：
```
fp_hash  = sha256($salt . '|' . $ua . '|' . $ip . '|' . $acceptLang . '|' . $acceptEnc)
ua_hash  = sha256($ua)          // 二级聚合：区分"同浏览器版本"
ip_hash  = sha256($ip)
```
`$salt` 取 `config('encryption')['fingerprint_salt']`，**不与存储层 AES key 共用**。

**存储原则**：只存 hash。不存明文 UA、不存 IP、不存前端 device id。

**账号关联**：写 `device_account_map` 边表，登录 / 提现 / 充值三个入口 upsert：
```sql
INSERT INTO device_account_map (id, fp_hash, user_id, first_seen_at, last_seen_at, login_count, login_ip_hash)
VALUES (?, ?, ?, NOW(), NOW(), 1, ?)
ON DUPLICATE KEY UPDATE last_seen_at = NOW(), login_count = login_count + 1, login_ip_hash = ?
```
唯一键 `uk_fp_user(fp_hash, user_id)` 保证幂等。

**检测逻辑**：
- 同 `fp_hash` 关联账号数 > `max_accounts_per_device`（默认 5）→ warn
- 该 `fp_hash` 首次出现（`first_seen_at` 距今 < `lookback_hours`）且发生提现 → warn；若金额 > 3x 平台提现均值 → block
- 同 `fp_hash` 下已有账号处于 `status=0`/`frozen`，新账号 24h 内提现 → block（换设备规避封禁）

**规则配置**：
```json
{
  "components": ["user_agent", "ip", "accept_language", "accept_encoding"],
  "max_accounts_per_device": 5,
  "new_device_lookback_hours": 24,
  "new_device_withdraw_block": true
}
```

**处置动作**：warn → 提现订单 `status=manual_review`；block → 拒绝申请（403）。

### 3.2 IP 信誉库 `ip_reputation`

**数据来源**（`ip_reputation.source` 区分，优先级从高到低）：

| source | 内容 | 产生方式 |
|---|---|---|
| `whitelist` | 运营白名单 | 管理端手动（申诉解除后写入） |
| `manual` | 运营黑名单 | 管理端手动 |
| `internal` | 自建历史聚合评分 | 每日定时进程从 `risk_log` + `withdraw_order` 聚合：关联风险账号数、拒付次数、被拦截次数 |
| `external` | 公共代理/VPN 标记 | 每日定时批量检测，写入 `is_proxy`/`proxy_type` |

**检测频率**：`external` 与 `internal` 均走 webman 定时进程（`RiskIpCron.php`），每日 03:00 扫描新增 IP。同步请求路径只做 Redis 读 `ip_reputation:{ip_hash}`（TTL 24h），超时 5ms 降级为 `unknown`（放行 + 异步补检）。**同步路径绝不实时调用第三方 API**。

**判定逻辑**：
- `is_proxy = 1 AND proxy_type IN ('vpn','residential_proxy')` → warn
- `reputation_score < 30` → block
- `30 <= reputation_score < 60` → warn
- `associated_risk_accounts >= 3` → warn

**误报处理**：
- `whitelist` 优先于一切评分与黑名单判定
- block 走申诉通道：管理端"申诉解除"写 `source=whitelist`
- 公共出口特判：`associated_accounts > 100` 的 IP 视为 NAT/ISP 出口，评分判定不生效（避免误杀整片出口）
- 灰度熔断：block 命中率 > `risk_ip_auto_degrade_pct`（默认 0.5%）时，该规则当日自动降级为 warn，次日人工确认

**规则配置**：
```json
{"block_score_below": 30, "warn_score_below": 60, "proxy_warn": true, "public_exit_threshold": 100}
```

**处置动作**：block → 拒绝；warn → 强制 2FA 校验 + 提现转人工审核。

### 3.3 同设备多账号关联图谱 `device_account_graph`

**图谱结构**（边表，不做图库）：
```
device_account_map      fp_hash → user_id          权重 = login_count
account_account_link    user_id_a ↔ user_id_b      link_type: device|ip|payment
```

**关联判定**（三类边）：
- **device 边**：同 `fp_hash` 下账号数 >= 2 → 生成
- **ip 边**：同一 C 段（IPv4 前三段）内账号数 >= 3 → 生成，权重低，仅用于 warn 与图谱展示
- **payment 边**（最强信号）：`withdraw_order.account_info` 解密后归一化（paypal 邮箱全量、银行卡后 4 位、加密货币地址全量）相同 → 生成。归一化后**立即丢弃明文，只留归一化标识**，不回写任何表

**团伙识别**：两跳闭包近似，以触发账号为根。
1. 一跳：`device_account_map` 取同设备账号集 S1
2. 二跳：对 S1 再取一跳得 S2
3. `|S1 ∪ S2| >= cluster_threshold`（默认 6）→ 团伙告警
4. S1 中任一账号处于 `frozen`/禁用/申诉中 → 当前账号 block

> `ponytail: 两跳闭包近似，非连通分量精确算法。受 max_accounts_per_device 约束（默认 50 截断），单账号查询 O(n²) 在 n<50 时可忽略。账号数 > 1000 或需要跨设备精确团伙时，改用定时进程构建连通分量落 `risk_cluster` 表，管理端直接读结果。`

**规则配置**：
```json
{"hops": 2, "cluster_threshold": 6, "device_edge_accounts": 2, "ip_edge_accounts": 3, "payment_edge_block": true}
```

**处置动作**：block → 拒绝提现并冻结余额（写 `user_wallet.frozen_balance`，`version` 乐观锁已在模型上）；warn → 提现转人工审核 + 管理端团伙视图高亮。

### 3.4 提现频率异常检测 `withdraw_pattern`

**数据来源**：`withdraw_order`（业务事实表，**不读 `risk_log`**，修复 §2.3-4 自激问题）+ `user_wallet.total_spent` + `user.country`（时区）。

**频率阈值**（Redis Lua 滑动窗口，复用 `RateLimit` 的原子化脚本，key 前缀 `risk:withdraw:freq:{ip_hash}` 与 `risk:withdraw:count:{user_id}`）：
- 窗口内申请次数 > `max_applies`（默认 60min / 5 次）
- 与上一笔提现间隔 < `min_interval_minutes`（默认 10 分钟）

**金额异常**：
- 单笔 > `single_hard_cap`（默认 50000）→ block + 人工审核
- 单笔 / 当前余额 >= `drain_ratio`（默认 0.99，清仓式提现）→ warn
- 单笔 > 该用户近 90 天提现均值 + `sigma_multiplier` × 标准差（样本 < 3 笔不计算，避免新用户被误杀）→ warn
- 提现金额落在 `[90%, 100%] × daily_limit` 区间连续 3 次 → warn（探测限额行为）

**时间模式**：
- `suspicious_hours`（默认 `["01:00","05:00"]`，按用户 `country` 折算本地时区）内提现占比 > 60% 且总次数 >= 10 → warn
- 相邻提现间隔 < 20s 且次数 >= 5（机械间隔，自动化脚本特征）→ warn

**规则配置**：
```json
{
  "window_minutes": 60,
  "max_applies": 5,
  "min_interval_minutes": 10,
  "single_hard_cap": "50000",
  "drain_ratio": "0.99",
  "sigma_window_days": 90,
  "sigma_multiplier": 3,
  "suspicious_hours": ["01:00", "05:00"],
  "probe_near_limit_pct": 90,
  "fast_interval_seconds": 20,
  "fast_interval_min_count": 5
}
```

**处置动作**：block → 拒绝申请，403 + 风控提示，不创建订单；warn → 创建订单但 `status=manual_review`，不冻结余额，暂停自动放款。

---

## 4. 数据模型变更

追加到 `install/install.sql`（前缀 `game_`，主键 Snowflake）。

### 4.1 新增表

```sql
-- ============================================================
-- 设备指纹表（只存 hash，不存明文 UA/IP）
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_device_fingerprint` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，snowflake生成',
    `fp_hash` CHAR(64) NOT NULL COMMENT '设备指纹 sha256(salt|ua|ip|accept_lang|accept_enc)',
    `ua_hash` CHAR(64) NOT NULL COMMENT 'User-Agent sha256，用于浏览器版本聚合',
    `ip_hash` CHAR(64) NOT NULL COMMENT '真实IP sha256',
    `device_type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'web/ios/android/harmonyos',
    `accept_lang` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '语言偏好（非PII，可用于聚合分析）',
    `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '首次观测时间',
    `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最近观测时间',
    `account_count` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '关联账号数（冗余计数，避免count查询）',
    `blocked` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=正常 1=黑名单设备',
    `block_reason` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '拉黑原因',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_fp_hash` (`fp_hash`),
    KEY `idx_ip_hash` (`ip_hash`),
    KEY `idx_ua_hash` (`ua_hash`),
    KEY `idx_account_count` (`account_count`),
    KEY `idx_last_seen_at` (`last_seen_at`),
    KEY `idx_blocked` (`blocked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备指纹表';

-- ============================================================
-- 设备-账号关联边表（图谱主表，支撑设备→账号与账号→账号推导）
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_device_account_map` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，snowflake生成',
    `fp_hash` CHAR(64) NOT NULL COMMENT '设备指纹',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '账号ID',
    `login_ip_hash` CHAR(64) NOT NULL DEFAULT '' COMMENT '最近一次关联IP的sha256',
    `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `login_count` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '关联次数（图谱边权重）',
    `last_action` VARCHAR(30) NOT NULL DEFAULT 'login' COMMENT '最近关联动作: login/withdraw/deposit/exchange',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_fp_user` (`fp_hash`, `user_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_last_seen_at` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备-账号关联边表';

-- ============================================================
-- IP信誉表
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_ip_reputation` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，snowflake生成',
    `ip_hash` CHAR(64) NOT NULL COMMENT 'IP sha256（不存明文）',
    `ip_c_segment` VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'IPv4 C段（前三段）或IPv6 /48，用于C段聚合查询',
    `source` VARCHAR(20) NOT NULL DEFAULT 'internal' COMMENT 'whitelist/manual/internal/external',
    `reputation_score` TINYINT UNSIGNED NOT NULL DEFAULT 50 COMMENT '0-100信誉分',
    `is_proxy` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=非代理 1=代理/VPN',
    `proxy_type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT 'vpn/residential_proxy/datacenter/''',
    `associated_accounts` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联账号总数（>100视为公共出口）',
    `associated_risk_accounts` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联的风险账号数',
    `block_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计被拦截次数',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=失效 1=生效',
    `external_checked_at` DATETIME DEFAULT NULL COMMENT '外部检测结果时间',
    `internal_scored_at` DATETIME DEFAULT NULL COMMENT '内部聚合评分时间',
    `appeal_note` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '申诉记录',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ip_hash` (`ip_hash`),
    KEY `idx_source_status` (`source`, `status`),
    KEY `idx_c_segment` (`ip_c_segment`),
    KEY `idx_score` (`reputation_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP信誉表';

-- ============================================================
-- 账号-账号关联边表（由设备/IP/收款账户共现派生）
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_account_account_link` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，snowflake生成',
    `user_id_a` BIGINT UNSIGNED NOT NULL COMMENT '账号A（user_id_a < user_id_b 规范存储）',
    `user_id_b` BIGINT UNSIGNED NOT NULL COMMENT '账号B',
    `link_type` VARCHAR(20) NOT NULL COMMENT 'device/ip/payment',
    `link_ref` CHAR(64) NOT NULL DEFAULT '' COMMENT '关联依据：fp_hash / ip_c_segment / 归一化收款标识',
    `weight` DECIMAL(5,2) NOT NULL DEFAULT 1.00 COMMENT '边权重：payment=1.00 device=0.60 ip=0.30',
    `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `occurrences` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '共现次数',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_pair_type` (`user_id_a`, `user_id_b`, `link_type`),
    KEY `idx_user_b` (`user_id_b`),
    KEY `idx_link_type` (`link_type`, `weight` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='账号关联边表';
```

### 4.2 修改现有表（修复 §2.3 缺陷）

```sql
-- 修复缺陷 5：result 被长文本静默截断
ALTER TABLE `game_risk_log`
    MODIFY `result` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '处理结果: passed/warn/blocked/manual_review',
    ADD COLUMN `detail` TEXT NULL COMMENT '命中详情（原 message 长文本，避免占用 result）',
    ADD COLUMN `ip_hash` CHAR(64) NOT NULL DEFAULT '' COMMENT '请求IP sha256',
    ADD COLUMN `fp_hash` CHAR(64) NOT NULL DEFAULT '' COMMENT '设备指纹 sha256',
    ADD COLUMN `user_agent_hash` CHAR(64) NOT NULL DEFAULT '' COMMENT 'UA sha256',
    ADD KEY `idx_ip_hash_created` (`ip_hash`, `created_at`),
    ADD KEY `idx_fp_hash` (`fp_hash`),
    ADD KEY `idx_action_created` (`action`, `created_at`);

-- 修复缺陷 10：schema 注释补齐新规则类型
ALTER TABLE `game_risk_rule`
    MODIFY `type` VARCHAR(40) NOT NULL COMMENT '类型: ip_blacklist/amount_anomaly/frequency/velocity/device_fingerprint/ip_reputation/device_account_graph/withdraw_pattern',
    ADD COLUMN `scope` VARCHAR(30) NOT NULL DEFAULT 'all' COMMENT '生效环节: all/deposit/withdraw/login/exchange';
```

`risk_log` 新列同时修复缺陷 1：`velocity` 规则改按 `ip_hash` 过滤（`idx_ip_hash_created` 复合索引支撑）。

**新增模型文件**（沿用现有双份约定，`RiskRule`/`RiskLog` 已在两侧各有一份）：
`DeviceFingerprint`、`DeviceAccountMap`、`IpReputation`、`AccountAccountLink` → 各在 `service/app/model/` 与 `admin/app/model/` 各一份，与 `RiskRule`/`RiskLog` 保持一致。

---

## 5. 风控规则引擎增强

### 5.1 评估器接口化（终止 switch 增长）

现状 `evaluateRule()` 是 switch，新增 4 个维度会把 switch 撑到 8 个 case。改为策略接口：

```php
interface RiskEvaluator
{
    public function type(): string;
    public function evaluate(RiskRule $rule, int $userId, string $checkType, array $context): array;
    // 返回 ['matched' => bool, 'message' => string, 'detail' => string, 'score' => int]
}
```

`RiskService` 持 `evaluatorMap`（`type => RiskEvaluator`），注册 `IpBlacklistEvaluator`（现有逻辑迁出）、`AmountAnomalyEvaluator`、`FrequencyEvaluator`、`VelocityEvaluator` 四个现有 + 四个新增。新增维度 = 新增一个类 + 注册，**不改 `RiskService` 主体**。

### 5.2 签名与返回值

保持向后兼容：`check(int $userId, string $checkType, array $context): array` 返回结构不变（`result/message/rule_name`）。

新增 `score()` 返回全量命中，`check()` = `score()` + 处置映射：

```php
public static function score(int $userId, string $checkType, array $context = []): array
// 返回 ['result' => 'passed|warn|block', 'action' => '', 'hits' => [
//   ['rule_id'=>, 'rule_name'=>, 'type'=>, 'action'=>, 'message'=>, 'detail'=>, 'score'=>], ...
// ], 'score' => 累计风险分]
```

### 5.3 规则优先级与叠加处置

- 执行顺序：`getEnabled()` 已按 `priority DESC` 排序，保留。
- 叠加策略：遍历全部规则收集所有命中（**不再首条即 return**），处置取最严 `block > warn > log`，累计 `score`。
- 命中列表**全部写入** `risk_log`（每命中一条），`detail` 列存 message 长文本，`result` 列存归一化动作 —— 修复缺陷 5 的同时保留完整审计信息。
- 短路优化：一旦某条规则命中 `block` 且 `scope` 匹配，可跳过后续 warn 级评估（性能），但已收集的命中仍全量落日志。

### 5.4 `scope` 过滤

新增 `scope` 列后，`deposit` 检查跳过 `scope=withdraw` 的规则，反之亦然。现状 `frequency` 规则的 `{"window_minutes":60,"max_count":5}` 注释写"高频提现"但实际对 deposit 也生效 —— `scope` 列让这类配置歧义可消除。

### 5.5 上下文构造

新增 `FingerprintContext::build()`，调用方一次调用，避免 8 条规则各自查库：

```php
$context = FingerprintContext::build($request, $userId, $amount);
// ['ip','amount','fp_hash','ip_hash','ua_hash','device_type',
//  'balance','user_level','kyc_status','country','timezone',
//  'account_count_on_device','ip_reputation','first_seen_fp_hours_ago']
```

### 5.6 熔断与故障策略

| 故障 | 策略 |
|---|---|
| 硬规则（`ip_blacklist`、`device_fingerprint.blocked=1`、`ip_reputation.source=manual`）DB 异常 | **fail-closed**，拒绝 |
| 软规则（frequency/withdraw_pattern/device_account_graph）DB 或 Redis 异常 | **fail-open**，放行 + 异步落日志 |
| 风控总耗时 > 200ms | fail-open，记录超时指标 |
| Redis 缓存 miss | 降级为 `unknown`，放行 + 异步补检 |

复用 `RateLimit` 的 fail-closed 写法作为模板。

### 5.7 规则热更新

`RiskRule::getEnabled()` 当前每次查库。改为 30s Redis 缓存（key `risk:rules:enabled`），管理端保存/启停规则时主动 `Redis::del()` 失效 —— 兼顾查询性能与运营实时生效。

---

## 6. 管理端 API

新增控制器放 `admin/app/admin/controller/`，路由挂到既有 `/admin` group（已含 `AdminJwtAuth` + `AdminPermission`），ID 一律 `BaseController::encodeIds()` hashid 编码。

### 6.1 路由（追加到 `admin/config/route.php` 的 `/admin` group）

```php
// 风控规则
Route::get('/risk/rule/list', [RiskRuleController::class, 'list']);
Route::post('/risk/rule/create', [RiskRuleController::class, 'create']);
Route::put('/risk/rule/{hashid}', [RiskRuleController::class, 'update']);
Route::post('/risk/rule/{hashid}/toggle', [RiskRuleController::class, 'toggle']);
Route::post('/risk/rule/test', [RiskRuleController::class, 'test']); // 沙箱试算，不写日志

// 风险事件
Route::get('/risk/event', [RiskEventController::class, 'list']);
Route::get('/risk/event/{hashid}', [RiskEventController::class, 'detail']);
Route::post('/risk/event/{hashid}/handle', [RiskEventController::class, 'handle']); // 人工审核放行/驳回

// 设备黑名单
Route::get('/risk/device/list', [RiskDeviceController::class, 'list']);
Route::post('/risk/device/block', [RiskDeviceController::class, 'block']);
Route::post('/risk/device/{hashid}/unblock', [RiskDeviceController::class, 'unblock']);

// IP 黑名单 / 白名单
Route::get('/risk/ip/list', [RiskIpController::class, 'list']);
Route::post('/risk/ip/block', [RiskIpController::class, 'block']);
Route::post('/risk/ip/whitelist', [RiskIpController::class, 'whitelist']);
Route::post('/risk/ip/appeal', [RiskIpController::class, 'appeal']); // 申诉解除，写 source=whitelist
Route::post('/risk/ip/recheck', [RiskIpController::class, 'recheck']); // 触发异步外部检测

// 关联图谱
Route::get('/risk/graph/{userId}', [RiskGraphController::class, 'graph']); // 节点+边，供前端画图
Route::get('/risk/graph/clusters', [RiskGraphController::class, 'clusters']);

// 风控概览
Route::get('/risk/dashboard', [RiskDashboardController::class, 'index']); // 命中率/误杀率/团伙数
```

### 6.2 关键返回结构

`GET /admin/risk/event` 列表项（PII 已脱敏）：
```json
{
  "id": "hashid",
  "user_id": "hashid",
  "type": "withdraw",
  "rule_name": "提现频率异常检测",
  "action": "warn",
  "result": "manual_review",
  "detail": "窗口内 6 次申请 > 阈值 5",
  "ip_masked": "1.2.*.*",
  "fp_masked": "3f9a…c21d",
  "created_at": "2026-08-31 01:00:00"
}
```

`GET /admin/risk/graph/{userId}`：
```json
{
  "root": "hashid",
  "nodes": [{"id": "hashid", "username": "u123", "status": 1, "frozen": false}],
  "edges": [{"from": "h1", "to": "h2", "type": "payment", "weight": 1.00, "occurrences": 2}],
  "hops": 2,
  "cluster_size": 7,
  "risk_verdict": "cluster_suspicious"
}
```

IP 展示统一用 `ip_masked`（IPv4 前三段 + `*`，IPv6 `/48` 段），明细页可展开完整 IP，操作需二次权限校验并写操作日志。**IP 明文只存在于 `ip_reputation` 之外 —— 表中存 hash**，`ip_masked` 由 hash 反查不成立，故展示用另存的 `ip_c_segment` 列（见 §4.1）。

### 6.3 沙箱试算

`POST /risk/rule/test` 接受 `userId + context + rule_id`，只跑该规则评估器、不写 `risk_log`、不执行处置。运营改阈值时先看命中面，避免线上误杀。

---

## 7. 验收标准

| # | 标准 | 验证方式 |
|---|---|---|
| 1 | 新增 4 类规则可通过管理端配置启停，30s 内生效 | 创建规则 → 沙箱试算命中 → `Redis::ttl risk:rules:enabled` 验证缓存 |
| 2 | 命中必写 `risk_log`，`result` 为归一化值不截断，长文本落 `detail`，含 `ip_hash`/`fp_hash` | 断言 `result` ∈ `{passed,warn,blocked,manual_review}` 且 `detail` 完整 |
| 3 | `WithdrawController::apply` 接入风控：`block` 返回 403 且不创建订单 | 断言 `withdraw_order` 无新增行 |
| 4 | 充值回调命中 `block` 时订单转 `manual_review`，不再静默通过 | 断言 `deposit_order.status='manual_review'`，无入账 |
| 5 | **修复缺陷 1**：`velocity` 按 `ip_hash` 过滤生效 | 同 IP 3 账号 10 分钟内 → `block`；3 个不同 IP 的 3 账号 → 不命中 |
| 6 | **修复缺陷 4**：`frequency` 不再自激 | 构造"历史 4 条 `risk_log` + 1 笔真实提现"，`max_count=5` 时不应命中；再改查 `withdraw_order` 后应命中 |
| 7 | **修复缺陷 7**：`risk_log.id` 改用 `SnowflakeService::generate()` | 并发 1000 次写入无主键冲突 |
| 8 | 多规则叠加：2 条 warn + 1 条 block → 处置为 `block`，3 条全部落日志 | 断言 `hits` 长度 3 且日志行数 3 |
| 9 | 测试覆盖 | `service/tests/RiskServiceTest.php` 扩展：4 个新评估器各 ≥3 用例（命中/未命中/边界），覆盖 `block`/`warn`/`passed` 三态与叠加处置；`phpunit` 全绿 |
| 10 | 沙箱试算不污染生产数据 | 调用 `/risk/rule/test` 后 `risk_log` 行数不变 |

---

## 8. 风险与缓解

### 8.1 误杀正常用户

- **默认灰度**：新增 4 条规则的种子数据全部 `status=0`（禁用），管理端逐条开启；硬 `block` 规则开启前先设 `action=warn` 观察 48h 命中面。
- **warn 不阻断**：warn 只转人工审核或延迟放款，不影响用户余额与后续操作。
- **申诉通道**：IP 申诉写 `source=whitelist`，白名单优先级最高；设备误拉黑有 `unblock` 接口。
- **新用户保护**：`withdraw_pattern` 的 σ 检测要求样本 ≥ 3 笔提现才计算；新设备规则对首次提现只 warn，不 block。
- **灰度熔断**：IP 信誉 block 命中率 > 0.5% 当日自动降级 warn，次日人工确认（配置阈值可调）。
- **公共出口特判**：`associated_accounts > 100` 的 NAT/ISP 出口 IP 不参与评分判定。
- **沙箱试算**：运营改阈值前先看命中面。

### 8.2 隐私合规（PII 处理）

- **指纹不可逆**：`device_fingerprint` 只存 `fp_hash`/`ua_hash`/`ip_hash`（sha256 + 独立 salt），**不存明文 UA、不存明文 IP、不存前端 device id**。
- **IP 明文不落库**：`ip_reputation` 存 `ip_hash`；C 段聚合用 `ip_c_segment`（`1.2.3` 这类，不含主机段，粒度足够聚合又不指向个体）。
- **收款账户归一化即弃**：`account_info` 已在 `WithdrawOrder` 用 `Encryptable` 加密。图谱 payment 边判定时解密 → 归一化（paypal 邮箱 / 卡号后 4 位 / 加密地址）→ 只写 `link_ref`，**不回写任何表、不进日志**。
- **风控日志不含明文账户**：`risk_log.context` 与 `detail` 中禁止出现 `account_info` 明文；只写 hash 与脱敏掩码。
- **管理端脱敏展示**：列表页 `ip_masked`/`fp_masked`；明细页展开完整 IP 需二次权限校验并写操作日志。
- **数据保留**：`risk_log` 180 天自动清理（定时进程按月 `DELETE`，避免 `risk_log` 无限增长）；`device_fingerprint`/`device_account_map` 提供按 `user_id` 级联删除入口，支撑删除权请求（GDPR 23 / PIPL 45）。

### 8.3 性能影响

| 关注点 | 措施 |
|---|---|
| 规则查库 | 30s Redis 缓存（§5.7），单请求从 1 次 SQL 降为 0~1 次 |
| 上下文重复查库 | `FingerprintContext::build()` 一次填充全部派生字段，8 条规则共享 |
| 外部 IP 检测 | 异步定时进程 + Redis TTL 缓存；同步路径超时 5ms 降级，绝不阻塞提现 |
| 图谱查询 | 两跳闭包 + `max_accounts_per_device` 截断（默认 50）；受 `uk_fp_user` 唯一键支撑，无全表扫 |
| `risk_log` 增长 | 新增 `idx_action_created`/`idx_ip_hash_created` 复合索引；180 天清理 |
| 指纹写入 | `INSERT ... ON DUPLICATE KEY UPDATE` 单语句幂等，登录/提现/充值三入口复用 |
| 单次风控预算 | 目标 < 100ms，超时 200ms fail-open（§5.6） |

### 8.4 其他风险

- **Redis 不可用**：软规则 fail-open，硬规则 fail-closed；`RateLimit` 已有 fail-closed 模板可复用。
- **时区误判**：`suspicious_hours` 按 `user.country` 折算本地时区，而非服务器时区，避免欧美用户被判定为"深夜提现"。
- **C 段聚合误判**：IPv6 用 `/48` 段；IPv4 家用宽带 C 段账号数天然少，`ip_edge_accounts` 默认 3 且权重仅 0.30，只用于 warn 不用于 block。

---

## 9. 实施顺序

| 阶段 | 内容 | 依赖 |
|---|---|---|
| P0 | 修 4 个既有缺陷（velocity IP 过滤、frequency 改查业务表、`result`/`detail` 拆分、`SnowflakeService` 主键 + `Log::error`） | 无 |
| P1 | 建 4 张新表 + 2 条 ALTER，追加到 `install/install.sql`；新增 4 个模型（双侧各一份） | P0 |
| P2 | `RiskEvaluator` 接口化 + 4 个现有评估器迁出 + `score()`/`check()` 分层 + 叠加处置 | P1 |
| P3 | `FingerprintContext` + 设备指纹维度（`DeviceFingerprintEvaluator` + upsert） | P2 |
| P4 | IP 信誉维度 + `RiskIpCron` 定时进程 | P3 |
| P5 | 关联图谱维度（`AccountAccountLink` 派生 + 两跳闭包） | P3 |
| P6 | 提现频率异常维度（复用 `RateLimit` Lua 窗口） | P2 |
| P7 | 调用点接入：`WithdrawController::apply` + `PaymentController` 转人工审核 | P3-P6 |
| P8 | 管理端 5 个控制器 + 路由 + 沙箱试算 | P7 |
| P9 | 测试补齐（§7 全部 10 条） | P0-P8 |

P0 与 P1 无依赖，可并行。P3-P6 四个维度互不依赖，可并行。
