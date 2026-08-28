# Documentación de la API
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · **Español** · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Documentación en línea interactiva (con soporte de depuración en línea):
- Negocio del lado C: http://localhost:8788/apidoc/
- Panel de administración: http://localhost:8787/apidoc/
- Contraseña: admin123

## 1. Convenciones

### 1.1 URL base

| End | Dirección |
|----|------|
| Panel de administración | `http://localhost:8787` |
| Negocio del lado C | `http://localhost:8788` |

### 1.2 Cabeceras de solicitud comunes

```
Content-Type: application/json
API-Version: v1
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

## 2. Interfaces del lado C (service :8788)

### 2.1 Autenticación

#### POST /api/auth/register — Registro de usuario

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

#### POST /api/auth/login — Inicio de sesión de usuario

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

Error: 401 nombre de usuario o contraseña incorrectos / cuenta deshabilitada

#### POST /api/auth/refresh — Refrescar Token

```
请求: (Authorization: Bearer <refresh_token>)

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 Billetera

#### GET /api/wallet/info — Información de la billetera

```
需认证: 是

响应: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/wallet/transactions — Registro de movimientos

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

### 2.3 Recarga

#### POST /api/deposit/create — Crear orden de recarga

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

Valores de currency: USD / CNY / EUR

checkout_url: enlace de redirección a la pasarela de pago (se rellena al crear el pedido); expires_at: caducidad del enlace de pago (1 hora tras la creación)

#### GET /api/deposit/orders — Registros de recarga

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

Valores de status: pending / paid / confirmed / cancelled

### 2.4 Conversión

#### POST /api/exchange/quote — Cotización

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

direction: in=comprar moneda de juego / out=vender moneda de juego

#### POST /api/exchange/buy — Comprar moneda de juego

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

Error: 422 saldo insuficiente de moneda de plataforma / 404 juego no disponible

#### POST /api/exchange/sell — Vender moneda de juego

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

Error: 422 saldo insuficiente de moneda de juego

#### GET /api/exchange/records — Registros de conversión

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

### 2.5 Retiro

#### POST /api/withdraw/apply — Solicitud de retiro

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

Valores de method: paypal / bank / crypto

status:
- approved: aprobación automática (importe < auto_approve_threshold)
- pending: pendiente de revisión (importe >= auto_approve_threshold)

Errores:
- 403 la función de retiro está temporalmente deshabilitada (interruptor global apagado)
- 400 por debajo del importe mínimo de retiro
- 400 supera el límite diario de retiro
- 400 saldo insuficiente

#### GET /api/withdraw/orders — Registros de retiro

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

### 2.6 Juegos

#### GET /api/game/list — Lista de juegos

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

Valores de type: self / third_party

#### GET /api/game/{hashid} — Detalle del juego

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

#### POST /api/game/launch — Iniciar juego

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

### 2.7 Inicio de sesión OAuth de terceros

Admite 7 plataformas: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/auth/oauth/{provider} — Obtener URL de autorización

```
参数: provider = google / facebook / apple / twitter / microsoft / linkedin / github

响应: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/auth/oauth/{provider}/callback — Callback de OAuth

```
请求: { "code": "授权码", "state": "防CSRF状态" }

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=usuario recién registrado / false=cuenta existente vinculada

### 2.8 Verificación de identidad KYC

#### GET /api/user/identity/status — Estado de verificación

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

#### POST /api/user/identity/apply — Enviar verificación

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

### 2.9 Pagos

#### POST /api/payment/callback — Callback de pago (público)

```
请求: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

响应: { "message": "success" }
```

status: success / failed

Valores de provider: stripe / paypal / nowpayments / coinbase (nowpayments verifica con IPN HMAC-SHA512, coinbase con webhook HMAC-SHA256)

#### GET /api/payment/methods — Métodos de pago disponibles (público)

```
响应: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

Filtrado por país del usuario (X-Language/Accept-Language → código de país): countries vacío o con * significa visible globalmente; ordenado según la preferencia de métodos de pago de country_config de ese país

### 2.10 Registros de juego

#### GET /api/game/play-logs — Lista de registros de juego

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

#### GET /api/game/play-log/{hashid} — Detalle del registro de juego

```
需认证: 是
响应: { 完整记录，含 session_id / game_amount_before / after 等 }
```

### 2.12 Clasificaciones

#### GET /api/leaderboard/list — Lista de clasificaciones

```
响应: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/leaderboard/{hashid} — Detalle de clasificación

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

### 2.13 Cupones

#### GET /api/coupon/available — Cupones disponibles para reclamar

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/coupon/claim — Reclamar cupón

```
需认证: 是
请求: { "coupon_id": "hashid" }
响应: { "coupon": { ... } }
```

#### GET /api/coupon/my — Mis cupones

```
需认证: 是
参数: ?status=unused
响应: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 Configuración de países

