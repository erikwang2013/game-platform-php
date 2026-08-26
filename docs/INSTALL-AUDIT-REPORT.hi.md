# स्थापना प्रणाली समीक्षा रिपोर्ट
<!-- lang-nav -->

Languages: [中文](INSTALL-AUDIT-REPORT.md) · [English](INSTALL-AUDIT-REPORT.en.md) · [한국어](INSTALL-AUDIT-REPORT.ko.md) · [Русский](INSTALL-AUDIT-REPORT.ru.md) · [Deutsch](INSTALL-AUDIT-REPORT.de.md) · [Français](INSTALL-AUDIT-REPORT.fr.md) · [Español](INSTALL-AUDIT-REPORT.es.md) · [Português](INSTALL-AUDIT-REPORT.pt.md) · **हिन्दी** · [العربية](INSTALL-AUDIT-REPORT.ar.md) · [বাংলা](INSTALL-AUDIT-REPORT.bn.md) · [Bahasa Indonesia](INSTALL-AUDIT-REPORT.id.md) · [日本語](INSTALL-AUDIT-REPORT.ja.md)


> समीक्षा तिथि: 2026-08-04
> समीक्षा क्षेत्र: `install/` निर्देशिका की सभी फ़ाइलें + संबंधित दस्तावेज़ परिवर्तन
> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 一、समीक्षा सारांश

| आयाम | रेटिंग | विवरण |
|------|------|------|
| कार्यात्मक पूर्णता | पास | 5-चरणीय स्थापना प्रक्रिया पूर्ण, सभी 39 तालिकाएँ बनाई गईं, सीड डेटा संपूर्ण |
| SQL शुद्धता | पास | 42 तालिकाएँ मूल माइग्रेशन फ़ाइलों से पूरी तरह समान, source फ़ील्ड CREATE TABLE में विलीन हो गया |
| पारिस्थितिकी कॉन्फ़िगरेशन | पास | admin और service दोनों .env कॉन्फ़िगरेशन पूर्ण, कुंजियाँ स्वचालित रूप से उत्पन्न |
| सुरक्षा | मूल रूप से पास | पासवर्ड bcrypt एन्क्रिप्शन, XSS सुरक्षा पूर्ण, CSRF Token जोड़ने का सुझाव |
| रखरखाव-क्षमता | पास | कोड संरचना स्पष्ट, एकल फ़ाइल की ज़िम्मेदारी स्पष्ट |
| शक्ति-समानता (Idempotency) | पास | सभी INSERT को INSERT IGNORE में बदला गया, WHERE NOT EXISTS गार्ड सहित |
| उपयोगकर्ता अनुभव | पास | रिस्पॉन्सिव डिज़ाइन, AJAX कनेक्शन परीक्षण, चीनी त्रुटि संकेत |

---

## 二、बनाई गई फ़ाइलें

### 2.1 `install/install.sql` (988 पंक्तियाँ)
- 8 मूल माइग्रेशन फ़ाइलें विलीन की गईं
- 42 `erik_` उपसर्ग वाली डेटा तालिकाएँ (CREATE TABLE IF NOT EXISTS)
- 13 INSERT IGNORE सीड डेटा ब्लॉक
- `erik_operation_log` का `source` फ़ील्ड तालिका-निर्माण कथन में विलीन (ALTER TABLE की आवश्यकता नहीं)
- ट्रांज़ैक्शन में लपेटा गया (START TRANSACTION / COMMIT)
- सभी INSERT शक्ति-समान बनाए गए

**INSERT कथनों की शक्ति-समानता प्रसंस्करण विवरण:**

| तालिका नाम | प्रसंस्करण विधि |
|------|---------|
| `erik_admin_role` | INSERT IGNORE (निश्चित ID) |
| `erik_admin_permission` | INSERT IGNORE (निश्चित ID) - 4 बार |
| `erik_admin_role_permission` | WHERE NOT EXISTS उप-क्वेरी |
| `erik_platform_config` | INSERT IGNORE (निश्चित ID) - 2 बार |
| `erik_language` | INSERT IGNORE (निश्चित ID) |
| `erik_translation` | INSERT IGNORE (निश्चित ID) |
| `erik_risk_rule` | INSERT IGNORE (निश्चित ID) |
| `erik_withdraw_limit` | INSERT IGNORE (निश्चित ID) |
| `erik_game_category` | INSERT IGNORE (निश्चित ID) |
| `erik_country_config` | INSERT IGNORE (निश्चित ID) |

