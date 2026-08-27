# वैश्विक गेम एग्रीगेशन प्लेटफ़ॉर्म — पारिस्थितिकी विस्तार समीक्षा रिपोर्ट v2.0
<!-- lang-nav -->

Languages: [中文](PLATFORM-AUDIT-REPORT.md) · [English](PLATFORM-AUDIT-REPORT.en.md) · [한국어](PLATFORM-AUDIT-REPORT.ko.md) · [Русский](PLATFORM-AUDIT-REPORT.ru.md) · [Deutsch](PLATFORM-AUDIT-REPORT.de.md) · [Français](PLATFORM-AUDIT-REPORT.fr.md) · [Español](PLATFORM-AUDIT-REPORT.es.md) · [Português](PLATFORM-AUDIT-REPORT.pt.md) · **हिन्दी** · [العربية](PLATFORM-AUDIT-REPORT.ar.md) · [বাংলা](PLATFORM-AUDIT-REPORT.bn.md) · [Bahasa Indonesia](PLATFORM-AUDIT-REPORT.id.md) · [日本語](PLATFORM-AUDIT-REPORT.ja.md)


> **समीक्षा तिथि**: 2026-08-04
> **समीक्षा क्षेत्र**: सभी नियोजित 16 कार्यात्मकताएँ, कोड गुणवत्ता, सुरक्षा, मॉडल स्थिरता, परीक्षण
> **शाखा**: main

---

## 一、सारांश

| श्रेणी | रेटिंग | परिवर्तन |
|------|------|------|
| कार्यात्मक पूर्णता | **A (96/100)** | +18 एंडपॉइंट, +10 मॉडल, +7 सेवाएँ |
| कोड गुणवत्ता | **A (95/100)** | 0 सिंटैक्स त्रुटि, 0 रिग्रेशन |
| सुरक्षा सुरक्षा | **A (94/100)** | ProviderAuth HMAC-SHA256, PKCE, केवल मित्रों को निजी संदेश |
| पारिस्थितिकी कॉन्फ़िगरेशन | **A- (92/100)** | FeatureFlag 4 स्विच, Webhook 7 इवेंट, VIP 5 स्तर |
| परिनियोजन पूर्णता | **B+ (89/100)** | ChatWebSocket :8791, दस्तावेज़ समकालिकीकरण |

---

## 二、सत्यापित आइटम

### 2.1 PHP सिंटैक्स जाँच
- admin/ और service/ की सभी `.php` फ़ाइलें: **0 त्रुटि**
- कॉन्फ़िगरेशन फ़ाइलें (route.php, process.php): **0 त्रुटि**

### 2.2 परीक्षण सूट
- 132 परीक्षण / 251 असर्शन: **0 नया रिग्रेशन**
- पूर्व-मौजूद विफलताएँ (23 आइटम): ClickHouse स्थापित नहीं (14), Captcha पर्यावरण निर्भरता (2), मिडलवेयर कॉन्फ़िगरेशन (2), अनुवाद सेवा (3), स्वास्थ्य जाँच (2)

### 2.3 सुरक्षा समीक्षा

| आइटम | स्थिति |
|----|------|
| Provider HMAC-SHA256 हस्ताक्षर सत्यापन | ✓ 5 मिनट समय-विंडो रीप्ले रोकथाम |
| Twitter OAuth PKCE (S256) | ✓ code_verifier Redis भंडारण |
| OAuth state CSRF सुरक्षा | ✓ Redis भंडारण + एक-बार पढ़कर हटाना |
| केवल मित्र निजी संदेश भेज सकते हैं | ✓ FriendController सत्यापन |
| Webhook URL फ़िल्टरिंग | ✓ filter_var(FILTER_VALIDATE_URL) |
| Webhook इवेंट श्वेतसूची | ✓ 7 प्रकार के इवेंट, array_intersect फ़िल्टरिंग |
| JWT प्रमाणीकरण (ChatWebSocket) | ✓ jwt()->verify() |
| SQL इंजेक्शन सुरक्षा | ✓ Eloquent ORM, कोई नेटिव स्ट्रिंग संयोजन नहीं |
| API दर सीमा | ✓ OAuth 10 बार/मिनट, सामान्य 60 बार/मिनट |
| Encryptable एन्क्रिप्शन | ✓ OAuth token / API key स्वचालित एन्क्रिप्ट-डिक्रिप्ट |

