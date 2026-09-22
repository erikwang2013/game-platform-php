# Documentación de la API
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · **Español** · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Documentación en línea interactiva (con soporte de depuración en línea):
- Negocio del lado C: http://localhost:8792/apidoc/
- Panel de administración: http://localhost:8789/apidoc/
- Contraseña: consulte `APIDOC_PASSWORD` en el entorno de despliegue

## 1. Convenciones

### 1.1 URL base

| End | Dirección |
|----|------|
| Panel de administración | `http://localhost:8789` |
| Negocio del lado C | `http://localhost:8792` |

### 1.2 Cabeceras de solicitud comunes

```
Content-Type: application/json
Authorization: Bearer <token>    (interfaces que requieren autenticación)
```

### 1.3 Formato de respuesta unificado

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Significado |
|------|------|
| 0 | Éxito |
| 400 | Error de parámetros |
| 401 | Sin autenticar (Token faltante/expirado/no válido) |
| 403 | Sin permisos |
| 404 | Recurso inexistente |
| 422 | Fallo de validación |
| 429 | Demasiadas solicitudes (se activó la limitación) |
| 500 | Error del servidor |

### 1.4 Codificación de IDs

Todos los IDs en las solicitudes y respuestas de las interfaces son cadenas codificadas con Hashids, no valores BIGINT originales.

```
Externo: aB3xK9mW2pQ7rT5v  (cadena hashid)
Interno: 1750123456789      (Snowflake BIGINT)
```

### 1.5 Formato de paginación

```
Solicitud: ?page=1&per_page=20

Respuesta: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. Interfaces del lado C (service :8792)

### 2.1 Autenticación

#### POST /api/v1/auth/register — Registro de usuario

```
Solicitud: {
  "username": "player1",
  "password": "123456",
  "email": "player@example.com"     // 可选
}

