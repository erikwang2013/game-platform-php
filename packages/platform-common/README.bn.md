# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · **বাংলা** · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

admin/ ও service/ দ্বারা ব্যবহৃত শেয়ার্ড `common\service\*` লেয়ার, যা Composer path রিপোজিটরির মাধ্যমে লোকাল সোর্স রেফারেন্স করে।

## সার্ভিস

| সার্ভিস | বিবরণ |
|------|------|
| DepositLogService | টপ-আপ অডিট + রাজস্ব/কনভার্সন |
| GameDashboardService | অপারেশনাল ড্যাশবোর্ড |
| ProbabilityService | প্রোবাবিলিটি অ্যানালাইসিস |
| GamePlayLogService | গেম আচরণ লগ লেখা |
| CircuitBreaker / Retry | স্থিতিশীলতা প্রক্রিয়া (সার্কিট ব্রেকার/রিট্রাই) |

হোস্টের `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log` প্রদানের উপর নির্ভর করে।

## ইনস্টলেশন

প্যাকেজের নাম `erik/platform-common`। admin/ ও service/ উভয়ই composer.json-এ path রিপোজিটরি (`../packages/platform-common`) কনফিগার করেছে, তাই `composer install`-এর মাধ্যমে স্বয়ংক্রিয়ভাবে ইনস্টল হয়; admin/ বা service/ থেকে আলাদাভাবে আপডেট করাও সম্ভব:

```bash
composer update erik/platform-common
```

Packagist-এ প্রকাশিত হলে সরাসরি ইনস্টলও করা যায়:

```bash
composer require erik/platform-common
```

## ব্যবহার

নেমস্পেস `common\` (PSR-4 → `src/`):

```php
use common\service\GameDashboardService;

$dashboard = new GameDashboardService();
$overview = $dashboard->overview();
```

## ওয়ান-ক্লিক ইনস্টলেশন

প্ল্যাটফর্মের ওয়ান-ক্লিক ইনস্টলেশন উইজার্ড (`install/`) দিয়ে স্বয়ংক্রিয়ভাবে সম্পন্ন হয়: উইজার্ড admin/ ও service/-এর জন্য `composer install` চালায়, path রিপোজিটরি ডিপেন্ডেন্সি স্বয়ংক্রিয়ভাবে ইনস্টল হয়; ম্যানুয়াল কনফিগারেশনের প্রয়োজন নেই।

## অবশিষ্ট ডুপ্লিকেট

`app/model/*`, `app/common/*Service`, অধিকাংশ `app/service/*` এবং EventBus এখনও দুই পাশে কপি করা আছে।
