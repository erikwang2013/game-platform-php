<!--
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * 不可修改、不可移除、不可逆。
 -->

# 低优先级（L）扩展方案 — 全球游戏聚合平台

- 日期：2026-08-31
- 范围：L1 支付网关扩充 / L2 管理端前端深化 / L3 本地化与合规 / L4 性能与可观测性
- 依据文件：`service/app/payment/`（GatewayFactory + 16 网关实现）、`service/app/process/Monitor.php`、`service/app/model/CountryConfig.php`、`service/app/model/Language.php`、`admin/app/admin/controller/{Report,Analytics,Dashboard,Metrics}Controller.php`、`admin/apps/flutter/lib/app/pages/`（18 个页面）

---

## 前置事实校正（影响全部 L 任务范围）

任务描述中三处与代码现状不符，先纠正，否则后续设计会建立在错误前提上：

| 描述 | 实际 | 证据 |
|------|------|------|
| 18 个支付网关 | **16 个网关**（+GatewayFactory +Interface = 18 个文件） | `service/app/payment/` 目录清单 |
| Monitor 进程 = 监控进程 | Monitor 是**热重载文件监视器**：每 2s 比对 monitorDir 下扩展名文件的 mtime 哈希，变化时 `posix_kill(SIGUSR1)` 通知父进程重载。无指标、无健康检查、无告警 | `service/app/process/Monitor.php:30-37` |
| 已有 ClickHouse 分析 | **应用代码中零 ClickHouse 依赖**（vendor 中仅有华为 GaussDB ClickHouse / 腾讯 Cdwch SDK 声明）。真实分析栈 = MySQL 实时聚合 + Redis 5min 缓存 + ES(webman-scout) | `grep -rn clickhouse service/app admin/app` 结果为空 |

另：`game_country_config` 与 `game_language` 表结构存在，但 `install/install.sql` 中**没有任何 INSERT 种子数据**——当前覆盖范围只由代码常量决定（见 L3）。

---

# L1 — 支付网关扩充

## 目标

从 16 个网关扩展到 21 个，覆盖 SEA 游戏市场与多渠道收单；同时补齐现有接口缺失的**退款**与**状态查询**能力（当前接口只有支付与回调两个方法，无法做对账）。

## 现状

### 接口定义（`PaymentGatewayInterface`）

```php
public function createPayment(DepositOrder $order, PaymentMethod $method): array;
// → array{checkout_url: string, transaction_id: string}
public function verifyCallback(Request $request): array;
// → array{valid, order_no, transaction_id, amount, status: success|failed|ignored}
```

只有两个方法。**没有 refund、没有 query、没有对账单下载**。退款与对账能力在所有 16 个网关上都不存在。

### 四种接入模式（已在现有实现中分化）

| 模式 | 代表网关 | 特征 |
|------|---------|------|
| 托管收银页 + 事件回调 | Stripe（Checkout Session）、MercadoPago（Checkout Pro） | 返回 checkout_url，webhook 事件驱动 |
| 两步确认（无 webhook） | KakaoPay（ready/approve）、M-Pesa（STK Push） | 无服务端推送，前端回传凭据或按 ID 反查订单 |
| OAuth 动态 token | MercadoPago、M-Pesa | client_credentials 换 token，每次调用前取 |
| 固定密钥签名 | MercadoPago webhook（`X-Signature: ts,v1`，300s 漂移容差）、Stripe（由控制器验签） | 验签在网关内或控制器内，实现不统一 |

### 值得注意的既有防坑实践（新增网关必须继承）

- Stripe 异步支付方式：`payment_status !== 'paid'` 一律返回 `ignored`，避免 completed 事件提前入账被后续 CAS 幂等丢弃（`StripeGateway.php:72`）。
- M-Pesa 无签名机制：fail-closed 靠 `CheckoutRequestID == order.transaction_id` + 金额比对，缺任一环节即拒绝（`MpesaGateway.php:106`）。
- M-Pesa 拒绝小数金额，直接抛异常而非静默截断（`MpesaGateway.php:47`）。
- 金额零小数币种（JPY/KRW）不放大 100 倍（`StripeGateway::toMinor`）。
- MercadoPago 回查失败返回 `failed` 让网关重试，既不确认也不丢弃（`MercadoPagoGateway.php:99`）。

### 覆盖缺口

