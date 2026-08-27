# المشروع الفرعي أ: تحسينات الخلفية — مواصفات التصميم
<!-- lang-nav -->

Languages: **中文** · [English](2026-05-20-backend-enhancement-design.en.md) · [한국어](2026-05-20-backend-enhancement-design.ko.md) · [Русский](2026-05-20-backend-enhancement-design.ru.md) · [Deutsch](2026-05-20-backend-enhancement-design.de.md) · [Français](2026-05-20-backend-enhancement-design.fr.md) · [Español](2026-05-20-backend-enhancement-design.es.md) · [Português](2026-05-20-backend-enhancement-design.pt.md) · [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) · [العربية](2026-05-20-backend-enhancement-design.ar.md) · [বাংলা](2026-05-20-backend-enhancement-design.bn.md) · [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) · [日本語](2026-05-20-backend-enhancement-design.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## النطاق

هذه المرة تحسينات للخلفية، بإجمالي 15 نقطة وظيفية، تشمل 9 ملفات جديدة + 4 ملفات معدّلة.

---

## قائمة الملفات الجديدة/المعدّلة

```
app/middleware/
├── OperationLog.php          # جديد: تسجيل العمليات تلقائيًا
├── Cors.php                  # جديد: مشاركة الموارد عبر الأصول (CORS)
└── RateLimit.php             # جديد: تقييد المعدل عبر Redis
app/admin/controller/
├── ConfigController.php      # جديد: CRUD إعدادات النظام
├── LogController.php         # جديد: الاستعلام عن سجلات العمليات
├── ProfileController.php     # جديد: الملف الشخصي (يشمل تسجيل الخروج)
├── UploadController.php      # جديد: رفع الملفات
├── ImportController.php      # جديد: استيراد المستخدمين من Excel
└── HealthController.php      # جديد: فحص الصحة
app/model/
├── AdminUser.php             # تعديل: إضافة SoftDeletes + Searchable trait
└── OperationLog.php          # تعديل: إضافة public $timestamps = false
app/middleware/
└── AdminAuth.php             # تعديل: التحقق من قائمة JWT السوداء
app/admin/controller/
├── DashboardController.php   # تعديل: إحصائيات حقيقية من قاعدة البيانات
└── UserController.php        # تعديل: إضافة عمليات جماعية
config/
└── route.php                 # تعديل: إضافة مسارات + وسيطة
```

---

## 1. الوسائط

### 1.1 وسيطة CORS

**الملف**: `app/middleware/Cors.php`

- طلبات OPTIONS الاختبارية تُرجع 204 مباشرة
- الطلبات غير الاختبارية تُضيف `Access-Control-Allow-Origin: *` إلى رأس الاستجابة
- الرؤوس المسموحة: `Authorization, Content-Type, API-Version`
- الحد الأقصى للتخزين المؤقت: 86400 ثانية

التركيب: وسيطة عامة (`config/middleware.php`)

### 1.2 وسيطة تقييد المعدل

**الملف**: `app/middleware/RateLimit.php`

- التخزين: نافذة منزلقة عبر Redis Sorted Set
- الافتراضي: 60 طلبًا/دقيقة/IP/مسار
- الواجهات الحساسة:
  - `/api/auth/login`: 10 طلبات/دقيقة
  - `/api/auth/register`: 5 طلبات/دقيقة
- تجاوز الحد يُرجع `429 Too Many Requests`

التركيب: وسيطة عامة (`config/middleware.php`)، بعد Cors وقبل ApiVersion

### 1.3 وسيطة سجل العمليات

**الملف**: `app/middleware/OperationLog.php`

- يسجل فقط POST/PUT/DELETE
- الحقول المسجلة: user_id, action, method, path, ip, input(JSON)
- يُكتب بشكل غير متزامن بعد إرجاع الاستجابة (لا يحجب)

التركيب: مجموعة مسارات `/admin`، بعد AdminPermission

### 1.4 سلسلة تنفيذ الوسائط العامة

```
جميع الطلبات:
  Cors → RateLimit → ApiVersion → {وسيطة المسار} → Controller

طلبات /admin/*:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 تسجيل الخروج (قائمة JWT السوداء)

**الملف**: `app/middleware/AdminAuth.php` (تعديل)

**المبدأ**: JWT بلا حالة بطبيعته؛ عند تسجيل الخروج يُضاف token إلى القائمة السوداء في Redis، وعند التحقق يفحص AdminAuth القائمة السوداء أولًا.

**تعديل AdminAuth**:
- إضافة في بداية `process()`: فحص ما إذا كان token الحالي في مجموعة `jwt_blacklist` بـ Redis
- إذا وُجد في القائمة السوداء يُرجع 401

**مسار تسجيل الخروج** (ضمن الملف الشخصي):

| الطريقة | المسار | الوصف |
|------|------|------|
| `POST` | `/admin/profile/logout` | إضافة Bearer token الحالي إلى القائمة السوداء بـ Redis، TTL=المدة المتبقية لصلاحية token |

**منطق Logout**:
```php
// تحليل المدة المتبقية لصلاحية token
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// إضافة إلى القائمة السوداء
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. وحدات التحكم الجديدة وتعديلات القائمة

### 2.1 CRUD إعدادات النظام (`ConfigController`)

ترث من `BaseController`.

| الطريقة | المسار | الوصف |
|------|------|------|
| `index()` | GET `/admin/config` | قائمة صفحات، يمكن التصفية حسب `group`، ترقيم صفحات `page`/`limit` |
| `store()` | POST `/admin/config` | إنشاء عنصر إعداد، الحقول الإلزامية: group, key, value |
| `update()` | PUT `/admin/config/{id}` | تحديث value/type/description لعنصر الإعداد |
| `destroy()` | DELETE `/admin/config/{id}` | حذف عنصر الإعداد، يتطلب `confirmPassword()` |

### 2.2 الاستعلام عن سجلات العمليات (`LogController`)

ترث من `BaseController`.

| الطريقة | المسار | الوصف |
|------|------|------|
| `index()` | GET `/admin/log` | قائمة صفحات، تصفية حسب: user_id, action, path, created_at(نطاق) |

لا توجد إضافة/تعديل/حذف؛ السجلات تُسجَّل تلقائيًا بواسطة الوسيطة.

### 2.3 الملف الشخصي (`ProfileController`)

ترث من `BaseController`. تعمل على المستخدم المسجل حاليًا (`$request->adminId`).

| الطريقة | المسار | الوصف |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | تحديث real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | تغيير كلمة المرور، يتطلب old_password, new_password, new_password_confirmation |

### 2.4 رفع الملفات (`UploadController`)

ترث من `BaseController`.

| الطريقة | المسار | الوصف |
|------|------|------|
| `upload()` | POST `/admin/upload` | استقبال ملف، يدعم image/jpeg/png/gif/pdf/xlsx/docx |

- الحد الأقصى 10MB
- مسار التخزين: `public/upload/{date}/{hash}.{ext}`
- الإرجاع: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 بيانات حقيقية للوحة التحكم

**الملف**: `app/admin/controller/DashboardController.php` (تعديل)

تحويل البيانات الوهمية المرمّزة حاليًا إلى إحصائيات حقيقية من قاعدة البيانات:

| المؤشر | المصدر | الوصف |
|------|------|------|
| إجمالي المستخدمين | `AdminUser::count()` | دون الحذف الناعم |
| الجدد اليوم | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| إجمالي الأدوار | `AdminRole::count()` | |
| إجمالي الصلاحيات | `AdminPermission::count()` | |
| بيانات الاتجاه | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | إحصاء الجدد يوميًا لآخر 7 أيام |
| بيانات التوزيع | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | التوزيع حسب الحالة |
| أحدث العمليات | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | آخر 10 سجلات عمليات |

### 2.6 العمليات الجماعية على المستخدمين

**الملف**: `app/admin/controller/UserController.php` (تعديل، إضافة طرق)

| الطريقة | المسار | الوصف |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | حذف جماعي، الجسم `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | تفعيل/تعطيل جماعي، الجسم `{ ids: [hashid, ...], status: 1|0 }` |

- كل id يُحوَّل أولًا عبر `decodeId()` إلى BIGINT
- `batchDestroy()` يجب أن يمر عبر تحقق `confirmPassword()`

### 2.7 استيراد البيانات

**الملف**: `app/admin/controller/ImportController.php` (جديد)

| الطريقة | المسار | الوصف |
|------|------|------|
| `users()` | POST `/admin/import/users` | رفع ملف Excel وإنشاء مستخدمين جماعيًا |

الخطوات:
1. استقبال ملف `.xlsx`
2. تحليل PhpSpreadsheet، الأعمدة المتوقعة: `username, password, real_name, phone, email, status`
3. تحقق + إنشاء صفًا بصف (توليد ID بـ snowflake، كلمات مرور bcrypt، تشفير phone/email بـ encryption)
4. إرجاع النتيجة: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "اسم المستخدم موجود بالفعل"}, ...] }`

### 2.8 فحص الصحة

**الملف**: `app/admin/controller/HealthController.php` (جديد)

`GET /health` (بدون مصادقة، لا يُسجل في سجل العمليات):

إرجاع حالة اتصال كل مكوّن:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- عند فشل فحص مكوّن تكون قيمة الحقل المقابل سلسلة وصف الخطأ
- المسار لا يحمل بادئة `/admin`، يُسجَّل بشكل منفصل في المسارات العامة

---

## 3. تصحيحات النماذج

### 3.1 الطوابع الزمنية في OperationLog

**الملف**: `app/model/OperationLog.php` (تعديل)

جدول `game_operation_log` يحتوي عمود `created_at` فقط (دون `updated_at`). `save()` الافتراضي في Eloquent سيحاول كتابة `updated_at`، مما يسبب خطأ SQL.

الإصلاح: `public $timestamps = false;` + تحديد `created_at` يدويًا عند الكتابة.

### 3.2 تعديل نموذج AdminUser

- إضافة trait `Searchable`
- تنفيذ `toSearchableArray()`: يعيد username, real_name
- عند كشف كلمة مفتاحية في `UserController::index()` يُستخدم `AdminUser::search($kw)->get()` بدل LIKE في MySQL

يجب إنشاء الفهرس في ES أولًا، عبر أوامر Scout:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. تغييرات المسارات

إضافة مسارات في `config/route.php`:

```php
// إضافة داخل مجموعة /admin:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// فحص الصحة (مسار عام، خارج مجموعة /admin)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// الوسائط:
إضافة app\middleware\OperationLog::class إلى وسائط مجموعة /admin
```

تسجيل الوسائط العامة في `config/middleware.php`:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. إضافة رموز الأخطاء

| code | المعنى | سيناريو الظهور |
|------|------|---------|
| 429 | طلبات كثيرة جدًا | تفعيل RateLimit |

---

## 6. خارج نطاق هذه المرة

- نظام الإشعارات (يتطلب قائمة رسائل + بنية تحتية لدفع الواجهة الأمامية)
- صفحات Flutter الأمامية (المشروع الفرعي ب)
- تحديث Token في HarmonyOS (المشروع الفرعي ج)
