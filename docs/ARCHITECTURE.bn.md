# 架构文档
<!-- lang-nav -->

Languages: [中文](ARCHITECTURE.md) · [English](ARCHITECTURE.en.md) · [한국어](ARCHITECTURE.ko.md) · [Русский](ARCHITECTURE.ru.md) · [Deutsch](ARCHITECTURE.de.md) · [Français](ARCHITECTURE.fr.md) · [Español](ARCHITECTURE.es.md) · [Português](ARCHITECTURE.pt.md) · [हिन्दी](ARCHITECTURE.hi.md) · [العربية](ARCHITECTURE.ar.md) · **বাংলা** · [Bahasa Indonesia](ARCHITECTURE.id.md) · [日本語](ARCHITECTURE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. সিস্টেম টপোলজি

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

## 2. মডিউল আর্কিটেকচার

### 2.1 admin/ — অ্যাডমিন প্যানেল

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

### 2.2 service/ — C-এন্ড ব্যবসা

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

### 2.3 Provider লেয়ার — গেম ইন্টিগ্রেশন অ্যাবস্ট্রাকশন

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

### 2.4 EventBus — ইভেন্ট বাস

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

### 2.5 স্থিতিশীলতা নিশ্চয়তা — সার্কিট ব্রেকার / পুনঃচেষ্টা / ডিগ্রেডেশন

```
packages/platform-common/src/
├── CircuitBreaker.php   # 熔断 — Redis 状态 (cb:{key}:failures / opened_at)，阈值 5 / 窗口 30s
│                        #   达阈值抛 CircuitOpenException 快速失败；成功重置计数；半开探测
│                        #   Redis 不可用 fail-open，不影响主流程
└── Retry.php            # 重试 — 指数退避 (200/400/800ms)，仅网络类异常 (ConnectException/超时/cURL 28)
                         #   maxAttempts 上限 5；与熔断共用 isRetryable 判定
```

ডিগ্রেডেশন সুইচ `feature.provider_mock` (FeatureFlag / PlatformConfig, `on` হলে প্রকৃত নেটওয়ার্ক কল শর্ট-সার্কিট করে):

| প্রবেশ বিন্দু | mock=on আচরণ |
|--------|-------------|
| `PushService::send` | সাথে সাথে ফেরত, কোনো পুশ নয় |
| `PayoutService::execute` | `mock-{order_no}` ব্যাচ ফেরত দেয় এবং অর্ডার completed চিহ্নিত করে |
| `ThirdPartyProvider::request` | `['success' => true]` ফেরত দেয় |

সব প্রকৃত নেটওয়ার্ক কল `Retry::run → CircuitBreaker::call`-এ মোড়ানো (Push FCM/APNs/HarmonyOS, PayPal পেমেন্ট, থার্ড-পার্টি Provider অনুরোধ)।

## 3. মিডলওয়্যার এক্সিকিউশন চেইন

### admin/ (অ্যাডমিন প্যানেল)

```
请求 → Cors (跨域)
     → SecurityFilter (30+检测器→405/403)
     → RateLimit (Redis Lua滑动窗口→429)
     → AdminAuth (JWT认证→401)
     → AdminPermission (RBAC鉴权, Redis 60s缓存→403)
     → OperationLog (操作日志自动记录)
     → Controller → 响应
```

### service/ (C-এন্ড ব্যবসা)

```
常规API:
  请求 → Cors → SecurityFilter → RateLimit → Language → ApiVersion
       → [UserAuth] (JWT→401) → Controller → 响应

Provider API:
  请求 → Cors → SecurityFilter → RateLimit
       → ProviderAuth (HMAC-SHA256签名验证, 5min窗口→401)
       → ProviderController → 响应
```

## 4. কোর ডেটা ফ্লো

### 4.1 টপ-আপ প্রক্রিয়া

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

### 4.2 বিনিময় প্রক্রিয়া

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

### 4.3 উত্তোলন প্রক্রিয়া

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

### 4.4 গেম Provider ইন্টারঅ্যাকশন ফ্লো

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

### 4.5 VIP আপগ্রেড ফ্লো

```
充值完成 → VipService::addExp(userId, amount, 'deposit')
         → UserVip.exp += amount, UserVip.total_exp += amount
         → 查询下一级 VipLevel
         → exp >= required_exp → 升级: level+1, exp -= required_exp
         → 循环直到不再满足升级条件
         → EventBus::emit('user.vip_upgraded')
```

## 5. ডেটাবেস ER সম্পর্ক

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

## 6. ডিপ্লয়মেন্ট আর্কিটেকচার

### 6.1 ডেভেলপমেন্ট এনভায়রনমেন্ট

```
单机部署:
  admin/         :8787 (webman, 32 workers)
  service/       :8788 (webman, 32 workers)
  leaderboard-ws :8789 (WebSocket 排行榜)
  chat-ws        :8791 (WebSocket 聊天)
  MySQL          :3306
  Redis          :6379
```

### 6.2 Docker Compose (৮ সার্ভিস)

```yaml
nginx (80/443) → admin (8787) + service (8788) + static files
leaderboard-ws (8789) — WebSocket 排行榜实时推送
chat-ws (8791) — WebSocket 私信/聊天
mysql (3306) — 主数据库，数据卷持久化
redis (6379) — 缓存/限流/WebSocket/EventBus
elasticsearch (9200) — 全文检索
```

### 6.3 প্রোডাকশন এনভায়রনমেন্ট

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

## 7. টেস্ট আর্কিটেকচার

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

## 8. পোর্ট বরাদ্দ

| সার্ভিস | পোর্ট | বিবরণ |
|------|------|------|
| admin/ | 8787 | অ্যাডমিন প্যানেল API |
| service/ | 8788 | C-এন্ড ব্যবসা API |
| leaderboard-ws | 8789 | WebSocket রিয়েল-টাইম লিডারবোর্ড |
| chat-ws | 8791 | WebSocket প্রাইভেট মেসেজ/চ্যাট |
| MySQL | 3306 | মূল ডেটাবেস |
| Redis | 6379 | ক্যাশ/রেট লিমিট/WebSocket/EventBus |
| ClickHouse | 8123 | OLAP HTTP ইন্টারফেস |
| Elasticsearch | 9200 | ফুল-টেক্সট সার্চ |

## 9. API ডকুমেন্টেশন

কন্ট্রোলার অ্যানোটেশন থেকে `hg/apidoc` দিয়ে অটো-জেনারেটেড ইন্টারঅ্যাকটিভ API ডক:

| ডকুমেন্টেশন | ঠিকানা | কন্ট্রোলার | এন্ডপয়েন্ট |
|------|------|--------|------|
| অ্যাডমিন প্যানেল | :8787/apidoc/ | 28 | ~85 |
| C-এন্ড ব্যবসা | :8788/apidoc/ | 25 | ~65 |

## 10. ডেটাবেস টেবিল তালিকা

### বেসিক (১৪টি) + admin (৭টি)
erik_user, erik_user_wallet, erik_user_game_wallet, erik_game, erik_game_currency,
erik_deposit_order, erik_withdraw_order, erik_exchange_record, erik_transaction,
erik_payment_method, erik_announcement, erik_platform_config, erik_language, erik_translation,
erik_admin_user, erik_admin_role, erik_admin_permission, erik_admin_user_role,
erik_admin_role_permission, erik_operation_log, erik_system_config

### স্ট্যান্ডার্ড (১০টি)
erik_user_oauth, erik_user_session, erik_user_identity, erik_user_payment_account,
erik_withdraw_limit, erik_game_server, erik_game_play_log, erik_risk_rule,
erik_risk_log, erik_stat_daily

### ফুল (৮টি)
erik_game_category, erik_game_category_rel, erik_leaderboard, erik_coupon,
erik_user_coupon, erik_country_config, erik_platform_revenue

### ইকোসিস্টেম এক্সটেনশন (১০টি) ← নতুন
erik_ticket, erik_ticket_reply, erik_device_token,
erik_vip_level, erik_user_vip, erik_exp_log,
erik_achievement, erik_user_achievement,
erik_friend, erik_message

**মোট: ৫২টি টেবিল**

## 11. ফিচার সুইচ

`erik_platform_config`-এর `feature.*` নেমস্পেসের ভিত্তিতে, শূন্য অতিরিক্ত নির্ভরতা:

| সুইচ | ডিফল্ট | ফিচার |
|------|------|------|
| feature.tournament | off | টুর্নামেন্ট সিস্টেম |
| feature.chat | off | WebSocket প্রাইভেট মেসেজ |
| feature.vip | off | VIP লয়্যালটি |
| feature.achievements | off | অ্যাচিভমেন্ট ব্যাজ |

```php
use app\service\FeatureFlag;
if (FeatureFlag::isEnabled('vip')) { /* VIP logic */ }
```

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
