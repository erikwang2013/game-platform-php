# Documentation des interfaces
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · **Français** · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Documentation interactive en ligne (avec débogage en ligne) :
- Métier côté C : http://localhost:8792/apidoc/
- Administration : http://localhost:8789/apidoc/
- Mot de passe : voir la configuration `APIDOC_PASSWORD` de l'environnement de déploiement

## 1. Conventions

### 1.1 URL de base

| Extrémité | Adresse |
|----|------|
| Administration | `http://localhost:8789` |
| Métier côté C | `http://localhost:8792` |

### 1.2 En-têtes de requête courants

```
Content-Type: application/json
Authorization: Bearer <token>    (interfaces nécessitant une authentification)
```

### 1.3 Format de réponse uniforme

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Signification |
|------|------|
| 0 | Succès |
| 400 | Erreur de paramètres |
| 401 | Non authentifié (Token manquant/expiré/invalide) |
| 403 | Sans permission |
| 404 | Ressource inexistante |
| 422 | Échec de validation |
| 429 | Requêtes trop fréquentes (limitation déclenchée) |
| 500 | Erreur serveur |

### 1.4 Encodage des ID

Tous les ID présents dans les requêtes et réponses des interfaces sont des chaînes encodées en Hashids, et non les valeurs BIGINT brutes.

```
Externe: aB3xK9mW2pQ7rT5v  (chaîne hashid)
Interne: 1750123456789      (Snowflake BIGINT)
```

### 1.5 Format de pagination

```
Requête: ?page=1&per_page=20

Réponse: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. Interfaces côté C (service :8792)

### 2.1 Authentification

#### POST /api/v1/auth/register — Inscription d'un utilisateur

```
Requête: {
  "username": "player1",
  "password": "123456",
  "email": "player@example.com"     // 可选
}

Réponse: {
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

#### POST /api/v1/auth/login — Connexion d'un utilisateur

```
Requête: {
  "username": "player1",
  "password": "123456"
}

Réponse: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "...", ... }
}
```

Erreurs : 401 nom d'utilisateur ou mot de passe incorrect / compte désactivé

#### POST /api/v1/auth/refresh — Rafraîchissement du Token

```
Requête: (Authorization: Bearer <refresh_token>)

Réponse: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 Portefeuille

#### GET /api/v1/wallet/info — Informations du portefeuille

```
Authentification requise: Oui

Réponse: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/v1/wallet/transactions — Historique des transactions

```
Authentification requise: Oui
Paramètres: ?page=1&per_page=20&type=deposit    (type 可选)

Réponse: {
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

type Valeurs possibles: deposit / withdraw / exchange_in / exchange_out / game_earn / game_spend
```

### 2.3 Recharge

#### POST /api/v1/deposit/create — Création d'une commande de recharge

```
Authentification requise: Oui

Requête: {
  "amount": "10.00",
  "currency": "USD",
  "payment_method_id": "aB3xK..."
}

Réponse: {
  "order_id": "aB3xK...",
  "order_no": "DEP202605221030000123",
  "amount": "10.00",
  "platform_amount": "10.0000",
  "checkout_url": "https://checkout.stripe.com/...",
  "expires_at": "2026-05-22 11:30:00"
}
```

Valeurs possibles de currency : USD / CNY / EUR / JPY / KRW / GBP / BRL / INR

checkout_url : lien de redirection de la passerelle de paiement (rempli à la création de la commande) ; expires_at : expiration du lien de paiement (1 heure après la création)

#### GET /api/v1/deposit/orders — Historique des recharges

```
Authentification requise: Oui
Paramètres: ?page=1&per_page=20

Réponse: {
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

Valeurs possibles de status : pending / paid / confirmed / cancelled

### 2.4 Échange

#### POST /api/v1/exchange/quote — Demande de cotation

```
Authentification requise: Oui

Requête: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "direction": "in",
  "platform_amount": "10.0000"
}

Réponse: {
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000",
  "spread_pct": "5.00%"
}
```

direction : in=achat de devises de jeu / out=vente de devises de jeu

#### POST /api/v1/exchange/buy — Achat de devises de jeu

```
Authentification requise: Oui

Requête: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "10.0000"
}

Réponse: {
  "exchange_id": "aB3xK...",
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000"
}
```

Erreurs : 422 solde de devises de plateforme insuffisant / 404 jeu indisponible

#### POST /api/v1/exchange/sell — Vente de devises de jeu

```
Authentification requise: Oui

Requête: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "950.0000"
}

Réponse: {
  "exchange_id": "aB3xK...",
  "platform_amount": "9.0250",
  "game_amount": "950.0000",
  "spread_fee": "0.4750",
  "rate": "100.00000000"
}
```

Erreurs : 422 solde de devises de jeu insuffisant

#### GET /api/v1/exchange/records — Historique des échanges

```
Authentification requise: Oui
Paramètres: ?page=1&per_page=20

