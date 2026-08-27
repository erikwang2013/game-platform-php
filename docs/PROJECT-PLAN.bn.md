# 项目全面规划 (Project Plan)
<!-- lang-nav -->

Languages: [中文](PROJECT-PLAN.md) · [English](PROJECT-PLAN.en.md) · [한국어](PROJECT-PLAN.ko.md) · [Русский](PROJECT-PLAN.ru.md) · [Deutsch](PROJECT-PLAN.de.md) · [Français](PROJECT-PLAN.fr.md) · [Español](PROJECT-PLAN.es.md) · [Português](PROJECT-PLAN.pt.md) · [हिन्दी](PROJECT-PLAN.hi.md) · [العربية](PROJECT-PLAN.ar.md) · **বাংলা** · [Bahasa Indonesia](PROJECT-PLAN.id.md) · [日本語](PROJECT-PLAN.ja.md)


> 生成日期: 2026-08-16 · 基于 6 人团队 (researcher/architect/backend-dev/frontend-dev/tester/reviewer) 只读盘点 + 关键论断实测验证
> 覆盖: 现状总结 / 问题与风险 / P0-P1-P2 路线图 / 文档修复 / 质量门

---

## এক. প্রকল্পের বর্তমান অবস্থা

**বিশ্বব্যাপী গেম অ্যাগ্রিগেশন প্ল্যাটফর্ম** — PHP 8.3 + webman v2, ডুয়াল-অ্যাপ monorepo:
`admin/`(8787 অ্যাডমিন প্যানেল) + `service/`(8788 C-এন্ড) + `apps/`(Flutter + HarmonyOS) + `install/`(ইনস্টলেশন উইজার্ড ৪৩টি টেবিল)।

| মাত্রা | পরিমাপিত আকার |
|------|---------|
| কন্ট্রোলার | admin 32 + service 30 = 62 |
| API এন্ডপয়েন্ট | ~149 (admin 103 / service 88, Webhook/Provider কলব্যাক সহ) |
| ডেটা মডেল | admin 46 / service 44, admin/service-এ **ডুপ্লিকেট কপি** (কোনো শেয়ার্ড লেয়ার নেই) |
| টেস্ট | 132 কেস / 8 ফাইল (admin প্রজেক্ট), service প্রজেক্টে **শূন্য টেস্ট** |
| সংস্করণ | v1.1 (2026-08-07): Redis প্লাগইন, অ্যানালিটিক্স সার্ভিস, Redis ডিগ্রেডেশন, টেস্ট ফিক্স |

ইতোমধ্যে বাস্তবায়িত ক্ষমতা: JWT+RBAC, ওয়ালেট অপটিমিস্টিক লক, টপ-আপ(Stripe/PayPal ভেরিফিকেশন), বিনিময় স্প্রেড, উত্তোলন রিভিউ+PayPal পেমেন্ট, গেম CRUD/Provider গেটওয়ে(HMAC), কুপন/VIP/অ্যাচিভমেন্ট/টিকিট/রেফারেল কমিশন/2FA/সোশ্যাল(ফ্রেন্ড/চ্যাট WS)/টুর্নামেন্ট/Webhook/পুশ(FCM/APNs/হুয়াওয়ে)/i18n দ্বিভাষিক।

---

## দুই. সমস্যা ও ঝুঁকি (বাস্তবে যাচাই করা)

### CRITICAL — ফান্ড নিরাপত্তা

| # | সমস্যা | অবস্থান |
|---|------|------|
| C1 | পেমেন্ট কলব্যাকে `provider` ক্লায়েন্ট থেকে আসে, stripe/paypal না হলে **সম্পূর্ণরূপে ভেরিফিকেশন এড়িয়ে যায়**, জাল কলব্যাকে সরাসরি ক্রেডিট | service/.../PaymentController.php:36-42 |
| C2 | ভেরিফিকেশন fail-open: `STRIPE_WEBHOOK_SECRET` কনফিগ না থাকলে → `return true`; PayPal-এর যেকোনো ব্যতিক্রম → `return true`। আক্রমণ চেইন: নিজের টপ-আপ অর্ডার তৈরি→জাল কলব্যাক→আনলিমিটেড টপ-আপ | PaymentController.php:167, 225 |
| C3 | `JWT_SECRET_KEY` অনুপস্থিত হলে পাবলিক হার্ডকোডেড সিক্রেট `open-admin-jwt-secret-change-in-production`-এ ফিরে যায়; প্রোডাকশনে env না দিলে জাল অ্যাডমিন Token তৈরি করা যায় | admin+service config/plugin/erikwang2013/jwt/jwt.php:12 |

