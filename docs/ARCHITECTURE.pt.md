# Documento de arquitetura
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE.md) · [English](ARCHITECTURE.en.md) · [한국어](ARCHITECTURE.ko.md) · [Русский](ARCHITECTURE.ru.md) · [Deutsch](ARCHITECTURE.de.md) · [Français](ARCHITECTURE.fr.md) · [Español](ARCHITECTURE.es.md) · **Português** · [हिन्दी](ARCHITECTURE.hi.md) · [العربية](ARCHITECTURE.ar.md) · [বাংলা](ARCHITECTURE.bn.md) · [Bahasa Indonesia](ARCHITECTURE.id.md) · [日本語](ARCHITECTURE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Topologia do sistema

```mermaid
flowchart TB
    subgraph "客户端层"
        A1["Flutter Web PC<br/>管理后台"]
        A2["Flutter Web PC<br/>C端用户平台"]
        A3["HarmonyOS ArkTS<br/>手机/平板客户端"]
    end

    subgraph "网关层 (Nginx)"
        B1["反向代理 + HTTPS<br/>路由分发 + Gzip<br/>静态文件服务"]
    end

    subgraph "应用层"
        C1["admin/ webman<br/>管理后台 :8787<br/>AdminAuth → AdminPermission → OperationLog"]
        C2["service/ webman<br/>C端业务 :8788<br/>UserAuth → [ProviderAuth]"]
    end

    subgraph "服务层 (新增)"
        D0["GameProvider 抽象层<br/>SelfProvider / ThirdPartyProvider<br/>HMAC-SHA256 签名<br/>事务一致性保证"]
        D1["EventBus<br/>Redis Pub/Sub<br/>异步事件分发<br/>成就/通知/审计 解耦"]
        D2["VIP 引擎<br/>经验值累计→自动升级<br/>兑换折扣/提现减免<br/>汇率加成"]
        D3["成就引擎<br/>12 内置成就<br/>进度追踪<br/>事件驱动检测"]
        D4["特性开关<br/>FeatureFlag<br/>零依赖动态配置"]
    end

    subgraph "存储层"
        E1[("MySQL 8.0<br/>主存储<br/>52 张表")]
        E2[("Redis<br/>Session/缓存/限流<br/>EventBus/心跳")]
        E3[("Elasticsearch<br/>全文检索")]
        E4[("ClickHouse<br/>OLAP 分析<br/>概率计算")]
    end

    subgraph "外部集成"
        F1["第三方游戏<br/>Provider API<br/>余额/下注/结算/退款"]
        F2["推送通道<br/>FCM / APNs<br/>华为推送"]
        F3["OAuth (7平台)<br/>Google/Facebook/Apple<br/>X(Twitter)/Microsoft<br/>LinkedIn/GitHub"]
    end

    A1 & A2 & A3 -->|"HTTPS/JSON<br/>JWT Bearer"| B1
    B1 -->|"/admin/*"| C1
    B1 -->|"/api/*"| C2
    C1 & C2 --> D0 & D1 & D2 & D3 & D4
    C2 -->|"/api/provider/*"| F1
    C1 & C2 --> E1 & E2 & E3 & E4
    C2 --> F2 & F3
```

## 2. Arquitetura de módulos

### 2.1 admin/ — Painel administrativo

```
Camada de rotas: config/route.php
  ↓
Cadeia de middlewares: Cors → SecurityFilter → RateLimit → AdminAuth → AdminPermission → OperationLog
  ↓
Camada de controllers (28):
  ┌──────────────────────────────────────────────────────────┐
  │ Dashboard / User / Role / Permission / Config / Log      │ ← original
  │ Profile / Export / Import / Upload / Health / Docs       │ ← original
  │ Game / Withdraw / Payment / PlatformUser / Announce      │ ← original
  │ Analytics / GameCategory / GameServer / Identity         │ ← original
  │ CountryConfig / Coupon / Leaderboard / Metrics           │ ← original
  │ Ticket / Search                                          │ ← novo
  └──────────────────────────────────────────────────────────┘
  ↓
Camada de serviços: VIP / Achievement / EventBus / FeatureFlag / Risk / Notification
  ↓
Camada de Provider: GameProvider → SelfProvider / ThirdPartyProvider
  ↓
Camada de armazenamento: MySQL / Redis / Elasticsearch / ClickHouse
```

### 2.2 service/ — Serviço C-side

```
Camada de rotas: config/route.php
  ↓
Cadeia de middlewares: Cors → SecurityFilter → RateLimit → Language → ApiVersion → [UserAuth | ProviderAuth]
  ↓
Camada de controllers (25):
  ┌──────────────────────────────────────────────────────────┐
  │ Auth / Wallet / Deposit / Exchange / Withdraw            │ ← original
  │ Game / User / Announcement / Captcha                     │ ← original
  │ OAuth / Identity / Payment / GamePlayLog                 │ ← original
  │ Leaderboard / Notification / Referral / TwoFactor        │ ← original
  │ Country / Language / Coupon / Search                     │ ← original
  │ Provider / Ticket / Verification                         │ ← novo
  └──────────────────────────────────────────────────────────┘
  ↓
Camada de serviços: VIP / Achievement / EventBus / FeatureFlag / Risk / GameSession
  ↓
Camada de Provider: GameProvider → SelfProvider / ThirdPartyProvider
  ↓
Camada de armazenamento: MySQL / Redis / Elasticsearch / ClickHouse
```

### 2.3 Camada de Provider — Abstração de integração de jogos

```
provider/
├── GameProvider.php          # Classe base abstrata — interface unificada
│   ├── getBalance()          # Consultar saldo
│   ├── bet()                 # Apostar
│   ├── settle()              # Liquidar
│   ├── refund()              # Reembolsar
│   ├── rollback()            # Rollback
│   ├── verifySignature()     # Verificar assinatura do callback
│   └── signRequest()         # Gerar assinatura da requisição (HMAC-SHA256)
├── SelfProvider.php          # Jogos próprios — consistência por transação DB
├── ThirdPartyProvider.php    # Jogos de terceiros — HTTP API + assinatura
└── ProviderFactory.php       # Fábrica — match(game.type)
```

### 2.4 EventBus — Barramento de eventos

```
Publicação de eventos:
  DepositController → EventBus::emit('deposit.completed', $payload)
  ExchangeController → EventBus::emit('exchange.completed', $payload)
  GameController → EventBus::emit('game.played', $payload)
  ReferralController → EventBus::emit('referral.applied', $payload)

Redis Pub/Sub (canal: platform:events):
  ↓
Assinantes:
  AchievementService  — detecta progresso de conquistas
  VipService          — acumula pontos de experiência
  NotificationService — envia notificações
  WebhookController   — entrega webhooks externos

> Nota: até 2026-08-18, `emit()` tem chamadores mas `subscribe()` não tem nenhum processo registrado (P0-4 não feito); os eventos hoje são apenas publicados, sem consumo; os assinantes são metas de design.
```

### 2.5 Garantia de estabilidade — disjuntor / nova tentativa / degradação

```
packages/platform-common/src/
├── CircuitBreaker.php   # 熔断 — Redis 状态 (cb:{key}:failures / opened_at)，阈值 5 / 窗口 30s
│                        #   达阈值抛 CircuitOpenException 快速失败；成功重置计数；半开探测
│                        #   Redis 不可用 fail-open，不影响主流程
└── Retry.php            # 重试 — 指数退避 (200/400/800ms)，仅网络类异常 (ConnectException/超时/cURL 28)
                         #   maxAttempts 上限 5；与熔断共用 isRetryable 判定
```

Interruptor de degradação `feature.provider_mock` (FeatureFlag / PlatformConfig, curto-circuita chamadas de rede reais quando `on`):

| Ponto de entrada | Comportamento com mock=on |
|--------|-------------|
| `PushService::send` | Retorno imediato, nenhuma notificação enviada |
| `PayoutService::execute` | Retorna o lote `mock-{order_no}` e marca o pedido como completed |
| `ThirdPartyProvider::request` | Retorna `['success' => true]` |

Todas as chamadas de rede reais são envolvidas em `Retry::run → CircuitBreaker::call` (Push FCM/APNs/HarmonyOS, pagamentos PayPal, solicitações de Provider de terceiros).

## 3. Cadeias de execução de middlewares

### admin/ (painel administrativo)

```
Requisição → Cors (CORS)
     → SecurityFilter (30+ detectores→405/403)
     → RateLimit (Redis Lua janela deslizante→429)
     → AdminAuth (autenticação JWT→401)
     → AdminPermission (autorização RBAC, cache Redis 60s→403)
     → OperationLog (registro automático de operações)
     → Controller → Resposta
```

### service/ (serviço C-side)

```
APIs comuns:
  Requisição → Cors → SecurityFilter → RateLimit → Language → ApiVersion
       → [UserAuth] (JWT→401) → Controller → Resposta

Provider API:
  Requisição → Cors → SecurityFilter → RateLimit
       → ProviderAuth (verificação de assinatura HMAC-SHA256, janela 5min→401)
       → ProviderController → Resposta
```

## 4. Fluxos de dados principais

### 4.1 Fluxo de depósito

```
Usuário → POST /api/deposit/create → gerar ordem (status=pending)
     → criar pagamento via GatewayFactory (Stripe Checkout/fatura NowPayments/cobrança Coinbase) → preencher checkout_url + expires_at(+1h); em caso de falha, cancelar pedido via CAS e tentar novamente
     → redirecionar para pagamento de terceiros (Stripe/PayPal/NowPayments[USDT TRC20/ERC20]/Coinbase[USDC/BTC/ETH])
     → pagamento com sucesso → callback /api/payment/callback
     → whitelist do provider (apenas stripe/paypal/nowpayments/coinbase) + verificação de uso indevido entre canais + verificação de assinatura (fail-closed) + timestamp ±300s + conferência de valor com bccomp
     → atualizar ordem (status=confirmed, transacional)
     → UserWallet::addBalance() → crédito de moeda da plataforma
     → EventBus::emit('deposit.completed')
       → VipService::addExp() → acúmulo de EXP → detecção de upgrade VIP
       → AchievementService::check() → atualização de progresso de conquistas
     → registrar Transaction (type=deposit)
```

### 4.2 Fluxo de troca

```
Usuário → POST /api/exchange/quote → cotação
     → VipService::getExchangeDiscount() → aplicar desconto VIP
     → VipService::getRateBonus() → aplicar bônus de câmbio VIP
     → confirmação → POST /api/exchange/buy (ou sell)
     → DB::beginTransaction()
     ├─ debitar moeda de origem (lockForUpdate)
     ├─ creditar moeda de destino
     ├─ registrar ExchangeRecord
     ├─ registrar Transaction
     └─ DB::commit()
     → EventBus::emit('exchange.completed')
       → AchievementService::check()
```

### 4.3 Fluxo de saque

```
Usuário → POST /api/withdraw/apply
     → VipService::getWithdrawFeeDiscount() → aplicar redução de tarifa VIP
     → verificar interruptor global (PlatformConfig)
     → verificar limites (min_amount / daily_limit)
     → verificar saldo → debitar saldo
     → valor<threshold → auto-aprovado
     → valor≥threshold → pending (revisão manual)
     → registrar Transaction

Administrador → PUT /admin/withdraw/review
       → approve: marcar como concluído
       → reject: devolver moeda da plataforma + transação de reembolso
```

### 4.4 Fluxo de interação com o Provider de jogos

```
Servidor do jogo de terceiros:
  POST /api/provider/balance
    X-Game-Id + X-Timestamp + X-Signature (HMAC-SHA256)
    → ProviderAuth verifica assinatura → ProviderFactory::createById()
    → GameProvider::getBalance() → retorna saldo

  POST /api/provider/bet
    → ProviderAuth → GameProvider::bet()
    → SelfProvider: débito por transação DB (SELECT FOR UPDATE)
    → ThirdPartyProvider: encaminhamento HTTP ao provedor do jogo
    → registrar GamePlayLog (action=bet, round_id)

  POST /api/provider/settle
    → ProviderAuth → GameProvider::settle()
    → creditar saldo de moeda de jogo → atualizar GamePlayLog.ended_at

  POST /api/provider/refund
    → ProviderAuth → GameProvider::refund()
    → devolver saldo → registrar log de reembolso
```

### 4.5 Fluxo de upgrade VIP

```
Depósito concluído → VipService::addExp(userId, amount, 'deposit')
         → UserVip.exp += amount, UserVip.total_exp += amount
         → consultar próximo nível VipLevel
         → exp >= required_exp → upgrade: level+1, exp -= required_exp
         → loop até não satisfazer mais as condições de upgrade
         → EventBus::emit('user.vip_upgraded')
```

## 5. Relações ER do banco de dados

```
game_user ──┬── 1:1 ── game_user_wallet
            ├── 1:1 ── game_user_vip ── game_vip_level
            ├── 1:N ── game_user_game_wallet
            ├── 1:N ── game_deposit_order
            ├── 1:N ── game_withdraw_order
            ├── 1:N ── game_exchange_record
            ├── 1:N ── game_transaction
            ├── 1:N ── game_user_achievement ── game_achievement
            ├── 1:N ── game_exp_log
            ├── 1:N ── game_ticket ── game_ticket_reply
            ├── 1:N ── game_device_token
            ├── 1:N ── game_user_session
            └── 1:N ── game_message

game_game ──┬── 1:N ── game_game_currency
            ├── 1:N ── game_user_game_wallet
            ├── 1:N ── game_exchange_record
            └── 1:N ── game_game_play_log

game_friend ── user_id → game_user
             └── friend_id → game_user

game_vip_level ── 1:N ── game_user_vip
game_achievement ── 1:N ── game_user_achievement
```

## 6. Arquitetura de implantação

### 6.1 Ambiente de desenvolvimento

```
Implantação em uma máquina:
  admin/         :8787 (webman, 32 workers)
  service/       :8788 (webman, 32 workers)
  leaderboard-ws :8789 (WebSocket de rankings)
  chat-ws        :8791 (WebSocket de chat)
  MySQL          :3306
  Redis          :6379
```

### 6.2 Docker Compose (8 serviços)

```yaml
nginx (80/443) → admin (8787) + service (8788) + arquivos estáticos
leaderboard-ws (8789) — push em tempo real de rankings via WebSocket
chat-ws (8791) — mensagens privadas/chat via WebSocket
mysql (3306) — banco principal, persistência com volume de dados
redis (6379) — cache/rate limit/WebSocket/EventBus
elasticsearch (9200) — busca full-text
```

### 6.3 Ambiente de produção

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web 服务器 (Nginx)"
        NGX["反向代理 :443 HTTPS<br/>静态文件服务<br/>gzip + CSP + HSTS<br/>limit_req 限流"]
    end

    subgraph "应用服务器"
        ADM1["admin :8787"]
        ADM2["admin :8787"]
        SVC1["service :8788"]
        SVC2["service :8788"]
        WS1["leaderboard-ws :8789"]
        WS2["chat-ws :8791"]
    end

    subgraph "数据层"
        MYSQL["MySQL 8.0 主从复制"]
        REDIS["Redis 7.x 哨兵模式<br/>EventBus Pub/Sub"]
        ES["Elasticsearch 8.x"]
        CH["ClickHouse OLAP"]
    end

    subgraph "监控"
        MON["Grafana + Prometheus<br/>健康检查 /metrics"]
    end

    DNS --> NGX
    NGX --> ADM1 & ADM2 & SVC1 & SVC2
    ADM1 & ADM2 & SVC1 & SVC2 --> MYSQL & REDIS & ES & CH
    ADM1 & ADM2 & SVC1 & SVC2 --> MON
```

## 7. Arquitetura de testes

```
tests/
├── bootstrap.php                  # Bootstrap do PHPUnit
├── PlatformTest.php               # 56 testes de lógica de negócio
├── BackendEnhancementTest.php     # 23 testes de criptografia/serviço de IDs
├── CaptchaTest.php                # 7 testes de captcha
├── EncryptionServiceTest.php      # 6 testes de criptografia/descriptografia
├── EnvConfigTest.php              # 4 testes de configuração de ambiente
├── HashidsServiceTest.php         # 8 testes de codificação/decodificação de IDs
└── SnowflakeServiceTest.php       # 6 testes de IDs Snowflake
```

## 8. Distribuição de portas

| Serviço | Porta | Observação |
|------|------|------|
| admin/ | 8787 | API do painel administrativo |
| service/ | 8788 | API de negócio C-side |
| leaderboard-ws | 8789 | Rankings em tempo real via WebSocket |
| chat-ws | 8791 | Mensagens privadas/chat via WebSocket |
| MySQL | 3306 | Banco principal |
| Redis | 6379 | Cache/rate limit/WebSocket/EventBus |
| ClickHouse | 8123 | Interface HTTP OLAP |
| Elasticsearch | 9200 | Busca full-text |

## 9. Documentação da API

Documentação interativa de API gerada automaticamente a partir das anotações dos controllers com `hg/apidoc`:

| Documentação | Endereço | Controllers | Endpoints |
|------|------|--------|------|
| Painel administrativo | :8787/apidoc/ | 28 | ~85 |
| C-side | :8788/apidoc/ | 25 | ~65 |

## 10. Lista de tabelas do banco de dados

### Versão base (14) + admin (7)
game_user, game_user_wallet, game_user_game_wallet, game_game, game_game_currency,
game_deposit_order, game_withdraw_order, game_exchange_record, game_transaction,
game_payment_method, game_announcement, game-platform_config, game_language, game_translation,
game_admin_user, game_admin_role, game_admin_permission, game_admin_user_role,
game_admin_role_permission, game_operation_log, game_system_config

### Versão padrão (10)
game_user_oauth, game_user_session, game_user_identity, game_user_payment_account,
game_withdraw_limit, game_game_server, game_game_play_log, game_risk_rule,
game_risk_log, game_stat_daily

### Versão completa (8)
game_game_category, game_game_category_rel, game_leaderboard, game_coupon,
game_user_coupon, game_country_config, game-platform_revenue

### Expansão do ecossistema (10) ← nova
game_ticket, game_ticket_reply, game_device_token,
game_vip_level, game_user_vip, game_exp_log,
game_achievement, game_user_achievement,
game_friend, game_message

**Total: 52 tabelas**

## 11. Feature flags

Baseado no namespace `feature.*` de `game-platform_config`, zero dependências adicionais:

| Flag | Padrão | Função |
|------|------|------|
| feature.tournament | off | Sistema de torneios |
| feature.chat | off | Mensagens privadas via WebSocket |
| feature.vip | off | Fidelidade VIP |
| feature.achievements | off | Insígnias de conquistas |

```php
use app\service\FeatureFlag;
if (FeatureFlag::isEnabled('vip')) { /* VIP logic */ }
```

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
