# Документация API
<!-- lang-nav -->

Languages: **中文** · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Онлайн-интерактивная документация (с поддержкой онлайн-отладки):
- Бизнес-API C-стороны: http://localhost:8792/apidoc/
- Админ-панель: http://localhost:8789/apidoc/
- Пароль: см. настройку `APIDOC_PASSWORD` в среде развёртывания

## 1. Соглашения

### 1.1 Базовый URL

| Сторона | Адрес |
|----|------|
| Админ-панель | `http://localhost:8789` |
| Бизнес-API C-стороны | `http://localhost:8792` |

### 1.2 Общие заголовки запросов

```
Content-Type: application/json
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
Внешний: aB3xK9mW2pQ7rT5v  (hashid 字符串)
Внутренний: 1750123456789      (Snowflake BIGINT)
```

### 1.5 Формат пагинации

```
Запрос: ?page=1&per_page=20

Ответ: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. Интерфейсы C-стороны (service :8792)

### 2.1 Аутентификация

#### POST /api/v1/auth/register — регистрация пользователя

```
Запрос: {
  "username": "player1",
  "password": "123456",
  "email": "player@example.com"     // 可选
}

Ответ: {
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

#### POST /api/v1/auth/login — вход пользователя

```
Запрос: {
  "username": "player1",
  "password": "123456"
}

Ответ: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "...", ... }
}
```

Ошибка: 401 неверное имя пользователя или пароль / аккаунт отключён

#### POST /api/v1/auth/refresh — обновление токена

```
Запрос: (Authorization: Bearer <refresh_token>)

Ответ: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 Кошелёк

#### GET /api/v1/wallet/info — информация о кошельке

```
Требуется аутентификация: Да

Ответ: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/v1/wallet/transactions — записи операций

```
Требуется аутентификация: Да
Параметры: ?page=1&per_page=20&type=deposit    (type 可选)

Ответ: {
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

#### POST /api/v1/deposit/create — создание ордера на пополнение

```
Требуется аутентификация: Да

Запрос: {
  "amount": "10.00",
  "currency": "USD",
  "payment_method_id": "aB3xK..."
}

Ответ: {
  "order_id": "aB3xK...",
  "order_no": "DEP202605221030000123",
  "amount": "10.00",
  "platform_amount": "10.0000",
  "checkout_url": "https://checkout.stripe.com/...",
  "expires_at": "2026-05-22 11:30:00"
}
```

currency 可选值: USD / CNY / EUR / JPY / KRW / GBP / BRL / INR

checkout_url: ссылка перехода на платёжный шлюз (заполняется при создании заказа); expires_at: срок действия платёжной ссылки (1 час после создания)

#### GET /api/v1/deposit/orders — записи пополнений

```
Требуется аутентификация: Да
Параметры: ?page=1&per_page=20

Ответ: {
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

#### POST /api/v1/exchange/quote — запрос котировки

```
Требуется аутентификация: Да

Запрос: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "direction": "in",
  "platform_amount": "10.0000"
}

Ответ: {
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000",
  "spread_pct": "5.00%"
}
```

direction: in=покупка игровой валюты / out=продажа игровой валюты

#### POST /api/v1/exchange/buy — покупка игровой валюты

```
Требуется аутентификация: Да

Запрос: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "10.0000"
}

Ответ: {
  "exchange_id": "aB3xK...",
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000"
}
```

Ошибка: 422 недостаточно платформенной валюты / 404 игра недоступна

#### POST /api/v1/exchange/sell — продажа игровой валюты

```
Требуется аутентификация: Да

Запрос: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "950.0000"
}

Ответ: {
  "exchange_id": "aB3xK...",
  "platform_amount": "9.0250",
  "game_amount": "950.0000",
  "spread_fee": "0.4750",
  "rate": "100.00000000"
}
```

Ошибка: 422 недостаточно игровой валюты

#### GET /api/v1/exchange/records — записи обмена

```
Требуется аутентификация: Да
Параметры: ?page=1&per_page=20

Ответ: {
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

#### POST /api/v1/withdraw/apply — заявка на вывод

```
Требуется аутентификация: Да

Запрос: {
  "platform_amount": "50.0000",
  "method": "paypal",
  "account_info": "user@paypal.com"
}

Ответ: {
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

#### GET /api/v1/withdraw/orders — записи выводов

```
Требуется аутентификация: Да
Параметры: ?page=1&per_page=20

Ответ: {
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

#### GET /api/v1/game/list — список игр

```
Параметры: ?page=1&per_page=20&keyword=射击&type=self

Ответ: {
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

type 可选值: self / embedded / third_party

#### GET /api/v1/game/detail/{hashid} — детали игры

```
Ответ: {
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

#### POST /api/v1/game/launch — запуск игры

```
Требуется аутентификация: Да

Запрос: { "game_id": "aB3xK..." }

Ответ: {
  "id": "...",
  "name": "射击大师",
  "type": "self",
  "api_endpoint": "https://game.example.com/play"
}
```

### 2.7 OAuth — вход через третьи стороны

Поддерживается 7 платформ: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/v1/auth/oauth/{provider} — получение URL авторизации

```
Параметры: provider = google / facebook / apple / twitter / microsoft / linkedin / github

Ответ: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/v1/auth/oauth/{provider}/callback — колбэк OAuth

```
Запрос: { "code": "授权码", "state": "防CSRF状态" }

Ответ: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=новый зарегистрированный пользователь / false=привязка к существующему аккаунту

### 2.8 KYC — верификация личности

#### GET /api/v1/user/identity/status — статус верификации

```
Требуется аутентификация: Да

Ответ: {
  "status": "approved",          // not_submitted / pending / approved / rejected
  "real_name": "J***",
  "id_type": "id_card",
  "review_note": "",
  "submitted_at": "2026-05-22 10:00:00",
  "reviewed_at": "2026-05-23 14:00:00"
}
```

#### POST /api/v1/user/identity/apply — подача заявки на верификацию

```
Требуется аутентификация: Да

Запрос: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}

Ответ: { "message": "KYC submitted successfully" }
```

### 2.9 Платежи

#### POST /api/v1/payment/callback — платёжный колбэк (публичный)

```
Запрос: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

Ответ: { "message": "success" }
```

status: success / failed

Допустимые значения provider: stripe / paypal / nowpayments / coinbase / skrill / neteller / paysafecard / paytm / mercadopago / astropay / paypay / kakaopay / gcash / mpesa / paystack / toss / adyen / grabpay

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
| toss | Южная Корея | Server-side verify + amount check | KRW |
| mpesa | Кения | Trusted IP (CALLBACK_TRUSTED_IPS), no signature | KES |
| paystack | Нигерия | x-paystack-signature HMAC-SHA512 | NGN |
| adyen | Весь мир (валюта по заказу) | additionalData.hmacSignature HMAC-SHA256 (ADYEN_HMAC_KEY) | по заказу |
| grabpay | Сингапур (страна настраивается, по умолчанию SG) | x-signature HMAC-SHA256 (sorted key:value) | по заказу |

#### GET /api/v1/payment/methods — доступные способы оплаты (публичный)

```
Ответ: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

Фильтруется по стране пользователя (X-Language/Accept-Language → код страны): пустой countries или содержащий * означает видимость во всех странах; сортируется по предпочтению методов оплаты country_config этой страны

### 2.10 Игровые записи

#### GET /api/v1/game/play-logs — список игровых записей

```
Требуется аутентификация: Да
Параметры: ?page=1&per_page=20&game_id=xxx&action=start

Ответ: {
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

#### GET /api/v1/game/play-log/{hashid} — детали игровой записи

```
Требуется аутентификация: Да
Ответ: { 完整记录，含 session_id / game_amount_before / after 等 }
```

### 2.12 Рейтинги

#### GET /api/v1/leaderboard/list — список рейтингов

```
Ответ: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/v1/leaderboard/{hashid} — детали рейтинга

```
Ответ: {
  "id": "...",
  "name": "全服累计收入榜",
  "type": "total",
  "rankings": [
    { "rank": 1, "user_id": "...", "score": "50000.0000" }
  ]
}
```

### 2.13 Купоны

#### GET /api/v1/coupon/available — доступные купоны

```
Требуется аутентификация: Да
Ответ: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/v1/coupon/claim — получение купона

```
Требуется аутентификация: Да
Запрос: { "coupon_id": "hashid" }
Ответ: { "coupon": { ... } }
```

#### GET /api/v1/coupon/my — мои купоны

```
Требуется аутентификация: Да
Параметры: ?status=unused
Ответ: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 Конфигурация стран

#### GET /api/v1/country/list — список стран

```
Ответ: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/v1/country/{code} — детали страны

```
Ответ: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 Уведомления

#### GET /api/v1/notification/list — список уведомлений

```
Требуется аутентификация: Да
Параметры: ?page=1&per_page=20&is_read=0

Ответ: {
  "list": [
    { "id": "...", "type": "deposit", "title": "Deposit Received", "is_read": 0, "created_at": "..." }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### GET /api/v1/notification/unread-count — количество непрочитанных

```
Требуется аутентификация: Да
Ответ: { "count": 3 }
```

#### POST /api/v1/notification/read — отметить как прочитанное

```
Требуется аутентификация: Да
Запрос: { "id": "hashid" }  // 不传=全部已读
```

### 2.17 Рефералы

#### GET /api/v1/referral/my-code — мой реферальный код

```
Требуется аутентификация: Да
Ответ: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/v1/referral/apply — применение реферального кода

```
Требуется аутентификация: Да
Запрос: { "code": "ABC12345" }
Ответ: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/v1/user/2fa/status — статус 2FA

```
Требуется аутентификация: Да
Ответ: { "enabled": false }
```

#### POST /api/v1/user/2fa/setup — настройка 2FA

```
Требуется аутентификация: Да
Ответ: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/v1/user/2fa/enable — включение 2FA

```
Требуется аутентификация: Да
Запрос: { "code": "123456" }
Ответ: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/v1/2fa/verify — проверка 2FA (публичный)

```
Запрос: { "user_id": "hashid", "code": "123456" }
Ответ: { "valid": true }
```

### 2.19 Поиск

#### GET /api/v1/search — глобальный поиск

```
Параметры: ?q=keyword&type=game&page=1&per_page=20
Ответ: { "list": [...], "total": 100 }
```

#### GET /api/v1/game/suggest — поисковые подсказки

```
Параметры: ?q=shoot
Ответ: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 Языки

#### GET /api/v1/language/list — список доступных языков

```
Ответ: {
  "current": "en-US",
  "languages": {
    "en-US": { "name": "English", "nativeName": "English", "icon": "us" },
    "zh-CN": { "name": "Chinese (Simplified)", "nativeName": "简体中文", "icon": "cn" },
    "ja-JP": { "name": "Japanese", "nativeName": "日本語", "icon": "jp" },
    "ko-KR": { "name": "Korean", "nativeName": "한국어", "icon": "kr" }
  }
}
```

#### POST /api/v1/language/switch — переключение языка

```
Запрос: { "locale": "zh-CN" }
Ответ: { "locale": "zh-CN" }
```

locale 可选值: en-US / zh-CN / ja-JP / ko-KR

### 2.8 Пользователь

#### GET /api/v1/user/profile — личная информация

```
Требуется аутентификация: Да

Ответ: {
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

#### PUT /api/v1/user/profile — редактирование профиля

```
Требуется аутентификация: Да

Запрос: {
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}

Ответ: {
  "id": "...",
  "username": "player1",
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}
```

language 可选值: en-US / zh-CN / ja-JP / ko-KR

### 2.9 Объявления

#### GET /api/v1/announcement/list — список объявлений

```
Ответ: {
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

#### GET /api/v1/announcement/detail/{hashid} — детали объявления

```
Ответ: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

### 2.21 Статистика платформы

| Метод | Путь | Описание | Аутентификация |
|------|------|------|------|
| GET | /api/v1/platform/stats | Публичная статистика платформы (всего игр/всего пользователей/сегодняшних игр/активных за 7 дней) | нет |

#### GET /api/v1/platform/stats — Статистика платформы

```
无需认证

Ответ: {
  "total_games": 12,
  "total_users": 1500,
  "today_game_plays": 320,
  "active_users_7d": 450
}
```

## 3. Интерфейсы админ-панели (admin :8789)

### 3.1 Дашборд платформы

#### GET /admin/v1/dashboard/platform

```
Требуется аутентификация: Да (AdminAuth + AdminPermission)

Ответ: {
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

#### GET /admin/v1/game/list — список игр

```
Требуется аутентификация: Да
Параметры: ?page=1&limit=20&keyword=射击

Ответ: {
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

#### GET /admin/v1/game/{hashid} — детали игры

```
Требуется аутентификация: Да
Параметры: hashid 为游戏的 hashid 编码（路径参数）

Ответ: {
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

Возвращает code 404, если игра не найдена.

#### POST /admin/v1/game/launch — предварительный просмотр игры

```
Требуется аутентификация: Да

Запрос: {
  "game_id": "aB3xK..."      // 游戏 ID(hashid)
}

Ответ: {
  "id": "aB3xK...",
  "name": "射击大师",
  "slug": "shooter-master",
  "type": "self",
  "api_endpoint": "https://...",
  "preview": true
}
```

Если `game_id` отсутствует, возвращается code 422; если игры не существует, возвращается 404; если игра не опубликована (`status` не 1), возвращается 403.

Админ-превью — это чистое превью: оно только проверяет доступность игры и возвращает информацию о запуске, **не записывает игровые записи и не затрагивает кошелёк**. Админ-идентичности содержат только `adminId` (внедряется `AdminAuth`) и не имеют `userId` C-стороны, поэтому этот эндпоинт намеренно не выполняет никаких пользовательских записей — копирование `POST /api/v1/game/launch` C-стороны создало бы строки `game_game_play_log` с неверной привязкой.

#### POST /admin/v1/game/create — создание игры

```
Требуется аутентификация: Да

Запрос: {
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

Ответ: { "id": "aB3xK..." }
```

type 可选值: self / embedded / third_party

#### PUT /admin/v1/game/{hashid} — редактирование игры

```
Требуется аутентификация: Да

Запрос: {
  "name": "新名称",
  "status": 1
  // 可部分更新，字段同 create
}

Ответ: { "message": "更新成功" }
```

#### DELETE /admin/v1/game/{hashid} — удаление игры

```
Требуется аутентификация: Да
Ответ: { "message": "删除成功" }
```

#### POST /admin/v1/game/currency/manage — управление валютами

```
Требуется аутентификация: Да

Запрос: {
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

Ответ: { "message": "操作成功" }
```

Если `game_id` отсутствует или `currencies` не является массивом, возвращается 422; если игры не существует, возвращается 404.

`exchange_rate` и `spread_pct` проверяются только при передаче: `exchange_rate` должен быть числом больше 0, а `spread_pct` должен находиться в диапазоне [0, 100); нарушение любого из условий возвращает 422, и ни одна валюта не записывается (пакет полностью проверяется перед записью). Непереданные поля не проходят проверку: при создании берутся значения по умолчанию (`exchange_rate` — `1.00000000`, остальные — `0.00000000`), при обновлении сохраняется прежнее значение.

### 3.3 Управление выводами

#### GET /admin/v1/withdraw/orders — список ордеров на вывод

```
Требуется аутентификация: Да
Параметры: ?page=1&limit=20&status=pending

Ответ: {
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

#### PUT /admin/v1/withdraw/review — проверка вывода

```
Требуется аутентификация: Да

Запрос: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

Ответ: { "message": "已通过" }
```

action: approve=одобрить / reject=отклонить / confirm=подтвердить (при отказе платформенная валюта автоматически возвращается)

Ошибка: 422 статус ордера не в ожидании проверки

#### PUT /admin/v1/withdraw/switch — глобальный переключатель вывода

```
Требуется аутентификация: Да

Запрос: { "enabled": 1 }

Ответ: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/v1/withdraw/limits/set — установка лимитов вывода

```
Требуется аутентификация: Да

Запрос: {
  "daily_limit": "10000.0000",             // 可选
  "min_amount": "1.0000",                  // 可选
  "auto_approve_threshold": "100.0000"     // 可选
}

Ответ: {
  "daily_limit": "10000.0000",
  "min_amount": "1.0000",
  "auto_approve_threshold": "100.0000",
  "global_switch": true
}
```

#### POST /admin/v1/withdraw/batch-review — Пакетная проверка выводов

```
Требуется аутентификация: Да

Запрос: {
  "ids": ["aB3xK...", "cD4yL..."],
  "action": "approve",
  "note": "批量审核通过"
}

Ответ: {
  "processed": 2,
  "failed": []
}
```

action: approve=одобрить / reject=отклонить (обработка по каждому заказу; отклонённые заказы возвращаются автоматически; неудачные попадают в failed и не влияют на остальные)

#### POST /admin/v1/withdraw/execute-payout — Выполнить выплату

```
Требуется аутентификация: Да

Запрос: { "order_id": "aB3xK..." }

Ответ: {
  "payout_batch_id": "PAYOUT-123456",
  "payout_item_id": "ITEM-123456",
  "payout_status": "success",
  "payout_attempts": 1
}
```

Выплата возможна только для заказов в статусе approved (атомарный перевод в processing); повторный вызов возвращает 422. При включённой двойной проверке заказ должен быть предварительно подтверждён вторым сотрудником

#### POST /admin/v1/withdraw/sync-payout — Синхронизировать статус выплаты

```
Требуется аутентификация: Да

Запрос: { "order_id": "aB3xK..." }

Ответ: {
  "payout_status": "success",
  "order_status": "completed",
  "synced_status": "success"
}
```

Ошибка: 422 Для этого заказа выплата ещё не выполнялась

### 3.4 Управление пользователями платформы

#### GET /admin/v1/platform/user/list — список пользователей C-стороны

```
Требуется аутентификация: Да
Параметры: ?page=1&limit=20&keyword=player&status=1

Ответ: {
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

#### GET /admin/v1/platform/user/{hashid} — детали пользователя

```
Требуется аутентификация: Да

Ответ: {
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

#### PUT /admin/v1/platform/user/{hashid} — редактирование/блокировка пользователя

```
Требуется аутентификация: Да

Запрос: {
  "status": 0,         // 0=禁用 1=启用
  "nickname": "..."    // 可选
}

Ответ: { "message": "更新成功" }
```

### 3.5 Управление платежами

#### GET /admin/v1/payment/method/list

```
Требуется аутентификация: Да

Ответ: {
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

#### POST /admin/v1/payment/method/toggle — включение/отключение способа оплаты

```
Требуется аутентификация: Да

Запрос: { "id": "aB3xK...", "status": 0 }

Ответ: { "message": "已更新" }
```

### 3.6 Управление объявлениями

#### GET /admin/v1/announcement/list

```
Требуется аутентификация: Да
Параметры: ?page=1&limit=20

Ответ: {
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

#### POST /admin/v1/announcement/create — публикация объявления

```
Требуется аутентификация: Да

Запрос: {
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护。",
  "type": "system",           // 可选, 默认"system"
  "target_lang": "",          // 可选, 空=全语言
  "status": 1,                // 可选, 默认1 (0=草稿 1=发布)
  "start_at": "2026-05-23 02:00:00",  // 可选
  "end_at": "2026-05-23 04:00:00"     // 可选
}

Ответ: { "id": "aB3xK..." }
```

### 3.7 Проверка KYC

#### GET /admin/v1/identity/list — список KYC

```
Требуется аутентификация: Да
Параметры: ?page=1&limit=20&status=pending

Ответ: {
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

#### PUT /admin/v1/identity/review — проверка KYC

```
Требуется аутентификация: Да

Запрос: { "id": "hashid", "action": "approve", "note": "" }

Ответ: { "message": "Approved" }
```

action: approve / reject

### 3.8 Управление игровыми серверами

#### GET /admin/v1/game/server/list — список серверов

```
Требуется аутентификация: Да
Параметры: ?game_id=hashid

Ответ: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/v1/game/server/create — создание сервера

```
Требуется аутентификация: Да
Запрос: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
Ответ: { "id": "hashid" }
```

#### PUT /admin/v1/game/server/{hashid} — редактирование сервера

```
Требуется аутентификация: Да
Запрос: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/v1/game/server/{hashid} — удаление сервера

```
Требуется аутентификация: Да
```

### 3.9 Управление ступенчатыми лимитами вывода

#### GET /admin/v1/withdraw/limits/list

```
Требуется аутентификация: Да

Ответ: {
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

#### PUT /admin/v1/withdraw/limits/{hashid} — обновление лимита

```
Требуется аутентификация: Да

Запрос: { "single_max": "10000.0000", "fee_pct": "0.25" }
// 可部分更新
```

### 3.11 Управление категориями игр

#### GET /admin/v1/game/category/list

```
Требуется аутентификация: Да
Ответ: { "list": [{ "id": "...", "name": "动作", "slug": "action", "sort": 1 }] }
```

#### POST /admin/v1/game/category/create

```
Требуется аутентификация: Да
Запрос: { "name": "新分类", "slug": "new-cat", "icon": "star", "sort": 10 }
Ответ: { "id": "hashid" }
```

#### PUT /admin/v1/game/category/{hashid} — редактирование категории

#### DELETE /admin/v1/game/category/{hashid} — удаление категории

#### POST /admin/v1/game/category/assign — назначение игр

```
Требуется аутентификация: Да
Запрос: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 Управление рейтингами

#### GET /admin/v1/leaderboard/list — список рейтингов

```
Требуется аутентификация: Да
Ответ: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/v1/leaderboard/create — создание рейтинга

```
Требуется аутентификация: Да
Запрос: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(可选)" }
```

#### PUT /admin/v1/leaderboard/{hashid} — редактирование рейтинга

#### DELETE /admin/v1/leaderboard/{hashid} — удаление рейтинга

#### POST /admin/v1/leaderboard/{hashid}/refresh — обновление кэша

### 3.13 Управление купонами

#### GET /admin/v1/coupon/list — список купонов

#### POST /admin/v1/coupon/create — создание купона

```
Требуется аутентификация: Да
Запрос: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/v1/coupon/{hashid} — редактирование (если ещё не выдавался)

#### DELETE /admin/v1/coupon/{hashid} — удаление

#### GET /admin/v1/coupon/{hashid}/stats — статистика выдачи

```
Ответ: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 Управление конфигурацией стран

#### GET /admin/v1/country/config/list — список конфигураций стран

#### POST /admin/v1/country/config/create — создание конфигурации страны

```
Требуется аутентификация: Да
Запрос: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/v1/country/config/{hashid} — редактирование конфигурации страны

### 3.15 Экспорт данных

#### POST /admin/v1/export/users — экспорт пользователей C-стороны

```
Требуется аутентификация: Да
Параметры(JSON): { "status": 1 }   // 可选筛选

Ответ: Excel 文件下载 (xlsx)
```

#### POST /admin/v1/export/transactions — экспорт операций платформы

```
Требуется аутентификация: Да
Параметры(JSON): { "type": "deposit" }   // 可选筛选

Ответ: Excel 文件下载 (xlsx)
```

### 3.16 Анализ данных (реальная агрегация MySQL)

Все эндпоинты требуют аутентификации (AdminAuth + AdminPermission), данные агрегируются в реальном времени из MySQL, ClickHouse не используется.

| Метод | Путь | Описание |
|------|------|------|
| GET | /admin/v1/analytics/overview | Общий обзор платформы (сегодня/за 7 дней) |
| GET | /admin/v1/analytics/game-ranking | Рейтинг игр (?days=7) |
| GET | /admin/v1/analytics/dau-trend | Тренд DAU (?days=30) |
| GET | /admin/v1/analytics/hourly-trend | Почасовая динамика |
| GET | /admin/v1/analytics/action-distribution | Распределение действий |
| GET | /admin/v1/analytics/revenue | Анализ выручки |
| GET | /admin/v1/analytics/conversion | Конверсия игр |
| GET | /admin/v1/analytics/probability | Совместная/условная вероятность |
| GET | /admin/v1/analytics/retention | Анализ удержания D1/D3/D7/D30 |
| GET | /admin/v1/analytics/funnel | Конверсионная воронка |
| GET | /admin/v1/analytics/arpu | Тренд ARPU/ARPPU |
| GET | /admin/v1/analytics/economy | Экономические метрики игровых валют |

### 3.17 Управление тикетами

Все эндпоинты требуют аутентификации (AdminAuth + AdminPermission).

| Метод | Путь | Описание |
|------|------|------|
| GET | /admin/v1/ticket/list | Список тикетов (?page=&limit=&status=&type=) |
| GET | /admin/v1/ticket/{hashid} | Детали тикета (с ответами) |
| POST | /admin/v1/ticket/{hashid}/reply | Ответ на тикет |
| POST | /admin/v1/ticket/{hashid}/close | Закрытие тикета |
| POST | /admin/v1/ticket/{hashid}/assign | Назначение обработчика (admin_id) |

### 3.18 Управление конфигурацией CDN

Все эндпоинты требуют аутентификации (AdminAuth + AdminPermission).

| Метод | Путь | Описание | Аутентификация |
|------|------|------|------|
| GET | /admin/v1/cdn/provider/list | Список CDN-провайдеров (учётные данные не возвращаются) | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/toggle | Включение/отключение провайдера {id, status} | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/create | Создание {name, provider, config(JSON), status, sort}, проверка уникальности provider | AdminAuth + RBAC: cdn |
| PUT | /admin/v1/cdn/provider/{hashid} | Обновление (пустой config = без изменений) | AdminAuth + RBAC: cdn |
| DELETE | /admin/v1/cdn/provider/{hashid} | Удаление | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/test | Тест подключения HeadBucket {id} | AdminAuth + RBAC: cdn |

### 3.19 Отчёты по данным

Все эндпоинты требуют аутентификации (AdminAuth + AdminPermission).

| Метод | Путь | Описание | Аутентификация |
|------|------|------|------|
| GET | /admin/v1/report/summary | Сводный отчёт (новые пользователи/депозиты/выводы/обмены/игры) | AdminAuth + RBAC: report |
| GET | /admin/v1/report/daily | Ежедневный отчёт (агрегация по дням, пустые даты заполняются 0) | AdminAuth + RBAC: report |
| GET | /admin/v1/report/export | Экспорт ежедневного отчёта в CSV (UTF-8 BOM) | AdminAuth + RBAC: report |

## 4. Стратегия лимитов запросов

| Интерфейс | Лимит |
|------|------|
| По умолчанию | 60 раз/мин/IP |
| POST /api/v1/auth/login | 10 раз/мин |
| POST /api/v1/auth/register | 5 раз/мин |

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
Заголовки запроса:
  X-Game-Id: 1234567890
  X-Timestamp: 1716400830
  X-Signature: abc123...

Запрос: {
  "user_id": 1234567890,
  "game_id": 9876543210,
  "currency_id": 5555555555
}

Ответ: {
  "code": 0,
  "message": "success",
  "data": { "balance": "1000.50000000" }
}
```

#### POST /api/provider/bet — уведомление о ставке

```
Запрос: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "bet_type": "straight" }
}

Ответ: {
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
Запрос: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}

Ответ: {
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
Запрос: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "reason": "game_crash"
}

Ответ: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1000.50000000"
  }
}
```

### 7.2 API тикетов

#### GET /api/v1/ticket/list — список тикетов

```
Требуется аутентификация: Да
Параметры: ?page=1&per_page=20

Ответ: {
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

#### POST /api/v1/ticket/create — создание тикета

```
Требуется аутентификация: Да
Запрос: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
Ответ: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/v1/ticket/{hashid} — детали тикета

```
Требуется аутентификация: Да
Ответ: {
  "id": "...", "type": "deposit", "subject": "...",
  "content": "...", "status": "open",
  "replies": [
    { "id": "...", "content": "...", "is_admin": 1, "created_at": "..." }
  ]
}
```

#### POST /api/v1/ticket/{hashid}/reply — ответ на тикет

```
Требуется аутентификация: Да
Запрос: { "content": "已核实，将在24小时内处理" }
Ответ: { "code": 0, "message": "Reply sent" }
```

### 7.3 API верификации email

#### POST /api/v1/verify/send-email — отправка кода подтверждения email

```
Требуется аутентификация: Да
Запрос: { "email": "user@example.com" }
Ответ: { "code": 0, "message": "Verification code sent" }
Ошибка: 429 请60秒后重试
```

#### POST /api/v1/verify/confirm-email — подтверждение email

```
Требуется аутентификация: Да
Запрос: { "code": "123456" }
Ответ: { "code": 0, "message": "Email verified" }
Ошибка: 422 验证码无效或已过期
```

### 7.4 VIP API

#### GET /api/v1/user/vip-status — статус VIP

> **Не реализовано**: маршрут C-стороны не зарегистрирован (нет записи в `service/config/route.php`), запросы сейчас возвращают 404. Удалите эту строку после реализации.

```
Требуется аутентификация: Да
Ответ: {
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

#### GET /api/v1/user/achievements — список достижений

> **Не реализовано**: маршрут C-стороны не зарегистрирован (нет записи в `service/config/route.php`), запросы сейчас возвращают 404. Удалите эту строку после реализации.

```
Требуется аутентификация: Да
Ответ: {
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

#### GET /admin/v1/ticket/list — список тикетов

```
Требуется аутентификация: Да
Параметры: ?page=1&limit=20&status=pending&type=deposit

Ответ: {
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

#### POST /admin/v1/ticket/{hashid}/reply — ответ на тикет

```
Требуется аутентификация: Да
Запрос: { "content": "已处理" }
Ответ: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/v1/ticket/{hashid}/close — закрытие тикета

```
Требуется аутентификация: Да
Ответ: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/v1/ticket/{hashid}/assign — назначение обработчика

```
Требуется аутентификация: Да
Запрос: { "admin_id": 1234567890 }
Ответ: { "code": 0, "message": "Assigned" }
```

#### GET /admin/v1/analytics/retention — анализ удержания

```
Требуется аутентификация: Да
Параметры: ?days=30
Ответ: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/v1/analytics/funnel — конверсионная воронка

```
Требуется аутентификация: Да
Ответ: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/v1/analytics/arpu — тренд ARPU/ARPPU

```
Требуется аутентификация: Да
Параметры: ?days=30
Ответ: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/v1/analytics/economy — экономические метрики игровых валют

```
Требуется аутентификация: Да
Ответ: {
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


#### GET /admin/v1/cdn/provider/list — Список CDN-провайдеров (учётные данные не возвращаются)

```
Требуется аутентификация: Да
Ответ: { "list": [ { "id": "...", "name": "...", "provider": "cloudflare", "status": 1, "sort": 0 } ] }
```

#### POST /admin/v1/cdn/provider/toggle — Включение/отключение провайдера {id, status}

```
Требуется аутентификация: Да
Запрос: { "id": "...", "status": 1 }
Ответ: { "code": 0, "message": "..." }
```

#### POST /admin/v1/cdn/provider/create — Создание {name, provider, config(JSON), status, sort}, проверка уникальности provider

```
Требуется аутентификация: Да
Запрос: { "name": "...", "provider": "aliyun", "config": "{...}", "status": 1, "sort": 0 }
Ответ: { "code": 0, "data": { "id": "..." } }
```

#### PUT /admin/v1/cdn/provider/{hashid} — Обновление (пустой config = без изменений)

```
Требуется аутентификация: Да
Запрос: { "name": "...", "config": "" }
Ответ: { "code": 0, "message": "..." }
```

#### DELETE /admin/v1/cdn/provider/{hashid} — Удаление

```
Требуется аутентификация: Да
Ответ: { "code": 0, "message": "..." }
```

#### POST /admin/v1/cdn/provider/test — Тест подключения HeadBucket {id}

```
Требуется аутентификация: Да
Запрос: { "id": "..." }
Ответ: { "code": 0, "data": { "ok": true } }
```
#### GET /admin/v1/report/summary — Сводный отчёт

```
Требуется аутентификация: Да
Параметры: ?start=Y-m-d&end=Y-m-d (缺省最近30天，跨度 ≤90 天，Redis 缓存5分钟)
Ответ: {
  "start": "2026-08-01", "end": "2026-08-31",
  "new_users": 120, "deposit_amount": "5000.0000", "deposit_count": 45,
  "withdraw_amount": "1200.0000", "withdraw_count": 8,
  "exchange_amount": "3000.0000", "play_count": 1500
}
```


#### GET /admin/v1/report/daily — Ежедневный отчёт

```
Требуется аутентификация: Да
Параметры: ?start=Y-m-d&end=Y-m-d
Ответ: {
  "start": "2026-08-01", "end": "2026-08-31",
  "rows": [ { "date": "2026-08-01", "new_users": 12, "deposit_amount": "500.0000", "deposit_count": 4, "withdraw_amount": "100.0000", "withdraw_count": 1, "exchange_amount": "300.0000", "play_count": 150 } ]
}
```


#### GET /admin/v1/report/export — Экспорт отчёта в CSV

```
Требуется аутентификация: Да
Параметры: ?start=Y-m-d&end=Y-m-d&format=excel
Ответ: CSV 文件（UTF-8 BOM），文件名 report_{start}_{end}.csv，Excel 可直接打开
```

## 8. Стратегия лимитов запросов (обновлено)

| Интерфейс | Лимит |
|------|------|
| По умолчанию | 60 раз/мин/IP |
| POST /api/v1/auth/login | 10 раз/мин |
| POST /api/v1/auth/register | 5 раз/мин |
| POST /api/v1/auth/oauth | 10 раз/мин |
| POST /api/v1/payment/callback | 30 раз/мин |
| POST /api/provider/* | без лимита (аутентификация по подписи HMAC) |

## 9. Описание аутентификации (обновлено)

### Аутентификация Provider (ProviderAuth)

1. Извлечение `X-Game-Id`, `X-Timestamp`, `X-Signature` из заголовков запроса
2. Запрос к таблице `game_game` для проверки существования игры и status=1
3. Проверка, что временная метка находится в 5-минутном окне (защита от повторов)
4. Вычисление `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` и сравнение с подписью
5. Инъекция `$request->gameId` и `$request->game`


### 7.7 API друзей

#### GET /api/v1/friend/list — список друзей
```
Требуется аутентификация: Да
Ответ: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/v1/friend/requests — ожидающие заявки
```
Требуется аутентификация: Да
Ответ: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/v1/friend/request — отправка заявки в друзья
```
Требуется аутентификация: Да
Запрос: { "friend_id": "hashid" }
```

#### POST /api/v1/friend/accept — принятие заявки
```
Требуется аутентификация: Да
Запрос: { "request_id": "hashid" }
```

#### POST /api/v1/friend/reject — отклонение заявки
```
Требуется аутентификация: Да
Запрос: { "request_id": "hashid" }
```

#### POST /api/v1/friend/remove — удаление друга
```
Требуется аутентификация: Да
Запрос: { "friend_id": "hashid" }
```

#### GET /api/v1/friend/search — поиск пользователей
```
Требуется аутентификация: Да
Параметры: ?q=username
Ответ: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 API чата

#### GET /api/v1/chat/conversations — список диалогов
```
Требуется аутентификация: Да
Ответ: {
  "list": [{
    "peer": { "id": "...", "username": "...", "nickname": "...", "avatar": "..." },
    "last_message": "最近一条消息",
    "unread_count": 3,
    "updated_at": "2026-05-22 10:30:00"
  }]
}
```

#### GET /api/v1/chat/messages/{peerHashid} — список сообщений
```
Требуется аутентификация: Да
Параметры: ?page=1&per_page=50
Ответ: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
自动标记对端发来的未读消息为已读
```

#### POST /api/v1/chat/send — отправка сообщения
```
Требуется аутентификация: Да
Запрос: { "to_user_id": "hashid", "content": "Hello!" }
Ошибка: 403 非好友不可发
```

#### GET /api/v1/chat/unread-total — общее количество непрочитанных
```
Требуется аутентификация: Да
Ответ: { "count": 5 }
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

#### GET /api/v1/webhook/list — список подписок
```
Требуется аутентификация: Да
Ответ: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/v1/webhook/register — регистрация подписки
```
Требуется аутентификация: Да
Запрос: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
Доступные события: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/v1/webhook/delete — удаление подписки
```
Требуется аутентификация: Да
Запрос: { "id": "hook_id" }
```

### 7.10 API расширенной аналитики

#### GET /admin/v1/analytics/retention — анализ удержания
```
Требуется аутентификация: Да
Ответ: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/v1/analytics/funnel — конверсионная воронка
```
Требуется аутентификация: Да
Ответ: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/v1/analytics/arpu — тренд ARPU/ARPPU
```
Требуется аутентификация: Да
Параметры: ?days=30
Ответ: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/v1/analytics/economy — экономические метрики игр
```
Требуется аутентификация: Да
Ответ: {
  "currencies": [{
    "game_name": "Shooter Master", "currency": "Gold", "symbol": "G",
    "total_minted": "500000.00000000", "total_burned": "320000.00000000",
    "circulation": "180000.00000000", "inflation_rate": "36.00%"
  }]
}
```


### 7.11 API турниров

#### GET /api/v1/tournament/list — список турниров
```
Параметры: ?status=active|upcoming|ended&page=1&per_page=20
Ответ: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/v1/tournament/{hashid} — детали турнира
```
Ответ: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/v1/tournament/{hashid}/join — регистрация на турнир
```
Требуется аутентификация: Да
Ошибка: 422 已报名 / 400 已开始或已满员 / 503 FeatureFlag关闭
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
| POST /api/v1/tournament/{id}/join | 10 раз/мин |

---

## 10. Новые API (v1.3.15-v1.3.22)

### 10.1 Управление рисками (админ :8789)

| Эндпоинт | Описание |
|------|------|
| GET /admin/v1/risk/dashboard | Обзор дашборда рисков |
| GET /admin/v1/risk/overview | Метрики обзора рисков |
| GET /admin/v1/risk/hit-trend | Тренд срабатываний |
| GET /admin/v1/risk/action-distribution | Распределение действий |
| GET /admin/v1/risk/rule-performance | Производительность правил |
| GET /admin/v1/risk/rule/list | Список правил |
| POST /admin/v1/risk/rule/create | Создать правило |
| PUT /admin/v1/risk/rule/{hashid} | Обновить правило |
| POST /admin/v1/risk/rule/{hashid}/toggle | Включить/выключить правило |
| POST /admin/v1/risk/rule/test | Тест правила |
| GET /admin/v1/risk/event/list | Список событий рисков |
| GET /admin/v1/risk/event/{hashid} | Детали события |
| POST /admin/v1/risk/event/{hashid}/handle | Обработать событие |
| GET /admin/v1/risk/device/list | Список отпечатков устройств |
| POST /admin/v1/risk/device/block | Заблокировать устройство |
| POST /admin/v1/risk/device/unblock | Разблокировать устройство |
| GET /admin/v1/risk/ip/list | Список IP |
| POST /admin/v1/risk/ip/block | Заблокировать IP |
| POST /admin/v1/risk/ip/whitelist | Белый список IP |
| POST /admin/v1/risk/ip/appeal | Апелляция IP |
| POST /admin/v1/risk/ip/recheck | Перепроверка IP |
| GET /admin/v1/risk/graph/clusters | Список кластеров |
| GET /admin/v1/risk/graph/{userId} | Граф связей пользователя |
| GET /admin/v1/risk/clusters | Список кластеров риска |
| POST /admin/v1/risk/clusters/detect | Обнаружение кластеров (один IP с ≥5 аккаунтами / один отпечаток устройства с ≥3 аккаунтами за последние 7 дней; только кандидаты, без сохранения) |
| POST /admin/v1/risk/clusters/confirm | Подтвердить кластер вручную и сохранить его |
| GET /admin/v1/risk/clusters/{hashid}/members | Список участников кластера (участники определяются по отпечатку) |
| PUT /admin/v1/risk/clusters/{hashid}/status | Обновить статус кластера (1=наблюдение 2=обработан 0=ложное срабатывание) |
| GET /admin/v1/risk/users | Очередь подозрительных пользователей (фильтр по рейтингу доверия и времени последнего срабатывания) |
| GET /admin/v1/risk/users/{hashid}/timeline | Хронология рисков пользователя (события риска / игры / античита объединены) |
| POST /admin/v1/risk/users/{hashid}/hold | Заморозить доступный баланс пользователя и оставить запись в журнале |

### 10.2 Управление античитом (админ :8789)

| Эндпоинт | Описание |
|------|------|
| GET /admin/v1/anticheat/events | Список событий античита |
| GET /admin/v1/anticheat/events/{hashid} | Детали события |
| POST /admin/v1/anticheat/events/{hashid}/review | Проверка события |

### 10.3 Акции (админ :8789 + клиент :8792)

| Эндпоинт | Описание |
|------|------|
| GET /admin/v1/activities/list | Список акций (админ) |
| POST /admin/v1/activities/create | Создать акцию (админ) |
| PUT /admin/v1/activities/{hashid} | Обновить акцию (админ) |
| DELETE /admin/v1/activities/{hashid} | Удалить акцию (админ) |
| GET /api/v1/activities/list | Список акций (клиент) |
| GET /api/v1/activities/progress | Прогресс участия (клиент) |
| GET /api/v1/activities/{hashid} | Детали акции (клиент) |
| POST /api/v1/activities/{hashid}/checkin | Чек-ин (клиент) |

### 10.4 Группы / Поделиться (клиент :8792 + админ :8789)

| Эндпоинт | Описание |
|------|------|
| POST /api/v1/groups | Создать группу |
| GET /api/v1/groups/{hashid} | Детали группы |
| GET /api/v1/groups/{hashid}/members | Список участников |
| POST /api/v1/groups/{hashid}/join | Вступить в группу |
| POST /api/v1/groups/{hashid}/leave | Покинуть группу |
| PUT /api/v1/groups/{hashid}/role | Роль участника |
| POST /api/v1/shares | Создать ссылку для поделиться |
| POST /api/v1/shares/visit | Отслеживание переходов по ссылке |
| GET /admin/v1/groups | Список групп (админ) |
| GET /admin/v1/groups/{hashid}/audit | Аудит группы (админ) |
| GET /admin/v1/share/stats | Статистика поделиться (админ) |

### 10.5 Расширение платёжных шлюзов (L1)

| Шлюз | Описание |
|------|------|
| Adyen | Новый платёжный шлюз (депозит / проверка колбэка / автоматическое зачисление) |
| GrabPay | Новый платёжный шлюз (депозит / проверка колбэка / автоматическое зачисление) |

### 10.6 VIP / Достижения / Поиск / Квитанции (админ :8789)

Уровни VIP, настройка достижений, глобальный поиск и экспорт квитанций (админ).

| Эндпоинт | Описание |
|------|------|
| GET /admin/v1/vip/level/list | Список уровней VIP |
| POST /admin/v1/vip/level/create | Создать уровень VIP (level должен быть уникальным) |
| PUT /admin/v1/vip/level/{hashid} | Обновить уровень VIP |
| DELETE /admin/v1/vip/level/{hashid} | Удалить уровень VIP (отказ, если на этом уровне есть пользователи) |
| GET /admin/v1/achievement/list | Список достижений |
| POST /admin/v1/achievement/create | Создать достижение (дубликат key отклоняется) |
| PUT /admin/v1/achievement/{hashid} | Обновить достижение |
| DELETE /admin/v1/achievement/{hashid} | Удалить достижение |
| GET /admin/v1/search | Глобальный поиск (?q= запрос, type=game или user) |
| POST /admin/v1/export/receipt | Экспорт квитанции в PDF (type=deposit или withdraw и order_id) |
