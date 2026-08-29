# erik/platform-common

## تميمة المشروع

<img src="../../docs/mascot.svg" width="120" alt="Dicey"/>

**ديسي (Dicey)** — تميمة المنصة. النرد يرمز إلى الألعاب وأسلوب اللعب الاحتمالي، والعملة ترمز إلى اقتصاد المنصة وبوابات الدفع المتعددة، واللون البنفسجي يعكس هوية لوحة الإدارة. ملف SVG: `docs/mascot.svg`، قابل للتحجيم بلا حدود للوثائق والشعارات والمنتجات.
<!-- lang-nav -->

Languages: **中文** · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

يشارك `common\service\*` بين admin/ وservice/ عبر مرجع مسار Composer.

## الخدمات

- DepositLogService — تدقيق الشحن + الإيراد/التحويل
- GameDashboardService — لوحة التشغيل
- ProbabilityService — تحليل الاحتمالات
- GamePlayLogService — كتابة سجلات سلوك الألعاب

يعتمد على المضيف لتوفير `app\model\*` و`app\common\SnowflakeService` و`support\Db` و`support\Log`.

## الربط

```bash
cd admin && composer update erik/platform-common
cd ../service && composer update erik/platform-common
```

## المتبقي مزدوجًا

app/model/* وapp/common/*Service ومعظم app/service/* وEventBus ما زالت منسوخة على الجانبين.

