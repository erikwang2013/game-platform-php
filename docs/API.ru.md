# Документация API
<!-- lang-nav -->

Languages: **中文** · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Онлайн-интерактивная документация (с поддержкой онлайн-отладки):
- Бизнес-API C-стороны: http://localhost:8788/apidoc/
- Админ-панель: http://localhost:8787/apidoc/
- Пароль: admin123

## 1. Соглашения

### 1.1 Базовый URL

| Сторона | Адрес |
|----|------|
| Админ-панель | `http://localhost:8787` |
| Бизнес-API C-стороны | `http://localhost:8788` |

### 1.2 Общие заголовки запросов

```
Content-Type: application/json
API-Version: v1
Authorization: Bearer <token>    (需要认证的接口)
```

### 1.3 Единый формат ответа

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Значение |
|------|------|
| 0 | Успех |
| 400 | Ошибка параметров |
| 401 | Не аутентифицирован (Token отсутствует/истёк/недействителен) |
| 403 | Нет прав |
| 404 | Ресурс не найден |
| 422 | Ошибка валидации |
| 429 | Слишком много запросов (сработал лимит) |
| 500 | Ошибка сервера |

### 1.4 Кодирование ID

Все ID в запросах и ответах интерфейсов — это закодированные строки Hashids, а не исходные значения BIGINT.

```
外部: aB3xK9mW2pQ7rT5v  (hashid 字符串)
内部: 1750123456789      (Snowflake BIGINT)
```

### 1.5 Формат пагинации

```
请求: ?page=1&per_page=20

响应: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. Интерфейсы C-стороны (service :8788)

### 2.1 Аутентификация

#### POST /api/auth/register — регистрация пользователя

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

#### POST /api/auth/login — вход пользователя

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

Ошибка: 401 неверное имя пользователя или пароль / аккаунт отключён

#### POST /api/auth/refresh — обновление токена

```
请求: (Authorization: Bearer <refresh_token>)

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 Кошелёк

#### GET /api/wallet/info — информация о кошельке

```
需认证: 是

响应: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/wallet/transactions — записи операций

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

### 2.3 Пополнение

#### POST /api/deposit/create — создание ордера на пополнение

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

checkout_url: ссылка перехода на платёжный шлюз (заполняется при создании заказа); expires_at: срок действия платёжной ссылки (1 час после создания)

#### GET /api/deposit/orders — записи пополнений

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

### 2.4 Обмен

#### POST /api/exchange/quote — запрос котировки

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

direction: in=покупка игровой валюты / out=продажа игровой валюты

#### POST /api/exchange/buy — покупка игровой валюты

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

Ошибка: 422 недостаточно платформенной валюты / 404 игра недоступна

#### POST /api/exchange/sell — продажа игровой валюты

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

Ошибка: 422 недостаточно игровой валюты

#### GET /api/exchange/records — записи обмена

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

### 2.5 Вывод средств

#### POST /api/withdraw/apply — заявка на вывод

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
- approved: автоматически одобрен (сумма < auto_approve_threshold)
- pending: ожидает проверки (сумма >= auto_approve_threshold)

Ошибки:
- 403 вывод временно отключён (глобальный переключатель выключен)
- 400 ниже минимальной суммы вывода
- 400 превышен суточный лимит вывода
- 400 недостаточно средств

#### GET /api/withdraw/orders — записи выводов

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

### 2.6 Игры

#### GET /api/game/list — список игр

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

#### GET /api/game/{hashid} — детали игры

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

#### POST /api/game/launch — запуск игры

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

### 2.7 OAuth — вход через третьи стороны

Поддерживается 7 платформ: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/auth/oauth/{provider} — получение URL авторизации

```
参数: provider = google / facebook / apple / twitter / microsoft / linkedin / github

响应: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/auth/oauth/{provider}/callback — колбэк OAuth

