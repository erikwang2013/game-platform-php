# منصة تجميع الألعاب العالمية — تقرير مراجعة التوسعة البيئية v2.0
<!-- lang-nav -->

Languages: **中文** · [English](PLATFORM-AUDIT-REPORT.en.md) · [한국어](PLATFORM-AUDIT-REPORT.ko.md) · [Русский](PLATFORM-AUDIT-REPORT.ru.md) · [Deutsch](PLATFORM-AUDIT-REPORT.de.md) · [Français](PLATFORM-AUDIT-REPORT.fr.md) · [Español](PLATFORM-AUDIT-REPORT.es.md) · [Português](PLATFORM-AUDIT-REPORT.pt.md) · [हिन्दी](PLATFORM-AUDIT-REPORT.hi.md) · [العربية](PLATFORM-AUDIT-REPORT.ar.md) · [বাংলা](PLATFORM-AUDIT-REPORT.bn.md) · [Bahasa Indonesia](PLATFORM-AUDIT-REPORT.id.md) · [日本語](PLATFORM-AUDIT-REPORT.ja.md)


> **تاريخ المراجعة**: 2026-08-04
> **نطاق المراجعة**: الوظائف الـ 16 المخطط لها بالكامل، جودة الكود، الأمان، تناسق النماذج، الاختبارات
> **الفرع**: main

---

## أولًا: نظرة عامة

| الفئة | التقييم | التغيير |
|------|------|------|
| اكتمال الوظائف | **A (96/100)** | +18 نقطة نهاية، +10 نماذج، +7 خدمات |
| جودة الكود | **A (95/100)** | 0 أخطاء صياغة، 0 تراجع |
| الحماية الأمنية | **A (94/100)** | ProviderAuth HMAC-SHA256، PKCE، رسائل خاصة للأصدقاء فقط |
| إعداد النظام البيئي | **A- (92/100)** | FeatureFlag 4 مفاتيح، Webhook 7 أحداث، VIP 5 مستويات |
| اكتمال النشر | **B+ (89/100)** | ChatWebSocket :8791، مزامنة التوثيق |

---

## ثانيًا: البنود التي تم التحقق منها

### 2.1 فحص صيغة PHP
- جميع ملفات `.php` في admin/ وservice/: **0 خطأ**
- ملفات الإعداد (route.php, process.php): **0 خطأ**

### 2.2 مجموعة الاختبارات
- 132 اختبارًا / 251 تأكيدًا: **0 تراجع جديد**
- إخفاقات مسبقة (23 بندًا): ClickHouse غير مثبت (14)، تبعيات بيئة الكابتشا (2)، إعداد الوسائط (2)، خدمة الترجمة (3)، فحص الصحة (2)

### 2.3 المراجعة الأمنية

| البند | الحالة |
|----|------|
| التحقق من توقيع Provider HMAC-SHA256 | ✓ نافذة 5 دقائق للحماية من إعادة التشغيل |
| Twitter OAuth PKCE (S256) | ✓ تخزين code_verifier في Redis |
| حماية OAuth state من CSRF | ✓ تخزين Redis + قراءة لمرة واحدة وحذف |
| الرسائل الخاصة للأصدقاء فقط | ✓ تحقق FriendController |
| فلترة عنوان Webhook | ✓ filter_var(FILTER_VALIDATE_URL) |
| القائمة البيضاء لأحداث Webhook | ✓ 7 أحداث، فلترة array_intersect |
| مصادقة JWT (ChatWebSocket) | ✓ jwt()->verify() |
| الحماية من حقن SQL | ✓ Eloquent ORM، دون ربط أصلي |
| حد معدل API | ✓ OAuth 10 مرات/دقيقة، عام 60 مرة/دقيقة |
| تشفير Encryptable | ✓ تشفير/فك تشفير تلقائي لـ OAuth token / API key |

### 2.4 إصلاحات تناسق النماذج

| المشكلة | الإصلاح |
|------|------|
| 🔴 أسماء جداول نماذج service تحمل بادئة `game_` (تتعارض مع المعايير الحالية) | إزالة البادئة من جميع النماذج العشرة الجديدة |
| 🟡 `AchievementService` بترميز صلب لـ `game_user_session` | نسخة service تغيّرت إلى `user_session` |
| 🟡 `GameController` بترميز صلب لـ `game_game_category_rel` | نسخة service تغيّرت إلى `game_category_rel` |

---

## ثالثًا: قائمة تسليم الوظائف

### المرحلة 1 — طبقة ربط الألعاب