| 区域 | 现状 | 缺口 |
|------|------|------|
| 东南亚 | Gcash（仅菲律宾） | 印尼、泰国、马来、越南、新加坡无覆盖 |
| 中国大陆 | 无 | 无 |
| 日韩 | PayPay（日）、KakaoPay/Toss（韩） | 已覆盖 |
| 拉美 | MercadoPago、AstroPay | 已覆盖 |
| 非洲 | M-Pesa（肯尼亚）、Paystack（尼日利亚） | 南非无覆盖 |
| 企业收单 | 无 | 无多渠道聚合与本地货币清算 |

## 方案设计

### 1.1 接口扩展：用可选接口，不破坏现有 16 个网关

不要在 `PaymentGatewayInterface` 上加方法——16 个实现会全部报错，且多数本地支付网关（M-Pesa STK Push、Paysafecard 预付码）根本不支持退款。

```php
// 新增两个可选接口，GatewayFactory 保持不变
interface RefundableGatewayInterface {
    public function refund(DepositOrder $order, string $amount, string $reason = ''): array;
    // → array{success: bool, refund_id: string}
}

interface QueryableGatewayInterface {
    public function query(DepositOrder $order): array;
    // → array{status: pending|confirmed|failed, amount: string, raw: array}
}
```

调用侧统一封装 `GatewayCapabilities`（`instanceof` 判断 + 能力表），控制器不感知具体网关。H3 对账模块只需依赖 `QueryableGatewayInterface` 即可工作。

### 1.2 推荐接入的 5 个网关（按游戏业务 ROI 排序）

| # | 网关 | 理由 | 优先级 |
|---|------|------|--------|
| 1 | **Codashop** | 游戏发行专用支付，1 次集成覆盖印尼/菲律宾/泰国/马来/越南/缅甸的新加坡/中国共 8 市场、100+ 本地支付方式（DANA/GoPay/OVO/ShopeePay/TrueMoney）。游戏行业专用结算，费率优于通用收单 | P0 |
| 2 | **Adyen** | 企业级多渠道收单，有游戏垂直方案（Adyen for Games）；唯一同时提供退款、void、查询、对账单下载完整能力的通用方案，直接补齐 1.1 的能力缺口 | P0 |
| 3 | **Antom（Alipay+ 商户侧）** | 中国 + 东南亚聚合，覆盖当前完全空白的中国大陆市场 | P1 |
| 4 | **GrabPay** | 新加坡/马来/泰国/菲律宾/印尼/越南钱包直连，与 Codashop 互补（Codashop 走分销结算，GrabPay 直连清算） | P1 |
| 5 | **Airwallex** | 多币种收款 + 跨境清算到银行账户；同时服务 L3 的提现侧（各国提现规则细化） | P2 |

排除项说明：PIX 已被 Stripe 的 `apm_types` 覆盖，不单独接；PayPal 已有；加密货币已有 NowPayments + CoinbaseCommerce 两个，无增量价值。

### 1.3 每个网关的接入要点

**通用四件事，缺一不可：**

1. **对账单**：优先用 API 拉取（Adyen Report API、Codashop settlement report），无法拉取的网关走 T+1 文件下载 + 落地表 `gateway_statement`，与 H3 对账模块共用同一张对账表。
2. **退款**：实现 `RefundableGatewayInterface`。Adyen 支持全额/部分/多次退款；Codashop 支持分销退款；M-Pesa/KakaoPay/Paysafecard 类**明确标注不支持**，能力表返回 false，前端隐藏退款入口。
3. **回调**：沿用现有 `verifyCallback` 的四种模式判定，签名校验策略必须二选一并写明——「网关内验签」或「控制器验签」，不混用（当前 Stripe 与 MercadoPago 就是混用状态，这是技术债）。
4. **金额单位**：零小数币种清单需从 Stripe 单点维护升级为共享常量 `CurrencyUtils::ZERO_DECIMAL`（JPY/KRW/VND/CLP/KRW 等），避免每个网关复制一份并漂移。

**Codashop 特有：** sandbox 与 production base URL 不同；回调无签名，靠 `transactionId` 反查订单（同 M-Pesa 的 fail-closed 模式）；退款需调用 distributor 侧而非商户侧接口。

**Adyen 特有：** 支持 `refunds` / `cancels` 独立资源；对账单需按 settlement 日期而非支付日期对齐；hmac 签名校验需按 Adyen 官方顺序拼接字段。

