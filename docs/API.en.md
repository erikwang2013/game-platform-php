# API Documentation
<!-- lang-nav -->

Languages: [中文](API.md) · **English** · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Online interactive documentation (with live debugging support):
- C-end service: http://localhost:8788/apidoc/
- Admin backend: http://localhost:8787/apidoc/
- Password: admin123

## 1. Conventions

### 1.1 Base URLs

| End | URL |
|----|------|
| Admin backend | `http://localhost:8787` |
| C-end service | `http://localhost:8788` |

### 1.2 Common Request Headers

```
Content-Type: application/json
API-Version: v1
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
外部: aB3xK9mW2pQ7rT5v  (hashid 字符串)
内部: 1750123456789      (Snowflake BIGINT)
```

### 1.5 Pagination Format

```
请求: ?page=1&per_page=20

响应: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. C-end APIs (service :8788)

### 2.1 Authentication

#### POST /api/auth/register — User Registration
```
请求: {
  "username": "player1",
  "password": "123456",
  "email": "player@example.com"     // 可选
}

响应: {
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

#### POST /api/auth/login — User Login
```
请求: {
  "username": "player1",
  "password": "123456"
}

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "...", ... }
}
```

错误: 401 用户名或密码错误 / 账号已被禁用

#### POST /api/auth/refresh — Refresh Token
```
请求: (Authorization: Bearer <refresh_token>)

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 Wallet

#### GET /api/wallet/info — Wallet Info
```
需认证: 是

响应: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/wallet/transactions — Transaction Records
```
需认证: 是
参数: ?page=1&per_page=20&type=deposit    (type 可选)

响应: {
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

type 可选值: deposit / withdraw / exchange_in / exchange_out / game_earn / game_spend
```

### 2.3 Deposit

#### POST /api/deposit/create — Create Deposit Order
```
需认证: 是

请求: {
  "amount": "10.00",
  "currency": "USD",
  "payment_method_id": "aB3xK..."
}

响应: {
  "order_id": "aB3xK...",
  "order_no": "DEP202605221030000123",
  "amount": "10.00",
  "platform_amount": "10.0000",
  "checkout_url": "https://checkout.stripe.com/...",
  "expires_at": "2026-05-22 11:30:00"
}
```

currency 可选值: USD / CNY / EUR

checkout_url: payment gateway redirect link (filled in at order creation); expires_at: payment link expiry (1 hour after creation)

#### GET /api/deposit/orders — Deposit Records
```
需认证: 是
参数: ?page=1&per_page=20

响应: {
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

status 可选值: pending / paid / confirmed / cancelled

### 2.4 Exchange

#### POST /api/exchange/quote — Quote
```
需认证: 是

请求: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "direction": "in",
  "platform_amount": "10.0000"
}

响应: {
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000",
  "spread_pct": "5.00%"
}
```

direction: in=买入游戏币 / out=卖出游戏币

#### POST /api/exchange/buy — Buy Game Currency
```
需认证: 是

请求: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "10.0000"
}

响应: {
  "exchange_id": "aB3xK...",
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000"
}
```

错误: 422 平台币余额不足 / 404 游戏不可用

#### POST /api/exchange/sell — Sell Game Currency
```
需认证: 是

请求: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "950.0000"
}

响应: {
  "exchange_id": "aB3xK...",
  "platform_amount": "9.0250",
  "game_amount": "950.0000",
  "spread_fee": "0.4750",
  "rate": "100.00000000"
}
```

错误: 422 游戏币余额不足

#### GET /api/exchange/records — Exchange Records
```
需认证: 是
参数: ?page=1&per_page=20

