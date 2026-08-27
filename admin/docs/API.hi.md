# API संदर्भ दस्तावेज़
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · **हिन्दी** · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. अवलोकन

开放管理后台 (open-admin) webman v2 पर आधारित है और RESTful JSON API प्रदान करता है। सभी एडमिन इंटरफ़ेस के लिए JWT प्रमाणीकरण और RBAC अनुमति सत्यापन आवश्यक है; सार्वजनिक इंटरफ़ेस API संस्करण हेडर द्वारा वर्ज़न किए गए कंट्रोलर में रूट होते हैं।

- **बेस URL**: `http://localhost:8787`
- **API संस्करण**: अनुरोध हेडर `API-Version: v1` से नियंत्रित (अनुपस्थित पर डिफ़ॉल्ट v1)

> **एंडपॉइंट अवलोकन**: प्रमाणीकरण(5) | डैशबोर्ड(1) | उपयोगकर्ता(7) | भूमिका(4) | अनुमति(4) | कॉन्फ़िगरेशन(4) | लॉग(1) | प्रोफ़ाइल(3) | आयात-निर्यात(3) | अपलोड(1) | संचालन(4: health/metrics/docs/security.txt) | कुल 37 एंडपॉइंट
- **प्रमाणीकरण**: `Authorization: Bearer <token>` (JWT)
- **प्रतिक्रिया प्रारूप**: `{ "code": 0, "message": "success", "data": {...} }`
- **दस्तावेज़ एंडपॉइंट**: `GET /api/docs` OpenAPI 3.0 JSON विनिर्देश लौटाता है

### अनुरोध आवश्यकताएँ

- केवल `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` विधियाँ अनुमत हैं, अन्य HTTP विधियाँ (जैसे TRACE, CONNECT, PATCH) 405 लौटाती हैं
- सभी `POST` / `PUT` अनुरोधों में `Content-Type: application/json` होना चाहिए (फ़ाइल अपलोड को छोड़कर), अन्यथा 415 लौटता है
- अनुरोध निकाय 10MB से अधिक नहीं होना चाहिए, अन्यथा 413 लौटता है
- सुरक्षा फ़िल्टर सभी अनुरोध इनपुट की XSS, SQL इंजेक्शन, पाथ ट्रैवर्सल, कमांड इंजेक्शन स्कैन करता है, हिट पर 403 लौटता है
- लगातार 5 असफल लॉगिन पर खाता लॉक (15 मिनट) ट्रिगर होता है, लॉक अवधि में लॉगिन अनुरोध 429 लौटाता है
- एक उपयोगकर्ता अधिकतम 3 सक्रिय Token रख सकता है, अधिक होने पर सबसे पुराना Token स्वचालित रूप से ब्लैकलिस्ट होता है

## 2. त्रुटि कोड

| code | अर्थ | ट्रिगर परिदृश्य |
|------|------|---------|
| 0 | सफल | |
| 400 | अनुरोध पैरामीटर त्रुटि | अनुरोध प्रारूप सही नहीं |
| 401 | प्रमाणित नहीं | Token अनुपस्थित / समाप्त / ब्लैकलिस्ट में |
| 403 | कोई अनुमति नहीं / सुरक्षा अवरोध | RBAC अनुमति अपर्याप्त / SecurityFilter हिट |
| 404 | संसाधन मौजूद नहीं | क्वेरी/अपडेट/डिलीट का लक्ष्य मौजूद नहीं |
| 405 | अनुरोध विधि अनुमत नहीं | केवल GET/POST/PUT/DELETE/OPTIONS/HEAD, गैर-मानक विधि सीधे अस्वीकार |
| 413 | अनुरोध निकाय बहुत बड़ा | Content-Length 10MB से अधिक |
| 415 | असमर्थित मीडिया प्रकार | POST/PUT अनुरोध में Content-Type JSON नहीं और फ़ाइल अपलोड नहीं |
| 422 | पैरामीटर सत्यापन विफल | अनिवार्य फ़ील्ड अनुपस्थित, प्रारूप अमान्य, व्यावसायिक सत्यापन असफल |
| 429 | अनुरोध बहुत बार-बार | RateLimit ट्रिगर / खाता लॉक (लगातार 5 असफल लॉगिन पर 15 मिनट लॉक) |
| 500 | सर्वर आंतरिक त्रुटि | |

## 3. सार्वजनिक एंडपॉइंट

सभी सार्वजनिक एंडपॉइंट `/api` समूह में माउंट हैं, `ApiVersion` मिडलवेयर `API-Version` हेडर के अनुसार वर्ज़न किए गए कंट्रोलर में वितरित करता है (जैसे `app\api\v1\controller\AuthController`)।

### 3.1 स्वास्थ्य जांच

```
GET /health
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **दर सीमा**: नहीं

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.1",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

`database`, `redis`, `elasticsearch` मान: `"ok"` | `"unavailable"`। ES अप्राप्य होने पर `elasticsearch` `"unavailable"` लौटाता है; क्लस्टर स्वास्थ्य स्थिति green/yellow नहीं होने पर वास्तविक status मान लौटता है (जैसे `"red"`)।

### 3.2 API दस्तावेज़

```
GET /api/docs
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **दर सीमा**: वैश्विक डिफ़ॉल्ट (60 बार/मिनट)
- **प्रतिक्रिया**: OpenAPI 3.0.3 JSON विनिर्देश, सभी एंडपॉइंट परिभाषाएँ, पैरामीटर और Schema शामिल

### 3.3 क्लिक कैप्चा उत्पन्न करें

