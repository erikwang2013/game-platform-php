# وثيقة مرجع API
<!-- lang-nav -->

Languages: **中文** · [English](API.en.md) · [한국어](API.ko.md) · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. نظرة عامة

نظام الإدارة الخلفية المفتوح (open-admin) مبني على webman v2 ويوفر واجهات RESTful JSON API. تتطلب جميع واجهات لوحة الإدارة مصادقة JWT والتحقق من صلاحيات RBAC، بينما تُركَّب الواجهات العامة تحت بادئة `/api/v1` وواجهات لوحة الإدارة تحت بادئة `/admin/v1`، وتُحدَّد النسخة من مسار URL بدلاً من الترويسة.

- **URL الأساسي**: `http://localhost:8787`
- **إصدار API**: مضمّن في مسار URL — الواجهات العامة تحت `/api/v1` وواجهات لوحة الإدارة تحت `/admin/v1`؛ لا تُستخدم ترويسة إصدار، والإصدار v2 المستقبلي سيُسجَّل كمجموعة `/api/v2`

> **نظرة عامة على نقاط النهاية**: المصادقة(5) | لوحة المعلومات(1) | المستخدمون(7) | الأدوار(4) | الصلاحيات(4) | الإعدادات(4) | السجلات(1) | المركز الشخصي(3) | الاستيراد/التصدير(3) | الرفع(1) | التشغيل والصيانة(4: health/metrics/docs/security.txt) | المجموع 37 نقطة نهاية
- **المصادقة**: `Authorization: Bearer <token>` (JWT)
- **تنسيق الاستجابة**: `{ "code": 0, "message": "success", "data": {...} }`
- **نقطة الوثائق**: `GET /api/docs` تُرجع مواصفات OpenAPI 3.0 بصيغة JSON

### متطلبات الطلب

- يُسمح فقط بطرق `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD`، واستخدام طرق HTTP أخرى (مثل TRACE وCONNECT وPATCH) يُرجع 405
- يجب أن تحدد جميع طلبات `POST` / `PUT` ترويسة `Content-Type: application/json` (باستثناء رفع الملفات)، وإلا يُرجع 415
- يجب ألا يتجاوز حجم جسم الطلب 10MB، وإلا يُرجع 413
- يفحص مرشح الأمان جميع مدخلات الطلبات بحثًا عن XSS وحقن SQL واجتياز المسار وحقن الأوامر، وعند الإصابة يُرجع 403
- تؤدي 5 محاولات تسجيل دخول فاشلة متتالية إلى قفل الحساب (15 دقيقة)، وخلال فترة القفل تُرجع طلبات تسجيل الدخول 429
- يمكن للمستخدم نفسه الاحتفاظ بـ 3 رموز صالحة كحد أقصى في آنٍ واحد، وعند التجاوز يُضاف أقدم رمز تلقائيًا إلى القائمة السوداء

## 2. رموز الأخطاء

| code | المعنى | سيناريو الإطلاق |
|------|------|---------|
| 0 | نجاح | |
| 400 | خطأ في معاملات الطلب | تنسيق الطلب غير صحيح |
| 401 | غير مصادق | رمز مفقود / منتهي الصلاحية / موجود في القائمة السوداء |
| 403 | لا صلاحية / اعتراض أمني | صلاحيات RBAC غير كافية / إصابة SecurityFilter |
| 404 | المورد غير موجود | هدف الاستعلام/التحديث/الحذف غير موجود |
| 405 | طريقة الطلب غير مسموح بها | يُسمح فقط بـ GET/POST/PUT/DELETE/OPTIONS/HEAD، والطرق غير القياسية تُرفض مباشرة |
| 413 | جسم الطلب كبير جدًا | Content-Length يتجاوز 10MB |
| 415 | نوع الوسائط غير مدعوم | Content-Type لطلبات POST/PUT ليس JSON وليس رفع ملفات |
| 422 | فشل التحقق من المعاملات | حقل إلزامي مفقود، تنسيق غير مطابق، فشل تحقق الأعمال |
| 429 | عدد الطلبات كبير جدًا | إطلاق RateLimit / قفل الحساب (5 محاولات تسجيل دخول فاشلة متتالية تقفل 15 دقيقة) |
| 500 | خطأ داخلي في الخادم | |

## 3. نقاط النهاية العامة

تُركَّب جميع نقاط النهاية العامة تحت بادئة `/api/v1` ونقاط نهاية لوحة الإدارة تحت بادئة `/admin/v1`؛ تُحدَّد النسخة ببادئة مجموعة المسارات ؛ ولا تُستخدم أي ترويسة نسخة. مثال على وحدة تحكم عامة: `app\api\v1\controller\AuthController`.

### 3.1 فحص الصحة

```
GET /health
```

- **المصادقة**: غير مطلوبة
- **الحد من المعدل**: لا يوجد

**مثال على الاستجابة**:
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

قيم `database` و`redis` و`elasticsearch`: `"ok"` | `"unavailable"`. يُرجع `elasticsearch` قيمة `"unavailable"` عند تعذر الوصول إلى ES، وعندما لا تكون حالة صحة الكتلة green/yellow يُرجع قيمة الحالة الفعلية (مثل `"red"`).

### 3.2 وثائق API

```
GET /api/docs
```

- **المصادقة**: غير مطلوبة
- **الحد من المعدل**: الافتراضي العام (60 مرة/دقيقة)
- **الاستجابة**: مواصفات OpenAPI 3.0.3 بصيغة JSON، تتضمن تعريفات جميع نقاط النهاية والمعاملات وSchemas

### 3.3 توليد رمز التحقق بالنقر

```
POST /api/v1/captcha/generate
```

- **المصادقة**: غير مطلوبة
- **الحد من المعدل**: الافتراضي العام (60 مرة/دقيقة)

