# 项目全面规划 (Project Plan)
<!-- lang-nav -->

Languages: [中文](PROJECT-PLAN.md) · [English](PROJECT-PLAN.en.md) · [한국어](PROJECT-PLAN.ko.md) · [Русский](PROJECT-PLAN.ru.md) · [Deutsch](PROJECT-PLAN.de.md) · [Français](PROJECT-PLAN.fr.md) · [Español](PROJECT-PLAN.es.md) · [Português](PROJECT-PLAN.pt.md) · **हिन्दी** · [العربية](PROJECT-PLAN.ar.md) · [বাংলা](PROJECT-PLAN.bn.md) · [Bahasa Indonesia](PROJECT-PLAN.id.md) · [日本語](PROJECT-PLAN.ja.md)


> निर्माण तिथि: 2026-08-16 · 6-सदस्यीय टीम (researcher/architect/backend-dev/frontend-dev/tester/reviewer) के केवल-पठन सर्वेक्षण + प्रमुख दावों का वास्तविक सत्यापन पर आधारित
> कवरेज: वर्तमान स्थिति सारांश / समस्याएँ और जोखिम / P0-P1-P2 रोडमैप / दस्तावेज़ मरम्मत / गुणवत्ता द्वार

---

## 一、परियोजना की वर्तमान स्थिति

**वैश्विक गेम एग्रीगेशन प्लेटफ़ॉर्म** — PHP 8.3 + webman v2, दोहरा एप्लिकेशन monorepo:
`admin/`(8787 प्रशासन कंसोल) + `service/`(8788 C-छोर) + `apps/`(Flutter + HarmonyOS) + `install/`(स्थापना विज़ार्ड 43 तालिकाएँ)।

| आयाम | वास्तविक आकार |
|------|---------|
| कंट्रोलर | admin 32 + service 30 = 62 |
| API एंडपॉइंट | ~149 (admin 103 / service 88, Webhook/Provider कॉलबैक सहित) |
| डेटा मॉडल | admin 46 / service 44, admin/service **दोहरी प्रति** (कोई साझा परत नहीं) |
| परीक्षण | 132 मामले / 8 फ़ाइलें (admin परियोजना), service परियोजना **शून्य परीक्षण** |
| संस्करण | v1.1 (2026-08-07): Redis प्लगइन, विश्लेषण सेवा, Redis डिग्रेडेशन, परीक्षण मरम्मत |

लागू क्षमताएँ: JWT+RBAC, वॉलेट ऑप्टिमिस्टिक लॉक, रिचार्ज (Stripe/PayPal हस्ताक्षर सत्यापन), विनिमय मार्जिन, निकासी समीक्षा+PayPal भुगतान, गेम CRUD/Provider गेटवे (HMAC), कूपन/VIP/उपलब्धियाँ/टिकट/रेफरल कमीशन/2FA/सामाजिक (मित्र/चैट WS)/टूर्नामेंट/Webhook/पुश (FCM/APNs/हुआवेई)/i18n द्विभाषी।

---

## 二、समस्याएँ और जोखिम (वास्तविक सत्यापित)

### CRITICAL — धन सुरक्षा

| # | समस्या | स्थान |
|---|------|------|
| C1 | भुगतान कॉलबैक `provider` क्लाइंट से आता है, stripe/paypal न होने पर **हस्ताक्षर सत्यापन पूरी तरह छोड़ दिया जाता है**, नकली कॉलबैक सीधे जमा हो जाता है | service/.../PaymentController.php:36-42 |
| C2 | हस्ताक्षर सत्यापन fail-open: `STRIPE_WEBHOOK_SECRET` कॉन्फ़िगर नहीं → `return true`; PayPal कोई भी असामान्यता → `return true`। हमला श्रृंखला: स्वयं रिचार्ज ऑर्डर बनाना→नकली कॉलबैक→असीमित रिचार्ज | PaymentController.php:167, 225 |
| C3 | `JWT_SECRET_KEY` डिफ़ॉल्ट रूप से सार्वजनिक हार्डकोडेड कुंजी `open-admin-jwt-secret-change-in-production` पर फ़ॉलबैक, प्रोडक्शन में env कॉन्फ़िगर न होने पर एडमिन टोकन नकली बनाया जा सकता है | admin+service config/plugin/erikwang2013/jwt/jwt.php:12 |