### HIGH — সঠিকতা/সামঞ্জস্য

| # | সমস্যা | অবস্থান |
|---|------|------|
| H1 | অ্যানালিটিক্স সার্ভিস AnalyticsController-এর ১২টি মেথড সব বাস্তবায়িত কিন্তু **শূন্য রুট**, সব 404 ডেড কোড, অথচ VERSIONS.md ঘোষণা করেছে ডেলিভারড | admin/config/route.php (0 জায়গায় analytics) |
| H2 | ইভেন্ট বাস বিচ্ছিন্ন: emit-এর ৪টি কল আছে (game.played/withdraw.completed/exchange.completed/referral.applied), কিন্তু `subscribe()`-এ কোনো প্রসেস রেজিস্টার নেই, ইভেন্ট পাবলিশ হলেই হারিয়ে যায়; VIP/অ্যাচিভমেন্ট/নোটিফিকেশন ইঞ্জিন সব ঝুলে আছে | admin+service app/event/EventBus.php |
| H3 | common/ ও model/ দুটি কপিতে কপি হয়ে ড্রিফট হয়েছে (DepositLogService দুটি কপির বিষয়বস্তু আলাদা, User.php অসামঞ্জস্য); এক জায়গায় ফিক্স মানে দুটি জায়গায় কাজ। **common/service ইতিমধ্যে** `packages/platform-common`-এ নিষ্কাশিত (erik/platform-common, আগের common-php একীভূত); model ও app/common র্যাপার এখনও দুটি কপি | admin/common vs service/common → packages/platform-common |
| H4 | ~~HarmonyOS C-এন্ড `apps/harmonyos/` খালি ডিরেক্টরি, 0 পেজ vs VERSIONS.md-এর দাবি 5 পেজ~~ — বাস্তবায়িত হয়েছে (2026-08-18: 5 পেজ `apps/harmonyos/`-এ) | apps/harmonyos/ |
| H5 | Stripe কলব্যাকে `t=` টাইমস্ট্যাম্প টলারেন্স যাচাই নেই (রিপ্লে করা যায়), এবং ক্রেডিট পরিমাণ গেটওয়ের প্রকৃত পেমেন্ট পরিমাণের সাথে মেলানো হয় না | PaymentController.php:191-194 |
| H6 | Apple id_token শুধুমাত্র base64 ডিকোড করে payload পড়া হয়; ভেরিফিকেশন নেই, aud/iss/exp যাচাই নেই, ক্রস-অ্যাপ আইডেন্টিটি কনফিউশন ঝুঁকি | OAuthController.php:376-380 |

### MEDIUM — নির্ভরযোগ্যতা/বাস্তবায়ন ত্রুটি

