# API-Dokumentation
<!-- lang-nav -->

Languages: **中文** · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Online-interaktive Dokumentation (mit Online-Debugging):
- C-End-Geschäft: http://localhost:8788/apidoc/
- Verwaltungsbackend: http://localhost:8787/apidoc/
- Passwort: admin123

## 1. Konventionen

### 1.1 Basis-URL

| Endgerät | Adresse |
|----|------|
| Verwaltungsbackend | `http://localhost:8787` |
| C-End-Geschäft | `http://localhost:8788` |

### 1.2 Allgemeine Anfrage-Header

```
Content-Type: application/json
API-Version: v1
Authorization: Bearer <token>    (bei authentifizierungspflichtigen Schnittstellen)
```

### 1.3 Einheitliches Antwortformat

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Bedeutung |
|------|------|
| 0 | Erfolg |
| 400 | Parameterfehler |
| 401 | Nicht authentifiziert (Token fehlt/abgelaufen/ungültig) |
| 403 | Keine Berechtigung |
| 404 | Ressource nicht vorhanden |
| 422 | Validierungsfehler |
| 429 | Zu viele Anfragen (Ratenbegrenzung ausgelöst) |
| 500 | Serverfehler |

### 1.4 ID-Kodierung

Alle IDs in API-Anfragen und -Antworten sind Hashids-kodierte Strings, keine rohen BIGINT-Werte.

```
Extern: aB3xK9mW2pQ7rT5v  (hashid-String)
Intern: 1750123456789      (Snowflake BIGINT)
```

### 1.5 Paginierungsformat

```
Anfrage: ?page=1&per_page=20

Antwort: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. C-End-Schnittstellen (service :8788)

### 2.1 Authentifizierung

#### POST /api/auth/register — Benutzerregistrierung

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

#### POST /api/auth/login — Benutzer-Login

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

Fehler: 401 falscher Benutzername oder falsches Passwort / Konto deaktiviert

#### POST /api/auth/refresh — Token aktualisieren

```
请求: (Authorization: Bearer <refresh_token>)

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 Wallet

#### GET /api/wallet/info — Wallet-Informationen

```
需认证: 是

响应: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/wallet/transactions — Transaktionsprotokoll

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

### 2.3 Einzahlungen

#### POST /api/deposit/create — Einzahlungsauftrag erstellen

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
  "platform_amount": "10.0000"
}
```

currency 可选值: USD / CNY / EUR

#### GET /api/deposit/orders — Einzahlungsverlauf

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

### 2.4 Umtausch

#### POST /api/exchange/quote — Preisangebot

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

direction: in=Spielwährung kaufen / out=Spielwährung verkaufen

#### POST /api/exchange/buy — Spielwährung kaufen

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

Fehler: 422 unzureichendes Plattformwährungs-Guthaben / 404 Spiel nicht verfügbar

#### POST /api/exchange/sell — Spielwährung verkaufen

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

Fehler: 422 unzureichendes Spielwährungs-Guthaben

#### GET /api/exchange/records — Umtauschverlauf

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

### 2.5 Auszahlungen

#### POST /api/withdraw/apply — Auszahlungsantrag

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
- approved: automatisch genehmigt (Betrag < auto_approve_threshold)
- pending: Prüfung ausstehend (Betrag >= auto_approve_threshold)

Fehler:
- 403 Auszahlungsfunktion vorübergehend deaktiviert (globaler Schalter aus)
- 400 unter dem Mindestauszahlungsbetrag
- 400 Tageslimit der Auszahlung überschritten
- 400 unzureichendes Guthaben

#### GET /api/withdraw/orders — Auszahlungsverlauf

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

### 2.6 Spiele

#### GET /api/game/list — Spieleliste

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

#### GET /api/game/{hashid} — Spieldetails

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

#### POST /api/game/launch — Spiel starten

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

### 2.7 OAuth-Login von Drittanbietern

Unterstützt 7 Plattformen: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/auth/oauth/{provider} — Autorisierungs-URL abrufen