**جسم الطلب**:
```json
{
  "difficulty": "medium"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| difficulty | string | لا | `easy` / `medium` / `hard`، الافتراضي `medium` |

**مثال على الاستجابة**:
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

| الحقل | النوع | الوصف |
|------|------|------|
| key | string | معرّف رمز التحقق، يُعاد إرساله عند التحقق |
| image | string | صورة PNG بترميز base64 |
| extra.targets[].order | int | ترتيب النقر |
| extra.targets[].text | string | نص تلميح هدف النقر |

### 3.4 التحقق من رمز التحقق بالنقر

```
POST /api/v1/captcha/verify
```

- **المصادقة**: غير مطلوبة
- **الحد من المعدل**: الافتراضي العام (60 مرة/دقيقة)

**جسم الطلب**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| key | string | نعم | مفتاح رمز التحقق، يُرجع من generate |
| clicks | array{object} | نعم | مصفوفة إحداثيات النقر، كل عنصر يتضمن `x` (int) و`y` (int) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

عند فشل التحقق يكون `code` هو 422 و`message` هو `"验证失败，请重试"` و`data.valid` هو `false`.

### 3.5 تسجيل الدخول

```
POST /api/v1/auth/login
```

- **المصادقة**: غير مطلوبة
- **الحد من المعدل**: 10 مرات/دقيقة (حسب IP + المسار)

**جسم الطلب**:
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

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| username | string | نعم | min:3, max:50 | اسم المستخدم |
| password | string | نعم | min:6, max:32 | كلمة المرور |
| captcha_key | string | نعم | | مفتاح رمز التحقق |
| clicks | array{object} | نعم | min:2 | مصفوفة إحداثيات النقر |

**مثال على الاستجابة**:
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

| الحقل | النوع | الوصف |
|------|------|------|
| access_token | string | رمز الوصول JWT |
| refresh_token | string | رمز التحديث JWT |
| expires_in | int | مدة صلاحية رمز الوصول (بالثواني)، الافتراضي 7200 |
| user.id | string | معرّف المستخدم المشفّر بـ hashid |
| user.username | string | اسم المستخدم |
| user.real_name | string | الاسم الحقيقي |

**الأخطاء المحتملة**:
- 422: فشل التحقق من المعاملات (حقل إلزامي مفقود، تنسيق غير مطابق)
- 422: رمز التحقق خاطئ، يرجى إعادة المحاولة
- 401: اسم المستخدم أو كلمة المرور خاطئة
- 403: الحساب معطّل
- 429: الحساب مقفول، يرجى المحاولة بعد 15 دقيقة (يُطلق بعد 5 محاولات تسجيل دخول فاشلة متتالية)

### 3.6 التسجيل

```
POST /api/v1/auth/register
```

- **المصادقة**: غير مطلوبة
- **الحد من المعدل**: 5 مرات/دقيقة (حسب IP + المسار)

**جسم الطلب**:
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

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| username | string | نعم | min:3, max:50 | اسم المستخدم (فريد) |
| password | string | نعم | min:6, max:32 | كلمة المرور (تُخزَّن بترميز bcrypt) |
| real_name | string | نعم | max:50 | الاسم الحقيقي |
| captcha_key | string | نعم | | مفتاح رمز التحقق |
| clicks | array{object} | نعم | min:2 | مصفوفة إحداثيات النقر |

**مثال على الاستجابة**:
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

بعد نجاح التسجيل، يُرجع رمزا JWT مباشرة، وتكون حالة المستخدم مفعّلة افتراضيًا (status=1).

### 3.7 تحديث الرمز

```
POST /api/v1/auth/refresh
```

- **المصادقة**: غير مطلوبة
- **الحد من المعدل**: الافتراضي العام (60 مرة/دقيقة)

**جسم الطلب**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| refresh_token | string | نعم | الرمز refresh_token الذي حصلت عليه عند تسجيل الدخول/التسجيل |

**مثال على الاستجابة**:
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

عند نجاح التحديث، يُرجع access_token وrefresh_token جديدين معًا، ويُبطَل الرمز القديم تلقائيًا. يُحدَّث وقت آخر تسجيل دخول وعنوان IP عند التحديث.

**الأخطاء المحتملة**:
- 422: رمز التحديث مفقود
- 401: رمز التحديث غير صالح أو منتهي الصلاحية

### 3.8 مقاييس مراقبة Prometheus

```
GET /metrics
```

- **المصادقة**: غير مطلوبة
- **الحد من المعدل**: لا يوجد
- **تنسيق الاستجابة**: تنسيق نص Prometheus (`text/plain; version=0.0.4`)

نقطة نهاية عامة لمقاييس مراقبة Prometheus، ليجمعها Grafana/Prometheus.

**مثال على الاستجابة**:
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

| اسم المقياس | النوع | الوصف |
|------|------|------|
| `openadmin_http_requests_total` | gauge | إجمالي عدد طلبات HTTP المتراكمة |
| `openadmin_active_users` | gauge | عدد المستخدمين النشطين حاليًا (سجّلوا الدخول خلال 24 ساعة) |
| `openadmin_db_connection_status` | gauge | حالة اتصال قاعدة البيانات، 1=طبيعي, 0=غير طبيعي |
| `openadmin_redis_connection_status` | gauge | حالة اتصال Redis، 1=طبيعي, 0=غير طبيعي |
| `openadmin_memory_usage_bytes` | gauge | استخدام الذاكرة الحالي لعملية PHP (بايت) |

## 4. لوحة المعلومات

تُركَّب جميع واجهات لوحة الإدارة تحت بادئة `/admin/v1`، وتمر عبر ثلاث وسيطات: `AdminAuth` (مصادقة JWT) و`AdminPermission` (التحقق من صلاحيات RBAC) و`OperationLog` (تسجيل العمليات).

### 4.1 بيانات لوحة المعلومات

```
GET /admin/v1/dashboard
```

- **المصادقة**: JWT + RBAC
- **التخزين المؤقت**: Redis لمدة 5 دقائق

**مثال على الاستجابة**:
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

| حقل stats | النوع | الوصف |
|------|------|------|
| label | string | اسم المؤشر |
| value | string | قيمة المؤشر (نوع سلسلة) |
| icon | string | اسم أيقونة Material |
| color | string | قيمة لون البطاقة |
| trend | float? | معدل النمو اليومي (نسبة مئوية)، فقط "用户总数" يتضمن هذا الحقل |

| حقل trends | النوع | الوصف |
|------|------|------|
| dates | array{string} | تسلسل تواريخ آخر 30 يومًا |
| series | array{object} | بيانات خط الاتجاه، كل عنصر يتضمن name (الاسم) وdata (مصفوفة القيم) وcolor (اللون) |

## 5. إدارة المستخدمين

جميع `id` المرجعة من واجهات إدارة المستخدمين هي سلاسل مشفّرة بـ hashid. حقل كلمة المرور مستبعد من الاستجابة. يتم إخفاء أرقام الهواتف والبريد الإلكتروني في واجهات القوائم، وتُرجع بوضوح في واجهات التفاصيل (حقول قاعدة البيانات المشفّرة تُفك تلقائيًا عبر trait Encryptable).

### 5.1 قائمة المستخدمين

```
GET /admin/v1/user
```

- **المصادقة**: JWT + RBAC

**معاملات الاستعلام**:

| المعامل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| page | int | لا | 1 | رقم الصفحة |
| limit | int | لا | 15 | عدد العناصر لكل صفحة |
| keyword | string | لا | | كلمة البحث، تطابق اسم المستخدم والاسم الحقيقي |
| status | int | لا | | تصفية الحالة، 0=معطّل، 1=مفعّل |

**مثال على الاستجابة**:
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

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | معرّف المستخدم المشفّر بـ hashid |
| username | string | اسم المستخدم |
| real_name | string | الاسم الحقيقي |
| phone | string | رقم هاتف مخفي (`138****5678` التنسيق) |
| email | string | بريد إلكتروني مخفي (`a***@example.com` التنسيق) |
| status | int | 1=مفعّل, 0=معطّل |
| last_login_at | string | وقت آخر تسجيل دخول (datetime) |
| created_at | string | وقت الإنشاء (datetime) |

### 5.2 إنشاء مستخدم

```
POST /admin/v1/user
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
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

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| username | string | نعم | min:3, max:50 | اسم المستخدم (فريد) |
| password | string | نعم | min:6, max:32 | كلمة المرور (تخزين bcrypt) |
| real_name | string | نعم | max:50 | الاسم الحقيقي |
| phone | string | لا | | رقم الهاتف (تخزين مشفّر عبر Encryptable) |
| email | string | لا | | البريد الإلكتروني (تخزين مشفّر عبر Encryptable) |
| status | int | لا | in:0,1 | الحالة، الافتراضي 1 (مفعّل) |

