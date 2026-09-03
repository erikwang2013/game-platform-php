# API রেফারেন্স ডকুমেন্ট
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · **বাংলা** · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. ওভারভিউ

ওপেন অ্যাডমিন প্যানেল (open-admin) webman v2-এর উপর নির্মিত, RESTful JSON API প্রদান করে। সব প্রশাসনিক প্যানেল ইন্টারফেসে JWT অথেনটিকেশন ও RBAC পারমিশন ভেরিফিকেশন প্রয়োজন, পাবলিক এন্ডপয়েন্টগুলো `/api/v1` প্রিফিক্সে এবং অ্যাডমিন এন্ডপয়েন্টগুলো `/admin/v1` প্রিফিক্সে মাউন্ট করা হয়; ভার্সন URL পাথ থেকে নির্ধারিত হয়, হেডার থেকে নয়।

- **বেস URL**: `http://localhost:8787`
- **API ভার্সন**: URL পাথে এনকোড করা — পাবলিক এন্ডপয়েন্ট `/api/v1`-এ, অ্যাডমিন এন্ডপয়েন্ট `/admin/v1`-এ; কোনো ভার্সন হেডার নেই, ভবিষ্যত v2 `/api/v2` গ্রুপ হিসেবে নিবন্ধিত হবে

> **এন্ডপয়েন্ট ওভারভিউ**: অথেনটিকেশন(৫) | ড্যাশবোর্ড(১) | ইউজার(৭) | রোল(৪) | পারমিশন(৪) | কনফিগ(৪) | লগ(১) | ব্যক্তিগত সেন্টার(৩) | ইমপোর্ট-এক্সপোর্ট(৩) | আপলোড(১) | অপারেশন(৪: health/metrics/docs/security.txt) | মোট ৩৭ এন্ডপয়েন্ট
- **অথেনটিকেশন**: `Authorization: Bearer <token>` (JWT)
- **রেসপন্স ফরম্যাট**: `{ "code": 0, "message": "success", "data": {...} }`
- **ডকুমেন্ট এন্ডপয়েন্ট**: `GET /api/docs` OpenAPI 3.0 JSON স্পেক ফেরত দেয়

### রিকোয়েস্ট প্রয়োজনীয়তা

- শুধুমাত্র `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` মেথড অনুমোদিত, অন্যান্য HTTP মেথড (যেমন TRACE, CONNECT, PATCH) ব্যবহারে 405 ফেরত আসে
- সব `POST` / `PUT` রিকোয়েস্টে অবশ্যই `Content-Type: application/json` সেট করতে হবে (ফাইল আপলোড ছাড়া), না হলে 415 ফেরত আসে
- রিকোয়েস্ট বডির আকার 10MB-এর বেশি হতে পারবে না, না হলে 413 ফেরত আসে
- সিকিউরিটি ফিল্টার সব রিকোয়েস্ট ইনপুটে XSS, SQL ইনজেকশন, পাথ ট্রাভার্সাল, কমান্ড ইনজেকশন স্ক্যান করে, হিট হলে 403 ফেরত আসে
- টানা ৫ বার লগইন ব্যর্থ হলে অ্যাকাউন্ট লক ট্রিগার হয় (১৫ মিনিট), লক থাকা অবস্থায় লগইন রিকোয়েস্টে 429 ফেরত আসে
- একই ইউজার একসাথে সর্বোচ্চ ৩টি ভ্যালিড Token রাখতে পারে, এর বেশি হলে সবচেয়ে পুরনো Token স্বয়ংক্রিয়ভাবে ব্ল্যাকলিস্টে যায়

## 2. এরর কোড

| code | অর্থ | ট্রিগার পরিস্থিতি |
|------|------|---------|
| 0 | সফল | |
| 400 | রিকোয়েস্ট প্যারামিটার ত্রুটি | রিকোয়েস্ট ফরম্যাট সঠিক নয় |
| 401 | অথেনটিকেটেড নয় | Token অনুপস্থিত / মেয়াদোত্তীর্ণ / ব্ল্যাকলিস্টে |
| 403 | কোনো অনুমতি নেই / নিরাপত্তা ব্লক | RBAC পারমিশন অপর্যাপ্ত / SecurityFilter হিট |
| 404 | রিসোর্স নেই | কোয়েরি/আপডেট/ডিলিটের লক্ষ্য অস্তিত্বহীন |
| 405 | রিকোয়েস্ট মেথড অনুমোদিত নয় | শুধুমাত্র GET/POST/PUT/DELETE/OPTIONS/HEAD অনুমোদিত, নন-স্ট্যান্ডার্ড মেথড সরাসরি প্রত্যাখ্যাত |
| 413 | রিকোয়েস্ট বডি খুব বড় | Content-Length 10MB-এর বেশি |
| 415 | অসমর্থিত মিডিয়া টাইপ | POST/PUT রিকোয়েস্টে Content-Type JSON নয় এবং ফাইল আপলোডও নয় |
| 422 | প্যারামিটার ভ্যালিডেশন ব্যর্থ | বাধ্যতামূলক ফিল্ড অনুপস্থিত, ফরম্যাট ঠিক নেই, ব্যবসায়িক ভ্যালিডেশন পাস হয়নি |
| 429 | খুব বেশি রিকোয়েস্ট | RateLimit ট্রিগার / অ্যাকাউন্ট লক (টানা ৫ বার লগইন ব্যর্থ হলে ১৫ মিনিট লক) |
| 500 | সার্ভার অভ্যন্তরীণ ত্রুটি | |

## 3. পাবলিক এন্ডপয়েন্ট

সব পাবলিক এন্ডপয়েন্ট `/api/v1` প্রিফিক্সে এবং অ্যাডমিন এন্ডপয়েন্ট `/admin/v1` প্রিফিক্সে মাউন্ট করা হয়; রুট গ্রুপ প্রিফিক্স দিয়ে ভার্সন নির্ধারিত হয়; কোনো ভার্সন রিকোয়েস্ট হেডার ব্যবহার হয় না। পাবলিক কন্ট্রোলারের উদাহরণ: `app\api\v1\controller\AuthController`।

### 3.1 হেলথ চেক

```
GET /health
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রেট লিমিট**: নেই

**রেসপন্স উদাহরণ**:
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

`database`, `redis`, `elasticsearch` মান: `"ok"` | `"unavailable"`। ES অ্যাক্সেসযোগ্য না হলে `elasticsearch` `"unavailable"` ফেরত দেয়, ক্লাস্টার হেলথ status green/yellow না হলে প্রকৃত status মান ফেরত দেয় (যেমন `"red"`)।

### 3.2 API ডকুমেন্ট

```
GET /api/docs
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রেট লিমিট**: গ্লোবাল ডিফল্ট (৬০ বার/মিনিট)
- **রেসপন্স**: OpenAPI 3.0.3 JSON স্পেক, সব এন্ডপয়েন্ট ডেফিনিশন, প্যারামিটার ও Schema সহ

### 3.3 ক্লিক ক্যাপচা জেনারেট

