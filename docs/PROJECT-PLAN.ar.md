# التخطيط الشامل للمشروع (Project Plan)
<!-- lang-nav -->

Languages: **中文** · [English](PROJECT-PLAN.en.md) · [한국어](PROJECT-PLAN.ko.md) · [Русский](PROJECT-PLAN.ru.md) · [Deutsch](PROJECT-PLAN.de.md) · [Français](PROJECT-PLAN.fr.md) · [Español](PROJECT-PLAN.es.md) · [Português](PROJECT-PLAN.pt.md) · [हिन्दी](PROJECT-PLAN.hi.md) · [العربية](PROJECT-PLAN.ar.md) · [বাংলা](PROJECT-PLAN.bn.md) · [Bahasa Indonesia](PROJECT-PLAN.id.md) · [日本語](PROJECT-PLAN.ja.md)


> تاريخ التوليد: 2026-08-16 · استنادًا إلى جرد قراءة فقط لفريق من 6 أعضاء (researcher/architect/backend-dev/frontend-dev/tester/reviewer) + تحقق عملي من الاستنتاجات الرئيسية
> التغطية: ملخص الوضع الحالي / المشكلات والمخاطر / خارطة طريق P0-P1-P2 / إصلاحات التوثيق / بوابة الجودة

---

## أولًا: الوضع الحالي للمشروع

**منصة تجميع الألعاب العالمية** — PHP 8.3 + webman v2، monorepo بتطبيقين:
`admin/`(8787 لوحة الإدارة) + `service/`(8788 الطرف C) + `apps/`(Flutter + HarmonyOS) + `install/`(معالج التثبيت 43 جدولًا).

| البعد | الحجم الفعلي |
|------|---------|
| وحدات التحكم | admin 32 + service 30 = 62 |
| نقاط نهاية API | ~149 (admin 103 / service 88، تشمل استدعاءات Webhook/Provider) |
| نماذج البيانات | admin 46 / service 44، admin/service **مكررة النسخ** (بدون طبقة مشتركة) |
| الاختبارات | 132 حالة / 8 ملفات (مشروع admin)، مشروع service **صفر اختبارات** |
| الإصدار | v1.1 (2026-08-07): إضافة Redis، خدمة التحليل، تخفيف Redis، إصلاحات الاختبارات |

القدرات المنفذة: JWT+RBAC، القفل التفاؤلي للمحفظة، الشحن (تحقق Stripe/PayPal/NowPayments/Coinbase)، فرق الاستبدال، مراجعة السحب + دفع PayPal، CRUD الألعاب/بوابة Provider (HMAC)/القسائم/VIP/الإنجازات/التذاكر/عمولة الإحالة/2FA/الاجتماعي (أصدقاء/محادثة WS)/البطولات/Webhook/الدفع (FCM/APNs/هواوي)/i18n ثنائية اللغة.

---

## ثانيًا: المشكلات والمخاطر (تحقق عملي)

### حرجة — أمان الأموال

| # | المشكلة | الموقع |
|---|------|------|
| C1 | `provider` في استدعاء الدفع يأتي من العميل، وعندما لا يكون stripe/paypal **يُتخطى التحقق من التوقيع تمامًا**، فيمكن للإيداع المزوّر الدخول مباشرة | service/.../PaymentController.php:36-42 |
| C2 | تحقق fail-open: عدم إعداد `STRIPE_WEBHOOK_SECRET` → `return true`؛ أي استثناء PayPal → `return true`. سلسلة الهجوم: إنشاء طلب شحن ذاتيًا → تزوير استدعاء → شحن بلا حدود | PaymentController.php:167, 225 |
| C3 | `JWT_SECRET_KEY` يعود افتراضيًا إلى مفتاح مشفر صلب عام `open-admin-jwt-secret-change-in-production`، وعدم إعداد env في الإنتاج يسمح بتزوير Token المشرف | admin+service config/plugin/erikwang2013/jwt/jwt.php:12 |