| # | সমস্যা |
|---|------|
| M1 | 2FA ত্রুটি দ্বৈত: `/api/2fa/verify` পাবলিক, প্রতি-ব্যবহারকারী ট্রাই লক নেই (ব্রুট-ফোর্স অরাকল); TOTP Base32 স্ট্রিং সরাসরি HMAC কী হিসেবে ব্যবহৃত (ডিকোড করা হয়নি), Authenticator-এর সাথে মেলে না → **2FA কার্যত ব্যবহারযোগ্য নয়** |
| M2 | উত্তোলন রিভিউ/পেমেন্ট check-then-act, অ্যাটমিক স্টেট আপডেট নেই, কনকারেন্সিতে ডুপ্লিকেট পেমেন্ট সম্ভব; দ্বৈত পর্যালোচনা নেই |
| M3 | Webhook কলব্যাক URL শুধুমাত্র filter_var দিয়ে যাচাই, ইন্ট্রানেট IP নির্দেশ করা যায় (SSRF), dispatch যেকোনো URL-এ POST করে |
| M4 | উত্তোলনের দৈনিক/মাসিক সীমা "আগে চেক পরে ইনসার্ট" অ-অ্যাটমিক, কনকারেন্সিতে সীমা ভাঙা যায় |
| M5 | Redis ব্যর্থ হলে fail-open, কোনো ইউনিফাইড অ্যাবস্ট্রাকশন নেই: JWT ব্ল্যাকলিস্ট লগআউট অকার্যকর, রেট লিমিট নিঃশব্দে অকার্যকর; ডিগ্রেডেশন ফাঁক: PayoutService::getAccessToken, ChatWebSocket brpop, OAuth state সংরক্ষণ |
| M6 | ClickHouse সম্পূর্ণ অব্যবহৃত: প্রোবাবিলিটি গণনা আসলে MySQL রিয়েল-টাইম COUNT(DISTINCT)+সাবকুয়েরি JOIN, বড় টেবিলে O(n²) ঝুঁকি; composer নির্ভরতা অক্ষম |
| M7 | কিউ অর্ধসমাপ্ত: admin/app/queue-এ ComputeDailyStats + ৩টি ES টাস্ক আছে, কিন্তু webman/queue ইনস্টল নেই, process.php-এ রেজিস্টার নেই, কোনো কলার নেই |
| M8 | ডেড কোড: Vip/Achievement/Notification/FeatureFlag সার্ভিসের শূন্য কলার; DepositLogService::log() খালি বাস্তবায়ন; Test মডেল অবশিষ্ট; রিটেনশন অ্যালগরিদম একক cohort অনুমান, অগভীর |

### LOW
- উত্তোলনে 2FA/KYC বাধ্যতামূলক নয়, যেকোনো PayPal ইমেইলে পেমেন্ট যায়; রিভিউ নোট নোটিফিকেশন টেক্সটে যায় (XSS সারফেস)
- ডকুমেন্টেশন বাস্তবতার সাথে মেলে না: install.sql 43 টেবিল vs ডকুমেন্টে আগে লেখা 52; docker-compose ৭টি সার্ভিস vs FEATURES.md-এ আগে লেখা 8; "শেয়ার্ড Model 34" সত্য নয় (admin 46 / service 44 প্রত্যেকে আলাদা, কোনো শেয়ার্ড লেয়ার নেই)। CHANGELOG-এ যোগ করা হয়েছে, দেখুন `docs/CHANGELOG.md`।

### পাস করা আইটেম (নিরাপত্তা রিভিউতে সমস্যা নেই)
ওয়ালেট অপটিমিস্টিক লক + version শর্তসাপেক্ষ আপডেট সঠিক; কলব্যাক idempotency `where status=pending` শর্তসাপেক্ষ আপডেট সঠিক; সম্পূর্ণ ORM-এ সরাসরি SQL জোড়া নেই; .env git-এ নেই; admin-এর সব রুট AdminAuth+RBAC ডিফল্ট ডিনাই; OAuth state যাচাই + একবার ব্যবহার সঠিক।

> **2026-08-18 ফিক্স অবস্থা**: C1/C2/C3/H1/H5/H6 ফিক্সড; H2 ইভেন্ট বাস: `process.php`-এ `event-consumer` নিবন্ধিত এবং কনজিউমার ক্লাস `EventConsumer` বাস্তবায়িত, emit-এর কনজিউমার আছে; M1 Base32 + প্রতি-ব্যবহারকারী লক ফিক্সড; M2 উত্তোলন স্টেট অ্যাটমিক + ঐচ্ছিক দ্বৈত পর্যালোচনা করা হয়েছে; M3 Webhook SSRF ব্লক করা হয়েছে; M4 উত্তোলন আবেদনে Redis ইউজার লক করা হয়েছে; M5 আংশিক সম্পন্ন (RateLimit fail-closed); P2-19 ব্যবসায়িক মেট্রিক + FeatureFlag গ্রেস্কেল বাস্তবায়িত। সমস্যা তালিকা ঐতিহাসিক অডিট উপসংহার হিসেবে রয়ে গেছে।