```
请求: { "code": "授权码", "state": "防CSRF状态" }

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=новый зарегистрированный пользователь / false=привязка к существующему аккаунту

### 2.8 KYC — верификация личности

#### GET /api/user/identity/status — статус верификации

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

#### POST /api/user/identity/apply — подача заявки на верификацию

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

### 2.9 Платежи

#### POST /api/payment/callback — платёжный колбэк (публичный)

```
请求: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

响应: { "message": "success" }
```

status: success / failed

Допустимые значения provider: stripe / paypal / nowpayments / coinbase / skrill / neteller / paysafecard / paytm / mercadopago / astropay / paypay / kakaopay / gcash (toss / mpesa / paystack скоро)

| provider | Регион | Схема подписи | Поддерживаемые валюты |
|----------|--------|---------------|-----------------------|
| stripe | Глобально (125+ локальных способов оплаты, включая Alipay/WeChat Pay APM) | Webhook HMAC-SHA256 | USD / CNY / EUR |
| paypal | 200+ рынков по всему миру | Проверка webhook (verify-webhook-signature) | USD / CNY / EUR и другие фиатные валюты |
| nowpayments | Глобально (крипто) | IPN HMAC-SHA512 | USDT TRC20 / ERC20 |
| coinbase | Глобально (крипто) | Webhook HMAC-SHA256 (base64 secret) | USDC / BTC / ETH |
| skrill | Европа / Глобально | Проверка MD5 секретного слова | EUR и другие фиатные валюты |
| neteller | Европа / Глобально | Сравнение поля secret key | EUR и другие фиатные валюты |
| paysafecard | Европа (DE / AT / CH и др.) | X-Signature HMAC-SHA256 | EUR и другие фиатные валюты |
| paytm | Индия | SHA256 + AES-128-CBC | INR |
| mercadopago | Латинская Америка (BR / MX и др.) | X-Signature (ts,v1) HMAC-SHA256 | BRL / MXN и другие фиатные валюты |
| astropay | Латинская Америка (BR и др.) | MD5(order_id.amount.status.secret) | BRL и другие фиатные валюты |
| paypay | Япония | PayPay-Signature HMAC-SHA256 | JPY |
| kakaopay | Южная Корея | Без webhook (двухэтапный ready/approve) | KRW |
| gcash | Филиппины | Paymongo-Signature HMAC-SHA256 | PHP |
| toss | Южная Корея (скоро) | — | KRW |
| mpesa | Кения / Танзания и др. (скоро) | — | KES / TZS |
| paystack | Нигерия (скоро) | — | NGN |

#### GET /api/payment/methods — доступные способы оплаты (публичный)

```
响应: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

Фильтруется по стране пользователя (X-Language/Accept-Language → код страны): пустой countries или содержащий * означает видимость во всех странах; сортируется по предпочтению методов оплаты country_config этой страны

### 2.10 Игровые записи

#### GET /api/game/play-logs — список игровых записей

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

#### GET /api/game/play-log/{hashid} — детали игровой записи

```
需认证: 是
响应: { 完整记录，含 session_id / game_amount_before / after 等 }
```

### 2.12 Рейтинги

#### GET /api/leaderboard/list — список рейтингов

```
响应: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/leaderboard/{hashid} — детали рейтинга

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

### 2.13 Купоны

#### GET /api/coupon/available — доступные купоны

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/coupon/claim — получение купона

```
需认证: 是
请求: { "coupon_id": "hashid" }
响应: { "coupon": { ... } }
```

#### GET /api/coupon/my — мои купоны

```
需认证: 是
参数: ?status=unused
响应: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 Конфигурация стран

#### GET /api/country/list — список стран

```
响应: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/country/{code} — детали страны

```
响应: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 Уведомления

#### GET /api/notification/list — список уведомлений

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

#### GET /api/notification/unread-count — количество непрочитанных

```
需认证: 是
响应: { "count": 3 }
```

#### POST /api/notification/read — отметить как прочитанное

```
需认证: 是
请求: { "id": "hashid" }  // 不传=全部已读
```

### 2.17 Рефералы

#### GET /api/referral/my-code — мой реферальный код

