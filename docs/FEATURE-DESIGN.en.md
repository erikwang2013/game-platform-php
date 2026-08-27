# Feature Design Document
<!-- lang-nav -->

Languages: [中文](FEATURE-DESIGN.md) · **English** · [한국어](FEATURE-DESIGN.ko.md) · [Русский](FEATURE-DESIGN.ru.md) · [Deutsch](FEATURE-DESIGN.de.md) · [Français](FEATURE-DESIGN.fr.md) · [Español](FEATURE-DESIGN.es.md) · [Português](FEATURE-DESIGN.pt.md) · [हिन्दी](FEATURE-DESIGN.hi.md) · [العربية](FEATURE-DESIGN.ar.md) · [বাংলা](FEATURE-DESIGN.bn.md) · [Bahasa Indonesia](FEATURE-DESIGN.id.md) · [日本語](FEATURE-DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Currency System Design

### 1.1 Three-Tier Currency Model

```
第1层: 法币 (USD / CNY / EUR / JPY ...)
       ↕ 充值/提现（按汇率兑换）
第2层: 平台币（统一，精度 decimal(18,4)）
       ↕ 兑换（含汇率 + 平台抽成差价）
第3层: 游戏币（每种游戏独立，独立汇率）
```

### 1.2 Platform Currency

- The unified pricing unit within the platform
- Precision: `DECIMAL(18,4)`, minimum unit 0.0001
- Obtained by depositing fiat currency, exchangeable for any game currency
- Game currency can also be converted back to platform currency, then withdrawn as fiat
- The platform collects the exchange spread as its revenue source

### 1.3 Game Currency

- Each game can have multiple game currencies (e.g. gold, diamonds, points)
- Each currency has an independent exchange rate against platform currency (`exchange_rate`)
- Each currency has an independent platform cut ratio (`spread_pct`)
- Minimum/maximum exchange limits are supported (`min_exchange` / `max_exchange`)

### 1.4 Exchange Formulas

**Buying game currency (platform currency → game currency):**
```
游戏币到账 = 平台币数量 × exchange_rate × (1 - spread_pct / 100)
```

**Selling game currency (game currency → platform currency):**
```
平台币到账 = 游戏币数量 ÷ exchange_rate × (1 - spread_pct / 100)
```

**Example:**
- exchange_rate = 100 (1 platform currency = 100 game currency)
- spread_pct = 5% (platform takes a 5% spread)
- User buys with 10 platform currency: (10 × 100 × 0.95) = 950 game currency
- User sells 950 game currency: (950 ÷ 100 × 0.95) = 9.025 platform currency
- Platform revenue: 10 - 9.025 = 0.975 platform currency

## 2. Wallet Design

### 2.1 Platform Currency Wallet (game_user_wallet)

Auto-created on user registration, balance starts at 0.

| Field | Description |
|------|------|
| balance | Available balance (deposit/withdraw/exchangeable) |
| frozen_balance | Frozen balance (reserved, e.g. during withdrawal) |
| total_earned | Cumulative income |
| total_spent | Cumulative spending |
| version | Optimistic lock version (increments on every update) |

### 2.2 Game Currency Wallet (game_user_game_wallet)

Unique on the user + game + currency three dimensions. Auto-created on first exchange.

### 2.3 Concurrency Safety

An optimistic lock prevents concurrency issues:

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

## 3. Withdrawal System Design

### 3.1 Multi-Layer Controls

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

### 3.2 Withdrawal State Machine

```
pending (待审核)
  ├─→ approved (已通过) → completed (已完成)
  └─→ rejected (已拒绝) → 余额退回 + 退款流水
```

### 3.3 Admin Backend Controls

- **Global switch button**: enable/disable all user withdrawals with one click
- **Review queue**: pending list sorted by time, with approve/reject buttons
- **Limit configuration**: visually configure each limit parameter

## 4. Deposit Design

### 4.1 Deposit Flow

```
1. 用户选择支付方式和金额
2. 平台创建充值订单 (status=pending, 生成唯一 order_no)
3. 跳转第三方支付页面
4. 用户完成支付
5. 第三方回调通知平台 (POST /api/payment/callback)
6. 平台验签 → 更新订单 (status=confirmed)
7. 平台币到账 → 记录流水
```

### 4.2 Payment Methods

| Type | Provider | Description |
|------|--------|------|
| Fiat | Stripe | International credit card payments |
| Fiat | PayPal | Global e-wallet |
| Fiat | Alipay | Alipay (mainland China) |
| Fiat | WeChat Pay | WeChat Pay (mainland China) |
| Crypto | USDT-TRC20 | USDT on the TRON chain |

The basic version integrates a single payment method first (e.g. Stripe); the standard version expands to all channels.

## 5. Game Integration Design

### 5.1 Self-Developed Games

Self-developed games integrate directly into the platform, sharing the user system and wallets:

- Games query user game-currency balance via internal APIs
- Game settlement debits/credits game currency via internal APIs
- No additional signature verification needed

### 5.2 Third-Party Games

Third-party games integrate via SDK/API:

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

### 5.3 Signature Algorithm

```
signature = HMAC-SHA256(
    secret = api_secret,
    message = user_id + "|" + timestamp + "|" + amount + "|" + nonce,
)
```

Verification conditions:
- Correct signature
- Timestamp within ±60s (anti replay attack)
- nonce unused (recorded in Redis, expires in 60s)
- Request IP is on the whitelist

## 6. Permission Design

### 6.1 Preset Roles

| Role | Permission Scope |
|------|---------|
| Super admin | * (all permissions) |
| Game operations | Game management, announcement management, dashboard |
| Financial review | Withdrawal review, payment management, transaction viewing |
| Customer service | C-end user viewing, deposit order viewing |

### 6.2 Permission Granularity

```
{method}.{path}

示例:
  get.admin/game/list      → 查看游戏列表
  post.admin/game/create   → 创建游戏
  put.admin/withdraw/review → 审核提现
  put.admin/withdraw/switch → 操作提现开关（仅超级管理员）
```

## 呼. Standard Version Additional Designs

### 8.1 Risk Control Engine

Four rule types:
- `ip_blacklist` — IP blacklist matching, direct block on hit
- `amount_anomaly` — single large-amount detection, warns when exceeding the threshold
- `frequency` — operation frequency detection within a time window
- `velocity` — short-window multi-account correlation detection

Rules execute in descending priority order; the first matching rule decides the outcome (block > warn > log).

### 8.2 OAuth Third-Party Login

Supported providers: Google, Facebook, Apple

Flow:
1. Frontend requests `GET /api/auth/oauth/{provider}` to get the authorization URL
2. User is redirected to the third party to complete authorization
3. Callback `POST /api/auth/oauth/{provider}/callback` carries the authorization code
4. Backend looks up an existing binding → direct login; no binding → auto register + bind + create wallet

### 8.3 KYC Limit System

| Level | How to Obtain | Single Limit | Daily Limit | Fee |
|------|---------|---------|--------|--------|
| default | Default on registration | 1,000 | 10,000 | 1.00% |
| verified | KYC review approved | 5,000 | 50,000 | 0.50% |
| vip | Granted by operations | 20,000 | 200,000 | 0.00% |

### 8.4 Game Servers

Each game can configure multiple servers (region: global/asia/eu/na); server status: maintenance/normal/popular/new.

### 8.5 Daily Statistics Snapshot

`ComputeDailyStats::run()` runs via crontab in the early morning daily, computing five metrics:
- User stats (new/active/cumulative)
- Deposit stats (count/total amount)
- Withdrawal stats (count/total amount)
- Exchange stats (count/total fees)
- Game stats (player count/session count)

## 9. Production-Grade Features

### 9.1 Notification System

Notification types: system/deposit/withdraw/kyc/coupon/announcement

Auto-trigger scenarios:
- Deposit credited → NotificationService::send()
- Withdrawal approved/rejected → auto notification
- KYC approved/rejected → auto notification
- Coupon claimed → auto notification
- Referral reward credited → auto notification

Supports dual channels: in-app messages + email (email requires the MAIL_HOST environment variable).

### 9.2 Referral Rebates

```
用户A 生成推荐码 → 分享给用户B
用户B 注册时填写推荐码 → 双方各得注册奖励(signup_reward)
用户B 充值 → A 获得充值返佣(deposit_commission_pct%)
```

### 9.3 2FA Two-Factor Authentication

- TOTP standard protocol (RFC 6238), compatible with Google Authenticator
- Enable flow: get secret → scan to bind → verify TOTP → generate 8 backup recovery codes
- Login second-step verification: POST /api/2fa/verify
- ±1 time-window tolerance supported (30 seconds)

### 9.4 Real OAuth Integration

| Provider | Token Endpoint | User Info Endpoint |
|--------|----------|------------|
| Google | oauth2.googleapis.com/token | www.googleapis.com/oauth2/v3/userinfo |
| Facebook | graph.facebook.com/v18.0/oauth/access_token | graph.facebook.com/me |
| Apple | appleid.apple.com/auth/token | JWT id_token decoding |

Configured via PlatformConfig or environment variables; requests automatically fall back to mock mode on failure.

### 9.5 Payment Webhook Verification

- Stripe: HMAC-SHA256 signature verification (Stripe-Signature header)
- PayPal: POST back to PayPal's verification endpoint
- Verification is skipped when keys are not configured (development mode)

### 9.6 WebSocket Real-Time Leaderboard

- Protocol: WebSocket (ws://host:8789)
- Subscribe: {action: "subscribe", leaderboard_id: 123}
- Push: {type: "ranking_update", rankings: [...]}
- ping/pong heartbeat keepalive supported

## 7. Internationalization Design

### 7.1 Supported Languages

| Code | Name | Native Name | Icon |
|------|------|--------|------|
| en-US | English | English | us |
| zh-CN | Chinese (Simplified) | 简体中文 | cn |
| ja-JP | Japanese | 日本語 | jp |
| ko-KR | Korean | 한국어 | kr |

### 7.2 Translation Management

- Translations are organized in `group.key` format (e.g. `auth.login_success`)
- Stored in the `game_translation` table, cached in Redis (TTL 1 hour)
- APIs: `GET /api/language/list` gets available languages, `POST /api/language/switch` switches the language
- Frontend auto-detects via the `X-Language` request header or `Accept-Language`
- Falls back to en-US when a translation is missing; returns the raw key if en-US also lacks it

### 7.3 User Language Preference

- Auto-set from the browser's `Accept-Language` on registration
- Users can modify the `language` field via `PUT /api/user/profile` after login
- The user record is updated in sync when switching languages

## 8. Platform Revenue Model

| Revenue Source | Calculation | Description |
|---------|---------|------|
| Exchange spread | spread_fee per exchange | Charged on both buy and sell |
| Withdrawal fee | withdrawal amount × fee_pct | Implemented in the standard version |
| Game revenue share | third-party game revenue share | Per contract terms |
| Deposit FX spread | fiat→platform currency rate difference | Difference between platform-set rate and market rate |