**مثال على الاستجابة**:
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

**الأخطاء المحتملة**:
- 422: اسم المستخدم موجود بالفعل
- 422: فشل التحقق من المعاملات (حقل إلزامي مفقود)

### 5.3 تفاصيل المستخدم

```
GET /admin/v1/user/{id}
```

- **المصادقة**: JWT + RBAC
- **معامل المسار**: `{id}` هو معرّف المستخدم المشفّر بـ hashid

**مثال على الاستجابة**:
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

في واجهة التفاصيل، يُرجع `phone` و`email` بوضوح (في قاعدة البيانات مخزنان مشفَّرين، ويُفك التشفير تلقائيًا عبر cast Encryptable)، دون إخفاء. `password` و`id_card` غير موجودين دائمًا في الاستجابة.

**الأخطاء المحتملة**:
- 404: المستخدم غير موجود

### 5.4 تحديث المستخدم

```
PUT /admin/v1/user/{id}
```

- **المصادقة**: JWT + RBAC
- **معامل المسار**: `{id}` هو معرّف المستخدم المشفّر بـ hashid

**جسم الطلب**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| real_name | string | لا | الاسم الحقيقي، عند عدم الإرسال يبقى على قيمته الأصلية |
| password | string | لا | كلمة المرور الجديدة، عند كونها سلسلة فارغة أو عدم إرسالها لا تُعدَّل |
| phone | string | لا | رقم الهاتف |
| email | string | لا | البريد الإلكتروني |
| status | int | لا | 0=معطّل, 1=مفعّل |

**مثال على الاستجابة**:
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

**الأخطاء المحتملة**:
- 404: المستخدم غير موجود

### 5.5 حذف المستخدم

```
DELETE /admin/v1/user/{id}
```

- **المصادقة**: JWT + RBAC
- **معامل المسار**: `{id}` هو معرّف المستخدم المشفّر بـ hashid
- **عملية حساسة**: تتطلب تأكيد كلمة المرور ثانويًا

**جسم الطلب**:
```json
{
  "password": "admin_password"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| password | string | نعم | كلمة مرور المستخدم المسجل دخوله حاليًا (تأكيد ثانوي) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

يُنفَّذ حذف ناعم (Eloquent SoftDeletes)، حيث يُعلَّم السجل بـ deleted_at دون حذف فيزيائي.

**الأخطاء المحتملة**:
- 404: المستخدم غير موجود
- 422: تتطلب العملية الحساسة إدخال كلمة المرور للتأكيد (password فارغة)
- 422: فشل التحقق من كلمة المرور (كلمة المرور غير مطابقة)

### 5.6 حذف جماعي للمستخدمين

```
POST /admin/v1/user/batch/destroy
```

- **المصادقة**: JWT + RBAC
- **عملية حساسة**: تتطلب تأكيد كلمة المرور ثانويًا

**جسم الطلب**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| ids | array{string} | نعم | مصفوفة معرّفات المستخدمين المشفّرة بـ hashid |
| password | string | نعم | كلمة مرور المستخدم المسجل دخوله حاليًا (تأكيد ثانوي) |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

يُنفَّذ حذف ناعم، و`data.count` هو عدد المحذوفات الفعلي.

**الأخطاء المحتملة**:
- 422: يرجى اختيار المستخدمين للحذف (ids فارغة)
- 422: معرف غير صالح (فشل فك تشفير hashid)
- 422: فشل التحقق من كلمة المرور

### 5.7 تفعيل/تعطيل جماعي للمستخدمين

```
POST /admin/v1/user/batch/status
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| ids | array{string} | نعم | مصفوفة معرّفات المستخدمين المشفّرة بـ hashid |
| status | int | نعم | 0=معطّل, 1=مفعّل |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

يتغير message ديناميكيًا حسب قيمة status إلى `"批量启用成功"` أو `"批量禁用成功"`.

**الأخطاء المحتملة**:
- 422: يرجى اختيار المستخدمين (ids فارغة)
- 422: قيمة الحالة غير صالحة (status ليس 0 أو 1)

## 6. إدارة الأدوار

### 6.1 قائمة الأدوار

```
GET /admin/v1/role
```

- **المصادقة**: JWT + RBAC

**معاملات الاستعلام**:

| المعامل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| page | int | لا | 1 | رقم الصفحة |
| limit | int | لا | 15 | عدد العناصر لكل صفحة |

**مثال على الاستجابة**:
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

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | معرّف الدور المشفّر بـ hashid |
| name | string | اسم الدور |
| slug | string | معرّف الدور (فريد، يُستخدم للحكم على الصلاحيات) |
| description | string | وصف الدور |
| status | int | 1=مفعّل, 0=معطّل |
| users_count | int | عدد المستخدمين الحاصلين على هذا الدور |

### 6.2 إنشاء دور