```
需认证: 是
响应: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/referral/apply — применение реферального кода

```
需认证: 是
请求: { "code": "ABC12345" }
响应: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/user/2fa/status — статус 2FA

```
需认证: 是
响应: { "enabled": false }
```

#### POST /api/user/2fa/setup — настройка 2FA

```
需认证: 是
响应: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/user/2fa/enable — включение 2FA

```
需认证: 是
请求: { "code": "123456" }
响应: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/2fa/verify — проверка 2FA (публичный)

```
请求: { "user_id": "hashid", "code": "123456" }
响应: { "valid": true }
```

### 2.19 Поиск

#### GET /api/search — глобальный поиск

```
参数: ?q=keyword&type=game&page=1&per_page=20
响应: { "list": [...], "total": 100 }
```

#### GET /api/game/suggest — поисковые подсказки

```
参数: ?q=shoot
响应: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 Языки

#### GET /api/language/list — список доступных языков

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

#### POST /api/language/switch — переключение языка

```
请求: { "locale": "zh-CN" }
响应: { "locale": "zh-CN" }
```

locale 可选值: en-US / zh-CN / ja-JP / ko-KR

### 2.8 Пользователь

#### GET /api/user/profile — личная информация

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

#### PUT /api/user/profile — редактирование профиля

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

### 2.9 Объявления

#### GET /api/announcement/list — список объявлений

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

#### GET /api/announcement/detail/{hashid} — детали объявления

```
响应: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

## 3. Интерфейсы админ-панели (admin :8787)

### 3.1 Дашборд платформы

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

### 3.2 Управление играми

#### GET /admin/game/list — список игр

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

#### POST /admin/game/create — создание игры

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

#### PUT /admin/game/{hashid} — редактирование игры

```
需认证: 是

请求: {
  "name": "新名称",
  "status": 1
  // 可部分更新，字段同 create
}

响应: { "message": "更新成功" }
```

#### DELETE /admin/game/{hashid} — удаление игры

```
需认证: 是
响应: { "message": "删除成功" }
```

#### POST /admin/game/currency/manage — управление валютами

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

### 3.3 Управление выводами

#### GET /admin/withdraw/orders — список ордеров на вывод

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

#### PUT /admin/withdraw/review — проверка вывода

```
需认证: 是

请求: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

响应: { "message": "已通过" }
```

action: approve=одобрить / reject=отклонить (при отказе платформенная валюта автоматически возвращается)

Ошибка: 422 статус ордера не в ожидании проверки

#### PUT /admin/withdraw/switch — глобальный переключатель вывода

```
需认证: 是

请求: { "enabled": 1 }

响应: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/withdraw/limits/set — установка лимитов вывода

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

### 3.4 Управление пользователями платформы

#### GET /admin/platform/user/list — список пользователей C-стороны

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

#### GET /admin/platform/user/{hashid} — детали пользователя

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

#### PUT /admin/platform/user/{hashid} — редактирование/блокировка пользователя

```
需认证: 是

请求: {
  "status": 0,         // 0=禁用 1=启用
  "nickname": "..."    // 可选
}

响应: { "message": "更新成功" }
```

### 3.5 Управление платежами

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

#### POST /admin/payment/method/toggle — включение/отключение способа оплаты

```
需认证: 是

请求: { "id": "aB3xK...", "status": 0 }

响应: { "message": "已更新" }
```

### 3.6 Управление объявлениями

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

#### POST /admin/announcement/create — публикация объявления

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

### 3.7 Проверка KYC

#### GET /admin/identity/list — список KYC

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

#### PUT /admin/identity/review — проверка KYC

```
需认证: 是

请求: { "id": "hashid", "action": "approve", "note": "" }

响应: { "message": "Approved" }
```

action: approve / reject

### 3.8 Управление игровыми серверами

#### GET /admin/game/server/list — список серверов

```
需认证: 是
参数: ?game_id=hashid

响应: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/game/server/create — создание сервера

