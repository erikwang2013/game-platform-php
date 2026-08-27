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
