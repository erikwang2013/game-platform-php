# سجل التغييرات
<!-- lang-nav -->

Languages: **中文** · [English](CHANGELOG.en.md) · [한국어](CHANGELOG.ko.md) · [Русский](CHANGELOG.ru.md) · [Deutsch](CHANGELOG.de.md) · [Français](CHANGELOG.fr.md) · [Español](CHANGELOG.es.md) · [Português](CHANGELOG.pt.md) · [हिन्दी](CHANGELOG.hi.md) · [العربية](CHANGELOG.ar.md) · [বাংলা](CHANGELOG.bn.md) · [Bahasa Indonesia](CHANGELOG.id.md) · [日本語](CHANGELOG.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

سجل تغييرات قابل للقراءة البشرية. لا يستورده PHP. يقابل PROJECT-PLAN P2-21.

## [1.1] — 2026-08-07

- ربط إضافة Redis وخدمة التحليل وتدهور Redis وإصلاحات الاختبارات.

## [1.1] security / ops — 2026-08-18

### الأمان

- استدعاء الدفع: القائمة البيضاء للمزود (stripe/paypal)، تحقق fail-closed من التوقيع، مطابقة المبالغ، معاملاتية الإيداع، طابع Stripe الزمني ±300 ثانية لمنع إعادة التشغيل.
- JWT: رفض الإقلاع عند غياب `JWT_SECRET_KEY` / `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY` أو كونها قيمًا افتراضية.
- Apple id_token: التحقق من التوقيع JWKS (RS256) + aud/iss/exp.
- Webhook: عناوين HTTPS عامة فقط، رفض العناوين الداخلية/المحجوزة (SSRF).
- 2FA: يستخدم مفتاح TOTP HMAC المُفكَّك من ترميز RFC 4648 Base32؛ قفل لكل مستخدم عند فشل `/api/2fa/verify` (5 مرات / 15 دقيقة، fail-closed عند تعطل Redis).
- السحب: قلب الحالة ذري عبر UPDATE مشروط عند المراجعة/الدفع؛ مراجعة مزدوجة اختيارية (`withdraw.require_dual_review`)؛ قفل مستخدم Redis في جانب الطلب لمنع اختراق الحدود المتزامن.
- حد المعدل: fail-closed عند تعطل Redis.

### التوافر

- تركيب 12 مسارًا من `/admin/analytics/*` لخدمة تحليل admin.
- إزالة البادئة المرمّزة `game_` من النماذج؛ ترحيل تدقيق DepositLog إلى قاعدة البيانات؛ حذف نموذج Test.

### قابلية المراقبة

- إضافة إلى `GET /metrics`: السحب قيد المراجعة، الشحنات المؤكدة اليوم (COUNT مع تخزين مؤقت Redis 30 ثانية)، عدّادات emit/consume للأحداث، `memory_usage`، `info version=1.1`.
- FeatureFlag: `inRollout` / `abTest` يقرآن `feature.{name}_percent` بالتقسيم عبر crc32.
- EventBus: `emit` / `consume` تعملان INCR على `metrics:event_emit_total` / `metrics:event_consume_total` في Redis.

### العميل / الطبقة المشتركة (أُكمل في نفس اليوم)

- Flutter Platform: جدول مسارات `app_pages.dart`؛ إضافة صفحات إعداد/تحقق 2FA والقسائم ولوحات المتصدرين والإشعارات واستدعاء OAuth؛ دخول اللوبي مربوط بالتنقل.
- HarmonyOS الطرف C: خمس صفحات في `apps/harmonyos/` (تسجيل الدخول/اللوبي/التفاصيل/المحفظة/الملف الشخصي)، `BASE_URL` الافتراضي يشير إلى service `8788`.
- الطبقة المشتركة: `packages/platform-common` (حزمة path `erik/platform-common`) استخرجت DepositLog / GameDashboard / Probability / GamePlayLog؛ النماذج ما زالت نسختين.
- ClickHouse: إزالة تبعية composer؛ يستمر التحليل عبر تجميع MySQL لحظي.
- CI: تشغيل phpunit في admin / service كوظائف منفصلة، فشل أي منها يقطع.

### فجوات متبقية

- نماذج **admin / service** ما زالت نسختين (جزء فقط من `common/service` دخل حزمة path).
- `webman/queue` غير موصول؛ الاحتمالات/الاحتفاظ لم تُرحَّل إلى OLAP.
- بعض الفقرات في PROJECT-PLAN / VERSIONS / تقارير التدقيق قد تتأخر عن سجل التغييرات هذا؛ المرجع هو هذا الملف والقرص.

## [1.1] resilience — 2026-08-27

### الاستقرار

- إضافة `CircuitBreaker` (حالة في Redis، عتبة 5 / نافذة 30 ثانية، fail-open عند تعذر Redis) و`Retry` (تراجع أسي، استثناءات الشبكة فقط، 5 محاولات كحد أقصى) إلى الطبقة المشتركة، في `packages/platform-common/src/`.
- مفتاح التدهور `feature.provider_mock`: PushService (FCM/APNs/HarmonyOS) وPayoutService (PayPal) وThirdPartyProvider يختصرون المكالمات عند `on`، بدون طلبات شبكة حقيقية.
- إصلاح 11 عيب نوع في `getenv($name, '')` (TypeError مع strict_types)؛ نقل فحص mock في PushService إلى try/catch.
- اختبارات جديدة: CircuitBreakerTest / RetryTest / ResilienceMockTest؛ مجموعة service 45 ← 60 حالة، كلها ناجحة (تقرير: [test-reports/resilience.md](test-reports/resilience.md)).