```
需认证: 是
请求: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
响应: { "id": "hashid" }
```

#### PUT /admin/game/server/{hashid} — редактирование сервера

```
需认证: 是
请求: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/game/server/{hashid} — удаление сервера

```
需认证: 是
```

### 3.9 Управление ступенчатыми лимитами вывода

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

#### PUT /admin/withdraw/limits/{hashid} — обновление лимита

```
需认证: 是

请求: { "single_max": "10000.0000", "fee_pct": "0.25" }
// 可部分更新
```

### 3.11 Управление категориями игр

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

#### PUT /admin/game/category/{hashid} — редактирование категории

#### DELETE /admin/game/category/{hashid} — удаление категории

#### POST /admin/game/category/assign — назначение игр

```
需认证: 是
请求: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 Управление рейтингами

#### GET /admin/leaderboard/list — список рейтингов

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/leaderboard/create — создание рейтинга

```
需认证: 是
请求: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(可选)" }
```

#### PUT /admin/leaderboard/{hashid} — редактирование рейтинга

#### DELETE /admin/leaderboard/{hashid} — удаление рейтинга

#### POST /admin/leaderboard/{hashid}/refresh — обновление кэша

### 3.13 Управление купонами

#### GET /admin/coupon/list — список купонов

#### POST /admin/coupon/create — создание купона

```
需认证: 是
请求: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/coupon/{hashid} — редактирование (если ещё не выдавался)

#### DELETE /admin/coupon/{hashid} — удаление

#### GET /admin/coupon/{hashid}/stats — статистика выдачи

```
响应: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 Управление конфигурацией стран

#### GET /admin/country/config/list — список конфигураций стран

#### POST /admin/country/config/create — создание конфигурации страны

```
需认证: 是
请求: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/country/config/{hashid} — редактирование конфигурации страны

### 3.15 Экспорт данных

#### POST /admin/export/users — экспорт пользователей C-стороны

```
需认证: 是
参数(JSON): { "status": 1 }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

#### POST /admin/export/transactions — экспорт операций платформы

```
需认证: 是
参数(JSON): { "type": "deposit" }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

### 3.16 Анализ данных (реальная агрегация MySQL)

Все эндпоинты требуют аутентификации (AdminAuth + AdminPermission), данные агрегируются в реальном времени из MySQL, ClickHouse не используется.

| Метод | Путь | Описание |
|------|------|------|
| GET | /admin/analytics/overview | Общий обзор платформы (сегодня/за 7 дней) |
| GET | /admin/analytics/game-ranking | Рейтинг игр (?days=7) |
| GET | /admin/analytics/dau-trend | Тренд DAU (?days=30) |
| GET | /admin/analytics/hourly-trend | Почасовая динамика |
| GET | /admin/analytics/action-distribution | Распределение действий |
| GET | /admin/analytics/revenue | Анализ выручки |
| GET | /admin/analytics/conversion | Конверсия игр |
| GET | /admin/analytics/probability | Совместная/условная вероятность |
| GET | /admin/analytics/retention | Анализ удержания D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Конверсионная воронка |
| GET | /admin/analytics/arpu | Тренд ARPU/ARPPU |
| GET | /admin/analytics/economy | Экономические метрики игровых валют |

### 3.17 Управление тикетами

Все эндпоинты требуют аутентификации (AdminAuth + AdminPermission).

| Метод | Путь | Описание |
|------|------|------|
| GET | /admin/ticket/list | Список тикетов (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | Детали тикета (с ответами) |
| POST | /admin/ticket/{hashid}/reply | Ответ на тикет |
| POST | /admin/ticket/{hashid}/close | Закрытие тикета |
| POST | /admin/ticket/{hashid}/assign | Назначение обработчика (admin_id) |

## 4. Стратегия лимитов запросов

| Интерфейс | Лимит |
|------|------|
| По умолчанию | 60 раз/мин/IP |
| POST /api/auth/login | 10 раз/мин |
| POST /api/auth/register | 5 раз/мин |

При превышении возвращается 429, в заголовках ответа:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1716400830
Retry-After: 60
```

