# 接口文档
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · **বাংলা** · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

অনলাইন ইন্টারঅ্যাকটিভ ডকুমেন্টেশন (অনলাইন ডিবাগিং সাপোর্ট):
- C-এন্ড ব্যবসা: http://localhost:8788/apidoc/
- অ্যাডমিন প্যানেল: http://localhost:8787/apidoc/
- পাসওয়ার্ড: admin123

## 1. নিয়মাবলী

### 1.1 বেস URL

| এন্ড | ঠিকানা |
|----|------|
| অ্যাডমিন প্যানেল | `http://localhost:8787` |
| C-এন্ড ব্যবসা | `http://localhost:8788` |

### 1.2 সাধারণ রিকোয়েস্ট হেডার

```
Content-Type: application/json
API-Version: v1
Authorization: Bearer <token>    (অথেনটিকেশন প্রয়োজন এমন ইন্টারফেস)
```

### 1.3 ইউনিফাইড রেসপন্স ফরম্যাট

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | অর্থ |
|------|------|
| 0 | সফল |
| 400 | প্যারামিটার ত্রুটি |
| 401 | অনথেনটিকেটেড (Token অনুপস্থিত/মেয়াদোত্তীর্ণ/অবৈধ) |
| 403 | কোনো পারমিশন নেই |
| 404 | রিসোর্স নেই |
| 422 | ভেরিফিকেশন ব্যর্থ |
| 429 | অতিরিক্ত রিকোয়েস্ট (রেট লিমিট ট্রিগার হয়েছে) |
| 500 | সার্ভার ত্রুটি |

### 1.4 ID এনকোডিং

সব API রিকোয়েস্ট ও রেসপন্সে থাকা ID হলো Hashids-এনকোডেড স্ট্রিং, মূল BIGINT মান নয়।

```
বাহ্যিক: aB3xK9mW2pQ7rT5v  (hashid স্ট্রিং)
অভ্যন্তরীণ: 1750123456789      (Snowflake BIGINT)
```

### 1.5 পেজিনেশন ফরম্যাট

```
রিকোয়েস্ট: ?page=1&per_page=20

রেসপন্স: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. C-এন্ড ইন্টারফেস (service :8788)

### 2.1 অথেনটিকেশন

#### POST /api/auth/register — ইউজার রেজিস্ট্রেশন

```
রিকোয়েস্ট: {
  "username": "player1",
  "password": "123456",
  "email": "player@example.com"     // ঐচ্ছিক
}

রেসপন্স: {
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

#### POST /api/auth/login — ইউজার লগইন

```
রিকোয়েস্ট: {
  "username": "player1",
  "password": "123456"
}

রেসপন্স: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "...", ... }
}
```

ত্রুটি: 401 ইউজারনেম বা পাসওয়ার্ড ভুল / অ্যাকাউন্ট ডিসেবল করা হয়েছে

#### POST /api/auth/refresh — Token রিফ্রেশ

```
রিকোয়েস্ট: (Authorization: Bearer <refresh_token>)

রেসপন্স: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 ওয়ালেট

#### GET /api/wallet/info — ওয়ালেট তথ্য

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রেসপন্স: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/wallet/transactions — লেজার রেকর্ড

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&per_page=20&type=deposit    (type ঐচ্ছিক)

রেসপন্স: {
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

type এর মান: deposit / withdraw / exchange_in / exchange_out / game_earn / game_spend
```

### 2.3 টপ-আপ

#### POST /api/deposit/create — টপ-আপ অর্ডার তৈরি

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "amount": "10.00",
  "currency": "USD",
  "payment_method_id": "aB3xK..."
}

রেসপন্স: {
  "order_id": "aB3xK...",
  "order_no": "DEP202605221030000123",
  "amount": "10.00",
  "platform_amount": "10.0000",
  "checkout_url": "https://checkout.stripe.com/...",
  "expires_at": "2026-05-22 11:30:00"
}
```

currency এর মান: USD / CNY / EUR

checkout_url: পেমেন্ট গেটওয়ে রিডাইরেক্ট লিংক (অর্ডার তৈরির সময় পূরণ করা হয়); expires_at: পেমেন্ট লিংকের মেয়াদ (তৈরির ১ ঘণ্টা পরে)

#### GET /api/deposit/orders — টপ-আপ রেকর্ড

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&per_page=20

রেসপন্স: {
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

status এর মান: pending / paid / confirmed / cancelled

### 2.4 বিনিময়

#### POST /api/exchange/quote — মূল্য জিজ্ঞাসা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "direction": "in",
  "platform_amount": "10.0000"
}

রেসপন্স: {
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000",
  "spread_pct": "5.00%"
}
```

direction: in=গেম কয়েন কেনা / out=গেম কয়েন বিক্রি

#### POST /api/exchange/buy — গেম কয়েন কেনা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "10.0000"
}

রেসপন্স: {
  "exchange_id": "aB3xK...",
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000"
}
```

ত্রুটি: 422 প্ল্যাটফর্ম কয়েন ব্যালেন্স অপর্যাপ্ত / 404 গেম অনুপলব্ধ

#### POST /api/exchange/sell — গেম কয়েন বিক্রি

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "950.0000"
}

রেসপন্স: {
  "exchange_id": "aB3xK...",
  "platform_amount": "9.0250",
  "game_amount": "950.0000",
  "spread_fee": "0.4750",
  "rate": "100.00000000"
}
```

ত্রুটি: 422 গেম কয়েন ব্যালেন্স অপর্যাপ্ত

#### GET /api/exchange/records — বিনিময় রেকর্ড

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&per_page=20

রেসপন্স: {
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

### 2.5 উত্তোলন

#### POST /api/withdraw/apply — উত্তোলন আবেদন

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "platform_amount": "50.0000",
  "method": "paypal",
  "account_info": "user@paypal.com"
}

রেসপন্স: {
  "order_id": "...",
  "order_no": "WTH202605221030000456",
  "status": "approved"
}
```

method এর মান: paypal / bank / crypto

status:
- approved: স্বয়ংক্রিয়ভাবে অনুমোদিত (পরিমাণ < auto_approve_threshold)
- pending: রিভিউ অপেক্ষমাণ (পরিমাণ >= auto_approve_threshold)

ত্রুটি:
- 403 উত্তোলন সাময়িকভাবে বন্ধ (গ্লোবাল সুইচ বন্ধ)
- 400 সর্বনিম্ন উত্তোলন পরিমাণের চেয়ে কম
- 400 দৈনিক উত্তোলন সীমা অতিক্রম করেছে
- 400 ব্যালেন্স অপর্যাপ্ত

#### GET /api/withdraw/orders — উত্তোলন রেকর্ড

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&per_page=20

রেসপন্স: {
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

### 2.6 গেম

#### GET /api/game/list — গেম তালিকা

```
প্যারামিটার: ?page=1&per_page=20&keyword=射击&type=self

