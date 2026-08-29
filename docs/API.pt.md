# Documentação da API
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · **Português** · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Documentação online interativa (suporta teste em tempo real):
- C-side: http://localhost:8788/apidoc/
- Painel administrativo: http://localhost:8787/apidoc/
- Senha: admin123

## 1. Convenções

### 1.1 URL base

| Endpoint | Endereço |
|----|------|
| Painel administrativo | `http://localhost:8787` |
| C-side | `http://localhost:8788` |

### 1.2 Cabeçalhos comuns de requisição

```
Content-Type: application/json
API-Version: v1
Authorization: Bearer <token>    (interfaces que exigem autenticação)
```

### 1.3 Formato de resposta unificado

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Significado |
|------|------|
| 0 | Sucesso |
| 400 | Erro de parâmetros |
| 401 | Não autenticado (Token ausente/expirado/inválido) |
| 403 | Sem permissão |
| 404 | Recurso não existe |
| 422 | Falha de validação |
| 429 | Requisições em excesso (rate limit disparado) |
| 500 | Erro de servidor |

### 1.4 Codificação de IDs

Todos os IDs em requisições e respostas das interfaces são strings codificadas com Hashids, não valores BIGINT originais.

```
Externo: aB3xK9mW2pQ7rT5v  (string hashid)
Interno: 1750123456789      (Snowflake BIGINT)
```

### 1.5 Formato de paginação

```
Requisição: ?page=1&per_page=20

Resposta: {
  "list": [...],
  "total": 150,
  "page": 1,
  "per_page": 20
}
```

## 2. Interfaces C-side (service :8788)

### 2.1 Autenticação

#### POST /api/auth/register — Registro de usuário

```
Requisição: {
  "username": "player1",
  "password": "123456",
  "email": "player@example.com"     // opcional
}

Resposta: {
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

#### POST /api/auth/login — Login de usuário

```
Requisição: {
  "username": "player1",
  "password": "123456"
}

Resposta: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "...", ... }
}
```

Erro: 401 nome de usuário ou senha incorretos / conta desabilitada

#### POST /api/auth/refresh — Refresh do Token

```
Requisição: (Authorization: Bearer <refresh_token>)

Resposta: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG..."
}
```

### 2.2 Carteira

#### GET /api/wallet/info — Informações da carteira

```
Requer autenticação: sim

Resposta: {
  "balance": "100.5000",
  "frozen_balance": "0.0000",
  "total_earned": "500.0000",
  "total_spent": "399.5000"
}
```

#### GET /api/wallet/transactions — Registro de transações

```
Requer autenticação: sim
Parâmetros: ?page=1&per_page=20&type=deposit    (type opcional)