### عالية — الصحة/التناسق

| # | المشكلة | الموقع |
|---|------|------|
| H1 | طرق AnalyticsController الـ 12 في خدمة التحليل منفذة بالكامل لكن **بدون أي مسارات**، كود ميت 404 بالكامل، بينما تعلن VERSIONS.md التسليم | admin/config/route.php (0 موضع analytics) |
| H2 | انقطاع ناقل الأحداث: emit له 4 مواضع استدعاء (game.played/withdraw.completed/exchange.completed/referral.applied)، و`subscribe()` دون أي عملية مسجلة، فالأحداث المنشورة تُفقد فورًا؛ محركات VIP/الإنجازات/الإشعارات كلها معلقة | admin+service app/event/EventBus.php |
| H3 | نسختان مكررتان من common/ وmodel/ وقد انحرفت كل منهما (نسختا DepositLogService بمحتوى مختلف، User.php غير متسق)، فالإصلاح أحادي النقطة يصبح عملًا مزدوجًا. **`common/service` سُحبت** إلى `packages/platform-common` (erik/platform-common، دُمج common-php الأصلي فيه)؛ model والغلاف app/common ما زالا نسختين | admin/common vs service/common → packages/platform-common |
| H4 | ~~دليل الطرف C في HarmonyOS `apps/harmonyos/` فارغ، 0 صفحات مقابل ادعاء VERSIONS.md بـ 5 صفحات~~ — تم التنفيذ (2026-08-18: 5 صفحات مُنفذة في `apps/harmonyos/`) | apps/harmonyos/ |
| H5 | استدعاء Stripe لا يتحقق من تحمل طابع `t=` الزمني (قابل لإعادة التشغيل)، ولا يطابق مبلغ الإيداع مع مبلغ الدفع الفعلي للبوابة | PaymentController.php:191-194 |
| H6 | Apple id_token يُفكك base64 للـ payload فقط، دون التحقق من التوقيع أو aud/iss/exp، خطر خلط الهوية عبر التطبيقات | OAuthController.php:376-380 |

### متوسطة — الموثوقية/عيوب التنفيذ

| # | المشكلة |
|---|------|
| M1 | عيب 2FA مزدوج: `/api/2fa/verify` عام دون قفل محاولات لكل مستخدم (oracle عنيف)؛ TOTP يستخدم سلسلة Base32 مباشرة كمفتاح HMAC (دون فك ترميز)، فلا يطابق Authenticator → **2FA غير قابل للاستخدام فعليًا** |
| M2 | مراجعة/دفع السحب هي check-then-act دون تحديث حالة ذري، فيمكن الدفع المكرر بالتزامن؛ لا توجد مراجعة مزدوجة |
| M3 | عنوان استدعاء Webhook يُتحقق منه بـ filter_var فقط، يمكن أن يشير إلى IP داخلي (SSRF)، ويُرسل POST إلى أي عنوان |
| M4 | الحد اليومي/الشهري للسحب "استعلام ثم إدراج" غير ذري، فيمكن اختراق الحد بالتزامن |
| M5 | تعطل Redis fail-open دون تجريد موحد: إبطال القائمة السوداء لـ JWT يفشل، وحد المعدل يفشل صامتًا؛ فجوات التخفيف: PayoutService::getAccessToken، ChatWebSocket brpop، تخزين/استرجاع حالة OAuth |
| M6 | ClickHouse بلا استخدام: حساب الاحتمالات هو في الواقع COUNT(DISTINCT)+JOIN استعلام فرعي لحظي في MySQL، خطر O(n²) على الجداول الكبيرة؛ تبعية composer بلا قدرة |
| M7 | قائمة نصف مكتملة: admin/app/queue فيه ComputeDailyStats + 3 مهام ES، لكن webman/queue غير مثبت وprocess.php بلا تسجيل، وكلها بلا مستدعين |
| M8 | كود ميت: خدمات Vip/Achievement/Notification/FeatureFlag بلا مستدعين؛ DepositLogService::log() تنفيذ فارغ؛ نموذج Test متبقٍ؛ حساب الاحتفاظ أحادي المجموعة تقريبي خشن |