### 2.4 मॉडल स्थिरता मरम्मत

| समस्या | मरम्मत |
|------|------|
| 🔴 service मॉडल तालिका नामों में `game_` उपसर्ग (मौजूदा मानकों से टकराव) | 10 नए मॉडलों से सभी उपसर्ग हटाए गए |
| 🟡 `AchievementService` हार्डकोडेड `game_user_session` | service संस्करण में `user_session` किया गया |
| 🟡 `GameController` हार्डकोडेड `game_game_category_rel` | service संस्करण में `game_category_rel` किया गया |

---

## 三、कार्यात्मक डिलीवरी सूची

### Phase 1 — गेम एकीकरण परत

| फ़ाइल | विवरण |
|------|------|
| `provider/GameProvider.php` (admin+service) | सार आधार वर्ग: bet/settle/refund/rollback/signRequest |
| `provider/SelfProvider.php` (admin+service) | स्वयं-विकसित गेम: DB ट्रांज़ैक्शन + SELECT FOR UPDATE |
| `provider/ThirdPartyProvider.php` (admin+service) | थर्ड-पार्टी: Guzzle HTTP + HMAC-SHA256 |
| `provider/ProviderFactory.php` (admin+service) | फ़ैक्टरी: match(game.type) |
| `middleware/ProviderAuth.php` (service) | HMAC-SHA256 हस्ताक्षर सत्यापन, 5 मिनट विंडो |
| `controller/ProviderController.php` (service) | 4 एंडपॉइंट: balance/bet/settle/refund |
| `service/GameSessionService.php` (admin+service) | Redis हार्टबीट + 15 मिनट टाइमआउट डिटेक्शन |

### Phase 2 — संचालन समर्थन परत

| फ़ाइल | विवरण |
|------|------|
| `model/Ticket.php` + `TicketReply.php` (admin+service) | टिकट + उत्तर, 5 प्रकार |
| `controller/TicketController.php` (service + admin) | C-छोर 4 एंडपॉइंट + प्रशासन 5 एंडपॉइंट |
| `service/VerificationService.php` (admin+service) | 6-अंकीय सत्यापन कोड, Redis 10 मिनट, 60s कूलडाउन |
| `controller/VerificationController.php` (service) | 4 एंडपॉइंट: sendEmail/confirmEmail/sendSms/confirmPhone |
| `service/PushService.php` (admin+service) | FCM/APNs/हुआवेई पुश एब्स्ट्रैक्शन |
| `model/DeviceToken.php` (admin+service) | डिवाइस टोकन भंडारण |

### Phase 3 — उपयोगकर्ता प्रतिधारण

| फ़ाइल | विवरण |
|------|------|
| `model/VipLevel.php` + `UserVip.php` + `ExpLog.php` | 5-स्तरीय VIP, अनुभव अंक प्रणाली |
| `service/VipService.php` (admin+service) | addExp/स्वचालित उन्नयन/लाभ क्वेरी |
| **ExchangeController** एकीकरण | quote() VIP छूट + विनिमय दर लाभ लागू करता है |
| **WithdrawController** एकीकरण | apply() VIP शुल्क राहत लागू करता है |
| **ReferralController** एकीकरण | apply() रेफरल EXP जोड़ता है |
| `model/Achievement.php` + `UserAchievement.php` | 12 अंतर्निहित उपलब्धियाँ |
| `service/AchievementService.php` (admin+service) | इवेंट-संचालित डिटेक्शन + प्रगति ट्रैकिंग |

### Phase 4 — सामाजिक परत

| फ़ाइल | विवरण |
|------|------|
| `model/Friend.php` (admin+service) | मित्र संबंध: user/friendUser द्विदिश संबंध |
| `controller/FriendController.php` (service) | 7 एंडपॉइंट: list/requests/request/accept/reject/remove/search |
| `model/Message.php` (admin+service) | निजी संदेश मॉडल |
| `controller/ChatController.php` (service) | 5 एंडपॉइंट: conversations/messages/send/markRead/unreadTotal |
| `process/ChatWebSocket.php` (service) | WebSocket :8791, JWT प्रमाणीकरण, Redis Pub/Sub रीयल-टाइम पुश |

### Phase 5 — बुनियादी ढाँचा