## 5. Описание аутентификации

### C-сторона (UserAuth)

1. Извлечение токена из `Authorization: Bearer <token>`
2. Проверка подписи JWT (HS256), извлечение `sub` (ID пользователя)
3. Запрос к таблице `game_user` для проверки существования пользователя и status=1
4. Инъекция `$request->userId`

### Админ-панель (AdminAuth + AdminPermission)

1. AdminAuth: проверка подписи JWT, извлечение `sub` (ID администратора), инъекция `$request->adminId`
2. AdminPermission: поиск прав по роли пользователя, сопоставление с идентификатором права в формате `method.path`
3. Супер-администратор с `slug=*` пропускает проверку прав

## 6. Справочник кодов ошибок

| code | Значение | Частые сценарии |
|------|------|---------|
| 0 | Успех | - |
| 400 | Ошибка параметров | Неверный формат запроса, недостаточно средств |
| 401 | Не аутентифицирован | Token отсутствует/истёк/недействителен, аккаунт отключён |
| 403 | Нет прав | У пользователя нет прав для соответствующей роли, игра недоступна |
| 404 | Не найдено | Ресурс не найден |
| 422 | Ошибка валидации | Параметры формы не соответствуют правилам, статус ордера не допускает операцию |
| 429 | Лимит запросов | Слишком частые запросы |
| 500 | Ошибка сервера | Непредвиденное исключение |


## 7. Новые API (v2.0 расширение экосистемы)

### 7.1 Provider API — колбэки игровой стороны

**Способ аутентификации**: подпись HMAC-SHA256 (X-Game-Id + X-Timestamp + X-Signature)
**Временное окно**: 5 минут

#### POST /api/provider/balance — запрос баланса пользователя

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

#### POST /api/provider/bet — уведомление о ставке

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

#### POST /api/provider/settle — уведомление о расчёте

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

#### POST /api/provider/refund — уведомление о возврате

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

### 7.2 API тикетов

#### GET /api/ticket/list — список тикетов

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

#### POST /api/ticket/create — создание тикета

```
需认证: 是
请求: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
响应: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/ticket/{hashid} — детали тикета

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

#### POST /api/ticket/{hashid}/reply — ответ на тикет

```
需认证: 是
请求: { "content": "已核实，将在24小时内处理" }
响应: { "code": 0, "message": "Reply sent" }
```

### 7.3 API верификации email

#### POST /api/verify/send-email — отправка кода подтверждения email

```
需认证: 是
请求: { "email": "user@example.com" }
响应: { "code": 0, "message": "Verification code sent" }
错误: 429 请60秒后重试
```

#### POST /api/verify/confirm-email — подтверждение email

```
需认证: 是
请求: { "code": "123456" }
响应: { "code": 0, "message": "Email verified" }
错误: 422 验证码无效或已过期
```

### 7.4 VIP API

#### GET /api/user/vip-status — статус VIP

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

### 7.5 API достижений

#### GET /api/user/achievements — список достижений

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

### 7.6 Новые API админ-панели

#### GET /admin/ticket/list — список тикетов

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

#### POST /admin/ticket/{hashid}/reply — ответ на тикет

```
需认证: 是
请求: { "content": "已处理" }
响应: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/ticket/{hashid}/close — закрытие тикета

```
需认证: 是
响应: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/ticket/{hashid}/assign — назначение обработчика

```
需认证: 是
请求: { "admin_id": 1234567890 }
响应: { "code": 0, "message": "Assigned" }
```

#### GET /admin/analytics/retention — анализ удержания

```
需认证: 是
参数: ?days=30
响应: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/analytics/funnel — конверсионная воронка

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

#### GET /admin/analytics/arpu — тренд ARPU/ARPPU