| الملف | الوصف |
|------|------|
| `provider/GameProvider.php` (admin+service) | الفئة الأساسية المجردة: bet/settle/refund/rollback/signRequest |
| `provider/SelfProvider.php` (admin+service) | الألعاب المطوّرة ذاتيًا: معاملة DB + SELECT FOR UPDATE |
| `provider/ThirdPartyProvider.php` (admin+service) | الطرف الثالث: Guzzle HTTP + HMAC-SHA256 |
| `provider/ProviderFactory.php` (admin+service) | المصنع: match(game.type) |
| `middleware/ProviderAuth.php` (service) | التحقق من توقيع HMAC-SHA256، نافذة 5 دقائق |
| `controller/ProviderController.php` (service) | 4 نقاط نهاية: balance/bet/settle/refund |
| `service/GameSessionService.php` (admin+service) | نبض Redis + كشف انتهاء المهلة 15 دقيقة |

### المرحلة 2 — طبقة دعم التشغيل

| الملف | الوصف |
|------|------|
| `model/Ticket.php` + `TicketReply.php` (admin+service) | التذاكر + الردود، 5 أنواع |
| `controller/TicketController.php` (service + admin) | 4 نقاط نهاية للطرف C + 5 نقاط نهاية للإدارة |
| `service/VerificationService.php` (admin+service) | رمز 6 أرقام، Redis 10 دقائق، تبريد 60 ثانية |
| `controller/VerificationController.php` (service) | 4 نقاط نهاية: sendEmail/confirmEmail/sendSms/confirmPhone |
| `service/PushService.php` (admin+service) | تجريد دفع FCM/APNs/هواوي |
| `model/DeviceToken.php` (admin+service) | تخزين رموز الأجهزة |

### المرحلة 3 — الاحتفاظ بالمستخدمين

| الملف | الوصف |
|------|------|
| `model/VipLevel.php` + `UserVip.php` + `ExpLog.php` | VIP من 5 مستويات، نظام الخبرة |
| `service/VipService.php` (admin+service) | addExp/ترقية تلقائية/استعلام الامتيازات |
| **تكامل ExchangeController** | quote() يطبق خصم VIP + مكافأة سعر الصرف |
| **تكامل WithdrawController** | apply() يطبق تخفيض رسوم VIP |
| **تكامل ReferralController** | apply() يضيف خبرة المُحيل |
| `model/Achievement.php` + `UserAchievement.php` | 12 إنجازًا مدمجًا |
| `service/AchievementService.php` (admin+service) | كشف موجه بالأحداث + تتبع التقدم |

### المرحلة 4 — الطبقة الاجتماعية

| الملف | الوصف |
|------|------|
| `model/Friend.php` (admin+service) | علاقات الصداقة: ربط ثنائي user/friendUser |
| `controller/FriendController.php` (service) | 7 نقاط نهاية: list/requests/request/accept/reject/remove/search |
| `model/Message.php` (admin+service) | نموذج الرسائل الخاصة |
| `controller/ChatController.php` (service) | 5 نقاط نهاية: conversations/messages/send/markRead/unreadTotal |
| `process/ChatWebSocket.php` (service) | WebSocket :8791، مصادقة JWT، دفع لحظي Redis Pub/Sub |

### المرحلة 5 — البنية التحتية

| الملف | الوصف |
|------|------|
| `event/EventBus.php` (admin+service) | ناقل أحداث Redis Pub/Sub |
| **تكامل emit في 5 وحدات تحكم** | Exchange/Withdraw/Game/Referral + Auth |
| `controller/WebhookController.php` (service) | 4 نقاط نهاية: list/register/delete/test |
| `AnalyticsController` إضافة 4 نقاط نهاية | retention/funnel/arpu/economy |
| `service/FeatureFlag.php` (admin+service) | مفاتيح ميزات DB، 4 مفاتيح مسبقة |

### إضافي — توسعة OAuth

| الملف | الوصف |
|------|------|
| **إعادة كتابة OAuthController** | 3→7 منصات: +X(Twitter)/Microsoft/LinkedIn/GitHub |
| Twitter PKCE | S256 code_challenge، تخزين code_verifier في Redis |
| التراجع عن بريد GitHub | واجهة /user/emails للبريد الأساسي الموثق |

---

## رابعًا: المشكلات المكتشفة والمُصلحة

| # | المشكلة | الخطورة | الإصلاح |
|---|------|--------|------|
| 1 | 🔴 أسماء جداول نماذج service تحمل بادئة `game_` بالكامل (10 نماذج) | عالية | إزالة جماعية عبر sed |
| 2 | 🟡 AchievementService في service بترميز صلب لـ `game_user_session` | متوسطة | تغيير إلى `user_session` |
| 3 | 🟡 GameController في service بترميز صلب لـ `game_game_category_rel` | متوسطة | تغيير إلى `game_category_rel` |
| 4 | 🟡 route.php شرطتان عكسيتان + عبارات echo متبقية | متوسطة | إصلاح |
| 5 | 🟢 نموذجا Friend/Message لم يُنشآ في البداية (SQL فقط) | منخفضة | أُنشئا |
| 6 | 🟢 منفذ LeaderboardWebSocket الفعلي 8790، chat-ws تغيّر إلى 8791 | منخفضة | تعديل المنافذ |