### منخفضة
- السحب دون إلزام 2FA/KYC يمكن الدفع إلى أي بريد PayPal؛ ملاحظة المراجعة تدخل نص الإشعار (سطح XSS)
- التوثيق لا يطابق الواقع: install.sql 43 جدولًا مقابل ما كُتب سابقًا 52؛ docker-compose 7 خدمات مقابل ما كتبه FEATURES.md سابقًا 8؛ "Model مشترك 34" غير دقيق (admin 46 / service 44 نسخة لكل منهما، دون طبقة مشتركة). CHANGELOG أُكمل، انظر `docs/CHANGELOG.md`.

### البنود الناجحة (أكدت المراجعة الأمنية عدم وجود مشاكل)
القفل التفاؤلي للمحفظة + تحديث شروط الإصدار صحيح؛ القوة الذاتية للاستدعاء `where status=pending` تحديث شرطي صحيح؛ ORM كامل دون ربط SQL مباشر؛ .env خارج git؛ جميع مسارات admin معلقة بـ AdminAuth+RBAC رفض افتراضي؛ التحقق من حالة OAuth + استهلاك لمرة واحدة صحيح.

> **حالة الإصلاح 2026-08-18**: أُصلح C1/C2/C3/H1/H5/H6؛ ناقل الأحداث H2: سُجل `event-consumer` في `process.php` ووُجدت فئة الاستهلاك `EventConsumer`، وemit له مستهلكون؛ أُصلح M1 Base32 + القفل لكل مستخدم؛ أُنجزت ذرية حالة السحب M2 + مراجعة مزدوجة اختيارية؛ حُجب SSRF لـ Webhook M3؛ أُنفذ قفل مستخدم Redis عند طلب السحب M4؛ اكتمل M5 جزئيًا (fail-closed لـ RateLimit)؛ وُضعت مؤشرات الأعمال P2-19 + تدرج رمادي FeatureFlag. تبقى قائمة المشكلات كاستنتاج تدقيق تاريخي.

---

## ثالثًا: خارطة الطريق

### P0 — أمان الأموال + الصحة (أولًا، حاجز الإطلاق)

1. **fail-closed لاستدعاء الدفع**: القائمة البيضاء للمزود (stripe/paypal/nowpayments/coinbase فقط) + رفض 500 إلزامي عند غياب المفتاح + رفض إلزامي لأي استثناء PayPal (C1/C2) — ✅ مكتمل (2026-08-18: القائمة البيضاء للمزود + التحقق من انتحال القنوات المتقاطعة + تحقق اختياري من IP المصدر + معاملاتية الإيداع في الاستدعاء)
2. **التحقق من الإقلاع JWT**: رفض الإقلاع عند غياب `JWT_SECRET_KEY` في env (C3) — ✅ مكتمل (2026-08-18: رفض الإقلاع عند غياب `JWT_SECRET_KEY` أو بقائه القيمة الافتراضية `open-admin-jwt-secret-change-in-production`، بشكل متسق في admin/service)
3. **تركيب مسارات خدمة التحليل**: تسجيل 12 مسارًا من analytics + نقاط الصلاحية، إصلاح وعد VERSIONS.md (H1) — ✅ مكتمل (2026-08-18: تسجيل 12 مسارًا من `/admin/analytics/*` في admin/config/route.php)
4. **ربط ناقل الأحداث**: تسجيل عملية اشتراك مقيمة للاستهلاك، أو تغييره إلى استدعاء مباشر متزامن؛ ترحيل الأحداث إلى قاعدة البيانات + إعادة محاولة عند الفشل (H2) — ✅ مكتمل (2026-08-18: emit/consume تعملان INCR لعدادات Redis؛ تسجيل `event-consumer` في `service/config/process.php`، و`service/app/process/EventConsumer.php` يستهلك الأحداث)
5. **التحقق من توقيع Apple id_token**: تحقق JWKS + aud/iss/exp (H6) — ✅ مكتمل (2026-08-18: RS256 JWKS + تحديث kid + aud/iss/exp)
6. **منع إعادة تشغيل Stripe ومطابقة المبالغ**: تحمل الطابع الزمني + المطابقة مع مبلغ البوابة (H5) — ✅ مكتمل (2026-08-18: طابع `t=` ±300 ثانية لمنع إعادة التشغيل + مطابقة المبالغ بدقة bccomp + رفض إلزامي عند غياب secret/webhook_id أو شذوذ التحقق)