```
参数: provider = google / facebook / apple / twitter / microsoft / linkedin / github

响应: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/auth/oauth/{provider}/callback — OAuth-Callback

```
请求: { "code": "授权码", "state": "防CSRF状态" }

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=neu registrierter Benutzer / false=vorhandenes Konto verknüpft

### 2.8 KYC-Identitätsprüfung

#### GET /api/user/identity/status — Prüfstatus

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

#### POST /api/user/identity/apply — Identitätsprüfung einreichen

```
需认证: 是

请求: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}
```

响应: { "message": "KYC submitted successfully" }
```

### 2.9 Zahlungen

#### POST /api/payment/callback — Zahlungs-Callback (öffentlich)

```
请求: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

响应: { "message": "success" }
```

status: success / failed

#### GET /api/payment/methods — Verfügbare Zahlungsmethoden (öffentlich)

```
响应: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "status": 1 }
  ]
}
```

### 2.10 Spielprotokolle

#### GET /api/game/play-logs — Spielprotokoll-Liste

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

#### GET /api/game/play-log/{hashid} — Spielprotokoll-Details

```
需认证: 是
响应: { 完整记录，含 session_id / game_amount_before / after 等 }
```

### 2.12 Ranglisten

#### GET /api/leaderboard/list — Ranglistenliste

```
响应: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/leaderboard/{hashid} — Ranglistendetails

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

### 2.13 Gutscheine

#### GET /api/coupon/available — Verfügbare Gutscheine

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/coupon/claim — Gutschein einlösen

```
需认证: 是
请求: { "coupon_id": "hashid" }
响应: { "coupon": { ... } }
```

#### GET /api/coupon/my — Meine Gutscheine

```
需认证: 是
参数: ?status=unused
响应: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 Länderkonfiguration

#### GET /api/country/list — Länderliste

```
响应: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/country/{code} — Länderdetails

```
响应: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 Benachrichtigungen

#### GET /api/notification/list — Benachrichtigungsliste

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

#### GET /api/notification/unread-count — Ungelesene Anzahl

```
需认证: 是
响应: { "count": 3 }
```

#### POST /api/notification/read — Als gelesen markieren

```
需认证: 是
请求: { "id": "hashid" }  // 不传=全部已读
```

### 2.17 Empfehlungen

#### GET /api/referral/my-code — Mein Empfehlungscode

```
需认证: 是
响应: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/referral/apply — Empfehlungscode verwenden

```
需认证: 是
请求: { "code": "ABC12345" }
响应: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/user/2fa/status — 2FA-Status

```
需认证: 是
响应: { "enabled": false }
```

#### POST /api/user/2fa/setup — 2FA einrichten

```
需认证: 是
响应: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/user/2fa/enable — 2FA aktivieren

```
需认证: 是
请求: { "code": "123456" }
响应: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/2fa/verify — 2FA verifizieren (öffentlich)

```
请求: { "user_id": "hashid", "code": "123456" }
响应: { "valid": true }
```

### 2.19 Suche

#### GET /api/search — Globale Suche

```
参数: ?q=keyword&type=game&page=1&per_page=20
响应: { "list": [...], "total": 100 }
```

#### GET /api/game/suggest — Suchvorschläge

```
参数: ?q=shoot
响应: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 Sprachen

#### GET /api/language/list — Verfügbare Sprachen

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

#### POST /api/language/switch — Sprache wechseln

```
请求: { "locale": "zh-CN" }
响应: { "locale": "zh-CN" }
```

locale 可选值: en-US / zh-CN / ja-JP / ko-KR

### 2.8 Benutzer

#### GET /api/user/profile — Persönliche Informationen

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

#### PUT /api/user/profile — Profil bearbeiten

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

### 2.9 Ankündigungen

#### GET /api/announcement/list — Ankündigungsliste

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

#### GET /api/announcement/detail/{hashid} — Ankündigungsdetails