---

## তিন. রোডম্যাপ

### P0 — ফান্ড নিরাপত্তা + সঠিকতা (আগে করুন, লঞ্চ ব্লক)

1. **পেমেন্ট কলব্যাক fail-closed**: provider হোয়াইটলিস্ট (শুধুমাত্র stripe/paypal) + সিক্রেট অনুপস্থিত হলে অবশ্যই 500 প্রত্যাখ্যান + PayPal-এর যেকোনো ব্যতিক্রম বাধ্যতামূলক প্রত্যাখ্যান (C1/C2) — ✅ সম্পন্ন (2026-08-18: provider হোয়াইটলিস্ট + ক্রস-চ্যানেল জাল ব্যবহার যাচাই + উৎস IP ঐচ্ছিক যাচাই + কলব্যাক ক্রেডিট ট্রানজেকশন-ভিত্তিক)
2. **JWT স্টার্টআপ যাচাই**: env-এ `JWT_SECRET_KEY` না থাকলে স্টার্ট প্রত্যাখ্যান (C3) — ✅ সম্পন্ন (2026-08-18: JWT_SECRET_KEY অনুপস্থিত বা ডিফল্ট `open-admin-jwt-secret-change-in-production` হলে স্টার্ট প্রত্যাখ্যান, admin/service একই)
3. **অ্যানালিটিক্স সার্ভিসে রুট মাউন্ট**: analytics ১২টি রুট + পারমিশন পয়েন্ট নিবন্ধন, VERSIONS.md-এর প্রতিশ্রুতি মেরামত (H1) — ✅ সম্পন্ন (2026-08-18: admin/config/route.php-এ ১২টি `/admin/analytics/*` রুট নিবন্ধিত)
4. **ইভেন্ট বাস চালু**: স্থায়ী সাবস্ক্রাইব প্রসেস নিবন্ধন, নয়তো সিঙ্ক ডাইরেক্ট কল; ইভেন্ট ডেটাবেসে সংরক্ষণ + ব্যর্থ হলে রিট্রাই (H2) — ✅ সম্পন্ন (2026-08-18: emit/consume Redis কাউন্ট INCR; `service/config/process.php`-এ `event-consumer` নিবন্ধিত, `service/app/process/EventConsumer.php` ইভেন্ট কনজিউম করে)
5. **Apple id_token ভেরিফিকেশন**: JWKS যাচাই + aud/iss/exp (H6) — ✅ সম্পন্ন (2026-08-18: RS256 JWKS + kid রিফ্রেশ + aud/iss/exp)
6. **Stripe রিপ্লে ও পরিমাণ মিলানো**: টাইমস্ট্যাম্প টলারেন্স + গেটওয়ে পরিমাণের সাথে তুলনা (H5) — ✅ সম্পন্ন (2026-08-18: t= টাইমস্ট্যাম্প ±৩০০s রিপ্লে প্রতিরোধ + bccomp নির্ভুলতা পরিমাণ মিলানো + secret/webhook_id কনফিগ না থাকলে বা ভেরিফিকেশন ব্যতিক্রম হলে সর্বদা প্রত্যাখ্যান)

### P1 — নির্ভরযোগ্যতা + সামঞ্জস্য