```
POST /admin/v1/role
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| name | string | نعم | max:50 | اسم الدور |
| slug | string | نعم | max:50 | معرّف الدور |
| description | string | لا | | وصف الدور، الافتراضي سلسلة فارغة |
| status | int | لا | | الحالة، الافتراضي 1 |
| permission_ids | array{int} | لا | | مصفوفة معرّفات الصلاحيات (معرّفات INT أصلية، ليست hashid) |

**مثال على الاستجابة**:
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

### 6.3 تحديث الدور

```
PUT /admin/v1/role/{id}
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| name | string | لا | اسم الدور |
| description | string | لا | الوصف |
| status | int | لا | 0=معطّل, 1=مفعّل |
| permission_ids | array{int} | لا | مصفوفة معرّفات الصلاحيات، عند إرسالها تُزامَن (تُستبدل) صلاحيات الدور |

**مثال على الاستجابة**:
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

### 6.4 حذف الدور

```
DELETE /admin/v1/role/{id}
```

- **المصادقة**: JWT + RBAC
- **عملية حساسة**: تتطلب تأكيد كلمة المرور ثانويًا

**جسم الطلب**:
```json
{
  "password": "admin_password"
}
```

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

عند الحذف، تُفك علاقات الارتباط بين الدور وجميع الصلاحيات والمستخدمين تلقائيًا، ثم يُحذف سجل الدور فيزيائيًا.

## 7. إدارة الصلاحيات

تعتمد الصلاحيات بنية شجرية (parent_id مرتبط ذاتيًا)، وتنقسم إلى ثلاثة أنواع. تُرجع واجهة القائمة شجرة الصلاحيات الكاملة.

### 7.1 شجرة الصلاحيات

```
GET /admin/v1/permission
```

- **المصادقة**: JWT + RBAC

**مثال على الاستجابة**:
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

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | مشفّر بـ hashid |
| parent_id | string | hashid الصلاحية الأم، "0" تعني عقدة الجذر |
| name | string | اسم الصلاحية |
| slug | string | معرّف الصلاحية (معرّف المسار/الزر) |
| type | int | 1=قائمة، 2=زر، 3=واجهة |
| icon | string | أيقونة القائمة (اسم أيقونة Material) |
| path | string | مسار التوجيه للواجهة الأمامية |
| sort | int | قيمة الترتيب (تصاعدي) |
| children | array? | قائمة الصلاحيات الفرعية (تكراري)، غير متضمنة عند عدم وجود عقد فرعية |

### 7.2 إنشاء صلاحية

```
POST /admin/v1/permission
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
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

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| parent_id | int | لا | | معرّف الصلاحية الأم (نوع INT أصلي)، الافتراضي 0 |
| name | string | نعم | max:50 | اسم الصلاحية |
| slug | string | نعم | max:100 | معرّف الصلاحية |
| type | int | نعم | in:1,2,3 | 1=قائمة, 2=زر, 3=واجهة |
| icon | string | لا | | أيقونة القائمة، الافتراضي فارغ |
| path | string | لا | | مسار التوجيه للواجهة الأمامية، الافتراضي فارغ |
| sort | int | لا | | قيمة الترتيب، الافتراضي 0 |

**مثال على الاستجابة**:
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

### 7.3 تحديث الصلاحية

```
PUT /admin/v1/permission/{id}
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| name | string | لا | اسم الصلاحية |
| icon | string | لا | الأيقونة |
| path | string | لا | مسار التوجيه |
| sort | int | لا | قيمة الترتيب |

### 7.4 حذف الصلاحية

```
DELETE /admin/v1/permission/{id}
```

- **المصادقة**: JWT + RBAC
- **عملية حساسة**: تتطلب تأكيد كلمة المرور ثانويًا

**جسم الطلب**:
```json
{
  "password": "admin_password"
}
```

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

عند الحذف، تُحذف جميع الصلاحيات الفرعية بشكل متسلسل (السجلات ذات `parent_id` = معرّف الصلاحية الحالي)، مع فك الارتباط بجميع الأدوار.

## 8. إعدادات النظام

إعدادات النظام فريدة من نوعها عبر تركيبة `group` + `key`.

### 8.1 قائمة الإعدادات

```
GET /admin/v1/config
```

- **المصادقة**: JWT + RBAC

**معاملات الاستعلام**:

| المعامل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| page | int | لا | 1 | رقم الصفحة |
| limit | int | لا | 15 | عدد العناصر لكل صفحة |
| group | string | لا | | تصفية حسب مجموعة الإعدادات |

**مثال على الاستجابة**:
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

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | hashid |
| group | string | مجموعة الإعدادات (مثل `system` و`email` و`storage`) |
| key | string | مفتاح الإعداد |
| value | string | قيمة الإعداد |
| type | string | تلميح نوع القيمة (`string` و`integer` و`boolean` و`json` وغيرها) |
| description | string | وصف الإعداد |

### 8.2 إنشاء إعداد