```
响应: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

## 3. Verwaltungsbackend-Schnittstellen (admin :8787)

### 3.1 Plattform-Dashboard

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

### 3.2 Spieleverwaltung

#### GET /admin/game/list — Spieleliste

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

#### POST /admin/game/create — Spiel erstellen

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

#### PUT /admin/game/{hashid} — Spiel bearbeiten

```
需认证: 是

请求: {
  "name": "新名称",
  "status": 1
  // 可部分更新，字段同 create
}

响应: { "message": "更新成功" }
```

#### DELETE /admin/game/{hashid} — Spiel löschen

```
需认证: 是
响应: { "message": "删除成功" }
```

#### POST /admin/game/currency/manage — Währungen verwalten

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

### 3.3 Auszahlungsverwaltung

#### GET /admin/withdraw/orders — Auszahlungsauftragsliste

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

#### PUT /admin/withdraw/review — Auszahlung prüfen

```
需认证: 是

请求: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

响应: { "message": "已通过" }
```

action: approve=genehmigen / reject=ablehnen (bei Ablehnung wird die Plattformwährung automatisch zurückgebucht)

Fehler: 422 Auftragsstatus ist nicht "Prüfung ausstehend"

#### PUT /admin/withdraw/switch — Globaler Auszahlungsschalter

```
需认证: 是

请求: { "enabled": 1 }

响应: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/withdraw/limits/set — Auszahlungslimits festlegen

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

### 3.4 Plattform-Benutzerverwaltung

#### GET /admin/platform/user/list — C-End-Benutzerliste

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

#### GET /admin/platform/user/{hashid} — Benutzerdetails

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

#### PUT /admin/platform/user/{hashid} — Benutzer bearbeiten/sperren

```
需认证: 是

请求: {
  "status": 0,         // 0=禁用 1=启用
  "nickname": "..."    // 可选
}

响应: { "message": "更新成功" }
```

### 3.5 Zahlungsverwaltung

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

#### POST /admin/payment/method/toggle — Zahlungsmethode aktivieren/deaktivieren

```
需认证: 是

请求: { "id": "aB3xK...", "status": 0 }

响应: { "message": "已更新" }
```

### 3.6 Ankündigungsverwaltung

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

#### POST /admin/announcement/create — Ankündigung veröffentlichen

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

### 3.7 KYC-Prüfung

#### GET /admin/identity/list — KYC-Liste

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

#### PUT /admin/identity/review — KYC prüfen

```
需认证: 是

请求: { "id": "hashid", "action": "approve", "note": "" }

响应: { "message": "Approved" }
```

action: approve / reject

### 3.8 Spielserver-Verwaltung

#### GET /admin/game/server/list — Serverliste

```
需认证: 是
参数: ?game_id=hashid

响应: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/game/server/create — Server erstellen

```
需认证: 是
请求: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
响应: { "id": "hashid" }
```

#### PUT /admin/game/server/{hashid} — Server bearbeiten

```
需认证: 是
请求: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/game/server/{hashid} — Server löschen

```
需认证: 是
```

### 3.9 Auszahlungs-Stufenlimit-Verwaltung

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

#### PUT /admin/withdraw/limits/{hashid} — Limit aktualisieren

```
需认证: 是

请求: { "single_max": "10000.0000", "fee_pct": "0.25" }
// 可部分更新
```

### 3.11 Spielkategorie-Verwaltung

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

#### PUT /admin/game/category/{hashid} — Kategorie bearbeiten

#### DELETE /admin/game/category/{hashid} — Kategorie löschen

#### POST /admin/game/category/assign — Spiele zuweisen

```
需认证: 是
请求: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 Ranglisten-Verwaltung

#### GET /admin/leaderboard/list — Ranglistenliste

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/leaderboard/create — Rangliste erstellen

```
需认证: 是
请求: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(可选)" }
```

#### PUT /admin/leaderboard/{hashid} — Rangliste bearbeiten

#### DELETE /admin/leaderboard/{hashid} — Rangliste löschen

#### POST /admin/leaderboard/{hashid}/refresh — Cache aktualisieren

### 3.13 Gutscheinverwaltung

#### GET /admin/coupon/list — Gutscheinliste

#### POST /admin/coupon/create — Gutschein erstellen

```
需认证: 是
请求: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/coupon/{hashid} — Bearbeiten (nur wenn nicht eingelöst)