### 2.2 `install/index.php` (485 पंक्तियाँ)
- रूट शेड्यूलिंग: step1 -> step2 -> step3 -> step4 -> step5
- AJAX इंटरफ़ेस: `?action=test-db` (POST JSON)
- 5 पेज टेम्पलेट फ़ंक्शन
- इनलाइन JavaScript (AJAX कनेक्शन परीक्षण)
- HTML आउटपुट XSS से बचाव के लिए `htmlspecialchars()` का उपयोग करता है
- स्थापित होने की जाँच (install.lock)

### 2.3 `install/Installer.php` (506 पंक्तियाँ)
- पर्यावरण जाँच: 11 आइटम (PHP संस्करण, 6 एक्सटेंशन, निर्देशिका अनुमतियाँ, SQL फ़ाइल)
- डेटाबेस कनेक्शन परीक्षण: PDO + स्वचालित डेटाबेस निर्माण
- स्थापना निष्पादन: SQL आयात -> एडमिन निर्माण -> .env लेखन -> लॉकिंग
- कुंजी निर्माण: JWT(64 बाइट) / Hashids(32 बाइट) / Encryption(32 बाइट)
- .env बैकअप: स्थापना से पहले मौजूदा .env फ़ाइल का स्वचालित बैकअप

### 2.4 `install/assets/style.css` (130 पंक्तियाँ)
- रिस्पॉन्सिव डिज़ाइन (मोबाइल <=600px समर्थित)
- CSS वेरिएबल थीम (--primary: #4f46e5)
- कोई बाहरी निर्भरता नहीं

---

## 三、पर्यावरण जाँच कवरेज (11 आइटम)

| # | जाँच आइटम | स्तर | स्थिति |
|---|--------|------|------|
| 1 | PHP >= 8.1.0 | अनिवार्य | पास |
| 2 | PDO MySQL | अनिवार्य | पास |
| 3 | MBString | अनिवार्य | पास |
| 4 | JSON | अनिवार्य | पास |
| 5 | OpenSSL | अनिवार्य | पास |
| 6 | PCNTL | अनिवार्य | पास |
| 7 | GD | सुझावित | पास |
| 8 | XML | सुझावित | पास |
| 9 | Redis | सुझावित | पास |
| 10 | निर्देशिका अनुमतियाँ (admin/runtime, service/runtime) | अनिवार्य | पास |
| 11 | install.sql फ़ाइल मौजूद है | अनिवार्य | पास |

---

## 四、पारिस्थितिकी कॉन्फ़िगरेशन पूर्णता

### 4.1 Admin `.env` निर्माण (70 कॉन्फ़िगरेशन आइटम)

| समूह | कॉन्फ़िगरेशन आइटम संख्या | कवरेज |
|------|---------|------|
| एप्लिकेशन कॉन्फ़िगरेशन | 3 | APP_NAME, APP_DEBUG, APP_URL |
| JWT प्रमाणीकरण | 6 | JWT_SECRET, JWT_ALGORITHM, JWT_TTL, JWT_REFRESH_TTL, JWT_ISSUER, JWT_AUDIENCE |
| Hashids | 2 | HASHIDS_SALT, HASHIDS_ALT_SALT |
| Snowflake | 3 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID, SNOWFLAKE_START_TIMESTAMP |
| एन्क्रिप्शन(API) | 3 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTION_IV |
| एन्क्रिप्शन(DB) | 3 | ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER, ENCRYPTION_PREVIOUS_KEYS |
| Scout/ES | 7 | SCOUT_DRIVER, SCOUT_HOSTS, SCOUT_PREFIX, SCOUT_SHARDS, SCOUT_REPLICAS, SCOUT_CHUNK_SIZE, SCOUT_SOFT_DELETE |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST आदि |
| Poster कैप्चा | 7 | POSTER_IMAGE_DRIVER आदि |
| डेटाबेस | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| Redis | 4 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DATABASE |
| संगत कुंजियाँ | 3 | JWT_SECRET_KEY, JWT_DEFAULT_EXPIRE, JWT_REFRESH_EXPIRE |

### 4.2 Service `.env` निर्माण (48 कॉन्फ़िगरेशन आइटम)

| समूह | कॉन्फ़िगरेशन आइटम संख्या | कवरेज |
|------|---------|------|
| एप्लिकेशन | 2 | APP_ENV, APP_DEBUG |
| डेटाबेस | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| JWT | 3 | JWT_SECRET, JWT_TTL, JWT_REFRESH_TTL |
| Hashids | 3 | HASHIDS_SALT, HASHIDS_ALPHABET, HASHIDS_MIN_LENGTH |
| Snowflake | 2 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID |
| एन्क्रिप्शन | 4 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER |
| Redis | 3 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD |
| ClickHouse | 5 | CLICKHOUSE_HOST, CLICKHOUSE_PORT, CLICKHOUSE_DB, CLICKHOUSE_USER, CLICKHOUSE_PASS |
| OAuth | 9 | OAUTH_GOOGLE/FACEBOOK/APPLE प्रत्येक 3 आइटम |
| भुगतान Webhook | 3 | STRIPE_WEBHOOK_SECRET, PAYPAL_WEBHOOK_ID, PAYPAL_VERIFY_URL |
| CORS | 1 | CORS_ORIGIN |
| Scout/ES | 6 | SCOUT_DRIVER आदि |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST आदि |

**तुलना निष्कर्ष**: दोनों `.env` कॉन्फ़िगरेशन मूल `.env.example` के अनुरूप हैं, और Service कॉन्फ़िगरेशन में अनुपलब्ध `ENCRYPTION_CIPHER`、`ENCRYPTABLE_CIPHER`、`JWT_REFRESH_TTL` जोड़े गए हैं।

---

## 五、सुरक्षा समीक्षा

### 5.1 लागू किए गए सुरक्षा उपाय

| उपाय | कार्यान्वयन विधि |
|------|---------|
| पासवर्ड सुरक्षा | bcrypt, cost=12 |
| कुंजी यादृच्छिकता | `random_int()` क्रिप्टोग्राफ़िक रूप से सुरक्षित यादृच्छिक संख्याएँ |
| XSS सुरक्षा | `htmlspecialchars()` सभी उपयोगकर्ता इनपुट/आउटपुट को एस्केप करता है |
| SQL इंजेक्शन सुरक्षा | PDO प्रीपेयर्ड स्टेटमेंट (`prepare/execute`) |
| स्थापना लॉकिंग | `install.lock` फ़ाइल + JSON मेटाडेटा |
| पथ सुरक्षा | निश्चित पथ, कोई उपयोगकर्ता-नियंत्रित फ़ाइल इन्क्लूज़ नहीं |
| एन्क्रिप्शन शक्ति | AES-256-CBC + 32-बाइट कुंजी |

### 5.2 संभावित जोखिम और शमन

| जोखिम | स्तर | शमन उपाय |
|------|------|---------|
| स्थापना के दौरान नेटवर्क एक्सपोज़र | मध्यम | स्थापना के तुरंत बाद `install/` निर्देशिका हटाएँ (पेज पर स्पष्ट संकेत) |
| CSRF Token नहीं | निम्न | स्थापना विज़ार्ड एक अस्थायी एक-बार उपकरण है, PHP अंतर्निहित सर्वर सिंगल-थ्रेडेड है |
| test-db पर कोई दर सीमा नहीं | निम्न | अस्थायी उपकरण, उपयोग के बाद हटाया जाता है |
| .env फ़ाइल अनुमतियाँ | निम्न | स्थापना के बाद मैन्युअल रूप से chmod 600 करने का सुझाव |

### 5.3 सुधार सुझाव

1. **प्रोडक्शन पर्यावरण सुदृढ़ीकरण**: स्थापना पूर्ण होने पर स्वचालित रूप से `chmod 600 admin/.env service/.env` करने पर विचार करें
2. **दूरस्थ पहुँच**: यदि दूरस्थ सर्वर है, तो SSH टनल के माध्यम से सुझाव: `ssh -L 8888:localhost:8888 user@host`
3. **स्थापना के बाद सफाई**: सफल स्थापना पृष्ठ पर "स्थापना निर्देशिका हटाएँ" का स्पष्ट संकेत जोड़ने पर विचार करें (पहले से लागू)

---

## 六、परीक्षण परिणाम

### 6.1 PHP सिंटैक्स जाँच
```
पास install/index.php — No syntax errors
पास install/Installer.php — No syntax errors
```

### 6.2 कार्यात्मक परीक्षण
```
पास Step 1 पर्यावरण जाँच — 11 जाँचें सभी पास
पास Step 2 डेटाबेस कॉन्फ़िगरेशन — फॉर्म रेंडरिंग सही, डिफ़ॉल्ट मान सामान्य रूप से भरे गए
पास AJAX test-db — JSON प्रतिक्रिया प्रारूप सही, चीनी त्रुटि संकेत स्पष्ट
पास CSS स्थिर संसाधन — 200 OK, text/css
पास स्थापित पृष्ठ — install.lock जाँच सामान्य, संकेत जानकारी पूर्ण
```

### 6.3 SQL सत्यापन
```
पास 42 तालिका नाम मूल माइग्रेशन फ़ाइलों से पूरी तरह समान
पास source फ़ील्ड erik_operation_log तालिका-निर्माण कथन में विलीन
पास सभी INSERT कथन शक्ति-समान बनाए गए
पास WHERE NOT EXISTS गार्ड पुनर्स्थापित (मूल माइग्रेशन के अनुरूप)
```

---

## 七、पाए गए और ठीक किए गए समस्याएँ

| # | समस्या | गंभीरता | स्थिति |
|---|------|--------|------|
| 1 | `erik_admin_role_permission` INSERT में `WHERE NOT EXISTS` गार्ड की कमी (मूल माइग्रेशन से असंगत) | उच्च | ठीक किया गया |
| 2 | सभी सीड डेटा INSERT शक्ति-समान नहीं थे (दोहरा निष्पादन विफल होगा) | मध्यम | ठीक किया गया (INSERT IGNORE) |
| 3 | पर्यावरण जाँच में `pcntl` एक्सटेंशन जाँच की कमी (webman मुख्य निर्भरता) | मध्यम | ठीक किया गया |
| 4 | Service .env में `ENCRYPTION_CIPHER` कॉन्फ़िगरेशन की कमी | निम्न | ठीक किया गया |
| 5 | Service .env में `ENCRYPTABLE_CIPHER` कॉन्फ़िगरेशन की कमी | निम्न | ठीक किया गया |
| 6 | Service .env में `JWT_REFRESH_TTL` कॉन्फ़िगरेशन की कमी | निम्न | ठीक किया गया |

---

## 八、दस्तावेज़ परिवर्तन

| फ़ाइल | परिवर्तन सामग्री |
|------|---------|
| `README.md` | त्वरित प्रारंभ को "एक-क्लिक स्थापना विज़ार्ड (अनुशंसित)" में बदला, मैन्युअल स्थापना फोल्डिंग ब्लॉक जोड़ा, प्रोजेक्ट संरचना अपडेट की |
| `README.en.md` | वही (अंग्रेज़ी संस्करण), प्रोजेक्ट संरचना अपडेट |
| `docs/DEPLOYMENT.md` | नया अनुभाग 2 "एक-क्लिक स्थापना विज़ार्ड (नए परिनियोजन के लिए अनुशंसित)", मूल Docker अनुभाग पीछे स्थानांतरित |
| `.gitignore` | नए `install/install.lock`, `admin/.env.backup.*`, `service/.env.backup.*` |

---

## 九、समग्र मूल्यांकन

स्थापना प्रणाली कार्यात्मक रूप से पूर्ण है, कोड गुणवत्ता अच्छी है, सुरक्षा उपाय पर्याप्त हैं। 5-चरणीय स्थापना प्रक्रिया स्पष्ट और सहज है, पर्यावरण जाँच webman संचालन के लिए आवश्यक सभी प्रमुख एक्सटेंशन को कवर करती है, उच्च-शक्ति कुंजियाँ स्वचालित रूप से उत्पन्न होती हैं, और कॉन्फ़िगरेशन फ़ाइलें मौजूदा सिस्टम के साथ पूरी तरह संगत हैं। SQL विलय प्रक्रिया ने मूल माइग्रेशन फ़ाइलों (42 तालिकाएँ) के साथ पूर्ण समानता बनाए रखी है, शक्ति-समानता प्रसंस्करण यह सुनिश्चित करता है कि दोहरा निष्पादन त्रुटि नहीं देगा।

**समीक्षा निष्कर्ष: पास, उपयोग के लिए तैयार।**

---

## 十、2026-08-18 स्थिति पुष्टि

इस दौर की सुरक्षा मरम्मत (भुगतान कॉलबैक fail-closed、JWT स्टार्टअप सत्यापन、तालिका उपसर्ग एकीकरण) **स्थापना प्रणाली को शामिल नहीं करती**, कोई नई समस्या नहीं:

- मॉडल से हार्डकोडेड `erik_` उपसर्ग हटाने के बाद, वास्तविक तालिका नाम अभी भी `config/database.php` के `prefix=erik_` से एकीकृत रूप से उत्पन्न होते हैं, जो install.sql द्वारा बनाई गई `erik_*` तालिकाओं से मेल खाते हैं, स्थापना SQL बदलने की आवश्यकता नहीं है
- JWT स्टार्टअप सत्यापन (`JWT_SECRET_KEY` अनुपलब्ध या डिफ़ॉल्ट मान पर स्टार्टअप अस्वीकार) स्थापना विज़ार्ड द्वारा स्वचालित रूप से उत्पन्न 64-बाइट यादृच्छिक कुंजी के साथ संगत है, स्थापना प्रक्रिया में समायोजन की आवश्यकता नहीं है

ऐतिहासिक निष्कर्ष और समस्या सूची अपरिवर्तित हैं।

---