Respuesta: {
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

#### POST /api/v1/auth/login — Inicio de sesión de usuario

```
Solicitud: {
  "username": "player1",
  "password": "123456"
}

Respuesta: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "...", ... }
}
```

Error: 401 nombre de usuario o contraseña incorrectos / cuenta deshabilitada

#### POST /api/v1/auth/refresh — Refrescar Token

```
Solicitud: (Authorization: Bearer <refresh_token>)

Respuesta: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 Billetera

#### GET /api/v1/wallet/info — Información de la billetera

```
Requiere autenticación: Sí

Respuesta: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/v1/wallet/transactions — Registro de movimientos

```
Requiere autenticación: Sí
Parámetros: ?page=1&per_page=20&type=deposit    (type 可选)

Respuesta: {
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

### 2.3 Recarga

#### POST /api/v1/deposit/create — Crear orden de recarga

```
Requiere autenticación: Sí

Solicitud: {
  "amount": "10.00",
  "currency": "USD",
  "payment_method_id": "aB3xK..."
}

Respuesta: {
  "order_id": "aB3xK...",
  "order_no": "DEP202605221030000123",
  "amount": "10.00",
  "platform_amount": "10.0000",
  "checkout_url": "https://checkout.stripe.com/...",
  "expires_at": "2026-05-22 11:30:00"
}
```

Valores de currency: USD / CNY / EUR / JPY / KRW / GBP / BRL / INR

checkout_url: enlace de redirección a la pasarela de pago (se rellena al crear el pedido); expires_at: caducidad del enlace de pago (1 hora tras la creación)

#### GET /api/v1/deposit/orders — Registros de recarga

```
Requiere autenticación: Sí
Parámetros: ?page=1&per_page=20

Respuesta: {
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

Valores de status: pending / paid / confirmed / cancelled

### 2.4 Conversión

#### POST /api/v1/exchange/quote — Cotización

```
Requiere autenticación: Sí

Solicitud: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "direction": "in",
  "platform_amount": "10.0000"
}

Respuesta: {
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000",
  "spread_pct": "5.00%"
}
```

direction: in=comprar moneda de juego / out=vender moneda de juego

#### POST /api/v1/exchange/buy — Comprar moneda de juego

```
Requiere autenticación: Sí

Solicitud: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "10.0000"
}

Respuesta: {
  "exchange_id": "aB3xK...",
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000"
}
```

Error: 422 saldo insuficiente de moneda de plataforma / 404 juego no disponible

#### POST /api/v1/exchange/sell — Vender moneda de juego

```
Requiere autenticación: Sí

Solicitud: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "950.0000"
}

Respuesta: {
  "exchange_id": "aB3xK...",
  "platform_amount": "9.0250",
  "game_amount": "950.0000",
  "spread_fee": "0.4750",
  "rate": "100.00000000"
}
```

Error: 422 saldo insuficiente de moneda de juego

#### GET /api/v1/exchange/records — Registros de conversión

```
Requiere autenticación: Sí
Parámetros: ?page=1&per_page=20

Respuesta: {
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

### 2.5 Retiro

#### POST /api/v1/withdraw/apply — Solicitud de retiro

```
Requiere autenticación: Sí

Solicitud: {
  "platform_amount": "50.0000",
  "method": "paypal",
  "account_info": "user@paypal.com"
}

Respuesta: {
  "order_id": "...",
  "order_no": "WTH202605221030000456",
  "status": "approved"
}
```

Valores de method: paypal / bank / crypto

status:
- approved: aprobación automática (importe < auto_approve_threshold)
- pending: pendiente de revisión (importe >= auto_approve_threshold)

Errores:
- 403 la función de retiro está temporalmente deshabilitada (interruptor global apagado)
- 400 por debajo del importe mínimo de retiro
- 400 supera el límite diario de retiro
- 400 saldo insuficiente

#### GET /api/v1/withdraw/orders — Registros de retiro

```
Requiere autenticación: Sí
Parámetros: ?page=1&per_page=20

Respuesta: {
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

### 2.6 Juegos

#### GET /api/v1/game/list — Lista de juegos

```
Parámetros: ?page=1&per_page=20&keyword=射击&type=self

Respuesta: {
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

Valores de type: self / embedded / third_party

#### GET /api/v1/game/detail/{hashid} — Detalle del juego

```
Respuesta: {
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

#### POST /api/v1/game/launch — Iniciar juego

```
Requiere autenticación: Sí

Solicitud: { "game_id": "aB3xK..." }

Respuesta: {
  "id": "...",
  "name": "射击大师",
  "type": "self",
  "api_endpoint": "https://game.example.com/play"
}
```

### 2.7 Inicio de sesión OAuth de terceros

Admite 7 plataformas: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/v1/auth/oauth/{provider} — Obtener URL de autorización

```
Parámetros: provider = google / facebook / apple / twitter / microsoft / linkedin / github

Respuesta: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/v1/auth/oauth/{provider}/callback — Callback de OAuth

```
Solicitud: { "code": "授权码", "state": "防CSRF状态" }

Respuesta: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=usuario recién registrado / false=cuenta existente vinculada

### 2.8 Verificación de identidad KYC

#### GET /api/v1/user/identity/status — Estado de verificación

```
Requiere autenticación: Sí

Respuesta: {
  "status": "approved",          // not_submitted / pending / approved / rejected
  "real_name": "J***",
  "id_type": "id_card",
  "review_note": "",
  "submitted_at": "2026-05-22 10:00:00",
  "reviewed_at": "2026-05-23 14:00:00"
}
```

#### POST /api/v1/user/identity/apply — Enviar verificación

```
Requiere autenticación: Sí

Solicitud: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}

Respuesta: { "message": "KYC submitted successfully" }
```

### 2.9 Pagos

#### POST /api/v1/payment/callback — Callback de pago (público)

```
Solicitud: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

Respuesta: { "message": "success" }
```

status: success / failed

Valores de provider: stripe / paypal / nowpayments / coinbase / skrill / neteller / paysafecard / paytm / mercadopago / astropay / paypay / kakaopay / gcash / mpesa / paystack / toss / adyen / grabpay

| provider | Región | Esquema de firma | Monedas compatibles |
|----------|--------|------------------|---------------------|
| stripe | Global (125+ métodos de pago locales, incl. APM Alipay/WeChat Pay) | Webhook HMAC-SHA256 | USD / CNY / EUR |
| paypal | 200+ mercados mundiales | Verificación de webhook (verify-webhook-signature) | USD / CNY / EUR y otras monedas fiat |
| nowpayments | Global (cripto) | IPN HMAC-SHA512 | USDT TRC20 / ERC20 |
| coinbase | Global (cripto) | Webhook HMAC-SHA256 (secret base64) | USDC / BTC / ETH |
| skrill | Europa / Global | Verificación MD5 de secret word | EUR y otras monedas fiat |
| neteller | Europa / Global | Comparación de campo secret key | EUR y otras monedas fiat |
| paysafecard | Europa (DE / AT / CH, etc.) | X-Signature HMAC-SHA256 | EUR y otras monedas fiat |
| paytm | India | SHA256 + AES-128-CBC | INR |
| mercadopago | Latinoamérica (BR / MX, etc.) | X-Signature (ts,v1) HMAC-SHA256 | BRL / MXN y otras monedas fiat |
| astropay | Latinoamérica (BR, etc.) | MD5(order_id.amount.status.secret) | BRL y otras monedas fiat |
| paypay | Japón | PayPay-Signature HMAC-SHA256 | JPY |
| kakaopay | Corea del Sur | Sin webhook (flujo de dos pasos ready/approve) | KRW |
| gcash | Filipinas | Paymongo-Signature HMAC-SHA256 | PHP |
| toss | Corea del Sur | Server-side verify + amount check | KRW |
| mpesa | Kenia | Trusted IP (CALLBACK_TRUSTED_IPS), no signature | KES |
| paystack | Nigeria | x-paystack-signature HMAC-SHA512 | NGN |
| adyen | Global (moneda según el pedido) | additionalData.hmacSignature HMAC-SHA256 (ADYEN_HMAC_KEY) | según el pedido |
| grabpay | Singapur (país configurable, por defecto SG) | x-signature HMAC-SHA256 (sorted key:value) | según el pedido |

#### GET /api/v1/payment/methods — Métodos de pago disponibles (público)

```
Respuesta: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

Filtrado por país del usuario (X-Language/Accept-Language → código de país): countries vacío o con * significa visible globalmente; ordenado según la preferencia de métodos de pago de country_config de ese país

### 2.10 Registros de juego

#### GET /api/v1/game/play-logs — Lista de registros de juego

```
Requiere autenticación: Sí
Parámetros: ?page=1&per_page=20&game_id=xxx&action=start

Respuesta: {
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

#### GET /api/v1/game/play-log/{hashid} — Detalle del registro de juego

```
Requiere autenticación: Sí
Respuesta: { 完整记录，含 session_id / game_amount_before / after 等 }
```

### 2.12 Clasificaciones

#### GET /api/v1/leaderboard/list — Lista de clasificaciones

```
Respuesta: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/v1/leaderboard/{hashid} — Detalle de clasificación

```
Respuesta: {
  "id": "...",
  "name": "全服累计收入榜",
  "type": "total",
  "rankings": [
    { "rank": 1, "user_id": "...", "score": "50000.0000" }
  ]
}
```

### 2.13 Cupones

#### GET /api/v1/coupon/available — Cupones disponibles para reclamar

```
Requiere autenticación: Sí
Respuesta: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/v1/coupon/claim — Reclamar cupón

```
Requiere autenticación: Sí
Solicitud: { "coupon_id": "hashid" }
Respuesta: { "coupon": { ... } }
```

#### GET /api/v1/coupon/my — Mis cupones

```
Requiere autenticación: Sí
Parámetros: ?status=unused
Respuesta: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 Configuración de países

#### GET /api/v1/country/list — Lista de países

```
Respuesta: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/v1/country/{code} — Detalle de país

```
Respuesta: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 Notificaciones

#### GET /api/v1/notification/list — Lista de notificaciones

```
Requiere autenticación: Sí
Parámetros: ?page=1&per_page=20&is_read=0

Respuesta: {
  "list": [
    { "id": "...", "type": "deposit", "title": "Deposit Received", "is_read": 0, "created_at": "..." }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### GET /api/v1/notification/unread-count — Cantidad de no leídas

```
Requiere autenticación: Sí
Respuesta: { "count": 3 }
```

#### POST /api/v1/notification/read — Marcar como leídas

```
Requiere autenticación: Sí
Solicitud: { "id": "hashid" }  // 不传=全部已读
```

### 2.17 Recomendación

#### GET /api/v1/referral/my-code — Mi código de recomendación

```
Requiere autenticación: Sí
Respuesta: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/v1/referral/apply — Usar código de recomendación

```
Requiere autenticación: Sí
Solicitud: { "code": "ABC12345" }
Respuesta: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/v1/user/2fa/status — Estado de 2FA

```
Requiere autenticación: Sí
Respuesta: { "enabled": false }
```

#### POST /api/v1/user/2fa/setup — Configurar 2FA

```
Requiere autenticación: Sí
Respuesta: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/v1/user/2fa/enable — Habilitar 2FA

```
Requiere autenticación: Sí
Solicitud: { "code": "123456" }
Respuesta: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/v1/2fa/verify — Verificar 2FA (público)

```
Solicitud: { "user_id": "hashid", "code": "123456" }
Respuesta: { "valid": true }
```

### 2.19 Búsqueda

#### GET /api/v1/search — Búsqueda global

```
Parámetros: ?q=keyword&type=game&page=1&per_page=20
Respuesta: { "list": [...], "total": 100 }
```

#### GET /api/v1/game/suggest — Sugerencias de búsqueda

```
Parámetros: ?q=shoot
Respuesta: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 Idiomas

#### GET /api/v1/language/list — Lista de idiomas disponibles

```
Respuesta: {
  "current": "en-US",
  "languages": {
    "en-US": { "name": "English", "nativeName": "English", "icon": "us" },
    "zh-CN": { "name": "Chinese (Simplified)", "nativeName": "简体中文", "icon": "cn" },
    "ja-JP": { "name": "Japanese", "nativeName": "日本語", "icon": "jp" },
    "ko-KR": { "name": "Korean", "nativeName": "한국어", "icon": "kr" }
  }
}
```

#### POST /api/v1/language/switch — Cambiar idioma

```
Solicitud: { "locale": "zh-CN" }
Respuesta: { "locale": "zh-CN" }
```

Valores de locale: en-US / zh-CN / ja-JP / ko-KR

### 2.8 Usuario

#### GET /api/v1/user/profile — Información personal

```
Requiere autenticación: Sí

Respuesta: {
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

#### PUT /api/v1/user/profile — Editar perfil

```
Requiere autenticación: Sí

Solicitud: {
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}

Respuesta: {
  "id": "...",
  "username": "player1",
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}
```

Valores de language: en-US / zh-CN / ja-JP / ko-KR

### 2.9 Anuncios

#### GET /api/v1/announcement/list — Lista de anuncios

```
Respuesta: {
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

#### GET /api/v1/announcement/detail/{hashid} — Detalle de anuncio

```
Respuesta: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

### 2.21 Estadísticas de la plataforma

| Método | Ruta | Descripción | Autenticación |
|------|------|------|------|
| GET | /api/v1/platform/stats | Estadísticas públicas de la plataforma (total juegos / total usuarios / partidas de hoy / activos en 7 días) | No |

#### GET /api/v1/platform/stats — Estadísticas de la plataforma

```
无需认证

Respuesta: {
  "total_games": 12,
  "total_users": 1500,
  "today_game_plays": 320,
  "active_users_7d": 450
}
```

## 3. Interfaces del panel de administración (admin :8789)

### 3.1 Dashboard de la plataforma

#### GET /admin/v1/dashboard/platform

```
Requiere autenticación: Sí (AdminAuth + AdminPermission)

Respuesta: {
  "total_users": 1500,
  "active_users_7d": 320,
  "total_games": 12,
  "pending_withdraws": 5,
  "today_deposits": "500.0000",
  "today_withdraws": "120.0000",
  "total_spread_fee": "1500.5000"
}
```

### 3.2 Gestión de juegos

#### GET /admin/v1/game/list — Lista de juegos

```
Requiere autenticación: Sí
Parámetros: ?page=1&limit=20&keyword=射击

Respuesta: {
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

#### GET /admin/v1/game/{hashid} — Detalle del juego

```
Requiere autenticación: Sí
Parámetros: hashid 为游戏的 hashid 编码（路径参数）

Respuesta: {
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

Devuelve code 404 si el juego no existe.

#### POST /admin/v1/game/launch — Vista previa del juego

```
Requiere autenticación: Sí

Solicitud: {
  "game_id": "aB3xK..."      // 游戏 ID(hashid)
}

Respuesta: {
  "id": "aB3xK...",
  "name": "射击大师",
  "slug": "shooter-master",
  "type": "self",
  "api_endpoint": "https://...",
  "preview": true
}
```

Si falta `game_id`, devuelve code 422; si el juego no existe, devuelve 404; si el juego no está publicado (`status` no es 1), devuelve 403.

La vista previa del panel es una vista previa pura: solo valida la disponibilidad del juego y devuelve la información de inicio, y **no escribe registros de juego ni toca la cartera**. Las identidades de administración solo portan `adminId` (inyectado por `AdminAuth`) y ningún `userId` del lado C, por lo que este endpoint no realiza deliberadamente ninguna escritura del lado del usuario — copiar el `POST /api/v1/game/launch` del lado C escribiría filas de `game_game_play_log` con un propietario incorrecto.

#### POST /admin/v1/game/create — Crear juego

```
Requiere autenticación: Sí

Solicitud: {
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

Respuesta: { "id": "aB3xK..." }
```

Valores de type: self / embedded / third_party

#### PUT /admin/v1/game/{hashid} — Editar juego

```
Requiere autenticación: Sí

Solicitud: {
  "name": "新名称",
  "status": 1
  // 可部分更新，字段同 create
}

Respuesta: { "message": "更新成功" }
```

#### DELETE /admin/v1/game/{hashid} — Eliminar juego

```
Requiere autenticación: Sí
Respuesta: { "message": "删除成功" }
```

#### POST /admin/v1/game/currency/manage — Gestionar monedas

```
Requiere autenticación: Sí

Solicitud: {
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

Respuesta: { "message": "操作成功" }
```

Si falta `game_id` o `currencies` no es un array, devuelve 422; si el juego no existe, devuelve 404.

Solo cuando se envían se validan `exchange_rate` y `spread_pct`: `exchange_rate` debe ser un número mayor que 0 y `spread_pct` debe estar en el rango [0, 100); infringir cualquiera de las dos reglas devuelve 422 y no se escribe ninguna moneda del lote (primero se valida el lote completo, luego se escribe). Los campos omitidos no activan la validación: al crear se usan los valores predeterminados (`exchange_rate` = `1.00000000`, los demás `0.00000000`) y al actualizar se conserva el valor existente.

### 3.3 Gestión de retiros

#### GET /admin/v1/withdraw/orders — Lista de órdenes de retiro

```
Requiere autenticación: Sí
Parámetros: ?page=1&limit=20&status=pending

Respuesta: {
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

#### PUT /admin/v1/withdraw/review — Revisar retiro

```
Requiere autenticación: Sí

Solicitud: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

Respuesta: { "message": "已通过" }
```

action: approve=aprobar / reject=rechazar / confirm=confirmar (al rechazar se devuelve automáticamente la moneda de plataforma)

Error: 422 el estado de la orden no es pendiente de revisión

#### PUT /admin/v1/withdraw/switch — Interruptor global de retiros

```
Requiere autenticación: Sí

Solicitud: { "enabled": 1 }

Respuesta: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/v1/withdraw/limits/set — Configurar límites de retiro

```
Requiere autenticación: Sí

Solicitud: {
  "daily_limit": "10000.0000",             // 可选
  "min_amount": "1.0000",                  // 可选
  "auto_approve_threshold": "100.0000"     // 可选
}

Respuesta: {
  "daily_limit": "10000.0000",
  "min_amount": "1.0000",
  "auto_approve_threshold": "100.0000",
  "global_switch": true
}
```

#### POST /admin/v1/withdraw/batch-review — Revisión masiva de retiros

```
Requiere autenticación: Sí

Solicitud: {
  "ids": ["aB3xK...", "cD4yL..."],
  "action": "approve",
  "note": "批量审核通过"
}

Respuesta: {
  "processed": 2,
  "failed": []
}
```

action: approve=aprobar / reject=rechazar (procesado pedido a pedido; los rechazados se reembolsan automáticamente; los fallos van a failed y no afectan al resto)

#### POST /admin/v1/withdraw/execute-payout — Ejecutar pago

```
Requiere autenticación: Sí

Solicitud: { "order_id": "aB3xK..." }

Respuesta: {
  "payout_batch_id": "PAYOUT-123456",
  "payout_item_id": "ITEM-123456",
  "payout_status": "success",
  "payout_attempts": 1
}
```

Solo se puede pagar un pedido en estado approved (cambio atómico a processing); una llamada repetida devuelve 422. Con la doble revisión activada, el pedido debe confirmarse antes por una segunda persona

#### POST /admin/v1/withdraw/sync-payout — Sincronizar estado del pago

```
Requiere autenticación: Sí

Solicitud: { "order_id": "aB3xK..." }

Respuesta: {
  "payout_status": "success",
  "order_status": "completed",
  "synced_status": "success"
}
```

Error: 422 Este pedido aún no tiene un pago ejecutado

### 3.4 Gestión de usuarios de la plataforma

#### GET /admin/v1/platform/user/list — Lista de usuarios del lado C

```
Requiere autenticación: Sí
Parámetros: ?page=1&limit=20&keyword=player&status=1

Respuesta: {
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

#### GET /admin/v1/platform/user/{hashid} — Detalle de usuario

```
Requiere autenticación: Sí

Respuesta: {
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

#### PUT /admin/v1/platform/user/{hashid} — Editar/banear usuario

```
Requiere autenticación: Sí

Solicitud: {
  "status": 0,         // 0=禁用 1=启用
  "nickname": "..."    // 可选
}

Respuesta: { "message": "更新成功" }
```

### 3.5 Gestión de pagos

#### GET /admin/v1/payment/method/list

```
Requiere autenticación: Sí

Respuesta: {
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

#### POST /admin/v1/payment/method/toggle — Habilitar/deshabilitar método de pago

```
Requiere autenticación: Sí

Solicitud: { "id": "aB3xK...", "status": 0 }

Respuesta: { "message": "已更新" }
```

### 3.6 Gestión de anuncios

#### GET /admin/v1/announcement/list

```
Requiere autenticación: Sí
Parámetros: ?page=1&limit=20

Respuesta: {
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

#### POST /admin/v1/announcement/create — Publicar anuncio

```
Requiere autenticación: Sí

Solicitud: {
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护。",
  "type": "system",           // 可选, 默认"system"
  "target_lang": "",          // 可选, 空=全语言
  "status": 1,                // 可选, 默认1 (0=草稿 1=发布)
  "start_at": "2026-05-23 02:00:00",  // 可选
  "end_at": "2026-05-23 04:00:00"     // 可选
}

Respuesta: { "id": "aB3xK..." }
```

### 3.7 Revisión KYC

#### GET /admin/v1/identity/list — Lista KYC

```
Requiere autenticación: Sí
Parámetros: ?page=1&limit=20&status=pending

Respuesta: {
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

#### PUT /admin/v1/identity/review — Revisar KYC

```
Requiere autenticación: Sí

Solicitud: { "id": "hashid", "action": "approve", "note": "" }

Respuesta: { "message": "Approved" }
```

action: approve / reject

### 3.8 Gestión de servidores del juego

#### GET /admin/v1/game/server/list — Lista de servidores

```
Requiere autenticación: Sí
Parámetros: ?game_id=hashid

Respuesta: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/v1/game/server/create — Crear servidor

```
Requiere autenticación: Sí
Solicitud: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
Respuesta: { "id": "hashid" }
```

#### PUT /admin/v1/game/server/{hashid} — Editar servidor

```
Requiere autenticación: Sí
Solicitud: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/v1/game/server/{hashid} — Eliminar servidor

```
Requiere autenticación: Sí
```

### 3.9 Gestión de límites escalonados de retiro

#### GET /admin/v1/withdraw/limits/list

```
Requiere autenticación: Sí

Respuesta: {
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

#### PUT /admin/v1/withdraw/limits/{hashid} — Actualizar límite

```
Requiere autenticación: Sí

Solicitud: { "single_max": "10000.0000", "fee_pct": "0.25" }
// 可部分更新
```

### 3.11 Gestión de categorías de juegos

#### GET /admin/v1/game/category/list

```
Requiere autenticación: Sí
Respuesta: { "list": [{ "id": "...", "name": "动作", "slug": "action", "sort": 1 }] }
```

#### POST /admin/v1/game/category/create

```
Requiere autenticación: Sí
Solicitud: { "name": "新分类", "slug": "new-cat", "icon": "star", "sort": 10 }
Respuesta: { "id": "hashid" }
```

#### PUT /admin/v1/game/category/{hashid} — Editar categoría

#### DELETE /admin/v1/game/category/{hashid} — Eliminar categoría

#### POST /admin/v1/game/category/assign — Asignar juegos

```
Requiere autenticación: Sí
Solicitud: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 Gestión de clasificaciones

#### GET /admin/v1/leaderboard/list — Lista de clasificaciones

```
Requiere autenticación: Sí
Respuesta: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/v1/leaderboard/create — Crear clasificación

```
Requiere autenticación: Sí
Solicitud: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(可选)" }
```

#### PUT /admin/v1/leaderboard/{hashid} — Editar clasificación

#### DELETE /admin/v1/leaderboard/{hashid} — Eliminar clasificación

#### POST /admin/v1/leaderboard/{hashid}/refresh — Refrescar caché

### 3.13 Gestión de cupones

#### GET /admin/v1/coupon/list — Lista de cupones

#### POST /admin/v1/coupon/create — Crear cupón

```
Requiere autenticación: Sí
Solicitud: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/v1/coupon/{hashid} — Editar (cuando no está reclamado)

#### DELETE /admin/v1/coupon/{hashid} — Eliminar

#### GET /admin/v1/coupon/{hashid}/stats — Estadísticas de reclamo

```
Respuesta: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 Gestión de configuración de países

#### GET /admin/v1/country/config/list — Lista de configuración de países

#### POST /admin/v1/country/config/create — Crear configuración de país

```
Requiere autenticación: Sí
Solicitud: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/v1/country/config/{hashid} — Editar configuración de país

### 3.15 Exportación de datos

#### POST /admin/v1/export/users — Exportar usuarios del lado C

```
Requiere autenticación: Sí
Parámetros(JSON): { "status": 1 }   // 可选筛选

Respuesta: Excel 文件下载 (xlsx)
```

#### POST /admin/v1/export/transactions — Exportar movimientos de la plataforma

```
Requiere autenticación: Sí
Parámetros(JSON): { "type": "deposit" }   // 可选筛选

Respuesta: Excel 文件下载 (xlsx)
```

### 3.16 Análisis de datos (agregación en tiempo real de MySQL)

Todos los endpoints requieren autenticación (AdminAuth + AdminPermission). Los datos se agregan en tiempo real desde MySQL, sin depender de ClickHouse.

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/v1/analytics/overview | Resumen de la plataforma (hoy/últimos 7 días) |
| GET | /admin/v1/analytics/game-ranking | Ranking de juegos (?days=7) |
| GET | /admin/v1/analytics/dau-trend | Tendencia DAU (?days=30) |
| GET | /admin/v1/analytics/hourly-trend | Tendencia por hora |
| GET | /admin/v1/analytics/action-distribution | Distribución de acciones |
| GET | /admin/v1/analytics/revenue | Análisis de ingresos |
| GET | /admin/v1/analytics/conversion | Tasa de conversión de juegos |
| GET | /admin/v1/analytics/probability | Probabilidad conjunta/condicional |
| GET | /admin/v1/analytics/retention | Análisis de retención D1/D3/D7/D30 |
| GET | /admin/v1/analytics/funnel | Embudo de conversión |
| GET | /admin/v1/analytics/arpu | Tendencia ARPU/ARPPU |
| GET | /admin/v1/analytics/economy | Indicadores económicos de monedas de juego |

### 3.17 Gestión de tickets

Todos los endpoints requieren autenticación (AdminAuth + AdminPermission).

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/v1/ticket/list | Lista de tickets (?page=&limit=&status=&type=) |
| GET | /admin/v1/ticket/{hashid} | Detalle de ticket (incluye respuestas) |
| POST | /admin/v1/ticket/{hashid}/reply | Responder ticket |
| POST | /admin/v1/ticket/{hashid}/close | Cerrar ticket |
| POST | /admin/v1/ticket/{hashid}/assign | Asignar responsable (admin_id) |

### 3.18 Gestión de configuración de CDN

Todos los endpoints requieren autenticación (AdminAuth + AdminPermission).

| Método | Ruta | Descripción | Autenticación |
|------|------|------|------|
| GET | /admin/v1/cdn/provider/list | Lista de proveedores CDN (las credenciales no se devuelven) | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/toggle | Activar/desactivar proveedor {id, status} | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/create | Crear {name, provider, config(JSON), status, sort}, verificación de unicidad de provider | AdminAuth + RBAC: cdn |
| PUT | /admin/v1/cdn/provider/{hashid} | Editar (config vacío = sin cambios) | AdminAuth + RBAC: cdn |
| DELETE | /admin/v1/cdn/provider/{hashid} | Eliminar | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/test | Prueba de conectividad HeadBucket {id} | AdminAuth + RBAC: cdn |

### 3.19 Informes de datos

Todos los endpoints requieren autenticación (AdminAuth + AdminPermission).

| Método | Ruta | Descripción | Autenticación |
|------|------|------|------|
| GET | /admin/v1/report/summary | Resumen de informes (nuevos usuarios/depósitos/retiros/cambios/partidas) | AdminAuth + RBAC: report |
| GET | /admin/v1/report/daily | Informe diario (agregación por día, días sin datos rellenados con 0) | AdminAuth + RBAC: report |
| GET | /admin/v1/report/export | Exportación del informe diario a CSV (UTF-8 BOM) | AdminAuth + RBAC: report |

## 4. Política de limitación de velocidad

| Interfaz | Límite |
|------|------|
| Predeterminado | 60 veces/minuto/IP |
| POST /api/v1/auth/login | 10 veces/minuto |
| POST /api/v1/auth/register | 5 veces/minuto |

Al superar el límite devuelve 429; las cabeceras de respuesta incluyen:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1716400830
Retry-After: 60
```

## 5. Descripción de la autenticación

### Lado C (UserAuth)

1. Extraer el Token de `Authorization: Bearer <token>`
2. Verificar la firma JWT (HS256), analizar `sub` (ID de usuario)
3. Consultar la tabla `game_user` para verificar que el usuario existe y status=1
4. Inyectar `$request->userId`

### Panel de administración (AdminAuth + AdminPermission)

1. AdminAuth: verificación de firma JWT, analizar `sub` (ID de administrador), inyectar `$request->adminId`
2. AdminPermission: buscar permisos según el rol del usuario, hacer coincidir el identificador de permiso en formato `method.path`
3. Los superadministradores con `slug=*` omiten la verificación de permisos

## 6. Referencia rápida de códigos de error

| code | Significado | Escenarios comunes |
|------|------|---------|
| 0 | Éxito | - |
| 400 | Error de parámetros | Formato de solicitud incorrecto, saldo insuficiente |
| 401 | Sin autenticar | Token faltante/expirado/no válido, cuenta deshabilitada |
| 403 | Sin permisos | El usuario no tiene el permiso del rol correspondiente, juego no disponible |
| 404 | Inexistente | Recurso no encontrado |
| 422 | Fallo de validación | Los parámetros del formulario no cumplen las reglas, el estado de la orden no permite la operación |
| 429 | Limitación | Demasiadas solicitudes |
| 500 | Error del servidor | Excepción inesperada |


## 7. Nuevas API (extensión de ecosistema v2.0)

### 7.1 Provider API — Interfaz de callback del proveedor de juegos

**Método de autenticación**: firma HMAC-SHA256 (X-Game-Id + X-Timestamp + X-Signature)
**Ventana de tiempo**: 5 minutos

#### POST /api/provider/balance — Consultar saldo del usuario

```
Cabeceras de solicitud:
  X-Game-Id: 1234567890
  X-Timestamp: 1716400830
  X-Signature: abc123...

Solicitud: {
  "user_id": 1234567890,
  "game_id": 9876543210,
  "currency_id": 5555555555
}

Respuesta: {
  "code": 0,
  "message": "success",
  "data": { "balance": "1000.50000000" }
}
```

#### POST /api/provider/bet — Notificar apuesta

```
Solicitud: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "bet_type": "straight" }
}

Respuesta: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "990.50000000"
  }
}
```

#### POST /api/provider/settle — Notificar liquidación

```
Solicitud: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}

Respuesta: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1040.50000000",
    "win_amount": "50.00000000"
  }
}
```

#### POST /api/provider/refund — Notificar reembolso

```
Solicitud: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "reason": "game_crash"
}

Respuesta: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1000.50000000"
  }
}
```

### 7.2 API de tickets

#### GET /api/v1/ticket/list — Lista de tickets

```
Requiere autenticación: Sí
Parámetros: ?page=1&per_page=20

Respuesta: {
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

#### POST /api/v1/ticket/create — Crear ticket

```
Requiere autenticación: Sí
Solicitud: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
Respuesta: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/v1/ticket/{hashid} — Detalle de ticket

```
Requiere autenticación: Sí
Respuesta: {
  "id": "...", "type": "deposit", "subject": "...",
  "content": "...", "status": "open",
  "replies": [
    { "id": "...", "content": "...", "is_admin": 1, "created_at": "..." }
  ]
}
```

#### POST /api/v1/ticket/{hashid}/reply — Responder ticket

```
Requiere autenticación: Sí
Solicitud: { "content": "已核实，将在24小时内处理" }
Respuesta: { "code": 0, "message": "Reply sent" }
```

### 7.3 API de verificación de email

#### POST /api/v1/verify/send-email — Enviar código de verificación de email

```
Requiere autenticación: Sí
Solicitud: { "email": "user@example.com" }
Respuesta: { "code": 0, "message": "Verification code sent" }
Error: 429 请60秒后重试
```

#### POST /api/v1/verify/confirm-email — Confirmar email

```
Requiere autenticación: Sí
Solicitud: { "code": "123456" }
Respuesta: { "code": 0, "message": "Email verified" }
Error: 422 验证码无效或已过期
```

### 7.4 API VIP

#### GET /api/v1/user/vip-status — Estado VIP

> **Sin implementar**: la ruta del lado C no está registrada (sin entrada en `service/config/route.php`), las peticiones devuelven 404 actualmente. Elimina esta línea cuando se implemente.

```
Requiere autenticación: Sí
Respuesta: {
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

### 7.5 API de logros

#### GET /api/v1/user/achievements — Lista de logros

> **Sin implementar**: la ruta del lado C no está registrada (sin entrada en `service/config/route.php`), las peticiones devuelven 404 actualmente. Elimina esta línea cuando se implemente.

```
Requiere autenticación: Sí
Respuesta: {
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

### 7.6 Nuevas API del panel de administración

#### GET /admin/v1/ticket/list — Lista de tickets

```
Requiere autenticación: Sí
Parámetros: ?page=1&limit=20&status=pending&type=deposit

Respuesta: {
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

#### POST /admin/v1/ticket/{hashid}/reply — Responder ticket

```
Requiere autenticación: Sí
Solicitud: { "content": "已处理" }
Respuesta: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/v1/ticket/{hashid}/close — Cerrar ticket

```
Requiere autenticación: Sí
Respuesta: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/v1/ticket/{hashid}/assign — Asignar responsable

```
Requiere autenticación: Sí
Solicitud: { "admin_id": 1234567890 }
Respuesta: { "code": 0, "message": "Assigned" }
```

#### GET /admin/v1/analytics/retention — Análisis de retención

```
Requiere autenticación: Sí
Parámetros: ?days=30
Respuesta: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/v1/analytics/funnel — Embudo de conversión

```
Requiere autenticación: Sí
Respuesta: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/v1/analytics/arpu — Tendencia ARPU/ARPPU

```
Requiere autenticación: Sí
Parámetros: ?days=30
Respuesta: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/v1/analytics/economy — Indicadores económicos de monedas de juego

```
Requiere autenticación: Sí
Respuesta: {
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


#### GET /admin/v1/cdn/provider/list — Lista de proveedores CDN (las credenciales no se devuelven)

```
Requiere autenticación: Sí
Respuesta: { "list": [ { "id": "...", "name": "...", "provider": "cloudflare", "status": 1, "sort": 0 } ] }
```

#### POST /admin/v1/cdn/provider/toggle — Activar/desactivar proveedor {id, status}

```
Requiere autenticación: Sí
Solicitud: { "id": "...", "status": 1 }
Respuesta: { "code": 0, "message": "..." }
```

#### POST /admin/v1/cdn/provider/create — Crear {name, provider, config(JSON), status, sort}, verificación de unicidad de provider

```
Requiere autenticación: Sí
Solicitud: { "name": "...", "provider": "aliyun", "config": "{...}", "status": 1, "sort": 0 }
Respuesta: { "code": 0, "data": { "id": "..." } }
```

#### PUT /admin/v1/cdn/provider/{hashid} — Editar (config vacío = sin cambios)

```
Requiere autenticación: Sí
Solicitud: { "name": "...", "config": "" }
Respuesta: { "code": 0, "message": "..." }
```

#### DELETE /admin/v1/cdn/provider/{hashid} — Eliminar

```
Requiere autenticación: Sí
Respuesta: { "code": 0, "message": "..." }
```

#### POST /admin/v1/cdn/provider/test — Prueba de conectividad HeadBucket {id}

```
Requiere autenticación: Sí
Solicitud: { "id": "..." }
Respuesta: { "code": 0, "data": { "ok": true } }
```
#### GET /admin/v1/report/summary — Resumen de informes

```
Requiere autenticación: Sí
Parámetros: ?start=Y-m-d&end=Y-m-d (缺省最近30天，跨度 ≤90 天，Redis 缓存5分钟)
Respuesta: {
  "start": "2026-08-01", "end": "2026-08-31",
  "new_users": 120, "deposit_amount": "5000.0000", "deposit_count": 45,
  "withdraw_amount": "1200.0000", "withdraw_count": 8,
  "exchange_amount": "3000.0000", "play_count": 1500
}
```


#### GET /admin/v1/report/daily — Informe diario

```
Requiere autenticación: Sí
Parámetros: ?start=Y-m-d&end=Y-m-d
Respuesta: {
  "start": "2026-08-01", "end": "2026-08-31",
  "rows": [ { "date": "2026-08-01", "new_users": 12, "deposit_amount": "500.0000", "deposit_count": 4, "withdraw_amount": "100.0000", "withdraw_count": 1, "exchange_amount": "300.0000", "play_count": 150 } ]
}
```


#### GET /admin/v1/report/export — Exportación del informe diario CSV

```
Requiere autenticación: Sí
Parámetros: ?start=Y-m-d&end=Y-m-d&format=excel
Respuesta: CSV 文件（UTF-8 BOM），文件名 report_{start}_{end}.csv，Excel 可直接打开
```

## 8. Política de limitación de velocidad (actualizada)

| Interfaz | Límite |
|------|------|
| Predeterminado | 60 veces/minuto/IP |
| POST /api/v1/auth/login | 10 veces/minuto |
| POST /api/v1/auth/register | 5 veces/minuto |
| POST /api/v1/auth/oauth | 10 veces/minuto |
| POST /api/v1/payment/callback | 30 veces/minuto |
| POST /api/provider/* | Sin límite (autenticación por firma HMAC) |

## 9. Descripción de la autenticación (actualizada)

### Autenticación de Provider (ProviderAuth)

1. Extraer `X-Game-Id`, `X-Timestamp`, `X-Signature` de las cabeceras de la solicitud
2. Consultar la tabla `game_game` para verificar que el juego existe y status=1
3. Verificar que la marca de tiempo esté dentro de la ventana de 5 minutos (anti-replay)
4. Calcular `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` y comparar con la firma
5. Inyectar `$request->gameId` y `$request->game`


### 7.7 API de amigos

#### GET /api/v1/friend/list — Lista de amigos
```
Requiere autenticación: Sí
Respuesta: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/v1/friend/requests — Solicitudes pendientes
```
Requiere autenticación: Sí
Respuesta: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/v1/friend/request — Enviar solicitud de amistad
```
Requiere autenticación: Sí
Solicitud: { "friend_id": "hashid" }
```

#### POST /api/v1/friend/accept — Aceptar solicitud
```
Requiere autenticación: Sí
Solicitud: { "request_id": "hashid" }
```

#### POST /api/v1/friend/reject — Rechazar solicitud
```
Requiere autenticación: Sí
Solicitud: { "request_id": "hashid" }
```

#### POST /api/v1/friend/remove — Eliminar amigo
```
Requiere autenticación: Sí
Solicitud: { "friend_id": "hashid" }
```

#### GET /api/v1/friend/search — Buscar usuarios
```
Requiere autenticación: Sí
Parámetros: ?q=username
Respuesta: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 API de chat

#### GET /api/v1/chat/conversations — Lista de conversaciones
```
Requiere autenticación: Sí
Respuesta: {
  "list": [{
    "peer": { "id": "...", "username": "...", "nickname": "...", "avatar": "..." },
    "last_message": "最近一条消息",
    "unread_count": 3,
    "updated_at": "2026-05-22 10:30:00"
  }]
}
```

#### GET /api/v1/chat/messages/{peerHashid} — Lista de mensajes
```
Requiere autenticación: Sí
Parámetros: ?page=1&per_page=50
Respuesta: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
自动标记对端发来的未读消息为已读
```

#### POST /api/v1/chat/send — Enviar mensaje
```
Requiere autenticación: Sí
Solicitud: { "to_user_id": "hashid", "content": "Hello!" }
Error: 403 非好友不可发
```

#### GET /api/v1/chat/unread-total — Total de no leídos
```
Requiere autenticación: Sí
Respuesta: { "count": 5 }
```

**Conexión WebSocket**: `ws://host:8791`
```
// 认证
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// 接收消息
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 Webhook API

#### GET /api/v1/webhook/list — Lista de suscripciones
```
Requiere autenticación: Sí
Respuesta: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/v1/webhook/register — Registrar suscripción
```
Requiere autenticación: Sí
Solicitud: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
Eventos disponibles: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/v1/webhook/delete — Eliminar suscripción
```
Requiere autenticación: Sí
Solicitud: { "id": "hook_id" }
```

### 7.10 API de análisis avanzado

#### GET /admin/v1/analytics/retention — Análisis de retención
```
Requiere autenticación: Sí
Respuesta: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/v1/analytics/funnel — Embudo de conversión
```
Requiere autenticación: Sí
Respuesta: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/v1/analytics/arpu — Tendencia ARPU/ARPPU
```
Requiere autenticación: Sí
Parámetros: ?days=30
Respuesta: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/v1/analytics/economy — Indicadores de economía del juego
```
Requiere autenticación: Sí
Respuesta: {
  "currencies": [{
    "game_name": "Shooter Master", "currency": "Gold", "symbol": "G",
    "total_minted": "500000.00000000", "total_burned": "320000.00000000",
    "circulation": "180000.00000000", "inflation_rate": "36.00%"
  }]
}
```


### 7.11 API de torneos

#### GET /api/v1/tournament/list — Lista de torneos
```
Parámetros: ?status=active|upcoming|ended&page=1&per_page=20
Respuesta: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/v1/tournament/{hashid} — Detalle de torneo
```
Respuesta: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/v1/tournament/{hashid}/join — Inscribirse en el torneo
```
Requiere autenticación: Sí
Error: 422 已报名 / 400 已开始或已满员 / 503 FeatureFlag关闭
```

### 7.12 Condiciones de cupones (nuevo)

El JSON `conditions` de los cupones admite:
- `min_deposit`: cadena, importe mínimo de recarga acumulada
- `first_user_only`: bool, solo para usuarios nuevos que nunca han recargado
- `game_id`: int, requiere haber jugado al juego especificado

Las condiciones se validan dos veces: en el filtro de la lista de `available()` y al reclamar con `claim()`.

### 7.13 Recomendación multinivel (nuevo)

La comisión por recomendación añade una segunda línea de reparto:
- L1: el recomendador directo obtiene `referrer_bonus` (config: referral.referrer_bonus)
- L2: el recomendador del recomendador obtiene `commission = referrer_bonus * level2_rate` (config: referral.level2_rate, predeterminado 5%)
- Se registra en `game_referral_commission` (level/commission_rate/commission_amount)

### 8. Política de limitación de velocidad (actualizada)

| Interfaz | Límite |
|------|------|
| POST /api/v1/tournament/{id}/join | 10 veces/minuto |

---

## 10. Nuevas API (v1.3.15-v1.3.22)

### 10.1 Gestión de riesgos (admin :8789)

| Endpoint | Descripción |
|------|------|
| GET /admin/v1/risk/dashboard | Resumen del panel de riesgos |
| GET /admin/v1/risk/overview | Métricas generales de riesgos |
| GET /admin/v1/risk/hit-trend | Tendencia de aciertos |
| GET /admin/v1/risk/action-distribution | Distribución de acciones |
| GET /admin/v1/risk/rule-performance | Rendimiento de reglas |
| GET /admin/v1/risk/rule/list | Lista de reglas |
| POST /admin/v1/risk/rule/create | Crear regla |
| PUT /admin/v1/risk/rule/{hashid} | Actualizar regla |
| POST /admin/v1/risk/rule/{hashid}/toggle | Activar/desactivar regla |
| POST /admin/v1/risk/rule/test | Probar regla |
| GET /admin/v1/risk/event/list | Lista de eventos de riesgo |
| GET /admin/v1/risk/event/{hashid} | Detalle del evento |
| POST /admin/v1/risk/event/{hashid}/handle | Gestionar evento |
| GET /admin/v1/risk/device/list | Lista de huellas de dispositivos |
| POST /admin/v1/risk/device/block | Bloquear dispositivo |
| POST /admin/v1/risk/device/unblock | Desbloquear dispositivo |
| GET /admin/v1/risk/ip/list | Lista de IP |
| POST /admin/v1/risk/ip/block | Bloquear IP |
| POST /admin/v1/risk/ip/whitelist | Lista blanca de IP |
| POST /admin/v1/risk/ip/appeal | Apelación de IP |
| POST /admin/v1/risk/ip/recheck | Revisión de IP |
| GET /admin/v1/risk/graph/clusters | Lista de clústeres |
| GET /admin/v1/risk/graph/{userId} | Grafo de vínculos del usuario |
| GET /admin/v1/risk/clusters | Lista de clústeres de riesgo |
| POST /admin/v1/risk/clusters/detect | Detección de clústeres (misma IP con ≥5 cuentas / misma huella de dispositivo con ≥3 cuentas en los últimos 7 días; solo candidatos, sin persistencia) |
| POST /admin/v1/risk/clusters/confirm | Confirmar manualmente un clúster y guardarlo |
| GET /admin/v1/risk/clusters/{hashid}/members | Lista de miembros del clúster (miembros resueltos desde la huella) |
| PUT /admin/v1/risk/clusters/{hashid}/status | Actualizar el estado del clúster (1=en observación 2=tratado 0=falso positivo) |
| GET /admin/v1/risk/users | Cola de usuarios anómalos (filtrada por puntuación de confianza y última detección) |
| GET /admin/v1/risk/users/{hashid}/timeline | Cronología de riesgo del usuario (eventos de riesgo / partidas / anti-fraude combinados) |
| POST /admin/v1/risk/users/{hashid}/hold | Congelar el saldo disponible del usuario y dejar registro |

### 10.2 Gestión anti-trampas (admin :8789)

| Endpoint | Descripción |
|------|------|
| GET /admin/v1/anticheat/events | Lista de eventos anti-trampas |
| GET /admin/v1/anticheat/events/{hashid} | Detalle del evento |
| POST /admin/v1/anticheat/events/{hashid}/review | Revisar evento |

### 10.3 Actividades (admin :8789 + cliente :8792)

| Endpoint | Descripción |
|------|------|
| GET /admin/v1/activities/list | Lista de actividades (admin) |
| POST /admin/v1/activities/create | Crear actividad (admin) |
| PUT /admin/v1/activities/{hashid} | Actualizar actividad (admin) |
| DELETE /admin/v1/activities/{hashid} | Eliminar actividad (admin) |
| GET /api/v1/activities/list | Lista de actividades (cliente) |
| GET /api/v1/activities/progress | Progreso de participación (cliente) |
| GET /api/v1/activities/{hashid} | Detalle de actividad (cliente) |
| POST /api/v1/activities/{hashid}/checkin | Check-in (cliente) |

### 10.4 Grupos / Compartir (cliente :8792 + admin :8789)

| Endpoint | Descripción |
|------|------|
| POST /api/v1/groups | Crear grupo |
| GET /api/v1/groups/{hashid} | Detalle del grupo |
| GET /api/v1/groups/{hashid}/members | Lista de miembros |
| POST /api/v1/groups/{hashid}/join | Unirse al grupo |
| POST /api/v1/groups/{hashid}/leave | Salir del grupo |
| PUT /api/v1/groups/{hashid}/role | Rol de miembro |
| POST /api/v1/shares | Crear enlace de compartir |
| POST /api/v1/shares/visit | Seguimiento de visitas de compartidos |
| GET /admin/v1/groups | Lista de grupos (admin) |
| GET /admin/v1/groups/{hashid}/audit | Auditoría de grupo (admin) |
| GET /admin/v1/share/stats | Estadísticas de compartidos (admin) |

### 10.5 Extensiones de pasarela de pago (L1)

| Pasarela | Descripción |
|------|------|
| Adyen | Nueva pasarela de pago (depósito / verificación de callback / acreditación automática) |
| GrabPay | Nueva pasarela de pago (depósito / verificación de callback / acreditación automática) |

### 10.6 VIP / Logros / Búsqueda / Recibos (Admin :8789)

Niveles VIP, configuración de logros, búsqueda global y exportación de recibos (admin).

| Endpoint | Descripción |
|------|------|
| GET /admin/v1/vip/level/list | Lista de niveles VIP |
| POST /admin/v1/vip/level/create | Crear nivel VIP (level debe ser único) |
| PUT /admin/v1/vip/level/{hashid} | Actualizar nivel VIP |
| DELETE /admin/v1/vip/level/{hashid} | Eliminar nivel VIP (rechazado si hay usuarios en ese nivel) |
| GET /admin/v1/achievement/list | Lista de logros |
| POST /admin/v1/achievement/create | Crear logro (key duplicada rechazada) |
| PUT /admin/v1/achievement/{hashid} | Actualizar logro |
| DELETE /admin/v1/achievement/{hashid} | Eliminar logro |
| GET /admin/v1/search | Búsqueda global (?q= término, type=game o user) |
| POST /admin/v1/export/receipt | Exportar recibo en PDF (type=deposit o withdraw más order_id) |
