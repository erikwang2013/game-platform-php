# H5 游戏侧反作弊方案设计

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> 不可修改、不可移除、不可逆。

日期：2026-08-31　状态：设计稿（待评审）

---

## 0. 前置结论（读代码得到的硬事实，决定整个方案的形态）

以下 6 条是读代码得出的，不是假设，方案按它们来设计：

1. **ClickHouse 没有接入。** `install/clickhouse.sql` 只是 DDL，`service/`、`packages/` 下没有任何 ClickHouse 客户端代码。`ProbabilityService` 用的是 `support\Db`，查的是 **MySQL** `game_game_play_log`。所以离线批量检测要么补 ClickHouse 写入链路，要么先跑在 MySQL 上。
2. **MySQL 与 ClickHouse 的 `game_game_play_log` 字段不一致。** MySQL 有金额三件套（`game_amount_before/change/after`）、`round_id`、`started_at`、`ended_at`；ClickHouse 只有 `detail`/`ip_address`/`user_agent`，**没有金额列**。反作弊核心信号（下注额、赢额）在 ClickHouse 里取不到。
3. **IP / User-Agent 被塞进了 `metadata` JSON。** `GamePlayLogService::write()` 把 `$ip`、`$user_agent` 写进 `metadata`，不是独立列。同 IP 多账号检测要做 `JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.ip'))`，无法建索引。
4. **`ProviderController::logPlay()` 写 settle/refund 时不写 `ip`/`user_agent`/`ended_at`。** 结算是反作弊最关键的行（有赢额），恰恰是最没有环境信息的行。
5. **`bet_amount` / `win_amount` 列是死列。** Phase 1 的 ALTER 给 MySQL 加了这两列，但 `GamePlayLogService::write()` 和 `ProviderController::logPlay()` 都不写它们。真实数值在 `game_amount_change`（bet 行为负、settle 为正）。
6. **`RiskService` 的 `velocity` 规则实现是错的。** 它用 `RiskLog::whereNotNull('context')->distinct('user_id')->count()` 统计用户数，**完全没有按 IP 过滤**——统计的是时间窗内全部风控用户。同 IP 检测不能复用它，需要新查询。

> 结论：反作弊**不能**复用 `RiskService` 的 velocity 分支；`RiskRule`/`RiskLog` 的表结构与调用范式可以复用；ClickHouse 需要补写入链路或降级到 MySQL 离线跑。

---

## 1. 目标与范围

**目标**：基于玩法数据（对局日志）自动识别三类行为，并把结果变成可执行的风控动作。

| 目标行为 | 识别什么 | 对应处置 |
|---|---|---|
| 刷币 | 小号群 + 同 IP/设备协同 + 提现 | 冻结、限制提现、封号 |
| 机器人 | 恒定操作间隔、超长在线、超快出招 | 账号标记、单轮赔付封顶、人工审核 |
| 作弊 | 胜率远超分布、服务端可复算不一致 | 单局拒赔、封号 |

**范围内**：自建游戏（`SelfProvider`，如 `game/xiaoxiaole`）的服务端数据检测；管理端处置与信任分。

**范围外（本期不做，明确记录）**：
- 客户端篡改检测（抓包、内存修改、模拟器识别）——需要客户端 SDK，本期只做服务端行为侧。
- 第三方 Provider 游戏的作弊检测——平台拿不到对局明细，只能拿对账结果，本期不覆盖。
- 机器学习模型训练/推理——用可解释统计方法，理由是作弊模式固定（固定注、马丁格尔、机器人间隔），统计阈值可运维，误报可解释给客服；等积累 ≥3 个月标注样本再上模型。

---

## 2. 现状分析

### 2.1 现有字段（MySQL `game_game_play_log`，`install/install.sql:538`）

| 字段 | 反作弊可用性 |
|---|---|
| `user_id` / `game_id` / `server_id` | 分组键，可用 |
| `session_id` | 会话粒度，可用 |
| `round_id` | **对局粒度关键键**（`SelfProvider::settle` 的幂等键），但 `GamePlayLogService::write()` 不写它，只有 `ProviderController::logPlay()` 写 |
| `action` | `start/end/earn/spend/settle/refund/launch`——**无 `level_win`/`level_fail` 语义** |
| `game_amount_change` | 真实下注/赢额载体 |
| `metadata` | JSON，含 `ip`、`user_agent` |
| `started_at` | 有 |
| `ended_at` | **只有 session 级**：`ProviderController` 在 settle 时把 session 下所有 `action='start'` 的 `ended_at` 一起改掉，无对局级时长 |

### 2.2 游戏玩法侧（`game/xiaoxiaole/api.md`）

田园消消乐，静态前端 + 服务端权威钱包。`round_id = session_id:levelId:attempt`。文档第 4 节定义了 4 个待上报事件：

