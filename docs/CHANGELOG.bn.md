# Changelog
<!-- lang-nav -->

Languages: [中文](CHANGELOG.md) · [English](CHANGELOG.en.md) · [한국어](CHANGELOG.ko.md) · [Русский](CHANGELOG.ru.md) · [Deutsch](CHANGELOG.de.md) · [Français](CHANGELOG.fr.md) · [Español](CHANGELOG.es.md) · [Português](CHANGELOG.pt.md) · [हिन्दी](CHANGELOG.hi.md) · [العربية](CHANGELOG.ar.md) · **বাংলা** · [Bahasa Indonesia](CHANGELOG.id.md) · [日本語](CHANGELOG.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

মানব-পাঠযোগ্য পরিবর্তন রেকর্ড। PHP এই ফাইলটি ইমপোর্ট করে না। PROJECT-PLAN P2-21-এর সাথে সম্পর্কিত।

## [1.1] — 2026-08-07

- Redis প্লাগইন ইন্টিগ্রেশন, অ্যানালিটিক্স সার্ভিস, Redis ডিগ্রেডেশন, টেস্ট ফিক্স।

## [1.1] security / ops — 2026-08-18

### নিরাপত্তা

- পেমেন্ট কলব্যাক: provider হোয়াইটলিস্ট (stripe/paypal), fail-closed ভেরিফিকেশন, পরিমাণ মিলানো, লেনদেন-ভিত্তিক এন্ট্রি, Stripe টাইমস্ট্যাম্প ±৩০০s দিয়ে রিপ্লে প্রতিরোধ।
- JWT: `JWT_SECRET_KEY` / `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY` অনুপস্থিত বা ডিফল্ট মান হলে স্টার্ট প্রত্যাখ্যান।
- Apple id_token: JWKS (RS256) ভেরিফিকেশন + aud/iss/exp।
- Webhook: শুধুমাত্র https পাবলিক URL; ইন্ট্রানেট/রিজার্ভড অ্যাড্রেস প্রত্যাখ্যান (SSRF)।
- 2FA: TOTP HMAC-এ RFC 4648 Base32 ডিকোড করা সিক্রেট ব্যবহৃত হয়; `/api/2fa/verify` প্রতি-ব্যবহারকারী ফেল লক (৫ বার / ১৫ মিনিট, Redis ব্যর্থ হলে fail-closed)।
- উত্তোলন: রিভিউ/পেমেন্ট শর্ত UPDATE স্টেট অ্যাটমিক ফ্লিপ; ঐচ্ছিক দ্বৈত পর্যালোচনা (`withdraw.require_dual_review`); আবেদনের পাশে Redis ইউজার লক দিয়ে সীমা কনকারেন্সি ভাঙা প্রতিরোধ।
- রেট লিমিট: Redis ব্যর্থ হলে fail-closed।

### প্রাপ্যতা

- admin অ্যানালিটিক্স সার্ভিসের ১২টি `/admin/analytics/*` রুট মাউন্ট।
- মডেল থেকে হার্ডকোডেড `game_` প্রিফিক্স বাদ; DepositLog অডিট ডেটাবেসে লেখা; Test মডেল মুছে ফেলা।

### পর্যবেক্ষণযোগ্যতা

- `GET /metrics`-এ যোগ হয়েছে: অপেক্ষমাণ রিভিউ উত্তোলন, আজকের নিশ্চিত টপ-আপ (COUNT কুয়েরি Redis ৩০s ক্যাশ), ইভেন্ট emit/consume কাউন্ট, `memory_usage`, `info version=1.1`।
- FeatureFlag: `inRollout` / `abTest` crc32 দিয়ে বাকেট করে `feature.{name}_percent` পড়ে।
- EventBus `emit` / `consume` Redis-এর `metrics:event_emit_total` / `metrics:event_consume_total`-এ INCR করে।

### ক্লায়েন্ট / শেয়ার্ড (একই দিনে পূরণ)

- Flutter Platform: `app_pages.dart` রুট টেবিল; 2FA সেটআপ/ভেরিফিকেশন, কুপন, লিডারবোর্ড, নোটিফিকেশন, OAuth কলব্যাক পেজ যোগ; লবি এন্ট্রিতে নেভিগেশন মাউন্ট করা হয়েছে।
- HarmonyOS C-এন্ড: `apps/harmonyos/` পাঁচটি পেজ (লগইন/লবি/ডিটেইল/ওয়ালেট/প্রোফাইল), ডিফল্ট `BASE_URL` service `8788`-এ নির্দেশিত।
- শেয়ার্ড লেয়ার: `packages/platform-common` (`erik/platform-common` path repo) থেকে DepositLog / GameDashboard / Probability / GamePlayLog নিষ্কাশিত; মডেল এখনও দুটি কপি।
- ClickHouse: composer নির্ভরতা অপসারিত; অ্যানালিটিক্স MySQL রিয়েল-টাইম অ্যাগ্রিগেশনের মাধ্যমে চলতে থাকে।
- CI: admin / service আলাদা job-এ phpunit চালায়, ব্যর্থ হলে ব্লক।

### এখনও বিদ্যমান ঘাটতি

- admin/service **মডেল** এখনও দুটি কপি (শুধুমাত্র আংশিক `common/service` path প্যাকেজে)।
- `webman/queue` সংযুক্ত নয়; প্রোবাবিলিটি/রিটেনশন OLAP-এ স্থানান্তরিত হয়নি।
- PROJECT-PLAN / VERSIONS / অডিট রিপোর্টের কিছু অনুচ্ছেদ এখনও এই CHANGELOG-এর চেয়ে পিছিয়ে থাকতে পারে; এই ফাইল ও ডিস্কই সঠিক উৎস।

## [1.1] resilience — 2026-08-27

### স্থিতিশীলতা

- শেয়ার্ড লেয়ারে `CircuitBreaker` (Redis-এ অবস্থা, থ্রেশহোল্ড 5 / উইন্ডো 30s, Redis অনুপলব্ধ হলে fail-open) এবং `Retry` (সূচকীয় ব্যাকঅফ, শুধু নেটওয়ার্ক ব্যতিক্রম, সর্বোচ্চ 5 চেষ্টা) যোগ হয়েছে, `packages/platform-common/src/`-এ।
- ডিগ্রেডেশন সুইচ `feature.provider_mock`: PushService (FCM/APNs/HarmonyOS), PayoutService (PayPal), ThirdPartyProvider `on` হলে শর্ট-সার্কিট করে, প্রকৃত নেটওয়ার্ক কল এড়িয়ে।
- `getenv($name, '')`-এর 11টি টাইপ ত্রুটি সংশোধন (strict_types-এ TypeError); PushService-এর mock পরীক্ষা try/catch-এ সরানো হয়েছে।
- নতুন পরীক্ষা: CircuitBreakerTest / RetryTest / ResilienceMockTest; service স্যুট 45 → 60 কেস, সব পাস (রিপোর্ট: [test-reports/resilience.md](test-reports/resilience.md))।

## [1.1] payments — 2026-08-29

- মাল্টি-পেমেন্ট গেটওয়ে: Stripe Checkout / NOWPayments (USDT TRC20+ERC20) / Coinbase Commerce (USDC) + Alipay/WeChat Pay (Stripe Checkout APM)।
- অ্যাডমিনে পেমেন্ট পদ্ধতি CRUD + দেশভিত্তিক দৃশ্যমানতা + পরিমাণ রেঞ্জ; টপ-আপ অর্ডার তৈরি হওয়ামাত্র checkout_url / expires_at ভরাট।
- নতুন মাইগ্রেশন install/migrations/2026_08_29_multi_payment.sql (চালানো প্রয়োজন)।

## [1.1] cdn — 2026-08-29

- পাঁচ-প্রদাতা CDN ইন্টিগ্রেশন (Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS আপলোড + পার্জ + প্রিলোড) + অ্যাডমিন কনফিগারেশন (game_cdn_provider টেবিল CRUD/সক্ষম-অক্ষম/HeadBucket সংযোগ পরীক্ষা), service শুধুমাত্র DB থেকে পড়ে (config/cdn.php মুছে ফেলা হয়েছে)।

## [1.1] features — 2026-08-29

- মিনি-গেম Farm Match-3 P0: ডোমেইন ইঞ্জিন + ৪ লেভেল ডিজাইন + Vitest ইউনিট টেস্ট (`game/xiaoxiaole/`)।
- ওয়ান-ক্লিক ইনস্টল উইজার্ড: ব্রাউজারে অ্যাডমিন তৈরি, বিদ্যমান DB আপগ্রেড (HY093 বাইন্ডিং-প্যারামিটার মিসম্যাচ, Unknown column 'countries' ঠিক), install.lock পুনরায় ইনস্টল আটকায়।
- CI: push-এ স্বয়ংক্রিয় ইনক্রিমেন্টাল tag + GitHub Release প্রকাশ।
- ইনফ্রাস্ট্রাকচার: ডেটাবেসের নাম game-platform, `game_` টেবিল প্রিফিক্স একীভূত।
- ডক সিঙ্ক: FEATURES.md ১৩ ভাষায় রেজিলিয়েন্স (circuit-breaker/retry/degradation সুইচ), পেমেন্ট পদ্ধতি CRUD, মিনি-গেম, ওয়ান-ক্লিক ইনস্টল, CI লাইন সম্পূর্ণ (উপরে [1.1] resilience / payments এন্ট্রির সাথে সামঞ্জস্যপূর্ণ)।