### HIGH — शुद्धता/स्थिरता

| # | समस्या | स्थान |
|---|------|------|
| H1 | विश्लेषण सेवा AnalyticsController के 12 तरीके पूर्ण रूप से लागू लेकिन **शून्य रूट**, सभी 404 मृत कोड, जबकि VERSIONS.md वितरित होने का दावा करता है | admin/config/route.php (0 स्थान analytics) |
| H2 | इवेंट बस टूटी श्रृंखला: emit के 4 कॉल हैं (game.played/withdraw.completed/exchange.completed/referral.applied), `subscribe()` के लिए कोई प्रक्रिया पंजीकृत नहीं, इवेंट प्रकाशित होते ही खो जाते हैं; VIP/उपलब्धि/सूचना इंजन सभी लटके हुए | admin+service app/event/EventBus.php |
| H3 | common/ और model/ दोहरी प्रति और विचलित हो चुके (DepositLogService दोनों प्रतियों में अलग सामग्री, User.php असंगत), एकल बिंदु मरम्मत दोहरा काम बन गई। **common/service निकाला जा चुका** `packages/platform-common` (erik/platform-common, मूल common-php विलीन); model और app/common रैपर अभी भी दोहरे | admin/common vs service/common → packages/platform-common |
| H4 | ~~HarmonyOS C-छोर `apps/harmonyos/` खाली निर्देशिका, 0 पेज vs VERSIONS.md का 5 पेज का दावा~~ — लागू हो चुका (2026-08-18: 5 पेज `apps/harmonyos/` में) | apps/harmonyos/ |
| H5 | Stripe कॉलबैक `t=` टाइमस्टैम्प सहनशीलता सत्यापित नहीं करता (रीप्ले संभव), और जमा राशि गेटवे की वास्तविक भुगतान राशि से नहीं मिलाई जाती | PaymentController.php:191-194 |
| H6 | Apple id_token केवल base64 डिकोड payload, हस्ताक्षर सत्यापन नहीं, aud/iss/exp सत्यापन नहीं, क्रॉस-एप्लिकेशन पहचान भ्रम का जोखिम | OAuthController.php:376-380 |

### MEDIUM — विश्वसनीयता/कार्यान्वयन दोष

| # | समस्या |
|---|------|
| M1 | 2FA दोष दोहरा प्रभाव: `/api/2fa/verify` सार्वजनिक बिना प्रति-उपयोगकर्ता प्रयास लॉक (ब्रूट-फोर्स oracle); TOTP Base32 स्ट्रिंग को सीधे HMAC कुंजी के रूप में उपयोग करता है (डिकोड नहीं), Authenticator से मेल नहीं खाता → **2FA वास्तव में उपयोग योग्य नहीं** |
| M2 | निकासी समीक्षा/भुगतान check-then-act है, कोई परमाणु स्थिति अद्यतन नहीं, समवर्ती दोहरा भुगतान संभव; कोई दोहरी समीक्षा नहीं |
| M3 | Webhook कॉलबैक URL केवल filter_var सत्यापन, इंट्रानेट IP इंगित किया जा सकता है (SSRF), dispatch किसी भी URL पर POST करता है |
| M4 | निकासी दैनिक/मासिक सीमा "पहले जाँच फिर डालें" गैर-परमाणु, समवर्ती सीमा भंग हो सकती है |
| M5 | Redis विफलता fail-open बिना एकीकृत एब्स्ट्रैक्शन: JWT ब्लैकलिस्ट लॉगआउट निष्क्रिय, दर सीमा मौन रूप से निष्क्रिय; डिग्रेडेशन अंतराल: PayoutService::getAccessToken, ChatWebSocket brpop, OAuth state संग्रहण |
| M6 | ClickHouse शून्य उपयोग: प्रायिकता गणना वास्तव में MySQL रीयल-टाइम COUNT(DISTINCT)+सबक्वेरी JOIN है, बड़ी तालिकाओं पर O(n²) जोखिम; composer में निर्भरता बिना क्षमता |
| M7 | कतार अधूरी: admin/app/queue में ComputeDailyStats + 3 ES कार्य हैं, लेकिन webman/queue स्थापित नहीं, process.php में पंजीकरण नहीं, सभी बिना कॉलर |
| M8 | मृत कोड: Vip/Achievement/Notification/FeatureFlag सेवाओं का शून्य कॉलर; DepositLogService::log() खाली कार्यान्वयन; Test मॉडल अवशेष; प्रतिधारण एल्गोरिथ्म एकल cohort अनुमान मोटा |