**Antom 特有：** 需通过 Antom 商户入驻（Antom 是 Ant Group 海外收单品牌，替代原 Alipay+ 商户侧），支付链路含 `createPaymentSession` + redirect + webhook 三段；中国大陆主体资质要求需法务确认后再排期。

**GrabPay 特有：** 各站点 API base URL 不同（grabpay.sg / .com.my / .co.th 等），按 `country_code` 路由，需扩展现有 `CountryConfig` 存 base URL。

**Airwallex 特有：** collection 与 payout 是两套 API，充值走 payment-link，提现走 payout；币种对支持矩阵需按 `country_config.currency` 白名单校验。

## 验收标准

- `GatewayFactory::resolve()` 支持 21 个 provider，未注册 provider 抛 `InvalidArgumentException`（行为不变）。
- 新增网关各提供单元测试，覆盖：金额转换、回调成功、回调拒绝（非法签名/金额不符/订单不存在）、不支持退款的能力声明。
- `CurrencyUtils::ZERO_DECIMAL` 被 Stripe 与所有新增网关共用，Stripe 单测通过（证明重构无回归）。
- 已实现 `RefundableGatewayInterface` 的网关，退款接口返回 `refund_id` 且二次调用幂等。
- 未实现该接口的网关，能力表返回 false，前端退款入口不渲染。
- 对账单落地表 `gateway_statement` 与 H3 对账模块的数据契约一致（不重复建表）。

---

# L2 — 管理端前端深化

## 目标

在已有 18 个 Flutter Web 页面与 HarmonyOS 端的基础上，把管理端从「能看数据」提升到「能在大屏值守、能高频操作」。纯前端改造，后端接口基本不动。

## 现状

- Flutter Web 页面（`admin/apps/flutter/lib/app/pages/`）：achievement、announcement、cdn、config、dashboard、game、identity、log、login、payment、platform_user、profile、report、risk、role、user、vip、withdraw 共 18 个。
- HarmonyOS 端：`admin/apps/harmonyos/`，Token 无感刷新已实现。
- 报表已有真实数据：`ReportController`（summary/daily/export，Redis 5min 缓存，MAX_DAYS=90）、`AnalyticsController`（12 个端点：overview/gameRanking/dauTrend/hourlyTrend/actionDistribution/revenue/conversion/probability/retention/funnel/arpu/economy）、`DashboardController`（index/platform）。
- 布局：PC 管理后台风格（侧边栏 + 顶栏 + 内容区），GetX 状态管理，`ApiService` 单例（Dio + JWT 拦截器）。
- 响应式断点：移动端 <768px / 桌面端 ≥768px。

## 方案设计

### 2.1 报表交互增强

| 改动 | 说明 |
|------|------|
| 日期快捷预设 | 今天 / 昨天 / 最近 7 天 / 最近 30 天 / 本自然月 / 自定义。当前前端需手填 `start`/`end` |
| 环比标注 | summary 与 daily 端点返回上一等长周期对比值，前端显示 ↑↓% |
| 图表联动下钻 | daily 折线点击某日 → 定位到该日的 user/payment 明细页 |
| 导出增强 | 现有 CSV 导出无 BOM 问题已解决，补 Excel（xlsx）格式分支；`format` 参数后端已预留 |
| 90 天上限前置校验 | 后端 `normalizeDateRange` 已返回 400，前端应在选择器处直接限制，不发无效请求 |

**注意：** 环比需要后端加一个端点或让 summary 返回双周期数据。这是本任务中唯一的后端改动，工作量约 1 个端点。

### 2.2 大屏模式

新增 `dashboard/bigscreen` 页面，目标场景是运营机房/办公室大屏值守：

- 无侧边栏、无顶栏、全屏、自动轮播（每 15s 切换 dashboard/index、dashboard/platform、analytics/revenue、analytics/retention 四块）。
- 字体与卡片按 4K 屏幕比例放大，间距用相对单位。
- 数据源复用现有 4 个端点，不新增后端接口。
- 键盘 `Esc` 退出，`F` 切换浏览器全屏（`Fullscreen API`，Flutter Web 用 `window_manager` 或直接 JS interop）。
- 轮播状态存内存，不持久化——YAGNI，页面关闭即重置。

### 2.3 操作效率优化