রেসপন্স: {
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

type এর মান: self / third_party

#### GET /api/game/{hashid} — গেম বিবরণ

```
রেসপন্স: {
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

#### POST /api/game/launch — গেম লঞ্চ

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: { "game_id": "aB3xK..." }

রেসপন্স: {
  "id": "...",
  "name": "射击大师",
  "type": "self",
  "api_endpoint": "https://game.example.com/play"
}
```

### 2.7 OAuth থার্ড-পার্টি লগইন

৭টি প্ল্যাটফর্ম সাপোর্ট করে: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/auth/oauth/{provider} — অথরাইজেশন URL পাওয়া

```
প্যারামিটার: provider = google / facebook / apple / twitter / microsoft / linkedin / github

রেসপন্স: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/auth/oauth/{provider}/callback — OAuth কলব্যাক

```
রিকোয়েস্ট: { "code": "授权码", "state": "防CSRF状态" }

রেসপন্স: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=নতুন রেজিস্টার্ড ইউজার / false=বিদ্যমান অ্যাকাউন্ট লিংকড

### 2.8 KYC রিয়েল-নেম ভেরিফিকেশন

#### GET /api/user/identity/status — ভেরিফিকেশন স্ট্যাটাস

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রেসপন্স: {
  "status": "approved",          // not_submitted / pending / approved / rejected
  "real_name": "J***",
  "id_type": "id_card",
  "review_note": "",
  "submitted_at": "2026-05-22 10:00:00",
  "reviewed_at": "2026-05-23 14:00:00"
}
```

#### POST /api/user/identity/apply — ভেরিফিকেশন সাবমিট

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}

রেসপন্স: { "message": "KYC submitted successfully" }
```

### 2.9 পেমেন্ট

#### POST /api/payment/callback — পেমেন্ট কলব্যাক (পাবলিক)

```
রিকোয়েস্ট: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

রেসপন্স: { "message": "success" }
```

status: success / failed

provider এর মান: stripe / paypal / nowpayments / coinbase / skrill / neteller / paysafecard / paytm / mercadopago / astropay / paypay / kakaopay / gcash (toss / mpesa / paystack শীঘ্রই আসছে)

| provider | অঞ্চল | স্বাক্ষর পদ্ধতি | সমর্থিত মুদ্রা |
|----------|-------|----------------|----------------|
| stripe | বৈশ্বিক (125+ স্থানীয় পেমেন্ট পদ্ধতি, Alipay/WeChat Pay APM সহ) | Webhook HMAC-SHA256 | USD / CNY / EUR |
| paypal | বিশ্বের 200+ বাজার | Webhook যাচাই (verify-webhook-signature) | USD / CNY / EUR এবং অন্যান্য ফিয়াট |
| nowpayments | বৈশ্বিক (ক্রিপ্টো) | IPN HMAC-SHA512 | USDT TRC20 / ERC20 |
| coinbase | বৈশ্বিক (ক্রিপ্টো) | Webhook HMAC-SHA256 (base64 secret) | USDC / BTC / ETH |
| skrill | ইউরোপ / বৈশ্বিক | Secret word MD5 যাচাই | EUR এবং অন্যান্য ফিয়াট |
| neteller | ইউরোপ / বৈশ্বিক | Secret key ফিল্ড তুলনা | EUR এবং অন্যান্য ফিয়াট |
| paysafecard | ইউরোপ (DE / AT / CH ইত্যাদি) | X-Signature HMAC-SHA256 | EUR এবং অন্যান্য ফিয়াট |
| paytm | ভারত | SHA256 + AES-128-CBC | INR |
| mercadopago | লাতিন আমেরিকা (BR / MX ইত্যাদি) | X-Signature (ts,v1) HMAC-SHA256 | BRL / MXN এবং অন্যান্য ফিয়াট |
| astropay | লাতিন আমেরিকা (BR ইত্যাদি) | MD5(order_id.amount.status.secret) | BRL এবং অন্যান্য ফিয়াট |
| paypay | জাপান | PayPay-Signature HMAC-SHA256 | JPY |
| kakaopay | দক্ষিণ কোরিয়া | Webhook নেই (ready/approve দুই-ধাপ) | KRW |
| gcash | ফিলিপাইন | Paymongo-Signature HMAC-SHA256 | PHP |
| toss | দক্ষিণ কোরিয়া (শীঘ্রই) | — | KRW |
| mpesa | কেনিয়া / তানজানিয়া ইত্যাদি (শীঘ্রই) | — | KES / TZS |
| paystack | নাইজেরিয়া (শীঘ্রই) | — | NGN |

#### GET /api/payment/methods — উপলব্ধ পেমেন্ট পদ্ধতি (পাবলিক)

```
রেসপন্স: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

ব্যবহারকারীর দেশ অনুযায়ী ফিল্টার করা হয় (X-Language/Accept-Language → দেশের কোড): countries খালি বা * থাকলে বিশ্বব্যাপী দৃশ্যমান; সে দেশের country_config পেমেন্ট পছন্দ অনুযায়ী সাজানো হয়

### 2.10 গেম রেকর্ড

#### GET /api/game/play-logs — গেম রেকর্ড তালিকা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&per_page=20&game_id=xxx&action=start

রেসপন্স: {
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

#### GET /api/game/play-log/{hashid} — গেম রেকর্ড বিবরণ

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { সম্পূর্ণ রেকর্ড, session_id / game_amount_before / after সহ }
```

### 2.12 লিডারবোর্ড

#### GET /api/leaderboard/list — লিডারবোর্ড তালিকা

```
রেসপন্স: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/leaderboard/{hashid} — লিডারবোর্ড বিবরণ

```
রেসপন্স: {
  "id": "...",
  "name": "全服累计收入榜",
  "type": "total",
  "rankings": [
    { "rank": 1, "user_id": "...", "score": "50000.0000" }
  ]
}
```

### 2.13 কুপন

#### GET /api/coupon/available — গ্রহণযোগ্য কুপন

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/coupon/claim — কুপন গ্রহণ

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "coupon_id": "hashid" }
রেসপন্স: { "coupon": { ... } }
```

#### GET /api/coupon/my — আমার কুপন

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?status=unused
রেসপন্স: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 দেশ কনফিগ

#### GET /api/country/list — দেশের তালিকা

```
রেসপন্স: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/country/{code} — দেশের বিবরণ

```
রেসপন্স: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 নোটিফিকেশন

#### GET /api/notification/list — নোটিফিকেশন তালিকা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&per_page=20&is_read=0

রেসপন্স: {
  "list": [
    { "id": "...", "type": "deposit", "title": "Deposit Received", "is_read": 0, "created_at": "..." }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### GET /api/notification/unread-count — অপঠিত সংখ্যা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "count": 3 }
```

#### POST /api/notification/read — পড়া হিসেবে চিহ্নিত

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "id": "hashid" }  // না দিলে = সব পড়া
```

### 2.17 রেফারেল

#### GET /api/referral/my-code — আমার রেফারেল কোড

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/referral/apply — রেফারেল কোড ব্যবহার

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "code": "ABC12345" }
রেসপন্স: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/user/2fa/status — 2FA স্ট্যাটাস

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "enabled": false }
```

#### POST /api/user/2fa/setup — 2FA সেটআপ

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/user/2fa/enable — 2FA এনাবল

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "code": "123456" }
রেসপন্স: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/2fa/verify — 2FA ভেরিফাই (পাবলিক)

```
রিকোয়েস্ট: { "user_id": "hashid", "code": "123456" }
রেসপন্স: { "valid": true }
```

### 2.19 সার্চ

#### GET /api/search — গ্লোবাল সার্চ

```
প্যারামিটার: ?q=keyword&type=game&page=1&per_page=20
রেসপন্স: { "list": [...], "total": 100 }
```

#### GET /api/game/suggest — সার্চ সাজেশন

```
প্যারামিটার: ?q=shoot
রেসপন্স: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 ভাষা

#### GET /api/language/list — উপলব্ধ ভাষার তালিকা

```
রেসপন্স: {
  "current": "en-US",
  "languages": {
    "en-US": { "name": "English", "nativeName": "English", "icon": "us" },
    "zh-CN": { "name": "Chinese (Simplified)", "nativeName": "简体中文", "icon": "cn" },
    "ja-JP": { "name": "Japanese", "nativeName": "日本語", "icon": "jp" },
    "ko-KR": { "name": "Korean", "nativeName": "한국어", "icon": "kr" }
  }
}
```

#### POST /api/language/switch — ভাষা পরিবর্তন

```
রিকোয়েস্ট: { "locale": "zh-CN" }
রেসপন্স: { "locale": "zh-CN" }
```

locale এর মান: en-US / zh-CN / ja-JP / ko-KR

### 2.8 ইউজার

#### GET /api/user/profile — ব্যক্তিগত তথ্য

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রেসপন্স: {
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

#### PUT /api/user/profile — প্রোফাইল সম্পাদনা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}

রেসপন্স: {
  "id": "...",
  "username": "player1",
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}
```

language এর মান: en-US / zh-CN / ja-JP / ko-KR

### 2.9 ঘোষণা

#### GET /api/announcement/list — ঘোষণা তালিকা

```
রেসপন্স: {
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

#### GET /api/announcement/detail/{hashid} — ঘোষণা বিবরণ

```
রেসপন্স: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

### 2.21 প্ল্যাটফর্ম পরিসংখ্যান

| মেথড | পাথ | বিবরণ | অথেনটিকেশন |
|------|------|------|------|
| GET | /api/platform/stats | প্ল্যাটফর্ম পাবলিক পরিসংখ্যান (মোট গেম/মোট ব্যবহারকারী/আজকের প্লে/৭ দিন সক্রিয়) | না |

#### GET /api/platform/stats — প্ল্যাটফর্ম পরিসংখ্যান

```
无需认证

响应: {
  "total_games": 12,
  "total_users": 1500,
  "today_game_plays": 320,
  "active_users_7d": 450
}
```

## 3. অ্যাডমিন প্যানেল ইন্টারফেস (admin :8787)

### 3.1 প্ল্যাটফর্ম ড্যাশবোর্ড

#### GET /admin/dashboard/platform

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ (AdminAuth + AdminPermission)

রেসপন্স: {
  "total_users": 1500,
  "active_users_7d": 320,
  "total_games": 12,
  "pending_withdraws": 5,
  "today_deposits": "500.0000",
  "today_withdraws": "120.0000",
  "total_spread_fee": "1500.5000"
}
```

### 3.2 গেম ম্যানেজমেন্ট

#### GET /admin/game/list — গেম তালিকা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&per_page=20&keyword=射击

রেসপন্স: {
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

#### POST /admin/game/create — গেম তৈরি

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "name": "新游戏",
  "slug": "new-game",
  "type": "self",
  "description": "游戏描述",        // ঐচ্ছিক
  "cover_image": "https://...",    // ঐচ্ছিক
  "api_endpoint": "https://...",   // ঐচ্ছিক
  "api_key": "...",                // ঐচ্ছিক
  "api_secret": "...",             // ঐচ্ছিক
  "status": 1,                     // ঐচ্ছিক, ডিফল্ট 0
  "sort": 0                        // ঐচ্ছিক, ডিফল্ট 0
}

রেসপন্স: { "id": "aB3xK..." }
```

type এর মান: self / third_party

#### PUT /admin/game/{hashid} — গেম সম্পাদনা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "name": "新名称",
  "status": 1
  // আংশিক আপডেট সম্ভব, ফিল্ডগুলো create-এর মতো
}

রেসপন্স: { "message": "更新成功" }
```

#### DELETE /admin/game/{hashid} — গেম মুছুন

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "message": "删除成功" }
```

#### POST /admin/game/currency/manage — কয়েন ম্যানেজমেন্ট

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "game_id": "aB3xK...",
  "currencies": [
    {
      "id": "",                       // খালি=নতুন, মান থাকলে=আপডেট
      "name": "金币",
      "symbol": "G",
      "exchange_rate": "100.00000000",
      "spread_pct": "5.00",
      "min_exchange": "1.0000",
      "max_exchange": "10000.0000"
    }
  ]
}

রেসপন্স: { "message": "币种更新成功" }
```

### 3.3 উত্তোলন ম্যানেজমেন্ট

#### GET /admin/withdraw/orders — উত্তোলন অর্ডার তালিকা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&per_page=20&status=pending

রেসপন্স: {
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

#### PUT /admin/withdraw/review — উত্তোলন রিভিউ

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

রেসপন্স: { "message": "已通过" }
```

action: approve=অনুমোদন / reject=প্রত্যাখ্যান (প্রত্যাখ্যান করলে স্বয়ংক্রিয়ভাবে প্ল্যাটফর্ম কয়েন ফেরত)

ত্রুটি: 422 অর্ডারের স্ট্যাটাস রিভিউ-অপেক্ষমাণ নয়

#### PUT /admin/withdraw/switch — গ্লোবাল উত্তোলন সুইচ

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: { "enabled": 1 }

রেসপন্স: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/withdraw/limits/set — উত্তোলন সীমা সেট

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "daily_limit": "10000.0000",             // ঐচ্ছিক
  "min_amount": "1.0000",                  // ঐচ্ছিক
  "auto_approve_threshold": "100.0000"     // ঐচ্ছিক
}

রেসপন্স: {
  "daily_limit": "10000.0000",
  "min_amount": "1.0000",
  "auto_approve_threshold": "100.0000",
  "global_switch": true
}
```

### 3.4 প্ল্যাটফর্ম ইউজার ম্যানেজমেন্ট

#### GET /admin/platform/user/list — C-এন্ড ইউজার তালিকা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&per_page=20&keyword=player&status=1

রেসপন্স: {
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

#### GET /admin/platform/user/{hashid} — ইউজার বিবরণ

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রেসপন্স: {
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

#### PUT /admin/platform/user/{hashid} — ইউজার সম্পাদনা/ব্যান

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "status": 0,         // 0=ডিসেবল 1=এনাবল
  "nickname": "..."    // ঐচ্ছিক
}

রেসপন্স: { "message": "更新成功" }
```

### 3.5 পেমেন্ট ম্যানেজমেন্ট

#### GET /admin/payment/method/list

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রেসপন্স: {
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

#### POST /admin/payment/method/toggle — পেমেন্ট পদ্ধতি এনাবল/ডিসেবল

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: { "id": "aB3xK...", "status": 0 }

রেসপন্স: { "message": "已更新" }
```

### 3.6 ঘোষণা ম্যানেজমেন্ট

#### GET /admin/announcement/list

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&per_page=20

রেসপন্স: {
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

#### POST /admin/announcement/create — ঘোষণা প্রকাশ

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: {
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护。",
  "type": "system",           // ঐচ্ছিক, ডিফল্ট "system"
  "target_lang": "",          // ঐচ্ছিক, খালি=সব ভাষা
  "status": 1,                // ঐচ্ছিক, ডিফল্ট 1 (0=ড্রাফট 1=প্রকাশিত)
  "start_at": "2026-05-23 02:00:00",  // ঐচ্ছিক
  "end_at": "2026-05-23 04:00:00"     // ঐচ্ছিক
}

রেসপন্স: { "id": "aB3xK..." }
```

### 3.7 KYC রিভিউ

#### GET /admin/identity/list — KYC তালিকা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&per_page=20&status=pending

রেসপন্স: {
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

#### PUT /admin/identity/review — KYC রিভিউ

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: { "id": "hashid", "action": "approve", "note": "" }

রেসপন্স: { "message": "Approved" }
```

action: approve / reject

### 3.8 গেম সার্ভার ম্যানেজমেন্ট

#### GET /admin/game/server/list — সার্ভার তালিকা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?game_id=hashid

রেসপন্স: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/game/server/create — সার্ভার তৈরি

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
রেসপন্স: { "id": "hashid" }
```

#### PUT /admin/game/server/{hashid} — সার্ভার সম্পাদনা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/game/server/{hashid} — সার্ভার মুছুন

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
```

### 3.9 উত্তোলন টায়ার্ড লিমিট ম্যানেজমেন্ট

#### GET /admin/withdraw/limits/list

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রেসপন্স: {
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

#### PUT /admin/withdraw/limits/{hashid} — লিমিট আপডেট

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ

রিকোয়েস্ট: { "single_max": "10000.0000", "fee_pct": "0.25" }
// আংশিক আপডেট সম্ভব
```

### 3.11 গেম ক্যাটাগরি ম্যানেজমেন্ট

#### GET /admin/game/category/list

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "list": [{ "id": "...", "name": "动作", "slug": "action", "sort": 1 }] }
```

#### POST /admin/game/category/create

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "name": "新分类", "slug": "new-cat", "icon": "star", "sort": 10 }
রেসপন্স: { "id": "hashid" }
```

#### PUT /admin/game/category/{hashid} — ক্যাটাগরি সম্পাদনা

#### DELETE /admin/game/category/{hashid} — ক্যাটাগরি মুছুন

#### POST /admin/game/category/assign — গেম অ্যাসাইন

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 লিডারবোর্ড ম্যানেজমেন্ট

#### GET /admin/leaderboard/list — লিডারবোর্ড তালিকা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/leaderboard/create — লিডারবোর্ড তৈরি

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(ঐচ্ছিক)" }
```

#### PUT /admin/leaderboard/{hashid} — লিডারবোর্ড সম্পাদনা

#### DELETE /admin/leaderboard/{hashid} — লিডারবোর্ড মুছুন

#### POST /admin/leaderboard/{hashid}/refresh — ক্যাশ রিফ্রেশ

### 3.13 কুপন ম্যানেজমেন্ট

#### GET /admin/coupon/list — কুপন তালিকা

#### POST /admin/coupon/create — কুপন তৈরি

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/coupon/{hashid} — সম্পাদনা (অনাক্লেইমড হলে)

#### DELETE /admin/coupon/{hashid} — মুছুন

#### GET /admin/coupon/{hashid}/stats — ক্লেইম পরিসংখ্যান

```
রেসপন্স: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 দেশ কনফিগ ম্যানেজমেন্ট

#### GET /admin/country/config/list — দেশ কনফিগ তালিকা

#### POST /admin/country/config/create — দেশ কনফিগ তৈরি

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/country/config/{hashid} — দেশ কনফিগ সম্পাদনা

### 3.15 ডেটা এক্সপোর্ট

#### POST /admin/export/users — C-এন্ড ইউজার এক্সপোর্ট

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার(JSON): { "status": 1 }   // ঐচ্ছিক ফিল্টার

রেসপন্স: Excel ফাইল ডাউনলোড (xlsx)
```

#### POST /admin/export/transactions — প্ল্যাটফর্ম লেজার এক্সপোর্ট

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার(JSON): { "type": "deposit" }   // ঐচ্ছিক ফিল্টার

রেসপন্স: Excel ফাইল ডাউনলোড (xlsx)
```

### 3.16 ডেটা অ্যানালিটিক্স (MySQL রিয়েল-টাইম অ্যাগ্রিগেশন)

সব এন্ডপয়েন্টে অথেনটিকেশন প্রয়োজন (AdminAuth + AdminPermission), ডেটা MySQL থেকে রিয়েল-টাইম অ্যাগ্রিগেট হয়, ClickHouse-এর উপর নির্ভর করে না।

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/analytics/overview | প্ল্যাটফর্ম ওভারভিউ (আজ/সাম্প্রতিক ৭ দিন) |
| GET | /admin/analytics/game-ranking | গেম র্যাংকিং (?days=7) |
| GET | /admin/analytics/dau-trend | DAU ট্রেন্ড (?days=30) |
| GET | /admin/analytics/hourly-trend | ঘণ্টাভিত্তিক ট্রেন্ড |
| GET | /admin/analytics/action-distribution | আচরণ বিতরণ |
| GET | /admin/analytics/revenue | রেভিনিউ অ্যানালাইসিস |
| GET | /admin/analytics/conversion | গেম কনভার্সন রেট |
| GET | /admin/analytics/probability | জয়েন্ট/কন্ডিশনাল প্রোবাবিলিটি |
| GET | /admin/analytics/retention | রিটেনশন অ্যানালাইসিস D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | কনভার্সন ফানেল |
| GET | /admin/analytics/arpu | ARPU/ARPPU ট্রেন্ড |
| GET | /admin/analytics/economy | গেম কয়েন অর্থনীতি মেট্রিক |

### 3.17 টিকিট ম্যানেজমেন্ট

সব এন্ডপয়েন্টে অথেনটিকেশন প্রয়োজন (AdminAuth + AdminPermission)।

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/ticket/list | টিকিট তালিকা (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | টিকিট বিবরণ (রিপ্লাই সহ) |
| POST | /admin/ticket/{hashid}/reply | টিকিটে রিপ্লাই |
| POST | /admin/ticket/{hashid}/close | টিকিট বন্ধ |
| POST | /admin/ticket/{hashid}/assign | হ্যান্ডলার নিয়োগ (admin_id) |

### 3.18 CDN কনফিগারেশন ম্যানেজমেন্ট

সব এন্ডপয়েন্টে অথেনটিকেশন প্রয়োজন (AdminAuth + AdminPermission)।

| মেথড | পাথ | বিবরণ | অথেনটিকেশন |
|------|------|------|------|
| GET | /admin/cdn/provider/list | CDN প্রোভাইডার তালিকা (ক্রেডেনশিয়াল ফেরত দেওয়া হয় না) | AdminAuth + RBAC: cdn |
| POST | /admin/cdn/provider/toggle | প্রোভাইডার চালু/বন্ধ {id, status} | AdminAuth + RBAC: cdn |
| POST | /admin/cdn/provider/create | তৈরি {name, provider, config(JSON), status, sort}，provider স্বতন্ত্রতা যাচাই | AdminAuth + RBAC: cdn |
| PUT | /admin/cdn/provider/{hashid} | সম্পাদনা (খালি config = অপরিবর্তিত) | AdminAuth + RBAC: cdn |
| DELETE | /admin/cdn/provider/{hashid} | মুছুন | AdminAuth + RBAC: cdn |
| POST | /admin/cdn/provider/test | সংযোগ পরীক্ষা HeadBucket {id} | AdminAuth + RBAC: cdn |

### 3.19 ডেটা রিপোর্ট

সব এন্ডপয়েন্টে অথেনটিকেশন প্রয়োজন (AdminAuth + AdminPermission)।

| মেথড | পাথ | বিবরণ | অথেনটিকেশন |
|------|------|------|------|
| GET | /admin/report/summary | রিপোর্ট সারাংশ (নতুন ব্যবহারকারী/ডিপোজিট/উইথড্রয়াল/এক্সচেঞ্জ/গেম প্লে) | AdminAuth + RBAC: report |
| GET | /admin/report/daily | দৈনিক রিপোর্ট (দিনভিত্তিক সমষ্টি, ডেটাবিহীন তারিখে 0 পূরণ) | AdminAuth + RBAC: report |
| GET | /admin/report/export | দৈনিক রিপোর্ট CSV এক্সপোর্ট (UTF-8 BOM) | AdminAuth + RBAC: report |

## 4. রেট লিমিট পলিসি

| ইন্টারফেস | সীমা |
|------|------|
| ডিফল্ট | ৬০ বার/মিনিট/IP |
| POST /api/auth/login | ১০ বার/মিনিট |
| POST /api/auth/register | ৫ বার/মিনিট |

সীমা অতিক্রম করলে 429 রিটার্ন, রেসপন্স হেডারে থাকে:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1716400830
Retry-After: 60
```

## 5. অথেনটিকেশন নোট

### C-এন্ড (UserAuth)

1. `Authorization: Bearer <token>` থেকে Token এক্সট্রাক্ট করুন
2. JWT সিগনেচার যাচাই (HS256), `sub` পার্স করুন (ইউজার ID)
3. `game_user` টেবিলে ইউজার আছে ও status=1 কিনা যাচাই করুন
4. `$request->userId` ইনজেক্ট করুন

### অ্যাডমিন প্যানেল (AdminAuth + AdminPermission)

1. AdminAuth: JWT সিগনেচার যাচাই, `sub` পার্স করুন (অ্যাডমিন ID), `$request->adminId` ইনজেক্ট করুন
2. AdminPermission: ইউজার রোল অনুযায়ী পারমিশন খুঁজে `method.path` ফরম্যাটের পারমিশন আইডেন্টিফায়ার ম্যাচ করুন
3. `slug=*` সহ সুপার অ্যাডমিন পারমিশন চেক এড়িয়ে যায়

## 6. এরর কোড রেফারেন্স

| code | অর্থ | সাধারণ পরিস্থিতি |
|------|------|---------|
| 0 | সফল | - |
| 400 | প্যারামিটার ত্রুটি | রিকোয়েস্ট ফরম্যাট ভুল, পরিমাণ অপর্যাপ্ত |
| 401 | অনথেনটিকেটেড | Token অনুপস্থিত/মেয়াদোত্তীর্ণ/অবৈধ, অ্যাকাউন্ট ডিসেবল |
| 403 | কোনো পারমিশন নেই | ইউজারের সংশ্লিষ্ট রোল পারমিশন নেই, গেম অনুপলব্ধ |
| 404 | নেই | রিসোর্স পাওয়া যায়নি |
| 422 | ভেরিফিকেশন ব্যর্থ | ফর্ম প্যারামিটার নিয়ম মেনে না, অর্ডার স্ট্যাটাসে অপারেশন অনুমোদিত নয় |
| 429 | রেট লিমিট | অতিরিক্ত রিকোয়েস্ট |
| 500 | সার্ভার ত্রুটি | অপ্রত্যাশিত ব্যতিক্রম |


## 7. নতুন API (v2.0 ইকোসিস্টেম এক্সটেনশন)

### 7.1 Provider API — গেম প্রোভাইডার কলব্যাক ইন্টারফেস

**অথেনটিকেশন পদ্ধতি**: HMAC-SHA256 সিগনেচার (X-Game-Id + X-Timestamp + X-Signature)
**টাইম উইন্ডো**: ৫ মিনিট

#### POST /api/provider/balance — ইউজার ব্যালেন্স কুয়েরি

```
রিকোয়েস্ট হেডার:
  X-Game-Id: 1234567890
  X-Timestamp: 1716400830
  X-Signature: abc123...

রিকোয়েস্ট: {
  "user_id": 1234567890,
  "game_id": 9876543210,
  "currency_id": 5555555555
}

রেসপন্স: {
  "code": 0,
  "message": "success",
  "data": { "balance": "1000.50000000" }
}
```

#### POST /api/provider/bet — বেট নোটিফিকেশন

```
রিকোয়েস্ট: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "bet_type": "straight" }
}

রেসপন্স: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "990.50000000"
  }
}
```

#### POST /api/provider/settle — সেটেলমেন্ট নোটিফিকেশন

```
রিকোয়েস্ট: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}

রেসপন্স: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1040.50000000",
    "win_amount": "50.00000000"
  }
}
```

#### POST /api/provider/refund — রিফান্ড নোটিফিকেশন

```
রিকোয়েস্ট: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "reason": "game_crash"
}

রেসপন্স: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1000.50000000"
  }
}
```

### 7.2 টিকিট API

#### GET /api/ticket/list — টিকিট তালিকা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&per_page=20

রেসপন্স: {
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

#### POST /api/ticket/create — টিকিট তৈরি

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
রেসপন্স: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/ticket/{hashid} — টিকিট বিবরণ

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: {
  "id": "...", "type": "deposit", "subject": "...",
  "content": "...", "status": "open",
  "replies": [
    { "id": "...", "content": "...", "is_admin": 1, "created_at": "..." }
  ]
}
```

#### POST /api/ticket/{hashid}/reply — টিকিটে রিপ্লাই

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "content": "已核实，将在24小时内处理" }
রেসপন্স: { "code": 0, "message": "Reply sent" }
```

### 7.3 ইমেইল ভেরিফিকেশন API

#### POST /api/verify/send-email — ইমেইল ভেরিফিকেশন কোড পাঠান

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "email": "user@example.com" }
রেসপন্স: { "code": 0, "message": "Verification code sent" }
ত্রুটি: 429 ৬০ সেকেন্ড পর আবার চেষ্টা করুন
```

#### POST /api/verify/confirm-email — ইমেইল নিশ্চিতকরণ

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "code": "123456" }
রেসপন্স: { "code": 0, "message": "Email verified" }
ত্রুটি: 422 ভেরিফিকেশন কোড অবৈধ বা মেয়াদোত্তীর্ণ
```

### 7.4 VIP API

#### GET /api/user/vip-status — VIP স্ট্যাটাস

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: {
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

### 7.5 অ্যাচিভমেন্ট API

#### GET /api/user/achievements — অ্যাচিভমেন্ট তালিকা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: {
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

### 7.6 অ্যাডমিন প্যানেলের নতুন API

#### GET /admin/ticket/list — টিকিট তালিকা

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&limit=20&status=pending&type=deposit

রেসপন্স: {
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

#### POST /admin/ticket/{hashid}/reply — টিকিটে রিপ্লাই

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "content": "已处理" }
রেসপন্স: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/ticket/{hashid}/close — টিকিট বন্ধ

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/ticket/{hashid}/assign — হ্যান্ডলার নিয়োগ

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "admin_id": 1234567890 }
রেসপন্স: { "code": 0, "message": "Assigned" }
```

#### GET /admin/analytics/retention — রিটেনশন অ্যানালাইসিস

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?days=30
রেসপন্স: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/analytics/funnel — কনভার্সন ফানেল

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — ARPU/ARPPU ট্রেন্ড

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?days=30
রেসপন্স: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/analytics/economy — গেম কয়েন অর্থনীতি মেট্রিক

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: {
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


#### GET /admin/cdn/provider/list — CDN প্রোভাইডার তালিকা (ক্রেডেনশিয়াল ফেরত দেওয়া হয় না)

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "list": [ { "id": "...", "name": "...", "provider": "cloudflare", "status": 1, "sort": 0 } ] }
```

#### POST /admin/cdn/provider/toggle — প্রোভাইডার চালু/বন্ধ {id, status}

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
অনুরোধ: { "id": "...", "status": 1 }
রেসপন্স: { "code": 0, "message": "..." }
```

#### POST /admin/cdn/provider/create — তৈরি {name, provider, config(JSON), status, sort}，provider স্বতন্ত্রতা যাচাই

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
অনুরোধ: { "name": "...", "provider": "aliyun", "config": "{...}", "status": 1, "sort": 0 }
রেসপন্স: { "code": 0, "data": { "id": "..." } }
```

#### PUT /admin/cdn/provider/{hashid} — সম্পাদনা (খালি config = অপরিবর্তিত)

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
অনুরোধ: { "name": "...", "config": "" }
রেসপন্স: { "code": 0, "message": "..." }
```

#### DELETE /admin/cdn/provider/{hashid} — মুছুন

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "code": 0, "message": "..." }
```

#### POST /admin/cdn/provider/test — সংযোগ পরীক্ষা HeadBucket {id}

```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
অনুরোধ: { "id": "..." }
রেসপন্স: { "code": 0, "data": { "ok": true } }
```
#### GET /admin/report/summary — রিপোর্ট সারাংশ

```
需认证: 是
参数: ?start=Y-m-d&end=Y-m-d (缺省最近30天，跨度 ≤90 天，Redis 缓存5分钟)
响应: {
  "start": "2026-08-01", "end": "2026-08-31",
  "new_users": 120, "deposit_amount": "5000.0000", "deposit_count": 45,
  "withdraw_amount": "1200.0000", "withdraw_count": 8,
  "exchange_amount": "3000.0000", "play_count": 1500
}
```


#### GET /admin/report/daily — দৈনিক রিপোর্ট

```
需认证: 是
参数: ?start=Y-m-d&end=Y-m-d
响应: {
  "start": "2026-08-01", "end": "2026-08-31",
  "rows": [ { "date": "2026-08-01", "new_users": 12, "deposit_amount": "500.0000", "deposit_count": 4, "withdraw_amount": "100.0000", "withdraw_count": 1, "exchange_amount": "300.0000", "play_count": 150 } ]
}
```


#### GET /admin/report/export — দৈনিক রিপোর্ট CSV এক্সপোর্ট

```
需认证: 是
参数: ?start=Y-m-d&end=Y-m-d&format=excel
响应: CSV 文件（UTF-8 BOM），文件名 report_{start}_{end}.csv，Excel 可直接打开
```

## 8. রেট লিমিট পলিসি (আপডেট)

| ইন্টারফেস | সীমা |
|------|------|
| ডিফল্ট | ৬০ বার/মিনিট/IP |
| POST /api/auth/login | ১০ বার/মিনিট |
| POST /api/auth/register | ৫ বার/মিনিট |
| POST /api/auth/oauth | ১০ বার/মিনিট |
| POST /api/payment/callback | ৩০ বার/মিনিট |
| POST /api/provider/* | সীমাহীন (HMAC সিগনেচার অথেনটিকেশন) |

## 9. অথেনটিকেশন নোট (আপডেট)

### Provider অথেনটিকেশন (ProviderAuth)

1. রিকোয়েস্ট হেডার থেকে `X-Game-Id`, `X-Timestamp`, `X-Signature` এক্সট্রাক্ট করুন
2. `game_game` টেবিলে গেম আছে ও status=1 কিনা যাচাই করুন
3. টাইমস্ট্যাম্প ৫ মিনিটের উইন্ডোর মধ্যে যাচাই করুন (রিপ্লে-প্রতিরোধ)
4. `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` গণনা করে সিগনেচারের সাথে তুলনা করুন
5. `$request->gameId` এবং `$request->game` ইনজেক্ট করুন


### 7.7 ফ্রেন্ড API

#### GET /api/friend/list — ফ্রেন্ড তালিকা
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/friend/requests — পেন্ডিং রিকোয়েস্ট
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/friend/request — ফ্রেন্ড রিকোয়েস্ট পাঠান
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "friend_id": "hashid" }
```

#### POST /api/friend/accept — রিকোয়েস্ট গ্রহণ
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "request_id": "hashid" }
```

#### POST /api/friend/reject — রিকোয়েস্ট প্রত্যাখ্যান
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "request_id": "hashid" }
```

#### POST /api/friend/remove — ফ্রেন্ড মুছুন
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "friend_id": "hashid" }
```

#### GET /api/friend/search — ইউজার সার্চ
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?q=username
রেসপন্স: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 চ্যাট API

#### GET /api/chat/conversations — কনভার্সেশন তালিকা
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: {
  "list": [{
    "peer": { "id": "...", "username": "...", "nickname": "...", "avatar": "..." },
    "last_message": "最近一条消息",
    "unread_count": 3,
    "updated_at": "2026-05-22 10:30:00"
  }]
}
```

#### GET /api/chat/messages/{peerHashid} — মেসেজ তালিকা
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?page=1&per_page=50
রেসপন্স: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
অন্য পাশ থেকে আসা অপঠিত মেসেজ স্বয়ংক্রিয়ভাবে পড়া হিসেবে চিহ্নিত হয়
```

#### POST /api/chat/send — মেসেজ পাঠান
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "to_user_id": "hashid", "content": "Hello!" }
ত্রুটি: 403 ফ্রেন্ড না হলে পাঠানো যাবে না
```

#### GET /api/chat/unread-total — অপঠিত মোট
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "count": 5 }
```

**WebSocket সংযোগ**: `ws://host:8791`
```
// অথেনটিকেশন
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// মেসেজ গ্রহণ
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 Webhook API

#### GET /api/webhook/list — সাবস্ক্রিপশন তালিকা
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/webhook/register — সাবস্ক্রিপশন রেজিস্টার
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
উপলব্ধ ইভেন্ট: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/webhook/delete — সাবস্ক্রিপশন মুছুন
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রিকোয়েস্ট: { "id": "hook_id" }
```

### 7.10 অ্যাডভান্সড অ্যানালিটিক্স API

#### GET /admin/analytics/retention — রিটেনশন অ্যানালাইসিস
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/analytics/funnel — কনভার্সন ফানেল
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — ARPU/ARPPU ট্রেন্ড
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
প্যারামিটার: ?days=30
রেসপন্স: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/analytics/economy — গেম অর্থনীতি মেট্রিক
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
রেসপন্স: {
  "currencies": [{
    "game_name": "Shooter Master", "currency": "Gold", "symbol": "G",
    "total_minted": "500000.00000000", "total_burned": "320000.00000000",
    "circulation": "180000.00000000", "inflation_rate": "36.00%"
  }]
}
```


### 7.11 টুর্নামেন্ট API

#### GET /api/tournament/list — টুর্নামেন্ট তালিকা
```
প্যারামিটার: ?status=active|upcoming|ended&page=1&per_page=20
রেসপন্স: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/tournament/{hashid} — টুর্নামেন্ট বিবরণ
```
রেসপন্স: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/tournament/{hashid}/join — অংশগ্রহণ রেজিস্ট্রেশন
```
অথেনটিকেশন প্রয়োজন: হ্যাঁ
ত্রুটি: 422 ইতিমধ্যে নিবন্ধিত / 400 শুরু হয়ে গেছে বা পূর্ণ / 503 FeatureFlag বন্ধ
```

### 7.12 কুপন শর্ত (নতুন)

কুপনের `conditions` JSON সাপোর্ট করে:
- `min_deposit`: স্ট্রিং, সর্বনিম্ন মোট টপ-আপ পরিমাণ
- `first_user_only`: bool, শুধুমাত্র কখনো টপ-আপ করেনি এমন নতুন ইউজার
- `game_id`: int, নির্দিষ্ট গেম খেলতে হবে

শর্তগুলো `available()` তালিকা ফিল্টারিং ও `claim()` গ্রহণে দ্বিগুণ যাচাই করা হয়।

### 7.13 মাল্টি-লেভেল রেফারেল (নতুন)

রেফারেল কমিশনে সেকেন্ড-লেভেল প্রফিট শেয়ার যোগ হয়েছে:
- L1: সরাসরি রেফারার `referrer_bonus` পায় (কনফিগ: referral.referrer_bonus)
- L2: রেফারারের রেফারার `commission = referrer_bonus * level2_rate` পায় (কনফিগ: referral.level2_rate, ডিফল্ট ৫%)
- `game_referral_commission`-এ রেকর্ড হয় (level/commission_rate/commission_amount)

### 8. রেট লিমিট পলিসি (আপডেট)

| ইন্টারফেস | সীমা |
|------|------|
| POST /api/tournament/{id}/join | ১০ বার/মিনিট |

---

## 10. নতুন API (v1.3.15-v1.3.22)

### 10.1 ঝুঁকি ব্যবস্থাপনা (অ্যাডমিন :8787)

| এন্ডপয়েন্ট | বর্ণনা |
|------|------|
| GET /admin/risk/dashboard | রিস্ক ড্যাশবোর্ড ওভারভিউ |
| GET /admin/risk/overview | রিস্ক ওভারভিউ মেট্রিক্স |
| GET /admin/risk/hit-trend | হিট ট্রেন্ড |
| GET /admin/risk/action-distribution | অ্যাকশন বিতরণ |
| GET /admin/risk/rule-performance | রুল পারফরম্যান্স |
| GET /admin/risk/rule/list | রুল তালিকা |
| POST /admin/risk/rule/create | রুল তৈরি |
| PUT /admin/risk/rule/{hashid} | রুল আপডেট |
| POST /admin/risk/rule/{hashid}/toggle | রুল সক্রিয়/নিষ্ক্রিয় |
| POST /admin/risk/rule/test | রুল টেস্ট |
| GET /admin/risk/event/list | রিস্ক ইভেন্ট তালিকা |
| GET /admin/risk/event/{hashid} | ইভেন্ট বিবরণ |
| POST /admin/risk/event/{hashid}/handle | ইভেন্ট নিষ্পত্তি |
| GET /admin/risk/device/list | ডিভাইস ফিঙ্গারপ্রিন্ট তালিকা |
| POST /admin/risk/device/block | ডিভাইস ব্লক |
| POST /admin/risk/device/unblock | ডিভাইস আনব্লক |
| GET /admin/risk/ip/list | IP তালিকা |
| POST /admin/risk/ip/block | IP ব্লক |
| POST /admin/risk/ip/whitelist | IP হোয়াইটলিস্ট |
| POST /admin/risk/ip/appeal | IP আপিল |
| POST /admin/risk/ip/recheck | IP পুনঃপরীক্ষা |
| GET /admin/risk/graph/clusters | ক্লাস্টার তালিকা |
| GET /admin/risk/graph/{userId} | ব্যবহারকারী লিংক গ্রাফ |
| GET /admin/risk/clusters | রিস্ক ক্লাস্টার তালিকা |

### 10.2 অ্যান্টি-চিট ব্যবস্থাপনা (অ্যাডমিন :8787)

| এন্ডপয়েন্ট | বর্ণনা |
|------|------|
| GET /admin/anticheat/events | অ্যান্টি-চিট ইভেন্ট তালিকা |
| GET /admin/anticheat/events/{hashid} | ইভেন্ট বিবরণ |
| POST /admin/anticheat/events/{hashid}/review | ইভেন্ট রিভিউ |

### 10.3 কার্যক্রম (অ্যাডমিন :8787 + ক্লায়েন্ট :8788)

| এন্ডপয়েন্ট | বর্ণনা |
|------|------|
| GET /admin/activities/list | অ্যাক্টিভিটি তালিকা (অ্যাডমিন) |
| POST /admin/activities/create | অ্যাক্টিভিটি তৈরি (অ্যাডমিন) |
| PUT /admin/activities/{hashid} | অ্যাক্টিভিটি আপডেট (অ্যাডমিন) |
| DELETE /admin/activities/{hashid} | অ্যাক্টিভিটি মুছুন (অ্যাডমিন) |
| GET /api/activities/list | অ্যাক্টিভিটি তালিকা (ক্লায়েন্ট) |
| GET /api/activities/progress | অংশগ্রহণের অগ্রগতি (ক্লায়েন্ট) |
| GET /api/activities/{hashid} | অ্যাক্টিভিটি বিবরণ (ক্লায়েন্ট) |
| POST /api/activities/{hashid}/checkin | চেক-ইন (ক্লায়েন্ট) |

### 10.4 গ্রুপ / শেয়ার (ক্লায়েন্ট :8788 + অ্যাডমিন :8787)

| এন্ডপয়েন্ট | বর্ণনা |
|------|------|
| POST /api/groups | গ্রুপ তৈরি |
| GET /api/groups/{hashid} | গ্রুপ বিবরণ |
| GET /api/groups/{hashid}/members | সদস্য তালিকা |
| POST /api/groups/{hashid}/join | গ্রুপে যোগ দিন |
| POST /api/groups/{hashid}/leave | গ্রুপ ত্যাগ |
| PUT /api/groups/{hashid}/role | সদস্য ভূমিকা |
| POST /api/shares | শেয়ার লিংক তৈরি |
| POST /api/shares/visit | শেয়ার ভিজিট ট্র্যাকিং |
| GET /admin/groups | গ্রুপ তালিকা (অ্যাডমিন) |
| GET /admin/groups/{hashid}/audit | গ্রুপ অডিট (অ্যাডমিন) |
| GET /admin/share/stats | শেয়ার পরিসংখ্যান (অ্যাডমিন) |

### 10.5 পেমেন্ট গেটওয়ে এক্সটেনশন (L1)

| গেটওয়ে | বর্ণনা |
|------|------|
| Adyen | নতুন পেমেন্ট গেটওয়ে (ডিপোজিট/কলব্যাক যাচাই/স্বয়ংক্রিয় ক্রেডিট) |
| GrabPay | নতুন পেমেন্ট গেটওয়ে (ডিপোজিট/কলব্যাক যাচাই/স্বয়ংক্রিয় ক্রেডিট) |