```
POST /api/v1/captcha/generate
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রেট লিমিট**: গ্লোবাল ডিফল্ট (৬০ বার/মিনিট)

**রিকোয়েস্ট বডি**:
```json
{
  "difficulty": "medium"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| difficulty | string | না | `easy` / `medium` / `hard`, ডিফল্ট `medium` |

**রেসপন্স উদাহরণ**:
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

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| key | string | ক্যাপচা আইডেন্টিফায়ার, ভেরিফিকেশনে ফেরত পাঠানো হয় |
| image | string | base64 এনকোডেড PNG ইমেজ |
| extra.targets[].order | int | ক্লিকের ক্রম |
| extra.targets[].text | string | ক্লিক টার্গেটের প্রম্পট টেক্সট |

### 3.4 ক্লিক ক্যাপচা ভেরিফাই

```
POST /api/v1/captcha/verify
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রেট লিমিট**: গ্লোবাল ডিফল্ট (৬০ বার/মিনিট)

**রিকোয়েস্ট বডি**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| key | string | হ্যাঁ | ক্যাপচা key, generate থেকে ফেরত পাওয়া |
| clicks | array{object} | হ্যাঁ | ক্লিক কোঅর্ডিনেট অ্যারে, প্রতিটি উপাদানে `x` (int) ও `y` (int) |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

ভেরিফিকেশন ব্যর্থ হলে `code` 422, `message` `"验证失败，请重试"`, `data.valid` `false`।

### 3.5 লগইন

```
POST /api/v1/auth/login
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রেট লিমিট**: ১০ বার/মিনিট (IP + পাথ অনুযায়ী)

**রিকোয়েস্ট বডি**:
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

| ফিল্ড | টাইপ | বাধ্যতামূলক | ভ্যালিডেশন রুল | বিবরণ |
|------|------|------|---------|------|
| username | string | হ্যাঁ | min:3, max:50 | ইউজারনেম |
| password | string | হ্যাঁ | min:6, max:32 | পাসওয়ার্ড |
| captcha_key | string | হ্যাঁ | | ক্যাপচা key |
| clicks | array{object} | হ্যাঁ | min:2 | ক্লিক কোঅর্ডিনেট অ্যারে |

**রেসপন্স উদাহরণ**:
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

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| access_token | string | JWT অ্যাক্সেস টোকেন |
| refresh_token | string | JWT রিফ্রেশ টোকেন |
| expires_in | int | অ্যাক্সেস টোকেনের মেয়াদ (সেকেন্ড), ডিফল্ট 7200 |
| user.id | string | hashid এনক্রিপ্টেড ইউজার ID |
| user.username | string | ইউজারনেম |
| user.real_name | string | প্রকৃত নাম |

**সম্ভাব্য ত্রুটি**:
- 422: প্যারামিটার ভ্যালিডেশন ব্যর্থ (বাধ্যতামূলক ফিল্ড অনুপস্থিত, ফরম্যাট ঠিক নেই)
- 422: ক্যাপচা ভুল, আবার চেষ্টা করুন
- 401: ইউজারনেম বা পাসওয়ার্ড ভুল
- 403: অ্যাকাউন্ট ডিসেবল করা হয়েছে
- 429: অ্যাকাউন্ট লক করা হয়েছে, ১৫ মিনিট পরে আবার চেষ্টা করুন (টানা ৫ বার লগইন ব্যর্থ হলে ট্রিগার)

### 3.6 রেজিস্ট্রেশন

```
POST /api/v1/auth/register
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রেট লিমিট**: ৫ বার/মিনিট (IP + পাথ অনুযায়ী)

**রিকোয়েস্ট বডি**:
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

| ফিল্ড | টাইপ | বাধ্যতামূলক | ভ্যালিডেশন রুল | বিবরণ |
|------|------|------|---------|------|
| username | string | হ্যাঁ | min:3, max:50 | ইউজারনেম (ইউনিক) |
| password | string | হ্যাঁ | min:6, max:32 | পাসওয়ার্ড (bcrypt হ্যাশে সংরক্ষিত) |
| real_name | string | হ্যাঁ | max:50 | প্রকৃত নাম |
| captcha_key | string | হ্যাঁ | | ক্যাপচা key |
| clicks | array{object} | হ্যাঁ | min:2 | ক্লিক কোঅর্ডিনেট অ্যারে |

**রেসপন্স উদাহরণ**:
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

রেজিস্ট্রেশন সফল হলে সরাসরি JWT টোকেন ফেরত আসে, ইউজার স্ট্যাটাস ডিফল্টে সক্রিয় (status=1)।

### 3.7 টোকেন রিফ্রেশ

```
POST /api/v1/auth/refresh
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রেট লিমিট**: গ্লোবাল ডিফল্ট (৬০ বার/মিনিট)

**রিকোয়েস্ট বডি**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| refresh_token | string | হ্যাঁ | লগইন/রেজিস্ট্রেশনে পাওয়া refresh_token |

**রেসপন্স উদাহরণ**:
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

রিফ্রেশ সফল হলে নতুন access_token ও refresh_token দুটোই ফেরত আসে, পুরনো টোকেন স্বয়ংক্রিয়ভাবে অকার্যকর হয়। রিফ্রেশের সময় ইউজারের শেষ লগইন সময় ও IP আপডেট হয়।

**সম্ভাব্য ত্রুটি**:
- 422: রিফ্রেশ টোকেন অনুপস্থিত
- 401: রিফ্রেশ টোকেন অবৈধ বা মেয়াদোত্তীর্ণ

### 3.8 Prometheus মনিটরিং মেট্রিক

```
GET /metrics
```

- **অথেনটিকেশন**: প্রয়োজন নেই
- **রেট লিমিট**: নেই
- **রেসপন্স ফরম্যাট**: Prometheus text format (`text/plain; version=0.0.4`)

Grafana/Prometheus-এর স্ক্র্যাপের জন্য পাবলিক Prometheus মনিটরিং মেট্রিক এন্ডপয়েন্ট।

**রেসপন্স উদাহরণ**:
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

| মেট্রিক নাম | টাইপ | বিবরণ |
|------|------|------|
| `openadmin_http_requests_total` | gauge | মোট HTTP রিকোয়েস্ট সংখ্যা |
| `openadmin_active_users` | gauge | বর্তমান সক্রিয় ইউজার সংখ্যা (২৪ ঘণ্টায় লগইন) |
| `openadmin_db_connection_status` | gauge | ডেটাবেস সংযোগ অবস্থা, 1=স্বাভাবিক, 0=অস্বাভাবিক |
| `openadmin_redis_connection_status` | gauge | Redis সংযোগ অবস্থা, 1=স্বাভাবিক, 0=অস্বাভাবিক |
| `openadmin_memory_usage_bytes` | gauge | PHP প্রসেসের বর্তমান মেমরি ব্যবহার (bytes) |

## 4. ড্যাশবোর্ড

সব প্রশাসনিক প্যানেল ইন্টারফেস `/admin/v1` প্রিফিক্সে মাউন্ট করা, `AdminAuth` (JWT অথেনটিকেশন), `AdminPermission` (RBAC পারমিশন ভেরিফিকেশন), `OperationLog` (অপারেশন রেকর্ড) তিনটি মিডলওয়্যারের মধ্য দিয়ে যায়।

### 4.1 ড্যাশবোর্ড ডেটা

```
GET /admin/v1/dashboard
```

- **অথেনটিকেশন**: JWT + RBAC
- **ক্যাশ**: Redis ৫ মিনিট

**রেসপন্স উদাহরণ**:
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
        "path": "/api/v1/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| stats ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| label | string | মেট্রিক নাম |
| value | string | মেট্রিক মান (স্ট্রিং টাইপ) |
| icon | string | Material আইকন নাম |
| color | string | কার্ড রঙের মান |
| trend | float? | দৈনিক চক্রবৃদ্ধি হার (শতাংশ), শুধুমাত্র "মোট ইউজার" ফিল্ডে থাকে |

| trends ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| dates | array{string} | সাম্প্রতিক ৩০ দিনের তারিখ সিরিজ |
| series | array{object} | ট্রেন্ড লাইন ডেটা, প্রতিটিতে name (নাম), data (মান অ্যারে), color (রঙ) |

## 5. ইউজার ম্যানেজমেন্ট

সব ইউজার ম্যানেজমেন্ট ইন্টারফেসের ফেরত `id` হল hashid এনক্রিপ্টেড স্ট্রিং। পাসওয়ার্ড ফিল্ড রেসপন্স থেকে বাদ দেওয়া হয়েছে। ফোন নম্বর ও ইমেইল লিস্ট ইন্টারফেসে মাস্ক করে দেখানো হয়, ডিটেইল ইন্টারফেসে প্লেইনটেক্সটে ফেরত আসে (ডেটাবেস এনক্রিপ্টেড ফিল্ড Encryptable trait দিয়ে স্বয়ংক্রিয় ডিক্রিপ্ট হয়)।

### 5.1 ইউজার তালিকা

```
GET /admin/v1/user
```

- **অথেনটিকেশন**: JWT + RBAC

**কোয়েরি প্যারামিটার**:

| প্যারামিটার | টাইপ | বাধ্যতামূলক | ডিফল্ট মান | বিবরণ |
|------|------|------|------|------|
| page | int | না | 1 | পেজ নম্বর |
| limit | int | না | 15 | প্রতি পেজে আইটেম সংখ্যা |
| keyword | string | না | | সার্চ কীওয়ার্ড, ইউজারনেম ও প্রকৃত নাম ম্যাচ করে |
| status | int | না | | স্ট্যাটাস ফিল্টার, 0=ডিসেবল, 1=এনাবল |

**রেসপন্স উদাহরণ**:
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

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| id | string | hashid এনক্রিপ্টেড ইউজার ID |
| username | string | ইউজারনেম |
| real_name | string | প্রকৃত নাম |
| phone | string | মাস্কড ফোন নম্বর (`138****5678` ফরম্যাট) |
| email | string | মাস্কড ইমেইল (`a***@example.com` ফরম্যাট) |
| status | int | 1=এনাবল, 0=ডিসেবল |
| last_login_at | string | শেষ লগইন সময় (datetime) |
| created_at | string | তৈরি সময় (datetime) |

### 5.2 ইউজার তৈরি

```
POST /admin/v1/user
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
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

| ফিল্ড | টাইপ | বাধ্যতামূলক | ভ্যালিডেশন রুল | বিবরণ |
|------|------|------|---------|------|
| username | string | হ্যাঁ | min:3, max:50 | ইউজারনেম (ইউনিক) |
| password | string | হ্যাঁ | min:6, max:32 | পাসওয়ার্ড (bcrypt স্টোর) |
| real_name | string | হ্যাঁ | max:50 | প্রকৃত নাম |
| phone | string | না | | ফোন নম্বর (Encryptable এনক্রিপ্টেড স্টোর) |
| email | string | না | | ইমেইল (Encryptable এনক্রিপ্টেড স্টোর) |
| status | int | না | in:0,1 | স্ট্যাটাস, ডিফল্ট 1 (এনাবল) |

**রেসপন্স উদাহরণ**:
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

**সম্ভাব্য ত্রুটি**:
- 422: ইউজারনেম ইতিমধ্যে বিদ্যমান
- 422: প্যারামিটার ভ্যালিডেশন ব্যর্থ (বাধ্যতামূলক ফিল্ড অনুপস্থিত)

### 5.3 ইউজার ডিটেইল

```
GET /admin/v1/user/{id}
```

- **অথেনটিকেশন**: JWT + RBAC
- **পাথ প্যারামিটার**: `{id}` হল hashid এনক্রিপ্টেড ইউজার ID

**রেসপন্স উদাহরণ**:
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

ডিটেইল ইন্টারফেসে `phone` ও `email` প্লেইনটেক্সটে ফেরত আসে (ডেটাবেসে এনক্রিপ্টেড স্টোর, Encryptable cast স্বয়ংক্রিয় ডিক্রিপ্ট করে), মাস্ক করা হয় না। `password` ও `id_card` কখনও রেসপন্সে থাকে না।

**সম্ভাব্য ত্রুটি**:
- 404: ইউজার নেই

### 5.4 ইউজার আপডেট

```
PUT /admin/v1/user/{id}
```

- **অথেনটিকেশন**: JWT + RBAC
- **পাথ প্যারামিটার**: `{id}` হল hashid এনক্রিপ্টেড ইউজার ID

**রিকোয়েস্ট বডি**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| real_name | string | না | প্রকৃত নাম, না পাঠালে আগের মান থাকে |
| password | string | না | নতুন পাসওয়ার্ড, খালি স্ট্রিং বা না পাঠালে পরিবর্তন হয় না |
| phone | string | না | ফোন নম্বর |
| email | string | না | ইমেইল |
| status | int | না | 0=ডিসেবল, 1=এনাবল |

**রেসপন্স উদাহরণ**:
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

**সম্ভাব্য ত্রুটি**:
- 404: ইউজার নেই

### 5.5 ইউজার ডিলিট

```
DELETE /admin/v1/user/{id}
```

- **অথেনটিকেশন**: JWT + RBAC
- **পাথ প্যারামিটার**: `{id}` হল hashid এনক্রিপ্টেড ইউজার ID
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "password": "admin_password"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| password | string | হ্যাঁ | বর্তমান লগইন ইউজারের পাসওয়ার্ড (দ্বিতীয় নিশ্চিতকরণ) |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

সফট ডিলিট করা হয় (Eloquent SoftDeletes), ডেটা deleted_at চিহ্নিত হয়, ফিজিক্যাল ডিলিট নয়।

**সম্ভাব্য ত্রুটি**:
- 404: ইউজার নেই
- 422: সংবেদনশীল অপারেশনে পাসওয়ার্ড নিশ্চিতকরণ প্রয়োজন (password খালি)
- 422: পাসওয়ার্ড ভেরিফিকেশন ব্যর্থ (পাসওয়ার্ড ম্যাচ করেনি)

### 5.6 ব্যাচ ইউজার ডিলিট

```
POST /admin/v1/user/batch/destroy
```

- **অথেনটিকেশন**: JWT + RBAC
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| ids | array{string} | হ্যাঁ | hashid এনক্রিপ্টেড ইউজার ID অ্যারে |
| password | string | হ্যাঁ | বর্তমান লগইন ইউজারের পাসওয়ার্ড (দ্বিতীয় নিশ্চিতকরণ) |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

সফট ডিলিট করা হয়, `data.count` প্রকৃত ডিলিট সংখ্যা।

**সম্ভাব্য ত্রুটি**:
- 422: ডিলিটের জন্য ইউজার নির্বাচন করুন (ids খালি)
- 422: অবৈধ ID (hashid ডিকোড ব্যর্থ)
- 422: পাসওয়ার্ড ভেরিফিকেশন ব্যর্থ

### 5.7 ব্যাচ ইউজার এনাবল/ডিসেবল

```
POST /admin/v1/user/batch/status
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| ids | array{string} | হ্যাঁ | hashid এনক্রিপ্টেড ইউজার ID অ্যারে |
| status | int | হ্যাঁ | 0=ডিসেবল, 1=এনাবল |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

status মান অনুযায়ী message গতিশীলভাবে `"批量启用成功"` বা `"批量禁用成功"` হয়।

**সম্ভাব্য ত্রুটি**:
- 422: ইউজার নির্বাচন করুন (ids খালি)
- 422: স্ট্যাটাস মান অবৈধ (status 0 বা 1 নয়)

## 6. রোল ম্যানেজমেন্ট

### 6.1 রোল তালিকা

```
GET /admin/v1/role
```

- **অথেনটিকেশন**: JWT + RBAC

**কোয়েরি প্যারামিটার**:

| প্যারামিটার | টাইপ | বাধ্যতামূলক | ডিফল্ট মান | বিবরণ |
|------|------|------|------|------|
| page | int | না | 1 | পেজ নম্বর |
| limit | int | না | 15 | প্রতি পেজে আইটেম সংখ্যা |

**রেসপন্স উদাহরণ**:
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

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| id | string | hashid এনক্রিপ্টেড রোল ID |
| name | string | রোল নাম |
| slug | string | রোল আইডেন্টিফায়ার (ইউনিক, পারমিশন বিচারে ব্যবহৃত) |
| description | string | রোল বিবরণ |
| status | int | 1=এনাবল, 0=ডিসেবল |
| users_count | int | এই রোলের ইউজার সংখ্যা |

### 6.2 রোল তৈরি

```
POST /admin/v1/role
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | ভ্যালিডেশন রুল | বিবরণ |
|------|------|------|---------|------|
| name | string | হ্যাঁ | max:50 | রোল নাম |
| slug | string | হ্যাঁ | max:50 | রোল আইডেন্টিফায়ার |
| description | string | না | | রোল বিবরণ, ডিফল্ট খালি স্ট্রিং |
| status | int | না | | স্ট্যাটাস, ডিফল্ট 1 |
| permission_ids | array{int} | না | | পারমিশন ID অ্যারে (আসল INT ID, hashid নয়) |

**রেসপন্স উদাহরণ**:
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

### 6.3 রোল আপডেট

```
PUT /admin/v1/role/{id}
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| name | string | না | রোল নাম |
| description | string | না | বিবরণ |
| status | int | না | 0=ডিসেবল, 1=এনাবল |
| permission_ids | array{int} | না | পারমিশন ID অ্যারে, পাঠালে সিঙ্ক (ওভাররাইট) হয় রোল পারমিশন |

**রেসপন্স উদাহরণ**:
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

### 6.4 রোল ডিলিট

```
DELETE /admin/v1/role/{id}
```

- **অথেনটিকেশন**: JWT + RBAC
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "password": "admin_password"
}
```

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

ডিলিটের সময় রোলের সাথে সব পারমিশন ও ইউজারের সম্পর্ক স্বয়ংক্রিয়ভাবে বিচ্ছিন্ন হয়, তারপর রোল রেকর্ড ফিজিক্যালি ডিলিট হয়।

## 7. পারমিশন ম্যানেজমেন্ট

পারমিশন ট্রি-স্ট্রাকচার (parent_id সেলফ-রেফারেন্স), তিন ধরনের। লিস্ট ইন্টারফেস সম্পূর্ণ পারমিশন ট্রি ফেরত দেয়।

### 7.1 পারমিশন ট্রি

```
GET /admin/v1/permission
```

- **অথেনটিকেশন**: JWT + RBAC

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/v1/user",
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
          "slug": "/admin/v1/user/index",
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

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| id | string | hashid এনক্রিপ্টেড |
| parent_id | string | প্যারেন্ট পারমিশন hashid, "0" মানে রুট নোড |
| name | string | পারমিশন নাম |
| slug | string | পারমিশন আইডেন্টিফায়ার (রাউট/বাটন আইডেন্টিফায়ার) |
| type | int | 1=মেনু, 2=বাটন, 3=ইন্টারফেস |
| icon | string | মেনু আইকন (Material আইকন নাম) |
| path | string | ফ্রন্টএন্ড রাউট পাথ |
| sort | int | সর্ট মান (ascending) |
| children | array? | সাব-পারমিশন তালিকা (রিকার্সিভ), কোনো চাইল্ড না থাকলে এই ফিল্ড থাকে না |

### 7.2 পারমিশন তৈরি

```
POST /admin/v1/permission
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/v1/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | ভ্যালিডেশন রুল | বিবরণ |
|------|------|------|---------|------|
| parent_id | int | না | | প্যারেন্ট পারমিশন ID (আসল INT টাইপ), ডিফল্ট 0 |
| name | string | হ্যাঁ | max:50 | পারমিশন নাম |
| slug | string | হ্যাঁ | max:100 | পারমিশন আইডেন্টিফায়ার |
| type | int | হ্যাঁ | in:1,2,3 | 1=মেনু, 2=বাটন, 3=ইন্টারফেস |
| icon | string | না | | মেনু আইকন, ডিফল্ট খালি |
| path | string | না | | ফ্রন্টএন্ড রাউট পাথ, ডিফল্ট খালি |
| sort | int | না | | সর্ট মান, ডিফল্ট 0 |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/v1/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 পারমিশন আপডেট

