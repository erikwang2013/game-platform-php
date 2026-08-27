# संस्करण तुलना
<!-- lang-nav -->

Languages: [中文](VERSIONS.md) · [English](VERSIONS.en.md) · [한국어](VERSIONS.ko.md) · [Русский](VERSIONS.ru.md) · [Deutsch](VERSIONS.de.md) · [Français](VERSIONS.fr.md) · [Español](VERSIONS.es.md) · [Português](VERSIONS.pt.md) · **हिन्दी** · [العربية](VERSIONS.ar.md) · [বাংলা](VERSIONS.bn.md) · [Bahasa Indonesia](VERSIONS.id.md) · [日本語](VERSIONS.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## सारांश

| | मूल संस्करण (Lite) | मानक संस्करण (Standard) | पूर्ण संस्करण (Full) |
|------|------|------|------|
| डेटा तालिकाएँ (install.sql) | 19 | 29 | **43** (दस्तावेज़ में पहले लिखे 52 नहीं) |
| API एंडपॉइंट | 38 | 54 | ~149 (admin+service, Webhook/Provider सहित) |
| बैकएंड कंट्रोलर | 14 | 22 | admin 32 + service 30 |
| डेटा मॉडल | गैर-साझा | गैर-साझा | **admin 46 / service 44 प्रत्येक की एक प्रति, कोई साझा परत नहीं** |
| साझा Service | कोई साझा परत नहीं | कोई साझा परत नहीं | `packages/platform-common` एकल साझा पैकेज |
| Admin फ्रंटएंड पेज | 11 | 13 | 15 |
| Platform फ्रंटएंड पेज | 8 | 10 | 10 |
| HarmonyOS (admin) | - | लॉगिन + डैशबोर्ड | **8 पेज** `admin/apps/harmonyos/` |
| HarmonyOS (C-छोर) | - | - | **5 पेज** `apps/harmonyos/` (लॉगिन/गेम लॉबी/विवरण/वॉलेट/मेरा) |
| Docker सेवाएँ | - | - | **7** (nginx/admin/service/leaderboard-ws/mysql/redis/elasticsearch) |
| परीक्षण मामले | 60 | 60 | admin ~132; service 3 |

---

## उपयोगकर्ता प्रमाणीकरण

| कार्यात्मकता | मूल संस्करण | मानक संस्करण | पूर्ण संस्करण |
|------|--------|--------|--------|
| उपयोगकर्ता नाम/पासवर्ड पंजीकरण/लॉगिन | ✓ | ✓ | ✓ |
| JWT Token (2h+14d) | ✓ | ✓ | ✓ |
| क्लिक कैप्चा | stub | stub | ✓ poster-php |
| खाता लॉक (5 बार/15 मिनट) | ✓ | ✓ | ✓ |
| सत्र सीमा (3 समवर्ती) | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ 7 प्लेटफ़ॉर्म (X/MS/LinkedIn/GitHub सहित) |
| 2FA TOTP दो-कारक प्रमाणीकरण | - | - | ✓ |
| GDPR डेटा निर्यात/विलोपन | - | - | ✓ |

---

## वॉलेट और धन

| कार्यात्मकता | मूल संस्करण | मानक संस्करण | पूर्ण संस्करण |
|------|--------|--------|--------|
| प्लेटफ़ॉर्म कॉइन वॉलेट | ✓ | ✓ | ✓ |
| वॉलेट ऑप्टिमिस्टिक लॉक | ✓ | ✓ | ✓ |
| लेन-देन रिकॉर्ड | ✓ | ✓ | ✓ |
| गेम कॉइन वॉलेट | ✓ | ✓ | ✓ |
| रिचार्ज ऑर्डर निर्माण | ✓ | ✓ | ✓ |
| रिचार्ज कॉलबैक स्वचालित जमा | - | ✓ मैन्युअल | ✓ Stripe/PayPal हस्ताक्षर सत्यापन |
| विनिमय कोटेशन/खरीद/बिक्री | ✓ | ✓ | ✓ |
| विनिमय मार्जिन आय | ✓ | ✓ | ✓ |
| निकासी आवेदन | ✓ | ✓ | ✓ |
| वैश्विक निकासी स्विच | ✓ | ✓ | ✓ |
| निकासी समीक्षा | ✓ मैन्युअल | ✓ मैन्युअल | ✓ बैच + मैन्युअल |
| KYC स्तरीय सीमाएँ | - | ✓ 3 स्तर | ✓ |
| निकासी शुल्क | - | - | ✓ |
| PDF रसीद | - | - | ✓ |

---

## गेम प्रबंधन

| कार्यात्मकता | मूल संस्करण | मानक संस्करण | पूर्ण संस्करण |
|------|--------|--------|--------|
| गेम CRUD | ✓ | ✓ | ✓ |
| गेम मुद्रा प्रबंधन | ✓ | ✓ | ✓ |
| C-छोर गेम सूची/विवरण | ✓ | ✓ | ✓ |
| गेम लॉन्च | ✓ | ✓ | ✓ |
| गेम श्रेणियाँ (10 प्रकार) | - | - | ✓ |
| श्रेणी फ़िल्टरिंग | - | - | ✓ |
| गेम सर्वर प्रबंधन | - | ✓ | ✓ |
| गेम रिकॉर्ड ट्रैकिंग | - | ✓ | ✓ |
| ES पूर्ण-पाठ खोज | - | - | ✓ |
| खोज सुझाव | - | - | ✓ |
| थर्ड-पार्टी गेम Provider SDK | - | - | ✓ HMAC-SHA256 |

---

## संचालन उपकरण

| कार्यात्मकता | मूल संस्करण | मानक संस्करण | पूर्ण संस्करण |
|------|--------|--------|--------|
| घोषणा प्रबंधन | ✓ | ✓ | ✓ |
| डैशबोर्ड | ✓ प्रशासन कंसोल | ✓ प्रशासन कंसोल | ✓ प्रशासन + प्लेटफ़ॉर्म |
| Excel निर्यात | ✓ | ✓ | ✓ |
| PDF निर्यात | ✓ | ✓ | ✓ |
| डैशबोर्ड वास्तविक चार्ट | - | - | ✓ fl_chart |
| कूपन प्रणाली | - | - | ✓ |
| लीडरबोर्ड (दैनिक/साप्ताहिक/मासिक/कुल) | - | - | ✓ Redis कैश |
| WebSocket रीयल-टाइम लीडरबोर्ड | - | - | ✓ पोर्ट 8789 |
| सूचना प्रणाली (साइट + ईमेल) | - | - | ✓ |
| रेफरल कमीशन | - | - | ✓ |
| दैनिक सांख्यिकी स्नैपशॉट | - | ✓ | ✓ |
| प्लेटफ़ॉर्म आय ट्रैकिंग | - | - | ✓ |

---

## सुरक्षा अनुपालन

| कार्यात्मकता | मूल संस्करण | मानक संस्करण | पूर्ण संस्करण |
|------|--------|--------|--------|
| 18-परत गहन रक्षा | ✓ | ✓ | ✓ |
| RBAC अनुमति नियंत्रण | ✓ | ✓ | ✓ |
| संचालन ऑडिट लॉग | ✓ | ✓ | ✓ |
| 8-प्लेटफ़ॉर्म स्रोत डिटेक्शन | ✓ | ✓ | ✓ |
| Redis स्लाइडिंग-विंडो दर सीमा | ✓ | ✓ | ✓ |
| KYC सत्यापन | - | ✓ | ✓ |
| जोखिम नियंत्रण इंजन (4 नियम) | - | ✓ | ✓ |
| भुगतान कॉलबैक हस्ताक्षर सत्यापन | - | - | ✓ |

---

## अंतर्राष्ट्रीयकरण

| कार्यात्मकता | मूल संस्करण | मानक संस्करण | पूर्ण संस्करण |
|------|--------|--------|--------|
| बहुभाषा समर्थन | चीनी/अंग्रेज़ी | 4 भाषाएँ | 4 भाषाएँ |
| अनुवाद तालिका + कैश | ✓ | ✓ | ✓ |
| भाषा स्वचालित डिटेक्शन | ✓ | ✓ | ✓ |
| देश-विभेदित कॉन्फ़िगरेशन | - | - | ✓ 8 देश |

---

## परिनियोजन और संचालन

| कार्यात्मकता | मूल संस्करण | मानक संस्करण | पूर्ण संस्करण |
|------|--------|--------|--------|
| webman स्वतंत्र परिनियोजन | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ 7 सेवाएँ |
| Nginx रिवर्स प्रॉक्सी | - | - | ✓ |
| Crontab निर्धारित कार्य | - | ✓ | ✓ |
| Prometheus निगरानी | ✓ | ✓ | ✓ `/metrics` व्यावसायिक gauge + इवेंट counter |
| स्वास्थ्य जाँच | ✓ | ✓ | ✓ |
| hg/apidoc ऑनलाइन दस्तावेज़ | - | - | ✓ 41 कंट्रोलर |

---

## क्लाइंट

| कार्यात्मकता | मूल संस्करण | मानक संस्करण | पूर्ण संस्करण |
|------|--------|--------|--------|
| Flutter Web PC प्रशासन कंसोल | ✓ 5 पेज | ✓ 11 पेज | ✓ 15 पेज |
| Flutter Web PC उपयोगकर्ता प्लेटफ़ॉर्म | ✓ 5 पेज | ✓ 8 पेज | ✓ 10 पेज |
| HarmonyOS admin | - | ✓ लॉगिन + डैशबोर्ड | ✓ 8 पेज `admin/apps/harmonyos/` |
| HarmonyOS C-छोर | - | - | ✓ 5 पेज `apps/harmonyos/` |

---

## डेटाबेस तालिकाएँ

### मूल संस्करण (19 तालिकाएँ)
```
प्रशासन कंसोल (7): game_admin_user, game_admin_role, game_admin_permission,
               game_admin_user_role, game_admin_role_permission,
               game_operation_log, game_system_config

प्लेटफ़ॉर्म मुख्य (12): game_user, game_user_wallet, game_user_game_wallet,
               game_game, game_game_currency, game_deposit_order,
               game_withdraw_order, game_exchange_record, game_transaction,
               game_payment_method, game_announcement, game-platform_config
```

### मानक संस्करण में नई (10 तालिकाएँ)
```
game_user_identity, game_user_oauth, game_user_payment_account,
game_user_session, game_game_server, game_game_play_log,
game_withdraw_limit, game_risk_rule, game_risk_log, game_stat_daily
```

### पूर्ण संस्करण में नई (13 तालिकाएँ)
```
game_game_category, game_game_category_rel, game_leaderboard,
game_coupon, game_user_coupon, game_language, game_translation,
game_country_config, game-platform_revenue,
game_notification, game_referral, game_referral_reward, game_user_2fa
```

---

## API एंडपॉइंट

| मॉड्यूल | मूल संस्करण | मानक संस्करण | पूर्ण संस्करण |
|------|--------|--------|--------|
| प्रमाणीकरण | 3 | 3 | 7 (+OAuth×2 +2FA×5) |
| वॉलेट | 2 | 2 | 3 (+रिचार्ज कॉलबैक) |
| विनिमय | 4 | 4 | 4 |
| निकासी | 2 | 2 | 8 (+बैच+सीमा+समीक्षा) |
| गेम | 3 | 4 | 7 (+सर्वर+रिकॉर्ड+खोज) |
| उपयोगकर्ता | 2 | 2 | 7 (+KYC+GDPR+गोपनीयता) |
| प्रशासन कंसोल | 18 | 25 | 79 |
| संचालन उपकरण | - | - | 30 (+लीडरबोर्ड+कूपन+सूचना+रेफरल) |
| अंतर्राष्ट्रीयकरण | 2 | 2 | 4 (+देश कॉन्फ़िगरेशन) |
| **कुल** | **38** | **54** | **129** |

---

## पारिस्थितिकी विस्तार (v2.0) — नया

| कार्यात्मकता | विवरण |
|------|------|
| GameProvider एब्स्ट्रैक्शन परत | SelfProvider (DB ट्रांज़ैक्शन) + ThirdPartyProvider (HTTP+हस्ताक्षर) |
| Provider API गेटवे | balance/bet/settle/refund कॉलबैक + ProviderAuth मिडलवेयर |
| टिकट प्रणाली | C-छोर निर्माण/उत्तर + प्रशासन प्रसंस्करण/आवंटन/बंद |
| ईमेल सत्यापन | 6-अंकीय सत्यापन कोड, Redis 10 मिनट समाप्ति, 60 सेकंड पुनर्भेज सीमा |
| पुश सूचनाएँ | PushService (FCM/APNs/हुआवेई पुश) |
| VIP प्रणाली | 5 स्तर, अनुभव अंक संचय, स्वचालित उन्नयन, विनिमय छूट, निकासी राहत, विनिमय दर लाभ |
| उपलब्धि प्रणाली | 12 अंतर्निहित उपलब्धियाँ, इवेंट-संचालित डिटेक्शन, प्रगति ट्रैकिंग |
| मित्र प्रणाली | आवेदन/स्वीकार/अस्वीकार/हटाना/खोज |
| निजी संदेश/चैट | REST + WebSocket रीयल-टाइम संदेश (पोर्ट 8790) |
| इवेंट बस | Redis Pub/Sub; emit INCR `metrics:event_*`; उपभोक्ता प्रक्रिया `EventConsumer` लागू |
| फ़ीचर स्विच | FeatureFlag DB-आधारित; `inRollout`/`abTest` `feature.{name}_percent` पढ़ता है |
| Webhook | - | - | ✓ 7 प्रकार के इवेंट + Pub/Sub डिलीवरी |
| चैट | - | - | ✓ REST+WebSocket :8791 |
| टूर्नामेंट प्रणाली | - | - | ✓ FeatureFlag+tournament |
| कूपन शर्तें | - | - | ✓ min_deposit/first_user/game_id |
| बहु-स्तरीय कमीशन | - | - | ✓ द्वितीय-स्तरीय लाभ-विभाजन |
| SDK दस्तावेज़ | - | - | ✓ PHP/Go/Python |
| उन्नत विश्लेषण | प्रतिधारण/D1-D30, रूपांतरण फ़नल, ARPU/ARPPU |

### नई डेटा तालिकाएँ (10 तालिकाएँ)
```
game_ticket, game_ticket_reply, game_device_token,
game_vip_level, game_user_vip, game_exp_log,
game_achievement, game_user_achievement,
game_friend, game_message
```

### नए Provider API एंडपॉइंट (4)
```
POST /api/provider/balance  — शेष राशि क्वेरी
POST /api/provider/bet      — दांव सूचना
POST /api/provider/settle   — निपटान सूचना
POST /api/provider/refund   — रिफंड सूचना
```

### नए C-छोर API एंडपॉइंट (8)
```
POST /api/verify/send-email    — ईमेल सत्यापन कोड भेजें
POST /api/verify/confirm-email — ईमेल पुष्टि करें
GET  /api/ticket/list             — टिकट सूची
POST /api/ticket/create           — टिकट बनाएँ
GET  /api/ticket/{id}             — टिकट विवरण
POST /api/ticket/{id}/reply       — टिकट का उत्तर दें
GET  /api/user/vip-status         — VIP स्थिति
GET  /api/user/achievements       — उपलब्धियाँ सूची
```

### नए प्रशासन कंसोल API एंडपॉइंट (6)
```
GET  /admin/ticket/list          — टिकट सूची
GET  /admin/ticket/{id}          — टिकट विवरण
POST /admin/ticket/{id}/reply    — टिकट का उत्तर दें
POST /admin/ticket/{id}/close    — टिकट बंद करें
POST /admin/ticket/{id}/assign   — प्रसंस्करणकर्ता नियुक्त करें
GET  /admin/analytics/retention  — प्रतिधारण विश्लेषण
GET  /admin/analytics/funnel     — रूपांतरण फ़नल
GET  /admin/analytics/arpu       — ARPU प्रवृत्ति
GET  /admin/analytics/economy    — आर्थिक मीट्रिक
```
