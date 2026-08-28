# توثيق الواجهات
<!-- lang-nav -->

Languages: **中文** · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

توثيق تفاعلي عبر الإنترنت (يدعم التصحيح عبر الإنترنت):
- أعمال الطرف C: http://localhost:8788/apidoc/
- لوحة الإدارة: http://localhost:8787/apidoc/
- كلمة المرور: admin123

## 1. الاتفاقيات

### 1.1 عنوان URL الأساسي

| الطرف | العنوان |
|----|------|
| لوحة الإدارة | `http://localhost:8787` |
| أعمال الطرف C | `http://localhost:8788` |

### 1.2 رؤوس الطلبات العامة

```
Content-Type: application/json
API-Version: v1
Authorization: Bearer <token>    (الواجهات التي تتطلب مصادقة)
```

### 1.3 صيغة الاستجابة الموحدة

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | المعنى |
|------|------|
| 0 | نجاح |
| 400 | خطأ في المعاملات |
| 401 | غير مصادَق (Token مفقود/منتهي/غير صالح) |
| 403 | لا صلاحية |
| 404 | المورد غير موجود |
| 422 | فشل التحقق |
| 429 | طلبات كثيرة جدًا (تفعيل حد المعدل) |
| 500 | خطأ في الخادم |

### 1.4 ترميز المعرّفات

جميع المعرّفات في طلبات واستجابات الواجهات هي سلاسل مشفرة بـ Hashids، وليست القيم BIGINT الأصلية.

```
خارجيًا: aB3xK9mW2pQ7rT5v  (سلسلة hashid)
داخليًا: 1750123456789      (Snowflake BIGINT)
```

### 1.5 صيغة الترقيم

```
الطلب: ?page=1&per_page=20

الاستجابة: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. واجهات الطرف C (service :8788)

### 2.1 المصادقة

#### POST /api/auth/register — تسجيل المستخدم

```
الطلب: {
  "username": "player1",
  "password": "123456",
  "email": "player@example.com"     // اختياري
}

الاستجابة: {
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

#### POST /api/auth/login — تسجيل دخول المستخدم

```
الطلب: {
  "username": "player1",
  "password": "123456"
}

الاستجابة: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "...", ... }
}
```

خطأ: 401 اسم المستخدم أو كلمة المرور غير صحيحة / الحساب معطّل

#### POST /api/auth/refresh — تجديد Token

```
الطلب: (Authorization: Bearer <refresh_token>)

الاستجابة: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 المحفظة

#### GET /api/wallet/info — معلومات المحفظة

```
يتطلب مصادقة: نعم

الاستجابة: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/wallet/transactions — سجل الحركات

```
يتطلب مصادقة: نعم
المعلمات: ?page=1&per_page=20&type=deposit    (type اختياري)

الاستجابة: {
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

القيم الممكنة لـ type: deposit / withdraw / exchange_in / exchange_out / game_earn / game_spend
```

### 2.3 الشحن

#### POST /api/deposit/create — إنشاء طلب شحن

```
يتطلب مصادقة: نعم

الطلب: {
  "amount": "10.00",
  "currency": "USD",
  "payment_method_id": "aB3xK..."
}

الاستجابة: {
  "order_id": "aB3xK...",
  "order_no": "DEP202605221030000123",
  "amount": "10.00",
  "platform_amount": "10.0000",
  "checkout_url": "https://checkout.stripe.com/...",
  "expires_at": "2026-05-22 11:30:00"
}
```

القيم الممكنة لـ currency: USD / CNY / EUR

checkout_url: رابط إعادة التوجيه إلى بوابة الدفع (يُملأ عند إنشاء الطلب)؛ expires_at: انتهاء صلاحية رابط الدفع (بعد ساعة من الإنشاء)

#### GET /api/deposit/orders — سجلات الشحن

```
يتطلب مصادقة: نعم
المعلمات: ?page=1&per_page=20

الاستجابة: {
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

القيم الممكنة لـ status: pending / paid / confirmed / cancelled

### 2.4 الاستبدال

#### POST /api/exchange/quote — الاستعلام عن السعر

```
يتطلب مصادقة: نعم

الطلب: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "direction": "in",
  "platform_amount": "10.0000"
}

الاستجابة: {
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000",
  "spread_pct": "5.00%"
}
```

direction: in=شراء عملات اللعبة / out=بيع عملات اللعبة

#### POST /api/exchange/buy — شراء عملات اللعبة

```
يتطلب مصادقة: نعم

الطلب: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "10.0000"
}

الاستجابة: {
  "exchange_id": "aB3xK...",
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000"
}
```

خطأ: 422 رصيد عملات المنصة غير كافٍ / 404 اللعبة غير متاحة

#### POST /api/exchange/sell — بيع عملات اللعبة

```
يتطلب مصادقة: نعم

