# Documentation des interfaces
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · **Français** · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Documentation interactive en ligne (avec débogage en ligne) :
- Métier côté C : http://localhost:8788/apidoc/
- Administration : http://localhost:8787/apidoc/
- Mot de passe : admin123

## 1. Conventions

### 1.1 URL de base

| Extrémité | Adresse |
|----|------|
| Administration | `http://localhost:8787` |
| Métier côté C | `http://localhost:8788` |

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

## 2. Interfaces côté C (service :8788)

### 2.1 Authentification

#### POST /api/v1/auth/register — Inscription d'un utilisateur

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

#### POST /api/v1/auth/login — Connexion d'un utilisateur

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

Erreurs : 401 nom d'utilisateur ou mot de passe incorrect / compte désactivé

#### POST /api/v1/auth/refresh — Rafraîchissement du Token

```
请求: (Authorization: Bearer <refresh_token>)

响应: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 Portefeuille

#### GET /api/v1/wallet/info — Informations du portefeuille

```
需认证: 是

响应: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/v1/wallet/transactions — Historique des transactions

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

### 2.3 Recharge

#### POST /api/v1/deposit/create — Création d'une commande de recharge

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

Valeurs possibles de currency : USD / CNY / EUR

checkout_url : lien de redirection de la passerelle de paiement (rempli à la création de la commande) ; expires_at : expiration du lien de paiement (1 heure après la création)

#### GET /api/v1/deposit/orders — Historique des recharges

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

Valeurs possibles de status : pending / paid / confirmed / cancelled

### 2.4 Échange

#### POST /api/v1/exchange/quote — Demande de cotation

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

direction : in=achat de devises de jeu / out=vente de devises de jeu

#### POST /api/v1/exchange/buy — Achat de devises de jeu

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

Erreurs : 422 solde de devises de plateforme insuffisant / 404 jeu indisponible

#### POST /api/v1/exchange/sell — Vente de devises de jeu

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

Erreurs : 422 solde de devises de jeu insuffisant

#### GET /api/v1/exchange/records — Historique des échanges

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

### 2.5 Retrait

#### POST /api/v1/withdraw/apply — Demande de retrait

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

### 2.6 Jeux

#### GET /api/v1/game/list — Liste des jeux

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

Valeurs possibles de type : self / third_party

#### GET /api/v1/game/{hashid} — Détail d'un jeu

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

#### POST /api/v1/game/launch — Lancement d'un jeu

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

### 2.7 Connexion tierce OAuth

Prend en charge 7 plateformes : Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/v1/auth/oauth/{provider} — Obtention de l'URL d'autorisation

```
参数: provider = google / facebook / apple / twitter / microsoft / linkedin / github

响应: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/v1/auth/oauth/{provider}/callback — Callback OAuth

```
请求: { "code": "授权码", "state": "防CSRF状态" }

