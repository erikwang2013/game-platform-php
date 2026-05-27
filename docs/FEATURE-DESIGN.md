# 功能设计文档

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 币种体系设计

### 1.1 三层币种模型

```
第1层: 法币 (USD / CNY / EUR / JPY ...)
       ↕ 充值/提现（按汇率兑换）
第2层: 平台币（统一，精度 decimal(18,4)）
       ↕ 兑换（含汇率 + 平台抽成差价）
第3层: 游戏币（每种游戏独立，独立汇率）
```

### 1.2 平台币

- 平台内统一计价单位
- 精度：`DECIMAL(18,4)`，最小单位 0.0001
- 通过法币充值获得，可兑换为任意游戏币
- 游戏币也可兑回平台币，再提现为法币
- 平台收取兑换差价作为收入来源

### 1.3 游戏币

- 每种游戏可有多个游戏币种（如金币、钻石、积分）
- 每个币种独立设置对平台币的兑换汇率 (`exchange_rate`)
- 每个币种独立设置平台抽成比例 (`spread_pct`)
- 支持设置最小/最大兑换限额 (`min_exchange` / `max_exchange`)

### 1.4 兑换公式

**买入游戏币（平台币 → 游戏币）：**
```
游戏币到账 = 平台币数量 × exchange_rate × (1 - spread_pct / 100)
```

**卖出游戏币（游戏币 → 平台币）：**
```
平台币到账 = 游戏币数量 ÷ exchange_rate × (1 - spread_pct / 100)
```

**示例：**
- exchange_rate = 100（1平台币 = 100游戏币）
- spread_pct = 5%（平台抽5%差价）
- 用户用 10 平台币买入：(10 × 100 × 0.95) = 950 游戏币
- 用户卖出 950 游戏币：(950 ÷ 100 × 0.95) = 9.025 平台币
- 平台收益：10 - 9.025 = 0.975 平台币

## 2. 钱包设计

### 2.1 平台币钱包 (erik_user_wallet)

用户注册时自动创建，余额初始为 0。

| 字段 | 说明 |
|------|------|
| balance | 可用余额（可充提可兑换） |
| frozen_balance | 冻结余额（预留，如提现中） |
| total_earned | 累计收入 |
| total_spent | 累计支出 |
| version | 乐观锁版本号（每次更新+1） |

### 2.2 游戏币钱包 (erik_user_game_wallet)

按用户+游戏+币种三维唯一。首次兑换时自动创建。

### 2.3 并发安全

使用乐观锁防止并发问题：

```php
// 更新时检查版本号
$updated = Wallet::where('id', $wallet->id)
    ->where('version', $wallet->version)
    ->update([
        'balance' => bcadd($wallet->balance, $amount, 4),
        'version' => $wallet->version + 1,
    ]);

// 更新失败（版本号已变）→ 重试，最多5次
```

## 3. 提现系统设计

### 3.1 多层控制

```
第1层: 全局提现开关
       ├─ 关闭 → 所有提现拒绝，用于紧急风控
       └─ 开启 → 进入第2层检查

第2层: 限额检查
       ├─ 单笔最低金额 (min_amount)
       ├─ 单笔最高金额 (max_amount)
       └─ 每日累计限额 (daily_limit)

第3层: 审核流程
       ├─ 金额 < 自动审核阈值 → 自动通过
       └─ 金额 >= 自动审核阈值 → 人工审核 → 通过/拒绝
```

### 3.2 提现状态机

```
pending (待审核)
  ├─→ approved (已通过) → completed (已完成)
  └─→ rejected (已拒绝) → 余额退回 + 退款流水
```

### 3.3 管理后台控制

- **全局开关按钮**：一键开启/关闭所有用户提现
- **审核队列**：按时间排序的待审核列表，通过/拒绝按钮
- **限额配置**：可视化设置各限额参数

## 4. 充值设计

### 4.1 充值流程

```
1. 用户选择支付方式和金额
2. 平台创建充值订单 (status=pending, 生成唯一 order_no)
3. 跳转第三方支付页面
4. 用户完成支付
5. 第三方回调通知平台 (POST /api/payment/callback)
6. 平台验签 → 更新订单 (status=confirmed)
7. 平台币到账 → 记录流水
```

### 4.2 支付方式

| 类型 | 提供商 | 说明 |
|------|--------|------|
| 法币 | Stripe | 国际信用卡支付 |
| 法币 | PayPal | 全球电子钱包 |
| 法币 | Alipay | 支付宝（中国大陆） |
| 法币 | WeChat Pay | 微信支付（中国大陆） |
| 加密货币 | USDT-TRC20 | 波场链 USDT |

基础版先对接单一支付方式（如 Stripe），标准版扩展全部渠道。

## 5. 游戏集成设计

### 5.1 自研游戏

自研游戏直接集成到平台，共享用户体系和钱包：