#### GET /api/country/list — Lista de países

```
响应: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/country/{code} — Detalle de país

```
响应: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 Notificaciones

#### GET /api/notification/list — Lista de notificaciones

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

#### GET /api/notification/unread-count — Cantidad de no leídas

```
需认证: 是
响应: { "count": 3 }
```

#### POST /api/notification/read — Marcar como leídas

```
需认证: 是
请求: { "id": "hashid" }  // 不传=全部已读
```

### 2.17 Recomendación

#### GET /api/referral/my-code — Mi código de recomendación

```
需认证: 是
响应: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/referral/apply — Usar código de recomendación

```
需认证: 是
请求: { "code": "ABC12345" }
响应: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/user/2fa/status — Estado de 2FA

```
需认证: 是
响应: { "enabled": false }
```

#### POST /api/user/2fa/setup — Configurar 2FA

```
需认证: 是
响应: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/user/2fa/enable — Habilitar 2FA

```
需认证: 是
请求: { "code": "123456" }
响应: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/2fa/verify — Verificar 2FA (público)

```
请求: { "user_id": "hashid", "code": "123456" }
响应: { "valid": true }
```

### 2.19 Búsqueda

#### GET /api/search — Búsqueda global

```
参数: ?q=keyword&type=game&page=1&per_page=20
响应: { "list": [...], "total": 100 }
```

#### GET /api/game/suggest — Sugerencias de búsqueda

```
参数: ?q=shoot
响应: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 Idiomas

#### GET /api/language/list — Lista de idiomas disponibles

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

#### POST /api/language/switch — Cambiar idioma

```
请求: { "locale": "zh-CN" }
响应: { "locale": "zh-CN" }
```

Valores de locale: en-US / zh-CN / ja-JP / ko-KR

### 2.8 Usuario

#### GET /api/user/profile — Información personal

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

#### PUT /api/user/profile — Editar perfil

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

Valores de language: en-US / zh-CN / ja-JP / ko-KR

### 2.9 Anuncios

#### GET /api/announcement/list — Lista de anuncios

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

#### GET /api/announcement/detail/{hashid} — Detalle de anuncio

```
响应: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

## 3. Interfaces del panel de administración (admin :8787)

### 3.1 Dashboard de la plataforma

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

### 3.2 Gestión de juegos

#### GET /admin/game/list — Lista de juegos

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

#### POST /admin/game/create — Crear juego

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

Valores de type: self / third_party

#### PUT /admin/game/{hashid} — Editar juego

```
需认证: 是

请求: {
  "name": "新名称",
  "status": 1
  // 可部分更新，字段同 create
}

响应: { "message": "更新成功" }
```

#### DELETE /admin/game/{hashid} — Eliminar juego

```
需认证: 是
响应: { "message": "删除成功" }
```

#### POST /admin/game/currency/manage — Gestionar monedas

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

### 3.3 Gestión de retiros

#### GET /admin/withdraw/orders — Lista de órdenes de retiro

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

#### PUT /admin/withdraw/review — Revisar retiro

```
需认证: 是

请求: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

响应: { "message": "已通过" }
```

action: approve=aprobar / reject=rechazar (al rechazar se devuelve automáticamente la moneda de plataforma)

Error: 422 el estado de la orden no es pendiente de revisión

#### PUT /admin/withdraw/switch — Interruptor global de retiros

```
需认证: 是

请求: { "enabled": 1 }

响应: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/withdraw/limits/set — Configurar límites de retiro

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

### 3.4 Gestión de usuarios de la plataforma

#### GET /admin/platform/user/list — Lista de usuarios del lado C

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

#### GET /admin/platform/user/{hashid} — Detalle de usuario

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

#### PUT /admin/platform/user/{hashid} — Editar/banear usuario

```
需认证: 是

请求: {
  "status": 0,         // 0=禁用 1=启用
  "nickname": "..."    // 可选
}

响应: { "message": "更新成功" }
```

### 3.5 Gestión de pagos

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

#### POST /admin/payment/method/toggle — Habilitar/deshabilitar método de pago

```
需认证: 是

请求: { "id": "aB3xK...", "status": 0 }

响应: { "message": "已更新" }
```

### 3.6 Gestión de anuncios

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

#### POST /admin/announcement/create — Publicar anuncio

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

### 3.7 Revisión KYC

#### GET /admin/identity/list — Lista KYC

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

#### PUT /admin/identity/review — Revisar KYC

```
需认证: 是

请求: { "id": "hashid", "action": "approve", "note": "" }

响应: { "message": "Approved" }
```

action: approve / reject

### 3.8 Gestión de servidores del juego

#### GET /admin/game/server/list — Lista de servidores

```
需认证: 是
参数: ?game_id=hashid