### P1 — الموثوقية + التناسق

7. **إزالة تكرار الطبقة المشتركة**: سحب common/model إلى composer path repo (أو رابط رمزي)، إزالة انحراف النسختين (H3) — 🔶 مكتمل جزئيًا (2026-08-18: سُحب `common/service` إلى `packages/platform-common` واحد / path repo `erik/platform-common` (دُمج `common-php` الأصلي فيه)، ويشير إليه admin+service؛ model والغلاف `app/common` المرتبط بالمضيف ما زالا نسختين، انظر `packages/platform-common/DUAL_MODELS.md`)
8. **غلاف موحد لتخفيض Redis**: إظهار استراتيجية الفشل صراحةً + إنذار دون صمت؛ تكميل الحلول الاحتياطية لـ PayoutService/OAuth/ChatWebSocket (M5) — 🔶 مكتمل جزئيًا (وُضع fail-closed لـ RateLimit: عند تعطل Redis يرفض حد المعدل بدلًا من المرور الصامت؛ الباقي لم يُنفَّذ)
9. **ربط webman/queue**: تحميل الأحداث وتسليم webhook (إعادة محاولة الاستهلاك، رسائل ميتة)، تفعيل أو حذف مهام ComputeDailyStats/ES (M7) — ⬜ لم يُنفَّذ
10. **إصلاح 2FA**: فك ترميز Base32 + إضافة حالة الدخول إلى verify + قفل المحاولات لكل مستخدم (M1) — ✅ مكتمل (2026-08-18: فك ترميز RFC 4648 Base32 ثم HMAC؛ قفل `/api/2fa/verify` 5 فشلات لمدة 15 دقيقة، fail-closed عند تعطل Redis)
11. **ذرية السحب**: تحديث شرطي للمراجعة/الدفع + مراجعة مزدوجة؛ حد Redis Lua/قيد فريد (M2/M4) — 🔶 مكتمل جزئيًا (2026-08-18: تحديث شرطي pending→approved/rejected وapproved→processing؛ مراجعة مزدوجة اختيارية `withdraw.require_dual_review`؛ قفل مستخدم Redis في جانب الطلب. دون حد Lua/قيد فريد)
12. **حجب SSRF لـ Webhook**: رفض العناوين الداخلية/المحجوزة (M3) — ✅ مكتمل (2026-08-18: `isSafeWebhookUrl()` https عام فقط)
13. **اختيار ClickHouse**: الربط الحقيقي أو إزالة التبعية + تنقيح التوثيق (M6) — ⬜ لم يُنفَّذ
14. **تنظيف الكود الميت**: ربط أو حذف Vip/Achievement/Notification/FeatureFlag؛ حذف نموذج Test؛ ترحيل تدقيق DepositLog إلى قاعدة البيانات (M8) — 🔶 مكتمل جزئيًا (2026-08-18: حُذف نموذج Test، ووُضع تدقيق DepositLog في قاعدة البيانات؛ Vip/FeatureFlag/Notification لها مستدعون؛ AchievementService يُستدعى من EventConsumer)
15. **اختبارات service + بوابة CI**: اختبارات تكامل للتحقق من توقيع الاستدعاء/تدفق السحب/تخفيض Redis/حساب الاحتمالات/التزامن بالقفل التفاؤلي؛ منع الإخفاق في phpunit؛ دمج service في CI (حاليًا `|| echo warning` يسمح بالفشل) — 🔶 مكتمل جزئيًا (لدى service WebhookUrlSafety / EventBusMessageFormat؛ مُدمج في وظيفة `phpunit-service` في CI تمنع الإخفاق)