Réponse: {
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

### 2.5 Retrait

#### POST /api/v1/withdraw/apply — Demande de retrait

```
Authentification requise: Oui

Requête: {
  "platform_amount": "50.0000",
  "method": "paypal",
  "account_info": "user@paypal.com"
}

Réponse: {
  "order_id": "...",
  "order_no": "WTH202605221030000456",
  "status": "approved"
}
```

Valeurs possibles de method : paypal / bank / crypto

status :
- approved : approbation automatique (montant < auto_approve_threshold)
- pending : en attente de validation (montant >= auto_approve_threshold)

Erreurs :
- 403 fonction de retrait temporairement désactivée (interrupteur global éteint)
- 400 montant inférieur au minimum de retrait
- 400 dépassement de la limite quotidienne de retrait
- 400 solde insuffisant

#### GET /api/v1/withdraw/orders — Historique des retraits

```
Authentification requise: Oui
Paramètres: ?page=1&per_page=20

Réponse: {
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

### 2.6 Jeux

#### GET /api/v1/game/list — Liste des jeux

```
Paramètres: ?page=1&per_page=20&keyword=射击&type=self

Réponse: {
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

Valeurs possibles de type : self / embedded / third_party

#### GET /api/v1/game/detail/{hashid} — Détail d'un jeu

```
Réponse: {
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

#### POST /api/v1/game/launch — Lancement d'un jeu

```
Authentification requise: Oui

Requête: { "game_id": "aB3xK..." }

Réponse: {
  "id": "...",
  "name": "射击大师",
  "type": "self",
  "api_endpoint": "https://game.example.com/play"
}
```

### 2.7 Connexion tierce OAuth

Prend en charge 7 plateformes : Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/v1/auth/oauth/{provider} — Obtention de l'URL d'autorisation

```
Paramètres: provider = google / facebook / apple / twitter / microsoft / linkedin / github

Réponse: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/v1/auth/oauth/{provider}/callback — Callback OAuth

```
Requête: { "code": "授权码", "state": "防CSRF状态" }

Réponse: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new : true=nouvel utilisateur enregistré / false=compte existant lié

### 2.8 Vérification d'identité KYC

#### GET /api/v1/user/identity/status — État de la certification

```
Authentification requise: Oui

Réponse: {
  "status": "approved",          // not_submitted / pending / approved / rejected
  "real_name": "J***",
  "id_type": "id_card",
  "review_note": "",
  "submitted_at": "2026-05-22 10:00:00",
  "reviewed_at": "2026-05-23 14:00:00"
}
```

#### POST /api/v1/user/identity/apply — Soumission de la certification

```
Authentification requise: Oui

Requête: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}

Réponse: { "message": "KYC submitted successfully" }
```

### 2.9 Paiement

#### POST /api/v1/payment/callback — Callback de paiement (public)

```
Requête: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

Réponse: { "message": "success" }
```

status : success / failed

Valeurs de provider : stripe / paypal / nowpayments / coinbase / skrill / neteller / paysafecard / paytm / mercadopago / astropay / paypay / kakaopay / gcash / mpesa / paystack / toss / adyen / grabpay

| provider | Région | Schéma de signature | Devises prises en charge |
|----------|--------|---------------------|--------------------------|
| stripe | Global (125+ moyens de paiement locaux, incl. APM Alipay/WeChat Pay) | Webhook HMAC-SHA256 | USD / CNY / EUR |
| paypal | 200+ marchés mondiaux | Vérification webhook (verify-webhook-signature) | USD / CNY / EUR et autres monnaies fiat |
| nowpayments | Global (crypto) | IPN HMAC-SHA512 | USDT TRC20 / ERC20 |
| coinbase | Global (crypto) | Webhook HMAC-SHA256 (secret base64) | USDC / BTC / ETH |
| skrill | Europe / Global | Vérification MD5 du secret word | EUR et autres monnaies fiat |
| neteller | Europe / Global | Comparaison du champ secret key | EUR et autres monnaies fiat |
| paysafecard | Europe (DE / AT / CH, etc.) | X-Signature HMAC-SHA256 | EUR et autres monnaies fiat |
| paytm | Inde | SHA256 + AES-128-CBC | INR |
| mercadopago | Amérique latine (BR / MX, etc.) | X-Signature (ts,v1) HMAC-SHA256 | BRL / MXN et autres monnaies fiat |
| astropay | Amérique latine (BR, etc.) | MD5(order_id.amount.status.secret) | BRL et autres monnaies fiat |
| paypay | Japon | PayPay-Signature HMAC-SHA256 | JPY |
| kakaopay | Corée du Sud | Pas de webhook (flux en deux étapes ready/approve) | KRW |
| gcash | Philippines | Paymongo-Signature HMAC-SHA256 | PHP |
| toss | Corée du Sud | Server-side verify + amount check | KRW |
| mpesa | Kenya | Trusted IP (CALLBACK_TRUSTED_IPS), no signature | KES |
| paystack | Nigéria | x-paystack-signature HMAC-SHA512 | NGN |
| adyen | Mondial (devise selon l'ordre) | additionalData.hmacSignature HMAC-SHA256 (ADYEN_HMAC_KEY) | selon l'ordre |
| grabpay | Singapour (pays configurable, par défaut SG) | x-signature HMAC-SHA256 (sorted key:value) | selon l'ordre |

#### GET /api/v1/payment/methods — Modes de paiement disponibles (public)

```
Réponse: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

Filtré par pays de l'utilisateur (X-Language/Accept-Language → code pays) : countries vide ou contenant * signifie visible mondialement ; trié selon la préférence de méthodes de paiement de country_config du pays

### 2.10 Historique de jeu

#### GET /api/v1/game/play-logs — Liste des historiques de jeu

```
Authentification requise: Oui
Paramètres: ?page=1&per_page=20&game_id=xxx&action=start

Réponse: {
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

#### GET /api/v1/game/play-log/{hashid} — Détail d'un historique de jeu

```
Authentification requise: Oui
Réponse: { 完整记录，含 session_id / game_amount_before / after 等 }
```

### 2.12 Classements

#### GET /api/v1/leaderboard/list — Liste des classements

```
Réponse: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/v1/leaderboard/{hashid} — Détail d'un classement

```
Réponse: {
  "id": "...",
  "name": "全服累计收入榜",
  "type": "total",
  "rankings": [
    { "rank": 1, "user_id": "...", "score": "50000.0000" }
  ]
}
```

### 2.13 Coupons

#### GET /api/v1/coupon/available — Coupons à réclamer

```
Authentification requise: Oui
Réponse: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/v1/coupon/claim — Réclamer un coupon

```
Authentification requise: Oui
Requête: { "coupon_id": "hashid" }
Réponse: { "coupon": { ... } }
```

#### GET /api/v1/coupon/my — Mes coupons

```
Authentification requise: Oui
Paramètres: ?status=unused
Réponse: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 Configuration des pays

#### GET /api/v1/country/list — Liste des pays

```
Réponse: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/v1/country/{code} — Détail d'un pays

```
Réponse: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 Notifications

#### GET /api/v1/notification/list — Liste des notifications

```
Authentification requise: Oui
Paramètres: ?page=1&per_page=20&is_read=0

Réponse: {
  "list": [
    { "id": "...", "type": "deposit", "title": "Deposit Received", "is_read": 0, "created_at": "..." }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### GET /api/v1/notification/unread-count — Nombre de non lues

```
Authentification requise: Oui
Réponse: { "count": 3 }
```

#### POST /api/v1/notification/read — Marquer comme lues

```
Authentification requise: Oui
Requête: { "id": "hashid" }  // 不传=全部已读
```

### 2.17 Parrainage

#### GET /api/v1/referral/my-code — Mon code de parrainage

```
Authentification requise: Oui
Réponse: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/v1/referral/apply — Utiliser un code de parrainage

```
Authentification requise: Oui
Requête: { "code": "ABC12345" }
Réponse: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/v1/user/2fa/status — État 2FA

```
Authentification requise: Oui
Réponse: { "enabled": false }
```

#### POST /api/v1/user/2fa/setup — Configurer le 2FA

```
Authentification requise: Oui
Réponse: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/v1/user/2fa/enable — Activer le 2FA

```
Authentification requise: Oui
Requête: { "code": "123456" }
Réponse: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/v1/2fa/verify — Vérifier le 2FA (public)

```
Requête: { "user_id": "hashid", "code": "123456" }
Réponse: { "valid": true }
```

### 2.19 Recherche

#### GET /api/v1/search — Recherche globale

```
Paramètres: ?q=keyword&type=game&page=1&per_page=20
Réponse: { "list": [...], "total": 100 }
```

#### GET /api/v1/game/suggest — Suggestions de recherche

```
Paramètres: ?q=shoot
Réponse: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 Langues

#### GET /api/v1/language/list — Liste des langues disponibles

```
Réponse: {
  "current": "en-US",
  "languages": {
    "en-US": { "name": "English", "nativeName": "English", "icon": "us" },
    "zh-CN": { "name": "Chinese (Simplified)", "nativeName": "简体中文", "icon": "cn" },
    "ja-JP": { "name": "Japanese", "nativeName": "日本語", "icon": "jp" },
    "ko-KR": { "name": "Korean", "nativeName": "한국어", "icon": "kr" }
  }
}
```

#### POST /api/v1/language/switch — Changer de langue

```
Requête: { "locale": "zh-CN" }
Réponse: { "locale": "zh-CN" }
```

Valeurs possibles de locale : en-US / zh-CN / ja-JP / ko-KR

### 2.8 Utilisateur

#### GET /api/v1/user/profile — Informations personnelles

```
Authentification requise: Oui

Réponse: {
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

#### PUT /api/v1/user/profile — Modifier le profil

```
Authentification requise: Oui

Requête: {
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}

Réponse: {
  "id": "...",
  "username": "player1",
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}
```

Valeurs possibles de language : en-US / zh-CN / ja-JP / ko-KR

### 2.9 Annonces

#### GET /api/v1/announcement/list — Liste des annonces

```
Réponse: {
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

#### GET /api/v1/announcement/detail/{hashid} — Détail d'une annonce

```
Réponse: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

### 2.21 Statistiques de la plateforme

| Méthode | Chemin | Description | Authentification |
|------|------|------|------|
| GET | /api/v1/platform/stats | Statistiques publiques de la plateforme (total jeux / total utilisateurs / parties du jour / actifs sur 7 jours) | Non |

#### GET /api/v1/platform/stats — Statistiques de la plateforme

```
无需认证

Réponse: {
  "total_games": 12,
  "total_users": 1500,
  "today_game_plays": 320,
  "active_users_7d": 450
}
```

## 3. Interfaces d'administration (admin :8789)

### 3.1 Tableau de bord de la plateforme

#### GET /admin/v1/dashboard/platform

```
Authentification requise: Oui (AdminAuth + AdminPermission)

Réponse: {
  "total_users": 1500,
  "active_users_7d": 320,
  "total_games": 12,
  "pending_withdraws": 5,
  "today_deposits": "500.0000",
  "today_withdraws": "120.0000",
  "total_spread_fee": "1500.5000"
}
```

### 3.2 Gestion des jeux

#### GET /admin/v1/game/list — Liste des jeux

```
Authentification requise: Oui
Paramètres: ?page=1&limit=20&keyword=射击

Réponse: {
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

#### GET /admin/v1/game/{hashid} — Détail d'un jeu

```
Authentification requise: Oui
Paramètres: hashid 为游戏的 hashid 编码（路径参数）

Réponse: {
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

Renvoie code 404 si le jeu n'existe pas.

#### POST /admin/v1/game/launch — Aperçu du jeu

```
Authentification requise: Oui

Requête: {
  "game_id": "aB3xK..."      // 游戏 ID(hashid)
}

Réponse: {
  "id": "aB3xK...",
  "name": "射击大师",
  "slug": "shooter-master",
  "type": "self",
  "api_endpoint": "https://...",
  "preview": true
}
```

Si `game_id` est absent, code 422 est renvoyé ; si le jeu n'existe pas, 404 est renvoyé ; si le jeu n'est pas publié (`status` différent de 1), 403 est renvoyé.

L'aperçu admin est un aperçu pur : il ne valide que la disponibilité du jeu et renvoie les informations de lancement, et **n'écrit aucun enregistrement de jeu et ne touche pas au portefeuille**. Les identités admin ne portent que `adminId` (injecté par `AdminAuth`) et aucun `userId` côté C, donc cet endpoint n'effectue délibérément aucune écriture côté utilisateur — copier le `POST /api/v1/game/launch` côté C écrirait des lignes `game_game_play_log` avec un mauvais propriétaire.

#### POST /admin/v1/game/create — Créer un jeu

```
Authentification requise: Oui

Requête: {
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

Réponse: { "id": "aB3xK..." }
```

Valeurs possibles de type : self / embedded / third_party

#### PUT /admin/v1/game/{hashid} — Modifier un jeu

```
Authentification requise: Oui

Requête: {
  "name": "新名称",
  "status": 1
  // 可部分更新，字段同 create
}

Réponse: { "message": "更新成功" }
```

#### DELETE /admin/v1/game/{hashid} — Supprimer un jeu

```
Authentification requise: Oui
Réponse: { "message": "删除成功" }
```

#### POST /admin/v1/game/currency/manage — Gérer les devises

```
Authentification requise: Oui

Requête: {
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

Réponse: { "message": "操作成功" }
```

Si `game_id` est absent ou si `currencies` n'est pas un tableau, 422 est renvoyé ; si le jeu n'existe pas, 404 est renvoyé.

Les champs `exchange_rate` et `spread_pct` ne sont validés que s'ils sont fournis : `exchange_rate` doit être un nombre supérieur à 0 et `spread_pct` doit être dans l'intervalle [0, 100) ; toute violation renvoie 422 et aucune devise du lot n'est écrite (le lot est validé en totalité avant l'écriture). Les champs omis ne déclenchent pas de validation : à la création les valeurs par défaut s'appliquent (`exchange_rate` = `1.00000000`, les autres `0.00000000`), à la mise à jour la valeur existante est conservée.

### 3.3 Gestion des retraits

#### GET /admin/v1/withdraw/orders — Liste des commandes de retrait

```
Authentification requise: Oui
Paramètres: ?page=1&limit=20&status=pending

Réponse: {
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

#### PUT /admin/v1/withdraw/review — Valider un retrait

```
Authentification requise: Oui

Requête: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

Réponse: { "message": "已通过" }
```

action : approve=approuver / reject=refuser / confirm=confirmer (en cas de refus, les devises de plateforme sont automatiquement retournées)

Erreurs : 422 l'état de la commande n'est pas « en attente de validation »

#### PUT /admin/v1/withdraw/switch — Interrupteur global des retraits

```
Authentification requise: Oui

Requête: { "enabled": 1 }

Réponse: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/v1/withdraw/limits/set — Définir les limites de retrait

```
Authentification requise: Oui

Requête: {
  "daily_limit": "10000.0000",             // 可选
  "min_amount": "1.0000",                  // 可选
  "auto_approve_threshold": "100.0000"     // 可选
}

Réponse: {
  "daily_limit": "10000.0000",
  "min_amount": "1.0000",
  "auto_approve_threshold": "100.0000",
  "global_switch": true
}
```

#### POST /admin/v1/withdraw/batch-review — Révision groupée des retraits

```
Authentification requise: Oui

Requête: {
  "ids": ["aB3xK...", "cD4yL..."],
  "action": "approve",
  "note": "批量审核通过"
}

Réponse: {
  "processed": 2,
  "failed": []
}
```

action: approve=approuver / reject=rejeter (traitement ordre par ordre ; les ordres rejetés sont remboursés automatiquement ; les échecs sont listés dans failed et ne bloquent pas les autres)

#### POST /admin/v1/withdraw/execute-payout — Exécuter le paiement

```
Authentification requise: Oui

Requête: { "order_id": "aB3xK..." }

Réponse: {
  "payout_batch_id": "PAYOUT-123456",
  "payout_item_id": "ITEM-123456",
  "payout_status": "success",
  "payout_attempts": 1
}
```

Seul un ordre au statut approved peut être payé (bascule atomique vers processing) ; un appel répété renvoie 422. Si la double validation est activée, l'ordre doit d'abord être confirmé par une seconde personne

#### POST /admin/v1/withdraw/sync-payout — Synchroniser le statut du paiement

```
Authentification requise: Oui

Requête: { "order_id": "aB3xK..." }

Réponse: {
  "payout_status": "success",
  "order_status": "completed",
  "synced_status": "success"
}
```

Erreur : 422 Aucun paiement n'a encore été exécuté pour cet ordre

### 3.4 Gestion des utilisateurs de la plateforme

#### GET /admin/v1/platform/user/list — Liste des utilisateurs côté C

```
Authentification requise: Oui
Paramètres: ?page=1&limit=20&keyword=player&status=1

Réponse: {
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

#### GET /admin/v1/platform/user/{hashid} — Détail d'un utilisateur

```
Authentification requise: Oui

Réponse: {
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

#### PUT /admin/v1/platform/user/{hashid} — Modifier/bannir un utilisateur

```
Authentification requise: Oui

Requête: {
  "status": 0,         // 0=禁用 1=启用
  "nickname": "..."    // 可选
}

Réponse: { "message": "更新成功" }
```

### 3.5 Gestion des paiements

#### GET /admin/v1/payment/method/list

```
Authentification requise: Oui

Réponse: {
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

#### POST /admin/v1/payment/method/toggle — Activer/désactiver un mode de paiement

```
Authentification requise: Oui

Requête: { "id": "aB3xK...", "status": 0 }

Réponse: { "message": "已更新" }
```

### 3.6 Gestion des annonces

#### GET /admin/v1/announcement/list

```
Authentification requise: Oui
Paramètres: ?page=1&limit=20

Réponse: {
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

#### POST /admin/v1/announcement/create — Publier une annonce

```
Authentification requise: Oui

Requête: {
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护。",
  "type": "system",           // 可选, 默认"system"
  "target_lang": "",          // 可选, 空=全语言
  "status": 1,                // 可选, 默认1 (0=草稿 1=发布)
  "start_at": "2026-05-23 02:00:00",  // 可选
  "end_at": "2026-05-23 04:00:00"     // 可选
}

Réponse: { "id": "aB3xK..." }
```

### 3.7 Validation KYC

#### GET /admin/v1/identity/list — Liste KYC

```
Authentification requise: Oui
Paramètres: ?page=1&limit=20&status=pending

Réponse: {
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

#### PUT /admin/v1/identity/review — Valider le KYC

```
Authentification requise: Oui

Requête: { "id": "hashid", "action": "approve", "note": "" }

Réponse: { "message": "Approved" }
```

action : approve / reject

### 3.8 Gestion des serveurs de jeux

#### GET /admin/v1/game/server/list — Liste des serveurs

```
Authentification requise: Oui
Paramètres: ?game_id=hashid

Réponse: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/v1/game/server/create — Créer un serveur

```
Authentification requise: Oui
Requête: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
Réponse: { "id": "hashid" }
```

#### PUT /admin/v1/game/server/{hashid} — Modifier un serveur

```
Authentification requise: Oui
Requête: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/v1/game/server/{hashid} — Supprimer un serveur

```
Authentification requise: Oui
```

### 3.9 Gestion des limites de retrait par paliers

#### GET /admin/v1/withdraw/limits/list

```
Authentification requise: Oui

Réponse: {
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

#### PUT /admin/v1/withdraw/limits/{hashid} — Mettre à jour les limites

```
Authentification requise: Oui

Requête: { "single_max": "10000.0000", "fee_pct": "0.25" }
// 可部分更新
```

### 3.11 Gestion des catégories de jeux

#### GET /admin/v1/game/category/list

```
Authentification requise: Oui
Réponse: { "list": [{ "id": "...", "name": "动作", "slug": "action", "sort": 1 }] }
```

#### POST /admin/v1/game/category/create

```
Authentification requise: Oui
Requête: { "name": "新分类", "slug": "new-cat", "icon": "star", "sort": 10 }
Réponse: { "id": "hashid" }
```

#### PUT /admin/v1/game/category/{hashid} — Modifier une catégorie

#### DELETE /admin/v1/game/category/{hashid} — Supprimer une catégorie

#### POST /admin/v1/game/category/assign — Attribuer des jeux

```
Authentification requise: Oui
Requête: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 Gestion des classements

#### GET /admin/v1/leaderboard/list — Liste des classements

```
Authentification requise: Oui
Réponse: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/v1/leaderboard/create — Créer un classement

```
Authentification requise: Oui
Requête: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(可选)" }
```

#### PUT /admin/v1/leaderboard/{hashid} — Modifier un classement

#### DELETE /admin/v1/leaderboard/{hashid} — Supprimer un classement

#### POST /admin/v1/leaderboard/{hashid}/refresh — Rafraîchir le cache

### 3.13 Gestion des coupons

#### GET /admin/v1/coupon/list — Liste des coupons

#### POST /admin/v1/coupon/create — Créer un coupon

```
Authentification requise: Oui
Requête: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/v1/coupon/{hashid} — Modifier (tant que non réclamé)

#### DELETE /admin/v1/coupon/{hashid} — Supprimer

#### GET /admin/v1/coupon/{hashid}/stats — Statistiques de réclamation

```
Réponse: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 Gestion de la configuration des pays

#### GET /admin/v1/country/config/list — Liste des configurations de pays

#### POST /admin/v1/country/config/create — Créer une configuration de pays

```
Authentification requise: Oui
Requête: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/v1/country/config/{hashid} — Modifier une configuration de pays

### 3.15 Export de données

#### POST /admin/v1/export/users — Exporter les utilisateurs côté C

```
Authentification requise: Oui
Paramètres (JSON): { "status": 1 }   // 可选筛选

Réponse: Excel 文件下载 (xlsx)
```

#### POST /admin/v1/export/transactions — Exporter les transactions de la plateforme

```
Authentification requise: Oui
Paramètres (JSON): { "type": "deposit" }   // 可选筛选

Réponse: Excel 文件下载 (xlsx)
```

### 3.16 Analyse de données (agrégation MySQL en temps réel)

Tous les points d'extrémité nécessitent une authentification (AdminAuth + AdminPermission) ; les données sont agrégées en temps réel depuis MySQL, sans dépendance à ClickHouse.

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/v1/analytics/overview | Aperçu de la plateforme (aujourd'hui/7 derniers jours) |
| GET | /admin/v1/analytics/game-ranking | Classement des jeux (?days=7) |
| GET | /admin/v1/analytics/dau-trend | Tendance DAU (?days=30) |
| GET | /admin/v1/analytics/hourly-trend | Tendance horaire |
| GET | /admin/v1/analytics/action-distribution | Répartition des comportements |
| GET | /admin/v1/analytics/revenue | Analyse des revenus |
| GET | /admin/v1/analytics/conversion | Taux de conversion des jeux |
| GET | /admin/v1/analytics/probability | Probabilités conjointes/conditionnelles |
| GET | /admin/v1/analytics/retention | Analyse de rétention D1/D3/D7/D30 |
| GET | /admin/v1/analytics/funnel | Entonnoir de conversion |
| GET | /admin/v1/analytics/arpu | Tendance ARPU/ARPPU |
| GET | /admin/v1/analytics/economy | Indicateurs économiques des devises de jeu |

### 3.17 Gestion des tickets

Tous les points d'extrémité nécessitent une authentification (AdminAuth + AdminPermission).

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/v1/ticket/list | Liste des tickets (?page=&limit=&status=&type=) |
| GET | /admin/v1/ticket/{hashid} | Détail d'un ticket (avec réponses) |
| POST | /admin/v1/ticket/{hashid}/reply | Répondre à un ticket |
| POST | /admin/v1/ticket/{hashid}/close | Clôturer un ticket |
| POST | /admin/v1/ticket/{hashid}/assign | Attribuer un traitement (admin_id) |

### 3.18 Gestion de la configuration CDN

Tous les points d'extrémité nécessitent une authentification (AdminAuth + AdminPermission).

| Méthode | Chemin | Description | Authentification |
|------|------|------|------|
| GET | /admin/v1/cdn/provider/list | Liste des fournisseurs CDN (les identifiants ne sont pas renvoyés) | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/toggle | Activer/désactiver le fournisseur {id, status} | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/create | Créer {name, provider, config(JSON), status, sort}, vérification d'unicité de provider | AdminAuth + RBAC: cdn |
| PUT | /admin/v1/cdn/provider/{hashid} | Modifier (config vide = inchangé) | AdminAuth + RBAC: cdn |
| DELETE | /admin/v1/cdn/provider/{hashid} | Supprimer | AdminAuth + RBAC: cdn |
| POST | /admin/v1/cdn/provider/test | Test de connectivité HeadBucket {id} | AdminAuth + RBAC: cdn |

### 3.19 Rapports de données

Tous les points d'extrémité nécessitent une authentification (AdminAuth + AdminPermission).

| Méthode | Chemin | Description | Authentification |
|------|------|------|------|
| GET | /admin/v1/report/summary | Récapitulatif des rapports (nouveaux utilisateurs/dépôts/retraits/échanges/parties) | AdminAuth + RBAC: report |
| GET | /admin/v1/report/daily | Rapport quotidien (agrégation par jour, jours sans données remplis à 0) | AdminAuth + RBAC: report |
| GET | /admin/v1/report/export | Export du rapport quotidien en CSV (UTF-8 BOM) | AdminAuth + RBAC: report |

## 4. Stratégie de limitation

| Interface | Limite |
|------|------|
| Défaut | 60 requêtes/minute/IP |
| POST /api/v1/auth/login | 10 requêtes/minute |
| POST /api/v1/auth/register | 5 requêtes/minute |

En cas de dépassement, 429 est renvoyé, avec les en-têtes de réponse suivants :
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1716400830
Retry-After: 60
```

## 5. Notes d'authentification

### Côté C (UserAuth)

1. Extraire le Token de `Authorization: Bearer <token>`
2. Vérification de la signature JWT (HS256), décodage de `sub` (ID utilisateur)
3. Interroger la table `game_user` pour vérifier que l'utilisateur existe et que status=1
4. Injection de `$request->userId`

### Administration (AdminAuth + AdminPermission)

1. AdminAuth : vérification de la signature JWT, décodage de `sub` (ID administrateur), injection de `$request->adminId`
2. AdminPermission : recherche des permissions selon les rôles de l'utilisateur, correspondance avec les identifiants de permission au format `method.path`
3. Les super administrateurs `slug=*` contournent la vérification des permissions

## 6. Référence rapide des codes d'erreur

| code | Signification | Scénario courant |
|------|------|---------|
| 0 | Succès | - |
| 400 | Erreur de paramètres | Format de requête incorrect, solde insuffisant |
| 401 | Non authentifié | Token manquant/expiré/invalide, compte désactivé |
| 403 | Sans permission | L'utilisateur n'a pas la permission du rôle correspondant, jeu indisponible |
| 404 | Inexistant | Ressource introuvable |
| 422 | Échec de validation | Paramètres du formulaire non conformes, opération interdite par l'état de la commande |
| 429 | Limitation | Requêtes trop fréquentes |
| 500 | Erreur serveur | Exception imprévue |


## 7. Nouvelles API (extension de l'écosystème v2.0)

### 7.1 Provider API — Interfaces de callback des fournisseurs de jeux

**Méthode d'authentification** : signature HMAC-SHA256 (X-Game-Id + X-Timestamp + X-Signature)
**Fenêtre temporelle** : 5 minutes

#### POST /api/provider/balance — Consultation du solde d'un utilisateur

```
En-têtes de requête:
  X-Game-Id: 1234567890
  X-Timestamp: 1716400830
  X-Signature: abc123...

Requête: {
  "user_id": 1234567890,
  "game_id": 9876543210,
  "currency_id": 5555555555
}

Réponse: {
  "code": 0,
  "message": "success",
  "data": { "balance": "1000.50000000" }
}
```

#### POST /api/provider/bet — Notification de mise

```
Requête: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "bet_type": "straight" }
}

Réponse: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "990.50000000"
  }
}
```

#### POST /api/provider/settle — Notification de règlement

```
Requête: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}

Réponse: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1040.50000000",
    "win_amount": "50.00000000"
  }
}
```

#### POST /api/provider/refund — Notification de remboursement

```
Requête: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "reason": "game_crash"
}

Réponse: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1000.50000000"
  }
}
```

### 7.2 API de tickets

#### GET /api/v1/ticket/list — Liste des tickets

```
Authentification requise: Oui
Paramètres: ?page=1&per_page=20