7. **শেয়ার্ড লেয়ার ডেডুপ**: common/model composer path repo-তে নিষ্কাশন (বা সিমলিংক), দুটি কপির ড্রিফট দূর করা (H3) — 🔶 আংশিক সম্পন্ন (2026-08-18: `common/service` একক `packages/platform-common` / `erik/platform-common` path repo-তে নিষ্কাশিত (আগের `common-php` একীভূত), admin+service রেফারেন্স করে; model ও host-bound `app/common` র্যাপার এখনও দুটি কপি, দেখুন `packages/platform-common/DUAL_MODELS.md`)
8. **ইউনিফাইড Redis ডিগ্রেডেশন র্যাপার**: fail কৌশল স্পষ্ট + নিঃশব্দ নয় এমন অ্যালার্ট; PayoutService/OAuth/ChatWebSocket ফলব্যাক যোগ (M5) — 🔶 আংশিক সম্পন্ন (RateLimit fail-closed বাস্তবায়িত: Redis ব্যর্থ হলে নিঃশব্দে পাস না করে লিমিট রিজেক্ট; বাকি করা হয়নি)
9. **webman/queue ওয়্যারিং**: ইভেন্ট ও webhook ডেলিভারি বহন (কনজিউম রিট্রাই, ডেড লেটার), ComputeDailyStats/ES টাস্ক সক্রিয় বা মুছে ফেলা (M7) — ⬜ করা হয়নি
10. **2FA ফিক্স**: Base32 ডিকোড + verify-এ লগইন স্টেট ও প্রতি-ব্যবহারকারী ট্রাই লক (M1) — ✅ সম্পন্ন (2026-08-18: RFC 4648 Base32 ডিকোডের পর HMAC; `/api/2fa/verify` ৫ বার ব্যর্থ হলে ১৫ মিনিট লক, Redis ব্যর্থ হলে fail-closed)
11. **উত্তোলন অ্যাটমিক**: রিভিউ/পেমেন্ট শর্তসাপেক্ষ আপডেট + দ্বৈত পর্যালোচনা; সীমা Redis Lua/ইউনিক কনস্ট্রেন্ট (M2/M4) — 🔶 আংশিক সম্পন্ন (2026-08-18: pending→approved/rejected, approved→processing শর্তসাপেক্ষ UPDATE; ঐচ্ছিক দ্বৈত পর্যালোচনা `withdraw.require_dual_review`; আবেদন পাশে Redis ইউজার লক। Lua সীমা/ইউনিক কনস্ট্রেন্ট নেই)
12. **Webhook SSRF ব্লক**: ইন্ট্রানেট/রিজার্ভড অ্যাড্রেস প্রত্যাখ্যান (M3) — ✅ সম্পন্ন (2026-08-18: `isSafeWebhookUrl()` শুধুমাত্র https পাবলিক)
13. **ClickHouse-এ দুটির একটি**: সত্যিকারের ইন্টিগ্রেশন বা নির্ভরতা অপসারণ + ডকুমেন্টেশন সংশোধন (M6) — ⬜ করা হয়নি
14. **ডেড কোড পরিষ্কার**: Vip/Achievement/Notification/FeatureFlag ওয়্যার বা মুছে ফেলা; Test মডেল মুছে ফেলা; DepositLog অডিট ডেটাবেসে (M8) — 🔶 আংশিক সম্পন্ন (2026-08-18: Test মডেল মুছে ফেলা হয়েছে, DepositLog অডিট ডেটাবেসে; Vip/FeatureFlag/Notification-এর কলার আছে; AchievementService EventConsumer দ্বারা কল হচ্ছে)
15. **service টেস্ট + CI গেট**: কলব্যাক ভেরিফিকেশন/উত্তোলন ফ্লো/Redis ডিগ্রেডেশন/প্রোবাবিলিটি গণনা/অপটিমিস্টিক লক কনকারেন্সি ইন্টিগ্রেশন টেস্ট; phpunit ব্যর্থ হলে ব্লক; service CI-তে (বর্তমান `|| echo warning` ব্যর্থতা অনুমোদন) — 🔶 আংশিক সম্পন্ন (service-এ WebhookUrlSafety / EventBusMessageFormat আছে; CI-তে `phpunit-service` job ব্যর্থ হলে ব্লক অন্তর্ভুক্ত)