| 改动 | 说明 |
|------|------|
| 命令面板 | `Ctrl+K` 唤起，输入路由名/功能名直达页面。18 个页面的导航深度是当前最大效率瓶颈 |
| 全局键盘快捷键 | `Alt+1..9` 切换一级菜单，`Alt+L` 日志页，`Alt+U` 用户页 |
| 批量操作标准化 | user/withdraw 已有批量，补齐 risk/achievement 的批量启停与批量指派 |
| 筛选条件持久化 | 各列表页的筛选条件存 `shared_preferences`，刷新后恢复 |
| 列表页列宽记忆 | 同上，避免每次拖拽重来 |

### 2.4 HarmonyOS 端

仅同步 2.3 的批量操作与 2.1 的日期预设；**不做 2.2 大屏模式**——HarmonyOS 端定位是移动端运维，不是值守大屏，YAGNI。

## 验收标准

- `flutter analyze` 零 error 零 warning（CI 已包含该步骤）。
- 大屏模式在 3840x2160 下无滚动条、无文字溢出、四屏轮播无白屏。
- 命令面板输入 2 个字符内出候选，18 个页面均可搜到。
- 报表页任意日期选择超限（>90 天）在 UI 层被拦截，不发 HTTP 请求。
- HarmonyOS 端功能与 Flutter Web 端的功能差异仅大屏模式一项，且在文档中记录。

---

# L3 — 本地化与合规扩充

## 目标

把国家配置从「7 个硬编码映射」扩展为可运营的数据表，补上 KYC/AML 合规的数据模型与策略钩子。

## 现状

### CountryConfig（`service/app/model/CountryConfig.php`）

字段：`country_code`（ISO 3166-1 alpha-2）、`currency`、`payment_methods`（JSON 数组）、`withdraw_methods`（JSON 数组）、`min_deposit`、`status`。

覆盖范围由代码常量决定，只有 7 个国家：

```php
// CountryConfig::fromLang()
$map = ['zh' => 'CN', 'ja' => 'JP', 'ko' => 'KR', 'pt' => 'BR', 'hi' => 'IN', 'de' => 'DE', 'en' => 'US'];
```

即：**CN、JP、KR、BR、IN、DE、US**。未知语言返回空串（fallback 到全局默认）。

表结构存在但 `install/install.sql` **无任何 INSERT 种子数据**。

### Language（`service/app/model/Language.php`）

字段：`code`（en-US 格式）、`name`、`native_name`、`icon`（国旗代码）、`status`、`sort`。同样表结构存在、无种子数据。

### 缺口

- 无 KYC 字段：没有身份证/护照、地址证明、面部核验、年龄门槛等任何合规字段。
- 无 AML 字段：没有交易限额、可疑交易标记、地理制裁名单。
- `CountryConfig` 无版本控制，改配置无法回滚。
- `min_deposit` 是单一数值，无按币种/按支付方式的差异化。
- 语言与国家是隐式关联（`fromLang` 硬编码），无正式外键关系。

## 方案设计

### 3.1 国家地区扩展

新增 13 个国家种子数据（对应 1.2 推荐网关的市场 + 现有网关覆盖的市场）：

```
SG MY TH ID VN PH  — SEA（Codashop / GrabPay / Gcash / Paystack）
CN                  — 中国（Antom）
ZA NG               — 非洲（Paystack 尼日利亚已有，ZA 待补）
AR CL               — 拉美补点（MercadoPago 覆盖）
```

种子数据以 migration SQL 落地（沿用 `install/migrations/` 模式，参考 `2026_08_29_multi_payment.sql`）。

同时把 `fromLang()` 的硬编码映射迁移为 `CountryConfig` 表的 `lang_prefix` 字段，代码只做查表。这是本任务唯一需要动的代码。

### 3.2 各国支付/提现规则细化

`CountryConfig` 现有 `payment_methods` / `withdraw_methods` 是 JSON 数组（只存标识），扩展为带规则的 JSON：

```json
{
  "stripe":  {"enabled": true,  "min": "10", "max": "5000", "fee_percent": "2.9"},
  "gcash":   {"enabled": true,  "min": "100", "max": "50000", "fee_percent": "1.5"}
}
```

新增字段：`max_deposit`、`daily_deposit_limit`、`withdraw_fee_percent`、`withdraw_min`、`settlement_days`（T+N）。

**不做的：** 费率硬编码在配置里会漂移，建议只存 `fee_percent` 作为对账参考值，真实费率以网关侧为准（与 H3 对账模块保持一致，避免双数据源）。

### 3.3 KYC/AML 合规策略

新增三张表，纯数据模型 + 策略钩子，**不做任何合规判定逻辑的实现**：