Resposta: {
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

Valores possíveis de type: deposit / withdraw / exchange_in / exchange_out / game_earn / game_spend
```

### 2.3 Depósito

#### POST /api/deposit/create — Criar ordem de depósito

```
Requer autenticação: sim

Requisição: {
  "amount": "10.00",
  "currency": "USD",
  "payment_method_id": "aB3xK..."
}

Resposta: {
  "order_id": "aB3xK...",
  "order_no": "DEP202605221030000123",
  "amount": "10.00",
  "platform_amount": "10.0000",
  "checkout_url": "https://checkout.stripe.com/...",
  "expires_at": "2026-05-22 11:30:00"
}
```

Valores possíveis de currency: USD / CNY / EUR

checkout_url: link de redirecionamento do gateway de pagamento (preenchido na criação do pedido); expires_at: expiração do link de pagamento (1 hora após a criação)

#### GET /api/deposit/orders — Registros de depósito

```
Requer autenticação: sim
Parâmetros: ?page=1&per_page=20

Resposta: {
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

Valores possíveis de status: pending / paid / confirmed / cancelled

### 2.4 Troca

#### POST /api/exchange/quote — Cotação

```
Requer autenticação: sim

Requisição: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "direction": "in",
  "platform_amount": "10.0000"
}

Resposta: {
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000",
  "spread_pct": "5.00%"
}
```

direction: in=comprar moeda de jogo / out=vender moeda de jogo

#### POST /api/exchange/buy — Comprar moeda de jogo

```
Requer autenticação: sim

Requisição: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "10.0000"
}

Resposta: {
  "exchange_id": "aB3xK...",
  "platform_amount": "10.0000",
  "game_amount": "950.0000",
  "spread_fee": "50.0000",
  "rate": "100.00000000"
}
```

Erro: 422 saldo insuficiente de moeda da plataforma / 404 jogo indisponível

#### POST /api/exchange/sell — Vender moeda de jogo

```
Requer autenticação: sim

Requisição: {
  "game_id": "aB3xK...",
  "currency_id": "aB3xK...",
  "platform_amount": "950.0000"
}

Resposta: {
  "exchange_id": "aB3xK...",
  "platform_amount": "9.0250",
  "game_amount": "950.0000",
  "spread_fee": "0.4750",
  "rate": "100.00000000"
}
```

Erro: 422 saldo insuficiente de moeda de jogo

#### GET /api/exchange/records — Registros de troca

```
Requer autenticação: sim
Parâmetros: ?page=1&per_page=20

Resposta: {
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

### 2.5 Saque

#### POST /api/withdraw/apply — Solicitação de saque

```
Requer autenticação: sim

Requisição: {
  "platform_amount": "50.0000",
  "method": "paypal",
  "account_info": "user@paypal.com"
}

Resposta: {
  "order_id": "...",
  "order_no": "WTH202605221030000456",
  "status": "approved"
}
```

Valores possíveis de method: paypal / bank / crypto

status:
- approved: aprovado automaticamente (valor < auto_approve_threshold)
- pending: aguardando revisão (valor >= auto_approve_threshold)

Erros:
- 403 saque temporariamente desativado (interruptor global desligado)
- 400 abaixo do valor mínimo de saque
- 400 excedeu o limite diário de saque
- 400 saldo insuficiente

#### GET /api/withdraw/orders — Registros de saque

```
Requer autenticação: sim
Parâmetros: ?page=1&per_page=20

Resposta: {
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

### 2.6 Jogos

#### GET /api/game/list — Lista de jogos

```
Parâmetros: ?page=1&per_page=20&keyword=射击&type=self

Resposta: {
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

Valores possíveis de type: self / third_party

#### GET /api/game/{hashid} — Detalhes do jogo

```
Resposta: {
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

#### POST /api/game/launch — Iniciar jogo

```
Requer autenticação: sim

Requisição: { "game_id": "aB3xK..." }

Resposta: {
  "id": "...",
  "name": "射击大师",
  "type": "self",
  "api_endpoint": "https://game.example.com/play"
}
```

### 2.7 Login OAuth de terceiros

Suporta 7 plataformas: Google / Facebook / Apple / X(Twitter) / Microsoft / LinkedIn / GitHub

#### GET /api/auth/oauth/{provider} — Obter URL de autorização

```
Parâmetros: provider = google / facebook / apple / twitter / microsoft / linkedin / github

Resposta: {
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?..."
}
```

#### POST /api/auth/oauth/{provider}/callback — Callback do OAuth

```
Requisição: { "code": "授权码", "state": "防CSRF状态" }

Resposta: {
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "user": { "id": "...", "username": "google_abc123", ... },
  "is_new": true
}
```

is_new: true=usuário recém-registrado / false=conta existente vinculada

### 2.8 KYC de identidade real

#### GET /api/user/identity/status — Status da verificação

```
Requer autenticação: sim

Resposta: {
  "status": "approved",          // not_submitted / pending / approved / rejected
  "real_name": "J***",
  "id_type": "id_card",
  "review_note": "",
  "submitted_at": "2026-05-22 10:00:00",
  "reviewed_at": "2026-05-23 14:00:00"
}
```

#### POST /api/user/identity/apply — Enviar verificação

```
Requer autenticação: sim

Requisição: {
  "real_name": "John Doe",
  "id_type": "id_card",
  "id_number": "123456789",
  "id_front_photo": "https://...",
  "selfie_photo": "https://..."
}

Resposta: { "message": "KYC submitted successfully" }
```

### 2.9 Pagamento

#### POST /api/payment/callback — Callback de pagamento (público)

```
Requisição: {
  "order_no": "DEP202605221030000123",
  "transaction_id": "txn_abc123",
  "status": "success"
}

Resposta: { "message": "success" }
```

status: success / failed

Valores de provider: stripe / paypal / nowpayments / coinbase / skrill / neteller / paysafecard / paytm / mercadopago / astropay / paypay / kakaopay / gcash (toss / mpesa / paystack em breve)

| provider | Região | Esquema de assinatura | Moedas suportadas |
|----------|--------|-----------------------|-------------------|
| stripe | Global (125+ métodos de pagamento locais, incl. APM Alipay/WeChat Pay) | Webhook HMAC-SHA256 | USD / CNY / EUR |
| paypal | 200+ mercados mundiais | Verificação de webhook (verify-webhook-signature) | USD / CNY / EUR e outras moedas fiduciárias |
| nowpayments | Global (cripto) | IPN HMAC-SHA512 | USDT TRC20 / ERC20 |
| coinbase | Global (cripto) | Webhook HMAC-SHA256 (secret base64) | USDC / BTC / ETH |
| skrill | Europa / Global | Verificação MD5 do secret word | EUR e outras moedas fiduciárias |
| neteller | Europa / Global | Comparação do campo secret key | EUR e outras moedas fiduciárias |
| paysafecard | Europa (DE / AT / CH etc.) | X-Signature HMAC-SHA256 | EUR e outras moedas fiduciárias |
| paytm | Índia | SHA256 + AES-128-CBC | INR |
| mercadopago | América Latina (BR / MX etc.) | X-Signature (ts,v1) HMAC-SHA256 | BRL / MXN e outras moedas fiduciárias |
| astropay | América Latina (BR etc.) | MD5(order_id.amount.status.secret) | BRL e outras moedas fiduciárias |
| paypay | Japão | PayPay-Signature HMAC-SHA256 | JPY |
| kakaopay | Coreia do Sul | Sem webhook (fluxo em duas etapas ready/approve) | KRW |
| gcash | Filipinas | Paymongo-Signature HMAC-SHA256 | PHP |
| toss | Coreia do Sul (em breve) | — | KRW |
| mpesa | Quênia / Tanzânia etc. (em breve) | — | KES / TZS |
| paystack | Nigéria (em breve) | — | NGN |

#### GET /api/payment/methods — Métodos de pagamento disponíveis (público)

```
Resposta: {
  "list": [
    { "id": "...", "name": "Stripe", "type": "fiat", "provider": "stripe", "min_amount": "10.00", "max_amount": "5000.00" }
  ]
}
```

Filtrado por país do usuário (X-Language/Accept-Language → código do país): countries vazio ou contendo * significa visível globalmente; ordenado pela preferência de métodos de pagamento de country_config do país

### 2.10 Registros de partidas

#### GET /api/game/play-logs — Lista de registros de partidas

```
Requer autenticação: sim
Parâmetros: ?page=1&per_page=20&game_id=xxx&action=start

Resposta: {
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

#### GET /api/game/play-log/{hashid} — Detalhes do registro de partida

```
Requer autenticação: sim
Resposta: { 完整记录，含 session_id / game_amount_before / after 等 }
```

### 2.12 Rankings

#### GET /api/leaderboard/list — Lista de rankings

```
Resposta: {
  "list": [
    { "id": "...", "name": "全服累计收入榜", "type": "total", "metric": "earned" }
  ]
}
```

#### GET /api/leaderboard/{hashid} — Detalhes do ranking

```
Resposta: {
  "id": "...",
  "name": "全服累计收入榜",
  "type": "total",
  "rankings": [
    { "rank": 1, "user_id": "...", "score": "50000.0000" }
  ]
}
```

### 2.13 Cupons

#### GET /api/coupon/available — Cupons resgatáveis

```
Requer autenticação: sim
Resposta: { "list": [{ "id": "...", "name": "新人礼包", "type": "fixed", "value": "10.0000" }] }
```

#### POST /api/coupon/claim — Resgatar cupom

```
Requer autenticação: sim
Requisição: { "coupon_id": "hashid" }
Resposta: { "coupon": { ... } }
```

#### GET /api/coupon/my — Meus cupons

```
Requer autenticação: sim
Parâmetros: ?status=unused
Resposta: { "list": [{ "id": "...", "coupon": {...}, "status": "unused" }] }
```

### 2.14 Configuração de países

#### GET /api/country/list — Lista de países

```
Resposta: {
  "list": [
    { "country_code": "US", "currency": "USD", "min_deposit": "1.0000" }
  ]
}
```

#### GET /api/country/{code} — Detalhes do país

```
Resposta: {
  "country_code": "US",
  "currency": "USD",
  "payment_methods": ["stripe", "paypal", "crypto"],
  "withdraw_methods": ["paypal", "bank", "crypto"],
  "min_deposit": "1.0000"
}
```

### 2.16 Notificações

#### GET /api/notification/list — Lista de notificações

```
Requer autenticação: sim
Parâmetros: ?page=1&per_page=20&is_read=0

Resposta: {
  "list": [
    { "id": "...", "type": "deposit", "title": "Deposit Received", "is_read": 0, "created_at": "..." }
  ],
  "total": 5, "page": 1, "per_page": 20
}
```

#### GET /api/notification/unread-count — Contagem de não lidas

```
Requer autenticação: sim
Resposta: { "count": 3 }
```

#### POST /api/notification/read — Marcar como lida

```
Requer autenticação: sim
Requisição: { "id": "hashid" }  // não enviar = marcar todas como lidas
```

### 2.17 Indicação

#### GET /api/referral/my-code — Meu código de indicação

```
Requer autenticação: sim
Resposta: { "code": "ABC12345", "referral_count": 12, "total_rewards": "150.0000" }
```

#### POST /api/referral/apply — Usar código de indicação

```
Requer autenticação: sim
Requisição: { "code": "ABC12345" }
Resposta: { "message": "Referral applied" }
```

### 2.18 2FA

#### GET /api/user/2fa/status — Status do 2FA

```
Requer autenticação: sim
Resposta: { "enabled": false }
```

#### POST /api/user/2fa/setup — Configurar 2FA

```
Requer autenticação: sim
Resposta: { "secret": "JBSWY3DPEHPK3PXP", "qr_url": "otpauth://totp/..." }
```

#### POST /api/user/2fa/enable — Habilitar 2FA

```
Requer autenticação: sim
Requisição: { "code": "123456" }
Resposta: { "backup_codes": ["abcd1234ef", ...] }
```

#### POST /api/2fa/verify — Verificar 2FA (público)

```
Requisição: { "user_id": "hashid", "code": "123456" }
Resposta: { "valid": true }
```

### 2.19 Busca

#### GET /api/search — Busca global

```
Parâmetros: ?q=keyword&type=game&page=1&per_page=20
Resposta: { "list": [...], "total": 100 }
```

#### GET /api/game/suggest — Sugestões de busca

```
Parâmetros: ?q=shoot
Resposta: { "suggestions": [{ "id": "...", "name": "Shooter Master" }] }
```

### 2.20 Idioma

#### GET /api/language/list — Lista de idiomas disponíveis

```
Resposta: {
  "current": "en-US",
  "languages": {
    "en-US": { "name": "English", "nativeName": "English", "icon": "us" },
    "zh-CN": { "name": "Chinese (Simplified)", "nativeName": "简体中文", "icon": "cn" },
    "ja-JP": { "name": "Japanese", "nativeName": "日本語", "icon": "jp" },
    "ko-KR": { "name": "Korean", "nativeName": "한국어", "icon": "kr" }
  }
}
```

#### POST /api/language/switch — Alternar idioma

```
Requisição: { "locale": "zh-CN" }
Resposta: { "locale": "zh-CN" }
```

Valores possíveis de locale: en-US / zh-CN / ja-JP / ko-KR

### 2.8 Usuário

#### GET /api/user/profile — Informações pessoais

```
Requer autenticação: sim

Resposta: {
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
Requer autenticação: sim

Requisição: {
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}

Resposta: {
  "id": "...",
  "username": "player1",
  "nickname": "New Name",
  "avatar": "https://...",
  "language": "zh-CN"
}
```

Valores possíveis de language: en-US / zh-CN / ja-JP / ko-KR

### 2.9 Anúncios

#### GET /api/announcement/list — Lista de anúncios

```
Resposta: {
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

#### GET /api/announcement/detail/{hashid} — Detalhes do anúncio

```
Resposta: {
  "id": "...",
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护...",
  "type": "system",
  "created_at": "2026-05-22 09:00:00"
}
```

## 3. Interfaces do painel administrativo (admin :8787)

### 3.1 Dashboard da plataforma

#### GET /admin/dashboard/platform

```
Requer autenticação: sim (AdminAuth + AdminPermission)

Resposta: {
  "total_users": 1500,
  "active_users_7d": 320,
  "total_games": 12,
  "pending_withdraws": 5,
  "today_deposits": "500.0000",
  "today_withdraws": "120.0000",
  "total_spread_fee": "1500.5000"
}
```

### 3.2 Gestão de jogos

#### GET /admin/game/list — Lista de jogos

```
Requer autenticação: sim
Parâmetros: ?page=1&per_page=20&keyword=射击

Resposta: {
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

#### POST /admin/game/create — Criar jogo

```
Requer autenticação: sim

Requisição: {
  "name": "新游戏",
  "slug": "new-game",
  "type": "self",
  "description": "游戏描述",        // opcional
  "cover_image": "https://...",    // opcional
  "api_endpoint": "https://...",   // opcional
  "api_key": "...",                // opcional
  "api_secret": "...",             // opcional
  "status": 1,                     // opcional, padrão 0
  "sort": 0                        // opcional, padrão 0
}

Resposta: { "id": "aB3xK..." }
```

Valores possíveis de type: self / third_party

#### PUT /admin/game/{hashid} — Editar jogo

```
Requer autenticação: sim

Requisição: {
  "name": "新名称",
  "status": 1
  // atualização parcial permitida; campos iguais aos do create
}

Resposta: { "message": "更新成功" }
```

#### DELETE /admin/game/{hashid} — Excluir jogo

```
Requer autenticação: sim
Resposta: { "message": "删除成功" }
```

#### POST /admin/game/currency/manage — Gerenciar moedas

```
Requer autenticação: sim

Requisição: {
  "game_id": "aB3xK...",
  "currencies": [
    {
      "id": "",                       // vazio=criar nova, com valor=atualizar
      "name": "金币",
      "symbol": "G",
      "exchange_rate": "100.00000000",
      "spread_pct": "5.00",
      "min_exchange": "1.0000",
      "max_exchange": "10000.0000"
    }
  ]
}

Resposta: { "message": "币种更新成功" }
```

### 3.3 Gestão de saques

#### GET /admin/withdraw/orders — Lista de ordens de saque

```
Requer autenticação: sim
Parâmetros: ?page=1&per_page=20&status=pending

Resposta: {
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

#### PUT /admin/withdraw/review — Revisar saque

```
Requer autenticação: sim

Requisição: {
  "order_id": "aB3xK...",
  "action": "approve",
  "note": "审核通过"
}

Resposta: { "message": "已通过" }
```

action: approve=aprovar / reject=recusar (na recusa, a moeda da plataforma é devolvida automaticamente)

Erro: 422 a ordem não está aguardando revisão

#### PUT /admin/withdraw/switch — Interruptor global de saque

```
Requer autenticação: sim

Requisição: { "enabled": 1 }

Resposta: {
  "global_switch": true,
  "message": "提现功能已开启"
}
```

#### POST /admin/withdraw/limits/set — Definir limites de saque

```
Requer autenticação: sim

Requisição: {
  "daily_limit": "10000.0000",             // opcional
  "min_amount": "1.0000",                  // opcional
  "auto_approve_threshold": "100.0000"     // opcional
}

Resposta: {
  "daily_limit": "10000.0000",
  "min_amount": "1.0000",
  "auto_approve_threshold": "100.0000",
  "global_switch": true
}
```

### 3.4 Gestão de usuários da plataforma

#### GET /admin/platform/user/list — Lista de usuários C-side

```
Requer autenticação: sim
Parâmetros: ?page=1&per_page=20&keyword=player&status=1

Resposta: {
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

#### GET /admin/platform/user/{hashid} — Detalhes do usuário

```
Requer autenticação: sim

Resposta: {
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

#### PUT /admin/platform/user/{hashid} — Editar/banir usuário

```
Requer autenticação: sim

Requisição: {
  "status": 0,         // 0=desabilitado 1=habilitado
  "nickname": "..."    // opcional
}

Resposta: { "message": "更新成功" }
```

### 3.5 Gestão de pagamentos

#### GET /admin/payment/method/list

```
Requer autenticação: sim

Resposta: {
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

#### POST /admin/payment/method/toggle — Habilitar/desabilitar método de pagamento

```
Requer autenticação: sim

Requisição: { "id": "aB3xK...", "status": 0 }

Resposta: { "message": "已更新" }
```

### 3.6 Gestão de anúncios

#### GET /admin/announcement/list

```
Requer autenticação: sim
Parâmetros: ?page=1&per_page=20

Resposta: {
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

#### POST /admin/announcement/create — Publicar anúncio

```
Requer autenticação: sim

Requisição: {
  "title": "系统维护通知",
  "content": "将于2026年5月23日凌晨2:00-4:00进行系统维护。",
  "type": "system",           // opcional, padrão "system"
  "target_lang": "",          // opcional, vazio=todos os idiomas
  "status": 1,                // opcional, padrão 1 (0=rascunho 1=publicado)
  "start_at": "2026-05-23 02:00:00",  // opcional
  "end_at": "2026-05-23 04:00:00"     // opcional
}

Resposta: { "id": "aB3xK..." }
```

### 3.7 Revisão de KYC

#### GET /admin/identity/list — Lista de KYC

```
Requer autenticação: sim
Parâmetros: ?page=1&per_page=20&status=pending

Resposta: {
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
Requer autenticação: sim

Requisição: { "id": "hashid", "action": "approve", "note": "" }

Resposta: { "message": "Approved" }
```

action: approve / reject

### 3.8 Gestão de servidores de jogo

#### GET /admin/game/server/list — Lista de servidores

```
Requer autenticação: sim
Parâmetros: ?game_id=hashid

Resposta: {
  "list": [
    { "id": "...", "name": "亚洲1服", "region": "asia", "status": 1, "sort": 0 }
  ]
}
```

#### POST /admin/game/server/create — Criar servidor

```
Requer autenticação: sim
Requisição: { "game_id": "hashid", "name": "亚洲1服", "region": "asia", "status": 1 }
Resposta: { "id": "hashid" }
```

#### PUT /admin/game/server/{hashid} — Editar servidor

```
Requer autenticação: sim
Requisição: { "name": "新名称", "status": 2 }
```

#### DELETE /admin/game/server/{hashid} — Excluir servidor

```
Requer autenticação: sim
```

### 3.9 Gestão de limites escalonados de saque

#### GET /admin/withdraw/limits/list

```
Requer autenticação: sim

Resposta: {
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

#### PUT /admin/withdraw/limits/{hashid} — Atualizar limite

```
Requer autenticação: sim

Requisição: { "single_max": "10000.0000", "fee_pct": "0.25" }
// atualização parcial permitida
```

### 3.11 Gestão de categorias de jogos

#### GET /admin/game/category/list

```
Requer autenticação: sim
Resposta: { "list": [{ "id": "...", "name": "动作", "slug": "action", "sort": 1 }] }
```

#### POST /admin/game/category/create

```
Requer autenticação: sim
Requisição: { "name": "新分类", "slug": "new-cat", "icon": "star", "sort": 10 }
Resposta: { "id": "hashid" }
```

#### PUT /admin/game/category/{hashid} — Editar categoria

#### DELETE /admin/game/category/{hashid} — Excluir categoria

#### POST /admin/game/category/assign — Atribuir jogos

```
Requer autenticação: sim
Requisição: { "category_id": "hashid", "game_ids": ["hash1", "hash2"] }
```

### 3.12 Gestão de rankings

#### GET /admin/leaderboard/list — Lista de rankings

```
Requer autenticação: sim
Resposta: { "list": [{ "id": "...", "name": "...", "type": "total", "metric": "earned" }] }
```

#### POST /admin/leaderboard/create — Criar ranking

```
Requer autenticação: sim
Requisição: { "name": "周收入榜", "type": "weekly", "metric": "earned", "game_id": "hashid(可选)" }
```

#### PUT /admin/leaderboard/{hashid} — Editar ranking

#### DELETE /admin/leaderboard/{hashid} — Excluir ranking

#### POST /admin/leaderboard/{hashid}/refresh — Atualizar cache

### 3.13 Gestão de cupons

#### GET /admin/coupon/list — Lista de cupons

#### POST /admin/coupon/create — Criar cupom

```
Requer autenticação: sim
Requisição: { "name": "新人礼包", "type": "fixed", "value": "10.0000", "total_qty": 1000 }
```

#### PUT /admin/coupon/{hashid} — Editar (quando não resgatado)

#### DELETE /admin/coupon/{hashid} — Excluir

#### GET /admin/coupon/{hashid}/stats — Estatísticas de resgate

```
Resposta: { "total_qty": 1000, "used_qty": 234, "remaining": 766, "usage_rate": "23.40%" }
```

### 3.14 Gestão de configuração de países

#### GET /admin/country/config/list — Lista de configurações de países

#### POST /admin/country/config/create — Criar configuração de país

```
Requer autenticação: sim
Requisição: { "country_code": "JP", "currency": "JPY", "payment_methods": "[\"stripe\",\"paypal\"]", "min_deposit": "100.0000" }
```

#### PUT /admin/country/config/{hashid} — Editar configuração de país

### 3.15 Exportação de dados

#### POST /admin/export/users — Exportar usuários C-side

```
Requer autenticação: sim
Parâmetros (JSON): { "status": 1 }   // filtro opcional

Resposta: download do arquivo Excel (xlsx)
```

#### POST /admin/export/transactions — Exportar fluxo da plataforma

```
Requer autenticação: sim
Parâmetros (JSON): { "type": "deposit" }   // filtro opcional

Resposta: download do arquivo Excel (xlsx)
```

### 3.16 Análise de dados (agregação em tempo real no MySQL)

Todos os endpoints exigem autenticação (AdminAuth + AdminPermission); os dados são agregados em tempo real a partir do MySQL, sem depender do ClickHouse.

| Método | Caminho | Observação |
|------|------|------|
| GET | /admin/analytics/overview | Visão geral da plataforma (hoje/últimos 7 dias) |
| GET | /admin/analytics/game-ranking | Ranking de jogos (?days=7) |
| GET | /admin/analytics/dau-trend | Tendência de DAU (?days=30) |
| GET | /admin/analytics/hourly-trend | Tendência por hora |
| GET | /admin/analytics/action-distribution | Distribuição de ações |
| GET | /admin/analytics/revenue | Análise de receita |
| GET | /admin/analytics/conversion | Taxa de conversão de jogos |
| GET | /admin/analytics/probability | Probabilidade conjunta/condicional |
| GET | /admin/analytics/retention | Análise de retenção D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Funil de conversão |
| GET | /admin/analytics/arpu | Tendência ARPU/ARPPU |
| GET | /admin/analytics/economy | Indicadores econômicos das moedas de jogo |

### 3.17 Gestão de tickets

Todos os endpoints exigem autenticação (AdminAuth + AdminPermission).

| Método | Caminho | Observação |
|------|------|------|
| GET | /admin/ticket/list | Lista de tickets (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | Detalhes do ticket (inclui respostas) |
| POST | /admin/ticket/{hashid}/reply | Responder ticket |
| POST | /admin/ticket/{hashid}/close | Fechar ticket |
| POST | /admin/ticket/{hashid}/assign | Designar responsável (admin_id) |

### 3.18 Gerenciamento de configuração de CDN

Todos os endpoints exigem autenticação (AdminAuth + AdminPermission).

| Método | Caminho | Observação | Autenticação |
|------|------|------|------|
| GET | /admin/cdn/provider/list | Lista de provedores de CDN (credenciais não retornadas) | AdminAuth + RBAC: cdn |
| POST | /admin/cdn/provider/toggle | Ativar/desativar provedor {id, status} | AdminAuth + RBAC: cdn |
| POST | /admin/cdn/provider/create | Criar {name, provider, config(JSON), status, sort}, verificação de unicidade do provider | AdminAuth + RBAC: cdn |
| PUT | /admin/cdn/provider/{hashid} | Editar (config vazio = inalterado) | AdminAuth + RBAC: cdn |
| DELETE | /admin/cdn/provider/{hashid} | Excluir | AdminAuth + RBAC: cdn |
| POST | /admin/cdn/provider/test | Teste de conectividade HeadBucket {id} | AdminAuth + RBAC: cdn |

## 4. Política de rate limit

| Interface | Limite |
|------|------|
| Padrão | 60 vezes/minuto/IP |
| POST /api/auth/login | 10 vezes/minuto |
| POST /api/auth/register | 5 vezes/minuto |

Ao exceder o limite retorna 429; o cabeçalho da resposta inclui:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1716400830
Retry-After: 60
```

## 5. Explicação da autenticação

### C-side (UserAuth)

1. Extrair o Token de `Authorization: Bearer <token>`
2. Verificar assinatura JWT (HS256), analisar `sub` (ID do usuário)
3. Consultar a tabela `game_user` para confirmar que o usuário existe e status=1
4. Injetar `$request->userId`

### Painel administrativo (AdminAuth + AdminPermission)

1. AdminAuth: verificação da assinatura JWT, analisar `sub` (ID do administrador), injetar `$request->adminId`
2. AdminPermission: buscar permissões pelo role do usuário, comparar com identificadores de permissão no formato `method.path`
3. Superadministradores com `slug=*` pulam a verificação de permissão

## 6. Referência rápida de códigos de erro

| code | Significado | Cenários comuns |
|------|------|---------|
| 0 | Sucesso | - |
| 400 | Erro de parâmetros | Formato da requisição incorreto, valor insuficiente |
| 401 | Não autenticado | Token ausente/expirado/inválido, conta desabilitada |
| 403 | Sem permissão | Usuário sem permissão do role correspondente, jogo indisponível |
| 404 | Não existe | Recurso não encontrado |
| 422 | Falha de validação | Parâmetros do formulário fora das regras, status da ordem não permite a operação |
| 429 | Rate limit | Requisições em excesso |
| 500 | Erro de servidor | Exceção inesperada |


## 7. Novas APIs (v2.0 expansão do ecossistema)

### 7.1 Provider API — Interfaces de callback do provedor de jogos

**Método de autenticação**: assinatura HMAC-SHA256 (X-Game-Id + X-Timestamp + X-Signature)
**Janela de tempo**: 5 minutos

#### POST /api/provider/balance — Consultar saldo do usuário

```
Cabeçalhos da requisição:
  X-Game-Id: 1234567890
  X-Timestamp: 1716400830
  X-Signature: abc123...

Requisição: {
  "user_id": 1234567890,
  "game_id": 9876543210,
  "currency_id": 5555555555
}

Resposta: {
  "code": 0,
  "message": "success",
  "data": { "balance": "1000.50000000" }
}
```

#### POST /api/provider/bet — Notificar aposta

```
Requisição: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "bet_type": "straight" }
}

Resposta: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "990.50000000"
  }
}
```

#### POST /api/provider/settle — Notificar liquidação

```
Requisição: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "50.00000000",
  "round_id": "ROUND_abc123",
  "meta": { "win_type": "jackpot" }
}

Resposta: {
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
Requisição: {
  "user_id": 1234567890,
  "session_id": "GAME_SESSION_202608041030001234",
  "amount": "10.00000000",
  "round_id": "ROUND_abc123",
  "reason": "game_crash"
}

Resposta: {
  "code": 0,
  "data": {
    "success": true,
    "transaction_id": "ROUND_abc123",
    "balance_after": "1000.50000000"
  }
}
```

### 7.2 Ticket API

#### GET /api/ticket/list — Lista de tickets

```
Requer autenticação: sim
Parâmetros: ?page=1&per_page=20

Resposta: {
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

#### POST /api/ticket/create — Criar ticket

```
Requer autenticação: sim
Requisição: {
  "type": "deposit",
  "subject": "充值未到账",
  "content": "我充值了100元但余额未更新..."
}
Resposta: { "code": 0, "message": "Ticket created", "data": { "id": "aB3xK..." } }
```

#### GET /api/ticket/{hashid} — Detalhes do ticket

```
Requer autenticação: sim
Resposta: {
  "id": "...", "type": "deposit", "subject": "...",
  "content": "...", "status": "open",
  "replies": [
    { "id": "...", "content": "...", "is_admin": 1, "created_at": "..." }
  ]
}
```

#### POST /api/ticket/{hashid}/reply — Responder ticket

```
Requer autenticação: sim
Requisição: { "content": "已核实，将在24小时内处理" }
Resposta: { "code": 0, "message": "Reply sent" }
```

### 7.3 API de verificação de email

#### POST /api/verify/send-email — Enviar código de verificação de email

```
Requer autenticação: sim
Requisição: { "email": "user@example.com" }
Resposta: { "code": 0, "message": "Verification code sent" }
Erro: 429 aguarde 60 segundos e tente novamente
```

#### POST /api/verify/confirm-email — Confirmar email

```
Requer autenticação: sim
Requisição: { "code": "123456" }
Resposta: { "code": 0, "message": "Email verified" }
Erro: 422 código de verificação inválido ou expirado
```

### 7.4 VIP API

#### GET /api/user/vip-status — Status do VIP

```
Requer autenticação: sim
Resposta: {
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

### 7.5 Achievement API

#### GET /api/user/achievements — Lista de conquistas

```
Requer autenticação: sim
Resposta: {
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

### 7.6 Novas APIs do painel administrativo

#### GET /admin/ticket/list — Lista de tickets

```
Requer autenticação: sim
Parâmetros: ?page=1&limit=20&status=pending&type=deposit

Resposta: {
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
Requer autenticação: sim
Requisição: { "content": "已处理" }
Resposta: { "code": 0, "message": "Reply sent" }
```

#### POST /admin/ticket/{hashid}/close — Fechar ticket

```
Requer autenticação: sim
Resposta: { "code": 0, "message": "Ticket closed" }
```

#### POST /admin/ticket/{hashid}/assign — Designar responsável

```
Requer autenticação: sim
Requisição: { "admin_id": 1234567890 }
Resposta: { "code": 0, "message": "Assigned" }
```

#### GET /admin/analytics/retention — Análise de retenção

```
Requer autenticação: sim
Parâmetros: ?days=30
Resposta: {
  "D1": "45.2%", "D3": "28.7%",
  "D7": "18.3%", "D30": "8.1%"
}
```

#### GET /admin/analytics/funnel — Funil de conversão

```
Requer autenticação: sim
Resposta: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — Tendência ARPU/ARPPU

```
Requer autenticação: sim
Parâmetros: ?days=30
Resposta: { "arpu": [...], "arppu": [...], "dates": [...] }
```

#### GET /admin/analytics/economy — Indicadores econômicos das moedas de jogo

```
Requer autenticação: sim
Resposta: {
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


#### GET /admin/cdn/provider/list — Lista de provedores de CDN (credenciais não retornadas)

```
Requer autenticação: sim
Resposta: { "list": [ { "id": "...", "name": "...", "provider": "cloudflare", "status": 1, "sort": 0 } ] }
```

#### POST /admin/cdn/provider/toggle — Ativar/desativar provedor {id, status}

```
Requer autenticação: sim
Requisição: { "id": "...", "status": 1 }
Resposta: { "code": 0, "message": "..." }
```

#### POST /admin/cdn/provider/create — Criar {name, provider, config(JSON), status, sort}, verificação de unicidade do provider

```
Requer autenticação: sim
Requisição: { "name": "...", "provider": "aliyun", "config": "{...}", "status": 1, "sort": 0 }
Resposta: { "code": 0, "data": { "id": "..." } }
```

#### PUT /admin/cdn/provider/{hashid} — Editar (config vazio = inalterado)

```
Requer autenticação: sim
Requisição: { "name": "...", "config": "" }
Resposta: { "code": 0, "message": "..." }
```

#### DELETE /admin/cdn/provider/{hashid} — Excluir

```
Requer autenticação: sim
Resposta: { "code": 0, "message": "..." }
```

#### POST /admin/cdn/provider/test — Teste de conectividade HeadBucket {id}

```
Requer autenticação: sim
Requisição: { "id": "..." }
Resposta: { "code": 0, "data": { "ok": true } }
```
## 8. Política de rate limit (atualizada)

| Interface | Limite |
|------|------|
| Padrão | 60 vezes/minuto/IP |
| POST /api/auth/login | 10 vezes/minuto |
| POST /api/auth/register | 5 vezes/minuto |
| POST /api/auth/oauth | 10 vezes/minuto |
| POST /api/payment/callback | 30 vezes/minuto |
| POST /api/provider/* | Sem limite (autenticação por assinatura HMAC) |

## 9. Explicação da autenticação (atualizada)

### Autenticação de Provider (ProviderAuth)

1. Extrair `X-Game-Id`, `X-Timestamp`, `X-Signature` dos cabeçalhos da requisição
2. Consultar a tabela `game_game` para confirmar que o jogo existe e status=1
3. Verificar se o timestamp está dentro da janela de 5 minutos (anti-replay)
4. Calcular `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)` e comparar com a assinatura
5. Injetar `$request->gameId` e `$request->game`


### 7.7 Friend API

#### GET /api/friend/list — Lista de amigos
```
Requer autenticação: sim
Resposta: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

#### GET /api/friend/requests — Solicitações pendentes
```
Requer autenticação: sim
Resposta: { "list": [{ "id": "...", "user": {...}, "created_at": "..." }] }
```

#### POST /api/friend/request — Enviar solicitação de amizade
```
Requer autenticação: sim
Requisição: { "friend_id": "hashid" }
```

#### POST /api/friend/accept — Aceitar solicitação
```
Requer autenticação: sim
Requisição: { "request_id": "hashid" }
```

#### POST /api/friend/reject — Recusar solicitação
```
Requer autenticação: sim
Requisição: { "request_id": "hashid" }
```

#### POST /api/friend/remove — Remover amigo
```
Requer autenticação: sim
Requisição: { "friend_id": "hashid" }
```

#### GET /api/friend/search — Buscar usuário
```
Requer autenticação: sim
Parâmetros: ?q=username
Resposta: { "list": [{ "id": "...", "username": "...", "nickname": "...", "avatar": "..." }] }
```

### 7.8 Chat API

#### GET /api/chat/conversations — Lista de conversas
```
Requer autenticação: sim
Resposta: {
  "list": [{
    "peer": { "id": "...", "username": "...", "nickname": "...", "avatar": "..." },
    "last_message": "最近一条消息",
    "unread_count": 3,
    "updated_at": "2026-05-22 10:30:00"
  }]
}
```

#### GET /api/chat/messages/{peerHashid} — Lista de mensagens
```
Requer autenticação: sim
Parâmetros: ?page=1&per_page=50
Resposta: { "items": [{ "id": "...", "content": "...", "is_read": 1 }], "total": 100 }
Marca automaticamente como lidas as mensagens não lidas recebidas do interlocutor
```

#### POST /api/chat/send — Enviar mensagem
```
Requer autenticação: sim
Requisição: { "to_user_id": "hashid", "content": "Hello!" }
Erro: 403 não é possível enviar para quem não é amigo
```

#### GET /api/chat/unread-total — Total de não lidas
```
Requer autenticação: sim
Resposta: { "count": 5 }
```

**Conexão WebSocket**: `ws://host:8791`
```
// Autenticação
→ { "action": "auth", "token": "eyJhbG..." }
← { "type": "authenticated", "user_id": 1234567890 }

// Receber mensagem
← { "type": "message", "message": { "id": "...", "from_user_id": "...", "content": "Hello!", "created_at": "..." } }
```

### 7.9 Webhook API

#### GET /api/webhook/list — Lista de assinaturas
```
Requer autenticação: sim
Resposta: { "list": [{ "id": "...", "url": "https://...", "events": ["deposit.completed"] }] }
```

#### POST /api/webhook/register — Registrar assinatura
```
Requer autenticação: sim
Requisição: { "url": "https://my-server.com/hook", "events": ["deposit.completed", "game.played"] }
Eventos disponíveis: deposit.completed / withdraw.completed / exchange.completed / game.played / user.registered / risk.alert / user.vip_upgraded
```

#### POST /api/webhook/delete — Excluir assinatura
```
Requer autenticação: sim
Requisição: { "id": "hook_id" }
```

### 7.10 APIs de análise avançada

#### GET /admin/analytics/retention — Análise de retenção
```
Requer autenticação: sim
Resposta: { "D1": "45.2%", "D3": "28.7%", "D7": "18.3%", "D30": "8.1%" }
```

#### GET /admin/analytics/funnel — Funil de conversão
```
Requer autenticação: sim
Resposta: {
  "funnel": [
    { "step": "register", "count": 1500, "rate": "100%" },
    { "step": "first_deposit", "count": 450, "rate": "30.0%" },
    { "step": "first_exchange", "count": 320, "rate": "21.3%" },
    { "step": "first_game", "count": 280, "rate": "18.7%" }
  ]
}
```

#### GET /admin/analytics/arpu — Tendência ARPU/ARPPU
```
Requer autenticação: sim
Parâmetros: ?days=30
Resposta: { "dates": [...], "arpu": [...], "arppu": [...] }
```

#### GET /admin/analytics/economy — Indicadores econômicos dos jogos
```
Requer autenticação: sim
Resposta: {
  "currencies": [{
    "game_name": "Shooter Master", "currency": "Gold", "symbol": "G",
    "total_minted": "500000.00000000", "total_burned": "320000.00000000",
    "circulation": "180000.00000000", "inflation_rate": "36.00%"
  }]
}
```


### 7.11 Tournament API

#### GET /api/tournament/list — Lista de torneios
```
Parâmetros: ?status=active|upcoming|ended&page=1&per_page=20
Resposta: { "items": [{ "id": "...", "name": "...", "prize_pool": "1000.0000", "player_count": 45, "max_players": 100 }], "total": 5 }
```

#### GET /api/tournament/{hashid} — Detalhes do torneio
```
Resposta: { "id": "...", "name": "...", "leaderboard": [...], "my_entry": {...} }
```

#### POST /api/tournament/{hashid}/join — Inscrever-se no torneio
```
Requer autenticação: sim
Erro: 422 já inscrito / 400 já começou ou lotado / 503 FeatureFlag desligado
```

### 7.12 Condições de cupons (novo)

O JSON `conditions` de cupons suporta:
- `min_deposit`: string, valor mínimo acumulado de depósito
- `first_user_only`: bool, apenas novos usuários que nunca depositaram
- `game_id`: int, precisa ter jogado o jogo especificado

As condições são verificadas duplamente: na filtragem da lista em `available()` e no resgate em `claim()`.

### 7.13 Indicação em vários níveis (novo)

A comissão de indicação adiciona repartição de segundo nível:
- L1: o indicador direto recebe `referrer_bonus` (config: referral.referrer_bonus)
- L2: o indicador do indicador recebe `commission = referrer_bonus * level2_rate` (config: referral.level2_rate, padrão 5%)
- Registra `game_referral_commission` (level/commission_rate/commission_amount)

### 8. Política de rate limit (atualizada)

| Interface | Limite |
|------|------|
| POST /api/tournament/{id}/join | 10 vezes/minuto |