| 事件 | 时机 | 现状 |
|---|---|---|
| `level_start` | 进入关卡 | 未实现（文档写"可先打 ClickHouse"，而 ClickHouse 没接入） |
| `level_win` | 通关 | 未实现 |
| `level_fail` | 失败 | 未实现 |
| `skill_use` | 使用技能 | 未实现 |

**这是最大的检测缺口**：胜率 = `level_win / (level_win + level_fail)`，而这两个事件目前都不存在。用 `settle` 行数推胜率只能得到"入场费关卡的付费率"，对免费关卡型消消乐几乎无信号。

文档第 7 节已规划"第二期服务端复算"（`seed + 操作序列` 服务端复算校验棋盘与得分）——这是**唯一能确定性判定消消乐作弊的手段**，但本期不做，方案预留接口位。

### 2.3 检测能力矩阵

| 检测维度 | 现有数据够不够 | 缺口 |
|---|---|---|
| 胜率异常 | 部分够（付费关卡型）；消消乐**不够** | 缺 `level_win`/`level_fail` 事件 |
| 下注额异常 | **够** | 无（`game_amount_change` 在 bet 行） |
| 对局时长异常 | **不够** | 缺对局级 `ended_at`（只有 session 级） |
| 操作频率异常 | **不够** | 缺对局级时间戳、缺操作序列上报 |
| 同 IP/设备 | **不够** | IP 在 JSON 里无法索引；无 `device_id`；`ended_at` 缺失导致聚类信号弱 |

---

## 3. 检测维度设计

通用约定：每个维度产出 `{'rule','severity','evidence','score_delta'}`，不直接处置，统一进信任分与事件表。所有阈值走 `game_risk_rule.config`，不硬编码。

### 3.1 胜率异常检测

**数据特征**：用户级滚动胜率，按 `game_id` 分层（不同游戏胜率不可比）。

```
p_user = (wins + k) / (rounds + 2k)      -- Laplace 平滑，k 默认 10
z      = (p_user - mu) / sigma           -- mu/sigma 取同 game 全体活跃用户分布
```

选 Laplace 平滑而不是裸 Z-score 的理由：新用户 3 连胜裸 Z-score 必爆表，平滑后前 10 局不产生信号，正好对齐"样本不足不下结论"。

**检测算法**：
1. 窗口：近 30 天，`rounds >= 30`（低于此不进榜，直接跳过，省算力）。
2. 触发条件（任一）：`z >= 3.0`（长期过高）**或** IQR 上围栏 `p_user > Q3 + 1.5*IQR`。
3. 趋势项（抓新部署的作弊器）：近 100 局胜率 vs 前 100 局胜率的两比例 Z 检验，`p < 0.001` 且提升 ≥ 15pp。
4. 付费关卡型额外信号：`avg_win / avg_bet` 偏离该游戏历史均值 3σ 以上。

**阈值配置**（`config` JSON）：

```json
{"window_days":30,"min_rounds":30,"z_threshold":3.0,"iqr_k":1.5,
 "smoothing_k":10,"trend_split":100,"trend_lift_pp":15,"trend_p":0.001}
```

**处置动作**：`warn` → 记事件 + 信任分 -20；连续 2 天命中 → `block` 该游戏结算。拒赔需要给 `SelfProvider::settle()` 新增一个分支（现有实现只有"成功入账"和"幂等跳过"两种返回，没有拒赔语义），且拒赔判断必须放在 `Db::transaction` 外层，避免事务里读信任分。

### 3.2 下注额异常检测

**数据特征**：按 `round_id` 排序的 `bet[]` 序列（取 `action='bet'` 行的 `ABS(game_amount_change)`）。

**检测算法**：模式匹配四模板，逐个算命中率，最高者 > 0.6 触发。

| 模板 | 判定式 | 典型命中 |
|---|---|---|
| 固定金额 | `stddev(bet) / mean(bet) < 0.02` 且 `n >= 30` | 固定注刷量 |
| 马丁格尔 | `count(bet[i] ≈ 2*bet[i-1] 且 上一局输) / 输局数 > 0.6` | 经典翻倍 |
| 反马丁格尔 | `count(bet[i] ≈ 2*bet[i-1] 且 上一局赢) / 赢局数 > 0.6` | 连胜加注 |
| 等差数列 | 相邻差值 `abs(diff[i] - diff[i-1]) < 1%` 连续 ≥ 8 次 | 自动化脚本 |

人工基线参考：真人用户 `CV(bet)` 一般 0.3–0.8，机器人 < 0.02。等差检测用 `LagInFrame`（ClickHouse）或自连接（MySQL）。

**阈值配置**：

```json
{"min_rounds":30,"fixed_cv":0.02,"martingale_ratio":0.6,
 "ratio_tolerance":0.005,"ar_run":8,"ar_diff_tolerance":0.01}
```

**处置动作**：`warn` + 信任分 -10；固定金额 + 高胜率同时命中 → 直接 `block`（两者叠加即强信号）。

### 3.3 对局时长异常

**数据特征**：`duration = ended_at - started_at`（**依赖 5.1 的字段补齐**，否则不可用）。