Réponse: {
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

type : deposit / withdraw / game / account / other
status : open / waiting / replied / closed

#### POST /api/v1/ticket/create — Créer un ticket

```
Authentification requise: Oui
Requête: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
Réponse: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/v1/ticket/{hashid} — Détail d'un ticket

```
Authentification requise: Oui
Réponse: {
  "id": "...", "type": "deposit", "subject": "...",
  "content": "...", "status": "open",
  "replies": [
    { "id": "...", "content": "...", "is_admin": 1, "created_at": "..." }
  ]
}
```

#### POST /api/v1/ticket/{hashid}/reply — Répondre à un ticket

```
Authentification requise: Oui
Requête: { "content": "已核实，将在24小时内处理" }
Réponse: { "code": 0, "message": "Reply sent" }
```

### 7.3 API de vérification d'email

#### POST /api/v1/verify/send-email — Envoi du code de vérification d'email

```
Authentification requise: Oui
Requête: { "email": "user@example.com" }
Réponse: { "code": 0, "message": "Verification code sent" }
Erreur: 429 请60秒后重试
```

#### POST /api/v1/verify/confirm-email — Confirmation de l'email

```
Authentification requise: Oui
Requête: { "code": "123456" }
Réponse: { "code": 0, "message": "Email verified" }
Erreur: 422 验证码无效或已过期
```

### 7.4 API VIP

#### GET /api/v1/user/vip-status — État VIP

> **Non implémenté** : la route côté C n'est pas enregistrée (aucune entrée dans `service/config/route.php`), les requêtes renvoient actuellement 404. Supprimez cette ligne une fois implémenté.

```
Authentification requise: Oui
Réponse: {
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

### 7.5 API de succès

#### GET /api/v1/user/achievements — Liste des succès

> **Non implémenté** : la route côté C n'est pas enregistrée (aucune entrée dans `service/config/route.php`), les requêtes renvoient actuellement 404. Supprimez cette ligne une fois implémenté.

```
Authentification requise: Oui
Réponse: {
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

### 7.6 Nouvelles API d'administration

#### GET /admin/v1/ticket/list — Liste des tickets

```
Authentification requise: Oui
Paramètres: ?page=1&limit=20&status=pending&type=deposit

Réponse: {
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

#### POST /admin/v1/ticket/{hashid}/reply — Répondre à un ticket

```
Authentification requise: Oui
Requête: { "content": "已处理" }
Réponse: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/v1/ticket/{hashid}/close — Clôturer un ticket

```
Authentification requise: Oui
Réponse: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/v1/ticket/{hashid}/assign — Attribuer un traitement

```
Authentification requise: Oui
Requête: { "admin_id": 1234567890 }
Réponse: { "code": 0, "message": "Assigned" }
```

#### GET /admin/v1/analytics/retention — Analyse de rétention

```
Authentification requise: Oui
Paramètres: ?days=30
Réponse: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/v1/analytics/funnel — Entonnoir de conversion

```
Authentification requise: Oui
Réponse: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/v1/analytics/arpu — Tendance ARPU/ARPPU

```
Authentification requise: Oui
Paramètres: ?days=30
Réponse: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/v1/analytics/economy — Indicateurs économiques des devises de jeu

```
Authentification requise: Oui
Réponse: {
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


#### GET /admin/v1/cdn/provider/list — Liste des fournisseurs CDN (les identifiants ne sont pas renvoyés)

```
Authentification requise: Oui
Réponse: { "list": [ { "id": "...", "name": "...", "provider": "cloudflare", "status": 1, "sort": 0 } ] }
```

#### POST /admin/v1/cdn/provider/toggle — Activer/désactiver le fournisseur {id, status}

```
Authentification requise: Oui
Requête: { "id": "...", "status": 1 }
Réponse: { "code": 0, "message": "..." }
```

#### POST /admin/v1/cdn/provider/create — Créer {name, provider, config(JSON), status, sort}, vérification d'unicité de provider

```
Authentification requise: Oui
Requête: { "name": "...", "provider": "aliyun", "config": "{...}", "status": 1, "sort": 0 }
Réponse: { "code": 0, "data": { "id": "..." } }
```

#### PUT /admin/v1/cdn/provider/{hashid} — Modifier (config vide = inchangé)

```
Authentification requise: Oui
Requête: { "name": "...", "config": "" }
Réponse: { "code": 0, "message": "..." }
```

#### DELETE /admin/v1/cdn/provider/{hashid} — Supprimer

```
Authentification requise: Oui
Réponse: { "code": 0, "message": "..." }
```

#### POST /admin/v1/cdn/provider/test — Test de connectivité HeadBucket {id}

```
Authentification requise: Oui
Requête: { "id": "..." }
Réponse: { "code": 0, "data": { "ok": true } }
```
#### GET /admin/v1/report/summary — Récapitulatif des rapports

```
Authentification requise: Oui
Paramètres: ?start=Y-m-d&end=Y-m-d (缺省最近30天，跨度 ≤90 天，Redis 缓存5分钟)
Réponse: {
  "start": "2026-08-01", "end": "2026-08-31",
  "new_users": 120, "deposit_amount": "5000.0000", "deposit_count": 45,
  "withdraw_amount": "1200.0000", "withdraw_count": 8,
  "exchange_amount": "3000.0000", "play_count": 1500
}
```


#### GET /admin/v1/report/daily — Rapport quotidien

```
Authentification requise: Oui
Paramètres: ?start=Y-m-d&end=Y-m-d
Réponse: {
  "start": "2026-08-01", "end": "2026-08-31",
  "rows": [ { "date": "2026-08-01", "new_users": 12, "deposit_amount": "500.0000", "deposit_count": 4, "withdraw_amount": "100.0000", "withdraw_count": 1, "exchange_amount": "300.0000", "play_count": 150 } ]
}
```


#### GET /admin/v1/report/export — Export CSV du rapport quotidien

```
Authentification requise: Oui
Paramètres: ?start=Y-m-d&end=Y-m-d&format=excel
Réponse: CSV 文件（UTF-8 BOM），文件名 report_{start}_{end}.csv，Excel 可直接打开
```

## 8. Stratégie de limitation (mise à jour)

| Interface | Limite |
|------|------|
| Défaut | 60 requêtes/minute/IP |
| POST /api/v1/auth/login | 10 requêtes/minute |
| POST /api/v1/auth/register | 5 requêtes/minute |
| POST /api/v1/auth/oauth | 10 requêtes/minute |
| POST /api/v1/payment/callback | 30 requêtes/minute |
| POST /api/provider/* | Illimité (authentification par signature HMAC) |

## 9. Notes d'authentification (mise à jour)

### Authentification Provider (ProviderAuth)

1. Extraire `X-Game-Id`, `X-Timestamp`, `X-Signature` des en-têtes de requête
2. Interroger la table `game_game` pour vérifier que le jeu existe et que status=1
3. Vérifier que l'horodatage se situe dans la fenêtre de 5 minutes (anti-rejeu)
4. Calculer `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` et le comparer à la signature
5. Injection de `$request->gameId` et `$request->game`


### 7.7 API d'amis

#### GET /api/v1/friend/list — Liste des amis
```
Authentification requise: Oui
Réponse: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/v1/friend/requests — Demandes en attente
```
Authentification requise: Oui
Réponse: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/v1/friend/request — Envoyer une demande d'ami
```
Authentification requise: Oui
Requête: { "friend_id": "hashid" }
```

#### POST /api/v1/friend/accept — Accepter une demande
```
Authentification requise: Oui
Requête: { "request_id": "hashid" }
```

#### POST /api/v1/friend/reject — Refuser une demande
```
Authentification requise: Oui
Requête: { "request_id": "hashid" }
```

#### POST /api/v1/friend/remove — Supprimer un ami
```
Authentification requise: Oui
Requête: { "friend_id": "hashid" }
```

#### GET /api/v1/friend/search — Rechercher des utilisateurs
```
Authentification requise: Oui
Paramètres: ?q=username
Réponse: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 API de chat

#### GET /api/v1/chat/conversations — Liste des conversations
```
Authentification requise: Oui
Réponse: {
  "list": [{
    "peer": { "id": "...", "username": "...", "nickname": "...", "avatar": "..." },
    "last_message": "最近一条消息",
    "unread_count": 3,
    "updated_at": "2026-05-22 10:30:00"
  }]
}
```

#### GET /api/v1/chat/messages/{peerHashid} — Liste des messages
```
Authentification requise: Oui
Paramètres: ?page=1&per_page=50
Réponse: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
自动标记对端发来的未读消息为已读
```

#### POST /api/v1/chat/send — Envoyer un message
```
Authentification requise: Oui
Requête: { "to_user_id": "hashid", "content": "Hello!" }
Erreur: 403 非好友不可发
```

#### GET /api/v1/chat/unread-total — Total des non lus
```
Authentification requise: Oui
Réponse: { "count": 5 }
```

**Connexion WebSocket** : `ws://host:8791`
```
// 认证
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// 接收消息
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 API Webhook

#### GET /api/v1/webhook/list — Liste des abonnements
```
Authentification requise: Oui
Réponse: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/v1/webhook/register — Enregistrer un abonnement
```
Authentification requise: Oui
Requête: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
Événements disponibles: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/v1/webhook/delete — Supprimer un abonnement
```
Authentification requise: Oui
Requête: { "id": "hook_id" }
```

### 7.10 API d'analyse avancée

#### GET /admin/v1/analytics/retention — Analyse de rétention
```
Authentification requise: Oui
Réponse: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/v1/analytics/funnel — Entonnoir de conversion
```
Authentification requise: Oui
Réponse: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/v1/analytics/arpu — Tendance ARPU/ARPPU
```
Authentification requise: Oui
Paramètres: ?days=30
Réponse: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/v1/analytics/economy — Indicateurs économiques des jeux
```
Authentification requise: Oui
Réponse: {
  "currencies": [{
    "game_name": "Shooter Master", "currency": "Gold", "symbol": "G",
    "total_minted": "500000.00000000", "total_burned": "320000.00000000",
    "circulation": "180000.00000000", "inflation_rate": "36.00%"
  }]
}
```


### 7.11 API de tournois

#### GET /api/v1/tournament/list — Liste des tournois
```
Paramètres: ?status=active|upcoming|ended&page=1&per_page=20
Réponse: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/v1/tournament/{hashid} — Détail d'un tournoi
```
Réponse: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/v1/tournament/{hashid}/join — S'inscrire à un tournoi
```
Authentification requise: Oui
Erreurs: 422 已报名 / 400 已开始或已满员 / 503 FeatureFlag关闭
```

### 7.12 Conditions de coupons (nouveau)

Le JSON `conditions` des coupons prend en charge :
- `min_deposit` : string, montant minimum de recharge cumulé
- `first_user_only` : bool, uniquement pour les nouveaux utilisateurs n'ayant jamais rechargé
- `game_id` : int, avoir joué au jeu spécifié

Les conditions sont vérifiées deux fois : dans le filtrage de la liste `available()` et à la réclamation `claim()`.

### 7.13 Parrainage multi-niveaux (nouveau)

La commission de parrainage ajoute une répartition de deuxième niveau :
- L1 : le parrain direct reçoit `referrer_bonus` (config : referral.referrer_bonus)
- L2 : le parrain du parrain reçoit `commission = referrer_bonus * level2_rate` (config : referral.level2_rate, défaut 5 %)
- Enregistrement dans `game_referral_commission` (level/commission_rate/commission_amount)

### 8. Stratégie de limitation (mise à jour)

| Interface | Limite |
|------|------|
| POST /api/v1/tournament/{id}/join | 10 requêtes/minute |

---

## 10. Nouvelles API (v1.3.15-v1.3.22)

### 10.1 Gestion des risques (admin :8789)

| Point d'accès | Description |
|------|------|
| GET /admin/v1/risk/dashboard | Vue d'ensemble du tableau de bord des risques |
| GET /admin/v1/risk/overview | Indicateurs d'ensemble des risques |
| GET /admin/v1/risk/hit-trend | Tendance des déclenchements |
| GET /admin/v1/risk/action-distribution | Répartition des actions |
| GET /admin/v1/risk/rule-performance | Performance des règles |
| GET /admin/v1/risk/rule/list | Liste des règles |
| POST /admin/v1/risk/rule/create | Créer une règle |
| PUT /admin/v1/risk/rule/{hashid} | Mettre à jour une règle |
| POST /admin/v1/risk/rule/{hashid}/toggle | Activer/désactiver une règle |
| POST /admin/v1/risk/rule/test | Tester une règle |
| GET /admin/v1/risk/event/list | Liste des événements à risque |
| GET /admin/v1/risk/event/{hashid} | Détail de l'événement |
| POST /admin/v1/risk/event/{hashid}/handle | Traiter l'événement |
| GET /admin/v1/risk/device/list | Liste des empreintes d'appareils |
| POST /admin/v1/risk/device/block | Bloquer l'appareil |
| POST /admin/v1/risk/device/unblock | Débloquer l'appareil |
| GET /admin/v1/risk/ip/list | Liste des IP |
| POST /admin/v1/risk/ip/block | Bloquer une IP |
| POST /admin/v1/risk/ip/whitelist | Liste blanche IP |
| POST /admin/v1/risk/ip/appeal | Appel d'IP |
| POST /admin/v1/risk/ip/recheck | Revérification d'IP |
| GET /admin/v1/risk/graph/clusters | Liste des clusters |
| GET /admin/v1/risk/graph/{userId} | Graphe de liens de l'utilisateur |
| GET /admin/v1/risk/clusters | Liste des clusters à risque |
| POST /admin/v1/risk/clusters/detect | Détection de grappes (même IP avec ≥5 comptes / même empreinte d'appareil avec ≥3 comptes sur les 7 derniers jours ; candidats uniquement, aucune écriture) |
| POST /admin/v1/risk/clusters/confirm | Confirmer manuellement une grappe et l'enregistrer |
| GET /admin/v1/risk/clusters/{hashid}/members | Liste des membres de la grappe (membres résolus depuis l'empreinte) |
| PUT /admin/v1/risk/clusters/{hashid}/status | Mettre à jour le statut de la grappe (1=en observation 2=traité 0=faux positif) |
| GET /admin/v1/risk/users | File des utilisateurs anormaux (filtrée par score de confiance et dernière détection) |
| GET /admin/v1/risk/users/{hashid}/timeline | Chronologie des risques de l'utilisateur (événements risque / parties / anti-triche fusionnés) |
| POST /admin/v1/risk/users/{hashid}/hold | Geler le solde disponible de l'utilisateur et journaliser l'action |

### 10.2 Gestion anti-triche (admin :8789)

| Point d'accès | Description |
|------|------|
| GET /admin/v1/anticheat/events | Liste des événements anti-triche |
| GET /admin/v1/anticheat/events/{hashid} | Détail de l'événement |
| POST /admin/v1/anticheat/events/{hashid}/review | Examiner l'événement |

### 10.3 Activités (admin :8789 + client :8792)

| Point d'accès | Description |
|------|------|
| GET /admin/v1/activities/list | Liste des activités (admin) |
| POST /admin/v1/activities/create | Créer une activité (admin) |
| PUT /admin/v1/activities/{hashid} | Mettre à jour une activité (admin) |
| DELETE /admin/v1/activities/{hashid} | Supprimer une activité (admin) |
| GET /api/v1/activities/list | Liste des activités (client) |
| GET /api/v1/activities/progress | Progression de participation (client) |
| GET /api/v1/activities/{hashid} | Détail de l'activité (client) |
| POST /api/v1/activities/{hashid}/checkin | Check-in (client) |

### 10.4 Groupes / Partage (client :8792 + admin :8789)

| Point d'accès | Description |
|------|------|
| POST /api/v1/groups | Créer un groupe |
| GET /api/v1/groups/{hashid} | Détail du groupe |
| GET /api/v1/groups/{hashid}/members | Liste des membres |
| POST /api/v1/groups/{hashid}/join | Rejoindre un groupe |
| POST /api/v1/groups/{hashid}/leave | Quitter un groupe |
| PUT /api/v1/groups/{hashid}/role | Rôle du membre |
| POST /api/v1/shares | Créer un lien de partage |
| POST /api/v1/shares/visit | Suivi des visites de partage |
| GET /admin/v1/groups | Liste des groupes (admin) |
| GET /admin/v1/groups/{hashid}/audit | Audit du groupe (admin) |
| GET /admin/v1/share/stats | Statistiques de partage (admin) |

### 10.5 Extensions de passerelle de paiement (L1)

| Passerelle | Description |
|------|------|
| Adyen | Nouvelle passerelle de paiement (dépôt / vérification du callback / crédit automatique) |
| GrabPay | Nouvelle passerelle de paiement (dépôt / vérification du callback / crédit automatique) |

### 10.6 VIP / Succès / Recherche / Reçus (admin :8789)

Niveaux VIP, configuration des succès, recherche globale et export de reçus (admin).

| Point d'accès | Description |
|------|------|
| GET /admin/v1/vip/level/list | Liste des niveaux VIP |
| POST /admin/v1/vip/level/create | Créer un niveau VIP (level doit être unique) |
| PUT /admin/v1/vip/level/{hashid} | Mettre à jour un niveau VIP |
| DELETE /admin/v1/vip/level/{hashid} | Supprimer un niveau VIP (refusé si des utilisateurs ont ce niveau) |
| GET /admin/v1/achievement/list | Liste des succès |
| POST /admin/v1/achievement/create | Créer un succès (key en double refusée) |
| PUT /admin/v1/achievement/{hashid} | Mettre à jour un succès |
| DELETE /admin/v1/achievement/{hashid} | Supprimer un succès |
| GET /admin/v1/search | Recherche globale (?q= mot-clé, type=game ou user) |
| POST /admin/v1/export/receipt | Exporter un reçu PDF (type=deposit ou withdraw, plus order_id) |
