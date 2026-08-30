# service/ — خدمة API لمنصة المستخدمين (الجانب C)
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · **العربية** · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

خدمة API لمنصة المستخدمين (الجانب C) هي خلفية PHP عالية الأداء مبنية على webman v2 (Workerman)، وتوفّر للمستخدمين كامل إمكانيات منصة تجميع الألعاب: التسجيل وتسجيل الدخول، المحفظة، الإيداع، السحب، التحويل، الألعاب، لوحات الترتيب، القسائم، تذاكر الدعم، VIP، الإنجازات، الوظائف الاجتماعية والإعلانات.

## قائمة الوظائف

| الوحدة | الوصف |
|------|------|
| المستخدمون | التسجيل/تسجيل الدخول (اسم المستخدم + كلمة المرور + OAuth لـ 7 منصات + 2FA TOTP)، الملف الشخصي |
| المحفظة | محفظة عملات المنصة (قفل متفائل) + محفظة عملات اللعبة + سجل المعاملات |
| الإيداع | 13 بوابة دفع (Stripe/PayPal/NowPayments/Coinbase وغيرها) مع التحقق من توقيع الاستدعاءات والإيداع التلقائي |
| السحب | طلب ← مراجعة ← دفع، حدود KYC متدرجة |
| التحويل | عروض أسعار فورية عملات المنصة ⇄ عملات اللعبة، خصومات VIP ومكافآت سعر صرف |
| الألعاب | قائمة/تصنيفات/بحث الألعاب، سجل اللعب، استدعاءات تسوية Provider |
| لوحات الترتيب | يومي/أسبوعي/شهري/إجمالي + دفع فوري عبر WebSocket |
| القسائم | مبلغ ثابت + خصم نسبي، محدودة بالوقت والكمية |
| التذاكر | إنشاء/الرد على تذاكر الدعم |
| VIP | 5 مستويات ولاء، تراكم خبرة، خصومات تحويل |
| الإنجازات | 12 إنجازًا مدمجًا، اكتشاف مدفوع بالأحداث |
| الاجتماعي | نظام الأصدقاء + رسائل WebSocket الفورية |
| الإعلانات | إعلانات داخل التطبيق + إشعارات/بريد |

## التقنيات

- PHP 8.3+ / webman v2 (workerman/webman)
- MySQL 8.0+ (بادئة الجداول `game_`، مفاتيح أساسية BIGINT بدون زيادة تلقائية)
- Redis (الجلسات / التخزين المؤقت / تحديد المعدل)
- ClickHouse (تحليلات OLAP / حساب الاحتمالات)
- Elasticsearch (البحث النصي الكامل)
- مصادقة JWT + توقيع HMAC-SHA256 لـ Provider

## هيكل المشروع

```
service/
├── app/
│   ├── api/v1/controller/  # وحدات تحكم API للجانب C (35)
│   ├── middleware/         # Middleware (Cors/SecurityFilter/RateLimit/ApiVersion/UserAuth/ProviderAuth)
│   ├── model/              # نماذج البيانات
│   ├── service/            # الخدمات التجارية (VIP/لوحات الترتيب/المخاطر/الإشعارات وغيرها)
│   ├── event/              # ناقل الأحداث (EventBus Redis Pub/Sub)
│   ├── provider/           # طبقة Provider للألعاب
│   └── payment/            # بوابات الدفع
├── common/                 # الخدمات المشتركة (منفّذة في حزمة erik/platform-common)
├── config/                 # ملفات الإعداد
├── public/                 # مدخل الويب
├── tests/                  # اختبارات PHPUnit
├── start.php               # نقطة البدء
└── composer.json
```

## التثبيت بنقرة واحدة

يُوصى باستخدام معالج التثبيت في جذر المشروع (نفّذ من جذر المشروع):

```bash
# 1. شغّل معالج التثبيت
php -S 0.0.0.0:8888 -t install/

# 2. افتح http://localhost:8888 في المتصفح
#    اتبع المعالج: فحص البيئة ← إعداد قاعدة البيانات ← إنشاء حساب المدير ← تثبيت تلقائي
```

أو شغّل كل شيء عبر Docker Compose (جذر المشروع):

```bash
docker compose up -d
```

## التثبيت اليدوي

```bash
# 1. ثبّت الاعتماديات
cd service && composer install

# 2. اضبط متغيرات البيئة
cp .env.example .env
# عدّل .env: اتصال قاعدة البيانات، مفاتيح JWT وغيرها

# 3. شغّل الخدمة (المنفذ الافتراضي 8788)
php start.php start        # في المقدمة
php start.php start -d     # في الخلفية (daemon)
```

## الاستخدام

- مرجع API: `docs/API.md` (مرجع كامل)
- التوثيق عبر الإنترنت: http://localhost:8788/apidoc/ (توثيق hg/apidoc التفاعلي)
- فحص الصحة: `GET http://localhost:8788/health`
- واجهة الجانب C: `apps/flutter/platform/` (منصة المستخدم Flutter Web)
- لوحة الإدارة: `admin/` (الخلفية وواجهة `admin/apps/flutter/`)

## الاختبارات

```bash
cd service
SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
```