```
POST /admin/v1/config
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| group | string | نعم | max:100 | مجموعة الإعدادات |
| key | string | نعم | max:100 | مفتاح الإعداد (فريد داخل المجموعة نفسها) |
| value | string | نعم | | قيمة الإعداد |
| type | string | لا | | نوع القيمة، الافتراضي `string` |
| description | string | لا | | وصف الإعداد، الافتراضي فارغ |

**مثال على الاستجابة**:
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

**الأخطاء المحتملة**:
- 422: عنصر الإعداد موجود بالفعل (نفس group + key)

### 8.3 تحديث الإعداد

```
PUT /admin/v1/config/{id}
```

- **المصادقة**: JWT + RBAC

**جسم الطلب**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| value | string | لا | تحديث قيمة الإعداد |
| type | string | لا | تحديث نوع القيمة |
| description | string | لا | تحديث نص الوصف |

### 8.4 حذف الإعداد

```
DELETE /admin/v1/config/{id}
```

- **المصادقة**: JWT + RBAC
- **عملية حساسة**: تتطلب تأكيد كلمة المرور ثانويًا

**جسم الطلب**:
```json
{
  "password": "admin_password"
}
```

حذف فيزيائي لسجل الإعداد.

## 9. سجلات العمليات

سجلات العمليات واجهات للقراءة فقط، تُكتب تلقائيًا بواسطة وسيطة `OperationLog` عند كل طلب POST/PUT/DELETE، وتشمل حقول التخزين `user_id` و`action` و`method` و`path` و`ip` و`source` و`input`.

### 9.1 قائمة سجلات العمليات

```
GET /admin/v1/log
```

- **المصادقة**: JWT + RBAC

**معاملات الاستعلام**:

| المعامل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| page | int | لا | 1 | رقم الصفحة |
| limit | int | لا | 15 | عدد العناصر لكل صفحة |
| user_id | int | لا | | تصفية دقيقة حسب معرّف المستخدم (نوع INT أصلي) |
| action | string | لا | | تصفية دقيقة حسب إجراء العملية |
| path | string | لا | | تصفية تقريبية حسب مسار الطلب |
| start_date | string | لا | | تاريخ البداية (تنسيق Y-m-d) |
| end_date | string | لا | | تاريخ النهاية (تنسيق Y-m-d) |

**مثال على الاستجابة**:
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

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | hashid |
| user_name | string | اسم مستخدم العملية (يُحصل عليه عبر ربط user، ويعرض "系统" للعمليات غير المسجلة الدخول) |
| action | string | وصف إجراء العملية |
| method | string | طريقة HTTP (POST/PUT/DELETE) |
| path | string | مسار الطلب |
| ip | string | عنوان IP للعميل |
| source | string | مصدر الطلب |
| input | string | سلسلة JSON لمعاملات الطلب (بدون ملفات) |
| created_at | string | وقت العملية (datetime) |

## 10. المركز الشخصي

تتطلب واجهات المركز الشخصي مصادقة JWT فقط (لا تتطلب التحقق من صلاحيات RBAC — يجب أن تُضاف وسيطة `AdminPermission` إلى القائمة البيضاء).

### 10.1 تحديث المعلومات الشخصية

```
PUT /admin/v1/profile
```

- **المصادقة**: JWT

**جسم الطلب**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| real_name | string | لا | الاسم الحقيقي |
| phone | string | لا | رقم الهاتف (تخزين مشفّر عبر Encryptable) |
| email | string | لا | البريد الإلكتروني (تخزين مشفّر عبر Encryptable) |

**مثال على الاستجابة**:
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

في الاستجابة، يُرجع `phone` و`email` بوضوح، ويُستبعد `password` و`id_card`.

### 10.2 تغيير كلمة المرور

```
PUT /admin/v1/profile/password
```

- **المصادقة**: JWT

**جسم الطلب**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| الحقل | النوع | إلزامي | قواعد التحقق | الوصف |
|------|------|------|---------|------|
| old_password | string | نعم | | كلمة المرور الحالية |
| new_password | string | نعم | min:6, max:32 | كلمة المرور الجديدة |

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**الأخطاء المحتملة**:
- 422: يرجى إدخال كلمة المرور القديمة والجديدة
- 422: كلمة المرور القديمة خاطئة
- 422: طول كلمة المرور الجديدة 6-32 حرفًا

### 10.3 تسجيل الخروج

```
POST /admin/v1/profile/logout
```

- **المصادقة**: JWT

**جسم الطلب**: لا يوجد (بدون requestBody، يُقرأ الرمز من ترويسة Authorization)

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

منطق تسجيل الخروج: فك تشفير JWT للحصول على مدة الصلاحية المتبقية (exp - now)، ثم كتابة ترميز md5 لهذا الرمز في القائمة السوداء `jwt_blacklist:{md5}` في Redis، مع TTL = مدة الصلاحية المتبقية. الرموز الموجودة في القائمة السوداء تُعترض في وسيطة `AdminAuth` وتُرجع 401.

عند غياب الرمز يُرجع 401. عند انتهاء صلاحية الرمز/كونه غير صالح (استثناء في فك التشفير) يُعتبر تسجيل الخروج ناجحًا.

## 11. الاستيراد والتصدير

### 11.1 تصدير Excel

```
POST /admin/v1/export/excel
```

- **المصادقة**: JWT + RBAC
- **نوع الاستجابة**: تنزيل ملف (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**جسم الطلب**:
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

| الحقل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| table | string | لا | `admin_user` | اسم الجدول للتصدير. المدعومة: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | لا | | مصفوفة أسماء أعمدة التصدير، عند الفراغ تُصدَّر جميع أعمدة الجدول |
| conditions | object | لا | `{}` | شروط التصفية، أزواج مفتاح-قيمة، تُستخدم في WHERE عندما تكون القيمة غير فارغة |
| title | string | لا | `数据导出` | عنوان Excel (يُعرض كاسم Sheet) |

**الجداول والأعمدة المدعومة**:

| table | الأعمدة المتاحة |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

الحقول الحساسة `phone` و`email` و`id_card` تُخفى تلقائيًا عند التصدير. حد البيانات 10000 صف. الصف الأول في Excel مجمَّد مع تصفية تلقائية.

### 11.2 تصدير PDF

```
POST /admin/v1/export/pdf
```

- **المصادقة**: JWT + RBAC
- **نوع الاستجابة**: تنزيل ملف (`application/pdf`، A4 أفقي)

**جسم الطلب**:
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

أو نمط الجدول:
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

| الحقل | النوع | إلزامي | القيمة الافتراضية | الوصف |
|------|------|------|------|------|
| type | string | لا | `table` | نوع التصدير: `table` / `dashboard` |
| title | string | لا | `数据导出` | عنوان PDF |
| data | object | لا | `{}` | بيانات التصدير |

عند `type=dashboard` يجب أن يتضمن `data` مصفوفة `stats` (تُعرض كبطاقات)؛ وعند `type=table` يجب أن يتضمن `data` مصفوفتَي `columns` و`rows`.

يتضمن قالب PDF معلومات حقوق النشر والطابع الزمني للتصدير.

### 11.3 استيراد المستخدمين (Excel)

```
POST /admin/v1/import/users
```

- **المصادقة**: JWT + RBAC
- **نوع الطلب**: `multipart/form-data` (رفع ملف)

**حقول النموذج**:

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| file | file | نعم | تنسيق `.xlsx` أو `.xls` |

**متطلبات أعمدة Excel**:

| اسم العمود | إلزامي | الوصف |
|------|------|------|
| username | نعم | اسم المستخدم (فريد) |
| password | نعم | كلمة المرور (تخزين bcrypt) |
| real_name | نعم | الاسم الحقيقي |
| phone | لا | رقم الهاتف |
| email | لا | البريد الإلكتروني |
| status | لا | الحالة، الافتراضي 1 |

الصف الأول هو ترويسة الأعمدة (غير حساس لحالة الأحرف)، والبيانات تبدأ من الصف الثاني.

**مثال على الاستجابة**:
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

| الحقل | النوع | الوصف |
|------|------|------|
| total | int | إجمالي عدد الصفوف (بدون صف الترويسة) |
| success | int | عدد عمليات الاستيراد الناجحة |
| failed | int | عدد الصفوف الفاشلة |
| errors | array | تفاصيل الفشل، كل عنصر يتضمن row (رقم صف Excel) وreason (سبب الفشل) |

## 12. رفع الملفات

```
POST /admin/v1/upload
```

- **المصادقة**: JWT + RBAC
- **نوع الطلب**: `multipart/form-data`

**حقول النموذج**:

| الحقل | النوع | إلزامي | الوصف |
|------|------|------|------|
| file | file | نعم | الملف المراد رفعه |

**أنواع الملفات المسموح بها**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**الحد الأقصى لحجم الملف**: 10MB

**مثال على الاستجابة**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

تُخزَّن الملفات في دلائل مقسمة حسب التاريخ `public/upload/{Y-m-d}/`، واسم الملف هو `md5(uniqid) + الامتداد الأصلي`. `url` مسار نسبي إلى المسار الجذري للموقع.

**الأخطاء المحتملة**:
- 422: يرجى اختيار ملف (لم يُرفع)
- 422: نوع الملف غير مدعوم
- 422: حجم الملف يجب ألا يتجاوز 10MB
- 500: فشل رفع الملف (ملف غير صالح)

## 13. ترويسات الاستجابة

تتضمن جميع الواجهات (المحقونة على طبقة الوسيطات العامة) الترويسات التالية:

| الترويسة | الوصف |
|----|------|
| `X-RateLimit-Limit` | حد الحد من المعدل (عدد المرات) |
| `X-RateLimit-Remaining` | عدد الطلبات المتبقية |
| `X-RateLimit-Reset` | الطابع الزمني لإعادة تعيين نافذة الحد من المعدل |
| `Retry-After` | تُرجع فقط عند تفعيل الحد من المعدل، عدد الثواني المقترح للانتظار |
| `X-Content-Type-Options` | `nosniff` (افتراضي من webman، يمنع فحص MIME) |
| `X-Frame-Options` | `DENY` (من وسيطة CORS/التكوين الأساسي في webman) |

تفاصيل الحد من المعدل:
- الحد العام الافتراضي: 60 مرة/دقيقة / IP+مسار
- نقطة تسجيل الدخول `/api/v1/auth/login`: 10 مرات/دقيقة
- نقطة التسجيل `/api/v1/auth/register`: 5 مرات/دقيقة
- استخدام خوارزمية النافذة المنزلقة الذرية في Redis (Lua ZSET)، لتجنب سباق TOCTOU
- عند تعذر الوصول إلى Redis يكون الإغلاق آمنًا (fail-closed): يُرجع 503 (`Retry-After: 5`)، دون تمرير الطلبات

## 14. تحليل البيانات (Analytics)

تتطلب جميع نقاط النهاية المصادقة (`AdminAuth` + `AdminPermission`)، تجميع فوري في MySQL، بإجمالي 12:

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/v1/analytics/overview | نظرة عامة على المنصة (اليوم/آخر 7 أيام) |
| GET | /admin/v1/analytics/game-ranking | ترتيب الألعاب (?days=7) |
| GET | /admin/v1/analytics/dau-trend | اتجاه DAU (?days=30) |
| GET | /admin/v1/analytics/hourly-trend | اتجاه الساعات |
| GET | /admin/v1/analytics/action-distribution | توزيع السلوك |
| GET | /admin/v1/analytics/revenue | تحليل الإيرادات |
| GET | /admin/v1/analytics/conversion | معدل تحويل الألعاب |
| GET | /admin/v1/analytics/probability | الاحتمال المشترك/الشرطي |
| GET | /admin/v1/analytics/retention | تحليل الاستبقاء D1/D3/D7/D30 |
| GET | /admin/v1/analytics/funnel | قمع التحويل |
| GET | /admin/v1/analytics/arpu | اتجاه ARPU/ARPPU |
| GET | /admin/v1/analytics/economy | المؤشرات الاقتصادية لعملات اللعبة |

## 15. إدارة التذاكر (Ticket)

تتطلب جميع نقاط النهاية المصادقة (`AdminAuth` + `AdminPermission`)، بإجمالي 5:

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/v1/ticket/list | قائمة التذاكر (?page=&limit=&status=&type=) |
| GET | /admin/v1/ticket/{hashid} | تفاصيل التذكرة (بما فيها الردود) |
| POST | /admin/v1/ticket/{hashid}/reply | الرد على التذكرة |
| POST | /admin/v1/ticket/{hashid}/close | إغلاق التذكرة |
| POST | /admin/v1/ticket/{hashid}/assign | تعيين المعالِج (admin_id) |

## 16. تدفق المصادقة

التسلسل الكامل للمصادقة:

```
1. يطلب العميل POST /api/v1/captcha/generate
    ↓
   يُرجع الخادم: key + صورة base64 + تلميحات أهداف النقر
   
