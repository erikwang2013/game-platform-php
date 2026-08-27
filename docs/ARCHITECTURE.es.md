# Documento de arquitectura
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE.md) · [English](ARCHITECTURE.en.md) · [한국어](ARCHITECTURE.ko.md) · [Русский](ARCHITECTURE.ru.md) · [Deutsch](ARCHITECTURE.de.md) · [Français](ARCHITECTURE.fr.md) · **Español** · [Português](ARCHITECTURE.pt.md) · [हिन्दी](ARCHITECTURE.hi.md) · [العربية](ARCHITECTURE.ar.md) · [বাংলা](ARCHITECTURE.bn.md) · [Bahasa Indonesia](ARCHITECTURE.id.md) · [日本語](ARCHITECTURE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Topología del sistema

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

## 2. Arquitectura de módulos

### 2.1 admin/ — Panel de administración

```
路由层: config/route.php
  ↓
中间件链: Cors → SecurityFilter → RateLimit → AdminAuth → AdminPermission → OperationLog
  ↓
控制器层 (28 个):
  ┌──────────────────────────────────────────────────────────┐
  │ Dashboard / User / Role / Permission / Config / Log      │ ← 原有
  │ Profile / Export / Import / Upload / Health / Docs       │ ← 原有
  │ Game / Withdraw / Payment / PlatformUser / Announce      │ ← 原有
  │ Analytics / GameCategory / GameServer / Identity         │ ← 原有
  │ CountryConfig / Coupon / Leaderboard / Metrics           │ ← 原有
  │ Ticket / Search                                          │ ← 新增
  └──────────────────────────────────────────────────────────┘
  ↓
服务层: VIP / Achievement / EventBus / FeatureFlag / Risk / Notification
  ↓
Provider 层: GameProvider → SelfProvider / ThirdPartyProvider
  ↓
存储层: MySQL / Redis / Elasticsearch / ClickHouse
```

### 2.2 service/ — End de negocio del lado C

```
路由层: config/route.php
  ↓
中间件链: Cors → SecurityFilter → RateLimit → Language → ApiVersion → [UserAuth | ProviderAuth]
  ↓
控制器层 (25 个):
  ┌──────────────────────────────────────────────────────────┐
  │ Auth / Wallet / Deposit / Exchange / Withdraw            │ ← 原有
  │ Game / User / Announcement / Captcha                     │ ← 原有
  │ OAuth / Identity / Payment / GamePlayLog                 │ ← 原有
  │ Leaderboard / Notification / Referral / TwoFactor        │ ← 原有
  │ Country / Language / Coupon / Search                     │ ← 原有
  │ Provider / Ticket / Verification                         │ ← 新增
  └──────────────────────────────────────────────────────────┘
  ↓
服务层: VIP / Achievement / EventBus / FeatureFlag / Risk / GameSession
  ↓
Provider 层: GameProvider → SelfProvider / ThirdPartyProvider
  ↓
存储层: MySQL / Redis / Elasticsearch / ClickHouse
```

### 2.3 Capa Provider — abstracción de integración de juegos

```
provider/
├── GameProvider.php          # 抽象基类 — 统一接口
│   ├── getBalance()          # 查询余额
│   ├── bet()                 # 下注
│   ├── settle()              # 结算
│   ├── refund()              # 退款
│   ├── rollback()            # 回滚
│   ├── verifySignature()     # 验证回调签名
│   └── signRequest()         # 生成请求签名 (HMAC-SHA256)
├── SelfProvider.php          # 自研游戏 — DB事务一致
├── ThirdPartyProvider.php    # 第三方游戏 — HTTP API + 签名
└── ProviderFactory.php       # 工厂 — match(game.type)
```

### 2.4 EventBus — Bus de eventos

```
事件发布:
  DepositController → EventBus::emit('deposit.completed', $payload)
  ExchangeController → EventBus::emit('exchange.completed', $payload)
  GameController → EventBus::emit('game.played', $payload)
  ReferralController → EventBus::emit('referral.applied', $payload)

Redis Pub/Sub (channel: platform:events):
  ↓
订阅者:
  AchievementService  — 检测成就进度
  VipService          — 累计经验值
  NotificationService — 发送通知
  WebhookController   — 投递外部 webhook

> 注：截至 2026-08-18，`emit()` 有调用方但 `subscribe()` 无任何进程注册（P0-4 未做），事件目前仅发布无消费，订阅者为设计目标。
```

### 2.5 Garantía de estabilidad — interruptor / reintento / degradación

```
packages/platform-common/src/
├── CircuitBreaker.php   # 熔断 — Redis 状态 (cb:{key}:failures / opened_at)，阈值 5 / 窗口 30s
│                        #   达阈值抛 CircuitOpenException 快速失败；成功重置计数；半开探测
│                        #   Redis 不可用 fail-open，不影响主流程
└── Retry.php            # 重试 — 指数退避 (200/400/800ms)，仅网络类异常 (ConnectException/超时/cURL 28)
                         #   maxAttempts 上限 5；与熔断共用 isRetryable 判定
```

Interruptor de degradación `feature.provider_mock` (FeatureFlag / PlatformConfig, hace cortocircuito de llamadas de red reales cuando `on`):

| Punto de entrada | Comportamiento con mock=on |
|--------|-------------|
| `PushService::send` | Retorno inmediato, no envía notificación |
| `PayoutService::execute` | Devuelve el lote `mock-{order_no}` y marca el pedido completed |
| `ThirdPartyProvider::request` | Devuelve `['success' => true]` |

Todas las llamadas de red reales están envueltas en `Retry::run → CircuitBreaker::call` (Push FCM/APNs/HarmonyOS, pagos PayPal, solicitudes de Provider de terceros).

## 3. Cadena de ejecución de middleware

### admin/ (panel de administración)

```
请求 → Cors (跨域)
     → SecurityFilter (30+检测器→405/403)
     → RateLimit (Redis Lua滑动窗口→429)
     → AdminAuth (JWT认证→401)
     → AdminPermission (RBAC鉴权, Redis 60s缓存→403)
     → OperationLog (操作日志自动记录)
     → Controller → 响应
```

### service/ (end de negocio del lado C)

```
常规API:
  请求 → Cors → SecurityFilter → RateLimit → Language → ApiVersion
       → [UserAuth] (JWT→401) → Controller → 响应

Provider API:
  请求 → Cors → SecurityFilter → RateLimit
       → ProviderAuth (HMAC-SHA256签名验证, 5min窗口→401)
       → ProviderController → 响应
```

## 4. Flujos de datos principales

### 4.1 Flujo de recarga

```
用户 → POST /api/deposit/create → 生成订单 (status=pending)
     → 跳转第三方支付 (Stripe/PayPal)
     → 支付成功 → 回调 /api/payment/callback
     → provider 白名单(仅 stripe/paypal) + 跨渠道冒用校验 + 验签(fail-closed) + 时间戳±300s + bccomp 金额核对
     → 更新订单 (status=confirmed, 事务化)
     → UserWallet::addBalance() → 平台币到账
     → EventBus::emit('deposit.completed')
       → VipService::addExp() → EXP累计 → VIP升级检测
       → AchievementService::check() → 成就进度更新
     → 记录 Transaction (type=deposit)
```

### 4.2 Flujo de conversión

```
用户 → POST /api/exchange/quote → 询价
     → VipService::getExchangeDiscount() → 应用VIP折扣
     → VipService::getRateBonus() → 应用VIP汇率加成
     → 确认 → POST /api/exchange/buy(或sell)
     → DB::beginTransaction()
     ├─ 扣减源币种 (lockForUpdate)
     ├─ 增加目标币种
     ├─ 记录 ExchangeRecord
     ├─ 记录 Transaction
     └─ DB::commit()
     → EventBus::emit('exchange.completed')
       → AchievementService::check()
```

### 4.3 Flujo de retiro

```
用户 → POST /api/withdraw/apply
     → VipService::getWithdrawFeeDiscount() → 应用VIP手续费减免
     → 检查全局开关 (PlatformConfig)
     → 检查限额 (min_amount / daily_limit)
     → 检查余额 → 扣减余额
     → 金额<阈值 → auto-approved
     → 金额≥阈值 → pending (人工审核)
     → 记录 Transaction

管理员 → PUT /admin/withdraw/review
       → approve: 标记完成
       → reject: 退回平台币 + 退款流水
```

### 4.4 Flujo de interacción con el Provider de juegos

```
第三方游戏服务器:
  POST /api/provider/balance
    X-Game-Id + X-Timestamp + X-Signature (HMAC-SHA256)
    → ProviderAuth 验证签名 → ProviderFactory::createById()
    → GameProvider::getBalance() → 返回余额

  POST /api/provider/bet
    → ProviderAuth → GameProvider::bet()
    → SelfProvider: DB事务扣减 (SELECT FOR UPDATE)
    → ThirdPartyProvider: HTTP转发到游戏方
    → 记录 GamePlayLog (action=bet, round_id)

  POST /api/provider/settle
    → ProviderAuth → GameProvider::settle()
    → 增加游戏币余额 → 更新 GamePlayLog.ended_at

  POST /api/provider/refund
    → ProviderAuth → GameProvider::refund()
    → 退回余额 → 记录退款日志
```

### 4.5 Flujo de subida VIP

```
充值完成 → VipService::addExp(userId, amount, 'deposit')
         → UserVip.exp += amount, UserVip.total_exp += amount
         → 查询下一级 VipLevel
         → exp >= required_exp → 升级: level+1, exp -= required_exp
         → 循环直到不再满足升级条件
         → EventBus::emit('user.vip_upgraded')
```

## 5. Relaciones ER de la base de datos

```
erik_user ──┬── 1:1 ── erik_user_wallet
            ├── 1:1 ── erik_user_vip ── erik_vip_level
            ├── 1:N ── erik_user_game_wallet
            ├── 1:N ── erik_deposit_order
            ├── 1:N ── erik_withdraw_order
            ├── 1:N ── erik_exchange_record
            ├── 1:N ── erik_transaction
            ├── 1:N ── erik_user_achievement ── erik_achievement
            ├── 1:N ── erik_exp_log
            ├── 1:N ── erik_ticket ── erik_ticket_reply
            ├── 1:N ── erik_device_token
            ├── 1:N ── erik_user_session
            └── 1:N ── erik_message

erik_game ──┬── 1:N ── erik_game_currency
            ├── 1:N ── erik_user_game_wallet
            ├── 1:N ── erik_exchange_record
            └── 1:N ── erik_game_play_log

erik_friend ── user_id → erik_user
             └── friend_id → erik_user

erik_vip_level ── 1:N ── erik_user_vip
erik_achievement ── 1:N ── erik_user_achievement
```

## 6. Arquitectura de despliegue

### 6.1 Entorno de desarrollo

```
单机部署:
  admin/         :8787 (webman, 32 workers)
  service/       :8788 (webman, 32 workers)
  leaderboard-ws :8789 (WebSocket 排行榜)
  chat-ws        :8791 (WebSocket 聊天)
  MySQL          :3306
  Redis          :6379
```

### 6.2 Docker Compose (8 servicios)

```yaml
nginx (80/443) → admin (8787) + service (8788) + static files
leaderboard-ws (8789) — WebSocket 排行榜实时推送
chat-ws (8791) — WebSocket 私信/聊天
mysql (3306) — 主数据库，数据卷持久化
redis (6379) — 缓存/限流/WebSocket/EventBus
elasticsearch (9200) — 全文检索
```

### 6.3 Entorno de producción

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

## 7. Arquitectura de pruebas

```
tests/
├── bootstrap.php                  # PHPUnit 引导
├── PlatformTest.php               # 56 个业务逻辑测试
├── BackendEnhancementTest.php     # 23 个加密/ID服务测试
├── CaptchaTest.php                # 7 个验证码测试
├── EncryptionServiceTest.php      # 6 个加解密测试
├── EnvConfigTest.php              # 4 个环境配置测试
├── HashidsServiceTest.php         # 8 个 ID 编解码测试
└── SnowflakeServiceTest.php       # 6 个 Snowflake ID 测试
```

## 8. Asignación de puertos

| Servicio | Puerto | Descripción |
|------|------|------|
| admin/ | 8787 | API del panel de administración |
| service/ | 8788 | API de negocio del lado C |
| leaderboard-ws | 8789 | Clasificación en tiempo real WebSocket |
| chat-ws | 8791 | Mensajes privados/chat WebSocket |
| MySQL | 3306 | Base de datos principal |
| Redis | 6379 | Caché/limitación/WebSocket/EventBus |
| ClickHouse | 8123 | Interfaz HTTP de OLAP |
| Elasticsearch | 9200 | Búsqueda de texto completo |

## 9. Documentación de la API

Se usa `hg/apidoc` para generar automáticamente la documentación de la API interactiva a partir de las anotaciones de los controladores:

| Documento | Dirección | Controladores | Endpoints |
|------|------|--------|------|
| Panel de administración | :8787/apidoc/ | 28 | ~85 |
| Negocio del lado C | :8788/apidoc/ | 25 | ~65 |

## 10. Lista de tablas de la base de datos

### Versión básica (14 tablas) + admin (7 tablas)
erik_user, erik_user_wallet, erik_user_game_wallet, erik_game, erik_game_currency,
erik_deposit_order, erik_withdraw_order, erik_exchange_record, erik_transaction,
erik_payment_method, erik_announcement, erik_platform_config, erik_language, erik_translation,
erik_admin_user, erik_admin_role, erik_admin_permission, erik_admin_user_role,
erik_admin_role_permission, erik_operation_log, erik_system_config

### Versión estándar (10 tablas)
erik_user_oauth, erik_user_session, erik_user_identity, erik_user_payment_account,
erik_withdraw_limit, erik_game_server, erik_game_play_log, erik_risk_rule,
erik_risk_log, erik_stat_daily

### Versión completa (8 tablas)
erik_game_category, erik_game_category_rel, erik_leaderboard, erik_coupon,
erik_user_coupon, erik_country_config, erik_platform_revenue

### Extensión de ecosistema (10 tablas) ← nuevas
erik_ticket, erik_ticket_reply, erik_device_token,
erik_vip_level, erik_user_vip, erik_exp_log,
erik_achievement, erik_user_achievement,
erik_friend, erik_message

**Total: 52 tablas**

## 11. Características (feature flags)

Basado en el namespace `feature.*` de `erik_platform_config`, sin dependencias adicionales:

| Interruptor | Predeterminado | Función |
|------|------|------|
| feature.tournament | off | Sistema de torneos |
| feature.chat | off | Mensajes privados WebSocket |
| feature.vip | off | Fidelización VIP |
| feature.achievements | off | Insignias de logros |

```php
use app\service\FeatureFlag;
if (FeatureFlag::isEnabled('vip')) { /* VIP logic */ }
```

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
