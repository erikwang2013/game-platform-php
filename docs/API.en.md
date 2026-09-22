# API Documentation
<!-- lang-nav -->

Languages: [中文](API.md) · **English** · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Online interactive documentation (with live debugging support):
- C-end service: http://localhost:8792/apidoc/
- Admin backend: http://localhost:8789/apidoc/
- Password: see the `APIDOC_PASSWORD` setting of the deployment environment

## 1. Conventions

### 1.1 Base URLs

| End | URL |
|----|------|
| Admin backend | `http://localhost:8789` |
| C-end service | `http://localhost:8792` |

### 1.2 Common Request Headers

```
Content-Type: application/json
Authorization: Bearer <token>    (需要认证的接口)
```

### 1.3 Unified Response Format

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Meaning |
|------|------|
| 0 | Success |
| 400 | Invalid parameters |
| 401 | Not authenticated (Token missing/expired/invalid) |
| 403 | No permission |
| 404 | Resource not found |
| 422 | Validation failed |
| 429 | Too Many Requests (rate limit triggered) |
| 500 | Server error |

### 1.4 ID Encoding

All IDs in API requests and responses are Hashids-encoded strings, not raw BIGINT values.

```
External: aB3xK9mW2pQ7rT5v  (hashid 字符串)
Internal: 1750123456789      (Snowflake BIGINT)
```

### 1.5 Pagination Format

```
Request: ?page=1&per_page=20

Response: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. C-end APIs (service :8792)

### 2.1 Authentication

#### POST /api/v1/auth/register — User Registration
```
Request: {
  "username": "player1",
  "password": "123456",
  "email": "player@example.com"     // 可选
}

Response: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": {
    "id": "aB3xK9mW2pQ7rT5v",
    "username": "player1",
    "nickname": "",
    "avatar": ""
  }
}
```

#### POST /api/v1/auth/login — User Login
```
Request: {
  "username": "player1",
  "password": "123456"
}

Response: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "...", ... }
}
```

Error: 401 用户名或密码错误 / 账号已被禁用

#### POST /api/v1/auth/refresh — Refresh Token
```
Request: (Authorization: Bearer <refresh_token>)

Response: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 Wallet

#### GET /api/v1/wallet/info — Wallet Info
```
Authentication required: Yes

Response: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/v1/wallet/transactions — Transaction Records
```
Authentication required: Yes
Parameters: ?page=1&per_page=20&type=deposit    (type 可选)

Response: {
  "list": [
    {
      "id": "...",
      "type": "deposit",
      "amount": "100.0000",
      "balance_after": "100.5000",
      "remark": "充值到账",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 25,
  "page": 1,
  "per_page": 20
}

type Allowed values: deposit / withdraw / exchange_in / exchange_out / game_earn / game_spend
```

### 2.3 Deposit

#### POST /api/v1/deposit/create — Create Deposit Order
```
Authentication required: Yes

Request: {
  "amount": "10.00",
  "currency": "USD",
  "payment_method_id": "aB3xK..."
}

Response: {
  "order_id": "aB3xK...",
  "order_no": "DEP202605221030000123",
  "amount": "10.00",
  "platform_amount": "10.0000",
  "checkout_url": "https://checkout.stripe.com/...",
  "expires_at": "2026-05-22 11:30:00"
}
```

currency Allowed values: USD / CNY / EUR / JPY / KRW / GBP / BRL / INR

checkout_url: payment gateway redirect link (filled in at order creation); expires_at: payment link expiry (1 hour after creation)

#### GET /api/v1/deposit/orders — Deposit Records
```
Authentication required: Yes
Parameters: ?page=1&per_page=20

Response: {
  "list": [
    {
      "id": "...",
      "order_no": "DEP...",
      "amount": "10.00",
      "currency": "USD",
      "platform_amount": "10.0000",
      "status": "pending",
      "paid_at": null,
      "created_at": "2026-05-22 10:25:00"
    }
  ],
  "total": 5,
  "page": 1,
  "per_page": 20
}
```

status Allowed values: pending / paid / confirmed / cancelled

### 2.4 Exchange

#### POST /api/v1/exchange/quote — Quote
```
Authentication required: Yes

Request: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "direction": "in",
  "platform_amount": "10.0000"
}

Response: {
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000",
  "spread_pct": "5.00%"
}
```

direction: in=买入游戏币 / out=卖出游戏币

#### POST /api/v1/exchange/buy — Buy Game Currency
```
Authentication required: Yes

Request: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "10.0000"
}

Response: {
  "exchange_id": "aB3xK...",
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000"
}
```

Error: 422 平台币余额不足 / 404 游戏不可用

#### POST /api/v1/exchange/sell — Sell Game Currency
```
Authentication required: Yes

Request: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "950.0000"
}

Response: {
  "exchange_id": "aB3xK...",
  "platform_amount": "9.0250",
  "game_amount": "950.0000",
  "spread_fee": "0.4750",
  "rate": "100.00000000"
}
```

Error: 422 游戏币余额不足

#### GET /api/v1/exchange/records — Exchange Records
```
Authentication required: Yes
Parameters: ?page=1&per_page=20