- 游戏通过内部 API 查询用户游戏币余额
- 游戏结算通过内部 API 扣减/增加游戏币
- 无需额外的签名验签

### 5.2 第三方游戏

第三方游戏通过 SDK/API 对接：

```
平台侧:
  1. 用户点击"进入游戏"
  2. 平台生成签名（user_id + timestamp + api_secret → HMAC-SHA256）
  3. 302跳转或iframe加载游戏URL（携带签名参数）

游戏侧:
  4. 验签 → 建立游戏会话
  5. 查询余额：GET /api/game/balance?user_id=...&sign=...
  6. 结算回调：POST /api/game/callback {user_id, amount, type, sign}
  7. 平台验签 → 更新余额 → 记录流水 → 返回结果
```

### 5.3 签名算法

```
signature = HMAC-SHA256(
    secret = api_secret,
    message = user_id + "|" + timestamp + "|" + amount + "|" + nonce,
)
```

验证条件：
- 签名正确
- 时间戳在 ±60s 内（防 replay attack）
- nonce 未使用过（Redis 记录，60s 过期）
- 请求 IP 在白名单内

## 6. 权限设计

### 6.1 角色预设

| 角色 | 权限范围 |
|------|---------|
| 超级管理员 | * (所有权限) |
| 游戏运营 | 游戏管理、公告管理、仪表盘 |
| 财务审核 | 提现审核、支付管理、流水查看 |
| 客服 | C端用户查看、充值订单查看 |

### 6.2 权限粒度

```
{method}.{path}

示例:
  get.admin/game/list      → 查看游戏列表
  post.admin/game/create   → 创建游戏
  put.admin/withdraw/review → 审核提现
  put.admin/withdraw/switch → 操作提现开关（仅超级管理员）
```

## 7. 国际化设计

### 7.1 支持语言

| 代码 | 名称 | 本地语 | 图标 |
|------|------|--------|------|
| en-US | English | English | us |
| zh-CN | Chinese (Simplified) | 简体中文 | cn |
| ja-JP | Japanese | 日本語 | jp |
| ko-KR | Korean | 한국어 | kr |

### 7.2 翻译管理

- 翻译以 `group.key` 格式组织（如 `auth.login_success`）
- 数据库表 `erik_translation` 存储，Redis 缓存（TTL 1小时）
- API: `GET /api/language/list` 获取可用语言，`POST /api/language/switch` 切换语言
- 前端通过 `X-Language` 请求头或 `Accept-Language` 自动检测
- 翻译缺失时回退到 en-US，en-US 也无则返回原始 key

### 7.3 用户语言偏好

- 用户注册时根据浏览器 `Accept-Language` 自动设置
- 登录后可通过 `PUT /api/user/profile` 修改 `language` 字段
- 切换语言时同步更新用户记录

## 8. 平台收益模型

| 收益来源 | 计算方式 | 说明 |
|---------|---------|------|
| 兑换差价 | 每笔兑换的 spread_fee | 买入卖出双向收取 |
| 提现手续费 | 提现金额 × fee_pct | 标准版实现 |
| 游戏分成 | 第三方游戏收入分成 | 按合同约定 |
| 充值汇差 | 法币→平台币汇率差 | 平台设定汇率与市场汇率差值 |

## 9. 数据分析设计

### 9.1 ClickHouse OLAP 分析

平台接入 ClickHouse 列式数据库，支撑以下分析场景：

| 场景 | 计算方式 | 示例 |
|------|---------|------|
| 联合概率 | P(A ∩ B) = countIf(A ∧ B) / count(*) | 同时玩过两个游戏的用户占比 |
| 条件概率 | P(A \| B) = countIf(A ∧ B) / countIf(B) | 玩过游戏A后充值的概率 |
| 行为统计 | 聚合函数 countIf/sumIf/avgIf | 按国家/语言/游戏维度的行为分布 |

### 9.2 ProbabilityService

位于 `common/service/ProbabilityService.php`，提供：

```php
// 联合概率
ProbabilityService::joint(
    ['table' => 'erik_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 5]],
    ['table' => 'erik_deposit_orders', 'alias' => 'user_id', 'where' => ['status' => 'paid']],
);

// 条件概率 P(A | B)
ProbabilityService::conditional(
    ['table' => 'erik_deposit_orders', 'alias' => 'user_id', 'where' => ['status' => 'paid']],
    ['table' => 'erik_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => 5]],
);
```

内部使用 `erikwang2013/clickhouse-php` 包，通过 HTTP 接口执行 ClickHouse 原生 SQL。

### 9.3 GamePlayLogService 双写

位于 `common/service/GamePlayLogService.php`，提供行为日志的 MySQL + ClickHouse 双写：

