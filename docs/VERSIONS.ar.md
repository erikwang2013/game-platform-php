# مقارنة الإصدارات
<!-- lang-nav -->

Languages: **中文** · [English](VERSIONS.en.md) · [한국어](VERSIONS.ko.md) · [Русский](VERSIONS.ru.md) · [Deutsch](VERSIONS.de.md) · [Français](VERSIONS.fr.md) · [Español](VERSIONS.es.md) · [Português](VERSIONS.pt.md) · [हिन्दी](VERSIONS.hi.md) · [العربية](VERSIONS.ar.md) · [বাংলা](VERSIONS.bn.md) · [Bahasa Indonesia](VERSIONS.id.md) · [日本語](VERSIONS.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## نظرة عامة

| | الأساسي (Lite) | القياسي (Standard) | الكامل (Full) |
|------|------|------|------|
| جداول البيانات (install.sql) | 19 | 29 | **66**（22 جدولًا جديدًا في v1.3.15-22） |
| نقاط نهاية API | 38 | 54 | ~260 (admin+service، تشمل Webhook/Provider) |
| وحدات التحكم الخلفية | 14 | 22 | admin 46 + service 35 |
| نماذج البيانات | غير مشتركة | غير مشتركة | **مشتركة 52 (platform-common) + admin 8 + service 10** |
| Service المشتركة | دون طبقة مشتركة | دون طبقة مشتركة | حزمة مشتركة واحدة `packages/platform-common` |
| صفحات إدارة Admin | 11 | 13 | 15 |
| صفحات منصة الطرف C | 8 | 10 | 10 |
| HarmonyOS (admin) | - | دخول + لوحة تحكم | **8 صفحات** `admin/apps/harmonyos/` |
| HarmonyOS (الطرف C) | - | - | **5 صفحات** `apps/harmonyos/` (الدخول/لوبي الألعاب/التفاصيل/المحفظة/حسابي) |
| خدمات Docker | - | - | **7** (nginx/admin/service/leaderboard-ws/mysql/redis/elasticsearch) |
| حالات الاختبار | 60 | 60 | admin ~132؛ service 3 |

---

## مصادقة المستخدم

| الوظيفة | الأساسي | القياسي | الكامل |
|------|--------|--------|--------|
| تسجيل/دخول باسم المستخدم وكلمة المرور | ✓ | ✓ | ✓ |
| JWT Token (2h+14d) | ✓ | ✓ | ✓ |
| كابتشا النقر | stub | stub | ✓ poster-php |
| قفل الحساب (5 مرات/15 دقيقة) | ✓ | ✓ | ✓ |
| تقييد الجلسات (3 جلسات متزامنة) | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ 7 منصات (تشمل X/MS/LinkedIn/GitHub) |
| مصادقة ثنائية 2FA TOTP | - | - | ✓ |
| تصدير/إلغاء البيانات GDPR | - | - | ✓ |

---

## المحفظة والأموال

| الوظيفة | الأساسي | القياسي | الكامل |
|------|--------|--------|--------|
| محفظة عملات المنصة | ✓ | ✓ | ✓ |
| القفل التفاؤلي للمحفظة | ✓ | ✓ | ✓ |
| سجلات الحركات | ✓ | ✓ | ✓ |
| محفظة عملات الألعاب | ✓ | ✓ | ✓ |
| إنشاء طلب شحن (يملأ checkout_url/expires_at عند الإنشاء) | ✓ | ✓ | ✓ |
| إيداع تلقائي عند استدعاء الشحن | - | ✓ يدوي | ✓ تحقق توقيع Stripe (incl. Alipay/WeChat Pay)/PayPal/NowPayments IPN/Coinbase webhook |
| استعلام سعر الاستبدال/شراء/بيع | ✓ | ✓ | ✓ |
| إيراد فرق الاستبدال | ✓ | ✓ | ✓ |
| طلب السحب | ✓ | ✓ | ✓ |
| مفتاح السحب العام | ✓ | ✓ | ✓ |
| مراجعة السحب | ✓ يدوي | ✓ يدوي | ✓ دفعة + يدوي |
| حدود KYC المتدرجة | - | ✓ 3 مستويات | ✓ |
| رسوم السحب | - | - | ✓ |
| إيصال PDF | - | - | ✓ |

---

## إدارة الألعاب

| الوظيفة | الأساسي | القياسي | الكامل |
|------|--------|--------|--------|
| CRUD الألعاب | ✓ | ✓ | ✓ |
| إدارة عملات الألعاب | ✓ | ✓ | ✓ |
| قائمة/تفاصيل ألعاب الطرف C | ✓ | ✓ | ✓ |
| تشغيل اللعبة | ✓ | ✓ | ✓ |
| تصنيفات الألعاب (10 فئات) | - | - | ✓ |
| فلترة التصنيفات | - | - | ✓ |
| إدارة خوادم الألعاب | - | ✓ | ✓ |
| تتبع سجلات اللعب | - | ✓ | ✓ |
| بحث نصي كامل ES | - | - | ✓ |
| اقتراحات البحث | - | - | ✓ |
| Provider SDK لألعاب الطرف الثالث | - | - | ✓ HMAC-SHA256 |

---

## أدوات التشغيل

| الوظيفة | الأساسي | القياسي | الكامل |
|------|--------|--------|--------|
| إدارة الإعلانات | ✓ | ✓ | ✓ |
| لوحة التحكم | ✓ خلفية الإدارة | ✓ خلفية الإدارة | ✓ الإدارة + المنصة |
| تصدير Excel | ✓ | ✓ | ✓ |
| تصدير PDF | ✓ | ✓ | ✓ |
| رسوم بيانية حقيقية في لوحة التحكم | - | - | ✓ fl_chart |
| نظام القسائم | - | - | ✓ |
| لوحات المتصدرين (يومي/أسبوعي/شهري/إجمالي) | - | - | ✓ تخزين مؤقت Redis |
| لوحات متصدرين لحظية WebSocket | - | - | ✓ المنفذ 8789 |
| نظام الإشعارات (داخل الموقع + البريد) | - | - | ✓ |
| عمولة الإحالة | - | - | ✓ |
| لقطة الإحصائيات اليومية | - | ✓ | ✓ |
| تقارير البيانات (ملخص/يومي/CSV) | - | - | ✓ |
| إحصائيات المنصة (الجانب C) | - | - | ✓ |
| تتبع إيراد المنصة | - | - | ✓ |

---

## الأمان والامتثال

| الوظيفة | الأساسي | القياسي | الكامل |
|------|--------|--------|--------|
| دفاع متعمق من 18 طبقة | ✓ | ✓ | ✓ |
| التحكم بالصلاحيات RBAC | ✓ | ✓ | ✓ |
| سجلات تدقيق العمليات | ✓ | ✓ | ✓ |
| كشف مصدر 8 منصات | ✓ | ✓ | ✓ |
| تحديد معدل Redis بالنافذة المنزلقة | ✓ | ✓ | ✓ |
| التحقق من الهوية KYC | - | ✓ | ✓ |
| محرك إدارة المخاطر (4 قواعد) | - | ✓ | ✓ |
| تحقق توقيع استدعاء الدفع | - | - | ✓ |

---

## التدويل

| الوظيفة | الأساسي | القياسي | الكامل |
|------|--------|--------|--------|
| دعم متعدد اللغات | الصينية/الإنجليزية | 4 لغات | 4 لغات |
| جدول الترجمات + التخزين المؤقت | ✓ | ✓ | ✓ |
| كشف اللغة تلقائيًا | ✓ | ✓ | ✓ |
| إعدادات تفاضلية حسب الدولة | - | - | ✓ 8 دول |

---

## النشر والتشغيل

| الوظيفة | الأساسي | القياسي | الكامل |
|------|--------|--------|--------|
| نشر webman مستقل | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ 7 خدمات |
| وكيل Nginx العكسي | - | - | ✓ |
| CDN | - | - | ✓ تكامل 5 مزودين + إعدادات الإدارة/تمكين-تعطيل/اختبار الاتصال (بيانات الاعتماد مشفرة، service يقرأ من قاعدة البيانات فقط) |
| مهام Crontab المجدولة | - | ✓ | ✓ |
| مراقبة Prometheus | ✓ | ✓ | ✓ `/metrics` gauges الأعمال + عدادات الأحداث |
| فحص الصحة | ✓ | ✓ | ✓ |
| توثيق hg/apidoc عبر الإنترنت | - | - | ✓ 41 وحدة تحكم |

---

## العملاء

| الوظيفة | الأساسي | القياسي | الكامل |
|------|--------|--------|--------|
| لوحة إدارة Flutter Web PC | ✓ 5 صفحات | ✓ 11 صفحة | ✓ 17 صفحة |
| منصة مستخدمي Flutter Web PC | ✓ 5 صفحات | ✓ 8 صفحات | ✓ 10 صفحات |
| HarmonyOS admin | - | ✓ دخول + لوحة تحكم | ✓ 8 صفحات `admin/apps/harmonyos/` |
| HarmonyOS الطرف C | - | - | ✓ 5 صفحات `apps/harmonyos/` |

---

## جداول قاعدة البيانات

### الأساسي (19 جدولًا)
```
لوحة الإدارة (7):  game_admin_user, game_admin_role, game_admin_permission,
               game_admin_user_role, game_admin_role_permission,
               game_operation_log, game_system_config

نواة المنصة (12): game_user, game_user_wallet, game_user_game_wallet,
               game_game, game_game_currency, game_deposit_order,
               game_withdraw_order, game_exchange_record, game_transaction,
               game_payment_method, game_announcement, game-platform_config
```

### إضافات القياسي (10 جداول)
```
game_user_identity, game_user_oauth, game_user_payment_account,
game_user_session, game_game_server, game_game_play_log,
game_withdraw_limit, game_risk_rule, game_risk_log, game_stat_daily
```

### إضافات الكامل (13 جدولًا)
```
game_game_category, game_game_category_rel, game_leaderboard,
game_coupon, game_user_coupon, game_language, game_translation,
game_country_config, game-platform_revenue,
game_notification, game_referral, game_referral_reward, game_user_2fa
```

### إضافات v1.3.15-22 (22 جدولًا)
```
game_event_outbox, game_reconciliation_batch, game_reconciliation_diff,
game_reconciliation_statement, game_device_fingerprint, game_device_account_map,
game_ip_reputation, game_account_account_link,
game_activity, game_activity_participation, game_activity_reward_log,
game_anticheat_event, game_anticheat_daily_stat,
game_group, game_group_member, game_share_link,
game_aml_rule, game_aml_hit, game_kyc_level, game_user_kyc, game_user_trust, game_risk_cluster
```

---

## نقاط نهاية API

| الوحدة | الأساسي | القياسي | الكامل |
|------|--------|--------|--------|
| المصادقة | 3 | 3 | 7 (+OAuth×2 +2FA×5) |
| المحفظة | 2 | 2 | 3 (+استدعاء الشحن) |
| الاستبدال | 4 | 4 | 4 |
| السحب | 2 | 2 | 8 (+دفعة+حدود+مراجعة) |
| الألعاب | 3 | 4 | 7 (+خوادم+سجلات+بحث) |
| المستخدم | 2 | 2 | 7 (+KYC+GDPR+الخصوصية) |
| لوحة الإدارة | 18 | 25 | 79 |
| أدوات التشغيل | - | - | 30 (+لوحات متصدرين+قسائم+إشعارات+إحالة) |
| التدويل | 2 | 2 | 4 (+إعدادات الدول) |
| **الإجمالي** | **38** | **54** | **~260** |

---

## التوسعة البيئية (v2.0) — الجديدة

| الوظيفة | الوصف |
|------|------|
| طبقة GameProvider التجريدية | SelfProvider (معاملة DB) + ThirdPartyProvider (HTTP+توقيع) |
| بوابة Provider API | استدعاءات balance/bet/settle/refund + وسيطة ProviderAuth |
| نظام التذاكر | إنشاء/رد من الطرف C + معالجة/توزيع/إغلاق من الإدارة |
| التحقق من البريد | رمز 6 أرقام، انتهاء Redis 10 دقائق، حد إعادة إرسال 60 ثانية |
| إشعارات الدفع | PushService (FCM/APNs/دفع هواوي) |
| نظام VIP | 5 مستويات، خبرة تراكمية، ترقية تلقائية، خصم الاستبدال، تخفيض السحب، مكافأة سعر الصرف |
| نظام الإنجازات | 12 إنجازًا مدمجًا، كشف موجه بالأحداث، تتبع التقدم |
| نظام الأصدقاء | طلب/قبول/رفض/حذف/بحث |
| الرسائل الخاصة/المحادثة | REST + WebSocket رسائل لحظية (المنفذ 8790) |
| ناقل الأحداث | Redis Pub/Sub؛ emit INCR `metrics:event_*`؛ عملية الاستهلاك `EventConsumer` مُسجَّلة |
| مفاتيح الميزات | FeatureFlag مبني على DB؛ `inRollout`/`abTest` يقرآن `feature.{name}_percent` |
| Webhook | - | - | ✓ 7 أحداث + تسليم Pub/Sub |
| المحادثة | - | - | ✓ REST+WebSocket :8791 |
| نظام البطولات | - | - | ✓ FeatureFlag+tournament |
| شروط القسائم | - | - | ✓ min_deposit/first_user/game_id |
| عمولة متعددة المستويات | - | - | ✓ تقسيم المستويين |
| توثيق SDK | - | - | ✓ PHP/Go/Python |
| التحليل المتقدم | الاحتفاظ/D1-D30، قمع التحويل، ARPU/ARPPU |

### الجداول الجديدة (10 جداول)
```
game_ticket, game_ticket_reply, game_device_token,
game_vip_level, game_user_vip, game_exp_log,
game_achievement, game_user_achievement,
game_friend, game_message
```

### نقاط نهاية Provider الجديدة (4)
```
POST /api/provider/balance  — استعلام الرصيد
POST /api/provider/bet      — إشعار المراهنة
POST /api/provider/settle   — إشعار التسوية
POST /api/provider/refund   — إشعار الاسترداد
```

### نقاط نهاية الطرف C الجديدة (8)
```
POST /api/verify/send-email    — إرسال رمز تحقق البريد
POST /api/verify/confirm-email — تأكيد البريد الإلكتروني
GET  /api/ticket/list             — قائمة التذاكر
POST /api/ticket/create           — إنشاء تذكرة
GET  /api/ticket/{id}             — تفاصيل التذكرة
POST /api/ticket/{id}/reply       — الرد على التذكرة
GET  /api/user/vip-status         — حالة VIP
GET  /api/user/achievements       — قائمة الإنجازات
```

### نقاط نهاية لوحة الإدارة الجديدة (6)
```
GET  /admin/ticket/list          — قائمة التذاكر
GET  /admin/ticket/{id}          — تفاصيل التذكرة
POST /admin/ticket/{id}/reply    — الرد على التذكرة
POST /admin/ticket/{id}/close    — إغلاق التذكرة
POST /admin/ticket/{id}/assign   — تعيين المعالج
GET  /admin/analytics/retention  — تحليل الاحتفاظ
GET  /admin/analytics/funnel     — قمع التحويل
GET  /admin/analytics/arpu       — اتجاه ARPU
GET  /admin/analytics/economy    — المؤشرات الاقتصادية
```