```
需认证: 是
参数: ?days=30
响应: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/analytics/economy — экономические метрики игровых валют

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

## 8. Стратегия лимитов запросов (обновлено)

| Интерфейс | Лимит |
|------|------|
| По умолчанию | 60 раз/мин/IP |
| POST /api/auth/login | 10 раз/мин |
| POST /api/auth/register | 5 раз/мин |
| POST /api/auth/oauth | 10 раз/мин |
| POST /api/payment/callback | 30 раз/мин |
| POST /api/provider/* | без лимита (аутентификация по подписи HMAC) |

## 9. Описание аутентификации (обновлено)

### Аутентификация Provider (ProviderAuth)

1. Извлечение `X-Game-Id`, `X-Timestamp`, `X-Signature` из заголовков запроса
2. Запрос к таблице `game_game` для проверки существования игры и status=1
3. Проверка, что временная метка находится в 5-минутном окне (защита от повторов)
4. Вычисление `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` и сравнение с подписью
5. Инъекция `$request->gameId` и `$request->game`


### 7.7 API друзей

#### GET /api/friend/list — список друзей
```
需认证: 是
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/friend/requests — ожидающие заявки
```
需认证: 是
响应: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/friend/request — отправка заявки в друзья
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### POST /api/friend/accept — принятие заявки
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/friend/reject — отклонение заявки
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/friend/remove — удаление друга
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### GET /api/friend/search — поиск пользователей
```
需认证: 是
参数: ?q=username
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 API чата

#### GET /api/chat/conversations — список диалогов
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

#### GET /api/chat/messages/{peerHashid} — список сообщений
```
需认证: 是
参数: ?page=1&per_page=50
响应: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
自动标记对端发来的未读消息为已读
```

#### POST /api/chat/send — отправка сообщения
```
需认证: 是
请求: { "to_user_id": "hashid", "content": "Hello!" }
错误: 403 非好友不可发
```

#### GET /api/chat/unread-total — общее количество непрочитанных
```
需认证: 是
响应: { "count": 5 }
```

**Подключение WebSocket**: `ws://host:8791`
```
// 认证
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// 接收消息
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 Webhook API

#### GET /api/webhook/list — список подписок
```
需认证: 是
响应: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/webhook/register — регистрация подписки
```
需认证: 是
请求: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
可用事件: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/webhook/delete — удаление подписки
```
需认证: 是
请求: { "id": "hook_id" }
```

### 7.10 API расширенной аналитики

#### GET /admin/analytics/retention — анализ удержания
```
需认证: 是
响应: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/analytics/funnel — конверсионная воронка
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

#### GET /admin/analytics/arpu — тренд ARPU/ARPPU
```
需认证: 是
参数: ?days=30
响应: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/analytics/economy — экономические метрики игр
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


### 7.11 API турниров

#### GET /api/tournament/list — список турниров
```
参数: ?status=active|upcoming|ended&page=1&per_page=20
响应: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/tournament/{hashid} — детали турнира
```
响应: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/tournament/{hashid}/join — регистрация на турнир
```
需认证: 是
错误: 422 已报名 / 400 已开始或已满员 / 503 FeatureFlag关闭
```

### 7.12 Условия купонов (новое)

JSON `conditions` купона поддерживает:
- `min_deposit`: строка, минимальная сумма накопленных пополнений
- `first_user_only`: bool, только новые пользователи, никогда не пополнявшие
- `game_id`: int, требуется игра в указанную игру

Условия проверяются дважды: при фильтрации списка в `available()` и при получении в `claim()`.

### 7.13 Многоуровневые рефералы (новое)

К реферальному вознаграждению добавлено распределение второго уровня:
- L1: прямой пригласивший получает `referrer_bonus` (конфигурация: referral.referrer_bonus)
- L2: пригласивший пригласившего получает `commission = referrer_bonus * level2_rate` (конфигурация: referral.level2_rate, по умолчанию 5%)
- Запись в `game_referral_commission` (level/commission_rate/commission_amount)

### 8. Стратегия лимитов запросов (обновлено)

| Интерфейс | Лимит |
|------|------|
| POST /api/tournament/{id}/join | 10 раз/мин |