```sql
-- KYC 等级定义
game_kyc_level (id, level_no, required_documents JSON, age_min, auto_approve, review_by)

-- 用户 KYC 记录
game_user_kyc (id, user_id, level_id, status enum('pending','approved','rejected'),
                documents JSON, reviewer_id, reviewed_at, country_code, created_at)

-- AML 规则与命中记录
game_aml_rule (id, name, country_code, daily_limit, single_limit, velocity_window_seconds, velocity_limit, status)
game_aml_hit  (id, rule_id, user_id, amount, country_code, status enum('pending','cleared','escalated'), created_at)
```

落地方式：在充值创建与提现申请处各插一个**策略钩子**（`ComplianceCheckService::beforeDeposit()` / `beforeWithdraw()`），默认 no-op 由配置开关控制，避免影响现有流程。规则判定逻辑是法务定义项，本文档只交付数据模型与挂载点。

**明确不做：** 制裁名单实时查询（需外部 API 授权）、年龄核验自动化（需第三方 OCR/人脸服务，成本独立评估）、KYC 材料内容审核。这些依赖外部供应商与法务结论，属于独立立项范围。

### 3.4 语言扩展

`game_language` 补种子数据（对应 13 国 + 现有 7 国的语言码），并新增 `country_code` 字段建立正式关联，废弃 `fromLang()` 硬编码。

## 验收标准

- `CountryConfig` 查表可返回 20 个国家（7 原有 + 13 新增），`fromLang()` 查表结果与迁移前的硬编码映射完全一致（单测断言，证明无回归）。
- `install/migrations/` 新增 migration 可在空库上重复执行不报错。
- 任意国家的 `payment_methods` JSON 中每个方法名都能被 `GatewayFactory::resolve()` 解析（防止配置了不存在的网关）。
- 合规钩子默认关闭时，充值与提现流程的行为与改造前完全一致（现有充值/提现单测全部通过）。
- `game_user_kyc` 与 `game_aml_hit` 表结构与文档描述一致，字段注释完整。

---

# L4 — 性能与可观测性

## 目标

把「热重载监视器 + MySQL 实时聚合 + 5 个 Prometheus gauge」补成真正的可观测性：补齐监控与告警，评估 ClickHouse 迁移收益，加入链路追踪。

## 现状（先纠正认知）

| 组件 | 实际作用 | 位置 |
|------|---------|------|
| `Monitor` 进程 | **热重载监视器**，非监控。每 2s 扫描 monitorDir 下指定扩展名文件的 mtime，md5 拼接比对，变化时 `posix_kill(SIGUSR1)` 通知父进程重载代码 | `service/app/process/Monitor.php` |
| ClickHouse | **不存在**。vendor 中有华为 GaussDB ClickHouse / 腾讯 Cdwch SDK 声明，但应用代码零引用 | `grep` 验证为空 |
| ES | 真实可用，`webman-scout` 驱动，`admin/config/scout.php` 与 service 侧 plugin 配置齐全 | `admin/config/scout.php` |
| 分析查询 | **MySQL 实时聚合**。`ReportController::dailyRows()` 单次请求对 5 张表分别 `GROUP BY DATE(created_at)`；`AnalyticsController` 12 个端点同模式 | `admin/app/admin/controller/` |
| 缓存 | Redis 5 分钟缓存，`report:summary:*` / `report:daily:*`，Redis 不可用时静默降级直查 | `ReportController.php:46-72` |
| Prometheus | `/metrics` 端点，仅 5 个 gauge：请求总数、活跃用户、DB 连接状态、Redis 连接状态、内存使用 | `MetricsController.php` |
| 链路追踪 | 无 | — |
| 告警 | 无 | — |

## 方案设计

### 4.1 ClickHouse 查询优化 — 先证明再迁移

**不迁移 ClickHouse 的理由（当前）：** `dailyRows()` 单请求 5 个 `GROUP BY DATE()`，MySQL 在 `created_at` 有索引且 90 天窗口下（MAX_DAYS 已硬限）是可接受的；而引入 ClickHouse 意味着写入链路改造（双写或 binlog 同步）+ 新依赖 + 新运维面。收益未证明前不付成本。

**先做的：** 给 `ReportController` 与 `AnalyticsController` 的关键聚合查询加 `EXPLAIN` 慢查询采集（落 MySQL 慢查询日志或 Redis 计数），跑出真实 P95。

