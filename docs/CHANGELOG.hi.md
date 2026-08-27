# Changelog
<!-- lang-nav -->

Languages: [中文](CHANGELOG.md) · [English](CHANGELOG.en.md) · [한국어](CHANGELOG.ko.md) · [Русский](CHANGELOG.ru.md) · [Deutsch](CHANGELOG.de.md) · [Français](CHANGELOG.fr.md) · [Español](CHANGELOG.es.md) · [Português](CHANGELOG.pt.md) · **हिन्दी** · [العربية](CHANGELOG.ar.md) · [বাংলা](CHANGELOG.bn.md) · [Bahasa Indonesia](CHANGELOG.id.md) · [日本語](CHANGELOG.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

मानव-पठनीय परिवर्तन रिकॉर्ड। PHP इस फ़ाइल को import नहीं करता। PROJECT-PLAN P2-21 के अनुरूप।

## [1.1] — 2026-08-07

- Redis प्लगइन एकीकरण, विश्लेषण सेवा, Redis डिग्रेडेशन, परीक्षण सुधार।

## [1.1] security / ops — 2026-08-18

### सुरक्षा

- भुगतान कॉलबैक: provider श्वेतसूची (stripe/paypal), fail-closed हस्ताक्षर सत्यापन, राशि मिलान, जमा लेनदेनित, Stripe टाइमस्टैम्प ±300s रीप्ले सुरक्षा।
- JWT: `JWT_SECRET_KEY` / `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY` अनुपस्थित या डिफ़ॉल्ट मान होने पर स्टार्टअप अस्वीकार।
- Apple id_token: JWKS (RS256) हस्ताक्षर सत्यापन + aud/iss/exp।
- Webhook: केवल https सार्वजनिक URL, आंतरिक/आरक्षित पते अस्वीकार (SSRF)।
- 2FA: TOTP HMAC RFC 4648 Base32 से डिकोड की गई कुंजी का उपयोग करता है; `/api/2fa/verify` प्रति-उपयोगकर्ता विफलता लॉक (5 बार / 15 मिनट, Redis विफलता पर fail-closed)।
- निकासी: समीक्षा/भुगतान शर्तों पर UPDATE स्थिति परमाणु फ्लिप; वैकल्पिक दोहरी समीक्षा (`withdraw.require_dual_review`); आवेदन पक्ष पर Redis उपयोगकर्ता लॉक सीमा समवर्ती उल्लंघन रोकता है।
- दर सीमा: Redis विफलता पर fail-closed।

### उपलब्धता

- admin विश्लेषण सेवा की 12 `/admin/analytics/*` रूट माउंट।
- मॉडलों से हार्ड-कोडेड `game_` उपसर्ग हटाया गया; DepositLog ऑडिट डेटाबेस में; Test मॉडल हटाया गया।

### अवलोकनीयता

- `GET /metrics` में लंबित समीक्षा निकासी, आज की पुष्टि रिचार्ज (COUNT क्वेरी Redis 30s कैश), इवेंट emit/consume काउंट, `memory_usage`, `info version=1.1` जोड़े।
- FeatureFlag: `inRollout` / `abTest` crc32 बकेटिंग द्वारा `feature.{name}_percent` पढ़ता है।
- EventBus `emit` / `consume` Redis `metrics:event_emit_total` / `metrics:event_consume_total` पर INCR करता है।

### क्लाइंट / साझा परत (उसी दिन पूर्ण)

- Flutter Platform: `app_pages.dart` रूट तालिका; 2FA सेटअप/सत्यापन, कूपन, लीडरबोर्ड, अधिसूचना, OAuth कॉलबैक पेज जोड़े; लॉबी प्रवेश नेविगेशन से जुड़ा।
- HarmonyOS C-छोर: `apps/harmonyos/` पाँच पेज (लॉगिन/लॉबी/विवरण/वॉलेट/व्यक्तिगत), डिफ़ॉल्ट `BASE_URL` service `8788` को इंगित करता है।
- साझा परत: `packages/platform-common` (`erik/platform-common` path repo) से DepositLog / GameDashboard / Probability / GamePlayLog निकाले गए; मॉडल अभी भी दो प्रतियों में।
- ClickHouse: composer निर्भरता हटाई गई; विश्लेषण MySQL वास्तविक समय एकत्रीकरण पर जारी।
- CI: admin / service अलग-अलग job में phpunit चलाते हैं, विफलता पर रुकावट।

### अभी भी शेष अंतराल

- admin/service **मॉडल** अभी भी दो प्रतियों में (केवल कुछ `common/service` path पैकेज में)।
- `webman/queue` जुड़ा नहीं; प्रायिकता/प्रतिधारण OLAP में स्थानांतरित नहीं।
- PROJECT-PLAN / VERSIONS / ऑडिट रिपोर्ट के कुछ अनुच्छेद इस CHANGELOG से पीछे रह सकते हैं; इस फ़ाइल और डिस्क को प्रामाणिक मानें।

## [1.1] resilience — 2026-08-27

### स्थिरता

- साझा परत में `CircuitBreaker` (Redis में स्थिति, सीमा 5 / विंडो 30s, Redis अनुपलब्ध होने पर fail-open) और `Retry` (घातीय बैकऑफ़, केवल नेटवर्क अपवाद, अधिकतम 5 प्रयास) जोड़े गए, `packages/platform-common/src/` में।
- डिग्रेडेशन स्विच `feature.provider_mock`: PushService (FCM/APNs/HarmonyOS), PayoutService (PayPal), ThirdPartyProvider `on` होने पर शॉर्ट-सर्किट करते हैं, वास्तविक नेटवर्क कॉल छोड़ते हैं।
- `getenv($name, '')` के 11 टाइप दोष ठीक किए (strict_types में TypeError); PushService mock जाँच try/catch में स्थानांतरित।
- नए परीक्षण: CircuitBreakerTest / RetryTest / ResilienceMockTest; service सूट 45 → 60 मामले, सभी पास (रिपोर्ट: [test-reports/resilience.md](test-reports/resilience.md))।