Response: {
  "list": [
    {
      "id": "...",
      "game_id": "...",
      "direction": "in",
      "platform_amount": "10.0000",
      "game_amount": "950.0000",
      "rate": "100.00000000",
      "spread_fee": "50.0000",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 15,
  "page": 1,
  "per_page": 20
}
```

### 2.5 Withdrawal

#### POST /api/v1/withdraw/apply — Withdrawal Application
```
Authentication required: Yes

Request: {
  "platform_amount": "50.0000",
  "method": "paypal",
  "account_info": "user@paypal.com"
}

Response: {
  "order_id": "...",
  "order_no": "WTH202605221030000456",
  "status": "approved"
}
```

method Allowed values: paypal / bank / crypto

status:
- approved: 自动通过（金额 < auto_approve_threshold）
- pending: 待审核（金额 >= auto_approve_threshold）

Error:
- 403 提现功能暂时关闭（全局开关关闭）
- 400 低于最低提现金额
- 400 超过每日提现限额
- 400 余额不足

#### GET /api/v1/withdraw/orders — Withdrawal Records
```
Authentication required: Yes
Parameters: ?page=1&per_page=20

Response: {
  "list": [
    {
      "id": "...",
      "order_no": "WTH...",
      "platform_amount": "50.0000",
      "method": "paypal",
      "status": "pending",
      "review_note": "",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 3,
  "page": 1,
  "per_page": 20
}
```

### 2.6 Games

#### GET /api/v1/game/list — Game List
```
Parameters: ?page=1&per_page=20&keyword=射击&type=self

Response: {
  "list": [
    {
      "id": "aB3xK...",
      "name": "射击大师",
      "slug": "shooter-master",
      "type": "self",
      "description": "一款精彩的射击游戏",
      "cover_image": "https://...",
      "currencies": [
        {
          "id": "...",
          "name": "金币",
          "symbol": "G",
          "exchange_rate": "100.00000000",
          "min_exchange": "1.0000",
          "max_exchange": "10000.0000"
        }
      ]
    }
  ],
  "total": 20,
  "page": 1,
  "per_page": 20
}
```

type Allowed values: self / embedded / third_party

#### GET /api/v1/game/detail/{hashid} — Game Detail
```
Response: {
  "id": "...",
  "name": "射击大师",
  "slug": "shooter-master",
  "type": "self",
  "description": "...",
  "cover_image": "https://...",
  "currencies": [
    {
      "id": "...",
      "name": "金币",
      "symbol": "G",
      "exchange_rate": "100.00000000",
      "spread_pct": "5.00",
      "min_exchange": "1.0000",
      "max_exchange": "10000.0000"
    }
  ]
}
```

#### POST /api/v1/game/launch — Launch Game
```
Authentication required: Yes

Request: { "game_id": "aB3xK..." }

Response: {
  "id": "...",
  "name": "射击大师",
  "type": "self",
  "api_endpoint": "https://game.example.com/play"
}
```

### 2.7 OAuth Third-Party Login

7 platforms supported: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/v1/auth/oauth/{provider} — Get Authorization URL
```
Parameters: provider = google / facebook / apple / twitter / microsoft / linkedin / github

Response: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/v1/auth/oauth/{provider}/callback — OAuth Callback
```
Request: { "code": "授权码", "state": "防CSRF状态" }

Response: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=新注册用户 / false=已有账号绑定

### 2.8 KYC Real-Name Verification

#### GET /api/v1/user/identity/status — Verification Status
```
Authentication required: Yes

Response: {
  "status": "approved",          // not_submitted / pending / approved / rejected
  "real_name": "J***",
  "id_type": "id_card",
  "review_note": "",
  "submitted_at": "2026-05-22 10:00:00",
  "reviewed_at": "2026-05-23 14:00:00"
}
```

#### POST /api/v1/user/identity/apply — Submit Verification
```
Authentication required: Yes

Request: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}

Response: { "message": "KYC submitted successfully" }
```

### 2.9 Payments

#### POST /api/v1/payment/callback — Payment Callback (public)
```
Request: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

Response: { "message": "success" }
```

status: success / failed

provider values: stripe / paypal / nowpayments / coinbase / skrill / neteller / paysafecard / paytm / mercadopago / astropay / paypay / kakaopay / gcash / mpesa / paystack / toss / adyen / grabpay

| provider | Region | Signature scheme | Supported currencies |
|----------|--------|------------------|----------------------|
| stripe | Global (125+ local payment methods, incl. Alipay/WeChat Pay APM) | Webhook HMAC-SHA256 | USD / CNY / EUR |
| paypal | 200+ markets worldwide | Webhook verify-webhook-signature | USD / CNY / EUR and other fiat |
| nowpayments | Global (crypto) | IPN HMAC-SHA512 | USDT TRC20 / ERC20 |
| coinbase | Global (crypto) | Webhook HMAC-SHA256 (base64 secret) | USDC / BTC / ETH |
| skrill | Europe / Global | Secret word MD5 check | EUR and other fiat |
| neteller | Europe / Global | Secret key field comparison | EUR and other fiat |
| paysafecard | Europe (DE / AT / CH etc.) | X-Signature HMAC-SHA256 | EUR and other fiat |
| paytm | India | SHA256 + AES-128-CBC | INR |
| mercadopago | Latin America (BR / MX etc.) | X-Signature (ts,v1) HMAC-SHA256 | BRL / MXN and other fiat |
| astropay | Latin America (BR etc.) | MD5(order_id.amount.status.secret) | BRL and other fiat |
| paypay | Japan | PayPay-Signature HMAC-SHA256 | JPY |
| kakaopay | South Korea | No webhook (ready/approve two-step) | KRW |
| gcash | Philippines | Paymongo-Signature HMAC-SHA256 | PHP |
| toss | South Korea | Server-side verify + amount check | KRW |
| mpesa | Kenya | Trusted IP (CALLBACK_TRUSTED_IPS), no signature | KES |
| paystack | Nigeria | x-paystack-signature HMAC-SHA512 | NGN |
| adyen | Global (currency per order) | additionalData.hmacSignature HMAC-SHA256 (ADYEN_HMAC_KEY) | per order |
| grabpay | Singapore (country configurable, default SG) | x-signature HMAC-SHA256 (sorted key:value) | per order |

#### GET /api/v1/payment/methods — Available Payment Methods (public)
```
Response: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

Filtered by user country (X-Language/Accept-Language → country code mapping): empty countries or containing * means globally visible; sorted by that country's country_config payment-method preference

### 2.10 Game Play Logs

#### GET /api/v1/game/play-logs — Game Play Log List
```
Authentication required: Yes
Parameters: ?page=1&per_page=20&game_id=xxx&action=start

Response: {
  "list": [
    {
      "id": "...",
      "game_id": "...",
      "action": "start",
      "game_amount_change": "-10.0000",
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 50, "page": 1, "per_page": 20
}
```

#### GET /api/v1/game/play-log/{hashid} — Game Play Log Detail
```
Authentication required: Yes
Response: { 完整记录，含 session_id / game_amount_before / after 等 }
```

### 2.12 Leaderboards

#### GET /api/v1/leaderboard/list — Leaderboard List
```
Response: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/v1/leaderboard/{hashid} — Leaderboard Detail
```
Response: {
  "id": "...",
  "name": "全服累计收入榜",
  "type": "total",
  "rankings": [
    { "rank": 1, "user_id": "...", "score": "50000.0000" }
  ]
}
```

### 2.13 Coupons

#### GET /api/v1/coupon/available — Available Coupons
```
Authentication required: Yes
Response: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/v1/coupon/claim — Claim Coupon
```
Authentication required: Yes
Request: { "coupon_id": "hashid" }
Response: { "coupon": { ... } }
```

#### GET /api/v1/coupon/my — My Coupons
```
Authentication required: Yes
Parameters: ?status=unused
Response: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 Country Config

#### GET /api/v1/country/list — Country List
```
Response: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/v1/country/{code} — Country Detail
```
Response: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 Notifications

#### GET /api/v1/notification/list — Notification List
```
Authentication required: Yes
Parameters: ?page=1&per_page=20&is_read=0

Response: {
  "list": [
    { "id": "...", "type": "deposit", "title": "Deposit Received", "is_read": 0, "created_at": "..." }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### GET /api/v1/notification/unread-count — Unread Count
```
Authentication required: Yes
Response: { "count": 3 }
```

#### POST /api/v1/notification/read — Mark as Read
```
Authentication required: Yes
Request: { "id": "hashid" }  // 不传=全部已读
```

### 2.17 Referrals

#### GET /api/v1/referral/my-code — My Referral Code
```
Authentication required: Yes
Response: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/v1/referral/apply — Apply Referral Code
```
Authentication required: Yes
Request: { "code": "ABC12345" }
Response: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/v1/user/2fa/status — 2FA Status
```
Authentication required: Yes
Response: { "enabled": false }
```

#### POST /api/v1/user/2fa/setup — Set Up 2FA
```
Authentication required: Yes
Response: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/v1/user/2fa/enable — Enable 2FA
```
Authentication required: Yes
Request: { "code": "123456" }
Response: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/v1/2fa/verify — Verify 2FA (public)
```
Request: { "user_id": "hashid", "code": "123456" }
Response: { "valid": true }
```

### 2.19 Search

#### GET /api/v1/search — Global Search
```
Parameters: ?q=keyword&type=game&page=1&per_page=20
Response: { "list": [...], "total": 100 }
```

#### GET /api/v1/game/suggest — Search Suggestions
```
Parameters: ?q=shoot
Response: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 Languages

#### GET /api/v1/language/list — Available Languages
```
Response: {
  "current": "en-US",
  "languages": {
    "en-US": { "name": "English", "nativeName": "English", "icon": "us" },
    "zh-CN": { "name": "Chinese (Simplified)", "nativeName": "简体中文", "icon": "cn" },
    "ja-JP": { "name": "Japanese", "nativeName": "日本語", "icon": "jp" },
    "ko-KR": { "name": "Korean", "nativeName": "한국어", "icon": "kr" }
  }
}
```

#### POST /api/v1/language/switch — Switch Language
```
Request: { "locale": "zh-CN" }
Response: { "locale": "zh-CN" }
```

locale Allowed values: en-US / zh-CN / ja-JP / ko-KR

### 2.8 User

#### GET /api/v1/user/profile — Personal Profile
```
Authentication required: Yes

Response: {
  "id": "...",
  "username": "player1",
  "nickname": "Player One",
  "avatar": "https://...",
  "email": "p***@example.com",
  "phone": "",
  "country": "US",
  "language": "en-US",
  "last_login_at": "2026-05-22 10:00:00",
  "created_at": "2026-05-20 08:00:00"
}
```

#### PUT /api/v1/user/profile — Edit Profile
```
Authentication required: Yes

Request: {
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}

Response: {
  "id": "...",
  "username": "player1",
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}
```

language Allowed values: en-US / zh-CN / ja-JP / ko-KR

### 2.9 Announcements

#### GET /api/v1/announcement/list — Announcement List
```
Response: {
  "list": [
    {
      "id": "...",
      "title": "系统维护通知",
      "type": "system",
      "created_at": "2026-05-22 09:00:00"
    }
  ]
}
```

#### GET /api/v1/announcement/detail/{hashid} — Announcement Detail
```
Response: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

### 2.21 Platform Stats

| Method | Path | Description | Auth |
|------|------|------|------|
| GET | /api/v1/platform/stats | Public platform stats (total games / total users / today's plays / 7-day active users) | No |

#### GET /api/v1/platform/stats — Platform Stats

```
无需认证

Response: {
  "total_games": 12,
  "total_users": 1500,
  "today_game_plays": 320,
  "active_users_7d": 450
}
```

## 3. Admin Backend APIs (admin :8789)

### 3.1 Platform Dashboard

#### GET /admin/v1/dashboard/platform

```
Authentication required: Yes (AdminAuth + AdminPermission)

Response: {
  "total_users": 1500,
  "active_users_7d": 320,
  "total_games": 12,
  "pending_withdraws": 5,
  "today_deposits": "500.0000",
  "today_withdraws": "120.0000",
  "total_spread_fee": "1500.5000"
}
```

### 3.2 Game Management

#### GET /admin/v1/game/list — Game List
```
Authentication required: Yes
Parameters: ?page=1&limit=20&keyword=射击

Response: {
  "list": [
    {
      "id": "...",
      "name": "射击大师",
      "slug": "shooter-master",
      "type": "self",
      "status": 1,
      "sort": 0,
      "currency_count": 2,
      "created_at": "2026-05-20 08:00:00"
    }
  ],
  "total": 12,
  "page": 1,
  "limit": 20
}
```

#### GET /admin/v1/game/{hashid} — Game Detail

```
Authentication required: Yes
Parameters: hashid 为游戏的 hashid 编码（路径参数）

Response: {
  "id": "aB3xK...",
  "name": "射击大师",
  "slug": "shooter-master",
  "type": "self",
  "description": "游戏描述",
  "cover_image": "https://...",
  "api_endpoint": "https://...",
  "sdk_version": "1.0.0",
  "platform": "h5",
  "region": "global",
  "currencies": [
    {
      "id": "cD4yL...",
      "name": "金币",
      "symbol": "G",
      "exchange_rate": "100.00000000",
      "spread_pct": "5.00000000",
      "min_exchange": "1.00000000",
      "max_exchange": "10000.00000000"
    }
  ]
}
```

Returns code 404 when the game does not exist.

#### POST /admin/v1/game/launch — Game Launch Preview

```
Authentication required: Yes

Request: {
  "game_id": "aB3xK..."      // 游戏 ID(hashid)
}

Response: {
  "id": "aB3xK...",
  "name": "射击大师",
  "slug": "shooter-master",
  "type": "self",
  "api_endpoint": "https://...",
  "preview": true
}
```

Missing `game_id` returns code 422; game not found returns 404; game not published (`status` is not 1) returns 403.

The admin preview is a pure preview: it only validates game availability and returns the launch info, and **writes no game records and touches no wallet**. Admin identities only carry `adminId` (injected by `AdminAuth`) and no C-end `userId`, so this endpoint deliberately performs no user-side writes — copying the C-end `POST /api/v1/game/launch` would write `game_game_play_log` rows attributed to the wrong owner.

#### POST /admin/v1/game/create — Create Game
```
Authentication required: Yes

Request: {
  "name": "新游戏",
  "slug": "new-game",
  "type": "self",
  "description": "游戏描述",        // 可选
  "cover_image": "https://...",    // 可选
  "api_endpoint": "https://...",   // 可选
  "api_key": "...",                // 可选
  "api_secret": "...",             // 可选
  "status": 1,                     // 可选, 默认0
  "sort": 0                        // 可选, 默认0
}

Response: { "id": "aB3xK..." }
```

type Allowed values: self / embedded / third_party

#### PUT /admin/v1/game/{hashid} — Edit Game
```
Authentication required: Yes

Request: {
  "name": "新名称",
  "status": 1
  // 可部分更新，字段同 create
}

Response: { "message": "更新成功" }
```

#### DELETE /admin/v1/game/{hashid} — Delete Game
```
Authentication required: Yes
Response: { "message": "删除成功" }
```

#### POST /admin/v1/game/currency/manage — Manage Currencies
```
Authentication required: Yes

Request: {
  "game_id": "aB3xK...",
  "currencies": [
    {
      "id": "",                       // 空=新建, 有值=更新
      "name": "金币",
      "symbol": "G",
      "exchange_rate": "100.00000000",
      "spread_pct": "5.00",
      "min_exchange": "1.0000",
      "max_exchange": "10000.0000"
    }
  ]
}

Response: { "message": "操作成功" }
```

Missing `game_id` or non-array `currencies` returns 422; game not found returns 404.

When supplied, `exchange_rate` must be greater than 0 and `spread_pct` must be in [0, 100); violating either returns 422 and no currency in the batch is written (the batch is fully validated before any write). Omitted fields skip validation: on create they default (`exchange_rate` to `1.00000000`, the others to `0.00000000`), on update the existing value is kept.

### 3.3 Withdrawal Management

#### GET /admin/v1/withdraw/orders — Withdrawal Order List
```
Authentication required: Yes
Parameters: ?page=1&limit=20&status=pending

Response: {
  "list": [
    {
      "id": "...",
      "order_no": "WTH...",
      "user": {
        "id": "...",
        "username": "player1"
      },
      "platform_amount": "500.0000",
      "method": "paypal",
      "status": "pending",
      "reviewer_id": null,
      "review_note": "",
      "reviewed_at": null,
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 5,
  "page": 1,
  "limit": 20
}
```

#### PUT /admin/v1/withdraw/review — Review Withdrawal
```
Authentication required: Yes

Request: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

Response: { "message": "已通过" }
```

action: approve=通过 / reject=拒绝 / confirm=确认打款（拒绝时自动退回平台币）

Error: 422 订单状态不是待审核

#### PUT /admin/v1/withdraw/switch — Global Withdrawal Switch
```
Authentication required: Yes

Request: { "enabled": 1 }

Response: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/v1/withdraw/limits/set — Set Withdrawal Limits
```
Authentication required: Yes

Request: {
  "daily_limit": "10000.0000",             // 可选
  "min_amount": "1.0000",                  // 可选
  "auto_approve_threshold": "100.0000"     // 可选
}

Response: {
  "daily_limit": "10000.0000",
  "min_amount": "1.0000",
  "auto_approve_threshold": "100.0000",
  "global_switch": true
}
```

#### POST /admin/v1/withdraw/batch-review — Batch Review Withdrawals

```
Authentication required: Yes

Request: {
  "ids": ["aB3xK...", "cD4yL..."],
  "action": "approve",
  "note": "批量审核通过"
}

Response: {
  "processed": 2,
  "failed": []
}
```

action: approve / reject (processed per order; rejected orders are refunded automatically; failures are listed in failed and do not affect the rest)

#### POST /admin/v1/withdraw/execute-payout — Execute Payout

```
Authentication required: Yes

Request: { "order_id": "aB3xK..." }

Response: {
  "payout_batch_id": "PAYOUT-123456",
  "payout_item_id": "ITEM-123456",
  "payout_status": "success",
  "payout_attempts": 1
}
```

Only orders in approved status can be paid out (atomic flip to processing); a repeated call returns 422. With dual review enabled, the order must first be confirmed by a second admin

#### POST /admin/v1/withdraw/sync-payout — Sync Payout Status

```
Authentication required: Yes

Request: { "order_id": "aB3xK..." }

Response: {
  "payout_status": "success",
  "order_status": "completed",
  "synced_status": "success"
}
```

Error: 422 no payout has been executed for this order yet

### 3.4 Platform User Management

#### GET /admin/v1/platform/user/list — C-end User List
```
Authentication required: Yes
Parameters: ?page=1&limit=20&keyword=player&status=1

Response: {
  "list": [
    {
      "id": "...",
      "username": "player1",
      "nickname": "Player One",
      "country": "US",
      "status": 1,
      "last_login_at": "2026-05-22 10:00:00",
      "created_at": "2026-05-20 08:00:00"
    }
  ],
  "total": 1500,
  "page": 1,
  "limit": 20
}
```

#### GET /admin/v1/platform/user/{hashid} — User Detail
```
Authentication required: Yes

Response: {
  "id": "...",
  "username": "player1",
  "nickname": "Player One",
  "email": "p***@example.com",
  "phone": "",
  "country": "US",
  "language": "en-US",
  "status": 1,
  "wallet": {
    "balance": "100.5000",
    "frozen_balance": "0.0000"
  },
  "last_login_at": "2026-05-22 10:00:00",
  "created_at": "2026-05-20 08:00:00"
}
```

#### PUT /admin/v1/platform/user/{hashid} — Edit/Ban User
```
Authentication required: Yes

Request: {
  "status": 0,         // 0=禁用 1=启用
  "nickname": "..."    // 可选
}

Response: { "message": "更新成功" }
```

### 3.5 Payment Management

#### GET /admin/v1/payment/method/list

```
Authentication required: Yes

Response: {
  "list": [
    {
      "id": "...",
      "name": "Stripe",
      "type": "fiat",
      "provider": "stripe",
      "status": 1
    }
  ]
}
```

#### POST /admin/v1/payment/method/toggle — Enable/Disable Payment Method
```
Authentication required: Yes

Request: { "id": "aB3xK...", "status": 0 }

Response: { "message": "已更新" }
```

### 3.6 Announcement Management

#### GET /admin/v1/announcement/list

```
Authentication required: Yes
Parameters: ?page=1&limit=20

Response: {
  "list": [
    {
      "id": "...",
      "title": "系统维护通知",
      "type": "system",
      "status": 1,
      "start_at": "2026-05-23 02:00:00",
      "end_at": "2026-05-23 04:00:00",
      "created_at": "2026-05-22 09:00:00"
    }
  ],
  "total": 5,
  "page": 1,
  "limit": 20
}
```

#### POST /admin/v1/announcement/create — Publish Announcement
```
Authentication required: Yes

Request: {
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护。",
  "type": "system",           // 可选, 默认"system"
  "target_lang": "",          // 可选, 空=全语言
  "status": 1,                // 可选, 默认1 (0=草稿 1=发布)
  "start_at": "2026-05-23 02:00:00",  // 可选
  "end_at": "2026-05-23 04:00:00"     // 可选
}

Response: { "id": "aB3xK..." }
```

### 3.7 KYC Review

#### GET /admin/v1/identity/list — KYC List
```
Authentication required: Yes
Parameters: ?page=1&limit=20&status=pending

Response: {
  "list": [
    {
      "id": "...",
      "user": { "id": "...", "username": "player1" },
      "real_name": "J***",
      "id_type": "id_card",
      "status": "pending",
      "created_at": "2026-05-22 10:00:00"
    }
  ],
  "total": 5, "page": 1, "limit": 20
}
```

#### PUT /admin/v1/identity/review — Review KYC
```
Authentication required: Yes

Request: { "id": "hashid", "action": "approve", "note": "" }

Response: { "message": "Approved" }
```

action: approve / reject

### 3.8 Game Server Management

#### GET /admin/v1/game/server/list — Server List
```
Authentication required: Yes
Parameters: ?game_id=hashid

Response: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/v1/game/server/create — Create Server
```
Authentication required: Yes
Request: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
Response: { "id": "hashid" }
```

#### PUT /admin/v1/game/server/{hashid} — Edit Server
```
Authentication required: Yes
Request: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/v1/game/server/{hashid} — Delete Server
```
Authentication required: Yes
```

### 3.9 Withdrawal Tier Limits Management

#### GET /admin/v1/withdraw/limits/list

```
Authentication required: Yes

Response: {
  "list": [
    {
      "id": "...",
      "user_level": "verified",
      "single_min": "1.0000",
      "single_max": "5000.0000",
      "daily_limit": "50000.0000",
      "monthly_limit": "200000.0000",
      "fee_pct": "0.50",
      "fee_max": "25.0000",
      "auto_approve_threshold": "500.0000"
    }
  ]
}
```

#### PUT /admin/v1/withdraw/limits/{hashid} — Update Limits
```
Authentication required: Yes

Request: { "single_max": "10000.0000", "fee_pct": "0.25" }
// 可部分更新
```

### 3.11 Game Category Management

#### GET /admin/v1/game/category/list

```
Authentication required: Yes
Response: { "list": [{ "id": "...", "name": "动作", "slug": "action", "sort": 1 }] }
```

#### POST /admin/v1/game/category/create

```
Authentication required: Yes
Request: { "name": "新分类", "slug": "new-cat", "icon": "star", "sort": 10 }
Response: { "id": "hashid" }
```

#### PUT /admin/v1/game/category/{hashid} — Edit Category
#### DELETE /admin/v1/game/category/{hashid} — Delete Category
#### POST /admin/v1/game/category/assign — Assign Games
```
Authentication required: Yes
Request: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 Leaderboard Management

#### GET /admin/v1/leaderboard/list — Leaderboard List
```
Authentication required: Yes
Response: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/v1/leaderboard/create — Create Leaderboard
```
Authentication required: Yes
Request: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(可选)" }
```

#### PUT /admin/v1/leaderboard/{hashid} — Edit Leaderboard
#### DELETE /admin/v1/leaderboard/{hashid} — Delete Leaderboard
#### POST /admin/v1/leaderboard/{hashid}/refresh — Refresh Cache
### 3.13 Coupon Management

#### GET /admin/v1/coupon/list — Coupon List
#### POST /admin/v1/coupon/create — Create Coupon
```
Authentication required: Yes
Request: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/v1/coupon/{hashid} — Edit (when unclaimed)
#### DELETE /admin/v1/coupon/{hashid} — Delete
#### GET /admin/v1/coupon/{hashid}/stats — Claim Statistics
```
Response: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 Country Config Management

#### GET /admin/v1/country/config/list — Country Config List
#### POST /admin/v1/country/config/create — Create Country Config
```
Authentication required: Yes
Request: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/v1/country/config/{hashid} — Edit Country Config
### 3.15 Data Export

#### POST /admin/v1/export/users — Export C-end Users
```
Authentication required: Yes
Parameters (JSON): { "status": 1 }   // 可选筛选

Response: Excel 文件下载 (xlsx)
```

#### POST /admin/v1/export/transactions — Export Platform Transactions
```
Authentication required: Yes
Parameters (JSON): { "type": "deposit" }   // 可选筛选

Response: Excel 文件下载 (xlsx)
```

### 3.16 Data Analytics (MySQL real-time aggregation)

All endpoints require authentication (AdminAuth + AdminPermission); data is aggregated in real time from MySQL, not dependent on ClickHouse.

| Method | Path | Description |
|------|------|------|
| GET | /admin/v1/analytics/overview | Platform overview (today/last 7 days) |
| GET | /admin/v1/analytics/game-ranking | Game ranking (?days=7) |
| GET | /admin/v1/analytics/dau-trend | DAU trend (?days=30) |
| GET | /admin/v1/analytics/hourly-trend | Hourly trend |
| GET | /admin/v1/analytics/action-distribution | Action distribution |
| GET | /admin/v1/analytics/revenue | Revenue analysis |
| GET | /admin/v1/analytics/conversion | Game conversion rate |
| GET | /admin/v1/analytics/probability | Joint/conditional probability |
| GET | /admin/v1/analytics/retention | Retention analysis D1/D3/D7/D30 |
| GET | /admin/v1/analytics/funnel | Conversion funnel |
| GET | /admin/v1/analytics/arpu | ARPU/ARPPU trend |
| GET | /admin/v1/analytics/economy | Game currency economy metrics |

### 3.17 Ticket Management

All endpoints require authentication (AdminAuth + AdminPermission).

| Method | Path | Description |
|------|------|------|
| GET | /admin/v1/ticket/list | Ticket list (?page=&limit=&status=&type=) |
| GET | /admin/v1/ticket/{hashid} | Ticket detail (incl. replies) |
| POST | /admin/v1/ticket/{hashid}/reply | Reply to ticket |
| POST | /admin/v1/ticket/{hashid}/close | Close ticket |
| POST | /admin/v1/ticket/{hashid}/assign | Assign handler (admin_id) |

### 3.18 CDN Configuration Management

All endpoints require authentication (AdminAuth + AdminPermission).

| Method | Path | Description | Auth |
|------|------|------|------|
| GET | /admin/v1/cdn/provider/list | List CDN providers (credentials not returned) | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/toggle | Enable/disable provider {id, status} | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/create | Create {name, provider, config(JSON), status, sort}, provider uniqueness check | AdminAuth + RBAC: cdn |
| PUT | /admin/v1/cdn/provider/{hashid} | Update (empty config = unchanged) | AdminAuth + RBAC: cdn |
| DELETE | /admin/v1/cdn/provider/{hashid} | Delete | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/test | Connectivity test HeadBucket {id} | AdminAuth + RBAC: cdn |

### 3.19 Data Reports

All endpoints require authentication (AdminAuth + AdminPermission).

| Method | Path | Description | Auth |
|------|------|------|------|
| GET | /admin/v1/report/summary | Report summary (new users / deposits / withdrawals / exchanges / game plays) | AdminAuth + RBAC: report |
| GET | /admin/v1/report/daily | Daily report (daily aggregation, zero-filled for empty days) | AdminAuth + RBAC: report |
| GET | /admin/v1/report/export | Daily report export as CSV (UTF-8 BOM) | AdminAuth + RBAC: report |

## 4. Rate Limit Policy

| Endpoint | Limit |
|------|------|
| Default | 60 requests/minute/IP |
| POST /api/v1/auth/login | 10 requests/minute |
| POST /api/v1/auth/register | 5 requests/minute |

Over limit returns 429, response headers include:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1716400830
Retry-After: 60
```

## 5. Authentication Notes

### C-end (UserAuth)

1. Extract the Token from `Authorization: Bearer <token>`
2. JWT signature verification (HS256), parse `sub` (user ID)
3. Query the `game_user` table to verify the user exists and status=1
4. Inject `$request->userId`

### Admin Backend (AdminAuth + AdminPermission)

1. AdminAuth: JWT signature verification, parse `sub` (admin ID), inject `$request->adminId`
2. AdminPermission: look up permissions by user role, match permission identifiers in `method.path` format
3. Super admins with `slug=*` skip permission checks

## 6. Error Code Quick Reference

| code | Meaning | Common Scenarios |
|------|------|---------|
| 0 | Success | - |
| 400 | Invalid parameters | Malformed request, insufficient balance |
| 401 | Not authenticated | Token missing/expired/invalid, account disabled |
| 403 | No permission | User lacks the corresponding role permission, game unavailable |
| 404 | Not found | Resource not found |
| 422 | Validation failed | Form parameters violate rules, order status does not permit the operation |
| 429 | Rate limited | Too many requests |
| 500 | Server error | Unexpected exception |


## 7. New APIs (v2.0 Ecosystem Expansion)

### 7.1 Provider API — Game Provider Callback Endpoints

**Authentication**: HMAC-SHA256 signature (X-Game-Id + X-Timestamp + X-Signature)
**Time window**: 5 minutes

#### POST /api/provider/balance — Query User Balance
```
Request headers:
  X-Game-Id: 1234567890
  X-Timestamp: 1716400830
  X-Signature: abc123...

Request: {
  "user_id": 1234567890,
  "game_id": 9876543210,
  "currency_id": 5555555555
}

Response: {
  "code": 0,
  "message": "success",
  "data": { "balance": "1000.50000000" }
}
```

#### POST /api/provider/bet — Notify Bet
```
Request: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "bet_type": "straight" }
}

Response: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "990.50000000"
  }
}
```

#### POST /api/provider/settle — Notify Settlement
```
Request: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}

Response: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1040.50000000",
    "win_amount": "50.00000000"
  }
}
```

#### POST /api/provider/refund — Notify Refund
```
Request: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "reason": "game_crash"
}