**এই রাউন্ডে (2026-08-18) অতিরিক্ত সম্পন্ন (আসল সংখ্যায় নেই)**:
- **টেবিল প্রিফিক্স ফিক্স**: ৫২টি মডেল থেকে হার্ডকোডেড `game_` প্রিফিক্স অপসারণ, `game_game_` ডাবল প্রিফিক্স দূর করা; DB প্রিফিক্স ইউনিফাইডভাবে config/database.php `prefix=game_` থেকে; install.sql পরিবর্তনের প্রয়োজন নেই
- **refresh token পুনর্লিখন**: service AuthController রিফ্রেশ টোকেন লজিক পুনর্লিখিত
- **DepositLogService service সংস্করণ পোর্ট**: service/common/service/DepositLogService.php পূরণ (admin/service ডুয়াল-কপি ড্রিফটের একটি দূর)

### P2 — পর্যবেক্ষণযোগ্যতা / সম্প্রসারণ / অভিজ্ঞতা

16. **HarmonyOS C-এন্ড** শূন্য থেকে ৫ পেজ বাস্তবায়ন (লগইন/লবি/ডিটেইল/ওয়ালেট/প্রোফাইল) (H4) — ✅ সম্পন্ন (2026-08-18: `apps/harmonyos/entry/src/main/ets/pages/`-এ ৫ পেজ রেপোতে)
17. **ফ্রন্টএন্ড পূরণ**: 2FA ভেরিফিকেশন পেজ, কুপন/লিডারবোর্ড/নোটিফিকেশন এন্ট্রি, ES সার্চ UI; main.dart/app_pages.dart রুট উৎস একীভূত; OAuth প্রকৃত কলব্যাক; ফ্রন্টএন্ড AES ট্রান্সপোর্ট লেয়ার
18. **প্রোবাবিলিটি গণনা ClickHouse-এ স্থানান্তর** বা MySQL মেটেরিয়ালাইজড স্ট্যাট টেবিল + ক্যাশ; রিটেনশন প্রকৃত cohort অনুযায়ী পুনর্গণনা
19. **Prometheus ব্যবসায়িক মেট্রিক** (ইভেন্ট ডেলিভারি/কনজিউম রেট, কিউ ডেপথ) + গ্রেস্কেল AB ডিভিশন মিডলওয়্যার (FeatureFlag পুনঃব্যবহার) — 🔶 আংশিক সম্পন্ন (2026-08-18: `GET /metrics` অপেক্ষমাণ রিভিউ উত্তোলন/আজকের নিশ্চিত টপ-আপ/ইভেন্ট emit·consume কাউন্ট; FeatureFlag `inRollout`/`abTest` crc32 বাকেট। কিউ ডেপথ করা হয়নি)
20. **WebSocket ডেটা লিংক ক্লোজড-লুপ**: লিডারবোর্ড/চ্যাট পারসিস্টেন্স নিশ্চিতকরণ
21. **ডকুমেন্টেশন অ্যালাইনমেন্ট**: টেবিল সংখ্যা/সার্ভিস সংখ্যা/শেয়ার্ড লেয়ার বর্ণনা সংশোধন, API ডকুমেন্টেশন বাস্তবায়নের সাথে মিলানো, CHANGELOG যোগ — ✅ সম্পন্ন (2026-08-18: দেখুন `docs/CHANGELOG.md`, FEATURES/VERSIONS/PROJECT-PLAN/অডিট রিপোর্ট §十)

---

## চার. কোয়ালিটি গেট (টিম সহযোগিতা)

- প্রতিটি কোড পরিবর্তনে: admin-এর সম্পূর্ণ টেস্ট `vendor/bin/phpunit` অবশ্যই পাস (শেষে `|| echo warning` বাদ)
- নতুন সংবেদনশীল পাথ (পেমেন্ট/উত্তোলন/অথেনটিকেশন) অবশ্যই টেস্ট সহ
- common/model পরিবর্তনে admin+service দুই পাশে সিঙ্ক (শেয়ার্ড লেয়ার চালুর আগে)
- রিভিউ রিপোর্টে সুপারিশকৃত ফোকাস: ProviderAuth সিগনেচার, AES এনক্রিপশন, ProbabilityService হাতে লেখা SQL

## পাঁচ. টিম

game-platform টিম (৬ সদস্য: researcher/architect/backend-dev/frontend-dev/tester/reviewer) প্রস্তুত, সরাসরি P0 এক্সিকিউট করতে পারে।
