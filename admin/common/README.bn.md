# common/ — অ্যাডমিন শেয়ার্ড লাইব্রেরি
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · **বাংলা** · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

অ্যাডমিন ব্যাকএন্ডের (admin/) শেয়ার্ড কোড ডিরেক্টরি। `common\service\*` শেয়ার্ড প্যাকেজ **erik/platform-common** (`packages/platform-common`)-এ স্থানান্তরিত হয়েছে; এই ডিরেক্টরিতে PHP ক্লাস রাখবেন না, কারণ সেগুলি প্যাকেজের অটোলোড ছাপিয়ে যাবে। বিস্তারিত: `packages/platform-common/README.md`।

## ফিচার

| বিভাগ | অবস্থান | বিবরণ |
|------|------|------|
| মডেল | `app\model\*` | ডেটা মডেল (ব্যবহারকারী/অর্ডার/গেম ইত্যাদি) |
| সার্ভিস | `common\service\*` | শেয়ার্ড বিজনেস সার্ভিস (erik/platform-common প্যাকেজে): DepositLogService (ডিপোজিট অডিট + রাজস্ব/কনভার্সন), GameDashboardService (অপারেশন ড্যাশবোর্ড), ProbabilityService (প্রোবাবিলিটি অ্যানালাইসিস), GamePlayLogService (গেম অ্যাক্টিভিটি লগ লেখা) |
| মিডলওয়্যার | `app\middleware\*` | Cors / SecurityFilter / RateLimit / AdminAuth / AdminPermission / OperationLog |

## ইনস্টলেশন

admin প্রজেক্টের অংশ হিসেবে, ডিপেন্ডেন্সিগুলি `admin/composer.json`-এ আগেই ঘোষিত (path রিপোজিটরি `../packages/platform-common` সহ) এবং `composer install`-এর মাধ্যমে স্বয়ংক্রিয়ভাবে ইনস্টল হয়; আলাদা ইনস্টলেশনের প্রয়োজন নেই:

```bash
cd admin && composer install
```

## ব্যবহার

- `app\...` নেমস্পেস admin প্রজেক্টের নিজস্ব কোডের সাথে মেলে, যেমন: `use app\model\User;`
- `common\...` নেমস্পেস শেয়ার্ড প্যাকেজ erik/platform-common (PSR-4 → `src/`) এর সাথে মেলে, যেমন:

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```