```
PUT /admin/v1/permission/{id}
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| name | string | না | পারমিশন নাম |
| icon | string | না | আইকন |
| path | string | না | রাউট পাথ |
| sort | int | না | সর্ট মান |

### 7.4 পারমিশন ডিলিট

```
DELETE /admin/v1/permission/{id}
```

- **অথেনটিকেশন**: JWT + RBAC
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "password": "admin_password"
}
```

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

ডিলিটের সময় সব সাব-পারমিশন ক্যাসকেড ডিলিট হয় (`parent_id` = বর্তমান পারমিশন ID-এর রেকর্ড), একই সাথে সব রোলের সাথে সম্পর্ক বিচ্ছিন্ন হয়।

## 8. সিস্টেম কনফিগারেশন

সিস্টেম কনফিগারেশন `group` + `key` কম্বিনেশনে ইউনিক।

### 8.1 কনফিগ তালিকা

```
GET /admin/v1/config
```

- **অথেনটিকেশন**: JWT + RBAC

**কোয়েরি প্যারামিটার**:

| প্যারামিটার | টাইপ | বাধ্যতামূলক | ডিফল্ট মান | বিবরণ |
|------|------|------|------|------|
| page | int | না | 1 | পেজ নম্বর |
| limit | int | না | 15 | প্রতি পেজে আইটেম সংখ্যা |
| group | string | না | | কনফিগ গ্রুপ অনুযায়ী ফিল্টার |

