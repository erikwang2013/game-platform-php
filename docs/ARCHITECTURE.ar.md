# وثيقة البنية
<!-- lang-nav -->

Languages: **中文** · [English](ARCHITECTURE.en.md) · [한국어](ARCHITECTURE.ko.md) · [Русский](ARCHITECTURE.ru.md) · [Deutsch](ARCHITECTURE.de.md) · [Français](ARCHITECTURE.fr.md) · [Español](ARCHITECTURE.es.md) · [Português](ARCHITECTURE.pt.md) · [हिन्दी](ARCHITECTURE.hi.md) · [العربية](ARCHITECTURE.ar.md) · [বাংলা](ARCHITECTURE.bn.md) · [Bahasa Indonesia](ARCHITECTURE.id.md) · [日本語](ARCHITECTURE.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. طوبولوجيا النظام

```mermaid
flowchart TB
    subgraph "طبقة العملاء"
        A1["Flutter Web PC<br/>لوحة الإدارة"]
        A2["Flutter Web PC<br/>منصة مستخدمي الطرف C"]
        A3["HarmonyOS ArkTS<br/>عميل الهاتف/الجهاز اللوحي"]
    end

    subgraph "طبقة البوابة (Nginx)"
        B1["وكيل عكسي + HTTPS<br/>توزيع المسارات + Gzip<br/>خدمة الملفات الثابتة"]
    end

    subgraph "طبقة التطبيق"
        C1["admin/ webman<br/>لوحة الإدارة :8787<br/>AdminAuth → AdminPermission → OperationLog"]
        C2["service/ webman<br/>أعمال الطرف C :8788<br/>UserAuth → [ProviderAuth]"]
    end

    subgraph "طبقة الخدمات (جديدة)"
        D0["طبقة GameProvider التجريدية<br/>SelfProvider / ThirdPartyProvider<br/>توقيع HMAC-SHA256<br/>ضمان تناسق المعاملات"]
        D1["EventBus<br/>Redis Pub/Sub<br/>توزيع الأحداث غير المتزامن<br/>فصل الإنجازات/الإشعارات/التدقيق"]
        D2["محرك VIP<br/>تراكم الخبرة → ترقية تلقائية<br/>خصم الاستبدال/تخفيض السحب<br/>مكافأة سعر الصرف"]
        D3["محرك الإنجازات<br/>12 إنجازًا مدمجًا<br/>تتبع التقدم<br/>كشف موجه بالأحداث"]
        D4["مفاتيح الميزات<br/>FeatureFlag<br/>إعداد ديناميكي بدون تبعيات"]
    end

    subgraph "طبقة التخزين"
        E1[("MySQL 8.0<br/>التخزين الرئيسي<br/>52 جدولًا")]
        E2[("Redis<br/>Session/تخزين مؤقت/تقييد<br/>EventBus/نبض")]
        E3[("Elasticsearch<br/>بحث نصي كامل")]
        E4[("ClickHouse<br/>تحليل OLAP<br/>حساب الاحتمالات")]
    end

    subgraph "التكامل الخارجي"
        F1["ألعاب الطرف الثالث<br/>Provider API<br/>الرصيد/المراهنة/التسوية/الاسترداد"]
        F2["قنوات الدفع<br/>FCM / APNs<br/>دفع هواوي"]
        F3["OAuth (7 منصات)<br/>Google/Facebook/Apple<br/>X(Twitter)/Microsoft<br/>LinkedIn/GitHub"]
    end

    A1 & A2 & A3 -->|"HTTPS/JSON<br/>JWT Bearer"| B1
    B1 -->|"/admin/*"| C1
    B1 -->|"/api/*"| C2
    C1 & C2 --> D0 & D1 & D2 & D3 & D4
    C2 -->|"/api/provider/*"| F1
    C1 & C2 --> E1 & E2 & E3 & E4
    C2 --> F2 & F3
```

## 2. بنية الوحدات

### 2.1 admin/ — لوحة الإدارة

```
طبقة المسارات: config/route.php
  ↓
سلسلة الوسائط: Cors → SecurityFilter → RateLimit → AdminAuth → AdminPermission → OperationLog
  ↓
طبقة وحدات التحكم (28):
  ┌──────────────────────────────────────────────────────────┐
  │ Dashboard / User / Role / Permission / Config / Log      │ ← أصلي
  │ Profile / Export / Import / Upload / Health / Docs       │ ← أصلي
  │ Game / Withdraw / Payment / PlatformUser / Announce      │ ← أصلي
  │ Analytics / GameCategory / GameServer / Identity         │ ← أصلي
  │ CountryConfig / Coupon / Leaderboard / Metrics           │ ← أصلي
  │ Ticket / Search                                          │ ← جديد
  └──────────────────────────────────────────────────────────┘
  ↓
طبقة الخدمات: VIP / Achievement / EventBus / FeatureFlag / Risk / Notification
  ↓
طبقة Provider: GameProvider → SelfProvider / ThirdPartyProvider
  ↓
طبقة التخزين: MySQL / Redis / Elasticsearch / ClickHouse
```

### 2.2 service/ — طرف أعمال الطرف C

```
طبقة المسارات: config/route.php
  ↓
سلسلة الوسائط: Cors → SecurityFilter → RateLimit → Language → ApiVersion → [UserAuth | ProviderAuth]
  ↓
طبقة وحدات التحكم (25):
  ┌──────────────────────────────────────────────────────────┐
  │ Auth / Wallet / Deposit / Exchange / Withdraw            │ ← أصلي
  │ Game / User / Announcement / Captcha                     │ ← أصلي
  │ OAuth / Identity / Payment / GamePlayLog                 │ ← أصلي
  │ Leaderboard / Notification / Referral / TwoFactor        │ ← أصلي
  │ Country / Language / Coupon / Search                     │ ← أصلي
  │ Provider / Ticket / Verification                         │ ← جديد
  └──────────────────────────────────────────────────────────┘
  ↓
طبقة الخدمات: VIP / Achievement / EventBus / FeatureFlag / Risk / GameSession
  ↓
طبقة Provider: GameProvider → SelfProvider / ThirdPartyProvider
  ↓
طبقة التخزين: MySQL / Redis / Elasticsearch / ClickHouse
```

### 2.3 طبقة Provider — تجريد ربط الألعاب

```
provider/
├── GameProvider.php          # الفئة الأساسية المجردة — واجهة موحدة
│   ├── getBalance()          # الاستعلام عن الرصيد
│   ├── bet()                 # المراهنة
│   ├── settle()              # التسوية
│   ├── refund()              # الاسترداد
│   ├── rollback()            # التراجع
│   ├── verifySignature()     # التحقق من توقيع الاستدعاء
│   └── signRequest()         # توليد توقيع الطلب (HMAC-SHA256)
├── SelfProvider.php          # ألعاب مطوّرة ذاتيًا — تناسق معاملات DB
├── ThirdPartyProvider.php    # ألعاب طرف ثالث — HTTP API + توقيع
└── ProviderFactory.php       # المصنع — match(game.type)
```

### 2.4 EventBus — ناقل الأحداث

```
نشر الأحداث:
  DepositController → EventBus::emit('deposit.completed', $payload)
  ExchangeController → EventBus::emit('exchange.completed', $payload)
  GameController → EventBus::emit('game.played', $payload)
  ReferralController → EventBus::emit('referral.applied', $payload)

Redis Pub/Sub (القناة: platform:events):
  ↓
المشتركون:
  AchievementService  — كشف تقدم الإنجازات
  VipService          — تراكم نقاط الخبرة
  NotificationService — إرسال الإشعارات
  WebhookController   — تسليم webhook الخارجية

> ملاحظة: حتى 2026-08-18، لـ `emit()` مستدعون لكن لا توجد أي عملية مسجّلة لـ `subscribe()` (P0-4 لم يُنفَّذ)، الأحداث حاليًا تُنشر دون استهلاك، والمشتركون أهداف تصميمية.
```

### 2.5 ضمان الاستقرار — قاطع الدائرة / إعادة المحاولة / التدهور

```
packages/platform-common/src/
├── CircuitBreaker.php   # 熔断 — Redis 状态 (cb:{key}:failures / opened_at)，阈值 5 / 窗口 30s
│                        #   达阈值抛 CircuitOpenException 快速失败；成功重置计数；半开探测
│                        #   Redis 不可用 fail-open，不影响主流程
└── Retry.php            # 重试 — 指数退避 (200/400/800ms)，仅网络类异常 (ConnectException/超时/cURL 28)
                         #   maxAttempts 上限 5；与熔断共用 isRetryable 判定
```

مفتاح التدهور `feature.provider_mock` (FeatureFlag / PlatformConfig، يختصر المكالمات الحقيقية عند `on`):

| نقطة الدخول | السلوك عند mock=on |
|--------|-------------|
| `PushService::send` | عودة فورية، لا إرسال إشعار |
| `PayoutService::execute` | يرجع دفعة `mock-{order_no}` ويحدد الطلب completed |
| `ThirdPartyProvider::request` | يرجع `['success' => true]` |

جميع المكالمات الحقيقية مغلفة بـ `Retry::run → CircuitBreaker::call` (Push FCM/APNs/HarmonyOS، مدفوعات PayPal، طلبات موفري الطرف الثالث).

## 3. سلسلة تنفيذ الوسائط

### admin/ (لوحة الإدارة)

```
الطلب → Cors (مشاركة الموارد عبر الأصول)
     → SecurityFilter (أكثر من 30 كاشفًا →405/403)
     → RateLimit (نافذة Redis Lua المنزلقة →429)
     → AdminAuth (مصادقة JWT →401)
     → AdminPermission (تفويض RBAC، تخزين مؤقت Redis 60s →403)
     → OperationLog (تسجيل العمليات تلقائيًا)
     → Controller → الاستجابة
```

### service/ (أعمال الطرف C)

```
واجهات عادية:
  الطلب → Cors → SecurityFilter → RateLimit → Language → ApiVersion
       → [UserAuth] (JWT →401) → Controller → الاستجابة

واجهات Provider:
  الطلب → Cors → SecurityFilter → RateLimit
       → ProviderAuth (التحقق من توقيع HMAC-SHA256، نافذة 5 دقائق →401)
       → ProviderController → الاستجابة
```

## 4. تدفقات البيانات الأساسية

### 4.1 عملية الشحن

```
المستخدم → POST /api/deposit/create → إنشاء الطلب (status=pending)
     → إنشاء الدفع عبر GatewayFactory (Stripe Checkout (incl. Alipay/WeChat Pay APM)/NowPayments invoice/Coinbase charge) → تعبئة checkout_url + expires_at(+1h)؛ عند الفشل إلغاء الطلب عبر CAS وإعادة المحاولة
     → الانتقال إلى الدفع عبر الطرف الثالث (Stripe (incl. Alipay/WeChat Pay)/PayPal/NowPayments[USDT TRC20/ERC20]/Coinbase[USDC/BTC/ETH])
     → نجاح الدفع → استدعاء /api/payment/callback
     → القائمة البيضاء للمزود (stripe/paypal/nowpayments/coinbase/skrill/neteller/paysafecard/paytm/mercadopago/astropay/paypay/kakaopay/gcash فقط) + التحقق من انتحال القنوات المتقاطعة + التحقق من التوقيع (fail-closed) + الطابع الزمني ±300s + مطابقة المبلغ bccomp
     → تحديث الطلب (status=confirmed، معاملاتي)
     → UserWallet::addBalance() → إيداع عملات المنصة
     → EventBus::emit('deposit.completed')
       → VipService::addExp() → تراكم EXP → كشف ترقية VIP
       → AchievementService::check() → تحديث تقدم الإنجازات
     → تسجيل Transaction (type=deposit)
```

### 4.2 عملية الاستبدال

```
المستخدم → POST /api/exchange/quote → الاستعلام عن السعر
     → VipService::getExchangeDiscount() → تطبيق خصم VIP
     → VipService::getRateBonus() → تطبيق مكافأة سعر الصرف VIP
     → التأكيد → POST /api/exchange/buy (أو sell)
     → DB::beginTransaction()
     ├─ خصم العملة المصدر (lockForUpdate)
     ├─ زيادة العملة الهدف
     ├─ تسجيل ExchangeRecord
     ├─ تسجيل Transaction
     └─ DB::commit()
     → EventBus::emit('exchange.completed')
       → AchievementService::check()
```

### 4.3 عملية السحب

```
المستخدم → POST /api/withdraw/apply
     → VipService::getWithdrawFeeDiscount() → تطبيق تخفيض رسوم السحب VIP
     → فحص المفتاح العام (PlatformConfig)
     → فحص الحدود (min_amount / daily_limit)
     → فحص الرصيد → خصم الرصيد
     → المبلغ < العتبة → موافقة تلقائية (auto-approved)
     → المبلغ >= العتبة → pending (مراجعة بشرية)
     → تسجيل Transaction

المشرف → PUT /admin/withdraw/review
       → approve: وضع علامة مكتمل
       → reject: إعادة عملات المنصة + سجل الاسترداد
```

### 4.4 تدفق تفاعل Provider الألعاب

```
خادم اللعبة من الطرف الثالث:
  POST /api/provider/balance
    X-Game-Id + X-Timestamp + X-Signature (HMAC-SHA256)
    → يتحقق ProviderAuth من التوقيع → ProviderFactory::createById()
    → GameProvider::getBalance() → إرجاع الرصيد

  POST /api/provider/bet
    → ProviderAuth → GameProvider::bet()
    → SelfProvider: خصم معاملاتي DB (SELECT FOR UPDATE)
    → ThirdPartyProvider: توجيه HTTP إلى جهة اللعبة
    → تسجيل GamePlayLog (action=bet, round_id)

  POST /api/provider/settle
    → ProviderAuth → GameProvider::settle()
    → زيادة رصيد عملات اللعبة → تحديث GamePlayLog.ended_at

  POST /api/provider/refund
    → ProviderAuth → GameProvider::refund()
    → إعادة الرصيد → تسجيل سجل الاسترداد
```

### 4.5 تدفق ترقية VIP

```
اكتمال الشحن → VipService::addExp(userId, amount, 'deposit')
         → UserVip.exp += amount, UserVip.total_exp += amount
         → الاستعلام عن VipLevel التالي
         → exp >= required_exp → ترقية: level+1, exp -= required_exp
         → التكرار حتى لا يتحقق شرط الترقية
         → EventBus::emit('user.vip_upgraded')
```

## 5. علاقات ER في قاعدة البيانات

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

## 6. بنية النشر

### 6.1 بيئة التطوير

```
نشر على جهاز واحد:
  admin/         :8787 (webman, 32 عمال)
  service/       :8788 (webman, 32 عمال)
  leaderboard-ws :8789 (WebSocket لوحة المتصدرين)
  chat-ws        :8791 (WebSocket المحادثة)
  MySQL          :3306
  Redis          :6379
```

### 6.2 Docker Compose (8 خدمات)

```yaml
nginx (80/443) → admin (8787) + service (8788) + الملفات الثابتة
leaderboard-ws (8789) — دفع لحظي للوحة المتصدرين عبر WebSocket
chat-ws (8791) — رسائل خاصة/محادثة عبر WebSocket
mysql (3306) — قاعدة البيانات الرئيسية، استمرارية البيانات عبر وحدة التخزين
redis (6379) — تخزين مؤقت/تقييد/WebSocket/EventBus
elasticsearch (9200) — بحث نصي كامل
```

### 6.3 بيئة الإنتاج

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "خوادم الويب (Nginx)"
        NGX["وكيل عكسي :443 HTTPS<br/>خدمة الملفات الثابتة<br/>gzip + CSP + HSTS<br/>limit_req تقييد"]
    end

    subgraph "خوادم التطبيقات"
        ADM1["admin :8787"]
        ADM2["admin :8787"]
        SVC1["service :8788"]
        SVC2["service :8788"]
        WS1["leaderboard-ws :8789"]
        WS2["chat-ws :8791"]
    end

    subgraph "طبقة البيانات"
        MYSQL["MySQL 8.0 النسخ المتماثل الرئيسي-التابع"]
        REDIS["Redis 7.x وضع الحارس<br/>EventBus Pub/Sub"]
        ES["Elasticsearch 8.x"]
        CH["ClickHouse OLAP"]
    end

    subgraph "المراقبة"
        MON["Grafana + Prometheus<br/>فحص الصحة /metrics"]
    end

    DNS --> NGX
    NGX --> ADM1 & ADM2 & SVC1 & SVC2
    ADM1 & ADM2 & SVC1 & SVC2 --> MYSQL & REDIS & ES & CH
    ADM1 & ADM2 & SVC1 & SVC2 --> MON
```

## 7. بنية الاختبارات

```
tests/
├── bootstrap.php                  # إقلاع PHPUnit
├── PlatformTest.php               # 56 اختبارًا لمنطق الأعمال
├── BackendEnhancementTest.php     # 23 اختبارًا للتشفير/خدمات المعرّفات
├── CaptchaTest.php                # 7 اختبارات للكابتشا
├── EncryptionServiceTest.php      # 6 اختبارات للتشفير وفك التشفير
├── EnvConfigTest.php              # 4 اختبارات لإعدادات البيئة
├── HashidsServiceTest.php         # 8 اختبارات لترميز وفك ترميز المعرّفات
└── SnowflakeServiceTest.php       # 6 اختبارات لمعرّفات Snowflake
```

## 8. توزيع المنافذ

| الخدمة | المنفذ | الوصف |
|------|------|------|
| admin/ | 8787 | واجهات لوحة الإدارة |
| service/ | 8788 | واجهات أعمال الطرف C |
| leaderboard-ws | 8789 | WebSocket لوحة المتصدرين اللحظية |
| chat-ws | 8791 | WebSocket الرسائل الخاصة/المحادثة |
| MySQL | 3306 | قاعدة البيانات الرئيسية |
| Redis | 6379 | تخزين مؤقت/تقييد/WebSocket/EventBus |
| ClickHouse | 8123 | واجهة OLAP HTTP |
| Elasticsearch | 9200 | بحث نصي كامل |

## 9. توثيق الواجهات

يُستخدَم `hg/apidoc` لتوليد توثيق واجهات تفاعلي تلقائيًا عبر شروحات وحدات التحكم:

| التوثيق | العنوان | وحدات التحكم | نقاط النهاية |
|------|------|--------|------|
| لوحة الإدارة | :8787/apidoc/ | 28 | ~85 |
| أعمال الطرف C | :8788/apidoc/ | 25 | ~65 |

## 10. قائمة جداول قاعدة البيانات

### الإصدار الأساسي (14 جدولًا) + admin (7 جداول)
game_user, game_user_wallet, game_user_game_wallet, game_game, game_game_currency,
game_deposit_order, game_withdraw_order, game_exchange_record, game_transaction,
game_payment_method, game_announcement, game-platform_config, game_language, game_translation,
game_admin_user, game_admin_role, game_admin_permission, game_admin_user_role,
game_admin_role_permission, game_operation_log, game_system_config

### الإصدار القياسي (10 جداول)
game_user_oauth, game_user_session, game_user_identity, game_user_payment_account,
game_withdraw_limit, game_game_server, game_game_play_log, game_risk_rule,
game_risk_log, game_stat_daily

### الإصدار الكامل (8 جداول)
game_game_category, game_game_category_rel, game_leaderboard, game_coupon,
game_user_coupon, game_country_config, game-platform_revenue

### التوسعة البيئية (10 جداول) ← جديدة
game_ticket, game_ticket_reply, game_device_token,
game_vip_level, game_user_vip, game_exp_log,
game_achievement, game_user_achievement,
game_friend, game_message

**الإجمالي: 52 جدولًا**

## 11. مفاتيح الميزات

استنادًا إلى مساحة أسماء `feature.*` في `game-platform_config`، بدون أي تبعيات إضافية:

| المفتاح | الافتراضي | الوظيفة |
|------|------|------|
| feature.tournament | off | نظام البطولات |
| feature.chat | off | رسائل WebSocket الخاصة |
| feature.vip | off | ولاء VIP |
| feature.achievements | off | شارات الإنجازات |

```php
use app\service\FeatureFlag;
if (FeatureFlag::isEnabled('vip')) { /* VIP logic */ }
```

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