**إضافي مُكتمل هذه الجولة (2026-08-18) (خارج الترقيم الأصلي)**:
- **إصلاح بادئات الجداول**: إزالة البادئة المرمّزة `game_` من 52 نموذجًا، إزالة البادئة المزدوجة `game_game_`؛ البادئة موحدة من `prefix=game_` في config/database.php، ولا حاجة لتغيير install.sql
- **إعادة كتابة refresh token**: إعادة كتابة منطق تجديد الرمز في AuthController في service
- **نقل نسخة DepositLogService في service**: استكمال service/common/service/DepositLogService.php (إزالة أحد انحرافات النسختين admin/service)

### P2 — قابلية المراقبة / التوسع / التجربة

16. **الطرف C في HarmonyOS** تنفيذ 5 صفحات من الصفر (الدخول/اللوبي/التفاصيل/المحفظة/الملف الشخصي) (H4) — ✅ مكتمل (2026-08-18: 5 صفحات في `apps/harmonyos/entry/src/main/ets/pages/`)
17. **تكميل الواجهة الأمامية**: صفحة تحقق 2FA، مداخل القسائم/لوحات المتصدرين/الإشعارات، واجهة بحث ES؛ دمج مصدر مسارات main.dart/app_pages.dart؛ استدعاء OAuth حقيقي؛ طبقة نقل AES في الواجهة الأمامية
18. **نقل حساب الاحتمالات إلى ClickHouse** أو جدول إحصائيات مادي في MySQL + تخزين مؤقت؛ إعادة حساب الاحتفاظ حسب المجموعات الحقيقية
19. **مؤشرات أعمال Prometheus** (معدلات تسليم/استهلاك الأحداث، عمق القائمة) + وسيطة تقسيم AB بالتدرج الرمادي (إعادة استخدام FeatureFlag) — 🔶 مكتمل جزئيًا (2026-08-18: `GET /metrics` السحب قيد المراجعة/الشحنات المؤكدة اليوم/عدادات emit·consume للأحداث؛ تقسيم crc32 لـ `inRollout`/`abTest` في FeatureFlag. عمق القائمة لم يُنفَّذ)
20. **إغلاق حلقة بيانات WebSocket**: تأكيد استمرارية لوحات المتصدرين/المحادثة
21. **محاذاة التوثيق**: تصحيح عدد الجداول/الخدمات/وصف الطبقة المشتركة، محاذاة توثيق API مع التنفيذ، إضافة CHANGELOG — ✅ مكتمل (2026-08-18: انظر `docs/CHANGELOG.md` وFEATURES/VERSIONS/PROJECT-PLAN/§10 في تقرير التدقيق)

---

## رابعًا: بوابة الجودة (تعاون الفريق)

- كل تغيير كود: يجب اجتياز اختبارات admin الكاملة `vendor/bin/phpunit` (إزالة `|| echo warning`)
- المسارات الحساسة الجديدة (الدفع/السحب/المصادقة) يجب أن تصحبها اختبارات
- عند تعديل common/model تُزامن جانبا admin+service (قبل تنفيذ الطبقة المشتركة)
- تركيز اقتراحات تقرير المراجعة: توقيع ProviderAuth، تشفير AES، SQL اليدوي في ProbabilityService

## خامسًا: الفريق

فريق game-platform (6 أعضاء: researcher/architect/backend-dev/frontend-dev/tester/reviewer) جاهز، ويمكنه تنفيذ P0 مباشرة.