الطلب: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "950.0000"
}

الاستجابة: {
  "exchange_id": "aB3xK...",
  "platform_amount": "9.0250",
  "game_amount": "950.0000",
  "spread_fee": "0.4750",
  "rate": "100.00000000"
}
```

خطأ: 422 رصيد عملات اللعبة غير كافٍ

#### GET /api/exchange/records — سجلات الاستبدال

```
يتطلب مصادقة: نعم
المعلمات: ?page=1&per_page=20

الاستجابة: {
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

### 2.5 السحب

#### POST /api/withdraw/apply — تقديم طلب سحب

```
يتطلب مصادقة: نعم

الطلب: {
  "platform_amount": "50.0000",
  "method": "paypal",
  "account_info": "user@paypal.com"
}

الاستجابة: {
  "order_id": "...",
  "order_no": "WTH202605221030000456",
  "status": "approved"
}
```

القيم الممكنة لـ method: paypal / bank / crypto

status:
- approved: موافقة تلقائية (المبلغ < auto_approve_threshold)
- pending: قيد المراجعة (المبلغ >= auto_approve_threshold)

الأخطاء:
- 403 وظيفة السحب مغلقة مؤقتًا (المفتاح العام مغلق)
- 400 أقل من الحد الأدنى لمبلغ السحب
- 400 تجاوز حد السحب اليومي
- 400 الرصيد غير كافٍ

#### GET /api/withdraw/orders — سجلات السحب

```
يتطلب مصادقة: نعم
المعلمات: ?page=1&per_page=20

الاستجابة: {
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

### 2.6 الألعاب

#### GET /api/game/list — قائمة الألعاب

```
المعلمات: ?page=1&per_page=20&keyword=射击&type=self

الاستجابة: {
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

القيم الممكنة لـ type: self / third_party

#### GET /api/game/{hashid} — تفاصيل اللعبة

```
الاستجابة: {
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

#### POST /api/game/launch — تشغيل اللعبة

```
يتطلب مصادقة: نعم

الطلب: { "game_id": "aB3xK..." }

الاستجابة: {
  "id": "...",
  "name": "射击大师",
  "type": "self",
  "api_endpoint": "https://game.example.com/play"
}
```

### 2.7 تسجيل دخول OAuth بطرف ثالث

يدعم 7 منصات: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/auth/oauth/{provider} — الحصول على عنوان التفويض

```
المعلمات: provider = google / facebook / apple / twitter / microsoft / linkedin / github

الاستجابة: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/auth/oauth/{provider}/callback — استدعاء OAuth

```
الطلب: { "code": "授权码", "state": "防CSRF状态" }

الاستجابة: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=مستخدم مسجل حديثًا / false=حساب موجود تم ربطه

### 2.8 التحقق من الهوية KYC

#### GET /api/user/identity/status — حالة التحقق

```
يتطلب مصادقة: نعم

الاستجابة: {
  "status": "approved",          // not_submitted / pending / approved / rejected
  "real_name": "J***",
  "id_type": "id_card",
  "review_note": "",
  "submitted_at": "2026-05-22 10:00:00",
  "reviewed_at": "2026-05-23 14:00:00"
}
```

#### POST /api/user/identity/apply — تقديم التحقق

```
يتطلب مصادقة: نعم

الطلب: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}

الاستجابة: { "message": "KYC submitted successfully" }
```

### 2.9 الدفع

#### POST /api/payment/callback — استدعاء الدفع (عام)

```
الطلب: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

الاستجابة: { "message": "success" }
```

status: success / failed

القيم الممكنة لـ provider: stripe / paypal / nowpayments / coinbase (nowpayments يتحقق عبر IPN HMAC-SHA512، وcoinbase عبر webhook HMAC-SHA256)

#### GET /api/payment/methods — طرق الدفع المتاحة (عام)

```
الاستجابة: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

يتم التصفية حسب دولة المستخدم (X-Language/Accept-Language → رمز الدولة): countries فارغ أو يحتوي على * يعني متاح عالميًا؛ يُرتب حسب تفضيل طرق الدفع في country_config لتلك الدولة

### 2.10 سجلات اللعب

#### GET /api/game/play-logs — قائمة سجلات اللعب

```
يتطلب مصادقة: نعم
المعلمات: ?page=1&per_page=20&game_id=xxx&action=start

الاستجابة: {
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

#### GET /api/game/play-log/{hashid} — تفاصيل سجل اللعب

```
يتطلب مصادقة: نعم
الاستجابة: { سجل كامل، يشمل session_id / game_amount_before / after إلخ }
```

### 2.12 لوحات المتصدرين

#### GET /api/leaderboard/list — قائمة لوحات المتصدرين

```
الاستجابة: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/leaderboard/{hashid} — تفاصيل لوحة المتصدرين

```
الاستجابة: {
  "id": "...",
  "name": "全服累计收入榜",
  "type": "total",
  "rankings": [
    { "rank": 1, "user_id": "...", "score": "50000.0000" }
  ]
}
```

### 2.13 القسائم

#### GET /api/coupon/available — القسائم المتاحة للاستلام

```
يتطلب مصادقة: نعم
الاستجابة: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/coupon/claim — استلام قسيمة

```
يتطلب مصادقة: نعم
الطلب: { "coupon_id": "hashid" }
الاستجابة: { "coupon": { ... } }
```

#### GET /api/coupon/my — قسائمي

```
يتطلب مصادقة: نعم
المعلمات: ?status=unused
الاستجابة: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 إعدادات الدول

#### GET /api/country/list — قائمة الدول

```
الاستجابة: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/country/{code} — تفاصيل الدولة

```
الاستجابة: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 الإشعارات

#### GET /api/notification/list — قائمة الإشعارات

```
يتطلب مصادقة: نعم
المعلمات: ?page=1&per_page=20&is_read=0

الاستجابة: {
  "list": [
    { "id": "...", "type": "deposit", "title": "Deposit Received", "is_read": 0, "created_at": "..." }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### GET /api/notification/unread-count — عدد غير المقروء

```
يتطلب مصادقة: نعم
الاستجابة: { "count": 3 }
```

#### POST /api/notification/read — تحديد كمقروء

```
يتطلب مصادقة: نعم
الطلب: { "id": "hashid" }  // عدم الإرسال = تحديد الكل كمقروء
```

### 2.17 الإحالات

#### GET /api/referral/my-code — رمز الإحالة الخاص بي

```
يتطلب مصادقة: نعم
الاستجابة: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/referral/apply — استخدام رمز الإحالة

```
يتطلب مصادقة: نعم
الطلب: { "code": "ABC12345" }
الاستجابة: { "message": "Referral applied" }
```

### 2.18 المصادقة الثنائية 2FA

#### GET /api/user/2fa/status — حالة 2FA

```
يتطلب مصادقة: نعم
الاستجابة: { "enabled": false }
```

#### POST /api/user/2fa/setup — إعداد 2FA

```
يتطلب مصادقة: نعم
الاستجابة: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/user/2fa/enable — تفعيل 2FA

```
يتطلب مصادقة: نعم
الطلب: { "code": "123456" }
الاستجابة: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/2fa/verify — التحقق من 2FA (عام)

```
الطلب: { "user_id": "hashid", "code": "123456" }
الاستجابة: { "valid": true }
```

### 2.19 البحث

#### GET /api/search — بحث عام

```
المعلمات: ?q=keyword&type=game&page=1&per_page=20
الاستجابة: { "list": [...], "total": 100 }
```

#### GET /api/game/suggest — اقتراحات البحث

```
المعلمات: ?q=shoot
الاستجابة: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 اللغات

#### GET /api/language/list — قائمة اللغات المتاحة

```
الاستجابة: {
  "current": "en-US",
  "languages": {
    "en-US": { "name": "English", "nativeName": "English", "icon": "us" },
    "zh-CN": { "name": "Chinese (Simplified)", "nativeName": "简体中文", "icon": "cn" },
    "ja-JP": { "name": "Japanese", "nativeName": "日本語", "icon": "jp" },
    "ko-KR": { "name": "Korean", "nativeName": "한국어", "icon": "kr" }
  }
}
```

#### POST /api/language/switch — تبديل اللغة

```
الطلب: { "locale": "zh-CN" }
الاستجابة: { "locale": "zh-CN" }
```

القيم الممكنة لـ locale: en-US / zh-CN / ja-JP / ko-KR

### 2.8 المستخدم

#### GET /api/user/profile — المعلومات الشخصية

```
يتطلب مصادقة: نعم

الاستجابة: {
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

#### PUT /api/user/profile — تعديل الملف الشخصي

```
يتطلب مصادقة: نعم

الطلب: {
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}

الاستجابة: {
  "id": "...",
  "username": "player1",
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}
```

القيم الممكنة لـ language: en-US / zh-CN / ja-JP / ko-KR

### 2.9 الإعلانات

#### GET /api/announcement/list — قائمة الإعلانات

```
الاستجابة: {
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

#### GET /api/announcement/detail/{hashid} — تفاصيل الإعلان

```
الاستجابة: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

## 3. واجهات لوحة الإدارة (admin :8787)

### 3.1 لوحة تحكم المنصة

#### GET /admin/dashboard/platform

```
يتطلب مصادقة: نعم (AdminAuth + AdminPermission)

الاستجابة: {
  "total_users": 1500,
  "active_users_7d": 320,
  "total_games": 12,
  "pending_withdraws": 5,
  "today_deposits": "500.0000",
  "today_withdraws": "120.0000",
  "total_spread_fee": "1500.5000"
}
```

### 3.2 إدارة الألعاب

#### GET /admin/game/list — قائمة الألعاب

```
يتطلب مصادقة: نعم
المعلمات: ?page=1&per_page=20&keyword=射击

الاستجابة: {
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

#### POST /admin/game/create — إنشاء لعبة

```
يتطلب مصادقة: نعم

الطلب: {
  "name": "新游戏",
  "slug": "new-game",
  "type": "self",
  "description": "游戏描述",        // اختياري
  "cover_image": "https://...",    // اختياري
  "api_endpoint": "https://...",   // اختياري
  "api_key": "...",                // اختياري
  "api_secret": "...",             // اختياري
  "status": 1,                     // اختياري، الافتراضي 0
  "sort": 0                        // اختياري، الافتراضي 0
}

الاستجابة: { "id": "aB3xK..." }
```

القيم الممكنة لـ type: self / third_party

#### PUT /admin/game/{hashid} — تعديل اللعبة

```
يتطلب مصادقة: نعم

الطلب: {
  "name": "新名称",
  "status": 1
  // يمكن التحديث جزئيًا، الحقول نفسها الموجودة في create
}

الاستجابة: { "message": "更新成功" }
```

#### DELETE /admin/game/{hashid} — حذف اللعبة

```
يتطلب مصادقة: نعم
الاستجابة: { "message": "删除成功" }
```

#### POST /admin/game/currency/manage — إدارة العملات

```
يتطلب مصادقة: نعم

الطلب: {
  "game_id": "aB3xK...",
  "currencies": [
    {
      "id": "",                       // فارغ=إنشاء جديد، بقيمة=تحديث
      "name": "金币",
      "symbol": "G",
      "exchange_rate": "100.00000000",
      "spread_pct": "5.00",
      "min_exchange": "1.0000",
      "max_exchange": "10000.0000"
    }
  ]
}

الاستجابة: { "message": "币种更新成功" }
```

### 3.3 إدارة السحب

#### GET /admin/withdraw/orders — قائمة طلبات السحب

```
يتطلب مصادقة: نعم
المعلمات: ?page=1&per_page=20&status=pending

الاستجابة: {
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

#### PUT /admin/withdraw/review — مراجعة السحب

```
يتطلب مصادقة: نعم

الطلب: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

الاستجابة: { "message": "已通过" }
```

action: approve=موافقة / reject=رفض (عند الرفض تُعاد عملات المنصة تلقائيًا)

خطأ: 422 حالة الطلب ليست قيد المراجعة

#### PUT /admin/withdraw/switch — المفتاح العام للسحب

```
يتطلب مصادقة: نعم

الطلب: { "enabled": 1 }

الاستجابة: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/withdraw/limits/set — تعيين حدود السحب

```
يتطلب مصادقة: نعم

الطلب: {
  "daily_limit": "10000.0000",             // اختياري
  "min_amount": "1.0000",                  // اختياري
  "auto_approve_threshold": "100.0000"     // اختياري
}

الاستجابة: {
  "daily_limit": "10000.0000",
  "min_amount": "1.0000",
  "auto_approve_threshold": "100.0000",
  "global_switch": true
}
```

### 3.4 إدارة مستخدمي المنصة

#### GET /admin/platform/user/list — قائمة مستخدمي الطرف C

```
يتطلب مصادقة: نعم
المعلمات: ?page=1&per_page=20&keyword=player&status=1

الاستجابة: {
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

#### GET /admin/platform/user/{hashid} — تفاصيل المستخدم

```
يتطلب مصادقة: نعم

الاستجابة: {
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

#### PUT /admin/platform/user/{hashid} — تعديل/حظر المستخدم

```
يتطلب مصادقة: نعم

الطلب: {
  "status": 0,         // 0=معطّل 1=مفعّل
  "nickname": "..."    // اختياري
}

الاستجابة: { "message": "更新成功" }
```

### 3.5 إدارة الدفع

#### GET /admin/payment/method/list

```
يتطلب مصادقة: نعم

الاستجابة: {
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

#### POST /admin/payment/method/toggle — تفعيل/تعطيل طريقة الدفع

```
يتطلب مصادقة: نعم

الطلب: { "id": "aB3xK...", "status": 0 }

الاستجابة: { "message": "已更新" }
```

### 3.6 إدارة الإعلانات

#### GET /admin/announcement/list

```
يتطلب مصادقة: نعم
المعلمات: ?page=1&per_page=20

الاستجابة: {
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

#### POST /admin/announcement/create — نشر إعلان

```
يتطلب مصادقة: نعم

الطلب: {
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护。",
  "type": "system",           // اختياري، الافتراضي "system"
  "target_lang": "",          // اختياري، فارغ=جميع اللغات
  "status": 1,                // اختياري، الافتراضي 1 (0=مسودة 1=منشور)
  "start_at": "2026-05-23 02:00:00",  // اختياري
  "end_at": "2026-05-23 04:00:00"     // اختياري
}

الاستجابة: { "id": "aB3xK..." }
```

### 3.7 مراجعة KYC

#### GET /admin/identity/list — قائمة KYC

```
يتطلب مصادقة: نعم
المعلمات: ?page=1&per_page=20&status=pending

الاستجابة: {
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

#### PUT /admin/identity/review — مراجعة KYC

```
يتطلب مصادقة: نعم

الطلب: { "id": "hashid", "action": "approve", "note": "" }

الاستجابة: { "message": "Approved" }
```

action: approve / reject

### 3.8 إدارة خوادم الألعاب

#### GET /admin/game/server/list — قائمة الخوادم

```
يتطلب مصادقة: نعم
المعلمات: ?game_id=hashid

الاستجابة: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/game/server/create — إنشاء خادم

```
يتطلب مصادقة: نعم
الطلب: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
الاستجابة: { "id": "hashid" }
```

#### PUT /admin/game/server/{hashid} — تعديل الخادم

```
يتطلب مصادقة: نعم
الطلب: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/game/server/{hashid} — حذف الخادم

```
يتطلب مصادقة: نعم
```

### 3.9 إدارة حدود السحب المتدرجة

#### GET /admin/withdraw/limits/list

```
يتطلب مصادقة: نعم

الاستجابة: {
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

#### PUT /admin/withdraw/limits/{hashid} — تحديث الحدود

```
يتطلب مصادقة: نعم

الطلب: { "single_max": "10000.0000", "fee_pct": "0.25" }
// يمكن التحديث جزئيًا
```

### 3.11 إدارة تصنيفات الألعاب

#### GET /admin/game/category/list

```
يتطلب مصادقة: نعم
الاستجابة: { "list": [{ "id": "...", "name": "动作", "slug": "action", "sort": 1 }] }
```

#### POST /admin/game/category/create

```
يتطلب مصادقة: نعم
الطلب: { "name": "新分类", "slug": "new-cat", "icon": "star", "sort": 10 }
الاستجابة: { "id": "hashid" }
```

#### PUT /admin/game/category/{hashid} — تعديل التصنيف

#### DELETE /admin/game/category/{hashid} — حذف التصنيف

#### POST /admin/game/category/assign — توزيع الألعاب

```
يتطلب مصادقة: نعم
الطلب: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 إدارة لوحات المتصدرين

#### GET /admin/leaderboard/list — قائمة لوحات المتصدرين

```
يتطلب مصادقة: نعم
الاستجابة: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/leaderboard/create — إنشاء لوحة متصدرين

```
يتطلب مصادقة: نعم
الطلب: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(اختياري)" }
```

#### PUT /admin/leaderboard/{hashid} — تعديل لوحة المتصدرين

#### DELETE /admin/leaderboard/{hashid} — حذف لوحة المتصدرين

#### POST /admin/leaderboard/{hashid}/refresh — تحديث التخزين المؤقت

### 3.13 إدارة القسائم

#### GET /admin/coupon/list — قائمة القسائم

#### POST /admin/coupon/create — إنشاء قسيمة

```
يتطلب مصادقة: نعم
الطلب: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/coupon/{hashid} — تعديل (قبل الاستلام)

#### DELETE /admin/coupon/{hashid} — حذف

#### GET /admin/coupon/{hashid}/stats — إحصائيات الاستلام

```
الاستجابة: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 إدارة إعدادات الدول

#### GET /admin/country/config/list — قائمة إعدادات الدول

#### POST /admin/country/config/create — إنشاء إعداد دولة

```
يتطلب مصادقة: نعم
الطلب: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/country/config/{hashid} — تعديل إعداد الدولة

### 3.15 تصدير البيانات

#### POST /admin/export/users — تصدير مستخدمي الطرف C

```
يتطلب مصادقة: نعم
المعلمات (JSON): { "status": 1 }   // فلترة اختيارية

الاستجابة: تنزيل ملف Excel (xlsx)
```

#### POST /admin/export/transactions — تصدير حركات المنصة

```
يتطلب مصادقة: نعم
المعلمات (JSON): { "type": "deposit" }   // فلترة اختيارية

الاستجابة: تنزيل ملف Excel (xlsx)
```

### 3.16 تحليل البيانات (تجميع MySQL لحظي)

جميع الواجهات تتطلب مصادقة (AdminAuth + AdminPermission)، البيانات تُجمَّع لحظيًا من MySQL، دون الاعتماد على ClickHouse.

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/analytics/overview | نظرة عامة على المنصة (اليوم/آخر 7 أيام) |
| GET | /admin/analytics/game-ranking | ترتيب الألعاب (?days=7) |
| GET | /admin/analytics/dau-trend | اتجاه DAU (?days=30) |
| GET | /admin/analytics/hourly-trend | الاتجاه بالساعة |
| GET | /admin/analytics/action-distribution | توزيع السلوكيات |
| GET | /admin/analytics/revenue | تحليل الإيرادات |
| GET | /admin/analytics/conversion | معدل تحويل الألعاب |
| GET | /admin/analytics/probability | الاحتمال المشترك/الشرطي |
| GET | /admin/analytics/retention | تحليل الاحتفاظ D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | قمع التحويل |
| GET | /admin/analytics/arpu | اتجاه ARPU/ARPPU |
| GET | /admin/analytics/economy | مؤشرات اقتصاد عملات الألعاب |

### 3.17 إدارة التذاكر

جميع الواجهات تتطلب مصادقة (AdminAuth + AdminPermission).

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/ticket/list | قائمة التذاكر (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | تفاصيل التذكرة (تشمل الردود) |
| POST | /admin/ticket/{hashid}/reply | الرد على التذكرة |
| POST | /admin/ticket/{hashid}/close | إغلاق التذكرة |
| POST | /admin/ticket/{hashid}/assign | تعيين المعالج (admin_id) |

## 4. سياسة حد المعدل

| الواجهة | الحد |
|------|------|
| الافتراضي | 60 مرة/دقيقة/IP |
| POST /api/auth/login | 10 مرات/دقيقة |
| POST /api/auth/register | 5 مرات/دقيقة |

تجاوز الحد يُرجع 429، وتشمل رؤوس الاستجابة:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1716400830
Retry-After: 60
```

## 5. شرح المصادقة

### الطرف C (UserAuth)

1. استخراج Token من `Authorization: Bearer <token>`
2. التحقق من توقيع JWT (HS256)، تحليل `sub` (معرّف المستخدم)
3. الاستعلام من جدول `game_user` للتحقق من وجود المستخدم وأن status=1
4. حقن `$request->userId`

### لوحة الإدارة (AdminAuth + AdminPermission)

1. AdminAuth: التحقق من توقيع JWT، تحليل `sub` (معرّف المشرف)، حقن `$request->adminId`
2. AdminPermission: البحث في صلاحيات دور المستخدم، مطابقة معرّف صلاحية بصيغة `method.path`
3. المشرفون الفائقون ذوو `slug=*` يتجاوزون فحص الصلاحيات

## 6. مرجع رموز الأخطاء

| code | المعنى | السيناريوهات الشائعة |
|------|------|---------|
| 0 | نجاح | - |
| 400 | خطأ في المعاملات | صيغة الطلب غير صحيحة، رصيد غير كافٍ |
| 401 | غير مصادَق | Token مفقود/منتهي/غير صالح، حساب معطّل |
| 403 | لا صلاحية | المستخدم بلا صلاحية الدور المطلوب، اللعبة غير متاحة |
| 404 | غير موجود | المورد غير موجود |
| 422 | فشل التحقق | معاملات النموذج لا تستوفي القواعد، حالة الطلب لا تسمح بالعملية |
| 429 | حد المعدل | طلبات كثيرة جدًا |
| 500 | خطأ في الخادم | استثناء غير متوقع |


## 7. واجهات جديدة (توسعة v2.0)

### 7.1 واجهات Provider — استدعاءات جهة اللعبة

**طريقة المصادقة**: توقيع HMAC-SHA256 (X-Game-Id + X-Timestamp + X-Signature)
**نافذة الوقت**: 5 دقائق

#### POST /api/provider/balance — الاستعلام عن رصيد المستخدم

```
رؤوس الطلب:
  X-Game-Id: 1234567890
  X-Timestamp: 1716400830
  X-Signature: abc123...

الطلب: {
  "user_id": 1234567890,
  "game_id": 9876543210,
  "currency_id": 5555555555
}

الاستجابة: {
  "code": 0,
  "message": "success",
  "data": { "balance": "1000.50000000" }
}
```

#### POST /api/provider/bet — إشعار المراهنة

```
الطلب: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "bet_type": "straight" }
}

الاستجابة: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "990.50000000"
  }
}
```

#### POST /api/provider/settle — إشعار التسوية

```
الطلب: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}

الاستجابة: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1040.50000000",
    "win_amount": "50.00000000"
  }
}
```

#### POST /api/provider/refund — إشعار الاسترداد

```
الطلب: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "reason": "game_crash"
}