---

## خامسًا: البيانات الإحصائية

### حجم الكود

| المؤشر | العدد |
|------|------|
| ملفات PHP الجديدة | 51 |
| ملفات SQL الجديدة | 1 (165 سطرًا) |
| تعديل الملفات الموجودة | 7 (5 وحدات تحكم + 2 إعداد مسارات/عمليات) |
| النماذج الجديدة | 10 (admin+service = 20 ملفًا) |
| الخدمات الجديدة | 6 |
| وحدات التحكم الجديدة | 6 |
| نقاط نهاية API الجديدة | 50+ |
| جداول البيانات الجديدة | 10 |
| تحديثات التوثيق | 8 ملفات .md + رسمان بيانيان |

### جودة الكود

| المؤشر | القيمة |
|------|-----|
| أخطاء صياغة PHP | 0 |
| تراجع الاختبارات | 0 |
| تبعيات vendor جديدة | 0 |
| مخاطر حقن SQL | 0 |
| مفاتيح بترميز صلب | 0 |

---

## سادسًا: مساحة التوسعة البيئية (بنود غير مكتملة)

| الوظيفة | الأولوية | الوصف |
|------|--------|------|
| نظام البطولات/البطولات التنافسية | P2 | FeatureFlag حجز مفتاح `feature.tournament` مسبقًا |
| عمولة إحالة متعددة المستويات | P3 | الإحالة الحالية أحادية المستوى، يمكن توسيعها لتقسيم المستويين |
| قيود شروط القسائم | P3 | إضافة شروط الحد الأدنى للشحن/اللعبة المحددة/المستخدم الجديد |
| الدفع التلقائي (PayPal Payouts) | P3 | السحب حاليًا بمراجعة يدوية، يمكن الربط بالدفع التلقائي |
| صفحة إعداد VIP/الإنجازات في الإدارة | P3 | النماذج موجودة في الخلفي، صفحات Flutter قيد الإنشاء |
| تكامل عميق لدفع الجوال | P3 | هيكل PushService موجود، يحتاج ربط اعتمادات FCM/APNs |
| واجهة محادثة/أصدقاء Flutter | P3 | API + WebSocket جاهزان، الصفحات الأمامية قيد الإنشاء |
| توثيق SDK لجهة الألعاب | P3 | Provider API جاهز، توثيق الربط قيد الإكمال |

---

---

## ثامنًا: إصلاحات مساحة التوسعة (الجولة الثالثة 2026-08-04)

### P2 مُنفَّذ

**#1 نظام البطولات/البطولات التنافسية**
- نموذجا `Tournament` + `TournamentEntry` (admin+service)
- `TournamentController` (service): نقاط نهاية list/detail/join الثلاثة
- التحكم عبر مفتاح FeatureFlag `tournament`
- الدعم: فلترة نشط/يبدأ قريبًا/انتهى، حد أقصى لعدد المشاركين، لوحة متصدرين

### P3 مُنفَّذ

**#2 عمولة الإحالة متعددة المستويات**
- نموذج `Referral` يضيف `parent_id` لدعم الربط الثنائي
- نموذج `ReferralCommission` يسجل تفاصيل التقسيم (level/commission_rate/commission_amount)
- `ReferralController` يحسب عمولة المستويين تلقائيًا (قابلة للإعداد `level2_rate`)

**#3 قيود شروط القسائم**
- نموذج `Coupon` يضيف حقل `conditions` JSON
- دعم 3 شروط:
  - `min_deposit`: الحد الأدنى للشحن التراكمي
  - `first_user_only`: المستخدمون الجدد غير الشاحنين فقط
  - `game_id`: يجب أن يكون قد لعب اللعبة المحددة
- يتحقق كل من `CouponController.available()` و`claim()` من الشروط

**#4 توثيق SDK للمزود**
- `docs/PROVIDER-SDK.md` توثيق ربط كامل
- شرح مفصل لخوارزمية التوقيع + أكواد أمثلة PHP/Go/Python
- توثيق 4 نقاط نهاية API (balance/bet/settle/refund)
- دليل ربط الألعاب المطوّرة ذاتيًا + إدارة الجلسات + إعداد الألعاب

## تاسعًا: التقييم النهائي (محدث)

| الفئة | الأولي (v1) | توسعة v2.0 | إصلاحات v2.1 | التغيير |
|------|-----------|---------------|---------------|------|
| اكتمال الوظائف | 85 → | 96 → | **98** | +13 |
| جودة الكود | 92 → | 95 → | **95** | +3 |
| الحماية الأمنية | 94 → | 94 → | **94** | ثابت |
| إعداد النظام البيئي | 80 → | 92 → | **95** | +15 |
| اكتمال النشر | 72 → | 89 → | **90** | +18 |