响应: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/game/server/create — Crear servidor

```
需认证: 是
请求: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
响应: { "id": "hashid" }
```

#### PUT /admin/game/server/{hashid} — Editar servidor

```
需认证: 是
请求: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/game/server/{hashid} — Eliminar servidor

```
需认证: 是
```

### 3.9 Gestión de límites escalonados de retiro

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

#### PUT /admin/withdraw/limits/{hashid} — Actualizar límite

```
需认证: 是

请求: { "single_max": "10000.0000", "fee_pct": "0.25" }
// 可部分更新
```

### 3.11 Gestión de categorías de juegos

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

#### PUT /admin/game/category/{hashid} — Editar categoría

#### DELETE /admin/game/category/{hashid} — Eliminar categoría

#### POST /admin/game/category/assign — Asignar juegos

```
需认证: 是
请求: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 Gestión de clasificaciones

#### GET /admin/leaderboard/list — Lista de clasificaciones

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/leaderboard/create — Crear clasificación

```
需认证: 是
请求: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(可选)" }
```

#### PUT /admin/leaderboard/{hashid} — Editar clasificación

#### DELETE /admin/leaderboard/{hashid} — Eliminar clasificación

#### POST /admin/leaderboard/{hashid}/refresh — Refrescar caché

### 3.13 Gestión de cupones

#### GET /admin/coupon/list — Lista de cupones

#### POST /admin/coupon/create — Crear cupón

```
需认证: 是
请求: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/coupon/{hashid} — Editar (cuando no está reclamado)

#### DELETE /admin/coupon/{hashid} — Eliminar

#### GET /admin/coupon/{hashid}/stats — Estadísticas de reclamo

```
响应: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 Gestión de configuración de países

#### GET /admin/country/config/list — Lista de configuración de países

#### POST /admin/country/config/create — Crear configuración de país

```
需认证: 是
请求: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/country/config/{hashid} — Editar configuración de país

### 3.15 Exportación de datos

#### POST /admin/export/users — Exportar usuarios del lado C

```
需认证: 是
参数(JSON): { "status": 1 }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

#### POST /admin/export/transactions — Exportar movimientos de la plataforma

```
需认证: 是
参数(JSON): { "type": "deposit" }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

### 3.16 Análisis de datos (agregación en tiempo real de MySQL)

Todos los endpoints requieren autenticación (AdminAuth + AdminPermission). Los datos se agregan en tiempo real desde MySQL, sin depender de ClickHouse.

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/analytics/overview | Resumen de la plataforma (hoy/últimos 7 días) |
| GET | /admin/analytics/game-ranking | Ranking de juegos (?days=7) |
| GET | /admin/analytics/dau-trend | Tendencia DAU (?days=30) |
| GET | /admin/analytics/hourly-trend | Tendencia por hora |
| GET | /admin/analytics/action-distribution | Distribución de acciones |
| GET | /admin/analytics/revenue | Análisis de ingresos |
| GET | /admin/analytics/conversion | Tasa de conversión de juegos |
| GET | /admin/analytics/probability | Probabilidad conjunta/condicional |
| GET | /admin/analytics/retention | Análisis de retención D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Embudo de conversión |
| GET | /admin/analytics/arpu | Tendencia ARPU/ARPPU |
| GET | /admin/analytics/economy | Indicadores económicos de monedas de juego |

### 3.17 Gestión de tickets

Todos los endpoints requieren autenticación (AdminAuth + AdminPermission).

| Método | Ruta | Descripción |
|------|------|------|
| GET | /admin/ticket/list | Lista de tickets (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | Detalle de ticket (incluye respuestas) |
| POST | /admin/ticket/{hashid}/reply | Responder ticket |
| POST | /admin/ticket/{hashid}/close | Cerrar ticket |
| POST | /admin/ticket/{hashid}/assign | Asignar responsable (admin_id) |

## 4. Política de limitación de velocidad

| Interfaz | Límite |
|------|------|
| Predeterminado | 60 veces/minuto/IP |
| POST /api/auth/login | 10 veces/minuto |
| POST /api/auth/register | 5 veces/minuto |

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

#### POST /api/provider/bet — Notificar apuesta

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

#### POST /api/provider/settle — Notificar liquidación

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

#### POST /api/provider/refund — Notificar reembolso

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

### 7.2 API de tickets

#### GET /api/ticket/list — Lista de tickets

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

#### POST /api/ticket/create — Crear ticket

```
需认证: 是
请求: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
响应: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/ticket/{hashid} — Detalle de ticket

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

#### POST /api/ticket/{hashid}/reply — Responder ticket

```
需认证: 是
请求: { "content": "已核实，将在24小时内处理" }
响应: { "code": 0, "message": "Reply sent" }
```