**检测算法**：对 `ln(duration)` 做 IQR（时长分布是长尾的，直接对原值算 IQR 会让长对局一侧失效）。

```
lower = Q1 - 1.5*IQR_ln,  upper = Q3 + 1.5*IQR_ln
异常短: ln(duration) < lower   → 秒过 / 重放缓存结果
异常长: ln(duration) > upper   → 挂机骗时长类奖励
```

对消消乐这类出招数确定的游戏，更稳的指标是**单位出招时长** `duration / move_count`：

```
人类中位数约 0.6–1.2 s/出招
触发: moves_per_second > 该游戏 P99（默认 4.0 出招/秒）
```

**阈值配置**：

```json
{"iqr_k":1.5,"abs_min_seconds":3,"abs_max_seconds":3600,
 "max_moves_per_second":4.0,"min_moves_for_rate":20}
```

**处置动作**：`warn` + 信任分 -8。异常短单局叠加赔付 > 赔付封顶 → 该单局**拒赔**（只回入场费）。

### 3.4 操作频率异常

**数据特征**：同 session 内事件到达时间戳序列 → 相邻间隔 `gap[]`。

**检测算法**（三个独立信号，命中 2 个即判定）：

1. **间隔方差趋零**：`CV(gap) < 0.05` 且 `median(gap) < 500ms` → 机器级恒定间隔。真人 `CV(gap)` 通常 > 0.3。
2. **超长在线**：单日活跃 ≥ 20 小时，或连续 24h 内无 ≥ 3h 中断。
3. **对局速率**：`rounds_per_hour > 该游戏 P99`（默认 300 局/小时）。

实时侧用 Redis 滑窗计数（见 4.2），离线侧用 ClickHouse `LagInFrame` 算 gap。

**阈值配置**：

```json
{"gap_cv_max":0.05,"gap_median_ms_max":500,"max_active_hours":20,
 "min_break_seconds":10800,"rounds_per_hour_p99":300,"need_hits":2}
```

**处置动作**：`warn` + 信任分 -15；命中 3/3 → 自动封禁候选（进人工队列，不自动封）。

### 3.5 同设备 / 同 IP 异常

**数据特征**：`(ip, ua_hash)` → 账号集合。**当前 IP 在 `metadata` JSON 里**，必须按 5.1 迁移出来，否则本维度无法索引扫描。

**检测算法**：

1. **同 IP 多账号**：时间窗 `W` 内同 IP 活跃账号数 ≥ `N`。
2. **协同同步性**（比数量更有杀伤力）：同 IP 下各账号的 bet 时间戳，若两两 `abs(t_a - t_b) < 1s` 的对数占比较集中 → 共享设备/代理群控。
3. **邀请链 + 同 IP**：`game_referral.referrer_id` 与被邀请者同 IP → 高优先（刷注册 + 刷佣的合体）。
4. **设备指纹**：需要 `device_id`（见 5.1 补齐项），同 `device_id` ≥ 3 账号直接高危。

**重要**：不要用 `RiskService` 的 `velocity` 分支（0 节前置结论 6，它不按 IP 过滤）。

**阈值配置**：

```json
{"window_minutes":60,"max_accounts_per_ip":3,"max_accounts_per_device":2,
 "sync_tolerance_ms":1000,"sync_pair_ratio":0.5,"referral_same_ip_score":0.9}
```

**处置动作**：集群 ≥ 3 账号 → 全部 `warn` + 信任分 -30，集群内非主账号进冻结队列；主账号定义 = 该 IP 下总充值最高者（避免误伤真人）。

---

## 4. 检测架构

### 4.1 分层：实时 3 项 + 离线 5 项

原则：**只把只需"当前对局 + 一个计数器"的检测放实时**，需要 ≥30 局历史的一律离线。这条边界决定了 Redis 压力和 SQL 压力的分配。

| 维度 | 实时 | 离线 |
|---|---|---|
| 操作频率（间隔、速率） | 实时（Redis 滑窗） | 离线复核 |
| 对局时长（绝对下限、出招速率） | 实时 | 离线 IQR |
| 单轮赔付封顶 | 实时 | — |
| 胜率 | — | 每日 |
| 下注模式 | — | 每日（需 30 局） |
| 同 IP/设备聚类 | — | 每小时 |

### 4.2 实时检测（对局结束后触发）

挂点：`ProviderController::settle()` 之后（对局结果已定、钱包已改，且 `logPlay()` 已落行）。通过 EventBus 发 `anticheat.round_finished`，与既有 `AchievementService::handle` 并列——不阻塞结算主链路。

```php
// 挂在 EventConsumer::dispatch() 旁路，try/catch 隔离
EventBus::publish(['event' => 'anticheat.round_finished', 'payload' => $payload]);
```

实时检查器 `AntiCheatRealtime::check($round)` 只做 3 件事，全部 O(1) Redis：