**রেসপন্স উদাহরণ**:
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

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| id | string | hashid |
| group | string | কনফিগ গ্রুপ (যেমন `system`, `email`, `storage`) |
| key | string | কনফিগ কী |
| value | string | কনফিগ মান |
| type | string | মান টাইপ নির্দেশ (`string`, `integer`, `boolean`, `json` ইত্যাদি) |
| description | string | কনফিগ বিবরণ |

### 8.2 কনফিগ তৈরি

```
POST /admin/v1/config
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | ভ্যালিডেশন রুল | বিবরণ |
|------|------|------|---------|------|
| group | string | হ্যাঁ | max:100 | কনফিগ গ্রুপ |
| key | string | হ্যাঁ | max:100 | কনফিগ কী (একই গ্রুপে ইউনিক) |
| value | string | হ্যাঁ | | কনফিগ মান |
| type | string | না | | মান টাইপ, ডিফল্ট `string` |
| description | string | না | | কনফিগ বিবরণ, ডিফল্ট খালি |

**রেসপন্স উদাহরণ**:
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

**সম্ভাব্য ত্রুটি**:
- 422: কনফিগ আইটেম ইতিমধ্যে বিদ্যমান (একই group + key)

### 8.3 কনফিগ আপডেট

```
PUT /admin/v1/config/{id}
```

- **অথেনটিকেশন**: JWT + RBAC

**রিকোয়েস্ট বডি**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| value | string | না | কনফিগ মান আপডেট |
| type | string | না | মান টাইপ আপডেট |
| description | string | না | বিবরণ টেক্সট আপডেট |

### 8.4 কনফিগ ডিলিট

```
DELETE /admin/v1/config/{id}
```

- **অথেনটিকেশন**: JWT + RBAC
- **সংবেদনশীল অপারেশন**: পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন

**রিকোয়েস্ট বডি**:
```json
{
  "password": "admin_password"
}
```

কনফিগ রেকর্ড ফিজিক্যালি ডিলিট হয়।

## 9. অপারেশন লগ

অপারেশন লগ শুধু-পঠন ইন্টারফেস, `OperationLog` মিডলওয়্যার প্রতিটি POST/PUT/DELETE রিকোয়েস্টে স্বয়ংক্রিয়ভাবে লেখে, স্টোর করা ফিল্ডের মধ্যে `user_id`, `action`, `method`, `path`, `ip`, `source`, `input` অন্তর্ভুক্ত।

### 9.1 অপারেশন লগ তালিকা

```
GET /admin/v1/log
```

- **অথেনটিকেশন**: JWT + RBAC

**কোয়েরি প্যারামিটার**:

| প্যারামিটার | টাইপ | বাধ্যতামূলক | ডিফল্ট মান | বিবরণ |
|------|------|------|------|------|
| page | int | না | 1 | পেজ নম্বর |
| limit | int | না | 15 | প্রতি পেজে আইটেম সংখ্যা |
| user_id | int | না | | ইউজার ID দিয়ে সঠিক ফিল্টার (আসল INT টাইপ) |
| action | string | না | | অ্যাকশন দিয়ে সঠিক ফিল্টার |
| path | string | না | | রিকোয়েস্ট পাথ দিয়ে ফাজি ফিল্টার |
| start_date | string | না | | শুরু তারিখ (Y-m-d ফরম্যাট) |
| end_date | string | না | | শেষ তারিখ (Y-m-d ফরম্যাট) |

**রেসপন্স উদাহরণ**:
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
        "path": "/api/v1/auth/login",
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

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| id | string | hashid |
| user_name | string | অপারেশনকারী ইউজারনেম (user রিলেশন দিয়ে পাওয়া, লগইন না থাকলে "সিস্টেম" দেখায়) |
| action | string | অপারেশন অ্যাকশন বিবরণ |
| method | string | HTTP মেথড (POST/PUT/DELETE) |
| path | string | রিকোয়েস্ট পাথ |
| ip | string | ক্লায়েন্ট IP |
| source | string | রিকোয়েস্ট সোর্স |
| input | string | রিকোয়েস্ট প্যারামিটার JSON স্ট্রিং (ফাইল ছাড়া) |
| created_at | string | অপারেশন সময় (datetime) |

## 10. ব্যক্তিগত সেন্টার

ব্যক্তিগত সেন্টার ইন্টারফেসে শুধুমাত্র JWT অথেনটিকেশন প্রয়োজন (RBAC পারমিশন ভেরিফিকেশন দরকার নেই — `AdminPermission` মিডলওয়্যারকে এটি হোয়াইটলিস্টে রাখতে হবে)।

### 10.1 ব্যক্তিগত তথ্য আপডেট

```
PUT /admin/v1/profile
```

- **অথেনটিকেশন**: JWT

**রিকোয়েস্ট বডি**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| real_name | string | না | প্রকৃত নাম |
| phone | string | না | ফোন নম্বর (Encryptable এনক্রিপ্টেড স্টোর) |
| email | string | না | ইমেইল (Encryptable এনক্রিপ্টেড স্টোর) |

**রেসপন্স উদাহরণ**:
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

রেসপন্সে `phone` ও `email` প্লেইনটেক্সটে ফেরত আসে, `password` ও `id_card` বাদ দেওয়া হয়েছে।

### 10.2 পাসওয়ার্ড পরিবর্তন

```
PUT /admin/v1/profile/password
```

- **অথেনটিকেশন**: JWT

**রিকোয়েস্ট বডি**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| ফিল্ড | টাইপ | বাধ্যতামূলক | ভ্যালিডেশন রুল | বিবরণ |
|------|------|------|---------|------|
| old_password | string | হ্যাঁ | | বর্তমান পাসওয়ার্ড |
| new_password | string | হ্যাঁ | min:6, max:32 | নতুন পাসওয়ার্ড |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**সম্ভাব্য ত্রুটি**:
- 422: পুরনো পাসওয়ার্ড ও নতুন পাসওয়ার্ড লিখুন
- 422: পুরনো পাসওয়ার্ড ভুল
- 422: নতুন পাসওয়ার্ডের দৈর্ঘ্য 6-32 অক্ষর

### 10.3 লগআউট

```
POST /admin/v1/profile/logout
```

- **অথেনটিকেশন**: JWT

**রিকোয়েস্ট বডি**: নেই (requestBody নেই, Authorization হেডার থেকে token পড়া হয়)

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

লগআউট লজিক: JWT ডিকোড করে অবশিষ্ট মেয়াদ (exp - now) পাওয়া যায়, সেই token-এর md5 হ্যাশ Redis ব্ল্যাকলিস্ট `jwt_blacklist:{md5}`-এ লেখা হয়, TTL = অবশিষ্ট মেয়াদ। ব্ল্যাকলিস্টের token `AdminAuth` মিডলওয়্যারে ব্লক হয়, 401 ফেরত আসে।

token না থাকলে 401 ফেরত আসে। token মেয়াদোত্তীর্ণ/অবৈধ হলে (ডিকোডে এক্সেপশন) তবুও লগআউট সফল ধরা হয়।

## 11. ইমপোর্ট-এক্সপোর্ট

### 11.1 Excel এক্সপোর্ট

```
POST /admin/v1/export/excel
```

- **অথেনটিকেশন**: JWT + RBAC
- **রেসপন্স টাইপ**: ফাইল ডাউনলোড (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**রিকোয়েস্ট বডি**:
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

| ফিল্ড | টাইপ | বাধ্যতামূলক | ডিফল্ট মান | বিবরণ |
|------|------|------|------|------|
| table | string | না | `admin_user` | এক্সপোর্ট টেবিল নাম। সমর্থিত: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | না | | এক্সপোর্ট কলাম ফিল্ড নাম অ্যারে, খালি হলে টেবিলের সব কলাম এক্সপোর্ট হয় |
| conditions | object | না | `{}` | ফিল্টার শর্ত, key-value পেয়ার, মান খালি না হলে WHERE-এ ব্যবহৃত |
| title | string | না | `数据导出` | Excel টাইটেল (Sheet নাম হিসেবে দেখায়) |

**সমর্থিত টেবিল ও কলাম**:

| table | উপলব্ধ কলাম |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

সংবেদনশীল ফিল্ড `phone`, `email`, `id_card` এক্সপোর্টের সময় স্বয়ংক্রিয় মাস্ক করা হয়। ডেটা সীমা ১০০০০ লাইন। Excel-এ প্রথম সারি ফ্রিজ করা হয়, অটো ফিল্টার চালু থাকে।

### 11.2 PDF এক্সপোর্ট

```
POST /admin/v1/export/pdf
```

- **অথেনটিকেশন**: JWT + RBAC
- **রেসপন্স টাইপ**: ফাইল ডাউনলোড (`application/pdf`, A4 অনুভূমিক)

**রিকোয়েস্ট বডি**:
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

অথবা টেবিল মোড:
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

| ফিল্ড | টাইপ | বাধ্যতামূলক | ডিফল্ট মান | বিবরণ |
|------|------|------|------|------|
| type | string | না | `table` | এক্সপোর্ট টাইপ: `table` / `dashboard` |
| title | string | না | `数据导出` | PDF টাইটেল |
| data | object | না | `{}` | এক্সপোর্ট ডেটা |

`type=dashboard` হলে `data`-তে `stats` অ্যারে থাকতে হবে (কার্ড ফরম্যাটে রেন্ডার হয়); `type=table` হলে `data`-তে `columns` ও `rows` অ্যারে থাকতে হবে।

PDF টেমপ্লেটে কপিরাইট তথ্য ও এক্সপোর্ট টাইমস্ট্যাম্প থাকে।

### 11.3 ইউজার ইমপোর্ট (Excel)

```
POST /admin/v1/import/users
```

- **অথেনটিকেশন**: JWT + RBAC
- **রিকোয়েস্ট টাইপ**: `multipart/form-data` (ফাইল আপলোড)

**ফর্ম ফিল্ড**:

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| file | file | হ্যাঁ | `.xlsx` বা `.xls` ফরম্যাট |

**Excel কলাম প্রয়োজনীয়তা**:

| কলাম নাম | বাধ্যতামূলক | বিবরণ |
|------|------|------|
| username | হ্যাঁ | ইউজারনেম (ইউনিক) |
| password | হ্যাঁ | পাসওয়ার্ড (bcrypt হ্যাশে স্টোর) |
| real_name | হ্যাঁ | প্রকৃত নাম |
| phone | না | ফোন নম্বর |
| email | না | ইমেইল |
| status | না | স্ট্যাটাস, ডিফল্ট 1 |

১ম সারি কলাম টাইটেল (কেস-ইনসেনসিটিভ), ২য় সারি থেকে ডেটা।

**রেসপন্স উদাহরণ**:
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

| ফিল্ড | টাইপ | বিবরণ |
|------|------|------|
| total | int | মোট লাইন সংখ্যা (টাইটেল লাইন ছাড়া) |
| success | int | সফল ইমপোর্ট সংখ্যা |
| failed | int | ব্যর্থ সংখ্যা |
| errors | array | ব্যর্থ বিবরণ, প্রতিটিতে row (Excel লাইন নম্বর) ও reason (ব্যর্থতার কারণ) |

## 12. ফাইল আপলোড

```
POST /admin/v1/upload
```

- **অথেনটিকেশন**: JWT + RBAC
- **রিকোয়েস্ট টাইপ**: `multipart/form-data`

**ফর্ম ফিল্ড**:

| ফিল্ড | টাইপ | বাধ্যতামূলক | বিবরণ |
|------|------|------|------|
| file | file | হ্যাঁ | আপলোড ফাইল |

**অনুমোদিত ফাইল টাইপ**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**সর্বোচ্চ ফাইল সাইজ**: 10MB

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

ফাইল তারিখ অনুযায়ী আলাদা ডিরেক্টরিতে `public/upload/{Y-m-d}/`-তে স্টোর হয়, ফাইলের নাম `md5(uniqid) + মূল এক্সটেনশন`। `url` সাইট রুট পাথের সাপেক্ষে রিলেটিভ পাথ।

**সম্ভাব্য ত্রুটি**:
- 422: ফাইল নির্বাচন করুন (আপলোড হয়নি)
- 422: অসমর্থিত ফাইল টাইপ
- 422: ফাইল সাইজ 10MB-এর বেশি হতে পারবে না
- 500: ফাইল আপলোড ব্যর্থ (ফাইল অবৈধ)

## 13. রেসপন্স হেডার

সব ইন্টারফেসে (গ্লোবাল মিডলওয়্যার লেয়ারে ইনজেক্ট করা) নিম্নলিখিত রেসপন্স হেডার থাকে:

| হেডার | বিবরণ |
|----|------|
| `X-RateLimit-Limit` | রেট লিমিট সীমা (সংখ্যা) |
| `X-RateLimit-Remaining` | অবশিষ্ট রিকোয়েস্ট সংখ্যা |
| `X-RateLimit-Reset` | রেট লিমিট উইন্ডো রিসেট টাইমস্ট্যাম্প |
| `Retry-After` | শুধুমাত্র রেট লিমিট ট্রিগার হলে ফেরত আসে, অপেক্ষার সেকেন্ড নির্দেশ করে |
| `X-Content-Type-Options` | `nosniff` (webman ডিফল্ট, MIME স্নিফিং নিষিদ্ধ) |
| `X-Frame-Options` | `DENY` (webman-এর CORS মিডলওয়্যার/বেস কনফিগ থেকে) |

রেট লিমিট বিস্তারিত:
- ডিফল্ট গ্লোবাল সীমা: ৬০ বার/মিনিট / IP+পাথ
- লগইন এন্ডপয়েন্ট `/api/v1/auth/login`: ১০ বার/মিনিট
- রেজিস্ট্রেশন এন্ডপয়েন্ট `/api/v1/auth/register`: ৫ বার/মিনিট
- Redis অ্যাটমিক স্লাইডিং উইন্ডো অ্যালগরিদম (Lua ZSET), TOCTOU রেস এড়ানো হয়
- Redis অনুপলব্ধ হলে fail-closed: 503 ফেরত আসে (`Retry-After: 5`), রিকোয়েস্ট ছাড় দেওয়া হয় না

## 14. ডেটা অ্যানালাইসিস (Analytics)

সব এন্ডপয়েন্টে অথেনটিকেশন প্রয়োজন (`AdminAuth` + `AdminPermission`), MySQL রিয়েল-টাইম অ্যাগ্রিগেশন, মোট ১২টি:

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/v1/analytics/overview | প্ল্যাটফর্ম ওভারভিউ (আজ/সাম্প্রতিক ৭ দিন) |
| GET | /admin/v1/analytics/game-ranking | গেম র্যাঙ্কিং (?days=7) |
| GET | /admin/v1/analytics/dau-trend | DAU ট্রেন্ড (?days=30) |
| GET | /admin/v1/analytics/hourly-trend | ঘণ্টাভিত্তিক ট্রেন্ড |
| GET | /admin/v1/analytics/action-distribution | আচরণ বিতরণ |
| GET | /admin/v1/analytics/revenue | রেভিনিউ অ্যানালাইসিস |
| GET | /admin/v1/analytics/conversion | গেম কনভার্সন রেট |
| GET | /admin/v1/analytics/probability | জয়েন্ট/কন্ডিশনাল প্রোবাবিলিটি |
| GET | /admin/v1/analytics/retention | রিটেনশন অ্যানালাইসিস D1/D3/D7/D30 |
| GET | /admin/v1/analytics/funnel | কনভার্সন ফানেল |
| GET | /admin/v1/analytics/arpu | ARPU/ARPPU ট্রেন্ড |
| GET | /admin/v1/analytics/economy | গেম কারেন্সি ইকোনমি মেট্রিক |

## 15. টিকিট ম্যানেজমেন্ট (Ticket)

সব এন্ডপয়েন্টে অথেনটিকেশন প্রয়োজন (`AdminAuth` + `AdminPermission`), মোট ৫টি:

| মেথড | পাথ | বিবরণ |
|------|------|------|
| GET | /admin/v1/ticket/list | টিকিট তালিকা (?page=&limit=&status=&type=) |
| GET | /admin/v1/ticket/{hashid} | টিকিট বিস্তারিত (রিপ্লাই সহ) |
| POST | /admin/v1/ticket/{hashid}/reply | টিকিটে রিপ্লাই |
| POST | /admin/v1/ticket/{hashid}/close | টিকিট বন্ধ |
| POST | /admin/v1/ticket/{hashid}/assign | হ্যান্ডলার নির্ধারণ (admin_id) |

## 16. অথেনটিকেশন ফ্লো

সম্পূর্ণ অথেনটিকেশন সিকোয়েন্স:

```
1. 客户端请求 POST /api/v1/captcha/generate
    ↓
   服务端返回: key + base64 图片 + 点击目标提示
   