### LOW
- निकासी बिना 2FA/KYC अनिवार्यता किसी भी PayPal ईमेल पर भुगतान कर सकती है; समीक्षा टिप्पणी सूचना टेक्स्ट में जाती है (XSS सतह)
- दस्तावेज़ वास्तविकता से असंगत: install.sql 43 तालिकाएँ vs दस्तावेज़ में कभी 52; docker-compose 7 सेवाएँ vs FEATURES.md में कभी 8; "साझा Model 34" अवास्तविक (admin 46 / service 44 प्रत्येक की एक प्रति, कोई साझा परत नहीं)। CHANGELOG पूरा किया गया, देखें `docs/CHANGELOG.md`।

### पास आइटम (सुरक्षा समीक्षा में कोई समस्या नहीं पुष्टि)
वॉलेट ऑप्टिमिस्टिक लॉक + संस्करण सशर्त अद्यतन सही; कॉलबैक शक्ति-समानता `where status=pending` सशर्त अद्यतन सही; पूर्ण ORM बिना सीधे SQL संयोजन; .env git में नहीं; admin के सभी रूट AdminAuth+RBAC डिफ़ॉल्ट अस्वीकार पर; OAuth state सत्यापन+एकल उपभोग सही।

> **2026-08-18 मरम्मत स्थिति**: C1/C2/C3/H1/H5/H6 ठीक हो चुके; H2 इवेंट बस: `process.php` में `event-consumer` पंजीकृत और उपभोक्ता वर्ग `EventConsumer` लागू, emit के उपभोक्ता हैं; M1 Base32 + प्रति-उपयोगकर्ता लॉक ठीक; M2 निकासी स्थिति परमाणुकरण + वैकल्पिक दोहरी समीक्षा; M3 Webhook SSRF रोका गया; M4 निकासी आवेदन Redis उपयोगकर्ता लॉक; M5 आंशिक (RateLimit fail-closed); P2-19 व्यावसायिक मीट्रिक + FeatureFlag ग्रेडुअल रोलआउट लागू। समस्या सूची ऐतिहासिक ऑडिट निष्कर्ष के रूप में बनाए रखी गई है।

---

## 三、रोडमैप

### P0 — धन सुरक्षा + शुद्धता (पहले करें, लॉन्च रोकने वाला)

1. **भुगतान कॉलबैक fail-closed**: provider श्वेतसूची (केवल stripe/paypal) + कुंजी अनुपलब्ध होने पर 500 अस्वीकार + PayPal असामान्यता अनिवार्य अस्वीकार (C1/C2) — ✅ पूर्ण (2026-08-18: provider श्वेतसूची + क्रॉस-चैनल प्रतिरूपण सत्यापन + स्रोत IP वैकल्पिक सत्यापन + कॉलबैक जमा ट्रांज़ैक्शनल)
2. **JWT स्टार्टअप सत्यापन**: env में `JWT_SECRET_KEY` न होने पर स्टार्टअप अस्वीकार (C3) — ✅ पूर्ण (2026-08-18: JWT_SECRET_KEY अनुपलब्ध या डिफ़ॉल्ट `open-admin-jwt-secret-change-in-production` होने पर स्टार्टअप अस्वीकार, admin/service एक समान)
3. **विश्लेषण सेवा रूट**: analytics 12 रूट + अनुमति बिंदु पंजीकृत, VERSIONS.md का वादा पूरा (H1) — ✅ पूर्ण (2026-08-18: admin/config/route.php में 12 `/admin/analytics/*` रूट पंजीकृत)
4. **इवेंट बस संपर्क**: स्थायी सदस्यता प्रक्रिया उपभोग पंजीकृत, या सिंक्रोनस सीधे कॉल में बदलें; इवेंट DB में लिखना + विफलता पुनःप्रयास (H2) — ✅ पूर्ण (2026-08-18: emit/consume के लिए Redis काउंटर INCR; `service/config/process.php` में `event-consumer` पंजीकृत, `service/app/process/EventConsumer.php` इवेंट उपभोग करता है)
5. **Apple id_token हस्ताक्षर सत्यापन**: JWKS सत्यापन + aud/iss/exp (H6) — ✅ पूर्ण (2026-08-18: RS256 JWKS + kid रिफ्रेश + aud/iss/exp)
6. **Stripe रीप्ले और राशि मिलान**: टाइमस्टैम्प सहनशीलता + गेटवे राशि तुलना (H5) — ✅ पूर्ण (2026-08-18: t= टाइमस्टैम्प ±300s रीप्ले रोकथाम + bccomp सटीकता राशि मिलान + secret/webhook_id कॉन्फ़िगर न होने या हस्ताक्षर सत्यापन असामान्य होने पर सभी अस्वीकार)