### 7.3 API de verificación de email

#### POST /api/verify/send-email — Enviar código de verificación de email

```
需认证: 是
请求: { "email": "user@example.com" }
响应: { "code": 0, "message": "Verification code sent" }
错误: 429 请60秒后重试
```

#### POST /api/verify/confirm-email — Confirmar email

```
需认证: 是
请求: { "code": "123456" }
响应: { "code": 0, "message": "Email verified" }
错误: 422 验证码无效或已过期
```

### 7.4 API VIP

#### GET /api/user/vip-status — Estado VIP

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

### 7.5 API de logros

#### GET /api/user/achievements — Lista de logros

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

### 7.6 Nuevas API del panel de administración

#### GET /admin/ticket/list — Lista de tickets

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

#### POST /admin/ticket/{hashid}/reply — Responder ticket

```
需认证: 是
请求: { "content": "已处理" }
响应: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/ticket/{hashid}/close — Cerrar ticket

```
需认证: 是
响应: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/ticket/{hashid}/assign — Asignar responsable

```
需认证: 是
请求: { "admin_id": 1234567890 }
响应: { "code": 0, "message": "Assigned" }
```

#### GET /admin/analytics/retention — Análisis de retención

```
需认证: 是
参数: ?days=30
响应: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/analytics/funnel — Embudo de conversión

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

#### GET /admin/analytics/arpu — Tendencia ARPU/ARPPU

```
需认证: 是
参数: ?days=30
响应: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/analytics/economy — Indicadores económicos de monedas de juego

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

## 8. Política de limitación de velocidad (actualizada)

| Interfaz | Límite |
|------|------|
| Predeterminado | 60 veces/minuto/IP |
| POST /api/auth/login | 10 veces/minuto |
| POST /api/auth/register | 5 veces/minuto |
| POST /api/auth/oauth | 10 veces/minuto |
| POST /api/payment/callback | 30 veces/minuto |
| POST /api/provider/* | Sin límite (autenticación por firma HMAC) |

## 9. Descripción de la autenticación (actualizada)

### Autenticación de Provider (ProviderAuth)

1. Extraer `X-Game-Id`, `X-Timestamp`, `X-Signature` de las cabeceras de la solicitud
2. Consultar la tabla `game_game` para verificar que el juego existe y status=1
3. Verificar que la marca de tiempo esté dentro de la ventana de 5 minutos (anti-replay)
4. Calcular `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` y comparar con la firma
5. Inyectar `$request->gameId` y `$request->game`


### 7.7 API de amigos

#### GET /api/friend/list — Lista de amigos
```
需认证: 是
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/friend/requests — Solicitudes pendientes
```
需认证: 是
响应: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/friend/request — Enviar solicitud de amistad
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### POST /api/friend/accept — Aceptar solicitud
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/friend/reject — Rechazar solicitud
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/friend/remove — Eliminar amigo
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### GET /api/friend/search — Buscar usuarios
```
需认证: 是
参数: ?q=username
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 API de chat

#### GET /api/chat/conversations — Lista de conversaciones
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

#### GET /api/chat/messages/{peerHashid} — Lista de mensajes
```
需认证: 是
参数: ?page=1&per_page=50
响应: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
自动标记对端发来的未读消息为已读
```

#### POST /api/chat/send — Enviar mensaje
```
需认证: 是
请求: { "to_user_id": "hashid", "content": "Hello!" }
错误: 403 非好友不可发
```

#### GET /api/chat/unread-total — Total de no leídos
```
需认证: 是
响应: { "count": 5 }
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

#### GET /api/webhook/list — Lista de suscripciones
```
需认证: 是
响应: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/webhook/register — Registrar suscripción
```
需认证: 是
请求: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
可用事件: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/webhook/delete — Eliminar suscripción
```
需认证: 是
请求: { "id": "hook_id" }
```

### 7.10 API de análisis avanzado

#### GET /admin/analytics/retention — Análisis de retención
```
需认证: 是
响应: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/analytics/funnel — Embudo de conversión
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

#### GET /admin/analytics/arpu — Tendencia ARPU/ARPPU
```
需认证: 是
参数: ?days=30
响应: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/analytics/economy — Indicadores de economía del juego
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


### 7.11 API de torneos

#### GET /api/tournament/list — Lista de torneos
```
参数: ?status=active|upcoming|ended&page=1&per_page=20
响应: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/tournament/{hashid} — Detalle de torneo
```
响应: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/tournament/{hashid}/join — Inscribirse en el torneo
```
需认证: 是
错误: 422 已报名 / 400 已开始或已满员 / 503 FeatureFlag关闭
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
| POST /api/tournament/{id}/join | 10 veces/minuto |