响应: {
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

#### POST /api/withdraw/apply — Withdrawal Application
```
需认证: 是

请求: {
  "platform_amount": "50.0000",
  "method": "paypal",
  "account_info": "user@paypal.com"
}

响应: {
  "order_id": "...",
  "order_no": "WTH202605221030000456",
  "status": "approved"
}
```

method 可选值: paypal / bank / crypto

status:
- approved: 自动通过（金额 < auto_approve_threshold）
- pending: 待审核（金额 >= auto_approve_threshold）

错误:
- 403 提现功能暂时关闭（全局开关关闭）
- 400 低于最低提现金额
- 400 超过每日提现限额
- 400 余额不足

#### GET /api/withdraw/orders — Withdrawal Records
```
需认证: 是
参数: ?page=1&per_page=20

响应: {
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

#### GET /api/game/list — Game List
```
参数: ?page=1&per_page=20&keyword=射击&type=self

响应: {
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

type 可选值: self / third_party

#### GET /api/game/{hashid} — Game Detail
```
响应: {
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

#### POST /api/game/launch — Launch Game
```
需认证: 是

请求: { "game_id": "aB3xK..." }

响应: {
  "id": "...",
  "name": "射击大师",
  "type": "self",
  "api_endpoint": "https://game.example.com/play"
}
```

### 2.7 OAuth Third-Party Login

7 platforms supported: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/auth/oauth/{provider} — Get Authorization URL
```
参数: provider = google / facebook / apple / twitter / microsoft / linkedin / github

响应: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/auth/oauth/{provider}/callback — OAuth Callback
```
请求: { "code": "授权码", "state": "防CSRF状态" }

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=新注册用户 / false=已有账号绑定

### 2.8 KYC Real-Name Verification

#### GET /api/user/identity/status — Verification Status
```
需认证: 是

响应: {
  "status": "approved",          // not_submitted / pending / approved / rejected
  "real_name": "J***",
  "id_type": "id_card",
  "review_note": "",
  "submitted_at": "2026-05-22 10:00:00",
  "reviewed_at": "2026-05-23 14:00:00"
}
```

#### POST /api/user/identity/apply — Submit Verification
```
需认证: 是

请求: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}

响应: { "message": "KYC submitted successfully" }
```

### 2.9 Payments

#### POST /api/payment/callback — Payment Callback (public)
```
请求: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

响应: { "message": "success" }
```

status: success / failed

provider values: stripe / paypal / nowpayments / coinbase (nowpayments verifies via IPN HMAC-SHA512, coinbase via webhook HMAC-SHA256)

#### GET /api/payment/methods — Available Payment Methods (public)
```
响应: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

Filtered by user country (X-Language/Accept-Language → country code mapping): empty countries or containing * means globally visible; sorted by that country's country_config payment-method preference

### 2.10 Game Play Logs

#### GET /api/game/play-logs — Game Play Log List
```
需认证: 是
参数: ?page=1&per_page=20&game_id=xxx&action=start

响应: {
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

#### GET /api/game/play-log/{hashid} — Game Play Log Detail
```
需认证: 是
响应: { 完整记录，含 session_id / game_amount_before / after 等 }
```

### 2.12 Leaderboards

#### GET /api/leaderboard/list — Leaderboard List
```
响应: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/leaderboard/{hashid} — Leaderboard Detail
```
响应: {
  "id": "...",
  "name": "全服累计收入榜",
  "type": "total",
  "rankings": [
    { "rank": 1, "user_id": "...", "score": "50000.0000" }
  ]
}
```

### 2.13 Coupons

#### GET /api/coupon/available — Available Coupons
```
需认证: 是
响应: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/coupon/claim — Claim Coupon
```
需认证: 是
请求: { "coupon_id": "hashid" }
响应: { "coupon": { ... } }
```

#### GET /api/coupon/my — My Coupons
```
需认证: 是
参数: ?status=unused
响应: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 Country Config

#### GET /api/country/list — Country List
```
响应: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/country/{code} — Country Detail
```
响应: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 Notifications

#### GET /api/notification/list — Notification List
```
需认证: 是
参数: ?page=1&per_page=20&is_read=0

响应: {
  "list": [
    { "id": "...", "type": "deposit", "title": "Deposit Received", "is_read": 0, "created_at": "..." }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### GET /api/notification/unread-count — Unread Count
```
需认证: 是
响应: { "count": 3 }
```

#### POST /api/notification/read — Mark as Read
```
需认证: 是
请求: { "id": "hashid" }  // 不传=全部已读
```

### 2.17 Referrals

#### GET /api/referral/my-code — My Referral Code
```
需认证: 是
响应: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/referral/apply — Apply Referral Code
```
需认证: 是
请求: { "code": "ABC12345" }
响应: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/user/2fa/status — 2FA Status
```
需认证: 是
响应: { "enabled": false }
```

#### POST /api/user/2fa/setup — Set Up 2FA
```
需认证: 是
响应: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/user/2fa/enable — Enable 2FA
```
需认证: 是
请求: { "code": "123456" }
响应: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/2fa/verify — Verify 2FA (public)
```
请求: { "user_id": "hashid", "code": "123456" }
响应: { "valid": true }
```

### 2.19 Search

#### GET /api/search — Global Search
```
参数: ?q=keyword&type=game&page=1&per_page=20
响应: { "list": [...], "total": 100 }
```

#### GET /api/game/suggest — Search Suggestions
```
参数: ?q=shoot
响应: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 Languages

#### GET /api/language/list — Available Languages
```
响应: {
  "current": "en-US",
  "languages": {
    "en-US": { "name": "English", "nativeName": "English", "icon": "us" },
    "zh-CN": { "name": "Chinese (Simplified)", "nativeName": "简体中文", "icon": "cn" },
    "ja-JP": { "name": "Japanese", "nativeName": "日本語", "icon": "jp" },
    "ko-KR": { "name": "Korean", "nativeName": "한국어", "icon": "kr" }
  }
}
```

#### POST /api/language/switch — Switch Language
```
请求: { "locale": "zh-CN" }
响应: { "locale": "zh-CN" }
```

locale 可选值: en-US / zh-CN / ja-JP / ko-KR

### 2.8 User

#### GET /api/user/profile — Personal Profile
```
需认证: 是

响应: {
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

#### PUT /api/user/profile — Edit Profile
```
需认证: 是

请求: {
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}

响应: {
  "id": "...",
  "username": "player1",
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}
```

language 可选值: en-US / zh-CN / ja-JP / ko-KR

### 2.9 Announcements

#### GET /api/announcement/list — Announcement List
```
响应: {
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

#### GET /api/announcement/detail/{hashid} — Announcement Detail
```
响应: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

## 3. Admin Backend APIs (admin :8787)

### 3.1 Platform Dashboard

#### GET /admin/dashboard/platform

```
需认证: 是 (AdminAuth + AdminPermission)

响应: {
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

#### GET /admin/game/list — Game List
```
需认证: 是
参数: ?page=1&per_page=20&keyword=射击

响应: {
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
  "per_page": 20
}
```

#### POST /admin/game/create — Create Game
```
需认证: 是

请求: {
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

响应: { "id": "aB3xK..." }
```

type 可选值: self / third_party

#### PUT /admin/game/{hashid} — Edit Game
```
需认证: 是

请求: {
  "name": "新名称",
  "status": 1
  // 可部分更新，字段同 create
}

响应: { "message": "更新成功" }
```

#### DELETE /admin/game/{hashid} — Delete Game
```
需认证: 是
响应: { "message": "删除成功" }
```

#### POST /admin/game/currency/manage — Manage Currencies
```
需认证: 是

请求: {
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

响应: { "message": "币种更新成功" }
```

### 3.3 Withdrawal Management

#### GET /admin/withdraw/orders — Withdrawal Order List
```
需认证: 是
参数: ?page=1&per_page=20&status=pending

响应: {
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
  "per_page": 20
}
```

#### PUT /admin/withdraw/review — Review Withdrawal
```
需认证: 是

请求: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

响应: { "message": "已通过" }
```

action: approve=通过 / reject=拒绝（拒绝时自动退回平台币）

错误: 422 订单状态不是待审核

#### PUT /admin/withdraw/switch — Global Withdrawal Switch
```
需认证: 是

请求: { "enabled": 1 }

响应: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/withdraw/limits/set — Set Withdrawal Limits
```
需认证: 是

请求: {
  "daily_limit": "10000.0000",             // 可选
  "min_amount": "1.0000",                  // 可选
  "auto_approve_threshold": "100.0000"     // 可选
}

响应: {
  "daily_limit": "10000.0000",
  "min_amount": "1.0000",
  "auto_approve_threshold": "100.0000",
  "global_switch": true
}
```

### 3.4 Platform User Management

#### GET /admin/platform/user/list — C-end User List
```
需认证: 是
参数: ?page=1&per_page=20&keyword=player&status=1

响应: {
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
  "per_page": 20
}
```

#### GET /admin/platform/user/{hashid} — User Detail
```
需认证: 是

响应: {
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

#### PUT /admin/platform/user/{hashid} — Edit/Ban User
```
需认证: 是

请求: {
  "status": 0,         // 0=禁用 1=启用
  "nickname": "..."    // 可选
}

响应: { "message": "更新成功" }
```

### 3.5 Payment Management

#### GET /admin/payment/method/list

```
需认证: 是

响应: {
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

#### POST /admin/payment/method/toggle — Enable/Disable Payment Method
```
需认证: 是

请求: { "id": "aB3xK...", "status": 0 }

响应: { "message": "已更新" }
```

### 3.6 Announcement Management

#### GET /admin/announcement/list

```
需认证: 是
参数: ?page=1&per_page=20

响应: {
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
  "per_page": 20
}
```

#### POST /admin/announcement/create — Publish Announcement
```
需认证: 是

请求: {
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护。",
  "type": "system",           // 可选, 默认"system"
  "target_lang": "",          // 可选, 空=全语言
  "status": 1,                // 可选, 默认1 (0=草稿 1=发布)
  "start_at": "2026-05-23 02:00:00",  // 可选
  "end_at": "2026-05-23 04:00:00"     // 可选
}

响应: { "id": "aB3xK..." }
```

### 3.7 KYC Review

#### GET /admin/identity/list — KYC List
```
需认证: 是
参数: ?page=1&per_page=20&status=pending

响应: {
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
  "total": 5, "page": 1, "per_page": 20
}
```

#### PUT /admin/identity/review — Review KYC
```
需认证: 是

请求: { "id": "hashid", "action": "approve", "note": "" }

响应: { "message": "Approved" }
```

action: approve / reject

### 3.8 Game Server Management

#### GET /admin/game/server/list — Server List
```
需认证: 是
参数: ?game_id=hashid

响应: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/game/server/create — Create Server
```
需认证: 是
请求: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
响应: { "id": "hashid" }
```

#### PUT /admin/game/server/{hashid} — Edit Server
```
需认证: 是
请求: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/game/server/{hashid} — Delete Server
```
需认证: 是
```

### 3.9 Withdrawal Tier Limits Management

#### GET /admin/withdraw/limits/list

```
需认证: 是

响应: {
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

#### PUT /admin/withdraw/limits/{hashid} — Update Limits
```
需认证: 是

请求: { "single_max": "10000.0000", "fee_pct": "0.25" }
// 可部分更新
```

### 3.11 Game Category Management

#### GET /admin/game/category/list

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "动作", "slug": "action", "sort": 1 }] }
```

#### POST /admin/game/category/create

```
需认证: 是
请求: { "name": "新分类", "slug": "new-cat", "icon": "star", "sort": 10 }
响应: { "id": "hashid" }
```

#### PUT /admin/game/category/{hashid} — Edit Category
#### DELETE /admin/game/category/{hashid} — Delete Category
#### POST /admin/game/category/assign — Assign Games
```
需认证: 是
请求: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 Leaderboard Management

#### GET /admin/leaderboard/list — Leaderboard List
```
需认证: 是
响应: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/leaderboard/create — Create Leaderboard
```
需认证: 是
请求: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(可选)" }
```

#### PUT /admin/leaderboard/{hashid} — Edit Leaderboard
#### DELETE /admin/leaderboard/{hashid} — Delete Leaderboard
#### POST /admin/leaderboard/{hashid}/refresh — Refresh Cache
### 3.13 Coupon Management

#### GET /admin/coupon/list — Coupon List
#### POST /admin/coupon/create — Create Coupon
```
需认证: 是
请求: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/coupon/{hashid} — Edit (when unclaimed)
#### DELETE /admin/coupon/{hashid} — Delete
#### GET /admin/coupon/{hashid}/stats — Claim Statistics
```
响应: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 Country Config Management

#### GET /admin/country/config/list — Country Config List
#### POST /admin/country/config/create — Create Country Config
```
需认证: 是
请求: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/country/config/{hashid} — Edit Country Config
### 3.15 Data Export

#### POST /admin/export/users — Export C-end Users
```
需认证: 是
参数(JSON): { "status": 1 }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

#### POST /admin/export/transactions — Export Platform Transactions
```
需认证: 是
参数(JSON): { "type": "deposit" }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

### 3.16 Data Analytics (MySQL real-time aggregation)

All endpoints require authentication (AdminAuth + AdminPermission); data is aggregated in real time from MySQL, not dependent on ClickHouse.

| Method | Path | Description |
|------|------|------|
| GET | /admin/analytics/overview | Platform overview (today/last 7 days) |
| GET | /admin/analytics/game-ranking | Game ranking (?days=7) |
| GET | /admin/analytics/dau-trend | DAU trend (?days=30) |
| GET | /admin/analytics/hourly-trend | Hourly trend |
| GET | /admin/analytics/action-distribution | Action distribution |
| GET | /admin/analytics/revenue | Revenue analysis |
| GET | /admin/analytics/conversion | Game conversion rate |
| GET | /admin/analytics/probability | Joint/conditional probability |
| GET | /admin/analytics/retention | Retention analysis D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Conversion funnel |
| GET | /admin/analytics/arpu | ARPU/ARPPU trend |
| GET | /admin/analytics/economy | Game currency economy metrics |

### 3.17 Ticket Management

All endpoints require authentication (AdminAuth + AdminPermission).

| Method | Path | Description |
|------|------|------|
| GET | /admin/ticket/list | Ticket list (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | Ticket detail (incl. replies) |
| POST | /admin/ticket/{hashid}/reply | Reply to ticket |
| POST | /admin/ticket/{hashid}/close | Close ticket |
| POST | /admin/ticket/{hashid}/assign | Assign handler (admin_id) |

## 4. Rate Limit Policy

| Endpoint | Limit |
|------|------|
| Default | 60 requests/minute/IP |
| POST /api/auth/login | 10 requests/minute |
| POST /api/auth/register | 5 requests/minute |

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
请求头:
  X-Game-Id: 1234567890
  X-Timestamp: 1716400830
  X-Signature: abc123...

请求: {
  "user_id": 1234567890,
  "game_id": 9876543210,
  "currency_id": 5555555555
}

响应: {
  "code": 0,
  "message": "success",
  "data": { "balance": "1000.50000000" }
}
```

#### POST /api/provider/bet — Notify Bet
```
请求: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "bet_type": "straight" }
}

响应: {
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
请求: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}

响应: {
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
请求: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "reason": "game_crash"
}

响应: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1000.50000000"
  }
}
```

### 7.2 Ticket APIs

#### GET /api/ticket/list — Ticket List
```
需认证: 是
参数: ?page=1&per_page=20