2. ينقر المستخدم على مواضع الأهداف في الصورة، يجمع العميل إحداثيات النقر
   
3. يطلب العميل POST /api/v1/auth/login
   (ترويسة الطلب: Content-Type: application/json)
   جسم الطلب: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   الخادم:
   a. التحقق من المعاملات ← 422
   b. التحقق من رمز التحقق ← 422
   c. التحقق من بيانات اعتماد المستخدم ← 401
   d. فحص حالة الحساب ← 403
   e. إصدار JWT (access + refresh) ← 200
   f. تحديث last_login_at / last_login_ip
    ↓
   يحفظ العميل: access_token, refresh_token, expires_in

4. تحمل الطلبات اللاحقة JWT
   ترويسة الطلب: Authorization: Bearer <access_token>
    ↓
   وسيطة AdminAuth:
   a. استخراج Bearer token
   b. فحص القائمة السوداء (Redis jwt_blacklist:{md5}) ← 401
   c. فك تشفير JWT والتحقق من انتهاء الصلاحية ← 401
   d. تعيين $request->adminId = حقل sub
    ↓
   وسيطة AdminPermission:
   a. غير مسجل دخول (adminId فارغ) ← 401
   b. تحليل معرّف الصلاحية لمسارات الموارد
   c. الاستعلام عن أدوار المستخدم ← صلاحيات الأدوار، والمطابقة
   d. لا صلاحية ← 403
    ↓
   وحدة التحكم تعالج الطلب
    ↓
   Response + ترويسات X-RateLimit-*

