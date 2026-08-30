# common/ — المكتبة المشتركة للوحة الإدارة
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · **العربية** · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

دليل الكود المشترك للوحة الإدارة (admin/). تم نقل `common\service\*` إلى الحزمة المشتركة **erik/platform-common** (`packages/platform-common`)؛ لا تضع فئات PHP في هذا الدليل، فهي ستظلل التحميل التلقائي للحزمة. التفاصيل: `packages/platform-common/README.md`.

## الوظائف

| الفئة | الموقع | الوصف |
|------|------|------|
| النماذج | `app\model\*` | نماذج البيانات (المستخدمون/الطلبات/الألعاب وغيرها) |
| الخدمات | `common\service\*` | الخدمات التجارية المشتركة (داخل حزمة erik/platform-common): DepositLogService (تدقيق الإيداعات + الإيرادات/التحويل)، GameDashboardService (لوحة العمليات)، ProbabilityService (تحليل الاحتمالات)، GamePlayLogService (كتابة سجلات نشاط اللعب) |
| Middleware | `app\middleware\*` | Cors / SecurityFilter / RateLimit / AdminAuth / AdminPermission / OperationLog |

## التثبيت

كجزء من مشروع admin، الاعتماديات معلنة بالفعل في `admin/composer.json` (بما في ذلك مستودع المسار `../packages/platform-common`) وتُثبَّت تلقائيًا عبر `composer install`؛ لا حاجة لتثبيت منفصل:

```bash
cd admin && composer install
```

## الاستخدام

- مساحة الاسم `app\...` تتوافق مع كود مشروع admin نفسه، مثل: `use app\model\User;`
- مساحة الاسم `common\...` تتوافق مع الحزمة المشتركة erik/platform-common (PSR-4 → `src/`)، مثل:

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```
