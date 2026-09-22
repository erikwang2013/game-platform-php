# API-Dokumentation
<!-- lang-nav -->

Languages: **中文** · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Online-interaktive Dokumentation (mit Online-Debugging):
- C-End-Geschäft: http://localhost:8792/apidoc/
- Verwaltungsbackend: http://localhost:8789/apidoc/
- Passwort: siehe `APIDOC_PASSWORD` in der Deployment-Umgebung

## 1. Konventionen

### 1.1 Basis-URL

| Endgerät | Adresse |
|----|------|
| Verwaltungsbackend | `http://localhost:8789` |
| C-End-Geschäft | `http://localhost:8792` |

### 1.2 Allgemeine Anfrage-Header

```
Content-Type: application/json
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

## 2. C-End-Schnittstellen (service :8792)

### 2.1 Authentifizierung

#### POST /api/v1/auth/register — Benutzerregistrierung

```
Anfrage: {
  "username": "player1",
  "password": "123456",
  "email": "player@example.com"     // 可选
}

Antwort: {
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

#### POST /api/v1/auth/login — Benutzer-Login

```
Anfrage: {
  "username": "player1",
  "password": "123456"
}

Antwort: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "...", ... }
}
```

Fehler: 401 falscher Benutzername oder falsches Passwort / Konto deaktiviert

#### POST /api/v1/auth/refresh — Token aktualisieren

```
Anfrage: (Authorization: Bearer <refresh_token>)

Antwort: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 Wallet

#### GET /api/v1/wallet/info — Wallet-Informationen

```
Authentifizierung erforderlich: Ja

Antwort: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/v1/wallet/transactions — Transaktionsprotokoll

```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&per_page=20&type=deposit    (type 可选)

Antwort: {
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

type Zulässige Werte: deposit / withdraw / exchange_in / exchange_out / game_earn / game_spend
```

### 2.3 Einzahlungen

#### POST /api/v1/deposit/create — Einzahlungsauftrag erstellen

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "amount": "10.00",
  "currency": "USD",
  "payment_method_id": "aB3xK..."
}

Antwort: {
  "order_id": "aB3xK...",
  "order_no": "DEP202605221030000123",
  "amount": "10.00",
  "platform_amount": "10.0000",
  "checkout_url": "https://checkout.stripe.com/...",
  "expires_at": "2026-05-22 11:30:00"
}
```

currency Zulässige Werte: USD / CNY / EUR / JPY / KRW / GBP / BRL / INR

checkout_url: Weiterleitungslink des Zahlungsgateways (wird bei Auftragserstellung befüllt); expires_at: Ablaufzeit des Zahlungslinks (1 Stunde nach Erstellung)

#### GET /api/v1/deposit/orders — Einzahlungsverlauf

```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&per_page=20

Antwort: {
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

status Zulässige Werte: pending / paid / confirmed / cancelled

### 2.4 Umtausch

#### POST /api/v1/exchange/quote — Preisangebot

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "direction": "in",
  "platform_amount": "10.0000"
}

Antwort: {
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000",
  "spread_pct": "5.00%"
}
```

direction: in=Spielwährung kaufen / out=Spielwährung verkaufen

#### POST /api/v1/exchange/buy — Spielwährung kaufen

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "10.0000"
}

Antwort: {
  "exchange_id": "aB3xK...",
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000"
}
```

Fehler: 422 unzureichendes Plattformwährungs-Guthaben / 404 Spiel nicht verfügbar

#### POST /api/v1/exchange/sell — Spielwährung verkaufen

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "950.0000"
}

Antwort: {
  "exchange_id": "aB3xK...",
  "platform_amount": "9.0250",
  "game_amount": "950.0000",
  "spread_fee": "0.4750",
  "rate": "100.00000000"
}
```

Fehler: 422 unzureichendes Spielwährungs-Guthaben

#### GET /api/v1/exchange/records — Umtauschverlauf

```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&per_page=20

Antwort: {
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

#### POST /api/v1/withdraw/apply — Auszahlungsantrag

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "platform_amount": "50.0000",
  "method": "paypal",
  "account_info": "user@paypal.com"
}

Antwort: {
  "order_id": "...",
  "order_no": "WTH202605221030000456",
  "status": "approved"
}
```

method Zulässige Werte: paypal / bank / crypto

status:
- approved: automatisch genehmigt (Betrag < auto_approve_threshold)
- pending: Prüfung ausstehend (Betrag >= auto_approve_threshold)

Fehler:
- 403 Auszahlungsfunktion vorübergehend deaktiviert (globaler Schalter aus)
- 400 unter dem Mindestauszahlungsbetrag
- 400 Tageslimit der Auszahlung überschritten
- 400 unzureichendes Guthaben

#### GET /api/v1/withdraw/orders — Auszahlungsverlauf

```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&per_page=20