```
POST /api/captcha/generate
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **अनुरोध हेडर**: `API-Version: v1` (अनिवार्य)
- **दर सीमा**: वैश्विक डिफ़ॉल्ट (60 बार/मिनट)

**अनुरोध निकाय**:
```json
{
  "difficulty": "medium"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| difficulty | string | नहीं | `easy` / `medium` / `hard`, डिफ़ॉल्ट `medium` |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| key | string | कैप्चा पहचानकर्ता, सत्यापन में वापस भेजा जाता है |
| image | string | base64 एन्कोडेड PNG छवि |
| extra.targets[].order | int | क्लिक क्रम |
| extra.targets[].text | string | क्लिक लक्ष्य संकेत टेक्स्ट |

### 3.4 क्लिक कैप्चा सत्यापित करें

```
POST /api/captcha/verify
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **अनुरोध हेडर**: `API-Version: v1` (अनिवार्य)
- **दर सीमा**: वैश्विक डिफ़ॉल्ट (60 बार/मिनट)

**अनुरोध निकाय**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| key | string | हाँ | कैप्चा key, generate द्वारा लौटाया गया |
| clicks | array{object} | हाँ | क्लिक निर्देशांक सरणी, प्रत्येक तत्व में `x` (int) और `y` (int) |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

सत्यापन विफल पर `code` 422 होता है, `message` `"验证失败，请重试"` होता है, `data.valid` `false` होता है।

### 3.5 लॉगिन

```
POST /api/auth/login
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **अनुरोध हेडर**: `API-Version: v1` (अनिवार्य)
- **दर सीमा**: 10 बार/मिनट (IP + पथ के अनुसार)

**अनुरोध निकाय**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| username | string | हाँ | min:3, max:50 | उपयोगकर्ता नाम |
| password | string | हाँ | min:6, max:32 | पासवर्ड |
| captcha_key | string | हाँ | | कैप्चा key |
| clicks | array{object} | हाँ | min:2 | क्लिक निर्देशांक सरणी |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| access_token | string | JWT एक्सेस टोकन |
| refresh_token | string | JWT रीफ़्रेश टोकन |
| expires_in | int | एक्सेस टोकन वैधता (सेकंड), डिफ़ॉल्ट 7200 |
| user.id | string | hashid एन्क्रिप्टेड उपयोगकर्ता ID |
| user.username | string | उपयोगकर्ता नाम |
| user.real_name | string | वास्तविक नाम |

**संभावित त्रुटियाँ**:
- 422: पैरामीटर सत्यापन विफल (अनिवार्य फ़ील्ड अनुपस्थित, प्रारूप अमान्य)
- 422: कैप्चा गलत, पुनः प्रयास करें
- 401: उपयोगकर्ता नाम या पासवर्ड गलत
- 403: खाता अक्षम किया गया है
- 429: खाता लॉक किया गया है, 15 मिनट बाद पुनः प्रयास करें (लगातार 5 असफल लॉगिन पर ट्रिगर)

### 3.6 पंजीकरण

```
POST /api/auth/register
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **अनुरोध हेडर**: `API-Version: v1` (अनिवार्य)
- **दर सीमा**: 5 बार/मिनट (IP + पथ के अनुसार)

**अनुरोध निकाय**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| username | string | हाँ | min:3, max:50 | उपयोगकर्ता नाम (अद्वितीय) |
| password | string | हाँ | min:6, max:32 | पासवर्ड (bcrypt हैश स्टोरेज) |
| real_name | string | हाँ | max:50 | वास्तविक नाम |
| captcha_key | string | हाँ | | कैप्चा key |
| clicks | array{object} | हाँ | min:2 | क्लिक निर्देशांक सरणी |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

पंजीकरण सफल होने पर सीधे JWT टोकन लौटता है, उपयोगकर्ता स्थिति डिफ़ॉल्ट रूप से सक्षम (status=1)।

### 3.7 टोकन रीफ़्रेश

```
POST /api/auth/refresh
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **अनुरोध हेडर**: `API-Version: v1` (अनिवार्य)
- **दर सीमा**: वैश्विक डिफ़ॉल्ट (60 बार/मिनट)

**अनुरोध निकाय**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| refresh_token | string | हाँ | लॉगिन/पंजीकरण पर प्राप्त refresh_token |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

रीफ़्रेश सफल होने पर नया access_token और refresh_token दोनों लौटते हैं, पुराना टोकन स्वचालित रूप से अमान्य। रीफ़्रेश के समय उपयोगकर्ता का अंतिम लॉगिन समय और IP अपडेट होता है।

**संभावित त्रुटियाँ**:
- 422: रीफ़्रेश टोकन अनुपस्थित
- 401: रीफ़्रेश टोकन अमान्य या समाप्त

### 3.8 Prometheus मॉनिटरिंग मेट्रिक्स

```
GET /metrics
```

- **प्रमाणीकरण**: आवश्यक नहीं
- **दर सीमा**: नहीं
- **प्रतिक्रिया प्रारूप**: Prometheus text format (`text/plain; version=0.0.4`)

Grafana/Prometheus द्वारा स्क्रेप के लिए सार्वजनिक Prometheus मॉनिटरिंग मेट्रिक्स एंडपॉइंट।

**प्रतिक्रिया उदाहरण**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| मेट्रिक्स नाम | प्रकार | विवरण |
|------|------|------|
| `openadmin_http_requests_total` | gauge | कुल संचित HTTP अनुरोध संख्या |
| `openadmin_active_users` | gauge | वर्तमान सक्रिय उपयोगकर्ता संख्या (24 घंटे में लॉगिन) |
| `openadmin_db_connection_status` | gauge | डेटाबेस कनेक्शन स्थिति, 1=सामान्य, 0=असामान्य |
| `openadmin_redis_connection_status` | gauge | Redis कनेक्शन स्थिति, 1=सामान्य, 0=असामान्य |
| `openadmin_memory_usage_bytes` | gauge | PHP प्रक्रिया का वर्तमान मेमोरी उपयोग (bytes) |

## 4. डैशबोर्ड

सभी एडमिन इंटरफ़ेस `/admin` समूह में माउंट हैं, `AdminAuth` (JWT प्रमाणीकरण), `AdminPermission` (RBAC अनुमति सत्यापन), `OperationLog` (ऑपरेशन रिकॉर्ड) तीन मिडलवेयर से गुजरते हैं।

### 4.1 डैशबोर्ड डेटा

```
GET /admin/dashboard
```

- **प्रमाणीकरण**: JWT + RBAC
- **कैश**: Redis 5 मिनट

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| stats फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| label | string | मेट्रिक्स नाम |
| value | string | मेट्रिक्स मान (स्ट्रिंग प्रकार) |
| icon | string | Material आइकन नाम |
| color | string | कार्ड रंग मान |
| trend | float? | दैनिक चक्रवृद्धि दर (प्रतिशत), केवल "用户总数" में है |

| trends फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| dates | array{string} | हाल के 30 दिनों की तिथि श्रृंखला |
| series | array{object} | ट्रेंड लाइन डेटा, प्रत्येक में name (नाम), data (मान सरणी), color (रंग) |

## 5. उपयोगकर्ता प्रबंधन

सभी उपयोगकर्ता प्रबंधन इंटरफ़ेस द्वारा लौटाया गया `id` hashid एन्क्रिप्टेड स्ट्रिंग है। पासवर्ड फ़ील्ड प्रतिक्रिया में बाहर रखा गया है। फ़ोन नंबर और ईमेल सूची इंटरफ़ेस में मास्क किए जाते हैं, विवरण इंटरफ़ेस में प्लेनटेक्स्ट लौटता है (डेटाबेस एन्क्रिप्टेड फ़ील्ड Encryptable trait द्वारा स्वचालित डिक्रिप्ट होते हैं)।

### 5.1 उपयोगकर्ता सूची

```
GET /admin/user
```

- **प्रमाणीकरण**: JWT + RBAC

**क्वेरी पैरामीटर**:

| पैरामीटर | प्रकार | अनिवार्य | डिफ़ॉल्ट मान | विवरण |
|------|------|------|------|------|
| page | int | नहीं | 1 | पृष्ठ संख्या |
| limit | int | नहीं | 15 | प्रति पृष्ठ संख्या |
| keyword | string | नहीं | | खोज कीवर्ड, उपयोगकर्ता नाम और वास्तविक नाम से मेल |
| status | int | नहीं | | स्थिति फ़िल्टर, 0=अक्षम, 1=सक्षम |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid एन्क्रिप्टेड उपयोगकर्ता ID |
| username | string | उपयोगकर्ता नाम |
| real_name | string | वास्तविक नाम |
| phone | string | मास्क किया गया फ़ोन नंबर (`138****5678` प्रारूप) |
| email | string | मास्क किया गया ईमेल (`a***@example.com` प्रारूप) |
| status | int | 1=सक्षम, 0=अक्षम |
| last_login_at | string | अंतिम लॉगिन समय (datetime) |
| created_at | string | निर्माण समय (datetime) |

### 5.2 उपयोगकर्ता बनाएं

```
POST /admin/user
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| username | string | हाँ | min:3, max:50 | उपयोगकर्ता नाम (अद्वितीय) |
| password | string | हाँ | min:6, max:32 | पासवर्ड (bcrypt स्टोरेज) |
| real_name | string | हाँ | max:50 | वास्तविक नाम |
| phone | string | नहीं | | फ़ोन नंबर (Encryptable एन्क्रिप्टेड स्टोरेज) |
| email | string | नहीं | | ईमेल (Encryptable एन्क्रिप्टेड स्टोरेज) |
| status | int | नहीं | in:0,1 | स्थिति, डिफ़ॉल्ट 1 (सक्षम) |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**संभावित त्रुटियाँ**:
- 422: उपयोगकर्ता नाम पहले से मौजूद
- 422: पैरामीटर सत्यापन विफल (अनिवार्य फ़ील्ड अनुपस्थित)

### 5.3 उपयोगकर्ता विवरण

```
GET /admin/user/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **पथ पैरामीटर**: `{id}` hashid एन्क्रिप्टेड उपयोगकर्ता ID है

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

विवरण इंटरफ़ेस में `phone` और `email` प्लेनटेक्स्ट लौटते हैं (डेटाबेस में एन्क्रिप्टेड स्टोरेज, Encryptable cast स्वचालित डिक्रिप्ट), बिना मास्किंग। `password` और `id_card` हमेशा प्रतिक्रिया में नहीं होते।

**संभावित त्रुटियाँ**:
- 404: उपयोगकर्ता मौजूद नहीं

### 5.4 उपयोगकर्ता अपडेट

```
PUT /admin/user/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **पथ पैरामीटर**: `{id}` hashid एन्क्रिप्टेड उपयोगकर्ता ID है

**अनुरोध निकाय**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| real_name | string | नहीं | वास्तविक नाम, न भेजने पर मूल मान बना रहता है |
| password | string | नहीं | नया पासवर्ड, खाली स्ट्रिंग या न भेजने पर संशोधित नहीं |
| phone | string | नहीं | फ़ोन नंबर |
| email | string | नहीं | ईमेल |
| status | int | नहीं | 0=अक्षम, 1=सक्षम |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**संभावित त्रुटियाँ**:
- 404: उपयोगकर्ता मौजूद नहीं

### 5.5 उपयोगकर्ता हटाएं

```
DELETE /admin/user/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **पथ पैरामीटर**: `{id}` hashid एन्क्रिप्टेड उपयोगकर्ता ID है
- **संवेदनशील ऑपरेशन**: पासवर्ड द्वितीय पुष्टि आवश्यक

**अनुरोध निकाय**:
```json
{
  "password": "admin_password"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| password | string | हाँ | वर्तमान लॉगिन उपयोगकर्ता का पासवर्ड (द्वितीय पुष्टि) |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

सॉफ्ट डिलीट निष्पादित होता है (Eloquent SoftDeletes), डेटा पर deleted_at चिह्न लगता है, भौतिक रूप से हटाया नहीं जाता।

**संभावित त्रुटियाँ**:
- 404: उपयोगकर्ता मौजूद नहीं
- 422: संवेदनशील ऑपरेशन के लिए पासवर्ड पुष्टि आवश्यक (password खाली)
- 422: पासवर्ड सत्यापन विफल (पासवर्ड मेल नहीं)

### 5.6 बैच उपयोगकर्ता हटाएं

```
POST /admin/user/batch/destroy
```

- **प्रमाणीकरण**: JWT + RBAC
- **संवेदनशील ऑपरेशन**: पासवर्ड द्वितीय पुष्टि आवश्यक

**अनुरोध निकाय**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| ids | array{string} | हाँ | hashid एन्क्रिप्टेड उपयोगकर्ता ID सरणी |
| password | string | हाँ | वर्तमान लॉगिन उपयोगकर्ता का पासवर्ड (द्वितीय पुष्टि) |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

सॉफ्ट डिलीट निष्पादित होता है, `data.count` वास्तविक हटाई गई संख्या है।

**संभावित त्रुटियाँ**:
- 422: हटाने के लिए उपयोगकर्ता चुनें (ids खाली)
- 422: अमान्य ID (hashid डिकोड विफल)
- 422: पासवर्ड सत्यापन विफल

### 5.7 बैच सक्षम/अक्षम उपयोगकर्ता

```
POST /admin/user/batch/status
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| ids | array{string} | हाँ | hashid एन्क्रिप्टेड उपयोगकर्ता ID सरणी |
| status | int | हाँ | 0=अक्षम, 1=सक्षम |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message status मान के अनुसार गतिशील रूप से `"批量启用成功"` या `"批量禁用成功"` होता है।

**संभावित त्रुटियाँ**:
- 422: उपयोगकर्ता चुनें (ids खाली)
- 422: स्थिति मान अमान्य (status 0 या 1 नहीं)

## 6. भूमिका प्रबंधन

### 6.1 भूमिका सूची

```
GET /admin/role
```

- **प्रमाणीकरण**: JWT + RBAC

**क्वेरी पैरामीटर**:

| पैरामीटर | प्रकार | अनिवार्य | डिफ़ॉल्ट मान | विवरण |
|------|------|------|------|------|
| page | int | नहीं | 1 | पृष्ठ संख्या |
| limit | int | नहीं | 15 | प्रति पृष्ठ संख्या |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid एन्क्रिप्टेड भूमिका ID |
| name | string | भूमिका नाम |
| slug | string | भूमिका पहचानकर्ता (अद्वितीय, अनुमति निर्णय के लिए) |
| description | string | भूमिका विवरण |
| status | int | 1=सक्षम, 0=अक्षम |
| users_count | int | भूमिका वाले उपयोगकर्ताओं की संख्या |

### 6.2 भूमिका बनाएं

```
POST /admin/role
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| name | string | हाँ | max:50 | भूमिका नाम |
| slug | string | हाँ | max:50 | भूमिका पहचानकर्ता |
| description | string | नहीं | | भूमिका विवरण, डिफ़ॉल्ट खाली स्ट्रिंग |
| status | int | नहीं | | स्थिति, डिफ़ॉल्ट 1 |
| permission_ids | array{int} | नहीं | | अनुमति ID सरणी (मूल INT ID, hashid नहीं) |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 भूमिका अपडेट

```
PUT /admin/role/{id}
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| name | string | नहीं | भूमिका नाम |
| description | string | नहीं | विवरण |
| status | int | नहीं | 0=अक्षम, 1=सक्षम |
| permission_ids | array{int} | नहीं | अनुमति ID सरणी, भेजने पर भूमिका अनुमतियाँ सिंक (ओवरराइट) होती हैं |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 भूमिका हटाएं

```
DELETE /admin/role/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **संवेदनशील ऑपरेशन**: पासवर्ड द्वितीय पुष्टि आवश्यक

**अनुरोध निकाय**:
```json
{
  "password": "admin_password"
}
```

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

हटाते समय भूमिका के सभी अनुमति और उपयोगकर्ता संबंध स्वचालित रूप से समाप्त होते हैं, फिर भूमिका रिकॉर्ड भौतिक रूप से हटाया जाता है।

## 7. अनुमति प्रबंधन

अनुमतियाँ वृक्ष संरचना (parent_id स्व-संदर्भ) में हैं, तीन प्रकार की। सूची इंटरफ़ेस पूर्ण अनुमति वृक्ष लौटाता है।

### 7.1 अनुमति वृक्ष

```
GET /admin/permission
```

- **प्रमाणीकरण**: JWT + RBAC

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid एन्क्रिप्टेड |
| parent_id | string | मूल अनुमति hashid, "0" रूट नोड |
| name | string | अनुमति नाम |
| slug | string | अनुमति पहचानकर्ता (रूट/बटन पहचानकर्ता) |
| type | int | 1=मेनू, 2=बटन, 3=इंटरफ़ेस |
| icon | string | मेनू आइकन (Material आइकन नाम) |
| path | string | फ्रंटएंड रूट पथ |
| sort | int | क्रम मान (आरोही) |
| children | array? | बाल अनुमति सूची (पुनरावर्ती), कोई बाल नोड न होने पर फ़ील्ड अनुपस्थित |

### 7.2 अनुमति बनाएं

```
POST /admin/permission
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| parent_id | int | नहीं | | मूल अनुमति ID (मूल INT प्रकार), डिफ़ॉल्ट 0 |
| name | string | हाँ | max:50 | अनुमति नाम |
| slug | string | हाँ | max:100 | अनुमति पहचानकर्ता |
| type | int | हाँ | in:1,2,3 | 1=मेनू, 2=बटन, 3=इंटरफ़ेस |
| icon | string | नहीं | | मेनू आइकन, डिफ़ॉल्ट खाली |
| path | string | नहीं | | फ्रंटएंड रूट पथ, डिफ़ॉल्ट खाली |
| sort | int | नहीं | | क्रम मान, डिफ़ॉल्ट 0 |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 अनुमति अपडेट

```
PUT /admin/permission/{id}
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| name | string | नहीं | अनुमति नाम |
| icon | string | नहीं | आइकन |
| path | string | नहीं | रूट पथ |
| sort | int | नहीं | क्रम मान |

### 7.4 अनुमति हटाएं

```
DELETE /admin/permission/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **संवेदनशील ऑपरेशन**: पासवर्ड द्वितीय पुष्टि आवश्यक

**अनुरोध निकाय**:
```json
{
  "password": "admin_password"
}
```

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

हटाते समय सभी बाल अनुमतियाँ कैस्केड हटती हैं (`parent_id` = वर्तमान अनुमति ID वाले रिकॉर्ड), साथ ही सभी भूमिकाओं के संबंध समाप्त होते हैं।

## 8. सिस्टम कॉन्फ़िगरेशन

सिस्टम कॉन्फ़िगरेशन `group` + `key` संयोजन से अद्वितीय होता है।

### 8.1 कॉन्फ़िगरेशन सूची

```
GET /admin/config
```

- **प्रमाणीकरण**: JWT + RBAC

**क्वेरी पैरामीटर**:

| पैरामीटर | प्रकार | अनिवार्य | डिफ़ॉल्ट मान | विवरण |
|------|------|------|------|------|
| page | int | नहीं | 1 | पृष्ठ संख्या |
| limit | int | नहीं | 15 | प्रति पृष्ठ संख्या |
| group | string | नहीं | | कॉन्फ़िगरेशन समूह द्वारा फ़िल्टर |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid |
| group | string | कॉन्फ़िगरेशन समूह (जैसे `system`, `email`, `storage`) |
| key | string | कॉन्फ़िगरेशन कुंजी |
| value | string | कॉन्फ़िगरेशन मान |
| type | string | मान प्रकार संकेत (`string`, `integer`, `boolean`, `json` आदि) |
| description | string | कॉन्फ़िगरेशन विवरण |

### 8.2 कॉन्फ़िगरेशन बनाएं

```
POST /admin/config
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| group | string | हाँ | max:100 | कॉन्फ़िगरेशन समूह |
| key | string | हाँ | max:100 | कॉन्फ़िगरेशन कुंजी (समूह में अद्वितीय) |
| value | string | हाँ | | कॉन्फ़िगरेशन मान |
| type | string | नहीं | | मान प्रकार, डिफ़ॉल्ट `string` |
| description | string | नहीं | | कॉन्फ़िगरेशन विवरण, डिफ़ॉल्ट खाली |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**संभावित त्रुटियाँ**:
- 422: कॉन्फ़िगरेशन आइटम पहले से मौजूद (समान group + key)

### 8.3 कॉन्फ़िगरेशन अपडेट

```
PUT /admin/config/{id}
```

- **प्रमाणीकरण**: JWT + RBAC

**अनुरोध निकाय**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| value | string | नहीं | कॉन्फ़िगरेशन मान अपडेट |
| type | string | नहीं | मान प्रकार अपडेट |
| description | string | नहीं | विवरण टेक्स्ट अपडेट |

### 8.4 कॉन्फ़िगरेशन हटाएं

```
DELETE /admin/config/{id}
```

- **प्रमाणीकरण**: JWT + RBAC
- **संवेदनशील ऑपरेशन**: पासवर्ड द्वितीय पुष्टि आवश्यक

**अनुरोध निकाय**:
```json
{
  "password": "admin_password"
}
```

कॉन्फ़िगरेशन रिकॉर्ड भौतिक रूप से हटाया जाता है।

## 9. ऑपरेशन लॉग

ऑपरेशन लॉग केवल-पठनीय इंटरफ़ेस है, `OperationLog` मिडलवेयर हर POST/PUT/DELETE अनुरोध पर स्वचालित रूप से लिखता है; संग्रहीत फ़ील्ड: `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`।

### 9.1 ऑपरेशन लॉग सूची

```
GET /admin/log
```

- **प्रमाणीकरण**: JWT + RBAC

**क्वेरी पैरामीटर**:

| पैरामीटर | प्रकार | अनिवार्य | डिफ़ॉल्ट मान | विवरण |
|------|------|------|------|------|
| page | int | नहीं | 1 | पृष्ठ संख्या |
| limit | int | नहीं | 15 | प्रति पृष्ठ संख्या |
| user_id | int | नहीं | | उपयोगकर्ता ID द्वारा सटीक फ़िल्टर (मूल INT प्रकार) |
| action | string | नहीं | | ऑपरेशन क्रिया द्वारा सटीक फ़िल्टर |
| path | string | नहीं | | अनुरोध पथ द्वारा फ़ज़ी फ़िल्टर |
| start_date | string | नहीं | | प्रारंभ तिथि (Y-m-d प्रारूप) |
| end_date | string | नहीं | | समाप्ति तिथि (Y-m-d प्रारूप) |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| id | string | hashid |
| user_name | string | ऑपरेटिंग उपयोगकर्ता नाम (user संबंध से प्राप्त, बिना लॉगिन ऑपरेशन "系统" दिखाता है) |
| action | string | ऑपरेशन क्रिया विवरण |
| method | string | HTTP विधि (POST/PUT/DELETE) |
| path | string | अनुरोध पथ |
| ip | string | क्लाइंट IP |
| source | string | अनुरोध स्रोत |
| input | string | अनुरोध पैरामीटर JSON स्ट्रिंग (फ़ाइलों को छोड़कर) |
| created_at | string | ऑपरेशन समय (datetime) |

## 10. प्रोफ़ाइल

प्रोफ़ाइल इंटरफ़ेस के लिए केवल JWT प्रमाणीकरण आवश्यक है (RBAC अनुमति सत्यापन नहीं — `AdminPermission` मिडलवेयर को इन्हें व्हाइटलिस्ट में डालना चाहिए)।

### 10.1 व्यक्तिगत जानकारी अपडेट

```
PUT /admin/profile
```

- **प्रमाणीकरण**: JWT

**अनुरोध निकाय**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| real_name | string | नहीं | वास्तविक नाम |
| phone | string | नहीं | फ़ोन नंबर (Encryptable एन्क्रिप्टेड स्टोरेज) |
| email | string | नहीं | ईमेल (Encryptable एन्क्रिप्टेड स्टोरेज) |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

प्रतिक्रिया में `phone` और `email` प्लेनटेक्स्ट लौटते हैं, `password` और `id_card` हटा दिए गए हैं।

### 10.2 पासवर्ड बदलें

```
PUT /admin/profile/password
```

- **प्रमाणीकरण**: JWT

**अनुरोध निकाय**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | सत्यापन नियम | विवरण |
|------|------|------|---------|------|
| old_password | string | हाँ | | वर्तमान पासवर्ड |
| new_password | string | हाँ | min:6, max:32 | नया पासवर्ड |

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**संभावित त्रुटियाँ**:
- 422: पुराना और नया पासवर्ड भरें
- 422: पुराना पासवर्ड गलत
- 422: नया पासवर्ड 6-32 अक्षरों का होना चाहिए

### 10.3 लॉगआउट

```
POST /admin/profile/logout
```

- **प्रमाणीकरण**: JWT

**अनुरोध निकाय**: नहीं (कोई requestBody नहीं, Authorization हेडर से token पढ़ा जाता है)

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

लॉगआउट तर्क: JWT डिकोड कर शेष वैधता (exp - now) प्राप्त करें, उस token का md5 हैश Redis ब्लैकलिस्ट `jwt_blacklist:{md5}` में लिखें, TTL = शेष वैधता। ब्लैकलिस्ट का token `AdminAuth` मिडलवेयर में रोका जाता है, 401 लौटता है।

बिना token के 401 लौटता है। token समाप्त/अमान्य होने पर (डिकोड में अपवाद) फिर भी लॉगआउट सफल माना जाता है।

## 11. आयात-निर्यात

### 11.1 Excel निर्यात

```
POST /admin/export/excel
```

- **प्रमाणीकरण**: JWT + RBAC
- **प्रतिक्रिया प्रकार**: फ़ाइल डाउनलोड (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**अनुरोध निकाय**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| फ़ील्ड | प्रकार | अनिवार्य | डिफ़ॉल्ट मान | विवरण |
|------|------|------|------|------|
| table | string | नहीं | `admin_user` | निर्यात तालिका नाम। समर्थित: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | नहीं | | निर्यात कॉलम फ़ील्ड नाम सरणी, खाली होने पर तालिका की सभी कॉलम निर्यात होती हैं |
| conditions | object | नहीं | `{}` | फ़िल्टर शर्तें, key-value जोड़े, मान खाली न होने पर WHERE के लिए |
| title | string | नहीं | `数据导出` | Excel शीर्षक (Sheet नाम के रूप में दिखता है) |

**समर्थित तालिकाएँ और कॉलम**:

| table | उपलब्ध कॉलम |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

संवेदनशील फ़ील्ड `phone`, `email`, `id_card` निर्यात के समय स्वचालित मास्क होते हैं। डेटा सीमा 10000 पंक्तियाँ। Excel में पहली पंक्ति फ़्रीज़ और स्वचालित फ़िल्टर।

### 11.2 PDF निर्यात

```
POST /admin/export/pdf
```

- **प्रमाणीकरण**: JWT + RBAC
- **प्रतिक्रिया प्रकार**: फ़ाइल डाउनलोड (`application/pdf`, A4 लैंडस्केप)

**अनुरोध निकाय**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

या तालिका मोड:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| फ़ील्ड | प्रकार | अनिवार्य | डिफ़ॉल्ट मान | विवरण |
|------|------|------|------|------|
| type | string | नहीं | `table` | निर्यात प्रकार: `table` / `dashboard` |
| title | string | नहीं | `数据导出` | PDF शीर्षक |
| data | object | नहीं | `{}` | निर्यात डेटा |

`type=dashboard` पर `data` में `stats` सरणी आवश्यक (कार्ड रूप में रेंडर); `type=table` पर `data` में `columns` और `rows` सरणियाँ आवश्यक।

PDF टेम्पलेट में कॉपीराइट जानकारी और निर्यात टाइमस्टैम्प शामिल है।

### 11.3 उपयोगकर्ता आयात (Excel)

```
POST /admin/import/users
```

- **प्रमाणीकरण**: JWT + RBAC
- **अनुरोध प्रकार**: `multipart/form-data` (फ़ाइल अपलोड)

**फ़ॉर्म फ़ील्ड**:

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| file | file | हाँ | `.xlsx` या `.xls` प्रारूप |

**Excel कॉलम आवश्यकताएँ**:

| कॉलम नाम | अनिवार्य | विवरण |
|------|------|------|
| username | हाँ | उपयोगकर्ता नाम (अद्वितीय) |
| password | हाँ | पासवर्ड (bcrypt हैश स्टोरेज) |
| real_name | हाँ | वास्तविक नाम |
| phone | नहीं | फ़ोन नंबर |
| email | नहीं | ईमेल |
| status | नहीं | स्थिति, डिफ़ॉल्ट 1 |

पहली पंक्ति कॉलम शीर्षक है (केस-असंवेदनशील), दूसरी पंक्ति से डेटा।

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| फ़ील्ड | प्रकार | विवरण |
|------|------|------|
| total | int | कुल पंक्तियाँ (शीर्षक पंक्ति को छोड़कर) |
| success | int | सफल आयात संख्या |
| failed | int | असफल संख्या |
| errors | array | असफल विवरण, प्रत्येक में row (Excel पंक्ति संख्या) और reason (असफल कारण) |

## 12. फ़ाइल अपलोड

```
POST /admin/upload
```

- **प्रमाणीकरण**: JWT + RBAC
- **अनुरोध प्रकार**: `multipart/form-data`

**फ़ॉर्म फ़ील्ड**:

| फ़ील्ड | प्रकार | अनिवार्य | विवरण |
|------|------|------|------|
| file | file | हाँ | अपलोड फ़ाइल |

**अनुमत फ़ाइल प्रकार**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**अधिकतम फ़ाइल आकार**: 10MB

**प्रतिक्रिया उदाहरण**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

फ़ाइलें तिथि के अनुसार `public/upload/{Y-m-d}/` में संग्रहीत होती हैं, फ़ाइल नाम `md5(uniqid) + मूल एक्सटेंशन`। `url` साइट रूट के सापेक्ष सापेक्ष पथ है।

**संभावित त्रुटियाँ**:
- 422: फ़ाइल चुनें (अपलोड नहीं हुई)
- 422: असमर्थित फ़ाइल प्रकार
- 422: फ़ाइल आकार 10MB से अधिक नहीं हो सकता
- 500: फ़ाइल अपलोड विफल (फ़ाइल अमान्य)

## 13. प्रतिक्रिया हेडर

सभी इंटरफ़ेस (वैश्विक मिडलवेयर परत इंजेक्शन) में निम्न प्रतिक्रिया हेडर होते हैं:

| हेडर | विवरण |
|----|------|
| `X-RateLimit-Limit` | दर सीमा ऊपरी सीमा (संख्या) |
| `X-RateLimit-Remaining` | शेष अनुरोध संख्या |
| `X-RateLimit-Reset` | दर सीमा विंडो रीसेट टाइमस्टैम्प |
| `Retry-After` | केवल दर सीमा ट्रिगर पर, अनुशंसित प्रतीक्षा सेकंड |
| `X-Content-Type-Options` | `nosniff` (webman डिफ़ॉल्ट, MIME स्निफिंग निषिद्ध) |
| `X-Frame-Options` | `DENY` (webman के CORS मिडलवेयर/बेस कॉन्फ़िगरेशन द्वारा) |

दर सीमा विवरण:
- डिफ़ॉल्ट वैश्विक सीमा: 60 बार/मिनट / IP+पथ
- लॉगिन एंडपॉइंट `/api/auth/login`: 10 बार/मिनट
- पंजीकरण एंडपॉइंट `/api/auth/register`: 5 बार/मिनट
- Redis एटॉमिक स्लाइडिंग विंडो एल्गोरिदम (Lua ZSET), TOCTOU रेस से बचाव
- Redis अनुपलब्ध पर fail-closed: 503 लौटता है (`Retry-After: 5`), अनुरोध पास नहीं होता

## 14. डेटा विश्लेषण (Analytics)

सभी एंडपॉइंट के लिए प्रमाणीकरण आवश्यक (`AdminAuth` + `AdminPermission`), MySQL रीयल-टाइम एग्रीगेशन, कुल 12:

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/analytics/overview | प्लेटफ़ॉर्म अवलोकन (आज/पिछले 7 दिन) |
| GET | /admin/analytics/game-ranking | गेम रैंकिंग (?days=7) |
| GET | /admin/analytics/dau-trend | DAU ट्रेंड (?days=30) |
| GET | /admin/analytics/hourly-trend | घंटेवार ट्रेंड |
| GET | /admin/analytics/action-distribution | व्यवहार वितरण |
| GET | /admin/analytics/revenue | राजस्व विश्लेषण |
| GET | /admin/analytics/conversion | गेम रूपांतरण दर |
| GET | /admin/analytics/probability | संयुक्त/सशर्त संभाव्यता |
| GET | /admin/analytics/retention | रिटेंशन विश्लेषण D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | रूपांतरण फ़नल |
| GET | /admin/analytics/arpu | ARPU/ARPPU ट्रेंड |
| GET | /admin/analytics/economy | गेम मुद्रा आर्थिक मेट्रिक्स |

## 15. टिकट प्रबंधन (Ticket)

सभी एंडपॉइंट के लिए प्रमाणीकरण आवश्यक (`AdminAuth` + `AdminPermission`), कुल 5:

| विधि | पथ | विवरण |
|------|------|------|
| GET | /admin/ticket/list | टिकट सूची (?page=&limit=&status=&type=) |
| GET | /admin/ticket/{hashid} | टिकट विवरण (उत्तर सहित) |
| POST | /admin/ticket/{hashid}/reply | टिकट का उत्तर |
| POST | /admin/ticket/{hashid}/close | टिकट बंद करें |
| POST | /admin/ticket/{hashid}/assign | प्रबंधक नियुक्त करें (admin_id) |

## 16. प्रमाणीकरण प्रवाह

पूर्ण प्रमाणीकरण अनुक्रम:

```
1. 客户端请求 POST /api/captcha/generate
   (请求头: API-Version: v1)
    ↓
   服务端返回: key + base64 图片 + 点击目标提示
   
2. 用户点击图片目标位置，前/客户端收集点击坐标
   
3. 客户端请求 POST /api/auth/login
   (请求头: API-Version: v1, Content-Type: application/json)
   请求体: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   服务端:
   a. 参数校验 → 422
   b. 校验验证码 → 422
   c. 校验用户凭证 → 401
   d. 检查账号状态 → 403
   e. 签发 JWT (access + refresh) → 200
   f. 更新 last_login_at / last_login_ip
    ↓
   客户端保存: access_token, refresh_token, expires_in

4. 后续请求携带 JWT
   请求头: Authorization: Bearer <access_token>
    ↓
   AdminAuth 中间件:
   a. 提取 Bearer token
   b. 检查黑名单 (Redis jwt_blacklist:{md5}) → 401
   c. 解码 JWT，校验过期 → 401
   d. 设置 $request->adminId = sub 字段
    ↓
   AdminPermission 中间件:
   a. 未登录（adminId 为空）→ 401
   b. 对资源路由解析权限标识
   c. 查询用户角色 → 角色权限，进行匹配
   d. 无权限 → 403
    ↓
   Controller 处理请求
    ↓
   Response + X-RateLimit-* 头

5. Access Token 过期前刷新
   客户端请求 POST /api/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

6. 登出
   客户端请求 POST /admin/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### JWT संरचना

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, डिफ़ॉल्ट TTL 7200 सेकंड (JWT कॉन्फ़िगरेशन `default_expire` द्वारा नियंत्रित)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, डिफ़ॉल्ट TTL 1209600 सेकंड (JWT कॉन्फ़िगरेशन `refresh_expire` द्वारा नियंत्रित, यानी 14 दिन)

### सुरक्षा प्रबंधन

- पासवर्ड `PASSWORD_BCRYPT` हैश में संग्रहीत
- संवेदनशील फ़ील्ड (phone, email, id_card) `erikwang2013/encryptable` से डेटाबेस परत पर पारदर्शी एन्क्रिप्शन/डिक्रिप्शन
- API परत ID `erikwang2013/hashids` से एन्क्रिप्टेड ट्रांसमिशन, मूल snowflake ID अनुक्रम उजागर होने से बचाव
- SecurityFilter वैश्विक रूप से XSS, SQL इंजेक्शन, पाथ ट्रैवर्सल, कमांड इंजेक्शन स्कैन करता है, समान IP 5 बार/60 सेकंड पर 15 मिनट अस्थायी ब्लैकलिस्ट
- संवेदनशील ऑपरेशन (उपयोगकर्ता, भूमिका, अनुमति, कॉन्फ़िगरेशन हटाना) के लिए वर्तमान लॉगिन उपयोगकर्ता के पासवर्ड की द्वितीय पुष्टि आवश्यक
- समवर्ती सत्र सीमा: एक उपयोगकर्ता के अधिकतम 3 सक्रिय Token, चौथे डिवाइस से लॉगिन पर सबसे पुराना Token ब्लैकलिस्ट में जाता है
- खाता लॉक: लगातार 5 असफल लॉगिन पर 15 मिनट खाता लॉक, लॉक अवधि में 429 लौटता है

## 15. डिप्लॉयमेंट और संचालन

### Docker Compose

प्रोजेक्ट रूट में `docker-compose.yml` उपलब्ध है, 5 सेवाओं का ऑर्केस्ट्रेशन (Nginx, webman app, MySQL, Redis, Elasticsearch)। PHP `Dockerfile` से निर्मित (`php:8.3-cli` पर आधारित, OPcache सक्षम)।

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` GitHub Actions निरंतर एकीकरण पाइपलाइन परिभाषित करता है:
- `php -l` सिंटैक्स जांच
- PHPUnit यूनिट टेस्ट
- `flutter analyze` स्टैटिक विश्लेषण

### डेटाबेस बैकअप

`database/backup/` निर्देशिका बैकअप और पुनर्स्थापना स्क्रिप्ट प्रदान करती है:
- `backup.sh` — mysqldump + gzip संपीड़ित बैकअप, 30 दिन पुरानी बैकअप फ़ाइलें स्वचालित सफाई
- `restore.sh` — इंटरैक्टिव पुनर्स्थापना, मौजूदा बैकअप सूचीबद्ध करता है

### Nginx सुरक्षा कॉन्फ़िगरेशन

प्रोडक्शन डिप्लॉयमेंट में रिवर्स प्रॉक्सी सुरक्षा हार्डनिंग के लिए `docs/nginx-security.conf` देखें।

## 16. डेटा विश्लेषण (Analytics)

डेटा विश्लेषण इंटरफ़ेस `AnalyticsController` द्वारा प्रदान किए जाते हैं, सभी MySQL रीयल-टाइम एग्रीगेशन पर आधारित (`game_game_play_log` गेम व्यवहार लॉग / `game_deposit_order` टॉप-अप ऑर्डर), डेटाबेस विफलता पर 500 के बजाय खाली डेटा लौटता है। विशेष उल्लेख के अलावा सभी के लिए JWT + RBAC प्रमाणीकरण आवश्यक है, प्रतिक्रिया प्रारूप समान है: `{ "code": 0, "message": "success", "data": ... }`।

### 16.1 प्लेटफ़ॉर्म अवलोकन

```
GET /admin/analytics/overview
```

**प्रतिक्रिया**: `today` / `week` प्रत्येक में `dau` (सक्रिय उपयोगकर्ता संख्या), `revenue` (पुष्टि टॉप-अप कुल, स्ट्रिंग), `new_users` (नए उपयोगकर्ता संख्या)।

### 16.2 गेम रैंकिंग

```
GET /admin/analytics/game-ranking?days=7
```

**प्रतिक्रिया**: गेम व्यवहार संख्या के अवरोही क्रम में शीर्ष 10, प्रत्येक में `game_id` (hashid), `name`, `plays`, `players`।

### 16.3 DAU ट्रेंड

```
GET /admin/analytics/dau-trend?days=30
```

**प्रतिक्रिया**: `{ "日期": 活跃数, ... }`, अनुपस्थित तिथि पर 0 भरा जाता है।

### 16.4 घंटेवार ट्रेंड

```
GET /admin/analytics/hourly-trend?game_id=<hashid>
```

**प्रतिक्रिया**: `{ "0": 次数, ... "23": 次数 }` 24 घंटे के स्लॉट; `game_id` खाली होने पर सभी गेमों की गणना।

### 16.5 व्यवहार वितरण

```
GET /admin/analytics/action-distribution?game_id=<hashid>&hours=24
```

**प्रतिक्रिया**: `{ "start": n, "end": n, "earn": n, "spend": n }` चार प्रकार के व्यवहार गणना; `hours` अधिकतम 168।

### 16.6 राजस्व अवलोकन

```
GET /admin/analytics/revenue?days=7
```

**प्रतिक्रिया**: `{ "total": "总额", "trend": { "日期": "当日额", ... } }`, केवल `status=confirmed` ऑर्डर गिने जाते हैं।

### 16.7 गेम रूपांतरण दर

```
GET /admin/analytics/conversion?days=30
```

**प्रतिक्रिया**: प्रत्येक गेम में `game_id` (hashid), `game_name`, `players` (अद्वितीय खिलाड़ी संख्या), `depositors` (अद्वितीय टॉप-अप संख्या), `conversion_rate` (टॉप-अप रूपांतरण दर, 0~1)।

### 16.8 संयुक्त संभाव्यता

```
GET /admin/analytics/probability?game_a=<hashid>&game_b=<hashid>
```

**प्रतिक्रिया**: `{ "joint": { "joint_probability": 0.12, "confidence": 0.3 } }` — Jaccard गुणांक (दोनों गेमों के साझा खिलाड़ी / यूनियन खिलाड़ी) और विश्वास (साझा खिलाड़ी / A गेम खिलाड़ी)।

### 16.9 रिटेंशन विश्लेषण

```
GET /admin/analytics/retention?days=30
```

**प्रतिक्रिया**: `{ "D1": "8.5%", "D3": "...", "D7": "...", "D30": "..." }` पंजीकरण दिवस समूह के अनुसार अगले दिन/3 दिन/7 दिन/30 दिन रिटेंशन दर।

### 16.10 रूपांतरण फ़नल

```
GET /admin/analytics/funnel?days=30
```

**प्रतिक्रिया**: पंजीकरण → पहला टॉप-अप → पहला विनिमय → पहला गेम चार चरणों के `step`, `count`, `rate` (पंजीकरण संख्या के सापेक्ष प्रतिशत)।

### 16.11 ARPU/ARPPU ट्रेंड

```
GET /admin/analytics/arpu?days=30
```

**प्रतिक्रिया**: `{ "dates": [...], "arpu": [...], "arppu": [...] }` दैनिक प्रति-उपयोगकर्ता राजस्व (ARPU) और प्रति-भुगतान उपयोगकर्ता राजस्व (ARPPU)।

### 16.12 गेम आर्थिक मेट्रिक्स

```
GET /admin/analytics/economy
```

**प्रतिक्रिया**: `currencies` सरणी, प्रत्येक में `game_name`, `currency`, `symbol`, `total_minted` (कुल ढलाई), `total_burned` (कुल विनाश), `circulation` (प्रचलन), `inflation_rate` (मुद्रास्फीति दर), bcmath उच्च-परिशुद्धता गणना।