```
INCR ac:rate:{user_id}:{HH:mm}   → 出招/对局速率（3.4-3）
SET  ac:last:{user_id} NX EX 5   → 间隔恒定检测（3.4-1）
GET  ac:payout:{user_id}:{round_id} → 赔付封顶（3.3）
```

不写 MySQL。命中才写 `game_anticheat_event`。

### 4.3 离线批量检测

webman `process/` 已有长驻进程范式（`EventConsumer`、`Monitor`）。新增 `AntiCheatWorker`，每小时一轮，按游标推进：

```
扫描 created_at > 上次游标 的 game_game_play_log 增量
  → 聚合到 game_anticheat_daily_stat（每用户每游戏一行，UPSERT）
  → 跑 3.1 / 3.2 / 3.5 三个维度
  → 命中写 game_anticheat_event + 更新 game_user_trust
  → 推进游标
```

**存储选型**：
- 优先补 ClickHouse 写入（0 节前置结论 1、2）：日增量小、扫描全表、多用户聚合，MySQL 上扫 30 天全量会明显拖慢线上库。
- 若 ClickHouse 接入排期靠后，先跑 MySQL + `game_anticheat_daily_stat` 汇总表：**汇总表是唯一让 MySQL 方案可行的关键**——每天只扫当日增量，不扫历史，把全表扫描变成扫一行汇总。这条路径可以立刻开工。

### 4.4 与 RiskService 的集成

`RiskRule` 表结构（`type` + `config` JSON + `action` + `priority` + `status`）完全够用，**不建新规则表**——把反作弊规则的 `type` 取 `anticheat_winrate` / `anticheat_bet_pattern` / `anticheat_duration` / `anticheat_rate` / `anticheat_device_cluster` 即可，直接进 `RiskRule::getEnabled()` 的调度。

`RiskService::check()` 保持不动（它是同步阻断路径，反作弊大部分是异步标记）。新增一个窄接口：

```php
class AntiCheatService
{
    /** 实时检测，返回是否需拒赔 */
    public static function onRoundFinished(int $userId, int $gameId, array $round): array;

    /** 离线检测入口，Worker 调用 */
    public static function runBatch(int $sinceId, int $limit): int;

    /** 读取信任分级 → 供提现/结算链路调用 */
    public static function trustBand(int $userId): string; // normal/observe/restrict/freeze
}
```

提现链路复用 H4 风控纵深的接入点：`trustBand()` 返回 `restrict`/`freeze` 时，提现走人工审核或阻断。**信任分是唯一写入的副作用，事件表只记流水。**

---

## 5. 数据模型变更

迁移落点：本节全部 SQL 属平台库（`install/install.sql` 追加，不改已发布段落）；管理后台侧无新表。

### 5.1 前置 DDL：修好现有表（不做这个，3.3/3.4/3.5 全废）

```sql
-- MySQL: IP/UA 从 metadata 提为列，加 device_id 与对局级时长
ALTER TABLE `game_game_play_log`
    ADD COLUMN `ip_address` VARCHAR(50) NOT NULL DEFAULT '' AFTER `server_id`,
    ADD COLUMN `user_agent` VARCHAR(500) NOT NULL DEFAULT '' AFTER `ip_address`,
    ADD COLUMN `device_id`  VARCHAR(64) NOT NULL DEFAULT '' AFTER `user_agent`,
    ADD COLUMN `ended_at_round` DATETIME NULL COMMENT '对局结束时间(区别于session级ended_at)',
    ADD COLUMN `level_id`   INT NULL COMMENT '关卡ID',
    ADD COLUMN `move_count` INT NULL COMMENT '出招次数',
    ADD COLUMN `result`     VARCHAR(10) NULL COMMENT 'win/fail',
    ADD KEY `idx_ip_created` (`ip_address`, `created_at`),
    ADD KEY `idx_device` (`device_id`),
    ADD KEY `idx_round` (`round_id`);

-- 清理：Phase 1 加的 bet_amount/win_amount 无人写入，真实值在 game_amount_change
-- 保留不动（避免迁移成本），文档标注为 dead column，不要在新代码里写它们
```

`ended_at` 命名冲突说明：现有 `ended_at` 是 session 级（`ProviderController` settle 时批量改），对局级新增 `ended_at_round`，不改原语义以免破坏 `GamePlayLogController` / `LeaderboardService` 的现有读取。

### 5.2 反作弊事件表

```sql
CREATE TABLE IF NOT EXISTS `game_anticheat_event` (
    `id`           BIGINT UNSIGNED NOT NULL,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `game_id`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `rule_type`    VARCHAR(30) NOT NULL COMMENT 'anticheat_winrate/anticheat_bet_pattern/anticheat_duration/anticheat_rate/anticheat_device_cluster',
    `rule_name`    VARCHAR(100) NOT NULL DEFAULT '',
    `severity`     TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=低 2=中 3=高',
    `score_delta`  INT NOT NULL DEFAULT 0 COMMENT '信任分变动(负数)',
    `action`       VARCHAR(20) NOT NULL DEFAULT 'warn' COMMENT 'log/warn/block',
    `evidence`     TEXT COMMENT '命中证据JSON: z值/分布/序列样例/集群账号ID',
    `round_id`     VARCHAR(64) NOT NULL DEFAULT '' COMMENT '关联对局,可为空',
    `status`       VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT 'open/confirmed/whitelisted/closed',
    `reviewer_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `review_note`  VARCHAR(500) NOT NULL DEFAULT '',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_created` (`user_id`, `created_at`),
    KEY `idx_status` (`status`),
    KEY `idx_rule` (`rule_type`),
    KEY `idx_game` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='反作弊事件表';
```