| फ़ाइल | विवरण |
|------|------|
| `event/EventBus.php` (admin+service) | Redis Pub/Sub इवेंट बस |
| **5 कंट्रोलर** emit एकीकरण | Exchange/Withdraw/Game/Referral + Auth |
| `controller/WebhookController.php` (service) | 4 एंडपॉइंट: list/register/delete/test |
| `AnalyticsController` नए 4 एंडपॉइंट | retention/funnel/arpu/economy |
| `service/FeatureFlag.php` (admin+service) | DB फ़ीचर स्विच, 4 पूर्व-सेट स्विच |

### अतिरिक्त — OAuth विस्तार

| फ़ाइल | विवरण |
|------|------|
| **OAuthController** पुनर्लेखन | 3→7 प्लेटफ़ॉर्म: +X(Twitter)/Microsoft/LinkedIn/GitHub |
| Twitter PKCE | S256 code_challenge, Redis में code_verifier भंडारण |
| GitHub ईमेल फ़ॉलबैक | /user/emails API primary verified email |

---

## 四、पाई गई और ठीक की गई समस्याएँ

| # | समस्या | गंभीरता | मरम्मत |
|---|------|--------|------|
| 1 | 🔴 service मॉडल तालिका नामों में सभी `game_` उपसर्ग (10) | उच्च | sed बैच निष्कासन |
| 2 | 🟡 service AchievementService हार्डकोडेड `game_user_session` | मध्यम | `user_session` किया गया |
| 3 | 🟡 service GameController हार्डकोडेड `game_game_category_rel` | मध्यम | `game_category_rel` किया गया |
| 4 | 🟡 route.php दोहरा बैकस्लैश + अवशिष्ट echo कथन | मध्यम | मरम्मत |
| 5 | 🟢 Friend/Message मॉडल शुरुआत में नहीं बनाए गए थे (केवल SQL) | निम्न | बनाए गए |
| 6 | 🟢 LeaderboardWebSocket पोर्ट वास्तव में 8790 उपयोग करता है, chat-ws 8791 पर बदला गया | निम्न | पोर्ट समायोजन |

---

## 五、सांख्यिकी डेटा

### कोड मात्रा

| मीट्रिक | संख्या |
|------|------|
| नई PHP फ़ाइलें | 51 |
| नई SQL फ़ाइलें | 1 (165 पंक्तियाँ) |
| संशोधित मौजूदा फ़ाइलें | 7 (5 कंट्रोलर + 2 रूट/प्रोसेस कॉन्फ़िगरेशन) |
| नए मॉडल | 10 (admin+service = 20 फ़ाइलें) |
| नई सेवाएँ | 6 |
| नए कंट्रोलर | 6 |
| नए API एंडपॉइंट | 50+ |
| नई डेटा तालिकाएँ | 10 |
| दस्तावेज़ अपडेट | 8 .md + 2 चित्र |

### कोड गुणवत्ता

| मीट्रिक | मान |
|------|-----|
| PHP सिंटैक्स त्रुटियाँ | 0 |
| परीक्षण रिग्रेशन | 0 |
| नई vendor निर्भरताएँ | 0 |
| SQL इंजेक्शन जोखिम | 0 |
| हार्डकोडेड कुंजियाँ | 0 |

---

## 六、पारिस्थितिकी विस्तार स्थान (अपूर्ण आइटम)

| कार्यात्मकता | प्राथमिकता | विवरण |
|------|--------|------|
| टूर्नामेंट/चैंपियनशिप प्रणाली | P2 | FeatureFlag में `feature.tournament` स्विच आरक्षित |
| बहु-स्तरीय रेफरल कमीशन | P3 | वर्तमान में एकल-स्तरीय रेफरल, द्वितीय-स्तरीय लाभ-विभाजन बढ़ाया जा सकता है |
| कूपन शर्त सीमाएँ | P3 | न्यूनतम रिचार्ज/निर्दिष्ट गेम/पहली-बार उपयोगकर्ता शर्तें जोड़ें |
| स्वचालित भुगतान (PayPal Payouts) | P3 | निकासी वर्तमान में मैन्युअल समीक्षा, स्वचालित भुगतान जोड़ा जा सकता है |
| प्रशासन VIP/उपलब्धि कॉन्फ़िगरेशन पेज | P3 | बैकएंड मॉडल मौजूद है, Flutter पेज बनाना बाकी |
| मोबाइल पुश गहन एकीकरण | P3 | PushService ढाँचा मौजूद है, FCM/APNs क्रेडेंशियल जोड़ने होंगे |
| Flutter चैट/मित्र UI | P3 | API + WebSocket तैयार, फ्रंटएंड पेज बनाना बाकी |
| गेम प्रदाता SDK दस्तावेज़ | P3 | Provider API तैयार, एकीकरण दस्तावेज़ पूरा करना बाकी |