#### DELETE /admin/coupon/{hashid} — Löschen

#### GET /admin/coupon/{hashid}/stats — Einlösungsstatistik

```
响应: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 Länderkonfigurations-Verwaltung

#### GET /admin/country/config/list — Länderkonfigurationsliste

#### POST /admin/country/config/create — Länderkonfiguration erstellen

```
需认证: 是
请求: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/country/config/{hashid} — Länderkonfiguration bearbeiten

### 3.15 Datencxport

#### POST /admin/export/users — C-End-Benutzer exportieren

```
需认证: 是
参数(JSON): { "status": 1 }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

#### POST /admin/export/transactions — Plattformtransaktionen exportieren

```
需认证: 是
参数(JSON): { "type": "deposit" }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

### 3.16 Datenanalyse (MySQL-Echtzeitaggregation)

Alle Endpunkte erfordern Authentifizierung (AdminAuth + AdminPermission); die Daten werden in Echtzeit aus MySQL aggregiert, ohne Abhängigkeit von ClickHouse.

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/analytics/overview | Plattform-Überblick (heute/letzte 7 Tage) |
| GET | /admin/analytics/game-ranking | Spiel-Ranking (?days=7) |
| GET | /admin/analytics/dau-trend | DAU-Trend (?days=30) |
| GET | /admin/analytics/hourly-trend | Stunden-Trend |
| GET | /admin/analytics/action-distribution | Verhaltensverteilung |
| GET | /admin/analytics/revenue | Umsatzanalyse |
| GET | /admin/analytics/conversion | Spiel-Konversionsrate |
| GET | /admin/analytics/probability | Gemeinsame/bedingte Wahrscheinlichkeit |
| GET | /admin/analytics/retention | Retentionsanalyse D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Konversions-Trichter |
| GET | /admin/analytics/arpu | ARPU/ARPPU-Trend |
| GET | /admin/analytics/economy | Wirtschaftskennzahlen der Spielwährungen |

### 3.17 Ticket-Verwaltung

Alle Endpunkte erfordern Authentifizierung (AdminAuth + AdminPermission).

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/ticket/list | Ticketliste (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | Ticketdetails (inkl. Antworten) |
| POST | /admin/ticket/{hashid}/reply | Ticket beantworten |
| POST | /admin/ticket/{hashid}/close | Ticket schließen |
| POST | /admin/ticket/{hashid}/assign | Bearbeiter zuweisen (admin_id) |

## 4. Ratenbegrenzungsstrategie

| Schnittstelle | Limit |
|------|------|
| Standard | 60 Anfragen/Minute/IP |
| POST /api/auth/login | 10 Anfragen/Minute |
| POST /api/auth/register | 5 Anfragen/Minute |

Bei Überschreitung wird 429 zurückgegeben, die Antwort-Header enthalten:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1716400830
Retry-After: 60
```

## 5. Authentifizierungshinweise

### C-End (UserAuth)

1. Token aus `Authorization: Bearer <token>` extrahieren
2. JWT-Signaturprüfung (HS256), `sub` (Benutzer-ID) parsen
3. `game_user`-Tabelle abfragen, um zu prüfen, dass der Benutzer existiert und status=1
4. `$request->userId` injizieren

### Verwaltungsbackend (AdminAuth + AdminPermission)

1. AdminAuth: JWT-Signaturprüfung, `sub` (Admin-ID) parsen, `$request->adminId` injizieren
2. AdminPermission: Berechtigungen anhand der Benutzerrolle suchen, Berechtigungskennung im Format `method.path` abgleichen
3. Superadmins mit `slug=*` überspringen die Berechtigungsprüfung

## 6. Fehlercode-Nachschlage

| code | Bedeutung | Häufige Szenarien |
|------|------|---------|
| 0 | Erfolg | - |
| 400 | Parameterfehler | falsches Anfrageformat, unzureichender Betrag |
| 401 | Nicht authentifiziert | Token fehlt/abgelaufen/ungültig, Konto deaktiviert |
| 403 | Keine Berechtigung | Benutzer hat keine entsprechende Rollenberechtigung, Spiel nicht verfügbar |
| 404 | Nicht vorhanden | Ressource nicht gefunden |
| 422 | Validierungsfehler | Formularparameter verletzen Regeln, Auftragsstatus erlaubt die Aktion nicht |
| 429 | Ratenbegrenzung | zu viele Anfragen |
| 500 | Serverfehler | unerwartete Ausnahme |


## 7. Neue APIs (v2.0 Ökosystem-Erweiterung)

### 7.1 Provider-API — Callback-Schnittstellen für Spieleanbieter

**Authentifizierung**: HMAC-SHA256-Signatur (X-Game-Id + X-Timestamp + X-Signature)
**Zeitfenster**: 5 Minuten

#### POST /api/provider/balance — Benutzerguthaben abfragen

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

#### POST /api/provider/bet — Einsatz melden

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

#### POST /api/provider/settle — Abrechnung melden

```
请求: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}
```

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

#### POST /api/provider/refund — Erstattung melden

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

### 7.2 Ticket-API

#### GET /api/ticket/list — Ticketliste

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

#### POST /api/ticket/create — Ticket erstellen

```
需认证: 是
请求: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
响应: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/ticket/{hashid} — Ticketdetails

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