### 5.3 反作弊规则表

**推荐：不建。** 复用 `game_risk_rule`（`type` 取 `anticheat_*`，`config` 存阈值 JSON，`action` 已是 `log/warn/block`），零新增表、零新增调度代码。

若评审要求物理隔离（例如反作弊规则只给风控角色看、避免误改金额类规则），则建：

```sql
CREATE TABLE IF NOT EXISTS `game_anticheat_rule` (
    `id`         BIGINT UNSIGNED NOT NULL,
    `name`       VARCHAR(100) NOT NULL,
    `type`       VARCHAR(30) NOT NULL,
    `game_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=全游戏',
    `config`     TEXT NOT NULL COMMENT '阈值JSON,结构见第3节各维度',
    `action`     VARCHAR(20) NOT NULL DEFAULT 'warn' COMMENT 'log/warn/block',
    `score_delta` INT NOT NULL DEFAULT -10,
    `status`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_status_game` (`status`, `game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='反作弊规则表(可选,默认复用game_risk_rule)';
```

### 5.4 用户信任分表

```sql
CREATE TABLE IF NOT EXISTS `game_user_trust` (
    `id`            BIGINT UNSIGNED NOT NULL,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `score`         INT NOT NULL DEFAULT 100 COMMENT '0-100',
    `band`          VARCHAR(10) NOT NULL DEFAULT 'normal' COMMENT 'normal/observe/restrict/freeze',
    `hit_count`     INT NOT NULL DEFAULT 0 COMMENT '累计命中次数',
    `last_hit_at`   DATETIME NULL,
    `last_decay_at` DATETIME NULL COMMENT '最近一次自然回分时间',
    `whitelisted`   TINYINT NOT NULL DEFAULT 0 COMMENT '客服加白,冻结不自动触发',
    `whitelist_by`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user` (`user_id`),
    KEY `idx_band` (`band`),
    KEY `idx_score` (`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户信任分表';
```

### 5.5 离线汇总表（MySQL 路径的关键）

```sql
CREATE TABLE IF NOT EXISTS `game_anticheat_daily_stat` (
    `id`            BIGINT UNSIGNED NOT NULL,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `game_id`       BIGINT UNSIGNED NOT NULL,
    `stat_date`     DATE NOT NULL,
    `rounds`        INT NOT NULL DEFAULT 0,
    `wins`          INT NOT NULL DEFAULT 0,
    `bets`          DECIMAL(20,4) NOT NULL DEFAULT 0 COMMENT 'SUM(ABS(bet))',
    `avg_bet`       DECIMAL(20,4) NOT NULL DEFAULT 0,
    `std_bet`       DECIMAL(20,4) NOT NULL DEFAULT 0 COMMENT '标准差,固定注检测用',
    `wins_total`    DECIMAL(20,4) NOT NULL DEFAULT 0 COMMENT 'SUM(settle)',
    `plays_30d`     INT NOT NULL DEFAULT 0 COMMENT '滚动30天对局数',
    `wins_30d`      INT NOT NULL DEFAULT 0,
    `active_seconds` INT NOT NULL DEFAULT 0,
    `moves_per_sec_p50` DECIMAL(8,4) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_game_date` (`user_id`, `game_id`, `stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='反作弊日汇总(离线检测输入)';
```

`std_bet` 用 MySQL 5.7 的 `STDDEV_POP()`；低于 5.7 需手算 `SQRT(SUM(x²)/n - AVG(x)²)`。

---

## 6. 管理端 API

沿用现有 `service/config/route.php` 的 admin 分组风格，挂 `BaseController`，返回 `['list'=>[],'total'=>n,'page'=>n]`（`GamePlayLogController::list` 已确立的范式）。

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/admin/anticheat/events` | 事件列表，筛选 `status/rule_type/game_id/user_id/date`，分页 |
| GET | `/admin/anticheat/events/{id}` | 事件详情，含 `evidence` + 该用户近 30 天聚合 |
| POST | `/admin/anticheat/events/{id}/review` | 处置：`confirm`(确认作弊) / `whitelist`(加白) / `close` |
| GET | `/admin/anticheat/users` | 可疑用户查询，按 `trust_band != normal` 或命中数排序 |
| GET | `/admin/anticheat/users/{userId}` | 单用户画像：近 30 天胜率曲线、bet 序列样例、IP/设备关联账号 |
| GET | `/admin/anticheat/rules` | 规则列表（`game_risk_rule` 中 `type LIKE 'anticheat_%'`） |
| POST | `/admin/anticheat/rules` | 新增/改规则（`config` 走 JSON schema 校验，不合法 422） |
| PUT | `/admin/anticheat/trust/{userId}` | 手动调分：`score` 0-100 + `whitelisted` 开关，**必须记操作人** |
| POST | `/admin/anticheat/batch/run` | 手动触发离线检测（传入 `since_id`，管理员补跑用） |
| GET | `/admin/anticheat/dashboard` | 统计：今日命中数、按规则分布、置信度分布、加白率 |

权限：挂在现有 admin 中间件下，事件处置与调分两个写接口单独要求 `risk_admin` 权限位（复用 H4 风控的角色方案，不新造）。

**`PUT /admin/anticheat/trust/{userId}` 是唯一能直接改分数的接口，必须写审计**：操作人、前后值、理由。这是客服误操作和内部滥用的主要入口。

---

## 7. ClickHouse 查询设计

**前提**：先执行 5.1 的 ClickHouse DDL 补齐（当前 CH 表没有金额列，下面的查询在现有 DDL 上跑不通）。

```sql
ALTER TABLE game_game_play_log ADD COLUMN IF NOT EXISTS round_id String DEFAULT '',
    ADD COLUMN IF NOT EXISTS session_id String DEFAULT '',
    ADD COLUMN IF NOT EXISTS started_at DateTime DEFAULT toDateTime(0),
    ADD COLUMN IF NOT EXISTS ended_at_round DateTime DEFAULT toDateTime(0),
    ADD COLUMN IF NOT EXISTS device_id String DEFAULT '',
    ADD COLUMN IF NOT EXISTS result String DEFAULT '',
    ADD COLUMN IF NOT EXISTS level_id Int32 DEFAULT 0,
    ADD COLUMN IF NOT EXISTS move_count Int32 DEFAULT 0,
    ADD COLUMN IF NOT EXISTS bet_amount Float64 DEFAULT 0,
    ADD COLUMN IF NOT EXISTS win_amount Float64 DEFAULT 0;
-- 需要重建表(MergeTree ALTER 加列可以,但 ORDER BY 变了必须重建)
```

`PARTITION BY toYYYYMM(created_at) ORDER BY (user_id, created_at)` 的分区/排序设计对反作弊友好（单用户全量序列一次读完），保留不改。

### 7.1 胜率 Z-score（每日离线）

```sql
WITH per_user AS (
    SELECT user_id, game_id,
           countIf(result = 'win')  AS wins,
           count(*) AS rounds,
           (countIf(result = 'win') + 10.0) / (count() + 20.0) AS p_smooth
    FROM game_game_play_log
    WHERE created_at >= now() - INTERVAL 30 DAY
      AND round_id != '' AND result IN ('win', 'fail')
    GROUP BY user_id, game_id
    HAVING rounds >= 30
),
pop AS (
    SELECT game_id, avg(p_smooth) AS mu, stddevPop(p_smooth) AS sd,
           quantile(0.75)(p_smooth) AS q3, quantile(0.25)(p_smooth) AS q1
    FROM per_user GROUP BY game_id
)
SELECT u.user_id, u.game_id, u.p_smooth,
       (u.p_smooth - p.mu) / nullIf(p.sd, 0) AS z,
       u.p_smooth > (p.q3 + 1.5 * (p.q3 - p.q1)) AS iqr_hit
FROM per_user u JOIN pop p USING (game_id)
WHERE (u.p_smooth - p.mu) / nullIf(p.sd, 0) >= 3.0
   OR u.p_smooth > (p.q3 + 1.5 * (p.q3 - p.q1));
```

### 7.2 马丁格尔检测

```sql
-- 相邻对局: 上一局输 + 本局下注≈2倍 → 马丁格尔
SELECT user_id, game_id,
       countIf(prev_result = 'fail' AND abs(bet_amount - 2 * prev_bet) < 0.005) AS martin_hits,
       countIf(prev_result = 'fail') AS fail_count
FROM (
    SELECT user_id, game_id,
           bet_amount,
           lagInFrame(bet_amount, 1)  OVER w AS prev_bet,
           lagInFrame(result, 1)       OVER w AS prev_result,
           row_number() OVER w AS rn
    FROM game_game_play_log
    WHERE action = 'bet' AND created_at >= now() - INTERVAL 7 DAY
      AND round_id != ''
    WINDOW w AS (PARTITION BY user_id, game_id ORDER BY started_at)
) t WHERE rn > 1
GROUP BY user_id, game_id
HAVING fail_count >= 20
   AND martin_hits / fail_count > 0.6
ORDER BY martin_hits DESC;
```

固定注检测同构：`stddevPop(bet_amount) / nullIf(avg(bet_amount),0) < 0.02 HAVING count() >= 30`。

### 7.3 对局时长 IQR

```sql
WITH d AS (
    SELECT user_id, game_id,
           dateDiff('second', started_at, ended_at_round) AS dur
    FROM game_game_play_log
    WHERE action = 'settle' AND created_at >= now() - INTERVAL 30 DAY
      AND ended_at_round != toDateTime(0)
), g AS (
    SELECT game_id,
           quantile(0.25)(ln(dur + 1)) AS q1,
           quantile(0.75)(ln(dur + 1)) AS q3,
           quantile(0.99)(dur) AS p99
    FROM d GROUP BY game_id
)
SELECT dd.user_id, dd.game_id, dd.dur, dd.round_id
FROM (SELECT *, toUInt64(count()) OVER (PARTITION BY user_id, game_id) AS n FROM d) dd
JOIN g USING (game_id)
WHERE dd.dur <= 3
   OR ln(dd.dur + 1) < (g.q1 - 1.5 * (g.q3 - g.q1))
   OR ln(dd.dur + 1) > (g.q3 + 1.5 * (g.q3 - g.q1));
```

出招速率：`count() / nullIf(sum(dateDiff('second',started_at,ended_at_round)),0) > 4.0`，需 `move_count` 字段后改为 `sum(move_count)/sum(dur)`。

### 7.4 操作频率（间隔方差）

```sql
SELECT user_id, game_id,
       uniqExact(round_id) AS rounds,
       avg(gap) AS avg_gap,
       stddevPop(gap) / nullIf(avg(gap), 0) AS gap_cv
FROM (
    SELECT user_id, game_id, round_id,
           dateDiff('millisecond', started_at,
                    lagInFrame(started_at, 1) OVER w) AS gap,
           row_number() OVER w AS rn
    FROM game_game_play_log
    WHERE action = 'bet' AND created_at >= now() - INTERVAL 1 DAY
    WINDOW w AS (PARTITION BY user_id, game_id ORDER BY started_at)
) t WHERE rn > 1 AND gap > 0
GROUP BY user_id, game_id
HAVING rounds >= 50
   AND gap_cv < 0.05 AND avg_gap < 500;
```

### 7.5 同 IP / 同设备聚类

```sql
-- 同IP多账号 + 时间同步性
SELECT ip_address,
       groupUniqArray(user_id) AS accounts,
       uniqExact(user_id) AS account_count,
       sumIf(1, abs(sync_gap) < 1000) AS sync_pairs
FROM (
    SELECT user_id, ip_address, started_at,
           abs(dateDiff('millisecond', started_at,
               lagInFrame(started_at, 1) OVER w)) AS sync_gap,
           row_number() OVER w AS rn
    FROM game_game_play_log
    WHERE action = 'bet' AND created_at >= now() - INTERVAL 1 DAY
      AND ip_address != ''
    WINDOW w AS (PARTITION BY ip_address ORDER BY started_at)
) t WHERE rn > 1
GROUP BY ip_address
HAVING account_count >= 3
ORDER BY account_count DESC, sync_pairs DESC;
```

按设备聚类：同构把 `PARTITION BY ip_address` 换成 `PARTITION BY device_id`，`HAVING account_count >= 2`。

---

## 8. 验收标准

分三层，每层都有可执行的判定，不写"体验良好"这类不可测项。

### 8.1 单元测试（阈值逻辑正确性）

`AntiCheatDetector` 的每个维度各一个断言，注入构造数据：

| 用例 | 输入 | 期望 |
|---|---|---|
| 固定注命中 | 30 局，`bet=100` 恒定 | 命中 `anticheat_bet_pattern`，模板=`fixed` |
| 固定注不命中 | 30 局，bet 在 100±20% 均匀抖动 | 不命中（CV≈0.29 > 0.02） |
| 马丁格尔命中 | 10 输 10 赢，每次输后 2 倍 | 命中，命中率 1.0 |
| 胜率小样本抑制 | 新用户 3 连胜 | 不命中（`rounds < min_rounds`） |
| 胜率平滑不爆表 | 100 局全赢 | 命中 `z >= 3`，但 `p_smooth` 是 110/120 而非 1.0 |
| 时长短端 | `dur=2s` | 命中 `abs_min_seconds` |
| 出招速率 | 100 局，每局 3 出招 1 秒 | 命中 `max_moves_per_second` |
| 机器人间隔 | 5000 局，gap 全 480ms（CV≈0） | 命中 `anticheat_rate`，需 2 信号才 block |

### 8.2 端到端（模拟数据注入 → 被识别）

```
脚本注入 3 个测试账号（不进生产库，走 staging）:
  bot-A: 恒定 480ms 间隔 + 固定注 100 → 期望: rate 命中 + bet_pattern 命中, 信任分 ≤ 70
  cheater-B: 120 局 96 胜 (80%) + 全局 mu≈45% → 期望: winrate 命中 z>3
  cluster-C: 5 账号同 IP, 同秒下注 → 期望: device_cluster 命中, 4 个非主账号进冻结队列
判定: 3 账号全部出现在 game_anticheat_event 且 status=open
```

### 8.3 误报率（上线门槛）

前 2 周只 `log`/`warn`，不 block，统计：

```
误报率 = 客服标白数 / 总命中数
目标: ≤ 5%（首月可放宽到 10%）
每维度分别统计, 单维度 > 15% 则该维度阈值上调后重新观察
```

**放行条件**（全部满足才允许把 `warn` 升 `block`）：
1. 8.2 三个注入账号全部被识别；
2. 8.1 单元测试全绿；
3. 连续 7 天总误报率 ≤ 5% 且 `device_cluster` 维度误报率 ≤ 10%（该维度误伤成本最高）；
4. `AntiCheatWorker` 单轮耗时 < 300s（按当前日活量级），不阻塞次日数据。

### 8.4 幂等与可靠性

- 离线检测重跑同一区间不重复扣分：`game_anticheat_event` 加 `(user_id, rule_type, stat_date)` 唯一键，`INSERT ... ON DUPLICATE KEY UPDATE`。
- `AntiCheatWorker` 游标持久化，进程重启从上次游标续跑，不重扫。
- 实时检测 Redis 不可用时**降级放行**（`try/catch` 吞掉，只记 warning）——反作弊不能成为结算链路的故障源。

---

## 9. 风险与缓解

| 风险 | 影响 | 缓解 |
|---|---|---|
| **误报正常玩家** | 信任分扣错，影响提现/结算体验，客诉 | 三档灰度：先 `log` 只观察 2 周 → 再 `warn` → 满足 8.3 才 `block`。所有阈值走 `config` 热改，不用改代码。加白接口 + `whitelisted` 位让客服可一键豁免且不再自动触发 |
| **被作弊者规避** | 阈值被探测后绕过 | 阈值不在客户端暴露（检测全在服务端）。单维度不直接封号，必须多维叠加（3.2 的固定注 + 胜率、3.4 的需 2/3 信号）——规避单维度容易，规避叠加难。`evidence` 只存统计量不存完整序列，降低被反推阈值的风险 |
| **性能开销** | 拖慢结算或线上库 | 实时只做 3 个 O(1) Redis 操作，走 EventBus 旁路不阻塞结算事务；离线走独立游标扫描 + 汇总表，不扫历史全量；ClickHouse 未接入前先跑汇总表路径 |
| **数据质量** | 检测全废 | 见 0 节前置结论 3/4/5：IP 在 JSON、settle 行无环境信息、`bet_amount` 是死列。**5.1 的 ALTER 是所有维度的前置条件，必须在检测代码之前落地**，否则 3.3/3.4/3.5 三个维度直接不可用 |
| **消消乐胜率无信号** | 免费关卡型游戏检测能力缺失 | 本期只覆盖付费关卡型；`level_win`/`level_fail` 上报列入 P1（见第 10 节）。作弊最终要落到"服务端复算"（`game/xiaoxiaole/api.md` 第 7 节），统计方法只是前置 |
| **ClickHouse 长期不接入** | 第 7 节全部 SQL 用不上 | 汇总表路径可独立运行，第 7 节 SQL 作为 ClickHouse 接入后的替换品，不阻塞开发 |
| **风控角色滥用调分** | 内部舞弊 | `PUT /trust` 强制审计（操作人 + 前后值 + 理由）；`whitelisted` 同样审计；调分频率纳入 H4 的风控审计范围 |
| **`RiskService::velocity` 逻辑错误被复用** | 同 IP 检测完全失效且不自知 | 明确不复用该分支（0 节前置结论 6）；本方案的 IP 统计走独立查询。**建议顺手修掉 velocity 的 IP 过滤缺陷**，它现在统计的是全库用户数，任何调用方都被误导 |

---

## 10. 实施顺序

| 阶段 | 内容 | 前置 |
|---|---|---|
| P0 | 5.1 的 MySQL/ClickHouse DDL 补齐；`logPlay()` 补写 `ip`/`device_id`/`ended_at_round`/`result`/`move_count`；`GamePlayLogService::write()` 的 `round_id` 入 fillable | 无 |
| P1 | `game_anticheat_daily_stat` 汇总表 + `AntiCheatWorker`（先跑 3.2 下注模式，数据最全） | P0 |
| P1 | `game_anticheat_event` + `game_user_trust` + 管理端 4 个读接口 | P0 |
| P1 | 实时 3 项（操作频率、时长下限、赔付封顶）挂 EventBus | P0 |
| P2 | 胜率检测（**先补 `level_win`/`level_fail` 上报**，否则只覆盖付费关卡） | P1 |
| P2 | 同 IP/设备聚类 + 邀请链联动 | P0（依赖 `ip_address`/`device_id` 列） |
| P2 | 信任分级接入提现链路；灰度上线（log → warn → block） | P1 |
| P3 | 服务端复算（消消乐作弊的确定性判定） | 游戏侧上传操作序列 |