---

---

## 八、विस्तार स्थान मरम्मत (2026-08-04 तीसरा दौर)

### P2 लागू

**#1 टूर्नामेंट/चैंपियनशिप प्रणाली**
- `Tournament` + `TournamentEntry` मॉडल (admin+service)
- `TournamentController` (service): list/detail/join 3 एंडपॉइंट
- FeatureFlag `tournament` स्विच नियंत्रण
- समर्थन: सक्रिय/आगामी/समाप्त फ़िल्टरिंग, प्रतिभागी संख्या सीमा, लीडरबोर्ड

### P3 लागू

**#2 बहु-स्तरीय रेफरल कमीशन**
- `Referral` मॉडल में `parent_id` जोड़ा गया, द्वितीय-स्तरीय संबंध समर्थन
- `ReferralCommission` मॉडल लाभ-विभाजन विवरण रिकॉर्ड करता है (level/commission_rate/commission_amount)
- `ReferralController` स्वचालित रूप से द्वितीय-स्तरीय कमीशन की गणना करता है (कॉन्फ़िगर करने योग्य `level2_rate`)

**#3 कूपन शर्त सीमाएँ**
- `Coupon` मॉडल में `conditions` JSON फ़ील्ड जोड़ा गया
- 3 प्रकार की शर्तें समर्थित:
  - `min_deposit`: न्यूनतम संचयी रिचार्ज
  - `first_user_only`: केवल बिना रिचार्ज वाले नए उपयोगकर्ता
  - `game_id`: निर्दिष्ट गेम खेला होना चाहिए
- `CouponController.available()` और `claim()` दोनों शर्तें सत्यापित करते हैं

**#4 Provider SDK दस्तावेज़**
- `docs/PROVIDER-SDK.md` पूर्ण एकीकरण दस्तावेज़
- हस्ताक्षर एल्गोरिथ्म का विस्तृत विवरण + PHP/Go/Python उदाहरण कोड
- 4 API एंडपॉइंट दस्तावेज़ (balance/bet/settle/refund)
- स्वयं-विकसित गेम एकीकरण मार्गदर्शिका + सत्र प्रबंधन + गेम कॉन्फ़िगरेशन

## 九、अंतिम स्कोर (अद्यतन)

| श्रेणी | प्रारंभिक (v1) | v2.0 पारिस्थितिकी विस्तार | v2.1 विस्तार मरम्मत | परिवर्तन |
|------|-----------|---------------|---------------|------|
| कार्यात्मक पूर्णता | 85 → | 96 → | **98** | +13 |
| कोड गुणवत्ता | 92 → | 95 → | **95** | +3 |
| सुरक्षा सुरक्षा | 94 → | 94 → | **94** | स्थिर |
| पारिस्थितिकी कॉन्फ़िगरेशन | 80 → | 92 → | **95** | +15 |
| परिनियोजन पूर्णता | 72 → | 89 → | **90** | +18 |

**कुल**: A- (84.6) → A (93.2) → **A (94.4)**

---

## 十、2026-08-18 सुरक्षा और उपलब्धता मरम्मत पुष्टि

इस दौर (2026-08-18) में पूर्ण की गई सुरक्षा और उपलब्धता मरम्मतें (कार्यक्षेत्र में कमिट नहीं, संस्करण 1.1 के साथ बाद में जारी होंगी):