Antwort: {
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

#### GET /api/v1/game/list — Spieleliste

```
Parameter: ?page=1&per_page=20&keyword=射击&type=self

Antwort: {
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

type Zulässige Werte: self / embedded / third_party

#### GET /api/v1/game/detail/{hashid} — Spieldetails

```
Antwort: {
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

#### POST /api/v1/game/launch — Spiel starten

```
Authentifizierung erforderlich: Ja

Anfrage: { "game_id": "aB3xK..." }

Antwort: {
  "id": "...",
  "name": "射击大师",
  "type": "self",
  "api_endpoint": "https://game.example.com/play"
}
```

### 2.7 OAuth-Login von Drittanbietern

Unterstützt 7 Plattformen: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/v1/auth/oauth/{provider} — Autorisierungs-URL abrufen

```
Parameter: provider = google / facebook / apple / twitter / microsoft / linkedin / github

Antwort: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/v1/auth/oauth/{provider}/callback — OAuth-Callback

```
Anfrage: { "code": "授权码", "state": "防CSRF状态" }

Antwort: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=neu registrierter Benutzer / false=vorhandenes Konto verknüpft

### 2.8 KYC-Identitätsprüfung

#### GET /api/v1/user/identity/status — Prüfstatus

```
Authentifizierung erforderlich: Ja

Antwort: {
  "status": "approved",          // not_submitted / pending / approved / rejected
  "real_name": "J***",
  "id_type": "id_card",
  "review_note": "",
  "submitted_at": "2026-05-22 10:00:00",
  "reviewed_at": "2026-05-23 14:00:00"
}
```

#### POST /api/v1/user/identity/apply — Identitätsprüfung einreichen

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}

Antwort: { "message": "KYC submitted successfully" }
```

### 2.9 Zahlungen

#### POST /api/v1/payment/callback — Zahlungs-Callback (öffentlich)

```
Anfrage: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

Antwort: { "message": "success" }
```

status: success / failed

provider-Werte: stripe / paypal / nowpayments / coinbase / skrill / neteller / paysafecard / paytm / mercadopago / astropay / paypay / kakaopay / gcash / mpesa / paystack / toss / adyen / grabpay

| provider | Region | Signaturverfahren | Unterstützte Währungen |
|----------|--------|-------------------|-------------------------|
| stripe | Global (125+ lokale Zahlungsmethoden, inkl. Alipay/WeChat Pay APM) | Webhook HMAC-SHA256 | USD / CNY / EUR |
| paypal | 200+ Märkte weltweit | Webhook-Verifizierung (verify-webhook-signature) | USD / CNY / EUR und andere Fiat |
| nowpayments | Global (Krypto) | IPN HMAC-SHA512 | USDT TRC20 / ERC20 |
| coinbase | Global (Krypto) | Webhook HMAC-SHA256 (base64 secret) | USDC / BTC / ETH |
| skrill | Europa / Global | Secret word MD5-Prüfung | EUR und andere Fiat |
| neteller | Europa / Global | Secret key Feldvergleich | EUR und andere Fiat |
| paysafecard | Europa (DE / AT / CH usw.) | X-Signature HMAC-SHA256 | EUR und andere Fiat |
| paytm | Indien | SHA256 + AES-128-CBC | INR |
| mercadopago | Lateinamerika (BR / MX usw.) | X-Signature (ts,v1) HMAC-SHA256 | BRL / MXN und andere Fiat |
| astropay | Lateinamerika (BR usw.) | MD5(order_id.amount.status.secret) | BRL und andere Fiat |
| paypay | Japan | PayPay-Signature HMAC-SHA256 | JPY |
| kakaopay | Südkorea | Kein Webhook (ready/approve zweistufig) | KRW |
| gcash | Philippinen | Paymongo-Signature HMAC-SHA256 | PHP |
| toss | Südkorea | Server-side verify + amount check | KRW |
| mpesa | Kenia | Trusted IP (CALLBACK_TRUSTED_IPS), no signature | KES |
| paystack | Nigeria | x-paystack-signature HMAC-SHA512 | NGN |
| adyen | Global (Währung je Auftrag) | additionalData.hmacSignature HMAC-SHA256 (ADYEN_HMAC_KEY) | je Auftrag |
| grabpay | Singapur (Land konfigurierbar, Standard SG) | x-signature HMAC-SHA256 (sorted key:value) | je Auftrag |

#### GET /api/v1/payment/methods — Verfügbare Zahlungsmethoden (öffentlich)

```
Antwort: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

Nach Benutzerland gefiltert (X-Language/Accept-Language → Ländercode-Zuordnung): leere countries oder mit * bedeutet weltweit sichtbar; sortiert nach der Zahlungsarten-Präferenz der country_config dieses Landes

### 2.10 Spielprotokolle

#### GET /api/v1/game/play-logs — Spielprotokoll-Liste

```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&per_page=20&game_id=xxx&action=start

Antwort: {
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

#### GET /api/v1/game/play-log/{hashid} — Spielprotokoll-Details

```
Authentifizierung erforderlich: Ja
Antwort: { 完整记录，含 session_id / game_amount_before / after 等 }
```

### 2.12 Ranglisten

#### GET /api/v1/leaderboard/list — Ranglistenliste

```
Antwort: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/v1/leaderboard/{hashid} — Ranglistendetails

```
Antwort: {
  "id": "...",
  "name": "全服累计收入榜",
  "type": "total",
  "rankings": [
    { "rank": 1, "user_id": "...", "score": "50000.0000" }
  ]
}
```

### 2.13 Gutscheine

#### GET /api/v1/coupon/available — Verfügbare Gutscheine

```
Authentifizierung erforderlich: Ja
Antwort: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/v1/coupon/claim — Gutschein einlösen

```
Authentifizierung erforderlich: Ja
Anfrage: { "coupon_id": "hashid" }
Antwort: { "coupon": { ... } }
```

#### GET /api/v1/coupon/my — Meine Gutscheine

```
Authentifizierung erforderlich: Ja
Parameter: ?status=unused
Antwort: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 Länderkonfiguration

#### GET /api/v1/country/list — Länderliste

```
Antwort: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/v1/country/{code} — Länderdetails

```
Antwort: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 Benachrichtigungen

#### GET /api/v1/notification/list — Benachrichtigungsliste

```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&per_page=20&is_read=0

Antwort: {
  "list": [
    { "id": "...", "type": "deposit", "title": "Deposit Received", "is_read": 0, "created_at": "..." }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### GET /api/v1/notification/unread-count — Ungelesene Anzahl

```
Authentifizierung erforderlich: Ja
Antwort: { "count": 3 }
```

#### POST /api/v1/notification/read — Als gelesen markieren

```
Authentifizierung erforderlich: Ja
Anfrage: { "id": "hashid" }  // 不传=全部已读
```

### 2.17 Empfehlungen

#### GET /api/v1/referral/my-code — Mein Empfehlungscode

```
Authentifizierung erforderlich: Ja
Antwort: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/v1/referral/apply — Empfehlungscode verwenden

```
Authentifizierung erforderlich: Ja
Anfrage: { "code": "ABC12345" }
Antwort: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/v1/user/2fa/status — 2FA-Status

```
Authentifizierung erforderlich: Ja
Antwort: { "enabled": false }
```

#### POST /api/v1/user/2fa/setup — 2FA einrichten

```
Authentifizierung erforderlich: Ja
Antwort: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/v1/user/2fa/enable — 2FA aktivieren

```
Authentifizierung erforderlich: Ja
Anfrage: { "code": "123456" }
Antwort: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/v1/2fa/verify — 2FA verifizieren (öffentlich)

```
Anfrage: { "user_id": "hashid", "code": "123456" }
Antwort: { "valid": true }
```

### 2.19 Suche

#### GET /api/v1/search — Globale Suche

```
Parameter: ?q=keyword&type=game&page=1&per_page=20
Antwort: { "list": [...], "total": 100 }
```

#### GET /api/v1/game/suggest — Suchvorschläge

```
Parameter: ?q=shoot
Antwort: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 Sprachen

#### GET /api/v1/language/list — Verfügbare Sprachen

```
Antwort: {
  "current": "en-US",
  "languages": {
    "en-US": { "name": "English", "nativeName": "English", "icon": "us" },
    "zh-CN": { "name": "Chinese (Simplified)", "nativeName": "简体中文", "icon": "cn" },
    "ja-JP": { "name": "Japanese", "nativeName": "日本語", "icon": "jp" },
    "ko-KR": { "name": "Korean", "nativeName": "한국어", "icon": "kr" }
  }
}
```

#### POST /api/v1/language/switch — Sprache wechseln

```
Anfrage: { "locale": "zh-CN" }
Antwort: { "locale": "zh-CN" }
```

locale Zulässige Werte: en-US / zh-CN / ja-JP / ko-KR

### 2.8 Benutzer

#### GET /api/v1/user/profile — Persönliche Informationen

```
Authentifizierung erforderlich: Ja

Antwort: {
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

#### PUT /api/v1/user/profile — Profil bearbeiten

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}

Antwort: {
  "id": "...",
  "username": "player1",
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}
```

language Zulässige Werte: en-US / zh-CN / ja-JP / ko-KR

### 2.9 Ankündigungen

#### GET /api/v1/announcement/list — Ankündigungsliste

```
Antwort: {
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

#### GET /api/v1/announcement/detail/{hashid} — Ankündigungsdetails

```
Antwort: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

### 2.21 Plattform-Statistik

| Methode | Pfad | Beschreibung | Authentifizierung |
|------|------|------|------|
| GET | /api/v1/platform/stats | Öffentliche Plattform-Statistik (Spiele gesamt/User gesamt/Spiele heute/7-Tage-aktive Nutzer) | nein |

#### GET /api/v1/platform/stats — Plattform-Statistik

```
无需认证

Antwort: {
  "total_games": 12,
  "total_users": 1500,
  "today_game_plays": 320,
  "active_users_7d": 450
}
```

## 3. Verwaltungsbackend-Schnittstellen (admin :8789)

### 3.1 Plattform-Dashboard

#### GET /admin/v1/dashboard/platform

```
Authentifizierung erforderlich: Ja (AdminAuth + AdminPermission)

Antwort: {
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

#### GET /admin/v1/game/list — Spieleliste

```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&limit=20&keyword=射击

Antwort: {
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

#### GET /admin/v1/game/{hashid} — Spieldetails

```
Authentifizierung erforderlich: Ja
Parameter: hashid 为游戏的 hashid 编码（路径参数）

Antwort: {
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

Gibt code 404 zurück, wenn das Spiel nicht existiert.

#### POST /admin/v1/game/launch — Spielvorschau

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "game_id": "aB3xK..."      // 游戏 ID(hashid)
}

Antwort: {
  "id": "aB3xK...",
  "name": "射击大师",
  "slug": "shooter-master",
  "type": "self",
  "api_endpoint": "https://...",
  "preview": true
}
```

Fehlt `game_id`, wird code 422 zurückgegeben; ist das Spiel nicht vorhanden, 404; ist es nicht freigeschaltet (`status` nicht 1), 403.

Die Admin-Vorschau ist eine reine Vorschau: Sie prüft nur die Verfügbarkeit des Spiels und gibt die Startinformationen zurück und **schreibt keine Spielaufzeichnungen und berührt keine Wallet**. Admin-Identitäten tragen nur `adminId` (von `AdminAuth` injiziert) und keine `userId` der C-End-Seite, daher führt dieser Endpunkt bewusst keine benutzerseitigen Schreibvorgänge aus — eine Übernahme des C-End-`POST /api/v1/game/launch` würde `game_game_play_log`-Einträge mit falscher Zuordnung schreiben.

#### POST /admin/v1/game/create — Spiel erstellen

```
Authentifizierung erforderlich: Ja

Anfrage: {
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

Antwort: { "id": "aB3xK..." }
```

type Zulässige Werte: self / embedded / third_party

#### PUT /admin/v1/game/{hashid} — Spiel bearbeiten

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "name": "新名称",
  "status": 1
  // 可部分更新，字段同 create
}

Antwort: { "message": "更新成功" }
```

#### DELETE /admin/v1/game/{hashid} — Spiel löschen

```
Authentifizierung erforderlich: Ja
Antwort: { "message": "删除成功" }
```

#### POST /admin/v1/game/currency/manage — Währungen verwalten

```
Authentifizierung erforderlich: Ja

Anfrage: {
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

Antwort: { "message": "操作成功" }
```

Fehlt `game_id` oder ist `currencies` kein Array, wird 422 zurückgegeben; ist das Spiel nicht vorhanden, 404.

Nur bei Übergabe werden `exchange_rate` und `spread_pct` validiert: `exchange_rate` muss eine Zahl größer als 0 sein, `spread_pct` muss im Bereich [0, 100) liegen; ein Verstoß gegen eine der beiden Regeln gibt 422 zurück, und keine der übergebenen Währungen wird geschrieben (erst vollständige Validierung, dann Schreiben). Nicht übergebene Felder lösen keine Validierung aus: beim Anlegen gelten die Standardwerte (`exchange_rate` = `1.00000000`, die übrigen `0.00000000`), beim Aktualisieren bleibt der bisherige Wert erhalten.

### 3.3 Auszahlungsverwaltung

#### GET /admin/v1/withdraw/orders — Auszahlungsauftragsliste

```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&limit=20&status=pending

Antwort: {
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

#### PUT /admin/v1/withdraw/review — Auszahlung prüfen

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

Antwort: { "message": "已通过" }
```

action: approve=genehmigen / reject=ablehnen / confirm=bestätigen (bei Ablehnung wird die Plattformwährung automatisch zurückgebucht)

Fehler: 422 Auftragsstatus ist nicht "Prüfung ausstehend"

#### PUT /admin/v1/withdraw/switch — Globaler Auszahlungsschalter

```
Authentifizierung erforderlich: Ja

Anfrage: { "enabled": 1 }

Antwort: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/v1/withdraw/limits/set — Auszahlungslimits festlegen

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "daily_limit": "10000.0000",             // 可选
  "min_amount": "1.0000",                  // 可选
  "auto_approve_threshold": "100.0000"     // 可选
}

Antwort: {
  "daily_limit": "10000.0000",
  "min_amount": "1.0000",
  "auto_approve_threshold": "100.0000",
  "global_switch": true
}
```

#### POST /admin/v1/withdraw/batch-review — Sammelprüfung von Auszahlungen

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "ids": ["aB3xK...", "cD4yL..."],
  "action": "approve",
  "note": "批量审核通过"
}

Antwort: {
  "processed": 2,
  "failed": []
}
```

action: approve=genehmigen / reject=ablehnen (Verarbeitung pro Auftrag; abgelehnte Aufträge werden automatisch erstattet; Fehler landen in failed und blockieren die übrigen nicht)

#### POST /admin/v1/withdraw/execute-payout — Auszahlung ausführen

```
Authentifizierung erforderlich: Ja

Anfrage: { "order_id": "aB3xK..." }

Antwort: {
  "payout_batch_id": "PAYOUT-123456",
  "payout_item_id": "ITEM-123456",
  "payout_status": "success",
  "payout_attempts": 1
}
```

Auszahlung nur im Status approved möglich (atomare Umschaltung auf processing); ein wiederholter Aufruf liefert 422. Bei aktivierter Doppelprüfung muss der Auftrag zuvor von einer zweiten Person bestätigt werden

#### POST /admin/v1/withdraw/sync-payout — Auszahlungsstatus synchronisieren

```
Authentifizierung erforderlich: Ja

Anfrage: { "order_id": "aB3xK..." }

Antwort: {
  "payout_status": "success",
  "order_status": "completed",
  "synced_status": "success"
}
```

Fehler: 422 Für diesen Auftrag wurde noch keine Auszahlung ausgeführt

### 3.4 Plattform-Benutzerverwaltung

#### GET /admin/v1/platform/user/list — C-End-Benutzerliste

```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&limit=20&keyword=player&status=1

Antwort: {
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

#### GET /admin/v1/platform/user/{hashid} — Benutzerdetails

```
Authentifizierung erforderlich: Ja

Antwort: {
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

#### PUT /admin/v1/platform/user/{hashid} — Benutzer bearbeiten/sperren

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "status": 0,         // 0=禁用 1=启用
  "nickname": "..."    // 可选
}

Antwort: { "message": "更新成功" }
```

### 3.5 Zahlungsverwaltung

#### GET /admin/v1/payment/method/list

```
Authentifizierung erforderlich: Ja

Antwort: {
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

#### POST /admin/v1/payment/method/toggle — Zahlungsmethode aktivieren/deaktivieren

```
Authentifizierung erforderlich: Ja

Anfrage: { "id": "aB3xK...", "status": 0 }

Antwort: { "message": "已更新" }
```

### 3.6 Ankündigungsverwaltung

#### GET /admin/v1/announcement/list

```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&limit=20

Antwort: {
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

#### POST /admin/v1/announcement/create — Ankündigung veröffentlichen

```
Authentifizierung erforderlich: Ja

Anfrage: {
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护。",
  "type": "system",           // 可选, 默认"system"
  "target_lang": "",          // 可选, 空=全语言
  "status": 1,                // 可选, 默认1 (0=草稿 1=发布)
  "start_at": "2026-05-23 02:00:00",  // 可选
  "end_at": "2026-05-23 04:00:00"     // 可选
}

Antwort: { "id": "aB3xK..." }
```

### 3.7 KYC-Prüfung

#### GET /admin/v1/identity/list — KYC-Liste

```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&limit=20&status=pending

Antwort: {
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

#### PUT /admin/v1/identity/review — KYC prüfen

```
Authentifizierung erforderlich: Ja

Anfrage: { "id": "hashid", "action": "approve", "note": "" }

Antwort: { "message": "Approved" }
```

action: approve / reject

### 3.8 Spielserver-Verwaltung

#### GET /admin/v1/game/server/list — Serverliste

```
Authentifizierung erforderlich: Ja
Parameter: ?game_id=hashid

Antwort: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/v1/game/server/create — Server erstellen

```
Authentifizierung erforderlich: Ja
Anfrage: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
Antwort: { "id": "hashid" }
```

#### PUT /admin/v1/game/server/{hashid} — Server bearbeiten

```
Authentifizierung erforderlich: Ja
Anfrage: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/v1/game/server/{hashid} — Server löschen

```
Authentifizierung erforderlich: Ja
```

### 3.9 Auszahlungs-Stufenlimit-Verwaltung

#### GET /admin/v1/withdraw/limits/list

```
Authentifizierung erforderlich: Ja

Antwort: {
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

#### PUT /admin/v1/withdraw/limits/{hashid} — Limit aktualisieren

```
Authentifizierung erforderlich: Ja

Anfrage: { "single_max": "10000.0000", "fee_pct": "0.25" }
// 可部分更新
```

### 3.11 Spielkategorie-Verwaltung

#### GET /admin/v1/game/category/list

```
Authentifizierung erforderlich: Ja
Antwort: { "list": [{ "id": "...", "name": "动作", "slug": "action", "sort": 1 }] }
```

#### POST /admin/v1/game/category/create

```
Authentifizierung erforderlich: Ja
Anfrage: { "name": "新分类", "slug": "new-cat", "icon": "star", "sort": 10 }
Antwort: { "id": "hashid" }
```

#### PUT /admin/v1/game/category/{hashid} — Kategorie bearbeiten

#### DELETE /admin/v1/game/category/{hashid} — Kategorie löschen

#### POST /admin/v1/game/category/assign — Spiele zuweisen

```
Authentifizierung erforderlich: Ja
Anfrage: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 Ranglisten-Verwaltung

#### GET /admin/v1/leaderboard/list — Ranglistenliste

```
Authentifizierung erforderlich: Ja
Antwort: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/v1/leaderboard/create — Rangliste erstellen

```
Authentifizierung erforderlich: Ja
Anfrage: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(可选)" }
```

#### PUT /admin/v1/leaderboard/{hashid} — Rangliste bearbeiten

#### DELETE /admin/v1/leaderboard/{hashid} — Rangliste löschen

#### POST /admin/v1/leaderboard/{hashid}/refresh — Cache aktualisieren

### 3.13 Gutscheinverwaltung

#### GET /admin/v1/coupon/list — Gutscheinliste

#### POST /admin/v1/coupon/create — Gutschein erstellen

```
Authentifizierung erforderlich: Ja
Anfrage: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/v1/coupon/{hashid} — Bearbeiten (nur wenn nicht eingelöst)

#### DELETE /admin/v1/coupon/{hashid} — Löschen

#### GET /admin/v1/coupon/{hashid}/stats — Einlösungsstatistik

```
Antwort: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 Länderkonfigurations-Verwaltung

#### GET /admin/v1/country/config/list — Länderkonfigurationsliste

#### POST /admin/v1/country/config/create — Länderkonfiguration erstellen

```
Authentifizierung erforderlich: Ja
Anfrage: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/v1/country/config/{hashid} — Länderkonfiguration bearbeiten

### 3.15 Datencxport

#### POST /admin/v1/export/users — C-End-Benutzer exportieren

```
Authentifizierung erforderlich: Ja
Parameter (JSON): { "status": 1 }   // 可选筛选

Antwort: Excel 文件下载 (xlsx)
```

#### POST /admin/v1/export/transactions — Plattformtransaktionen exportieren

```
Authentifizierung erforderlich: Ja
Parameter (JSON): { "type": "deposit" }   // 可选筛选

Antwort: Excel 文件下载 (xlsx)
```

### 3.16 Datenanalyse (MySQL-Echtzeitaggregation)

Alle Endpunkte erfordern Authentifizierung (AdminAuth + AdminPermission); die Daten werden in Echtzeit aus MySQL aggregiert, ohne Abhängigkeit von ClickHouse.

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/analytics/overview | Plattform-Überblick (heute/letzte 7 Tage) |
| GET | /admin/v1/analytics/game-ranking | Spiel-Ranking (?days=7) |
| GET | /admin/v1/analytics/dau-trend | DAU-Trend (?days=30) |
| GET | /admin/v1/analytics/hourly-trend | Stunden-Trend |
| GET | /admin/v1/analytics/action-distribution | Verhaltensverteilung |
| GET | /admin/v1/analytics/revenue | Umsatzanalyse |
| GET | /admin/v1/analytics/conversion | Spiel-Konversionsrate |
| GET | /admin/v1/analytics/probability | Gemeinsame/bedingte Wahrscheinlichkeit |
| GET | /admin/v1/analytics/retention | Retentionsanalyse D1/D3/D7/D30 |
| GET | /admin/v1/analytics/funnel | Konversions-Trichter |
| GET | /admin/v1/analytics/arpu | ARPU/ARPPU-Trend |
| GET | /admin/v1/analytics/economy | Wirtschaftskennzahlen der Spielwährungen |

### 3.17 Ticket-Verwaltung

Alle Endpunkte erfordern Authentifizierung (AdminAuth + AdminPermission).

| Methode | Pfad | Beschreibung |
|------|------|------|
| GET | /admin/v1/ticket/list | Ticketliste (?page=&limit=&status=&type=) |
| GET | /admin/v1/ticket/{hashid} | Ticketdetails (inkl. Antworten) |
| POST | /admin/v1/ticket/{hashid}/reply | Ticket beantworten |
| POST | /admin/v1/ticket/{hashid}/close | Ticket schließen |
| POST | /admin/v1/ticket/{hashid}/assign | Bearbeiter zuweisen (admin_id) |

### 3.18 CDN-Konfigurationsverwaltung

Alle Endpunkte erfordern Authentifizierung (AdminAuth + AdminPermission).

| Methode | Pfad | Beschreibung | Authentifizierung |
|------|------|------|------|
| GET | /admin/v1/cdn/provider/list | CDN-Anbieter auflisten (Anmeldedaten werden nicht zurückgegeben) | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/toggle | Anbieter aktivieren/deaktivieren {id, status} | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/create | Anlegen {name, provider, config(JSON), status, sort}, Eindeutigkeitsprüfung von provider | AdminAuth + RBAC: cdn |
| PUT | /admin/v1/cdn/provider/{hashid} | Bearbeiten (leerer config = unverändert) | AdminAuth + RBAC: cdn |
| DELETE | /admin/v1/cdn/provider/{hashid} | Löschen | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/test | Verbindungstest HeadBucket {id} | AdminAuth + RBAC: cdn |

### 3.19 Datenberichte

Alle Endpunkte erfordern Authentifizierung (AdminAuth + AdminPermission).

| Methode | Pfad | Beschreibung | Authentifizierung |
|------|------|------|------|
| GET | /admin/v1/report/summary | Berichtszusammenfassung (neue Nutzer/Einzahlungen/Auszahlungen/Umrechnungen/Spiele) | AdminAuth + RBAC: report |
| GET | /admin/v1/report/daily | Tagesbericht (tägliche Aggregation, leere Tage mit 0 aufgefüllt) | AdminAuth + RBAC: report |
| GET | /admin/v1/report/export | Tagesbericht als CSV exportieren (UTF-8 BOM) | AdminAuth + RBAC: report |

## 4. Ratenbegrenzungsstrategie

| Schnittstelle | Limit |
|------|------|
| Standard | 60 Anfragen/Minute/IP |
| POST /api/v1/auth/login | 10 Anfragen/Minute |
| POST /api/v1/auth/register | 5 Anfragen/Minute |

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
Anfrage-Header:
  X-Game-Id: 1234567890
  X-Timestamp: 1716400830
  X-Signature: abc123...

Anfrage: {
  "user_id": 1234567890,
  "game_id": 9876543210,
  "currency_id": 5555555555
}

Antwort: {
  "code": 0,
  "message": "success",
  "data": { "balance": "1000.50000000" }
}
```

#### POST /api/provider/bet — Einsatz melden

```
Anfrage: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "bet_type": "straight" }
}

Antwort: {
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
Anfrage: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}

Antwort: {
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
Anfrage: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "reason": "game_crash"
}

Antwort: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1000.50000000"
  }
}
```

### 7.2 Ticket-API

#### GET /api/v1/ticket/list — Ticketliste

```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&per_page=20