5. تحديث قبل انتهاء صلاحية Access Token
   يطلب العميل POST /api/v1/auth/refresh
   جسم الطلب: { refresh_token: "..." }
    ↓
   يفك الخادم refresh_token ← إصدار access + refresh جديدين
    ↓
   يحدّث العميل الرموز المحلية

6. تسجيل الخروج
   يطلب العميل POST /admin/v1/profile/logout
   ترويسة الطلب: Authorization: Bearer <access_token>
    ↓
   الخادم:
   a. فك تشفير JWT للحصول على TTL المتبقي
   b. كتابة القائمة السوداء في Redis: jwt_blacklist:{md5(token)} = 1, TTL = مدة الصلاحية المتبقية
   c. إرجاع النجاح
```

### بنية JWT

- **access_token**: `{ sub: <user_id>, username: "<name>" }`، TTL الافتراضي 7200 ثانية (يُتحكم به عبر إعداد JWT `default_expire`)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`، TTL الافتراضي 1209600 ثانية (يُتحكم به عبر إعداد JWT `refresh_expire`، أي 14 يومًا)

### الإدارة الأمنية

- تُخزَّن كلمات المرور بترميز `PASSWORD_BCRYPT`
- الحقول الحساسة (phone, email, id_card) تُشفّر/تُفك تشفيرها بشفافية على طبقة قاعدة البيانات عبر `erikwang2013/encryptable`
- تُشفّر معرفات طبقة API عبر `erikwang2013/hashids` أثناء النقل، لتجنب كشف تسلسل معرفات snowflake الأصلية
- يفحص SecurityFilter عالميًا XSS وحقن SQL واجتياز المسار وحقن الأوامر، مع قائمة سوداء مؤقتة 15 دقيقة عند 5 مرات/60 ثانية لنفس IP
- تتطلب العمليات الحساسة (حذف المستخدمين والأدوار والصلاحيات والإعدادات) تأكيد كلمة مرور المستخدم المسجل دخوله حاليًا ثانويًا
- تقييد الجلسات المتزامنة: 3 رموز صالحة كحد أقصى لكل مستخدم، وعند تسجيل دخول الجهاز الرابع يُضاف أقدم رمز قسريًا إلى القائمة السوداء
- قفل الحساب: 5 محاولات تسجيل دخول فاشلة متتالية تُطلق قفل الحساب لمدة 15 دقيقة، وخلالها يُرجع 429

## 15. النشر والتشغيل

### Docker Compose

يوفر دليل جذر المشروع `docker-compose.yml`، ينظم 5 خدمات (Nginx وتطبيق webman وMySQL وRedis وElasticsearch). يُبنى PHP عبر `Dockerfile` (مبني على `php:8.3-cli` مع تفعيل OPcache).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

يعرّف `.github/workflows/ci.yml` خط أنابيب التكامل المستمر في GitHub Actions:
- فحص الصياغة `php -l`
- اختبارات PHPUnit الوحدوية
- التحليل الساكن `flutter analyze`

### النسخ الاحتياطي لقاعدة البيانات

يوفر دليل `database/backup/` نصوصًا للنسخ الاحتياطي والاستعادة:
- `backup.sh` — نسخ احتياطي مضغوط عبر mysqldump + gzip، تنظيف تلقائي لملفات النسخ الأقدم من 30 يومًا
- `restore.sh` — استعادة تفاعلية، تعرض النسخ الاحتياطية الموجودة ليختار المستخدم

### إعدادات أمان Nginx

يرجى الرجوع إلى `docs/nginx-security.conf` في النشر الإنتاجي لتكوين تحصين الوكيل العكسي.

## 16. تحليل البيانات (Analytics)

توفر واجهات تحليل البيانات عبر `AnalyticsController`، وكلها تعتمد التجميع الفوري في MySQL (`game_game_play_log` سجلات سلوك اللعب / `game_deposit_order` طلبات التعبئة)، وعند تعطل قاعدة البيانات تُرجع بيانات فارغة بدلاً من 500. ما لم يُذكر خلاف ذلك، تتطلب جميعها مصادقة JWT + RBAC، وتنسيق الاستجابة الموحد `{ "code": 0, "message": "success", "data": ... }`.

### 16.1 نظرة عامة على المنصة

```
GET /admin/v1/analytics/overview
```

**الاستجابة**: يتضمن `today` / `week` كلٌّ منهما `dau` (عدد المستخدمين النشطين) و`revenue` (إجمالي التعبئة المؤكدة، سلسلة) و`new_users` (عدد المستخدمين الجدد).

### 16.2 ترتيب الألعاب

```
GET /admin/v1/analytics/game-ranking?days=7
```

**الاستجابة**: أعلى 10 حسب عدد مرات سلوك اللعب تنازليًا، كل عنصر يتضمن `game_id` (hashid) و`name` و`plays` و`players`.

### 16.3 اتجاه DAU

```
GET /admin/v1/analytics/dau-trend?days=30
```

**الاستجابة**: `{ "التاريخ": عدد النشطاء, ... }`، التاريخ المفقود يُكمل بـ 0.

### 16.4 اتجاه الساعات

```
GET /admin/v1/analytics/hourly-trend?game_id=<hashid>
```

**الاستجابة**: `{ "0": عدد المرات, ... "23": عدد المرات }` 24 خانة زمنية؛ عند فراغ `game_id` تُحصى جميع الألعاب.

### 16.5 توزيع السلوك

```
GET /admin/v1/analytics/action-distribution?game_id=<hashid>&hours=24
```

**الاستجابة**: `{ "start": n, "end": n, "earn": n, "spend": n }` عدد أربعة أنواع من السلوك؛ حد `hours` هو 168.

### 16.6 نظرة عامة على الإيرادات

```
GET /admin/v1/analytics/revenue?days=7
```

**الاستجابة**: `{ "total": "الإجمالي", "trend": { "التاريخ": "قيمة اليوم", ... } }`، تُحصى طلبات `status=confirmed` فقط.

### 16.7 معدل تحويل الألعاب

```
GET /admin/v1/analytics/conversion?days=30
```

**الاستجابة**: كل لعبة تتضمن `game_id` (hashid) و`game_name` و`players` (عدد اللاعبين الفريدين) و`depositors` (عدد المعبئين الفريدين) و`conversion_rate` (معدل تحويل التعبئة، 0~1).

### 16.8 الاحتمال المشترك

```
GET /admin/v1/analytics/probability?game_a=<hashid>&game_b=<hashid>
```

**الاستجابة**: `{ "joint": { "joint_probability": 0.12, "confidence": 0.3 } }` — معامل Jaccard (اللاعبون المشتركون بين اللعبتين / اتحاد اللاعبين) والثقة (اللاعبون المشتركون / لاعبو اللعبة A).

