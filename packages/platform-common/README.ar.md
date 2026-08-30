# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · **العربية** · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

الطبقة المشتركة `common\service\*`، المستخدمة من قبل admin/ وservice/، والتي تشير إلى الكود المصدري المحلي عبر مرجع مسار Composer.

## الخدمات

| الخدمة | الوصف |
|------|------|
| DepositLogService | تدقيق الشحن + الإيراد/التحويل |
| GameDashboardService | لوحة التشغيل |
| ProbabilityService | تحليل الاحتمالات |
| GamePlayLogService | كتابة سجلات سلوك الألعاب |
| CircuitBreaker / Retry | آليات الاستقرار (قاطع الدائرة/إعادة المحاولة) |

يعتمد على المضيف لتوفير `app\model\*` و`app\common\SnowflakeService` و`support\Db` و`support\Log`.

## التثبيت

اسم الحزمة `erik/platform-common`. قام كل من admin/ وservice/ بالفعل بتكوين مستودع المسار (`../packages/platform-common`) في composer.json، لذلك تُثبَّت تلقائيًا عبر `composer install`؛ كما يمكن تحديثها بشكل منفصل من admin/ أو service/:

```bash
composer update erik/platform-common
```

إذا نُشرت على Packagist، يمكن تثبيتها مباشرة أيضًا:

```bash
composer require erik/platform-common
```

## الاستخدام

مساحة الاسم `common\` (PSR-4 → `src/`):

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```

## التثبيت بنقرة واحدة

يُنجَز تلقائيًا عبر معالج تثبيت المنصة بنقرة واحدة (`install/`): ينفّذ المعالج `composer install` لكل من admin/ وservice/، وتُثبَّت تبعية مستودع المسار تلقائيًا؛ لا حاجة لإعداد يدوي.

## المتبقي مزدوجًا

`app/model/*` و`app/common/*Service` ومعظم `app/service/*` وEventBus ما زالت منسوخة على الجانبين.