```php
// 单条写入
GamePlayLogService::write(
    userId: $userId,
    gameId: $gameId,
    action: 'launch',
    detail: ['version' => '1.2.3'],
    ipAddress: $request->getRealIp(),
    userAgent: $request->header('User-Agent'),
);

// 批量写入
GamePlayLogService::writeBatch([
    ['user_id' => 1, 'game_id' => 5, 'action' => 'spin', 'detail' => ['result' => 'win']],
    ['user_id' => 1, 'game_id' => 5, 'action' => 'bet', 'detail' => ['amount' => 100]],
]);
```

ClickHouse 表 `erik_game_play_log` 使用 MergeTree 引擎按月分区，附带物化视图 `erik_game_play_log_hourly` 按小时聚合。

### 9.4 游戏推荐引擎 (RecommendService)

位于 `common/service/RecommendService.php`，基于协同过滤：

| 方法 | 功能 | 算法 |
|------|------|------|
| `alsoPlayed(gameId)` | 玩过X的人也玩了Y | P(Y\|X) 条件概率 |
| `trending(hours)` | 热门游戏排行 | 近期活跃玩家数 |
| `forUser(userId)` | 个性化推荐 | 相似用户协同过滤 |
| `gameAffinity(A, B)` | 游戏关联度 | 联合概率 P(A∩B) |

### 9.5 增强风控 (RiskClickHouseService)

位于 `common/service/RiskClickHouseService.php`：

| 方法 | 检测内容 |
|------|---------|
| `detectHighFrequency()` | 5分钟内操作超阈值 |
| `detectMultiAccount()` | 同IP多账号关联 |
| `detectIpHopping()` | 短时间多IP切换 |
| `assessUser(userId)` | 综合风险评分 0-100 |

### 9.6 智能优惠券 (SmartCouponService)

`common/service/SmartCouponService.php` — 流失检测 + 挽留建议：
- 7/14/30天不活跃自动分级（medium/high/critical）
- 按阶梯建议面额（$5/$10/$20）
- 游戏参与度排行榜辅助营销决策

### 9.7 数据看板 (GameDashboardService / RateLimitDashboardService)

- `GameDashboardService`：概览指标、行为分布、时段趋势、DAU趋势、游戏排行榜
- `RateLimitDashboardService`：IP 请求分布、按小时趋势、action 分布、可疑来源识别

### 9.8 用户画像 (UserProfileService)

`common/service/UserProfileService.php` — 5维度标签体系：
- 活跃度：daily_active / weekly_active / casual / dormant
- 游戏偏好：explorer / multi_game / focused
- 行为密度：hardcore / regular / light
- IP稳定性：stable_ip / normal_ip / roaming
- 时段偏好：night_owl / morning_player / afternoon_player / evening_player

### 9.9 A/B 实验 (AbTestService)

`common/service/AbTestService.php` — 实验框架：
- `assign()`: 哈希分桶（crc32），支持自定义变体权重
- `report()`: 各变体核心指标对比
- `comparePeriods()`: 前后时期行为变化分析

### 9.10 充值双写 (DepositLogService)

`common/service/DepositLogService.php` — 充值订单 + 交易流水入 ClickHouse：
- `logDeposit()`: 充值订单写入 ClickHouse
- `logTransaction()`: 交易流水写入 ClickHouse
- `revenueOverview()`: 收入概览（总额/笔数/均额）
- `conversionByGame()`: 按游戏的充值转化率 P(充值\|玩过X)

需在 ClickHouse 先建表 `erik_deposit_log` 和 `erik_transaction_log`。

### 9.11 留存分析 (RetentionService)

`common/service/RetentionService.php` — D1/D7/D30 队列留存：
- `cohortRetention()`: 按首次行为日期队列，跟踪 D1/D7/D30 留存率
- `retentionByGame()`: 按游戏对比留存（≥10用户才纳入）
- `retentionByRegion()`: 按 IP 地域前缀对比
- `churnRate()`: 整体流失率

### 9.12 反作弊 (AntiCheatService)

`common/service/AntiCheatService.php` — 时序异常检测：
- `detectBotPattern()`: 操作间隔规律检测（<1s=90分脚本嫌疑）
- `detect24HourActivity()`: 24h中≥18h活跃（挂机/共用）
- `detectDensityAnomaly()`: 10分钟内操作≥100次
- `detectAccountFarming()`: 同IP短时间大量账号切换
- `assessUser()`: 综合反作弊评分 0-100

### 9.13 实时推送 (WebSocketService)

`common/service/WebSocketService.php` — webman WebSocket :8789：
- `pushLeaderboard()`: 实时游戏排行榜（1h活跃玩家数）
- `pushRiskAlert()`: 风控告警推送（高频/多账号/IP跳变）
- `pushGameEvents()`: 1分钟内游戏事件流
- `pushOverview()`: 概览快照（DAU/活跃游戏/操作量）

客户端连接: `ws://localhost:8789`，服务端配置 `config/process.php`。