**迁移触发条件（满足任一再动手）：**
- 报表端点 P95 > 1s（当前 Redis 5min 缓存命中率低时）
- 单表行数 > 5000 万
- 需要超过 90 天的历史窗口（当前 MAX_DAYS 硬限 90）
- 需要明细级下钻（analytics 12 端点当前只能聚合）

**迁移路径（到时按此执行）：** 保留 MySQL 为交易系统，聚合表通过 binlog 同步（Canal / Debezium）写入 ClickHouse，`ReportController` 按开关切换数据源，先双跑对账再切读。

### 4.2 监控大盘

现有 5 个 gauge 远不足以值守。补齐为三组：

| 组 | 指标 | 类型 |
|----|------|------|
| 业务 | 支付成功率、支付回调延迟、提现处理量、对账差异笔数、KYC 通过率 | counter / histogram |
| 系统 | Workerman worker 存活数、CPU、内存、RSS、句柄数、事件循环延迟 | gauge |
| 基础设施 | MySQL 活跃连接、慢查询数、Redis 命中率、ES 查询延迟、队列积压 | gauge / histogram |

实现方式：扩展 `MetricsController`（当前仅 5 gauge），或独立进程写 `/metrics`。Grafana 面板随 Prometheus 标准暴露方式接入，不自建前端大盘——YAGNI。

**新增一个 `Health` 进程**（区别于 `Monitor`）：每分钟探活 MySQL/Redis/ES，失败写入指标与日志。`Monitor` 保持现状只做热重载，命名不动以免动到既有部署配置。

### 4.3 告警规则

基于 4.2 的指标，Prometheus Alertmanager 规则起步 5 条：

```
1. 支付回调延迟 P95 > 5s 持续 10m       → P1（影响入账）
2. DB / Redis 连接状态 = 0 持续 1m       → P1（系统不可用）
3. 对账差异笔数 > 0 持续 30m             → P2（资金风险，联动 H3）
4. worker 存活数 < 预期值 持续 5m         → P1（进程掉线）
5. 队列积压 > 1000 持续 10m              → P2（提现/KYC 延迟）
```

告警通道走现有通知能力（`service/app/api/v1/controller/NotificationController.php` 已有通知基础设施），不新建告警通道。

### 4.4 链路追踪

现状无任何 tracing。起步方案：

- 中间件层生成 `trace_id`（雪花或 UUIDv7），写响应头 `X-Trace-Id`，透传到日志字段。
- 支付回调链路（`PaymentController::callback` → `GatewayFactory::resolve()` → `verifyCallback()` → 入账）是最高价值链路，单独打点。
- OpenTelemetry 导出到 Jaeger/Tempo 属于 P2，需要时再加——起步只需要 trace_id 能在日志中串起来，这是「可定位」的最低可用状态。

**不做：** 全链路 APM 商业方案、RUM（前端体验监控）——独立立项。

## 验收标准

- `MetricsController` 暴露的指标数 ≥ 20，且包含 4.2 三组中的每项。
- 新增 `Health` 进程在 MySQL/Redis/ES 任一不可用时，1 分钟内指标归零并写入错误日志；`Monitor` 进程行为不变（热重载功能回归测试通过）。
- Alertmanager 5 条规则语法校验通过（`amtool check-config`），每条规则能人工触发一次。
- 任意支付回调请求响应头含 `X-Trace-Id`，且该 id 能在日志中检索到完整链路（createPayment → verifyCallback → 入账）。
- 报表端点 P95 基线数据被采集（EXPLAIN 或慢查询日志），作为后续是否迁移 ClickHouse 的决策依据——本任务不要求迁移完成，只要求有数据。

---

## 四个子任务的依赖关系

```
L1 网关扩充 ──┬──> 需 1.1 的 Refundable/Queryable 接口 → 供 H3 对账使用
              └──> 依赖 L3 的 CountryConfig（各国 base URL / 币种白名单）

L3 本地化合规 ──> 独立，可先做（L1 的 Antom/Codashop 反而依赖 L3 的国家数据）

L2 前端深化 ──> 依赖 ReportController 环比端点（1 个后端改动）

L4 可观测性 ──> 4.2/4.3 与 H3 对账模块联动（告警规则 3）
```

**建议执行顺序：L3 → L1 → L4 → L2。** L3 数据先行（L1 网关落地需要国家/币种配置），L1 接口补齐后 L4 的对账告警才有意义，L2 纯前端独立收尾。