响应: {
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

#### POST /api/v1/user/identity/apply — Soumission de la certification

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

### 2.9 Paiement

#### POST /api/v1/payment/callback — Callback de paiement (public)

```
请求: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

响应: { "message": "success" }
```

status : success / failed

Valeurs de provider : stripe / paypal / nowpayments / coinbase / skrill / neteller / paysafecard / paytm / mercadopago / astropay / paypay / kakaopay / gcash (toss / mpesa / paystack bientôt disponibles)

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
| toss | Corée du Sud (bientôt) | — | KRW |
| mpesa | Kenya / Tanzanie, etc. (bientôt) | — | KES / TZS |
| paystack | Nigéria (bientôt) | — | NGN |

#### GET /api/v1/payment/methods — Modes de paiement disponibles (public)

```
响应: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

Filtré par pays de l'utilisateur (X-Language/Accept-Language → code pays) : countries vide ou contenant * signifie visible mondialement ; trié selon la préférence de méthodes de paiement de country_config du pays

### 2.10 Historique de jeu

#### GET /api/v1/game/play-logs — Liste des historiques de jeu

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

#### GET /api/v1/game/play-log/{hashid} — Détail d'un historique de jeu

```
需认证: 是
响应: { 完整记录，含 session_id / game_amount_before / after 等 }
```

### 2.12 Classements

#### GET /api/v1/leaderboard/list — Liste des classements

```
响应: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/v1/leaderboard/{hashid} — Détail d'un classement

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

#### GET /api/v1/coupon/available — Coupons à réclamer

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/v1/coupon/claim — Réclamer un coupon

```
需认证: 是
请求: { "coupon_id": "hashid" }
响应: { "coupon": { ... } }
```

#### GET /api/v1/coupon/my — Mes coupons

```
需认证: 是
参数: ?status=unused
响应: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 Configuration des pays

#### GET /api/v1/country/list — Liste des pays

```
响应: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/v1/country/{code} — Détail d'un pays

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

#### GET /api/v1/notification/list — Liste des notifications

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

#### GET /api/v1/notification/unread-count — Nombre de non lues

```
需认证: 是
响应: { "count": 3 }
```

#### POST /api/v1/notification/read — Marquer comme lues

```
需认证: 是
请求: { "id": "hashid" }  // 不传=全部已读
```

### 2.17 Parrainage

#### GET /api/v1/referral/my-code — Mon code de parrainage

```
需认证: 是
响应: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/v1/referral/apply — Utiliser un code de parrainage

```
需认证: 是
请求: { "code": "ABC12345" }
响应: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/v1/user/2fa/status — État 2FA

```
需认证: 是
响应: { "enabled": false }
```

#### POST /api/v1/user/2fa/setup — Configurer le 2FA

```
需认证: 是
响应: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/v1/user/2fa/enable — Activer le 2FA

```
需认证: 是
请求: { "code": "123456" }
响应: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/v1/2fa/verify — Vérifier le 2FA (public)

```
请求: { "user_id": "hashid", "code": "123456" }
响应: { "valid": true }
```

### 2.19 Recherche

#### GET /api/v1/search — Recherche globale

```
参数: ?q=keyword&type=game&page=1&per_page=20
响应: { "list": [...], "total": 100 }
```

#### GET /api/v1/game/suggest — Suggestions de recherche

```
参数: ?q=shoot
响应: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 Langues

#### GET /api/v1/language/list — Liste des langues disponibles

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

#### POST /api/v1/language/switch — Changer de langue

```
请求: { "locale": "zh-CN" }
响应: { "locale": "zh-CN" }
```

Valeurs possibles de locale : en-US / zh-CN / ja-JP / ko-KR

### 2.8 Utilisateur

#### GET /api/v1/user/profile — Informations personnelles

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

#### PUT /api/v1/user/profile — Modifier le profil

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

Valeurs possibles de language : en-US / zh-CN / ja-JP / ko-KR

### 2.9 Annonces

#### GET /api/v1/announcement/list — Liste des annonces

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

#### GET /api/v1/announcement/detail/{hashid} — Détail d'une annonce

```
响应: {
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

响应: {
  "total_games": 12,
  "total_users": 1500,
  "today_game_plays": 320,
  "active_users_7d": 450
}
```

## 3. Interfaces d'administration (admin :8787)

### 3.1 Tableau de bord de la plateforme

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

### 3.2 Gestion des jeux

#### GET /admin/game/list — Liste des jeux

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

#### POST /admin/game/create — Créer un jeu

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

Valeurs possibles de type : self / third_party

#### PUT /admin/game/{hashid} — Modifier un jeu

```
需认证: 是

请求: {
  "name": "新名称",
  "status": 1
  // 可部分更新，字段同 create
}

响应: { "message": "更新成功" }
```

#### DELETE /admin/game/{hashid} — Supprimer un jeu

```
需认证: 是
响应: { "message": "删除成功" }
```

#### POST /admin/game/currency/manage — Gérer les devises

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

### 3.3 Gestion des retraits

#### GET /admin/withdraw/orders — Liste des commandes de retrait

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

#### PUT /admin/withdraw/review — Valider un retrait

```
需认证: 是

请求: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

响应: { "message": "已通过" }
```

action : approve=approuver / reject=refuser (en cas de refus, les devises de plateforme sont automatiquement retournées)

Erreurs : 422 l'état de la commande n'est pas « en attente de validation »

#### PUT /admin/withdraw/switch — Interrupteur global des retraits

```
需认证: 是

请求: { "enabled": 1 }

响应: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/withdraw/limits/set — Définir les limites de retrait

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

### 3.4 Gestion des utilisateurs de la plateforme

#### GET /admin/platform/user/list — Liste des utilisateurs côté C

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

#### GET /admin/platform/user/{hashid} — Détail d'un utilisateur

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

#### PUT /admin/platform/user/{hashid} — Modifier/bannir un utilisateur

```
需认证: 是

请求: {
  "status": 0,         // 0=禁用 1=启用
  "nickname": "..."    // 可选
}

响应: { "message": "更新成功" }
```

### 3.5 Gestion des paiements

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

#### POST /admin/payment/method/toggle — Activer/désactiver un mode de paiement

```
需认证: 是

请求: { "id": "aB3xK...", "status": 0 }

响应: { "message": "已更新" }
```

### 3.6 Gestion des annonces

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

#### POST /admin/announcement/create — Publier une annonce

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

### 3.7 Validation KYC

#### GET /admin/identity/list — Liste KYC

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

#### PUT /admin/identity/review — Valider le KYC

```
需认证: 是

请求: { "id": "hashid", "action": "approve", "note": "" }

响应: { "message": "Approved" }
```

action : approve / reject

### 3.8 Gestion des serveurs de jeux

#### GET /admin/game/server/list — Liste des serveurs

```
需认证: 是
参数: ?game_id=hashid

响应: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/game/server/create — Créer un serveur

```
需认证: 是
请求: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
响应: { "id": "hashid" }
```

#### PUT /admin/game/server/{hashid} — Modifier un serveur

```
需认证: 是
请求: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/game/server/{hashid} — Supprimer un serveur

```
需认证: 是
```

### 3.9 Gestion des limites de retrait par paliers

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

#### PUT /admin/withdraw/limits/{hashid} — Mettre à jour les limites

```
需认证: 是

请求: { "single_max": "10000.0000", "fee_pct": "0.25" }
// 可部分更新
```

### 3.11 Gestion des catégories de jeux

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

#### PUT /admin/game/category/{hashid} — Modifier une catégorie

#### DELETE /admin/game/category/{hashid} — Supprimer une catégorie

#### POST /admin/game/category/assign — Attribuer des jeux

```
需认证: 是
请求: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 Gestion des classements

#### GET /admin/leaderboard/list — Liste des classements

```
需认证: 是
响应: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/leaderboard/create — Créer un classement

```
需认证: 是
请求: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(可选)" }
```

#### PUT /admin/leaderboard/{hashid} — Modifier un classement

#### DELETE /admin/leaderboard/{hashid} — Supprimer un classement

#### POST /admin/leaderboard/{hashid}/refresh — Rafraîchir le cache

### 3.13 Gestion des coupons

#### GET /admin/coupon/list — Liste des coupons

#### POST /admin/coupon/create — Créer un coupon

```
需认证: 是
请求: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/coupon/{hashid} — Modifier (tant que non réclamé)

#### DELETE /admin/coupon/{hashid} — Supprimer

#### GET /admin/coupon/{hashid}/stats — Statistiques de réclamation

```
响应: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 Gestion de la configuration des pays

#### GET /admin/country/config/list — Liste des configurations de pays

#### POST /admin/country/config/create — Créer une configuration de pays

```
需认证: 是
请求: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/country/config/{hashid} — Modifier une configuration de pays

### 3.15 Export de données

#### POST /admin/export/users — Exporter les utilisateurs côté C

```
需认证: 是
参数(JSON): { "status": 1 }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

#### POST /admin/export/transactions — Exporter les transactions de la plateforme

```
需认证: 是
参数(JSON): { "type": "deposit" }   // 可选筛选

响应: Excel 文件下载 (xlsx)
```

### 3.16 Analyse de données (agrégation MySQL en temps réel)

Tous les points d'extrémité nécessitent une authentification (AdminAuth + AdminPermission) ; les données sont agrégées en temps réel depuis MySQL, sans dépendance à ClickHouse.

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/analytics/overview | Aperçu de la plateforme (aujourd'hui/7 derniers jours) |
| GET | /admin/analytics/game-ranking | Classement des jeux (?days=7) |
| GET | /admin/analytics/dau-trend | Tendance DAU (?days=30) |
| GET | /admin/analytics/hourly-trend | Tendance horaire |
| GET | /admin/analytics/action-distribution | Répartition des comportements |
| GET | /admin/analytics/revenue | Analyse des revenus |
| GET | /admin/analytics/conversion | Taux de conversion des jeux |
| GET | /admin/analytics/probability | Probabilités conjointes/conditionnelles |
| GET | /admin/analytics/retention | Analyse de rétention D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Entonnoir de conversion |
| GET | /admin/analytics/arpu | Tendance ARPU/ARPPU |
| GET | /admin/analytics/economy | Indicateurs économiques des devises de jeu |

### 3.17 Gestion des tickets

Tous les points d'extrémité nécessitent une authentification (AdminAuth + AdminPermission).

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/ticket/list | Liste des tickets (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | Détail d'un ticket (avec réponses) |
| POST | /admin/ticket/{hashid}/reply | Répondre à un ticket |
| POST | /admin/ticket/{hashid}/close | Clôturer un ticket |
| POST | /admin/ticket/{hashid}/assign | Attribuer un traitement (admin_id) |

### 3.18 Gestion de la configuration CDN

Tous les points d'extrémité nécessitent une authentification (AdminAuth + AdminPermission).

| Méthode | Chemin | Description | Authentification |
|------|------|------|------|
| GET | /admin/cdn/provider/list | Liste des fournisseurs CDN (les identifiants ne sont pas renvoyés) | AdminAuth + RBAC: cdn |
| POST | /admin/cdn/provider/toggle | Activer/désactiver le fournisseur {id, status} | AdminAuth + RBAC: cdn |
| POST | /admin/cdn/provider/create | Créer {name, provider, config(JSON), status, sort}, vérification d'unicité de provider | AdminAuth + RBAC: cdn |
| PUT | /admin/cdn/provider/{hashid} | Modifier (config vide = inchangé) | AdminAuth + RBAC: cdn |
| DELETE | /admin/cdn/provider/{hashid} | Supprimer | AdminAuth + RBAC: cdn |
| POST | /admin/cdn/provider/test | Test de connectivité HeadBucket {id} | AdminAuth + RBAC: cdn |

### 3.19 Rapports de données

Tous les points d'extrémité nécessitent une authentification (AdminAuth + AdminPermission).

| Méthode | Chemin | Description | Authentification |
|------|------|------|------|
| GET | /admin/report/summary | Récapitulatif des rapports (nouveaux utilisateurs/dépôts/retraits/échanges/parties) | AdminAuth + RBAC: report |
| GET | /admin/report/daily | Rapport quotidien (agrégation par jour, jours sans données remplis à 0) | AdminAuth + RBAC: report |
| GET | /admin/report/export | Export du rapport quotidien en CSV (UTF-8 BOM) | AdminAuth + RBAC: report |

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

#### POST /api/provider/bet — Notification de mise

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

#### POST /api/provider/settle — Notification de règlement

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

#### POST /api/provider/refund — Notification de remboursement

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

#### GET /api/v1/ticket/list — Liste des tickets

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

type : deposit / withdraw / game / account / other
status : open / waiting / replied / closed

#### POST /api/v1/ticket/create — Créer un ticket

```
需认证: 是
请求: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
响应: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/v1/ticket/{hashid} — Détail d'un ticket

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

#### POST /api/v1/ticket/{hashid}/reply — Répondre à un ticket

```
需认证: 是
请求: { "content": "已核实，将在24小时内处理" }
响应: { "code": 0, "message": "Reply sent" }
```

### 7.3 API de vérification d'email

#### POST /api/v1/verify/send-email — Envoi du code de vérification d'email

```
需认证: 是
请求: { "email": "user@example.com" }
响应: { "code": 0, "message": "Verification code sent" }
错误: 429 请60秒后重试
```

#### POST /api/v1/verify/confirm-email — Confirmation de l'email

```
需认证: 是
请求: { "code": "123456" }
响应: { "code": 0, "message": "Email verified" }
错误: 422 验证码无效或已过期
```

### 7.4 API VIP

#### GET /api/v1/user/vip-status — État VIP

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

### 7.5 API de succès

#### GET /api/v1/user/achievements — Liste des succès

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

### 7.6 Nouvelles API d'administration

#### GET /admin/ticket/list — Liste des tickets

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

#### POST /admin/ticket/{hashid}/reply — Répondre à un ticket

```
需认证: 是
请求: { "content": "已处理" }
响应: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/ticket/{hashid}/close — Clôturer un ticket

```
需认证: 是
响应: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/ticket/{hashid}/assign — Attribuer un traitement

```
需认证: 是
请求: { "admin_id": 1234567890 }
响应: { "code": 0, "message": "Assigned" }
```

#### GET /admin/analytics/retention — Analyse de rétention

```
需认证: 是
参数: ?days=30
响应: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/analytics/funnel — Entonnoir de conversion

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

#### GET /admin/analytics/arpu — Tendance ARPU/ARPPU

```
需认证: 是
参数: ?days=30
响应: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/analytics/economy — Indicateurs économiques des devises de jeu

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


#### GET /admin/cdn/provider/list — Liste des fournisseurs CDN (les identifiants ne sont pas renvoyés)

```
需认证: 是
响应: { "list": [ { "id": "...", "name": "...", "provider": "cloudflare", "status": 1, "sort": 0 } ] }
```

#### POST /admin/cdn/provider/toggle — Activer/désactiver le fournisseur {id, status}

```
需认证: 是
请求: { "id": "...", "status": 1 }
响应: { "code": 0, "message": "..." }
```

#### POST /admin/cdn/provider/create — Créer {name, provider, config(JSON), status, sort}, vérification d'unicité de provider

```
需认证: 是
请求: { "name": "...", "provider": "aliyun", "config": "{...}", "status": 1, "sort": 0 }
响应: { "code": 0, "data": { "id": "..." } }
```

#### PUT /admin/cdn/provider/{hashid} — Modifier (config vide = inchangé)

```
需认证: 是
请求: { "name": "...", "config": "" }
响应: { "code": 0, "message": "..." }
```

#### DELETE /admin/cdn/provider/{hashid} — Supprimer

```
需认证: 是
响应: { "code": 0, "message": "..." }
```

#### POST /admin/cdn/provider/test — Test de connectivité HeadBucket {id}

```
需认证: 是
请求: { "id": "..." }
响应: { "code": 0, "data": { "ok": true } }
```
#### GET /admin/report/summary — Récapitulatif des rapports

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


#### GET /admin/report/daily — Rapport quotidien

```
需认证: 是
参数: ?start=Y-m-d&end=Y-m-d
响应: {
  "start": "2026-08-01", "end": "2026-08-31",
  "rows": [ { "date": "2026-08-01", "new_users": 12, "deposit_amount": "500.0000", "deposit_count": 4, "withdraw_amount": "100.0000", "withdraw_count": 1, "exchange_amount": "300.0000", "play_count": 150 } ]
}
```


#### GET /admin/report/export — Export CSV du rapport quotidien

```
需认证: 是
参数: ?start=Y-m-d&end=Y-m-d&format=excel
响应: CSV 文件（UTF-8 BOM），文件名 report_{start}_{end}.csv，Excel 可直接打开
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
需认证: 是
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/v1/friend/requests — Demandes en attente
```
需认证: 是
响应: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/v1/friend/request — Envoyer une demande d'ami
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### POST /api/v1/friend/accept — Accepter une demande
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/v1/friend/reject — Refuser une demande
```
需认证: 是
请求: { "request_id": "hashid" }
```

#### POST /api/v1/friend/remove — Supprimer un ami
```
需认证: 是
请求: { "friend_id": "hashid" }
```

#### GET /api/v1/friend/search — Rechercher des utilisateurs
```
需认证: 是
参数: ?q=username
响应: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 API de chat

#### GET /api/v1/chat/conversations — Liste des conversations
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

#### GET /api/v1/chat/messages/{peerHashid} — Liste des messages
```
需认证: 是
参数: ?page=1&per_page=50
响应: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
自动标记对端发来的未读消息为已读
```

#### POST /api/v1/chat/send — Envoyer un message
```
需认证: 是
请求: { "to_user_id": "hashid", "content": "Hello!" }
错误: 403 非好友不可发
```

#### GET /api/v1/chat/unread-total — Total des non lus
```
需认证: 是
响应: { "count": 5 }
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
需认证: 是
响应: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/v1/webhook/register — Enregistrer un abonnement
```
需认证: 是
请求: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
可用事件: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/v1/webhook/delete — Supprimer un abonnement
```
需认证: 是
请求: { "id": "hook_id" }
```

### 7.10 API d'analyse avancée

#### GET /admin/analytics/retention — Analyse de rétention
```
需认证: 是
响应: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/analytics/funnel — Entonnoir de conversion
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

#### GET /admin/analytics/arpu — Tendance ARPU/ARPPU
```
需认证: 是
参数: ?days=30
响应: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/analytics/economy — Indicateurs économiques des jeux
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


### 7.11 API de tournois

#### GET /api/v1/tournament/list — Liste des tournois
```
参数: ?status=active|upcoming|ended&page=1&per_page=20
响应: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/v1/tournament/{hashid} — Détail d'un tournoi
```
响应: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/v1/tournament/{hashid}/join — S'inscrire à un tournoi
```
需认证: 是
错误: 422 已报名 / 400 已开始或已满员 / 503 FeatureFlag关闭
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

### 10.1 Gestion des risques (admin :8787)

| Point d'accès | Description |
|------|------|
| GET /admin/risk/dashboard | Vue d'ensemble du tableau de bord des risques |
| GET /admin/risk/overview | Indicateurs d'ensemble des risques |
| GET /admin/risk/hit-trend | Tendance des déclenchements |
| GET /admin/risk/action-distribution | Répartition des actions |
| GET /admin/risk/rule-performance | Performance des règles |
| GET /admin/risk/rule/list | Liste des règles |
| POST /admin/risk/rule/create | Créer une règle |
| PUT /admin/risk/rule/{hashid} | Mettre à jour une règle |
| POST /admin/risk/rule/{hashid}/toggle | Activer/désactiver une règle |
| POST /admin/risk/rule/test | Tester une règle |
| GET /admin/risk/event/list | Liste des événements à risque |
| GET /admin/risk/event/{hashid} | Détail de l'événement |
| POST /admin/risk/event/{hashid}/handle | Traiter l'événement |
| GET /admin/risk/device/list | Liste des empreintes d'appareils |
| POST /admin/risk/device/block | Bloquer l'appareil |
| POST /admin/risk/device/unblock | Débloquer l'appareil |
| GET /admin/risk/ip/list | Liste des IP |
| POST /admin/risk/ip/block | Bloquer une IP |
| POST /admin/risk/ip/whitelist | Liste blanche IP |
| POST /admin/risk/ip/appeal | Appel d'IP |
| POST /admin/risk/ip/recheck | Revérification d'IP |
| GET /admin/risk/graph/clusters | Liste des clusters |
| GET /admin/risk/graph/{userId} | Graphe de liens de l'utilisateur |
| GET /admin/risk/clusters | Liste des clusters à risque |

### 10.2 Gestion anti-triche (admin :8787)

| Point d'accès | Description |
|------|------|
| GET /admin/anticheat/events | Liste des événements anti-triche |
| GET /admin/anticheat/events/{hashid} | Détail de l'événement |
| POST /admin/anticheat/events/{hashid}/review | Examiner l'événement |

### 10.3 Activités (admin :8787 + client :8788)

| Point d'accès | Description |
|------|------|
| GET /admin/activities/list | Liste des activités (admin) |
| POST /admin/activities/create | Créer une activité (admin) |
| PUT /admin/activities/{hashid} | Mettre à jour une activité (admin) |
| DELETE /admin/activities/{hashid} | Supprimer une activité (admin) |
| GET /api/v1/activities/list | Liste des activités (client) |
| GET /api/v1/activities/progress | Progression de participation (client) |
| GET /api/v1/activities/{hashid} | Détail de l'activité (client) |
| POST /api/v1/activities/{hashid}/checkin | Check-in (client) |

### 10.4 Groupes / Partage (client :8788 + admin :8787)

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
| GET /admin/groups | Liste des groupes (admin) |
| GET /admin/groups/{hashid}/audit | Audit du groupe (admin) |
| GET /admin/share/stats | Statistiques de partage (admin) |

### 10.5 Extensions de passerelle de paiement (L1)

| Passerelle | Description |
|------|------|
| Adyen | Nouvelle passerelle de paiement (dépôt / vérification du callback / crédit automatique) |
| GrabPay | Nouvelle passerelle de paiement (dépôt / vérification du callback / crédit automatique) |