2. 用户点击图片目标位置，前/客户端收集点击坐标
   
3. 客户端请求 POST /api/v1/auth/login
   (请求头: Content-Type: application/json)
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
   客户端请求 POST /api/v1/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

6. 登出
   客户端请求 POST /admin/v1/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### JWT স্ট্রাকচার

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, ডিফল্ট TTL 7200 সেকেন্ড (JWT কনফিগ `default_expire` দিয়ে নিয়ন্ত্রিত)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, ডিফল্ট TTL 1209600 সেকেন্ড (JWT কনফিগ `refresh_expire` দিয়ে নিয়ন্ত্রিত, অর্থাৎ ১৪ দিন)

### নিরাপত্তা ব্যবস্থাপনা

- পাসওয়ার্ড `PASSWORD_BCRYPT` হ্যাশে স্টোর হয়
- সংবেদনশীল ফিল্ড (phone, email, id_card) `erikwang2013/encryptable` দিয়ে ডেটাবেস লেয়ারে ট্রান্সপারেন্ট এনক্রিপশন/ডিক্রিপশন হয়
- API লেয়ার ID `erikwang2013/hashids` দিয়ে এনক্রিপ্টেড ট্রান্সমিট হয়, আসল snowflake ID সিকোয়েন্স প্রকাশ এড়ানো হয়
- SecurityFilter গ্লোবালি XSS, SQL ইনজেকশন, পাথ ট্রাভার্সাল, কমান্ড ইনজেকশন স্ক্যান করে, একই IP ৫ বার/৬০ সেকেন্ড হলে ১৫ মিনিট টেম্পোরারি ব্ল্যাকলিস্ট
- সংবেদনশীল অপারেশন (ইউজার, রোল, পারমিশন, কনফিগ ডিলিট) বর্তমান লগইন ইউজারের পাসওয়ার্ড দ্বিতীয় নিশ্চিতকরণ প্রয়োজন
- কনকারেন্ট সেশন সীমা: একই ইউজারের সর্বোচ্চ ৩টি ভ্যালিড Token, ৪র্থ ডিভাইস লগইন করলে সবচেয়ে পুরনো Token বাধ্যতামূলকভাবে ব্ল্যাকলিস্টে যায়
- অ্যাকাউন্ট লক: টানা ৫ বার লগইন ব্যর্থ হলে ১৫ মিনিট অ্যাকাউন্ট লক, লক থাকা অবস্থায় 429 ফেরত আসে

