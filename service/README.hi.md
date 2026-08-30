# service/ — C-साइड यूज़र प्लेटफ़ॉर्म API सेवा
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · **हिन्दी** · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

C-साइड यूज़र प्लेटफ़ॉर्म API सेवा, webman v2 (Workerman) पर आधारित एक उच्च-प्रदर्शन PHP बैकएंड है जो उपयोगकर्ताओं को गेम एग्रीगेशन प्लेटफ़ॉर्म की पूरी क्षमता प्रदान करती है: पंजीकरण और लॉगिन, वॉलेट, जमा, निकासी, विनिमय, गेम, लीडरबोर्ड, कूपन, सपोर्ट टिकट, VIP, उपलब्धियाँ, सोशल सुविधाएँ और घोषणाएँ।

## सुविधा सूची

| मॉड्यूल | विवरण |
|------|------|
| उपयोगकर्ता | पंजीकरण/लॉगिन (यूज़रनेम+पासवर्ड + 7 प्लेटफ़ॉर्म OAuth + 2FA TOTP), प्रोफ़ाइल |
| वॉलेट | प्लेटफ़ॉर्म कॉइन वॉलेट (ऑप्टिमिस्टिक लॉक) + गेम कॉइन वॉलेट + लेन-देन इतिहास |
| जमा | 13 पेमेंट गेटवे (Stripe/PayPal/NowPayments/Coinbase आदि) कॉलबैक हस्ताक्षर सत्यापन और स्वचालित क्रेडिट |
| निकासी | आवेदन → समीक्षा → भुगतान, KYC स्तरीय सीमाएँ |
| विनिमय | प्लेटफ़ॉर्म कॉइन ⇄ गेम कॉइन रीयल-टाइम कोटेशन, VIP छूट और दर बोनस |
| गेम | गेम सूची/श्रेणियाँ/खोज, खेल रिकॉर्ड, Provider सेटलमेंट कॉलबैक |
| लीडरबोर्ड | दैनिक/साप्ताहिक/मासिक/सर्वकालिक + WebSocket रीयल-टाइम पुश |
| कूपन | निश्चित राशि + प्रतिशत छूट, समय और मात्रा सीमित |
| टिकट | उपयोगकर्ता द्वारा सपोर्ट टिकट बनाना/उत्तर |
| VIP | 5-स्तरीय रॉयल्टी, अनुभव संचय, विनिमय छूट |
| उपलब्धियाँ | 12 अंतर्निहित उपलब्धियाँ, ईवेंट-संचालित पहचान |
| सोशल | मित्र प्रणाली + WebSocket रीयल-टाइम संदेश |
| घोषणाएँ | ऐप में घोषणाएँ + सूचनाएँ/ईमेल |

## तकनीकी स्टैक

- PHP 8.3+ / webman v2 (workerman/webman)
- MySQL 8.0+ (टेबल उपसर्ग `game_`, BIGINT गैर-ऑटोइन्क्रीमेंट प्राथमिक कुंजियाँ)
- Redis (सत्र / कैश / दर सीमा)
- ClickHouse (OLAP विश्लेषण / संभाव्यता गणना)
- Elasticsearch (पूर्ण-पाठ खोज)
- JWT प्रमाणीकरण + HMAC-SHA256 Provider हस्ताक्षर

## प्रोजेक्ट संरचना

```
service/
├── app/
│   ├── api/v1/controller/  # C-साइड API नियंत्रक (35)
│   ├── middleware/         # मिडलवेयर (Cors/SecurityFilter/RateLimit/ApiVersion/UserAuth/ProviderAuth)
│   ├── model/              # डेटा मॉडल
│   ├── service/            # व्यावसायिक सेवाएँ (VIP/लीडरबोर्ड/जोखिम/सूचनाएँ आदि)
│   ├── event/              # इवेंट बस (EventBus Redis Pub/Sub)
│   ├── provider/           # गेम Provider परत
│   └── payment/            # पेमेंट गेटवे
├── common/                 # साझा सेवा निर्देशिका (erik/platform-common पैकेज में कार्यान्वित)
├── config/                 # कॉन्फ़िगरेशन फ़ाइलें
├── public/                 # वेब प्रवेश
├── tests/                  # PHPUnit परीक्षण
├── start.php               # स्टार्टअप प्रवेश
└── composer.json
```

## वन-क्लिक इंस्टॉलेशन

प्रोजेक्ट रूट के वन-क्लिक इंस्टॉलेशन विज़ार्ड का उपयोग करें (प्रोजेक्ट रूट से चलाएँ):

```bash
# 1. इंस्टॉलेशन विज़ार्ड शुरू करें
php -S 0.0.0.0:8888 -t install/

# 2. ब्राउज़र में http://localhost:8888 खोलें
#    विज़ार्ड का पालन करें: पर्यावरण जाँच → डेटाबेस कॉन्फ़िग → व्यवस्थापक खाता → स्वचालित इंस्टॉल
```

या Docker Compose से सब कुछ शुरू करें (प्रोजेक्ट रूट):

```bash
docker compose up -d
```

## मैनुअल इंस्टॉलेशन

```bash
# 1. निर्भरताएँ स्थापित करें
cd service && composer install

# 2. पर्यावरण चर कॉन्फ़िगर करें
cp .env.example .env
# .env संपादित करें: डेटाबेस कनेक्शन, JWT कुंजियाँ आदि

# 3. सेवा शुरू करें (डिफ़ॉल्ट पोर्ट 8788)
php start.php start        # फोरग्राउंड
php start.php start -d     # बैकग्राउंड (डेमॉन)
```

## उपयोग

- API दस्तावेज़: `docs/API.md` (पूर्ण API संदर्भ)
- ऑनलाइन दस्तावेज़: http://localhost:8788/apidoc/ (hg/apidoc इंटरैक्टिव दस्तावेज़)
- स्वास्थ्य जाँच: `GET http://localhost:8788/health`
- C-साइड फ्रंटएंड: `apps/flutter/platform/` (Flutter Web यूज़र प्लेटफ़ॉर्म)
- व्यवस्थापक बैकएंड: `admin/` (व्यवस्थापक बैकएंड और `admin/apps/flutter/` फ्रंटएंड)

## परीक्षण

```bash
cd service
SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
```
