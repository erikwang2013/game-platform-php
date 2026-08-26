# Architecture Design Document
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE-DESIGN.md) · **English** · [한국어](ARCHITECTURE-DESIGN.ko.md) · [Русский](ARCHITECTURE-DESIGN.ru.md) · [Deutsch](ARCHITECTURE-DESIGN.de.md) · [Français](ARCHITECTURE-DESIGN.fr.md) · [Español](ARCHITECTURE-DESIGN.es.md) · [Português](ARCHITECTURE-DESIGN.pt.md) · [हिन्दी](ARCHITECTURE-DESIGN.hi.md) · [العربية](ARCHITECTURE-DESIGN.ar.md) · [বাংলা](ARCHITECTURE-DESIGN.bn.md) · [Bahasa Indonesia](ARCHITECTURE-DESIGN.id.md) · [日本語](ARCHITECTURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Design Goals

Build a globally universal, internationalized game aggregation platform. Core requirements:

- Users can top up on the platform, exchange for game currency, play games, earn game currency, and withdraw
- The platform manages a variety of games uniformly (self-developed + third-party), with each game having its own game currency and exchange rate
- The backend provides complete review, switch, and risk control capabilities
- Support global operations with multi-language, multi-currency, and multi-payment-channel capabilities

## 2. Architecture Selection

### 2.1 Why a Modular Monolith Instead of Microservices?

The modular monolith is chosen for the current stage:

| Consideration | Modular Monolith | Microservices |
|------|----------|--------|
| Development efficiency | In-process calls, no RPC needed | Must handle network latency, serialization |
| Transaction consistency | Local database transactions | Distributed transactions (complex) |
| Ops complexity | Single-process deployment | Multi-service orchestration, service discovery |
| Scalability | Can be split into microservices by module in the future | Naturally supports independent scaling |
| Team size | Suitable for small teams (1-5 people) | Suitable for multi-team parallel development |

**Decision**: admin/ (admin backend) and service/ (C-end business) are two independent webman instances that can be deployed on the same machine (different ports) or separately. The shared layer common/ eliminates code duplication via PSR-4 autoload. As business volume grows in the future, service/ can be split into multiple microservices (user service, wallet service, game service).

### 2.2 Why webman v2 Instead of Traditional PHP-FPM?

| Consideration | webman v2 | PHP-FPM (Laravel) |
|------|-----------|-------------------|
| Performance | Resident memory, coroutine support | Loads all files on every request |
| Concurrency | Tens of thousands of QPS on a single machine | Hundreds of QPS on a single machine |
| Deployment | Simple, single process with multiple workers | Complex Nginx + PHP-FPM configuration |
| Ecosystem | Compatible with Laravel Illuminate components | Full ecosystem |

**Decision**: the game platform must handle high-concurrency deposit callbacks, exchange requests, and game settlements; webman's resident memory and high concurrency are a better fit. It also remains compatible with Laravel's ORM, Queue, and other components, so development efficiency matches traditional frameworks.

### 2.3 Why Flutter Web PC Style?

- One codebase compiles to Web (PC), iOS, Android, and HarmonyOS simultaneously
- The Material 3 component library is mature; the PC-style sidebar + top bar layout works out of the box
- Shares the business logic layer with the HarmonyOS client
- Avoids maintaining two frontend codebases (React/Vue + Flutter)

## 3. Key Technical Decisions

### 3.1 ID System

```
Snowflake 生成 BIGINT（内部分布式唯一）
    ↓
Hashids 编码为短字符串（对外不可逆推真实ID）
    ↓
API 请求/响应中传输 hashid 字符串
```

**Rationale**:
- Snowflake IDs are globally unique, trend-increasing for index friendliness, and do not expose business volume
- Hashids prevents outsiders from enumerating data via sequential IDs and inferring scale

### 3.2 Currency Precision

Platform currency and game currency uniformly use `DECIMAL(18,4)` precision; on the PHP side all monetary calculations use the `bcmath` function family (bcadd/bcsub/bcmul/bcdiv/bccomp).

**Rationale**: floating-point numbers (float/double) have precision errors that are unacceptable in financial scenarios. DECIMAL + bcmath guarantees exact calculation.

### 3.3 Wallet Optimistic Lock

```sql
UPDATE erik_user_wallet 
SET balance = balance + ?, version = version + 1 
WHERE user_id = ? AND version = ?
```

Automatically retries on update failure (up to 5 times).

**Rationale**:
- Deposit, exchange, and withdrawal on a game platform can concurrently operate on the same wallet
- Pessimistic locking (SELECT FOR UPDATE) performs poorly under high concurrency
- Optimistic locking far outperforms pessimistic locking in low-conflict scenarios

### 3.4 Withdrawal Review Flow

```
用户发起提现
  ├─ 全局开关关闭 → 拒绝
  ├─ 金额 < 自动审核阈值 → 自动通过
  └─ 金额 >= 阈值 → 人工审核 → 通过/拒绝（拒绝退回平台币）
```

**Rationale**:
- The global switch is for emergency risk control (e.g. discovered vulnerabilities, abnormal traffic)
- Auto-approval of small amounts reduces manual cost and improves user experience
- Manual review of large amounts prevents money laundering and fraud

### 3.5 Exchange Spread Model

Each game currency has its own `exchange_rate` (1 platform currency = X game currency) and `spread_pct` (platform cut %).

On buy: game currency credited = platform currency × rate × (1 - cut %)
On sell: platform currency credited = game currency ÷ rate × (1 - cut %)

**Rationale**:
- Platform revenue comes from the exchange spread, not in-game payments
- Independent rates support different pricing strategies per game
- The spread percentage can be flexibly adjusted for fine-grained operations

## 4. Security Architecture

On top of the existing 18-layer defense in depth, new protection layers are added for the game platform:

| Layer | Measure | Reason |
|------|------|------|
| Concurrency safety | Wallet version optimistic lock | Prevents duplicate debits/credits |
| Withdrawal safety | Global switch + amount threshold + daily/monthly limits + poster-php verification | Multi-layer defense, reduces fund risk |
| Exchange safety | Quote and settlement separated, quotes expire in 60s | Prevents arbitrage from rate fluctuation |
| Game safety | Third-party callback signature verification + IP whitelist + replay attack defense | Prevents forged game settlements |
| Risk control | Rule engine (IP blacklist, large-amount alerts, frequency anomalies) | Real-time blocking of suspicious transactions |

## 5. Internationalization Design

### 5.1 Language Detection

```
请求进入
  ↓
LanguageMiddleware（全局中间件）
  ├── 1. X-Language 请求头
  ├── 2. Accept-Language 头（zh → zh-CN, en → en-US）
  └── 3. 默认 en-US
  ↓
TranslationService::setLocale($locale)
  ↓
Controller 中 __() 函数或 TranslationService::trans() 获取翻译文本
```

### 5.2 Translation Storage

- The database table `erik_translation` stores all translation texts (group + key + lang_code + value)
- On the first request, all translations are loaded from the database into Redis (key: `i18n:translations`, TTL: 1 hour)
- Subsequent requests read directly from Redis with in-memory caching for speed
- The admin backend can add a translation management page (implemented in the complete version)

### 5.3 Translation Key Naming

Format: `group.key` e.g. `auth.login_success`, `wallet.insufficient_balance`

| Group | Domain |
|------|------|
| auth | Authentication related |
| wallet | Wallet related |
| exchange | Exchange related |
| withdraw | Withdrawal related |
| deposit | Deposit related |
| game | Game related |
| admin | Admin backend |
| error | Error messages |

### 5.4 Fallback Strategy

- Request language has a matching translation → use it
- Request language has no matching translation → fall back to en-US
- en-US also missing → return the raw key

### 5.5 Frontend i18n

- Flutter uses a custom `AppTranslations` + `LocaleController` (GetX)
- Language preference is persisted to SharedPreferences
- Switching language triggers a global UI re-render via `Get.updateLocale()`
- The `StringResult` class leverages Dart's `toString()` for natural inline syntax: `Text('${AppTranslations.t("key")}')`

## 6. Standard Version Additional Designs

### 6.1 Risk Control Engine

Multi-layer rule checks are executed before key fund operations:

```
充值/提现/兑换请求
  ↓
RiskService::check(userId, type, context)
  ├── IP 黑名单检测 (ip_blacklist) → block
  ├── 大额异常检测 (amount_anomaly) → warn
  ├── 频率检测 (frequency) → warn/block
  └── 速度检测 (velocity) → block
  ↓
passed → 正常执行
warn   → 记录日志，继续执行
block  → 拒绝操作
```

Rules are stored in the `erik_risk_rule` table, configured as JSON, with dynamically adjustable thresholds and actions.

### 6.2 KYC Real-Name Verification

Three-tier verification system:
- `default` — unverified, basic limits
- `verified` — KYC approved, higher limits + lower fees
- `vip` — VIP level, highest limits + zero fees

Verification flow:
```
用户提交证件信息 → status=pending
管理员审核 → approve/reject
approve → 用户自动升级为 verified 等级
reject → 用户可重新提交
```

### 6.3 OAuth Third-Party Login

Google / Facebook / Apple login supported:

```
前端点击 OAuth 按钮
  → GET /api/auth/oauth/{provider} → 获取授权URL
  → 跳转第三方授权页 → 用户同意
  → 回调 POST /api/auth/oauth/{provider}/callback
  → 查找已有绑定 → 直接登录
  → 无绑定 → 自动注册新用户 + 绑定 + 创建钱包
```

### 6.4 Payment Callback

```
第三方支付完成 → POST /api/payment/callback
  → provider 白名单校验（仅 stripe/paypal）
  → 验签 fail-closed（未配 secret/webhook_id、验签失败、时间戳超 ±300s 一律拒绝）
  → 回调金额与订单金额 bccomp 核对（防跨渠道冒用）
  → 更新订单状态 confirmed（事务化，入账失败回滚）
  → UserWallet::addBalance 到账
  → 记录 Transaction
  → RiskService::check 风控检查
```

### 6.5 Tiered Withdrawal Limits

Different limits and fees are applied by the user's KYC level:

| Level | Single Limit | Daily Limit | Monthly Limit | Fee |
|------|---------|--------|--------|--------|
| default | 1,000 | 10,000 | 50,000 | 1.00% |
| verified | 5,000 | 50,000 | 200,000 | 0.50% |
| vip | 20,000 | 200,000 | 1,000,000 | 0.00% |

## 7. Scalability Design

### 5.1 Horizontal Scaling

Both admin/ and service/ support multiple worker processes. Combined with an Nginx reverse proxy, multiple machines can be deployed for horizontal scaling:

```
Nginx (负载均衡)
  ├── admin-1 (:8787)
  ├── admin-2 (:8787)
  ├── service-1 (:8788)
  └── service-2 (:8788)
```

### 5.2 Module Splitting Path

When a single service/ becomes the bottleneck, split along the following path:

```
service/ (单体)
  → service-user/ (用户服务 :8788)
  → service-wallet/ (钱包服务 :8789)
  → service-game/ (游戏服务 :8790)
  → service-payment/ (支付服务 :8791)
```

Criteria for deciding when to split:
- A single module's QPS exceeds the single-machine capacity
- A module needs its own tech stack or deployment strategy
- The team has grown to the point where different modules need parallel development