## 15. ডিপ্লয়মেন্ট ও অপারেশন

### Docker Compose

প্রজেক্ট রুটে `docker-compose.yml` রয়েছে, ৫টি সার্ভিস অর্কেস্ট্রেট করে (Nginx, webman app, MySQL, Redis, Elasticsearch)। PHP `Dockerfile` দিয়ে বিল্ড হয় (`php:8.3-cli` ভিত্তিক, OPcache সক্ষম)।

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` GitHub Actions কন্টিনিউয়াস ইন্টিগ্রেশন পাইপলাইন সংজ্ঞায়িত করে:
- `php -l` সিনট্যাক্স চেক
- PHPUnit ইউনিট টেস্ট
- `flutter analyze` স্ট্যাটিক অ্যানালাইসিস

### ডেটাবেস ব্যাকআপ

`database/backup/` ডিরেক্টরি ব্যাকআপ ও রিস্টোর স্ক্রিপ্ট প্রদান করে:
- `backup.sh` — mysqldump + gzip কমপ্রেশন ব্যাকআপ, ৩০ দিন আগের পুরনো ব্যাকআপ ফাইল স্বয়ংক্রিয় পরিষ্কার
- `restore.sh` — ইন্টারঅ্যাকটিভ রিস্টোর, বিদ্যমান ব্যাকআপ তালিকা দেখিয়ে ইউজারকে নির্বাচন করায়

### Nginx নিরাপত্তা কনফিগারেশন

প্রোডাকশন ডিপ্লয়মেন্টে রিভার্স প্রক্সি নিরাপত্তা হার্ডেনিং কনফিগারেশনের জন্য `docs/nginx-security.conf` দেখুন।

## 16. ডেটা অ্যানালাইসিস (Analytics)

ডেটা অ্যানালাইসিস ইন্টারফেস `AnalyticsController` প্রদান করে, সবগুলো MySQL রিয়েল-টাইম অ্যাগ্রিগেশন ভিত্তিক (`game_game_play_log` গেম আচরণ লগ / `game_deposit_order` টপ-আপ অর্ডার), ডেটাবেস ব্যর্থ হলে 500 নয় বরং খালি ডেটা ফেরত আসে। বিশেষ উল্লেখ ছাড়া সবগুলিতে JWT + RBAC অথেনটিকেশন প্রয়োজন, রেসপন্স র্যাপার ফরম্যাট একীভূত `{ "code": 0, "message": "success", "data": ... }`।

### 16.1 প্ল্যাটফর্ম ওভারভিউ

```
GET /admin/v1/analytics/overview
```

**রেসপন্স**: `today` / `week` প্রতিটিতে `dau` (সক্রিয় ইউজার সংখ্যা), `revenue` (কনফার্মড টপ-আপ মোট, স্ট্রিং), `new_users` (নতুন ইউজার সংখ্যা)।

### 16.2 গেম র্যাঙ্কিং

```
GET /admin/v1/analytics/game-ranking?days=7
```

**রেসপন্স**: গেম আচরণ সংখ্যার অবরোহ ক্রমে শীর্ষ ১০, প্রতিটিতে `game_id` (hashid), `name`, `plays`, `players`।

### 16.3 DAU ট্রেন্ড

```
GET /admin/v1/analytics/dau-trend?days=30
```

**রেসপন্স**: `{ "日期": 活跃数, ... }`, অনুপস্থিত তারিখে 0 বসে।

### 16.4 ঘণ্টাভিত্তিক ট্রেন্ড

```
GET /admin/v1/analytics/hourly-trend?game_id=<hashid>
```

**রেসপন্স**: `{ "0": 次数, ... "23": 次数 }` ২৪টি ঘণ্টার স্লট; `game_id` খালি হলে সব গেমের হিসাব।

### 16.5 আচরণ বিতরণ

```
GET /admin/v1/analytics/action-distribution?game_id=<hashid>&hours=24
```

**রেসপন্স**: `{ "start": n, "end": n, "earn": n, "spend": n }` চার ধরনের আচরণ কাউন্ট; `hours` সর্বোচ্চ 168।

### 16.6 রেভিনিউ ওভারভিউ

```
GET /admin/v1/analytics/revenue?days=7
```

**রেসপন্স**: `{ "total": "总额", "trend": { "日期": "当日额", ... } }`, শুধুমাত্র `status=confirmed` অর্ডার গণনা করা হয়।

### 16.7 গেম কনভার্সন রেট

```
GET /admin/v1/analytics/conversion?days=30
```

**রেসপন্স**: প্রতিটি গেমে `game_id` (hashid), `game_name`, `players` (ডিডুপ্লিকেটেড প্লেয়ার সংখ্যা), `depositors` (ডিডুপ্লিকেটেড টপ-আপ ইউজার সংখ্যা), `conversion_rate` (টপ-আপ কনভার্সন রেট, 0~1)।

### 16.8 জয়েন্ট প্রোবাবিলিটি

```
GET /admin/v1/analytics/probability?game_a=<hashid>&game_b=<hashid>
```

**রেসপন্স**: `{ "joint": { "joint_probability": 0.12, "confidence": 0.3 } }` — Jaccard সহগ (দুই গেমের কমন প্লেয়ার / ইউনিয়ন প্লেয়ার) ও কনফিডেন্স (কমন প্লেয়ার / A গেমের প্লেয়ার)।

### 16.9 রিটেনশন অ্যানালাইসিস

```
GET /admin/v1/analytics/retention?days=30
```

**রেসপন্স**: `{ "D1": "8.5%", "D3": "...", "D7": "...", "D30": "..." }` রেজিস্ট্রেশন তারিখ অনুযায়ী গ্রুপে পরের দিন/৩ দিন/৭ দিন/৩০ দিনের রিটেনশন রেট।

### 16.10 কনভার্সন ফানেল

```
GET /admin/v1/analytics/funnel?days=30
```

**রেসপন্স**: রেজিস্ট্রেশন → প্রথম টপ-আপ → প্রথম বিনিময় → প্রথম গেম চারটি ধাপের `step`, `count`, `rate` (রেজিস্ট্রেশন সংখ্যার সাপেক্ষে শতাংশ)।

### 16.11 ARPU/ARPPU ট্রেন্ড

```
GET /admin/v1/analytics/arpu?days=30
```

**রেসপন্স**: `{ "dates": [...], "arpu": [...], "arppu": [...] }` দৈনিক জনপ্রতি রেভিনিউ (ARPU) ও পেইং ইউজার জনপ্রতি রেভিনিউ (ARPPU)।

### 16.12 গেম ইকোনমি মেট্রিক

```
GET /admin/v1/analytics/economy
```

**রেসপন্স**: `currencies` অ্যারে, প্রতিটিতে `game_name`, `currency`, `symbol`, `total_minted` (মোট মিন্টেড), `total_burned` (মোট বার্নড), `circulation` (সার্কুলেশন), `inflation_rate` (ইনফ্লেশন রেট), bcmath উচ্চ-নির্ভুলতা গণনা ব্যবহৃত।

## 17. পেমেন্ট ম্যানেজমেন্ট (Payment)

পেমেন্ট পদ্ধতি ব্যবস্থাপনা `PaymentController` দ্বারা সরবরাহ করা হয়; ৫টি এন্ডপয়েন্টেরই JWT + RBAC প্রমাণীকরণ প্রয়োজন। `provider` হোয়াইটলিস্ট: `stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash`। `config` হল পেমেন্ট কনফিগারেশনের JSON স্ট্রিং (ডাটাবেসে এনক্রিপ্টেড সংরক্ষিত)।

| পদ্ধতি | পথ | বিবরণ |
|------|------|------|
| GET | /admin/v1/payment/method/list | পেমেন্ট পদ্ধতির তালিকা (sort অনুযায়ী ঊর্ধ্বক্রম) |
| POST | /admin/v1/payment/method/toggle | পেমেন্ট পদ্ধতি সক্রিয়/নিষ্ক্রিয় করা |
| POST | /admin/v1/payment/method/create | পেমেন্ট পদ্ধতি তৈরি করা |
| PUT | /admin/v1/payment/method/{hashid} | পেমেন্ট পদ্ধতি আপডেট করা |
| DELETE | /admin/v1/payment/method/{hashid} | পেমেন্ট পদ্ধতি মুছে ফেলা (pending অর্ডার থাকলে প্রত্যাখ্যান) |

### 17.1 পেমেন্ট পদ্ধতির তালিকা

```
GET /admin/v1/payment/method/list
```

- **প্রমাণীকরণ**: JWT + RBAC

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "name": "স্ট্রাইপ ক্রেডিট কার্ড",
        "type": "fiat",
        "provider": "stripe",
        "status": 1,
        "sort": 1,
        "countries": ["US", "SG"],
        "currency": "USD",
        "min_amount": "10",
        "max_amount": "5000",
        "config": null,
        "created_at": "2026-08-29 10:00:00",
        "updated_at": "2026-08-29 10:00:00"
      }
    ]
  }
}
```