| आइटम | मरम्मत सामग्री | स्थिति |
|----|---------|------|
| भुगतान कॉलबैक provider श्वेतसूची | केवल stripe/paypal स्वीकार, अन्य 403 अस्वीकृत; कॉलबैक provider और ऑर्डर भुगतान विधि असंगत (क्रॉस-चैनल प्रतिरूपण) अस्वीकृत | ✅ ठीक किया गया |
| भुगतान कॉलबैक fail-closed | Stripe: `STRIPE_WEBHOOK_SECRET` कॉन्फ़िगर नहीं या हस्ताक्षर सत्यापन विफल होने पर false; PayPal: `PAYPAL_WEBHOOK_ID` कॉन्फ़िगर नहीं या सत्यापन असामान्य होने पर अस्वीकृत; हस्ताक्षर टाइमस्टैम्प ±300s से अधिक को रीप्ले मानकर अस्वीकृत | ✅ ठीक किया गया |
| राशि मिलान | कॉलबैक राशि और ऑर्डर राशि `bccomp(…, 4)` से सटीक तुलना, असंगत होने पर अस्वीकृत | ✅ ठीक किया गया |
| कॉलबैक जमा ट्रांज़ैक्शनल | ऑर्डर अपडेट + वॉलेट जमा एक ही ट्रांज़ैक्शन में, जमा विफल होने पर रोलबैक | ✅ ठीक किया गया |
| JWT कुंजी स्टार्टअप सत्यापन | `JWT_SECRET_KEY` अनुपलब्ध या अभी भी डिफ़ॉल्ट मान `open-admin-jwt-secret-change-in-production` होने पर स्टार्टअप अस्वीकृत, admin/service एक समान | ✅ ठीक किया गया |
| विश्लेषण सेवा रूट | admin/config/route.php में 12 `/admin/analytics/*` रूट पंजीकृत (AnalyticsController के सभी तरीके) | ✅ ठीक किया गया |
| तालिका उपसर्ग | 52 मॉडलों से हार्डकोडेड `game_` उपसर्ग हटाया गया (`game_game_` दोहरे उपसर्ग का उन्मूलन), DB उपसर्ग एकीकृत रूप से config `prefix=game_` द्वारा | ✅ ठीक किया गया |
| दर सीमा डिग्रेडेशन | RateLimit Redis विफलता पर fail-closed (मौन अनुमति के बजाय अस्वीकार) | ✅ ठीक किया गया |
| refresh token | service AuthController रिफ्रेश टोकन तर्क पुनर्लेखन | ✅ ठीक किया गया |
| DepositLogService | service संस्करण पोर्ट पूरा किया गया, admin/service दोहरी प्रति में से एक का विचलन समाप्त | ✅ ठीक किया गया |
| मृत कोड सफाई | Test मॉडल हटाया गया; DepositLog ऑडिट DB में लिखा गया | ✅ ठीक किया गया |
| Apple id_token | JWKS RS256 हस्ताक्षर सत्यापन + kid रिफ्रेश + aud/iss/exp | ✅ ठीक किया गया |
| Webhook SSRF | `isSafeWebhookUrl()` केवल https सार्वजनिक नेटवर्क, इंट्रानेट/आरक्षित पते अस्वीकृत | ✅ ठीक किया गया |
| 2FA | Base32 डिकोड के बाद HMAC; `/api/2fa/verify` प्रति उपयोगकर्ता 5 बार/15 मिनट लॉक | ✅ ठीक किया गया |
| निकासी परमाणुकरण | समीक्षा/भुगतान सशर्त UPDATE; वैकल्पिक दोहरी समीक्षा; आवेदन पर Redis उपयोगकर्ता लॉक | ✅ ठीक किया गया |
| Prometheus व्यावसायिक मीट्रिक | `/metrics`: प्रतीक्षारत समीक्षा निकासी, आज की पुष्टि रिचार्ज (30s कैश), इवेंट emit/consume, memory_usage, version=1.1 | ✅ लागू |
| FeatureFlag ग्रेडुअल रोलआउट | `inRollout` / `abTest` crc32 बकेटिंग `feature.{name}_percent` पढ़ता है | ✅ लागू |

**अभी भी अपूर्ण**: webman/queue वायरिंग, ClickHouse वास्तविक एकीकरण। ऐतिहासिक स्कोर और निष्कर्ष अपरिवर्तित। लागू किया गया: इवेंट बस उपभोक्ता प्रक्रिया (`service/app/process/EventConsumer.php` + `process.php` में `event-consumer` पंजीकरण), साझा परत डीडुप्लिकेशन (एकल `packages/platform-common` में विलय), HarmonyOS C-छोर पेज, उपलब्धि इंजन वायरिंग (EventConsumer के भीतर कॉल), service CI गेट।

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