**الإجمالي**: من A- (84.6) → A (93.2) → **A (94.4)**

---

## عاشرًا: تأكيد إصلاحات الأمان والتوافر 2026-08-18

إصلاحات الأمان والتوافر المكتملة في هذه الجولة (2026-08-18) (غير ملتزمة في مساحة العمل، تُصدر لاحقًا مع الإصدار 1.1):

| البند | محتوى الإصلاح | الحالة |
|----|---------|------|
| القائمة البيضاء لمزودي استدعاء الدفع | قبول stripe/paypal فقط، ورفض البقية بـ 403؛ رفض استدعاء يكون مزوّده مخالفًا لطريقة دفع الطلب (انتحال القنوات المتقاطعة) | ✅ تم الإصلاح |
| fail-closed لاستدعاء الدفع | Stripe: رفض عند عدم إعداد `STRIPE_WEBHOOK_SECRET` أو فشل التحقق؛ PayPal: رفض عند عدم إعداد `PAYPAL_WEBHOOK_ID` أو شذوذ التحقق؛ تجاوز فرق طابع التوقيع ±300 ثانية يُعتبر إعادة تشغيل ويُرفض | ✅ تم الإصلاح |
| مطابقة المبالغ | مطابقة دقيقة `bccomp(…, 4)` بين مبلغ الاستدعاء ومبلغ الطلب، رفض عند عدم التطابق | ✅ تم الإصلاح |
| معاملاتية الإيداع في الاستدعاء | تحديث الطلب + إيداع المحفظة في معاملة واحدة، تراجع عند فشل الإيداع | ✅ تم الإصلاح |
| التحقق من مفتاح JWT عند الإقلاع | رفض الإقلاع عند غياب `JWT_SECRET_KEY` أو بقائه القيمة الافتراضية `open-admin-jwt-secret-change-in-production`، بشكل متسق في admin/service | ✅ تم الإصلاح |
| مسارات خدمة التحليل | تسجيل 12 مسارًا من `/admin/analytics/*` في admin/config/route.php (جميع طرق AnalyticsController) | ✅ تم الإصلاح |
| بادئات الجداول | إزالة البادئة المرمّزة `game_` من 52 نموذجًا (إزالة البادئة المزدوجة `game_game_`)، تُوفَّر البادئة موحدًا من إعداد `prefix=game_` | ✅ تم الإصلاح |
| تخفيف حد المعدل | RateLimit يتبع fail-closed عند تعطل Redis (رفض بدلًا من المرور الصامت) | ✅ تم الإصلاح |
| refresh token | إعادة كتابة منطق تجديد الرمز في AuthController في service | ✅ تم الإصلاح |
| DepositLogService | نقل نسخة service وإكمالها، إزالة أحد انحرافات النسختين admin/service | ✅ تم الإصلاح |
| تنظيف الكود الميت | حذف نموذج Test؛ ترحيل تدقيق DepositLog إلى قاعدة البيانات | ✅ تم الإصلاح |
| Apple id_token | تحقق توقيع JWKS RS256 + تحديث kid + aud/iss/exp | ✅ تم الإصلاح |
| Webhook SSRF | `isSafeWebhookUrl()` HTTPS عام فقط، رفض العناوين الداخلية/المحجوزة | ✅ تم الإصلاح |
| 2FA | فك ترميز Base32 ثم HMAC؛ قفل لكل مستخدم `/api/2fa/verify` 5 مرات/15 دقيقة | ✅ تم الإصلاح |
| ذرية السحب | تحديث مشروط عند المراجعة/الدفع؛ مراجعة مزدوجة اختيارية؛ قفل مستخدم Redis عند الطلب | ✅ تم الإصلاح |
| مؤشرات أعمال Prometheus | `/metrics`: السحب قيد المراجعة، الشحنات المؤكدة اليوم (تخزين مؤقت 30 ثانية)، عدادات emit/consume للأحداث، memory_usage، version=1.1 | ✅ تم التنفيذ |
| التدرج الرمادي FeatureFlag | `inRollout` / `abTest` قراءة crc32 المجزأة لـ `feature.{name}_percent` | ✅ تم التنفيذ |

**لم يكتمل بعد**: ربط webman/queue، الربط الحقيقي لـ ClickHouse. تبقى التقييمات والاستنتاجات التاريخية كما هي. تم التنفيذ: عملية استهلاك ناقل الأحداث (`service/app/process/EventConsumer.php` + تسجيل `event-consumer` في process.php)، إزالة تكرار الطبقة المشتركة (الدمج في `packages/platform-common` واحد)، صفحات الطرف C في HarmonyOS، ربط محرك الإنجازات (الاستدعاء داخل EventConsumer)، بوابة CI لـ service.

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