#### POST /api/ticket/{hashid}/reply — Ticket beantworten

```
需认证: 是
请求: { "content": "已核实，将在24小时内处理" }
响应: { "code": 0, "message": "Reply sent" }
```

### 7.3 E-Mail-Verifizierungs-API

#### POST /api/verify/send-email — E-Mail-Verifizierungscode senden

```
需认证: 是
请求: { "email": "user@example.com" }
响应: { "code": 0, "message": "Verification code sent" }
错误: 429 请60秒后重试
```

#### POST /api/verify/confirm-email — E-Mail bestätigen

```
需认证: 是
请求: { "code": "123456" }
响应: { "code": 0, "message": "Email verified" }
错误: 422 验证码无效或已过期
```

### 7.4 VIP-API

#### GET /api/user/vip-status — VIP-Status

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

### 7.5 Errungenschaften-API

#### GET /api/user/achievements — Errungenschaftsliste

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

### 7.6 Neue Verwaltungsbackend-APIs

#### GET /admin/ticket/list — Ticketliste

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

#### POST /admin/ticket/{hashid}/reply — Ticket beantworten

```
需认证: 是
请求: { "content": "已处理" }
响应: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/ticket/{hashid}/close — Ticket schließen

```
需认证: 是
响应: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/ticket/{hashid}/assign — Bearbeiter zuweisen

```
需认证: 是
请求: { "admin_id": 1234567890 }
响应: { "code": 0, "message": "Assigned" }
```

#### GET /admin/analytics/retention — Retentionsanalyse

```
需认证: 是
参数: ?days=30
响应: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/analytics/funnel — Konversions-Trichter

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

#### GET /admin/analytics/arpu — ARPU/ARPPU-Trend

```
需认证: 是
参数: ?days=30
响应: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/analytics/economy — Wirtschaftskennzahlen der Spielwährungen

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

## 8. Ratenbegrenzungsstrategie (aktualisiert)

