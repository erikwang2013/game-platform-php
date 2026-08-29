# erik/platform-common
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · **বাংলা** · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)


শেয়ার্ড `common\service\*`, admin/ ও service/ Composer path রিপোজিটরির মাধ্যমে রেফারেন্স করে।

## সার্ভিস

- DepositLogService — টপ-আপ অডিট + রাজস্ব/কনভার্সন
- GameDashboardService — অপারেশনাল ড্যাশবোর্ড
- ProbabilityService — প্রোবাবিলিটি অ্যানালাইসিস
- GamePlayLogService — গেম আচরণ লগ লেখা

হোস্টের `app\model\*`, `app\common\SnowflakeService`, `support\Db`, `support\Log` এর উপর নির্ভর করে।

## ইন্টিগ্রেশন

```bash
cd admin && composer update erik/platform-common
cd ../service && composer update erik/platform-common
```

## অবশিষ্ট ডুপ্লিকেট

app/model/*, app/common/*Service, অধিকাংশ app/service/*, EventBus এখনও দুই পাশে কপি করা আছে।

## প্রকল্প মাসকট

![প্রকল্প মাসকট: ডাইসি](../../docs/mascot.svg)

**ডাইসি (Dicey)** — প্ল্যাটফর্ম মাসকট। পাশা খেলা ও সম্ভাবনা-ভিত্তিক গেমপ্লে বোঝায়, মুদ্রা প্ল্যাটফর্ম অর্থনীতি ও মাল্টি-পেমেন্ট গেটওয়ে বোঝায়, বেগুনি রঙ অ্যাডমিন ব্র্যান্ডিংয়ের সাথে মেলে। SVG ফাইল: `docs/mascot.svg`, ডকুমেন্টেশন, লোগো ও পণ্যে অসীম স্কেলযোগ্য।
