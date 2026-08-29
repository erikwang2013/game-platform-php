# وثيقة الوظائف
<!-- lang-nav -->

Languages: **中文** · [English](FEATURES.en.md) · [한국어](FEATURES.ko.md) · [Русский](FEATURES.ru.md) · [Deutsch](FEATURES.de.md) · [Français](FEATURES.fr.md) · [Español](FEATURES.es.md) · [Português](FEATURES.pt.md) · [हिन्दी](FEATURES.hi.md) · [العربية](FEATURES.ar.md) · [বাংলা](FEATURES.bn.md) · [Bahasa Indonesia](FEATURES.id.md) · [日本語](FEATURES.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. نظرة عامة على الوظائف

### الإصدار الأساسي (MVP) — مكتمل

| المجال | الوظيفة | الحالة |
|----|------|------|
| المستخدم | تسجيل/دخول/JWT/كابتشا | مكتمل |
| المحفظة | رصيد عملات المنصة/استعلام الحركات | مكتمل |
| الشحن | إنشاء طلب شحن (Stripe 125+ وسيلة دفع محلية، بما في ذلك Alipay/WeChat Pay APM / NOWPayments USDT TRC20·ERC20 / Coinbase USDC·BTC·ETH / PayPal) | مكتمل |
| الاستبدال | عملة المنصة ⇄ عملة اللعبة (سعر صرف ثابت + فرق) | مكتمل |
| السحب | طلب/استعلام/مفتاح عام/مراجعة تلقائية/مراجعة بشرية | مكتمل |
| الألعاب | CRUD خلفي/إدارة العملات/قائمة الطرف C/تفاصيل/تشغيل | مكتمل |
| الإدارة | إدارة الألعاب/مراجعة السحب/إدارة المستخدمين/إدارة الدفع/إدارة الإعلانات | مكتمل |
| لوحة التحكم | لوحة تحكم المنصة (DAU/حركات/إيراد/ترتيب) | مكتمل |
| التصدير | تصدير Excel للمستخدمين/الحركات/السحوبات | مكتمل |
| التدويل | تبديل الصينية/الإنجليزية، جدول الترجمات، وسيطة كشف اللغة | مكتمل |
| الواجهة الأمامية | Flutter PC لوحة إدارة + منصة مستخدمي الطرف C (تشمل i18n) | مكتمل |

### الإصدار القياسي — مكتمل

| المجال | الوظيفة | الحالة |
|----|------|------|
| المستخدم | تسجيل دخول OAuth (Google/Facebook/Apple/Twitter/Microsoft/LinkedIn/GitHub) | مكتمل |
| الدفع | استدعاءات تلقائية لقنوات دفع متعددة (Stripe بما في ذلك Alipay/WeChat Pay APM / PayPal / NOWPayments IPN / Coinbase Webhook) | مكتمل |
| الألعاب | إدارة الخوادم، تتبع سجلات اللعب | مكتمل |
| السحب | حدود KYC المتدرجة (default/verified/vip) + الرسوم | مكتمل |
| KYC | طلب التحقق من الهوية + المراجعة | مكتمل |
| إدارة المخاطر | قائمة IP السوداء/إنذار المبالغ الكبيرة/التكرار/كشف السرعة | مكتمل |
| الإحصائيات | لقطة الإحصائيات اليومية (المستخدمون/الشحن/السحب/الاستبدال/الألعاب) | مكتمل |
| الواجهة الأمامية | Admin: مراجعة KYC + سجلات المخاطر / Platform: OAuth + KYC + سجلات اللعب | مكتمل |

### الإصدار الكامل — مكتمل

| المجال | الوظيفة | الحالة |
|----|------|------|
| اللوبي | 10 تصنيفات مسبقة، فلترة التصنيفات، ربط الألعاب-التصنيفات | مكتمل |
| لوحات المتصدرين | يومية/أسبوعية/شهرية/إجمالية، تخزين مؤقت Redis، مؤشرات متعددة | مكتمل |
| القسائم | مبلغ ثابت + خصم نسبي، محدودة بالوقت والكمية، تتبع الاستلام/الاستخدام | مكتمل |
| إعدادات الدول | 8 دول مسبقة، طرق دفع/سحب مختلفة، حد أدنى للشحن | مكتمل |
| الإحصائيات | لقطة الإحصائيات اليومية + تتبع إيراد المنصة | مكتمل |
| البحث | بحث نصي كامل Elasticsearch (مدمج في طبقة النماذج) | مكتمل |

### الترقية بمستوى الإنتاج — مكتمل

| المجال | الوظيفة | الحالة |
|----|------|------|
| OAuth | تبادل Token حقيقي Google/Facebook/Apple | مكتمل |
| الدفع | التحقق من توقيع الاستدعاء (Webhook Stripe بما في ذلك Alipay/WeChat Pay APM، Webhook PayPal، NOWPayments IPN HMAC-SHA512، Coinbase HMAC-SHA256 base64) | مكتمل |
| الكابتشا | كابتشا نقرة poster-php | مكتمل |
| الإشعارات | رسائل داخل الموقع + البريد، إشعارات تلقائية للشحن/السحب/KYC/القسائم | مكتمل |
| 2FA | Google Authenticator TOTP + رموز استرداد احتياطية | مكتمل |
| الإحالة | رمز إحالة، مكافأة تسجيل، عمولة شحن | مكتمل |
| البحث | واجهات بحث ES + اقتراحات ألعاب + تراجع LIKE | مكتمل |
| لوحات المتصدرين | دفع لحظي WebSocket (المنفذ 8789) | مكتمل |
| CDN | تكامل خمسة مزودين (Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS رفع + مسح + تحميل مسبق) | مكتمل |
| إدارة CDN | إعداد المزودين الخمسة من لوحة الإدارة (تخزين مشفر للاعتمادات/تفعيل-إيقاف/اختبار الاتصال HeadBucket)، والخدمة تقرأ من قاعدة البيانات فقط | مكتمل |
| النشر | Docker Compose 7 خدمات + وكيل Nginx عكسي | مكتمل |
| البيانات | تحليل تجميع MySQL لحظي + حساب الاحتمالات المشتركة/الشرطية | مكتمل |
| HarmonyOS | إدارة 8 صفحات؛ الطرف C في `apps/harmonyos/` نفّذ تسجيل الدخول/اللوبي/التفاصيل/المحفظة/الملف الشخصي (يشير إلى 8788) | مكتمل جزئيًا (المشروع يعمل، الجهاز الحقيقي يحتاج تغيير IP) |
| توثيق API | توثيق تفاعلي hg/apidoc | مكتمل |
| تثبيت بنقرة واحدة | معالج تثبيت بالمتصفح: إنشاء مدير، ترقية قاعدة بيانات موجودة، install.lock يمنع إعادة التثبيت | مكتمل |
| تحمل الأعطال | CircuitBreaker + Retry + مفتاح التدهور feature.provider_mock | مكتمل |
| طرق الدفع | CRUD في الإدارة + رؤية حسب الدولة + نطاق المبالغ + تقييد العملة | مكتمل |
| CI | tag تلقائي متزايد عند push + GitHub Release | مكتمل |

### التوسعة البيئية (v2.0) — أُنجزت للتو

| المجال | الوظيفة | الحالة |
|----|------|------|
| ربط الألعاب | طبقة GameProvider التجريدية (Self/ThirdParty) + توقيع HMAC-SHA256 | مكتمل |
| استدعاءات الألعاب | بوابة واجهات Provider (balance/bet/settle/refund) + وسيطة ProviderAuth | مكتمل |
| جلسات الألعاب | نبض Redis + تسوية تلقائية عند انتهاء المهلة 15 دقيقة + GameSessionService | مكتمل |
| نظام التذاكر | إنشاء/رد من الطرف C + معالجة/توزيع/إغلاق من الإدارة، 5 أنواع تذاكر | مكتمل |
| التحقق من البريد | رمز 6 أرقام، انتهاء Redis 10 دقائق، حد إعادة إرسال 60 ثانية | مكتمل |
| إشعارات الدفع | PushService (FCM/APNs/دفع هواوي) + نموذج DeviceToken | مكتمل |
| نظام VIP | 5 مستويات (عادي/فضي/ذهبي/بلاتيني/ماسي) + خبرة + ترقية تلقائية | مكتمل |
| امتيازات VIP | خصم استبدال 2-15%، تخفيض رسوم سحب 10-100%، مكافأة سعر صرف 0.1-1.0% | مكتمل |
| نظام الإنجازات | 12 إنجازًا مدمجًا؛ EventConsumer → كشف موجه بالأحداث AchievementService وخبرة VIP | مكتمل |
| نظام الأصدقاء | طلب/قبول/رفض/حذف/بحث، حالات pending/accepted/blocked | مكتمل |
| الرسائل الخاصة/المحادثة | REST رسائل خاصة + WebSocket رسائل لحظية (المنفذ 8790)، الأصدقاء فقط | مكتمل |
| ناقل الأحداث | Redis Pub/Sub؛ emit + EventConsumer يستهلك الإنجازات/Webhook + عدادات metrics INCR | مكتمل |
| مفاتيح الميزات | FeatureFlag مبني على DB؛ `inRollout`/`abTest` قراءة crc32 المجزأة لـ `feature.{name}_percent` | مكتمل |
| التحليل المتقدم | الاحتفاظ/D1-D30، قمع التحويل، ARPU/ARPPU، مؤشرات اقتصاد عملات الألعاب (تجميع MySQL لحظي) | مكتمل |
| Webhook | إدارة الاشتراكات + تسليم الأحداث عبر Redis Pub/Sub، 7 أحداث قابلة للاختيار | مكتمل |
| المحادثة | REST رسائل خاصة + WebSocket رسائل لحظية (المنفذ 8791)، الأصدقاء فقط | مكتمل |
| البطولات | إنشاء/list/detail/join، مفتاح FeatureFlag، لوحة متصدرين، حد أقصى للاعبين | مكتمل |
| عمولة متعددة المستويات | تقسيم عمولة المستويين، نموذج ReferralCommission، معدلات عمولة قابلة للإعداد | مكتمل |
| شروط القسائم | ثلاثة قيود: min_deposit/first_user_only/game_id | مكتمل |
| توثيق SDK | توثيق ربط Provider (أمثلة PHP/Go/Python + 4 نقاط نهاية API) | مكتمل |
| لعبة مصغرة | Farm Match-3 P0 (محرك المجال + تصميم 4 مستويات، اختبارات وحدة TypeScript/Vite/Vitest) | مكتمل |

## 2. وظائف مستخدمي الطرف C

### 2.1 رحلة المستخدم

```
تسجيل → دخول → تحقق البريد/الهاتف → تصفح اللوبي → الدخول إلى تفاصيل اللعبة
                                           ↓
عرض المحفظة ← لعب الألعاب ← استبدال عملات اللعبة (خصم VIP) ← شحن عملات المنصة
    ↓
سحب (تخفيض رسوم VIP) → مراجعة الخلفي → وصول المبلغ
    ↓
نظام الأصدقاء → رسائل خاصة → منافسة لوحات المتصدرين → تتبع الإنجازات
    ↓
دعم التذاكر
```

### 2.2 واجهات API

| الطريقة | المسار | الوصف | المصادقة |
|------|------|------|------|
| POST | /api/auth/register | تسجيل المستخدم | لا |
| POST | /api/auth/login | دخول المستخدم | لا |
| POST | /api/auth/refresh | تجديد Token | لا |
| GET | /api/game/list | قائمة الألعاب | لا |
| GET | /api/game/detail/{id} | تفاصيل اللعبة | لا |
| GET | /api/announcement/list | قائمة الإعلانات | لا |
| GET | /api/wallet/info | رصيد المحفظة | نعم |
| GET | /api/wallet/transactions | سجلات الحركات | نعم |
| POST | /api/deposit/create | إنشاء طلب شحن | نعم |
| GET | /api/payment/methods | قائمة طرق الدفع (حسب الدولة) | نعم |
| POST | /api/exchange/quote | استعلام سعر الاستبدال (خصم VIP) | نعم |
| POST | /api/exchange/buy | شراء عملات اللعبة | نعم |
| POST | /api/exchange/sell | بيع عملات اللعبة | نعم |
| POST | /api/withdraw/apply | طلب السحب (تخفيض VIP) | نعم |
| POST | /api/game/launch | تشغيل اللعبة | نعم |
| GET | /api/game/play-logs | سجلات اللعب | نعم |
| POST | /api/referral/apply | استخدام رمز الإحالة | نعم |
| POST | /api/verify/send-email | إرسال رمز التحقق للبريد | نعم |
| POST | /api/verify/confirm-email | تأكيد البريد الإلكتروني | نعم |
| GET | /api/ticket/list | قائمة التذاكر | نعم |
| POST | /api/ticket/create | إنشاء تذكرة | نعم |
| POST | /api/ticket/{id}/reply | الرد على التذكرة | نعم |

## 3. وظائف لوحة الإدارة

### 3.1 واجهات API (جديدة)

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/dashboard/platform | بيانات لوحة تحكم المنصة |
| GET | /admin/analytics/overview | نظرة عامة على المنصة (تجميع MySQL لحظي) |
| GET | /admin/analytics/game-ranking | ترتيب الألعاب |
| GET | /admin/analytics/dau-trend | اتجاه DAU |
| GET | /admin/analytics/hourly-trend | الاتجاه بالساعة |
| GET | /admin/analytics/action-distribution | توزيع السلوكيات |
| GET | /admin/analytics/revenue | تحليل الإيرادات |
| GET | /admin/analytics/conversion | معدل تحويل الألعاب |
| GET | /admin/analytics/probability | الاحتمال المشترك/الشرطي |
| GET | /admin/analytics/retention | تحليل الاحتفاظ D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | قمع التحويل |
| GET | /admin/analytics/arpu | اتجاه ARPU/ARPPU |
| GET | /admin/analytics/economy | مؤشرات اقتصاد عملات الألعاب |
| GET | /admin/game/list | قائمة الألعاب |
| POST | /admin/game/create | إنشاء لعبة (تشمل provider_config) |
| PUT | /admin/game/{id} | تعديل اللعبة |
| GET | /admin/withdraw/orders | قائمة طلبات السحب |
| PUT | /admin/withdraw/review | مراجعة السحب |
| GET | /admin/ticket/list | قائمة التذاكر |
| GET | /admin/ticket/{id} | تفاصيل التذكرة |
| POST | /admin/ticket/{id}/reply | الرد على التذكرة |
| POST | /admin/ticket/{id}/close | إغلاق التذكرة |
| POST | /admin/ticket/{id}/assign | تعيين المعالج |

## 4. واجهات Provider (استدعاءات جهة اللعبة)

| الطريقة | المسار | الوصف | المصادقة |
|------|------|------|------|
| POST | /api/provider/balance | الاستعلام عن رصيد المستخدم | HMAC-SHA256 |
| POST | /api/provider/bet | إشعار المراهنة | HMAC-SHA256 |
| POST | /api/provider/settle | إشعار التسوية | HMAC-SHA256 |
| POST | /api/provider/refund | إشعار الاسترداد | HMAC-SHA256 |

خوارزمية التوقيع: `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)`
رؤوس الطلب: `X-Game-Id` + `X-Timestamp` + `X-Signature`
نافذة الوقت: 5 دقائق

## 5. نظام VIP

| المستوى | الخبرة التراكمية | خصم الاستبدال | تخفيض رسوم السحب | مكافأة سعر الصرف |
|------|---------|---------|-------------|---------|
| عادي | 0 | 0% | 0% | الأساس |
| فضي | 500 | 2% | 10% | +0.1% |
| ذهبي | 2,500 | 5% | 30% | +0.3% |
| بلاتيني | 12,500 | 10% | 50% | +0.5% |
| ماسي | 62,500 | 15% | 100% | +1.0% |

### اكتساب الخبرة

| السلوك | EXP |
|------|-----|
| شحن 1 وحدة | 10 |
| دخول يومي | 5 |
| إكمال KYC | 50 |
| دعوة مستخدم جديد | 100 |
| تحقيق إنجاز | 10-100 |

## 6. قائمة الإنجازات

| الإنجاز | الشرط | النقاط |
|------|------|------|
| First Deposit | أول شحن | 20 |
| Century Club | شحن تراكمي 100 | 50 |
| High Roller | شحن تراكمي 1000 | 100 |
| Trader | أول استبدال | 20 |
| Day Trader | 100 عملية استبدال تراكمية | 100 |
| Explorer | اللعب في 3 ألعاب | 30 |
| Adventurer | اللعب في 5 ألعاب | 50 |
| Conqueror | اللعب في 10 ألعاب | 100 |
| Weekly Warrior | دخول 7 أيام متتالية | 30 |
| Monthly Master | دخول 30 يومًا متتاليًا | 100 |
| Connector | دعوة صديق واحد | 30 |
| Influencer | دعوة 10 أصدقاء | 100 |

## 7. قائمة جداول قاعدة البيانات

### جداول التوسعة البيئية الجديدة (10 جداول)

| اسم الجدول | الوصف | الميزات الرئيسية |
|------|------|---------|
| game_ticket | التذاكر | فهرس user_id+type+status، assigned_to |
| game_ticket_reply | ردود التذاكر | فهرس ticket_id، تمييز is_admin |
| game_device_token | رموز الأجهزة | فهرس فريد user_id+platform+token |
| game_vip_level | تعريف مستويات VIP | فهرس فريد level، benefits JSON |
| game_user_vip | سجلات VIP للمستخدمين | فهرس فريد user_id، level+exp+total_exp |
| game_exp_log | سجلات الخبرة | فهرس مركب user_id+source |
| game_achievement | تعريفات الإنجازات | فهرس فريد key، condition_json JSON |
| game_user_achievement | إنجازات المستخدمين | فهرس فريد user_id+achievement_id |
| game_friend | علاقات الصداقة | فهرس فريد user_id+friend_id |
| game_message | الرسائل الخاصة | from_user_id+to_user_id / to_user_id+is_read |

### تغييرات بنية الجداول

| اسم الجدول | التغيير |
|------|------|
| game_game | +provider_config (JSON) |
| game_game_play_log | +round_id، +bet_amount، +win_amount |

**الإجمالي: 43 جدولًا في install.sql** (جداول التوسعة البيئية العشرة في `install/`، غير مدمجة في install.sql). النماذج غير مشتركة: نسخة لكل من admin 46 / service 44.

## 8. تغطية الاختبارات

| ملف الاختبار | عدد الحالات | نطاق التغطية |
|---------|--------|---------|
| PlatformTest | 56 | دقة bcmath/حسابات الاستبدال/رسوم السحب/الحدود/المخاطر/القسائم/KYC/i18n |
| BackendEnhancementTest | 23 | خدمة التشفير/Hashids/Snowflake |
| CaptchaTest | 7 | توليد/التحقق من الكابتشا |
| EncryptionServiceTest | 6 | تشفير AES/إخفاء البيانات |
| EnvConfigTest | 4 | إعدادات متغيرات البيئة |
| HashidsServiceTest | 8 | دورة ترميز وفك ترميز المعرّفات |
| SnowflakeServiceTest | 6 | تفرد توليد المعرّفات |

**الإجمالي: admin ~132 حالة / 8 ملفات؛ service 3 حالات (WebhookUrlSafety + EventBusMessageFormat). service غير مدمج في كسر CI عند الفشل.**

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