| Schnittstelle | Limit |
|------|------|
| Standard | 60 Anfragen/Minute/IP |
| POST /api/auth/login | 10 Anfragen/Minute |
| POST /api/auth/register | 5 Anfragen/Minute |
| POST /api/auth/oauth | 10 Anfragen/Minute |
| POST /api/payment/callback | 30 Anfragen/Minute |
| POST /api/provider/* | unbegrenzt (HMAC-Signaturauthentifizierung) |

## 9. Authentifizierungshinweise (aktualisiert)

### Provider-Authentifizierung (ProviderAuth)

1. `X-Game-Id`, `X-Timestamp`, `X-Signature` aus den Anfrage-Headern extrahieren
2. `game_game`-Tabelle abfragen, um zu prüfen, dass das Spiel existiert und status=1
3. Prüfen, dass der Zeitstempel im 5-Minuten-Fenster liegt (gegen Replay)
4. `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` berechnen und mit der Signatur vergleichen
5. `$request->gameId` und `$request->game` injizieren


### 7.7 Freunde-API

#### GET /api/friend/list — Freundesliste
```
需认证: 是
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/friend/requests — Ausstehende Anfragen
```
需认证: 是
响应: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/friend/request — Freundschaftsanfrage senden
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### POST /api/friend/accept — Anfrage annehmen
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/friend/reject — Anfrage ablehnen
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/friend/remove — Freund entfernen
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### GET /api/friend/search — Benutzer suchen
```
需认证: 是
参数: ?q=username
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 Chat-API

#### GET /api/chat/conversations — Konversationsliste
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

#### GET /api/chat/messages/{peerHashid} — Nachrichtenliste
```
需认证: 是
参数: ?page=1&per_page=50
响应: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
自动标记对端发来的未读消息为已读
```

#### POST /api/chat/send — Nachricht senden
```
需认证: 是
请求: { "to_user_id": "hashid", "content": "Hello!" }
错误: 403 非好友不可发
```

#### GET /api/chat/unread-total — Ungelesene Gesamtzahl
```
需认证: 是
响应: { "count": 5 }
```

**WebSocket-Verbindung**: `ws://host:8791`
```
// 认证
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// 接收消息
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 Webhook-API

#### GET /api/webhook/list — Abonnementliste
```
需认证: 是
响应: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/webhook/register — Abonnement registrieren
```
需认证: 是
请求: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
可用事件: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/webhook/delete — Abonnement löschen
```
需认证: 是
请求: { "id": "hook_id" }
```

### 7.10 Erweiterte Analyse-APIs

#### GET /admin/analytics/retention — Retentionsanalyse
```
需认证: 是
响应: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/analytics/funnel — Konversions-Trichter
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

#### GET /admin/analytics/arpu — ARPU/ARPPU-Trend
```
需认证: 是
参数: ?days=30
响应: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/analytics/economy — Spiel-Wirtschaftskennzahlen
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


### 7.11 Turnier-API

#### GET /api/tournament/list — Turnierliste
```
参数: ?status=active|upcoming|ended&page=1&per_page=20
响应: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/tournament/{hashid} — Turnierdetails
```
响应: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/tournament/{hashid}/join — Teilnahme anmelden
```
需认证: 是
错误: 422 已报名 / 400 已开始或已满员 / 503 FeatureFlag关闭
```

### 7.12 Gutscheinbedingungen (neu)

Das JSON `conditions` des Gutscheins unterstützt:
- `min_deposit`: String, Mindestsumme der Einzahlungen
- `first_user_only`: bool, nur neue Benutzer, die nie eingezahlt haben
- `game_id`: int, das angegebene Spiel muss gespielt worden sein

Die Bedingungen werden doppelt geprüft: beim Filtern der Liste in `available()` und beim Einlösen in `claim()`.

### 7.13 Mehrstufige Empfehlungen (neu)

Die Empfehlungsprovision erhält eine zweistufige Gewinnbeteiligung:
- L1: Der direkte Empfehler erhält `referrer_bonus` (Konfiguration: referral.referrer_bonus)
- L2: Der Empfehler des Empfehlers erhält `commission = referrer_bonus * level2_rate` (Konfiguration: referral.level2_rate, Standard 5%)
- `game_referral_commission` protokollieren (level/commission_rate/commission_amount)

### 8. Ratenbegrenzungsstrategie (aktualisiert)

| Schnittstelle | Limit |
|------|------|
| POST /api/tournament/{id}/join | 10 Anfragen/Minute |