| ফিল্ড | ধরন | বিবরণ |
|------|------|------|
| id | string | পেমেন্ট পদ্ধতি ID (hashid এনকোডেড) |
| name | string | পেমেন্ট পদ্ধতির নাম |
| type | string | `fiat` (ফিয়াট মুদ্রা) / `crypto` (ক্রিপ্টোকারেন্সি) |
| provider | string | গেটওয়ে প্রদানকারী: `stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash` |
| status | int | 1=সক্রিয়, 0=নিষ্ক্রিয় |
| sort | int | ক্রম মান (ঊর্ধ্বক্রম) |
| countries | array{string} | দৃশ্যমান দেশের কোড অ্যারে (খালি অ্যারে = বিশ্বব্যাপী দৃশ্যমান) |
| currency | string | মুদ্রা (যেমন USD/USDT), খালি = কোনো সীমাবদ্ধতা নেই |
| min_amount / max_amount | string | পরিমাণ সীমা (স্পষ্টতার জন্য স্ট্রিং), 0 = সীমাহীন |
| config | string? | পেমেন্ট কনফিগ JSON (এনক্রিপ্টেড; সেট না থাকলে null) |

### 17.2 পেমেন্ট পদ্ধতি সক্রিয়/নিষ্ক্রিয় করা