响应: {
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

#### POST /api/ticket/create — Create Ticket
```
需认证: 是
请求: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
响应: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/ticket/{hashid} — Ticket Detail
```
需认证: 是
响应: {
  "id": "...", "type": "deposit", "subject": "...",
  "content": "...", "status": "open",
  "replies": [
    { "id": "...", "content": "...", "is_admin": 1, "created_at": "..." }
  ]
}
```

#### POST /api/ticket/{hashid}/reply — Reply to Ticket
```
需认证: 是
请求: { "content": "已核实，将在24小时内处理" }
响应: { "code": 0, "message": "Reply sent" }
```

### 7.3 Email Verification APIs

#### POST /api/verify/send-email — Send Email Verification Code
```
需认证: 是
请求: { "email": "user@example.com" }
响应: { "code": 0, "message": "Verification code sent" }
错误: 429 请60秒后重试
```

#### POST /api/verify/confirm-email — Confirm Email
```
需认证: 是
请求: { "code": "123456" }
响应: { "code": 0, "message": "Email verified" }
错误: 422 验证码无效或已过期
```

### 7.4 VIP APIs

#### GET /api/user/vip-status — VIP Status
```
需认证: 是
响应: {
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

#### GET /api/user/achievements — Achievement List
```
需认证: 是
响应: {
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

#### GET /admin/ticket/list — Ticket List
```
需认证: 是
参数: ?page=1&limit=20&status=pending&type=deposit

响应: {
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

#### POST /admin/ticket/{hashid}/reply — Reply to Ticket
```
需认证: 是
请求: { "content": "已处理" }
响应: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/ticket/{hashid}/close — Close Ticket
```
需认证: 是
响应: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/ticket/{hashid}/assign — Assign Handler
```
需认证: 是
请求: { "admin_id": 1234567890 }
响应: { "code": 0, "message": "Assigned" }
```

#### GET /admin/analytics/retention — Retention Analysis
```
需认证: 是
参数: ?days=30
响应: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/analytics/funnel — Conversion Funnel
```
需认证: 是
响应: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — ARPU/ARPPU Trend
```
需认证: 是
参数: ?days=30
响应: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/analytics/economy — Game Currency Economy Metrics
```
需认证: 是
响应: {
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

## 8. Rate Limit Policy (Updated)

| Endpoint | Limit |
|------|------|
| Default | 60 requests/minute/IP |
| POST /api/auth/login | 10 requests/minute |
| POST /api/auth/register | 5 requests/minute |
| POST /api/auth/oauth | 10 requests/minute |
| POST /api/payment/callback | 30 requests/minute |
| POST /api/provider/* | No limit (HMAC signature auth) |

## 9. Authentication Notes (Updated)

### Provider Authentication (ProviderAuth)

1. Extract `X-Game-Id`, `X-Timestamp`, `X-Signature` from the request headers
2. Query the `game_game` table to verify the game exists and status=1
3. Verify the timestamp is within the 5-minute window (anti-replay)
4. Compute `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` and compare against the signature
5. Inject `$request->gameId` and `$request->game`


### 7.7 Friend APIs

#### GET /api/friend/list — Friend List
```
需认证: 是
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/friend/requests — Pending Requests
```
需认证: 是
响应: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/friend/request — Send Friend Request
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### POST /api/friend/accept — Accept Request
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/friend/reject — Reject Request
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/friend/remove — Remove Friend
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### GET /api/friend/search — Search Users
```
需认证: 是
参数: ?q=username
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 Chat APIs

#### GET /api/chat/conversations — Conversation List
```
需认证: 是
响应: {
  "list": [{
    "peer": { "id": "...", "username": "...", "nickname": "...", "avatar": "..." },
    "last_message": "最近一条消息",
    "unread_count": 3,
    "updated_at": "2026-05-22 10:30:00"
  }]
}
```

#### GET /api/chat/messages/{peerHashid} — Message List
```
需认证: 是
参数: ?page=1&per_page=50
响应: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
自动标记对端发来的未读消息为已读
```

#### POST /api/chat/send — Send Message
```
需认证: 是
请求: { "to_user_id": "hashid", "content": "Hello!" }
错误: 403 非好友不可发
```

#### GET /api/chat/unread-total — Unread Total
```
需认证: 是
响应: { "count": 5 }
```

**WebSocket 连接**: `ws://host:8791`
```
// 认证
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// 接收消息
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 Webhook APIs

#### GET /api/webhook/list — Subscription List
```
需认证: 是
响应: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/webhook/register — Register Subscription
```
需认证: 是
请求: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
可用事件: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/webhook/delete — Delete Subscription
```
需认证: 是
请求: { "id": "hook_id" }
```

### 7.10 Advanced Analytics APIs

#### GET /admin/analytics/retention — Retention Analysis
```
需认证: 是
响应: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/analytics/funnel — Conversion Funnel
```
需认证: 是
响应: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — ARPU/ARPPU Trend
```
需认证: 是
参数: ?days=30
响应: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/analytics/economy — Game Economy Metrics
```
需认证: 是
响应: {
  "currencies": [{
    "game_name": "Shooter Master", "currency": "Gold", "symbol": "G",
    "total_minted": "500000.00000000", "total_burned": "320000.00000000",
    "circulation": "180000.00000000", "inflation_rate": "36.00%"
  }]
}
```


### 7.11 Tournament APIs

#### GET /api/tournament/list — Tournament List
```
参数: ?status=active|upcoming|ended&page=1&per_page=20
响应: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/tournament/{hashid} — Tournament Detail
```
响应: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/tournament/{hashid}/join — Join Tournament
```
需认证: 是
错误: 422 已报名 / 400 已开始或已满员 / 503 FeatureFlag关闭
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
| POST /api/tournament/{id}/join | 10 requests/minute |