### P1 — विश्वसनीयता + स्थिरता

7. **साझा परत डीडुप्लिकेशन**: common/model को composer path repo (या सिमलिंक) में निकालना, दोहरी प्रति विचलन समाप्त (H3) — 🔶 आंशिक पूर्ण (2026-08-18: `common/service` निकाला गया एकल `packages/platform-common` / `erik/platform-common` path repo (मूल `common-php` विलीन), admin+service संदर्भित; model और host-bound `app/common` रैपर अभी भी दोहरे, देखें `packages/platform-common/DUAL_MODELS.md`)
8. **एकीकृत Redis डिग्रेडेशन रैपर**: fail नीति स्पष्ट + अलर्ट मौन नहीं; PayoutService/OAuth/ChatWebSocket फ़ॉलबैक पूरा (M5) — 🔶 आंशिक पूर्ण (RateLimit fail-closed लागू: Redis विफलता पर दर सीमा अस्वीकार, मौन अनुमति नहीं; शेष नहीं किया गया)
9. **webman/queue वायरिंग**: इवेंट और webhook डिलीवरी (उपभोग पुनःप्रयास, डेड-लेटर), ComputeDailyStats/ES कार्य सक्षम या हटाना (M7) — ⬜ नहीं किया गया
10. **2FA मरम्मत**: Base32 डिकोड + verify में लॉगिन स्थिति और प्रति-उपयोगकर्ता प्रयास लॉक (M1) — ✅ पूर्ण (2026-08-18: RFC 4648 Base32 डिकोड के बाद HMAC; `/api/2fa/verify` 5 विफलताओं पर 15 मिनट लॉक, Redis विफलता पर fail-closed)
11. **निकासी परमाणुकरण**: समीक्षा/भुगतान सशर्त अद्यतन + दोहरी समीक्षा; सीमा Redis Lua/एकल-सीमा बाधा (M2/M4) — 🔶 आंशिक पूर्ण (2026-08-18: pending→approved/rejected, approved→processing सशर्त UPDATE; वैकल्पिक दोहरी समीक्षा `withdraw.require_dual_review`; आवेदन पक्ष Redis उपयोगकर्ता लॉक। कोई Lua सीमा/एकल-सीमा बाधा नहीं)
12. **Webhook SSRF रोक**: इंट्रानेट/आरक्षित पते अस्वीकार (M3) — ✅ पूर्ण (2026-08-18: `isSafeWebhookUrl()` केवल https सार्वजनिक नेटवर्क)
13. **ClickHouse दो में से एक**: वास्तविक एकीकरण या निर्भरता हटाना + दस्तावेज़ संशोधन (M6) — ⬜ नहीं किया गया
14. **मृत कोड सफाई**: Vip/Achievement/Notification/FeatureFlag को वायर या हटाना; Test मॉडल हटाना; DepositLog ऑडिट DB में (M8) — 🔶 आंशिक पूर्ण (2026-08-18: Test मॉडल हटाया गया, DepositLog ऑडिट DB में; Vip/FeatureFlag/Notification के कॉलर हैं; AchievementService EventConsumer द्वारा कॉल किया जाता है)
15. **service परीक्षण + CI द्वार**: कॉलबैक हस्ताक्षर सत्यापन/निकासी प्रवाह/Redis डिग्रेडेशन/प्रायिकता गणना/ऑप्टिमिस्टिक लॉक समवर्ती एकीकरण परीक्षण; phpunit विफलता पर अवरोध; service CI में (वर्तमान `|| echo warning` विफलता की अनुमति देता है) — 🔶 आंशिक पूर्ण (service में WebhookUrlSafety / EventBusMessageFormat हैं; CI में `phpunit-service` job शामिल, विफलता पर अवरोध)