```
POST /admin/v1/payment/method/toggle
```

**অনুরোধ বডি**:
```json
{
  "id": "a1b2c3d4",
  "status": 1
}
```

| ফিল্ড | ধরন | আবশ্যক | বিবরণ |
|------|------|------|------|
| id | string | হ্যাঁ | পেমেন্ট পদ্ধতি ID (hashid) |
| status | int | হ্যাঁ | 0=নিষ্ক্রিয়, 1=সক্রিয় |

**সম্ভাব্য ত্রুটি**:
- 422: ভ্যালিডেশন ব্যর্থ (id/status অনুপস্থিত বা status 0/1 নয়)
- 404: পেমেন্ট পদ্ধতি পাওয়া যায়নি

### 17.3 পেমেন্ট পদ্ধতি তৈরি করা

```
POST /admin/v1/payment/method/create
```

**অনুরোধ বডি**:
```json
{
  "name": "USDT ক্রিপ্টোকারেন্সি",
  "type": "crypto",
  "provider": "nowpayments",
  "status": 1,
  "sort": 2,
  "countries": [],
  "currency": "USDT",
  "min_amount": "10",
  "max_amount": "10000",
  "config": "{\"api_key\":\"...\"}"
}
```

| ফিল্ড | ধরন | আবশ্যক | ভ্যালিডেশন | বিবরণ |
|------|------|------|---------|------|
| name | string | হ্যাঁ | max:50 | পেমেন্ট পদ্ধতির নাম |
| type | string | হ্যাঁ | in:fiat,crypto | ধরন: ফিয়াট/ক্রিপ্টো |
| provider | string | হ্যাঁ | in:stripe,paypal,nowpayments,coinbase,skrill,neteller,paysafecard,paytm,mercadopago,astropay,paypay,kakaopay,gcash | গেটওয়ে প্রদানকারী হোয়াইটলিস্ট |
| status | int | হ্যাঁ | in:0,1 | অবস্থা |
| sort | int | না | integer,min:0 | ক্রম মান, ডিফল্ট 0 |
| countries | array{string} | না | max:2 | দৃশ্যমান দেশের কোড, খালি = বিশ্বব্যাপী |
| currency | string | না | max:10 | মুদ্রা, ডিফল্ট খালি |
| min_amount / max_amount | string | না | numeric,min:0 | পরিমাণ সীমা, ডিফল্ট "0" |
| config | string | না | | পেমেন্ট কনফিগ JSON (এনক্রিপ্টেড); খালি স্ট্রিং NULL হিসেবে সংরক্ষিত |

**রেসপন্স উদাহরণ**:
```json
{
  "code": 0,
  "message": "সফলভাবে তৈরি হয়েছে",
  "data": { "id": "e5f6g7h8" }
}
```

**সম্ভাব্য ত্রুটি**:
- 422: ভ্যালিডেশন ব্যর্থ

### 17.4 পেমেন্ট পদ্ধতি আপডেট করা

```
PUT /admin/v1/payment/method/{hashid}
```

- **পথ প্যারামিটার**: `{hashid}` হলো hashid এনকোডেড পেমেন্ট পদ্ধতি ID
- **অনুরোধ বডি**: তৈরি (17.3) এর মতোই, সব ফিল্ড ঐচ্ছিক, শুধুমাত্র পাঠানো ফিল্ড আপডেট হয়

**সম্ভাব্য ত্রুটি**:
- 404: পেমেন্ট পদ্ধতি পাওয়া যায়নি
- 422: ভ্যালিডেশন ব্যর্থ

### 17.5 পেমেন্ট পদ্ধতি মুছে ফেলা

```
DELETE /admin/v1/payment/method/{hashid}
```

- **পথ প্যারামিটার**: `{hashid}` হলো hashid এনকোডেড পেমেন্ট পদ্ধতি ID

**সম্ভাব্য ত্রুটি**:
- 404: পেমেন্ট পদ্ধতি পাওয়া যায়নি
- 422: pending ডিপোজিট অর্ডার (status=pending) আছে, মুছে ফেলা সম্ভব নয়