### 16.9 تحليل الاستبقاء

```
GET /admin/v1/analytics/retention?days=30
```

**الاستجابة**: `{ "D1": "8.5%", "D3": "...", "D7": "...", "D30": "..." }` معدلات الاستبقاء لليوم التالي/3 أيام/7 أيام/30 يومًا حسب مجموعات يوم التسجيل.

### 16.10 قمع التحويل

```
GET /admin/v1/analytics/funnel?days=30
```

**الاستجابة**: الخطوات الأربع التسجيل ← أول تعبئة ← أول صرف ← أول لعبة، مع `step` و`count` و`rate` (نسبة مئوية من عدد التسجيلات).

### 16.11 اتجاه ARPU/ARPPU

```
GET /admin/v1/analytics/arpu?days=30
```

**الاستجابة**: `{ "dates": [...], "arpu": [...], "arppu": [...] }` متوسط الإيراد اليومي لكل مستخدم (ARPU) ومتوسط الإيراد اليومي لكل مستخدم مدفوع (ARPPU).

### 16.12 المؤشرات الاقتصادية للألعاب

```
GET /admin/v1/analytics/economy
```

**الاستجابة**: مصفوفة `currencies`، كل عنصر يتضمن `game_name` و`currency` و`symbol` و`total_minted` (إجمالي السك) و`total_burned` (إجمالي الإتلاف) و`circulation` (حجم التداول) و`inflation_rate` (معدل التضخم)، بحسابات bcmath عالية الدقة.

## 17. إدارة الدفع (Payment)

توفر إدارة طرق الدفع عبر `PaymentController`؛ جميع نقاط النهاية الخمس تتطلب مصادقة JWT + RBAC. القائمة البيضاء لـ `provider`: `stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash`. `config` عبارة عن سلسلة JSON لإعدادات الدفع (مخزنة مشفرة في قاعدة البيانات).

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/v1/payment/method/list | قائمة طرق الدفع (تصاعديًا حسب sort) |
| POST | /admin/v1/payment/method/toggle | تفعيل/تعطيل طريقة دفع |
| POST | /admin/v1/payment/method/create | إنشاء طريقة دفع |
| PUT | /admin/v1/payment/method/{hashid} | تحديث طريقة دفع |
| DELETE | /admin/v1/payment/method/{hashid} | حذف طريقة دفع (مرفوض إذا كانت هناك طلبات معلقة) |

### 17.1 قائمة طرق الدفع

```
GET /admin/v1/payment/method/list
```

- **المصادقة**: JWT + RBAC

**مثال الاستجابة**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "name": "بطاقة ائتمان Stripe",
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

| الحقل | النوع | الوصف |
|------|------|------|
| id | string | معرف طريقة الدفع (مشفر hashid) |
| name | string | اسم طريقة الدفع |
| type | string | `fiat` (عملة ورقية) / `crypto` (عملة رقمية) |
| provider | string | مزود البوابة: `stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash` |
| status | int | 1=مفعل, 0=معطل |
| sort | int | قيمة الترتيب (تصاعديًا) |
| countries | array{string} | مصفوفة رموز الدول المرئية (مصفوفة فارغة = مرئي عالميًا) |
| currency | string | العملة (مثل USD/USDT)، فارغ = بدون قيود |
| min_amount / max_amount | string | نطاق المبالغ (سلسلة تحافظ على الدقة)، 0 = بلا حد |
| config | string? | JSON إعدادات الدفع (مشفر؛ null إذا لم يُحدد) |

### 17.2 تفعيل/تعطيل طريقة دفع

```
POST /admin/v1/payment/method/toggle
```

**نص الطلب**:
```json
{
  "id": "a1b2c3d4",
  "status": 1
}
```

| الحقل | النوع | مطلوب | الوصف |
|------|------|------|------|
| id | string | نعم | معرف طريقة الدفع (hashid) |
| status | int | نعم | 0=معطل, 1=مفعل |

**الأخطاء المحتملة**:
- 422: فشل التحقق (id/status مفقود أو status ليس 0/1)
- 404: طريقة الدفع غير موجودة

### 17.3 إنشاء طريقة دفع

```
POST /admin/v1/payment/method/create
```

**نص الطلب**:
```json
{
  "name": "عملة رقمية USDT",
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

| الحقل | النوع | مطلوب | التحقق | الوصف |
|------|------|------|---------|------|
| name | string | نعم | max:50 | اسم طريقة الدفع |
| type | string | نعم | in:fiat,crypto | النوع: ورقية/رقمية |
| provider | string | نعم | in:stripe,paypal,nowpayments,coinbase,skrill,neteller,paysafecard,paytm,mercadopago,astropay,paypay,kakaopay,gcash | القائمة البيضاء لمزودي البوابة |
| status | int | نعم | in:0,1 | الحالة |
| sort | int | لا | integer,min:0 | الترتيب، الافتراضي 0 |
| countries | array{string} | لا | max:2 | رموز الدول المرئية، فارغ = عالمي |
| currency | string | لا | max:10 | العملة، الافتراضي فارغ |
| min_amount / max_amount | string | لا | numeric,min:0 | نطاق المبالغ، الافتراضي "0" |
| config | string | لا | | JSON إعدادات الدفع (مشفر)؛ السلسلة الفارغة تُخزن كـ NULL |

**مثال الاستجابة**:
```json
{
  "code": 0,
  "message": "تم الإنشاء بنجاح",
  "data": { "id": "e5f6g7h8" }
}
```

**الأخطاء المحتملة**:
- 422: فشل التحقق

### 17.4 تحديث طريقة دفع

```
PUT /admin/v1/payment/method/{hashid}
```

- **معامل المسار**: `{hashid}` هو معرف طريقة الدفع المشفر بـ hashid
- **نص الطلب**: كما في الإنشاء (17.3)، جميع الحقول اختيارية، يتم تحديث الحقول المرسلة فقط

**الأخطاء المحتملة**:
- 404: طريقة الدفع غير موجودة
- 422: فشل التحقق

### 17.5 حذف طريقة دفع

```
DELETE /admin/v1/payment/method/{hashid}
```

- **معامل المسار**: `{hashid}` هو معرف طريقة الدفع المشفر بـ hashid

**الأخطاء المحتملة**:
- 404: طريقة الدفع غير موجودة
- 422: توجد طلبات إيداع معلقة (status=pending)، لا يمكن الحذف