Antwort: {
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

#### POST /api/v1/ticket/create — Ticket erstellen

```
Authentifizierung erforderlich: Ja
Anfrage: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
Antwort: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/v1/ticket/{hashid} — Ticketdetails

```
Authentifizierung erforderlich: Ja
Antwort: {
  "id": "...", "type": "deposit", "subject": "...",
  "content": "...", "status": "open",
  "replies": [
    { "id": "...", "content": "...", "is_admin": 1, "created_at": "..." }
  ]
}
```

#### POST /api/v1/ticket/{hashid}/reply — Ticket beantworten

```
Authentifizierung erforderlich: Ja
Anfrage: { "content": "已核实，将在24小时内处理" }
Antwort: { "code": 0, "message": "Reply sent" }
```

### 7.3 E-Mail-Verifizierungs-API

#### POST /api/v1/verify/send-email — E-Mail-Verifizierungscode senden

```
Authentifizierung erforderlich: Ja
Anfrage: { "email": "user@example.com" }
Antwort: { "code": 0, "message": "Verification code sent" }
Fehler: 429 请60秒后重试
```

#### POST /api/v1/verify/confirm-email — E-Mail bestätigen

```
Authentifizierung erforderlich: Ja
Anfrage: { "code": "123456" }
Antwort: { "code": 0, "message": "Email verified" }
Fehler: 422 验证码无效或已过期
```

### 7.4 VIP-API

#### GET /api/v1/user/vip-status — VIP-Status

> **Nicht implementiert**: Die C-End-Route ist nicht registriert (kein Eintrag in `service/config/route.php`), Anfragen liefern derzeit 404. Diese Zeile nach der Implementierung löschen.

```
Authentifizierung erforderlich: Ja
Antwort: {
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

#### GET /api/v1/user/achievements — Errungenschaftsliste

> **Nicht implementiert**: Die C-End-Route ist nicht registriert (kein Eintrag in `service/config/route.php`), Anfragen liefern derzeit 404. Diese Zeile nach der Implementierung löschen.

```
Authentifizierung erforderlich: Ja
Antwort: {
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

#### GET /admin/v1/ticket/list — Ticketliste

```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&limit=20&status=pending&type=deposit

Antwort: {
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

#### POST /admin/v1/ticket/{hashid}/reply — Ticket beantworten

```
Authentifizierung erforderlich: Ja
Anfrage: { "content": "已处理" }
Antwort: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/v1/ticket/{hashid}/close — Ticket schließen

```
Authentifizierung erforderlich: Ja
Antwort: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/v1/ticket/{hashid}/assign — Bearbeiter zuweisen

```
Authentifizierung erforderlich: Ja
Anfrage: { "admin_id": 1234567890 }
Antwort: { "code": 0, "message": "Assigned" }
```

#### GET /admin/v1/analytics/retention — Retentionsanalyse

```
Authentifizierung erforderlich: Ja
Parameter: ?days=30
Antwort: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/v1/analytics/funnel — Konversions-Trichter

```
Authentifizierung erforderlich: Ja
Antwort: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/v1/analytics/arpu — ARPU/ARPPU-Trend

```
Authentifizierung erforderlich: Ja
Parameter: ?days=30
Antwort: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/v1/analytics/economy — Wirtschaftskennzahlen der Spielwährungen

```
Authentifizierung erforderlich: Ja
Antwort: {
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


#### GET /admin/v1/cdn/provider/list — CDN-Anbieter auflisten (Anmeldedaten werden nicht zurückgegeben)

```
Authentifizierung erforderlich: Ja
Antwort: { "list": [ { "id": "...", "name": "...", "provider": "cloudflare", "status": 1, "sort": 0 } ] }
```

#### POST /admin/v1/cdn/provider/toggle — Anbieter aktivieren/deaktivieren {id, status}

```
Authentifizierung erforderlich: Ja
Anfrage: { "id": "...", "status": 1 }
Antwort: { "code": 0, "message": "..." }
```

#### POST /admin/v1/cdn/provider/create — Anlegen {name, provider, config(JSON), status, sort}, Eindeutigkeitsprüfung von provider

```
Authentifizierung erforderlich: Ja
Anfrage: { "name": "...", "provider": "aliyun", "config": "{...}", "status": 1, "sort": 0 }
Antwort: { "code": 0, "data": { "id": "..." } }
```

#### PUT /admin/v1/cdn/provider/{hashid} — Bearbeiten (leerer config = unverändert)

```
Authentifizierung erforderlich: Ja
Anfrage: { "name": "...", "config": "" }
Antwort: { "code": 0, "message": "..." }
```

#### DELETE /admin/v1/cdn/provider/{hashid} — Löschen

```
Authentifizierung erforderlich: Ja
Antwort: { "code": 0, "message": "..." }
```

#### POST /admin/v1/cdn/provider/test — Verbindungstest HeadBucket {id}

```
Authentifizierung erforderlich: Ja
Anfrage: { "id": "..." }
Antwort: { "code": 0, "data": { "ok": true } }
```
#### GET /admin/v1/report/summary — Berichtszusammenfassung

```
Authentifizierung erforderlich: Ja
Parameter: ?start=Y-m-d&end=Y-m-d (缺省最近30天，跨度 ≤90 天，Redis 缓存5分钟)
Antwort: {
  "start": "2026-08-01", "end": "2026-08-31",
  "new_users": 120, "deposit_amount": "5000.0000", "deposit_count": 45,
  "withdraw_amount": "1200.0000", "withdraw_count": 8,
  "exchange_amount": "3000.0000", "play_count": 1500
}
```


#### GET /admin/v1/report/daily — Tagesbericht

```
Authentifizierung erforderlich: Ja
Parameter: ?start=Y-m-d&end=Y-m-d
Antwort: {
  "start": "2026-08-01", "end": "2026-08-31",
  "rows": [ { "date": "2026-08-01", "new_users": 12, "deposit_amount": "500.0000", "deposit_count": 4, "withdraw_amount": "100.0000", "withdraw_count": 1, "exchange_amount": "300.0000", "play_count": 150 } ]
}
```


#### GET /admin/v1/report/export — Tagesbericht-CSV-Export

```
Authentifizierung erforderlich: Ja
Parameter: ?start=Y-m-d&end=Y-m-d&format=excel
Antwort: CSV 文件（UTF-8 BOM），文件名 report_{start}_{end}.csv，Excel 可直接打开
```

## 8. Ratenbegrenzungsstrategie (aktualisiert)

| Schnittstelle | Limit |
|------|------|
| Standard | 60 Anfragen/Minute/IP |
| POST /api/v1/auth/login | 10 Anfragen/Minute |
| POST /api/v1/auth/register | 5 Anfragen/Minute |
| POST /api/v1/auth/oauth | 10 Anfragen/Minute |
| POST /api/v1/payment/callback | 30 Anfragen/Minute |
| POST /api/provider/* | unbegrenzt (HMAC-Signaturauthentifizierung) |

## 9. Authentifizierungshinweise (aktualisiert)

### Provider-Authentifizierung (ProviderAuth)

1. `X-Game-Id`, `X-Timestamp`, `X-Signature` aus den Anfrage-Headern extrahieren
2. `game_game`-Tabelle abfragen, um zu prüfen, dass das Spiel existiert und status=1
3. Prüfen, dass der Zeitstempel im 5-Minuten-Fenster liegt (gegen Replay)
4. `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` berechnen und mit der Signatur vergleichen
5. `$request->gameId` und `$request->game` injizieren


### 7.7 Freunde-API

#### GET /api/v1/friend/list — Freundesliste
```
Authentifizierung erforderlich: Ja
Antwort: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/v1/friend/requests — Ausstehende Anfragen
```
Authentifizierung erforderlich: Ja
Antwort: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/v1/friend/request — Freundschaftsanfrage senden
```
Authentifizierung erforderlich: Ja
Anfrage: { "friend_id": "hashid" }
```

#### POST /api/v1/friend/accept — Anfrage annehmen
```
Authentifizierung erforderlich: Ja
Anfrage: { "request_id": "hashid" }
```

#### POST /api/v1/friend/reject — Anfrage ablehnen
```
Authentifizierung erforderlich: Ja
Anfrage: { "request_id": "hashid" }
```

#### POST /api/v1/friend/remove — Freund entfernen
```
Authentifizierung erforderlich: Ja
Anfrage: { "friend_id": "hashid" }
```

#### GET /api/v1/friend/search — Benutzer suchen
```
Authentifizierung erforderlich: Ja
Parameter: ?q=username
Antwort: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 Chat-API

#### GET /api/v1/chat/conversations — Konversationsliste
```
Authentifizierung erforderlich: Ja
Antwort: {
  "list": [{
    "peer": { "id": "...", "username": "...", "nickname": "...", "avatar": "..." },
    "last_message": "最近一条消息",
    "unread_count": 3,
    "updated_at": "2026-05-22 10:30:00"
  }]
}
```

#### GET /api/v1/chat/messages/{peerHashid} — Nachrichtenliste
```
Authentifizierung erforderlich: Ja
Parameter: ?page=1&per_page=50
Antwort: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
自动标记对端发来的未读消息为已读
```

#### POST /api/v1/chat/send — Nachricht senden
```
Authentifizierung erforderlich: Ja
Anfrage: { "to_user_id": "hashid", "content": "Hello!" }
Fehler: 403 非好友不可发
```

#### GET /api/v1/chat/unread-total — Ungelesene Gesamtzahl
```
Authentifizierung erforderlich: Ja
Antwort: { "count": 5 }
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

#### GET /api/v1/webhook/list — Abonnementliste
```
Authentifizierung erforderlich: Ja
Antwort: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/v1/webhook/register — Abonnement registrieren
```
Authentifizierung erforderlich: Ja
Anfrage: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
Verfügbare Ereignisse: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/v1/webhook/delete — Abonnement löschen
```
Authentifizierung erforderlich: Ja
Anfrage: { "id": "hook_id" }
```

### 7.10 Erweiterte Analyse-APIs

#### GET /admin/v1/analytics/retention — Retentionsanalyse
```
Authentifizierung erforderlich: Ja
Antwort: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/v1/analytics/funnel — Konversions-Trichter
```
Authentifizierung erforderlich: Ja
Antwort: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/v1/analytics/arpu — ARPU/ARPPU-Trend
```
Authentifizierung erforderlich: Ja
Parameter: ?days=30
Antwort: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/v1/analytics/economy — Spiel-Wirtschaftskennzahlen
```
Authentifizierung erforderlich: Ja
Antwort: {
  "currencies": [{
    "game_name": "Shooter Master", "currency": "Gold", "symbol": "G",
    "total_minted": "500000.00000000", "total_burned": "320000.00000000",
    "circulation": "180000.00000000", "inflation_rate": "36.00%"
  }]
}
```


### 7.11 Turnier-API

#### GET /api/v1/tournament/list — Turnierliste
```
Parameter: ?status=active|upcoming|ended&page=1&per_page=20
Antwort: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/v1/tournament/{hashid} — Turnierdetails
```
Antwort: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/v1/tournament/{hashid}/join — Teilnahme anmelden
```
Authentifizierung erforderlich: Ja
Fehler: 422 已报名 / 400 已开始或已满员 / 503 FeatureFlag关闭
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
| POST /api/v1/tournament/{id}/join | 10 Anfragen/Minute |

---

## 10. Neue APIs (v1.3.15-v1.3.22)

### 10.1 Risikomanagement (Admin :8789)

| Endpunkt | Beschreibung |
|------|------|
| GET /admin/v1/risk/dashboard | Risiko-Dashboard-Übersicht |
| GET /admin/v1/risk/overview | Risiko-Übersichtsmetriken |
| GET /admin/v1/risk/hit-trend | Treffer-Trend |
| GET /admin/v1/risk/action-distribution | Maßnahmenverteilung |
| GET /admin/v1/risk/rule-performance | Regelleistung |
| GET /admin/v1/risk/rule/list | Regelliste |
| POST /admin/v1/risk/rule/create | Regel erstellen |
| PUT /admin/v1/risk/rule/{hashid} | Regel aktualisieren |
| POST /admin/v1/risk/rule/{hashid}/toggle | Regel aktivieren/deaktivieren |
| POST /admin/v1/risk/rule/test | Regel testen |
| GET /admin/v1/risk/event/list | Risikoereignisliste |
| GET /admin/v1/risk/event/{hashid} | Ereignisdetails |
| POST /admin/v1/risk/event/{hashid}/handle | Ereignis bearbeiten |
| GET /admin/v1/risk/device/list | Geräte-Fingerprint-Liste |
| POST /admin/v1/risk/device/block | Gerät sperren |
| POST /admin/v1/risk/device/unblock | Gerät entsperren |
| GET /admin/v1/risk/ip/list | IP-Liste |
| POST /admin/v1/risk/ip/block | IP sperren |
| POST /admin/v1/risk/ip/whitelist | IP-Whitelist |
| POST /admin/v1/risk/ip/appeal | IP-Einspruch |
| POST /admin/v1/risk/ip/recheck | IP-Nachprüfung |
| GET /admin/v1/risk/graph/clusters | Clusterliste |
| GET /admin/v1/risk/graph/{userId} | Benutzer-Verknüpfungsgraph |
| GET /admin/v1/risk/clusters | Risikoclusterliste |
| POST /admin/v1/risk/clusters/detect | Cluster-Erkennung (gleiche IP mit ≥5 Konten / gleicher Geräte-Fingerprint mit ≥3 Konten in den letzten 7 Tagen; nur Kandidaten, keine Speicherung) |
| POST /admin/v1/risk/clusters/confirm | Cluster manuell bestätigen und speichern |
| GET /admin/v1/risk/clusters/{hashid}/members | Mitgliederliste des Clusters (Mitglieder aus dem Fingerprint aufgelöst) |
| PUT /admin/v1/risk/clusters/{hashid}/status | Cluster-Status aktualisieren (1=Beobachtung 2=erledigt 0=Fehlalarm) |
| GET /admin/v1/risk/users | Warteschlange auffälliger Benutzer (Filter nach Vertrauenswert und letztem Treffer) |
| GET /admin/v1/risk/users/{hashid}/timeline | Risiko-Zeitachse des Benutzers (Risiko-/Spiel-/Anti-Cheat-Ereignisse zusammengeführt) |
| POST /admin/v1/risk/users/{hashid}/hold | Verfügbares Plattform-Guthaben des Benutzers einfrieren und protokollieren |

### 10.2 Anti-Cheat-Verwaltung (Admin :8789)

| Endpunkt | Beschreibung |
|------|------|
| GET /admin/v1/anticheat/events | Anti-Cheat-Ereignisliste |
| GET /admin/v1/anticheat/events/{hashid} | Ereignisdetails |
| POST /admin/v1/anticheat/events/{hashid}/review | Ereignis prüfen |

### 10.3 Aktionen (Admin :8789 + Client :8792)

| Endpunkt | Beschreibung |
|------|------|
| GET /admin/v1/activities/list | Aktionsliste (Admin) |
| POST /admin/v1/activities/create | Aktion erstellen (Admin) |
| PUT /admin/v1/activities/{hashid} | Aktion aktualisieren (Admin) |
| DELETE /admin/v1/activities/{hashid} | Aktion löschen (Admin) |
| GET /api/v1/activities/list | Aktionsliste (Client) |
| GET /api/v1/activities/progress | Teilnahmefortschritt (Client) |
| GET /api/v1/activities/{hashid} | Aktionsdetails (Client) |
| POST /api/v1/activities/{hashid}/checkin | Check-in (Client) |

### 10.4 Gruppen / Teilen (Client :8792 + Admin :8789)

| Endpunkt | Beschreibung |
|------|------|
| POST /api/v1/groups | Gruppe erstellen |
| GET /api/v1/groups/{hashid} | Gruppendetails |
| GET /api/v1/groups/{hashid}/members | Mitgliederliste |
| POST /api/v1/groups/{hashid}/join | Gruppe beitreten |
| POST /api/v1/groups/{hashid}/leave | Gruppe verlassen |
| PUT /api/v1/groups/{hashid}/role | Mitgliederrolle |
| POST /api/v1/shares | Teilen-Link erstellen |
| POST /api/v1/shares/visit | Teilen-Zugriffsverfolgung |
| GET /admin/v1/groups | Gruppenliste (Admin) |
| GET /admin/v1/groups/{hashid}/audit | Gruppenprüfung (Admin) |
| GET /admin/v1/share/stats | Teilen-Statistik (Admin) |

### 10.5 Zahlungs-Gateway-Erweiterungen (L1)

| Gateway | Beschreibung |
|------|------|
| Adyen | Neues Zahlungs-Gateway (Einzahlung / Callback-Verifizierung / automatische Gutschrift) |
| GrabPay | Neues Zahlungs-Gateway (Einzahlung / Callback-Verifizierung / automatische Gutschrift) |

### 10.6 VIP / Erfolge / Suche / Belege (Admin :8789)

VIP-Stufen, Erfolgskonfiguration, globale Suche und Belegexport (Admin).

| Endpunkt | Beschreibung |
|------|------|
| GET /admin/v1/vip/level/list | VIP-Stufenliste |
| POST /admin/v1/vip/level/create | VIP-Stufe erstellen (level muss eindeutig sein) |
| PUT /admin/v1/vip/level/{hashid} | VIP-Stufe aktualisieren |
| DELETE /admin/v1/vip/level/{hashid} | VIP-Stufe löschen (abgelehnt, solange Benutzer diese Stufe haben) |
| GET /admin/v1/achievement/list | Erfolgsliste |
| POST /admin/v1/achievement/create | Erfolg erstellen (doppelter key wird abgelehnt) |
| PUT /admin/v1/achievement/{hashid} | Erfolg aktualisieren |
| DELETE /admin/v1/achievement/{hashid} | Erfolg löschen |
| GET /admin/v1/search | Globale Suche (?q= Suchbegriff, type=game oder user) |
| POST /admin/v1/export/receipt | Beleg als PDF exportieren (type=deposit oder withdraw plus order_id) |