الاستجابة: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1000.50000000"
  }
}
```

### 7.2 واجهات التذاكر

#### GET /api/ticket/list — قائمة التذاكر

```
يتطلب مصادقة: نعم
المعلمات: ?page=1&per_page=20

الاستجابة: {
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

#### POST /api/ticket/create — إنشاء تذكرة

```
يتطلب مصادقة: نعم
الطلب: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
الاستجابة: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/ticket/{hashid} — تفاصيل التذكرة

```
يتطلب مصادقة: نعم
الاستجابة: {
  "id": "...", "type": "deposit", "subject": "...",
  "content": "...", "status": "open",
  "replies": [
    { "id": "...", "content": "...", "is_admin": 1, "created_at": "..." }
  ]
}
```

#### POST /api/ticket/{hashid}/reply — الرد على التذكرة

```
يتطلب مصادقة: نعم
الطلب: { "content": "已核实，将在24小时内处理" }
الاستجابة: { "code": 0, "message": "Reply sent" }
```

### 7.3 واجهات التحقق من البريد الإلكتروني

#### POST /api/verify/send-email — إرسال رمز التحقق للبريد

```
يتطلب مصادقة: نعم
الطلب: { "email": "user@example.com" }
الاستجابة: { "code": 0, "message": "Verification code sent" }
خطأ: 429 يرجى إعادة المحاولة بعد 60 ثانية
```

#### POST /api/verify/confirm-email — تأكيد البريد الإلكتروني

```
يتطلب مصادقة: نعم
الطلب: { "code": "123456" }
الاستجابة: { "code": 0, "message": "Email verified" }
خطأ: 422 رمز التحقق غير صالح أو منتهي الصلاحية
```

### 7.4 واجهات VIP

#### GET /api/user/vip-status — حالة VIP

```
يتطلب مصادقة: نعم
الاستجابة: {
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

### 7.5 واجهات الإنجازات

#### GET /api/user/achievements — قائمة الإنجازات

```
يتطلب مصادقة: نعم
الاستجابة: {
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

### 7.6 واجهات لوحة الإدارة الجديدة

#### GET /admin/ticket/list — قائمة التذاكر

```
يتطلب مصادقة: نعم
المعلمات: ?page=1&limit=20&status=pending&type=deposit

الاستجابة: {
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

#### POST /admin/ticket/{hashid}/reply — الرد على التذكرة

```
يتطلب مصادقة: نعم
الطلب: { "content": "已处理" }
الاستجابة: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/ticket/{hashid}/close — إغلاق التذكرة

```
يتطلب مصادقة: نعم
الاستجابة: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/ticket/{hashid}/assign — تعيين المعالج

```
يتطلب مصادقة: نعم
الطلب: { "admin_id": 1234567890 }
الاستجابة: { "code": 0, "message": "Assigned" }
```

#### GET /admin/analytics/retention — تحليل الاحتفاظ

```
يتطلب مصادقة: نعم
المعلمات: ?days=30
الاستجابة: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/analytics/funnel — قمع التحويل

```
يتطلب مصادقة: نعم
الاستجابة: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — اتجاه ARPU/ARPPU

```
يتطلب مصادقة: نعم
المعلمات: ?days=30
الاستجابة: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/analytics/economy — مؤشرات اقتصاد عملات الألعاب

```
يتطلب مصادقة: نعم
الاستجابة: {
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

## 8. سياسة حد المعدل (محدثة)

| الواجهة | الحد |
|------|------|
| الافتراضي | 60 مرة/دقيقة/IP |
| POST /api/auth/login | 10 مرات/دقيقة |
| POST /api/auth/register | 5 مرات/دقيقة |
| POST /api/auth/oauth | 10 مرات/دقيقة |
| POST /api/payment/callback | 30 مرة/دقيقة |
| POST /api/provider/* | بدون حد (مصادقة توقيع HMAC) |

## 9. شرح المصادقة (محدث)

### مصادقة Provider (ProviderAuth)

1. استخراج `X-Game-Id` و`X-Timestamp` و`X-Signature` من رؤوس الطلب
2. الاستعلام من جدول `game_game` للتحقق من وجود اللعبة وأن status=1
3. التحقق من أن الطابع الزمني ضمن نافذة 5 دقائق (الحماية من إعادة التشغيل)
4. حساب `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` ومقارنته بالتوقيع
5. حقن `$request->gameId` و`$request->game`


### 7.7 واجهات الأصدقاء

#### GET /api/friend/list — قائمة الأصدقاء
```
يتطلب مصادقة: نعم
الاستجابة: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/friend/requests — الطلبات المعلقة
```
يتطلب مصادقة: نعم
الاستجابة: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/friend/request — إرسال طلب صداقة
```
يتطلب مصادقة: نعم
الطلب: { "friend_id": "hashid" }
```

#### POST /api/friend/accept — قبول الطلب
```
يتطلب مصادقة: نعم
الطلب: { "request_id": "hashid" }
```

#### POST /api/friend/reject — رفض الطلب
```
يتطلب مصادقة: نعم
الطلب: { "request_id": "hashid" }
```

#### POST /api/friend/remove — حذف صديق
```
يتطلب مصادقة: نعم
الطلب: { "friend_id": "hashid" }
```

#### GET /api/friend/search — البحث عن مستخدمين
```
يتطلب مصادقة: نعم
المعلمات: ?q=username
الاستجابة: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 واجهات المحادثة

#### GET /api/chat/conversations — قائمة المحادثات
```
يتطلب مصادقة: نعم
الاستجابة: {
  "list": [{
    "peer": { "id": "...", "username": "...", "nickname": "...", "avatar": "..." },
    "last_message": "最近一条消息",
    "unread_count": 3,
    "updated_at": "2026-05-22 10:30:00"
  }]
}
```

#### GET /api/chat/messages/{peerHashid} — قائمة الرسائل
```
يتطلب مصادقة: نعم
المعلمات: ?page=1&per_page=50
الاستجابة: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
وضع علامة قراءة تلقائيًا على الرسائل غير المقروءة الواردة من الطرف الآخر
```

#### POST /api/chat/send — إرسال رسالة
```
يتطلب مصادقة: نعم
الطلب: { "to_user_id": "hashid", "content": "Hello!" }
خطأ: 403 لا يمكن الإرسال لغير الأصدقاء
```

#### GET /api/chat/unread-total — إجمالي غير المقروء
```
يتطلب مصادقة: نعم
الاستجابة: { "count": 5 }
```

**اتصال WebSocket**: `ws://host:8791`
```
// المصادقة
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// استقبال الرسائل
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 واجهات Webhook

#### GET /api/webhook/list — قائمة الاشتراكات
```
يتطلب مصادقة: نعم
الاستجابة: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/webhook/register — تسجيل اشتراك
```
يتطلب مصادقة: نعم
الطلب: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
الأحداث المتاحة: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/webhook/delete — حذف الاشتراك
```
يتطلب مصادقة: نعم
الطلب: { "id": "hook_id" }
```

### 7.10 واجهات التحليل المتقدم

#### GET /admin/analytics/retention — تحليل الاحتفاظ
```
يتطلب مصادقة: نعم
الاستجابة: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/analytics/funnel — قمع التحويل
```
يتطلب مصادقة: نعم
الاستجابة: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — اتجاه ARPU/ARPPU
```
يتطلب مصادقة: نعم
المعلمات: ?days=30
الاستجابة: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/analytics/economy — مؤشرات اقتصاد الألعاب
```
يتطلب مصادقة: نعم
الاستجابة: {
  "currencies": [{
    "game_name": "Shooter Master", "currency": "Gold", "symbol": "G",
    "total_minted": "500000.00000000", "total_burned": "320000.00000000",
    "circulation": "180000.00000000", "inflation_rate": "36.00%"
  }]
}
```


### 7.11 واجهات البطولات

#### GET /api/tournament/list — قائمة البطولات
```
المعلمات: ?status=active|upcoming|ended&page=1&per_page=20
الاستجابة: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/tournament/{hashid} — تفاصيل البطولة
```
الاستجابة: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/tournament/{hashid}/join — التسجيل في البطولة
```
يتطلب مصادقة: نعم
خطأ: 422 مسجل مسبقًا / 400 بدأت أو اكتمل العدد / 503 ميزة FeatureFlag مغلقة
```

### 7.12 شروط القسائم (جديد)

يدعم JSON الخاص بـ `conditions` في القسائم:
- `min_deposit`: سلسلة، الحد الأدنى لمبلغ الشحن التراكمي
- `first_user_only`: منطقي، فقط للمستخدمين الجدد الذين لم يشحنوا أبدًا
- `game_id`: رقم صحيح، يجب أن يكون قد لعب اللعبة المحددة

تُتحقق الشروط مرتين: عند فلترة قائمة `available()` وعند الاستلام عبر `claim()`.

### 7.13 الإحالات متعددة المستويات (جديد)

تضيف عمولة الإحالة تقسيمًا من المستوى الثاني:
- L1: يحصل المُحيل المباشر على `referrer_bonus` (الإعداد: referral.referrer_bonus)
- L2: يحصل مُحيل المُحيل على `commission = referrer_bonus * level2_rate` (الإعداد: referral.level2_rate، الافتراضي 5%)
- يُسجَّل في `game_referral_commission` (level/commission_rate/commission_amount)

### 8. سياسة حد المعدل (محدثة)

| الواجهة | الحد |
|------|------|
| POST /api/tournament/{id}/join | 10 مرات/دقيقة |