**इस दौर (2026-08-18) के अतिरिक्त पूर्ण (मूल क्रमांकन में नहीं)**:
- **तालिका उपसर्ग मरम्मत**: 52 मॉडलों से हार्डकोडेड `erik_` उपसर्ग हटाया, `erik_erik_` दोहरा उपसर्ग समाप्त; DB उपसर्ग एकीकृत रूप से config/database.php `prefix=erik_` द्वारा, install.sql में परिवर्तन आवश्यक नहीं
- **refresh token पुनर्लेखन**: service AuthController रिफ्रेश टोकन तर्क पुनर्लेखन
- **DepositLogService service संस्करण पोर्ट**: service/common/service/DepositLogService.php पूरा किया (admin/service दोहरी प्रति का एक विचलन समाप्त)

### P2 — अवलोकनीयता / विस्तार / अनुभव

16. **HarmonyOS C-छोर** शून्य से 5 पेज लागू (लॉगिन/लॉबी/विवरण/वॉलेट/प्रोफ़ाइल) (H4) — ✅ पूर्ण (2026-08-18: `apps/harmonyos/entry/src/main/ets/pages/` में 5 पेज)
17. **फ्रंटएंड पूर्णता**: 2FA सत्यापन पेज, कूपन/लीडरबोर्ड/सूचना प्रवेश, ES खोज UI; main.dart/app_pages.dart रूट स्रोत विलय; OAuth वास्तविक कॉलबैक; फ्रंटएंड AES ट्रांसपोर्ट परत
18. **प्रायिकता गणना ClickHouse में स्थानांतरण** या MySQL भौतिक सांख्यिकी तालिका + कैश; प्रतिधारण वास्तविक cohort पर पुनर्गणना
19. **Prometheus व्यावसायिक मीट्रिक** (इवेंट डिलीवरी/उपभोग दर, कतार गहराई) + ग्रेडुअल रोलआउट AB विभाजन मिडलवेयर (FeatureFlag पुनः उपयोग) — 🔶 आंशिक पूर्ण (2026-08-18: `GET /metrics` प्रतीक्षारत समीक्षा निकासी/आज की पुष्टि रिचार्ज/इवेंट emit·consume गणना; FeatureFlag `inRollout`/`abTest` crc32 बकेटिंग। कतार गहराई नहीं)
20. **WebSocket डेटा लिंक बंद करना**: लीडरबोर्ड/चैट स्थायित्व पुष्टि
21. **दस्तावेज़ संरेखण**: तालिका संख्या/सेवा संख्या/साझा परत विवरण सुधार, API दस्तावेज़ और कार्यान्वयन संरेखण, CHANGELOG जोड़ना — ✅ पूर्ण (2026-08-18: देखें `docs/CHANGELOG.md`, FEATURES/VERSIONS/PROJECT-PLAN/ऑडिट रिपोर्ट §十)

---

## 四、गुणवत्ता द्वार (टीम सहयोग)

- हर कोड परिवर्तन: admin पूर्ण परीक्षण `vendor/bin/phpunit` अनिवार्य पास (`|| echo warning` हटाना)
- नए संवेदनशील पथ (भुगतान/निकासी/प्रमाणीकरण) के साथ परीक्षण अनिवार्य
- common/model परिवर्तन पर admin+service दोनों पक्ष समकालिक (साझा परत लागू होने से पहले)
- समीक्षा रिपोर्ट अनुशंसित फोकस: ProviderAuth हस्ताक्षर, AES एन्क्रिप्शन, ProbabilityService हस्तलिखित SQL

## 五、टीम

game-platform टीम (6 सदस्य: researcher/architect/backend-dev/frontend-dev/tester/reviewer) तैयार है, सीधे P0 निष्पादित कर सकती है।