Response: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1000.50000000"
  }
}
```

### 7.2 Ticket APIs

#### GET /api/v1/ticket/list — Ticket List
```
Authentication required: Yes
Parameters: ?page=1&per_page=20

Response: {
  "list": [
    {
      "id": "aB3xK...",
      "type": "deposit",
      "subject": "充值未到账",
      "status": "open",
      "priority": 0,
      "reply_count": 1,
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 3, "page": 1, "last_page": 1
}
```

type: deposit / withdraw / game / account / other
status: open / waiting / replied / closed

#### POST /api/v1/ticket/create — Create Ticket
```
Authentication required: Yes
Request: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
Response: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/v1/ticket/{hashid} — Ticket Detail
```
Authentication required: Yes
Response: {
  "id": "...", "type": "deposit", "subject": "...",
  "content": "...", "status": "open",
  "replies": [
    { "id": "...", "content": "...", "is_admin": 1, "created_at": "..." }
  ]
}
```

#### POST /api/v1/ticket/{hashid}/reply — Reply to Ticket
```
Authentication required: Yes
Request: { "content": "已核实，将在24小时内处理" }
Response: { "code": 0, "message": "Reply sent" }
```

### 7.3 Email Verification APIs

#### POST /api/v1/verify/send-email — Send Email Verification Code
```
Authentication required: Yes
Request: { "email": "user@example.com" }
Response: { "code": 0, "message": "Verification code sent" }
Error: 429 请60秒后重试
```

#### POST /api/v1/verify/confirm-email — Confirm Email
```
Authentication required: Yes
Request: { "code": "123456" }
Response: { "code": 0, "message": "Email verified" }
Error: 422 验证码无效或已过期
```

### 7.4 VIP APIs

#### GET /api/v1/user/vip-status — VIP Status

> **Not implemented**: the C-end route is not registered (no entry in `service/config/route.php`), so requests currently return 404. Delete this line once implemented.

```
Authentication required: Yes
Response: {
  "level": 2,
  "level_name": "Gold",
  "exp": 300,
  "total_exp": 2800,
  "next_level": { "level": 3, "name": "Platinum", "required_exp": 12500 },
  "benefits": {
    "exchange_discount": "0.05",
    "withdraw_fee_discount": "0.30",
    "rate_bonus": "0.003"
  }
}
```

### 7.5 Achievement APIs

#### GET /api/v1/user/achievements — Achievement List

> **Not implemented**: the C-end route is not registered (no entry in `service/config/route.php`), so requests currently return 404. Delete this line once implemented.

```
Authentication required: Yes
Response: {
  "achievements": [
    {
      "key": "first_deposit",
      "name": "First Deposit",
      "description": "Make your first deposit",
      "icon": "",
      "points": 20,
      "progress": 1,
      "completed": true
    }
  ]
}
```

### 7.6 New Admin Backend APIs

#### GET /admin/v1/ticket/list — Ticket List
```
Authentication required: Yes
Parameters: ?page=1&limit=20&status=pending&type=deposit

Response: {
  "list": [
    {
      "id": "...", "user_name": "player1",
      "type": "deposit", "subject": "...",
      "status": "open", "reply_count": 0,
      "created_at": "2026-05-22 10:30:00"
    }
  ],
  "total": 5, "page": 1, "limit": 20
}
```

#### POST /admin/v1/ticket/{hashid}/reply — Reply to Ticket
```
Authentication required: Yes
Request: { "content": "已处理" }
Response: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/v1/ticket/{hashid}/close — Close Ticket
```
Authentication required: Yes
Response: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/v1/ticket/{hashid}/assign — Assign Handler
```
Authentication required: Yes
Request: { "admin_id": 1234567890 }
Response: { "code": 0, "message": "Assigned" }
```

#### GET /admin/v1/analytics/retention — Retention Analysis
```
Authentication required: Yes
Parameters: ?days=30
Response: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/v1/analytics/funnel — Conversion Funnel
```
Authentication required: Yes
Response: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/v1/analytics/arpu — ARPU/ARPPU Trend
```
Authentication required: Yes
Parameters: ?days=30
Response: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/v1/analytics/economy — Game Currency Economy Metrics
```
Authentication required: Yes
Response: {
  "currencies": [
    {
      "game_name": "Shooter Master",
      "currency": "Gold",
      "total_minted": "500000.0000",
      "total_burned": "320000.0000",
      "circulation": "180000.0000",
      "inflation_rate": "2.3%"
    }
  ]
}
```


#### GET /admin/v1/cdn/provider/list — List CDN providers (credentials not returned)

```
Authentication required: Yes
Response: { "list": [ { "id": "...", "name": "...", "provider": "cloudflare", "status": 1, "sort": 0 } ] }
```

#### POST /admin/v1/cdn/provider/toggle — Enable/disable provider {id, status}

```
Authentication required: Yes
Request: { "id": "...", "status": 1 }
Response: { "code": 0, "message": "..." }
```

#### POST /admin/v1/cdn/provider/create — Create {name, provider, config(JSON), status, sort}, provider uniqueness check

```
Authentication required: Yes
Request: { "name": "...", "provider": "aliyun", "config": "{...}", "status": 1, "sort": 0 }
Response: { "code": 0, "data": { "id": "..." } }
```

#### PUT /admin/v1/cdn/provider/{hashid} — Update (empty config = unchanged)

```
Authentication required: Yes
Request: { "name": "...", "config": "" }
Response: { "code": 0, "message": "..." }
```

#### DELETE /admin/v1/cdn/provider/{hashid} — Delete

```
Authentication required: Yes
Response: { "code": 0, "message": "..." }
```

#### POST /admin/v1/cdn/provider/test — Connectivity test HeadBucket {id}

```
Authentication required: Yes
Request: { "id": "..." }
Response: { "code": 0, "data": { "ok": true } }
```
#### GET /admin/v1/report/summary — Report summary

```
Authentication required: Yes
Parameters: ?start=Y-m-d&end=Y-m-d (缺省最近30天，跨度 ≤90 天，Redis 缓存5分钟)
Response: {
  "start": "2026-08-01", "end": "2026-08-31",
  "new_users": 120, "deposit_amount": "5000.0000", "deposit_count": 45,
  "withdraw_amount": "1200.0000", "withdraw_count": 8,
  "exchange_amount": "3000.0000", "play_count": 1500
}
```


#### GET /admin/v1/report/daily — Daily report

```
Authentication required: Yes
Parameters: ?start=Y-m-d&end=Y-m-d
Response: {
  "start": "2026-08-01", "end": "2026-08-31",
  "rows": [ { "date": "2026-08-01", "new_users": 12, "deposit_amount": "500.0000", "deposit_count": 4, "withdraw_amount": "100.0000", "withdraw_count": 1, "exchange_amount": "300.0000", "play_count": 150 } ]
}
```


#### GET /admin/v1/report/export — Daily report CSV export

```
Authentication required: Yes
Parameters: ?start=Y-m-d&end=Y-m-d&format=excel
Response: CSV 文件（UTF-8 BOM），文件名 report_{start}_{end}.csv，Excel 可直接打开
```

## 8. Rate Limit Policy (Updated)

| Endpoint | Limit |
|------|------|
| Default | 60 requests/minute/IP |
| POST /api/v1/auth/login | 10 requests/minute |
| POST /api/v1/auth/register | 5 requests/minute |
| POST /api/v1/auth/oauth | 10 requests/minute |
| POST /api/v1/payment/callback | 30 requests/minute |
| POST /api/provider/* | No limit (HMAC signature auth) |

## 9. Authentication Notes (Updated)

### Provider Authentication (ProviderAuth)

1. Extract `X-Game-Id`, `X-Timestamp`, `X-Signature` from the request headers
2. Query the `game_game` table to verify the game exists and status=1
3. Verify the timestamp is within the 5-minute window (anti-replay)
4. Compute `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` and compare against the signature
5. Inject `$request->gameId` and `$request->game`


### 7.7 Friend APIs

#### GET /api/v1/friend/list — Friend List
```
Authentication required: Yes
Response: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/v1/friend/requests — Pending Requests
```
Authentication required: Yes
Response: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/v1/friend/request — Send Friend Request
```
Authentication required: Yes
Request: { "friend_id": "hashid" }
```

#### POST /api/v1/friend/accept — Accept Request
```
Authentication required: Yes
Request: { "request_id": "hashid" }
```

#### POST /api/v1/friend/reject — Reject Request
```
Authentication required: Yes
Request: { "request_id": "hashid" }
```

#### POST /api/v1/friend/remove — Remove Friend
```
Authentication required: Yes
Request: { "friend_id": "hashid" }
```

#### GET /api/v1/friend/search — Search Users
```
Authentication required: Yes
Parameters: ?q=username
Response: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 Chat APIs

#### GET /api/v1/chat/conversations — Conversation List
```
Authentication required: Yes
Response: {
  "list": [{
    "peer": { "id": "...", "username": "...", "nickname": "...", "avatar": "..." },
    "last_message": "最近一条消息",
    "unread_count": 3,
    "updated_at": "2026-05-22 10:30:00"
  }]
}
```

#### GET /api/v1/chat/messages/{peerHashid} — Message List
```
Authentication required: Yes
Parameters: ?page=1&per_page=50
Response: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
自动标记对端发来的未读消息为已读
```

#### POST /api/v1/chat/send — Send Message
```
Authentication required: Yes
Request: { "to_user_id": "hashid", "content": "Hello!" }
Error: 403 非好友不可发
```

#### GET /api/v1/chat/unread-total — Unread Total
```
Authentication required: Yes
Response: { "count": 5 }
```

**WebSocket connection**: `ws://host:8791`
```
// 认证
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// 接收消息
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 Webhook APIs

#### GET /api/v1/webhook/list — Subscription List
```
Authentication required: Yes
Response: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/v1/webhook/register — Register Subscription
```
Authentication required: Yes
Request: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
Available events: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/v1/webhook/delete — Delete Subscription
```
Authentication required: Yes
Request: { "id": "hook_id" }
```

### 7.10 Advanced Analytics APIs

#### GET /admin/v1/analytics/retention — Retention Analysis
```
Authentication required: Yes
Response: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/v1/analytics/funnel — Conversion Funnel
```
Authentication required: Yes
Response: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/v1/analytics/arpu — ARPU/ARPPU Trend
```
Authentication required: Yes
Parameters: ?days=30
Response: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/v1/analytics/economy — Game Economy Metrics
```
Authentication required: Yes
Response: {
  "currencies": [{
    "game_name": "Shooter Master", "currency": "Gold", "symbol": "G",
    "total_minted": "500000.00000000", "total_burned": "320000.00000000",
    "circulation": "180000.00000000", "inflation_rate": "36.00%"
  }]
}
```


### 7.11 Tournament APIs

#### GET /api/v1/tournament/list — Tournament List
```
Parameters: ?status=active|upcoming|ended&page=1&per_page=20
Response: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/v1/tournament/{hashid} — Tournament Detail
```
Response: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/v1/tournament/{hashid}/join — Join Tournament
```
Authentication required: Yes
Error: 422 已报名 / 400 已开始或已满员 / 503 FeatureFlag关闭
```

### 7.12 Coupon Conditions (New)

Coupon `conditions` JSON supports:
- `min_deposit`: string, minimum cumulative deposit amount
- `first_user_only`: bool, only for new users who have never deposited
- `game_id`: int, must have played the specified game

Conditions are double-validated in the `available()` list filter and at `claim()` time.

### 7.13 Multi-Level Referral (New)

Referral commission adds a second level:
- L1: direct referrer receives `referrer_bonus` (config: referral.referrer_bonus)
- L2: the referrer's referrer receives `commission = referrer_bonus * level2_rate` (config: referral.level2_rate, default 5%)
- Recorded in `game_referral_commission` (level/commission_rate/commission_amount)

### 8. Rate Limit Policy (Updated)

| Endpoint | Limit |
|------|------|
| POST /api/v1/tournament/{id}/join | 10 requests/minute |

---

## 10. New APIs (v1.3.15-v1.3.22)

### 10.1 Risk Control Management (Admin :8789)

| Endpoint | Description |
|------|------|
| GET /admin/v1/risk/dashboard | Risk dashboard overview |
| GET /admin/v1/risk/overview | Risk overview metrics |
| GET /admin/v1/risk/hit-trend | Hit trend |
| GET /admin/v1/risk/action-distribution | Action distribution |
| GET /admin/v1/risk/rule-performance | Rule performance |
| GET /admin/v1/risk/rule/list | Rule list |
| POST /admin/v1/risk/rule/create | Create rule |
| PUT /admin/v1/risk/rule/{hashid} | Update rule |
| POST /admin/v1/risk/rule/{hashid}/toggle | Enable/disable rule |
| POST /admin/v1/risk/rule/test | Test rule |
| GET /admin/v1/risk/event/list | Risk event list |
| GET /admin/v1/risk/event/{hashid} | Event detail |
| POST /admin/v1/risk/event/{hashid}/handle | Handle event |
| GET /admin/v1/risk/device/list | Device fingerprint list |
| POST /admin/v1/risk/device/block | Block device |
| POST /admin/v1/risk/device/unblock | Unblock device |
| GET /admin/v1/risk/ip/list | IP list |
| POST /admin/v1/risk/ip/block | Block IP |
| POST /admin/v1/risk/ip/whitelist | IP whitelist |
| POST /admin/v1/risk/ip/appeal | IP appeal |
| POST /admin/v1/risk/ip/recheck | IP recheck |
| GET /admin/v1/risk/graph/clusters | Cluster list |
| GET /admin/v1/risk/graph/{userId} | User link graph |
| GET /admin/v1/risk/clusters | Risk cluster list |
| POST /admin/v1/risk/clusters/detect | Cluster detection (same IP with 5+ accounts / same device fingerprint with 3+ accounts in the last 7 days; candidates only, nothing persisted) |
| POST /admin/v1/risk/clusters/confirm | Manually confirm a cluster and persist it |
| GET /admin/v1/risk/clusters/{hashid}/members | Cluster member list (members resolved from the fingerprint) |
| PUT /admin/v1/risk/clusters/{hashid}/status | Update cluster status (1=watching 2=handled 0=false positive) |
| GET /admin/v1/risk/users | Suspicious user queue (filtered by trust score and last hit time) |
| GET /admin/v1/risk/users/{hashid}/timeline | User risk timeline (risk / gameplay / anti-cheat events merged) |
| POST /admin/v1/risk/users/{hashid}/hold | Freeze the user's available platform balance and write a risk log |

### 10.2 Anti-Cheat Management (Admin :8789)

| Endpoint | Description |
|------|------|
| GET /admin/v1/anticheat/events | Anti-cheat event list |
| GET /admin/v1/anticheat/events/{hashid} | Event detail |
| POST /admin/v1/anticheat/events/{hashid}/review | Review event |

### 10.3 Activities (Admin :8789 + Client :8792)

| Endpoint | Description |
|------|------|
| GET /admin/v1/activities/list | Activity list (Admin) |
| POST /admin/v1/activities/create | Create activity (Admin) |
| PUT /admin/v1/activities/{hashid} | Update activity (Admin) |
| DELETE /admin/v1/activities/{hashid} | Delete activity (Admin) |
| GET /api/v1/activities/list | Activity list (Client) |
| GET /api/v1/activities/progress | Participation progress (Client) |
| GET /api/v1/activities/{hashid} | Activity detail (Client) |
| POST /api/v1/activities/{hashid}/checkin | Check-in (Client) |

### 10.4 Groups / Shares (Client :8792 + Admin :8789)

| Endpoint | Description |
|------|------|
| POST /api/v1/groups | Create group |
| GET /api/v1/groups/{hashid} | Group detail |
| GET /api/v1/groups/{hashid}/members | Member list |
| POST /api/v1/groups/{hashid}/join | Join group |
| POST /api/v1/groups/{hashid}/leave | Leave group |
| PUT /api/v1/groups/{hashid}/role | Member role |
| POST /api/v1/shares | Create share link |
| POST /api/v1/shares/visit | Share visit tracking |
| GET /admin/v1/groups | Group list (Admin) |
| GET /admin/v1/groups/{hashid}/audit | Group audit (Admin) |
| GET /admin/v1/share/stats | Share stats (Admin) |

### 10.5 Payment Gateway Extensions (L1)

| Gateway | Description |
|------|------|
| Adyen | New payment gateway (deposit / callback verification / auto-credit) |
| GrabPay | New payment gateway (deposit / callback verification / auto-credit) |

### 10.6 VIP / Achievements / Search / Receipts (Admin :8789)

VIP levels, achievement configuration, global search and electronic receipt export (admin).

| Endpoint | Description |
|------|------|
| GET /admin/v1/vip/level/list | VIP level list |
| POST /admin/v1/vip/level/create | Create VIP level (level must be unique) |
| PUT /admin/v1/vip/level/{hashid} | Update VIP level |
| DELETE /admin/v1/vip/level/{hashid} | Delete VIP level (rejected while users hold that level) |
| GET /admin/v1/achievement/list | Achievement list |
| POST /admin/v1/achievement/create | Create achievement (duplicate key rejected) |
| PUT /admin/v1/achievement/{hashid} | Update achievement |
| DELETE /admin/v1/achievement/{hashid} | Delete achievement |
| GET /admin/v1/search | Global search (?q= keyword, type=game or user) |
| POST /admin/v1/export/receipt | Export receipt PDF (type=deposit or withdraw, plus order_id) |
