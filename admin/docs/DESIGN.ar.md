# 开放管理后台 — 设计文档
<!-- lang-nav -->

Languages: **中文** · [English](DESIGN.en.md) · [한국어](DESIGN.ko.md) · [Русский](DESIGN.ru.md) · [Deutsch](DESIGN.de.md) · [Français](DESIGN.fr.md) · [Español](DESIGN.es.md) · [Português](DESIGN.pt.md) · [हिन्दी](DESIGN.hi.md) · [العربية](DESIGN.ar.md) · [বাংলা](DESIGN.bn.md) · [Bahasa Indonesia](DESIGN.id.md) · [日本語](DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> لمخططات Mermaid التفصيلية راجع [ARCHITECTURE.ar.md](ARCHITECTURE.ar.md) (تُعرض تلقائيًا في GitHub/GitLab/VS Code).

## 1. بنية النظام

> **قائمة الميزات**: المصادقة(login/register/refresh/logout + قفل الحساب + تقييد الجلسات) | لوحة المعلومات(تخزين مؤقت في Redis) | CRUD للمستخدمين + جماعي + استيراد | صلاحيات الأدوار(RBAC) | إعدادات النظام | تدقيق العمليات(8 منصات مصدر) | الملفات(رفع+تصدير+إخفاء) | الأمان(18 طبقة دفاع) | التشغيل(health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        طبقة العملاء                          │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  لوحة إدارة (نمط مكتبي) │  │  عميل (هاتف/لوحي/2in1)       │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   طبقة بوابة API                     │    │
│  │  AdminAuth(مصادقة) → AdminPermission(تفويض) → Controller │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │           طبقة منطق الأعمال (Controller/Service)       │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                    طبقة Model                           │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (توليد المفتاح)  (تشفير حقول DB)  (تشفير نقل API) │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │                 طبقة تخزين البيانات                   │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (تخزين رئيسي)│ (بحث نصي كامل) │  │ (تخزين مؤقت)│        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. بنية الواجهة الخلفية

### 2.1 التصميم الطبقي

| الطبقة | الدليل | المسؤولية |
|---|------|------|
| المسارات | `config/route.php` | تخطيط URL إلى وحدات التحكم، ربط الوسيطات، مسارات منسوخة حسب الإصدار |
| الوسيطات | `app/middleware/` | اعتراض الهجمات(SecurityFilter)، الحد من المعدل(RateLimit)، المصادقة(JWT)، التفويض(RBAC)، إصدار API(ApiVersion) |
| وحدات التحكم | 30 وحدة: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs/Metrics/Analytics/Game/Payment/Withdraw... (الإدارة) + Captcha/Auth (API v1) | التحقق من معاملات الطلب، استدعاء منطق الأعمال، تنسيق الاستجابة |
| خدمات الأعمال | `common/service/` | تحليل البيانات: GameDashboardService (نظرة عامة/ترتيب/اتجاه)، DepositLogService (إيراد/تحويل)، ProbabilityService (احتمال مشترك/شرطي، منشئ SQL)؛ عند تعطل DB تُرجع بيانات فارغة بدلاً من خطأ |
| نماذج البيانات | `app/model/` | تعيين ORM، علاقات الارتباط، تشفير/فك تشفير الحقول |
| الأدوات العامة | `app/common/` | خدمات Hashids وSnowflake وEncryption |

### 2.2 دورة حياة الطلب

```
طلب العميل
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
مطابقة Route
  │
  ▼
سلسلة الوسيطات:
  SecurityFilter ──────► فحص طريقة HTTP ← 405 (يُسمح فقط بـ GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     اعتراض هجمات XSS/حقن SQL/اجتياز المسار/حقن الأوامر/CSRF (403)
  ▼
  RateLimit ───────────► نافذة منزلقة في Redis
  │ (عند الفشل يُرجع 429 + ترويسة Retry-After)
  ▼
  ApiVersion ─────────► التحقق من ترويسة API-Version، حقن $request->apiVersion
  │ (عند الفشل يُرجع 400)
  ▼
  AdminAuth ──────────► التحقق من JWT، حقن $request->adminId
  │ (عند الفشل يُرجع 401)
  ▼
  AdminPermission ────► التحقق من صلاحيات RBAC (تخزين مؤقت في Redis 60s)
  │ (عند الفشل يُرجع 403)
  ▼
  OperationLog ───────► تسجيل سجل العملية (POST/PUT/DELETE)، كشف تلقائي لطرف المصدر
  │
  ▼
Controller::method()
  │
  ├─► التحقق من المعاملات (validator)
  ├─► تأكيد العمليات الحساسة (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► عمليات Model (تشفير/فك تشفير تلقائي عبر encryptable)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 دورة حياة المعرف

```
التوليد (Snowflake) → التخزين (MySQL BIGINT) → النقل (ترميز Hashids) → الخارجي (سلسلة hash)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 نظام تشفير البيانات

```
طبقة النقل (encryption)     — AES-256-CBC، مفتاح مستقل
طبقة التخزين (encryptable)  — AES-128-ECB، مفتاح مستقل، معالجة تلقائية عبر Model $casts
طبقة العرض (mask)           — الهاتف: 138****1234، البريد: a***@example.com
```

## 3. تصميم قاعدة البيانات

### 3.1 علاقات ER

```
game_admin_user ──┬── game_admin_user_role ──┬── game_admin_role
  (المستخدمون)     │    (ربط المستخدم-الدور)    │     (الأدوار)
                  │                          │
                  │                    game_admin_role_permission
                  │                     (ربط الدور-الصلاحية)
                  │                          │
                  │                          ▼
                  │                    game_admin_permission
                  │                      (الصلاحيات/القوائم)
                  │
                  ▼
           game_operation_log
             (سجلات العمليات)

game_system_config (إعدادات النظام) — جدول مستقل
```

### 3.2 بنية الجداول الأساسية

| اسم الجدول | عدد الحقول | الوصف |
|------|-------|------|
| `game_admin_user` | 14 | مستخدمو الإدارة، phone/email/id_card مخزنة مشفرة، تدعم الحذف الناعم |
| `game_admin_role` | 7 | الأدوار، slug فريد |
| `game_admin_permission` | 10 | شجرة الصلاحيات (parent_id مرجع ذاتي)، type: 1=قائمة 2=زر 3=API |
| `game_admin_user_role` | 2 | جدول وسيط متعدد-متعدد للمستخدم-الدور |
| `game_admin_role_permission` | 2 | جدول وسيط متعدد-متعدد للدور-الصلاحية |
| `game_system_config` | 8 | إعدادات أزواج مفتاح-قيمة، group+key فريدان معًا |
| `game_operation_log` | 9 | سجل تدقيق العمليات (بما فيه source طرف المصدر) |

### 3.3 مواصفات المفتاح الأساسي

- النوع: `BIGINT UNSIGNED NOT NULL`
- الخصائص: **غير متزايد ذاتيًا**، يولَّد بخوارزمية Snowflake على طبقة التطبيق
- المزايا: فريد عالميًا، صديق للتوزيع، تزايد اتجاهي يساعد الفهارس، لا يكشف حجم الأعمال
- التكوين: datacenter_id(0-31) + worker_id(0-31)، يدعم 1024 عقدة متزامنة

## 4. تصميم API

### 4.1 مواصفات URL

```
واجهات عامة:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

لوحة الإدارة: /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

مسارات الموارد:
  GET    /admin/user          → القائمة
  POST   /admin/user          → الإنشاء
  GET    /admin/user/{hashid} → التفاصيل
  PUT    /admin/user/{hashid} → التحديث
  DELETE /admin/user/{hashid} → الحذف (يتطلب تأكيد كلمة المرور)

إعدادات النظام:  /admin/config[/{hashid}]
سجلات العمليات:  /admin/log
المركز الشخصي:  /admin/profile[/password|/logout]
الاستيراد:     /admin/import/users
الرفع:     /admin/upload
الجماعي:     /admin/user/batch/{destroy|status}
الوثائق:     /api/docs     (OpenAPI 3.0)
الصحة:     /health
```

### 4.2 استراتيجية إصدار API

يُتحكم بإصدار API عبر ترويسة الطلب، **ولا يظهر في مسار URL**:

```http
API-Version: v1
```

| الآلية | الوصف |
|------|------|
| الإصدار الافتراضي | عند عدم حمل ترويسة `API-Version` يكون الافتراضي `v1` |
| التحقق | تتحقق وسيطة `ApiVersion`، والإصدارات غير المدعومة تُرجع 400 |
| التوجيه | تحلل الدالة المساعدة `v()` فئات وحدات التحكم ديناميكيًا حسب الإصدار |
| الدليل | تُنظم وحدات التحكم حسب الإصدار: `app/api/{version}/controller/` |

مثال على التوسعة — إضافة v2 API:
1. إنشاء `app/api/v2/controller/AuthController.php`
2. إضافة `'v2'` إلى ثابت `SUPPORTED` في وسيطة `ApiVersion`
3. لا حاجة لتعديل تعريفات المسارات

```bash
# استخدام v1
curl -H "API-Version: v1" /api/auth/login

# استخدام v2
curl -H "API-Version: v2" /api/auth/login

# بدون إرسال، الافتراضي v1
curl /api/auth/login
```

### 4.3 استراتيجية الحد من المعدل

بناءً على خوارزمية النافذة المنزلقة عبر Redis Sorted Set، تنفيذ ذري عبر Lua:

| الواجهة | الحد |
|------|------|
| الافتراضي | 60 مرة/دقيقة/IP/مسار |
| POST /api/auth/login | 10 مرات/دقيقة |
| POST /api/auth/register | 5 مرات/دقيقة |

عند تجاوز الحد يُرجع 429، وتتضمن ترويسات الاستجابة X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 الاستجابة الموحدة

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | المعنى | سيناريو الإطلاق |
|------|------|---------|
| 0 | نجاح | استجابة طبيعية |
| 400 | خطأ في المعاملات | تنسيق الطلب غير صحيح |
| 401 | غير مصادق | رمز مفقود/منتهي/غير صالح |
| 403 | لا صلاحية | دور المستخدم لا يتضمن الصلاحية المطلوبة |
| 404 | غير موجود | المورد غير موجود |
| 422 | فشل التحقق | معاملات النموذج لا تطابق القواعد / فشل تأكيد كلمة المرور |
| 500 | خطأ في الخادم | استثناء غير متوقع |

### 4.5 تدفق المصادقة (مع رمز التحقق بالنقر)

```
العميل                                الخادم
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② ينقر المستخدم على مواضع النص في الصورة │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 نموذج الصلاحيات (RBAC)

```
  المستخدم ──┬── الدور ──┬── الصلاحية
  User     Role      Permission
                 │
                 ├── type=1: قائمة (التحكم في ظهور الشريط الجانبي)
                 ├── type=2: زر (التحكم في العمليات داخل الصفحة)
                 └── type=3: API (التحكم في الوصول إلى الواجهات)

تنسيق معرّف الصلاحية: {method}.{path}
مثال: get.admin/user  post.admin/user  delete.admin/user
معرّف المدير الفائق: * (يتجاوز جميع فحوصات الصلاحيات)
```

### 4.7 التأكيد الثانوي للعمليات الحساسة

تتطلب العمليات الحساسة مثل حذف المستخدمين والأدوار والصلاحيات تمرير كلمة مرور المستخدم الحالي في جسم الطلب لإعادة التحقق من الهوية:

```
العميل                            الخادم
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → كلمة مرور خاطئة تُرجع 422
  │                                │ → كلمة مرور صحيحة يستمر التنفيذ
  │◄── 200 { code: 0 }           │
```

تظهر الواجهة الأمامية مربع حوار تأكيد قبل تنفيذ الحذف، وتجمع كلمة مرور المستخدم ثم ترسل الطلب.

### 4.8 إدارة طرق الدفع

توفر وحدة إدارة طرق الدفع (`PaymentController` + Flutter `payment_page.dart`) 5 نقاط نهاية، جميعها تتطلب مصادقة JWT + RBAC:

| الطريقة | المسار | الوصف |
|------|------|------|
| GET | /admin/payment/method/list | القائمة (تصاعديًا حسب sort) |
| POST | /admin/payment/method/toggle | تفعيل/تعطيل |
| POST | /admin/payment/method/create | إنشاء |
| PUT | /admin/payment/method/{hashid} | تحديث (الحقول المرسلة فقط) |
| DELETE | /admin/payment/method/{hashid} | حذف (422 إذا كانت هناك طلبات معلقة) |

- **القائمة البيضاء لـ provider**: `stripe` / `nowpayments` / `coinbase`
- **الحقول**: name / type (fiat|crypto) / provider / status / sort / countries[] (الرؤية حسب الدولة، فارغ = عالمي) / currency / min_amount / max_amount / config (JSON، مخزن مشفر)
- **حماية الحذف**: الحذف يُرجع 422 ما دامت هناك طلبات بحالة pending
- **الواجهة الأمامية**: Flutter `payment_page.dart` — قائمة + نافذة إنشاء/تعديل + مفتاح تفعيل/تعطيل

## 5. تصميم الواجهة الأمامية

### 5.1 لوحة إدارة Flutter Web

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ زر القائمة           🔔 رسائل  👤 المدير  ▼ │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 لوحة  │  │ بطاقات إحصائية ×4│ │ مخطط اتجاه│     │
│ 👥 المستخدمون │  └──────────────┘ └──────────┘     │
│ 🔒 الأدوار │  ┌──────┐ ┌────────────────┐       │
│ ⚙ الإعدادات │  │مخطط دائري│ │ أحدث سجلات العمليات│       │
│ 📋 السجلات │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

الخصائص: شريط جانبي قابل للطي، سمتان في Material 3، جدول بيانات عالي الكثافة، مربعات حوار Dialog، تفاعل بالتحويم

### 5.2 هاتف HarmonyOS

توجيه الصفحات:

| الصفحة | المسار | الوصف |
|------|------|------|
| LoginPage | `pages/LoginPage` | اسم المستخدم وكلمة المرور + تسجيل الدخول برمز التحقق بالنقر |
| DashboardPage | `pages/DashboardPage` | بطاقات إحصائية + أحدث العمليات |
| UserListPage | `pages/UserListPage` | قائمة المستخدمين، بحث + سحب للتحديث + تمرير لأعلى للتحميل |
| UserDetailPage | `pages/UserDetailPage` | إضافة/تعديل/عرض/حذف (تأكيد عبر AlertDialog) |
| ProfilePage | `pages/ProfilePage` | المركز الشخصي، تسجيل الخروج (تأكيد عبر AlertDialog) |

تدفق البيانات: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. التصميم الأمني

### 6.1 الدفاع المتعمق

| الطبقة | الإجراء |
|------|------|
| تقييد الطرق | قائمة بيضاء لطرق HTTP في SecurityFilter، يُسمح فقط بـ GET/POST/PUT/DELETE/OPTIONS/HEAD، الطرق غير القياسية تُرجع 405 |
| اعتراض الهجمات | وسيطة SecurityFilter، كشف واعتراض XSS/حقن SQL/اجتياز المسار/حقن الأوامر/CSRF |
| التحقق البشري | رمز التحقق بالنقر (Click Captcha)، إلزامي لتسجيل الدخول/التسجيل |
| قفل الحساب | 5 محاولات تسجيل دخول فاشلة متتالية تقفل الحساب 15 دقيقة، وخلالها يُرجع 429 |
| تقييد الجلسات | 3 رموز متزامنة كحد أقصى لكل مستخدم، وعند التجاوز يُضاف الأقدم تلقائيًا إلى القائمة السوداء |
| الحد من المعدل | وسيطة RateLimit، نافذة منزلقة في Redis، ذرّية عبر Lua |
| CSP | ترويسة Content-Security-Policy تقيد مصادر الموارد، تمنع XSS وحقن البيانات |
| تأكيد العمليات | العمليات الحساسة مثل الحذف تتطلب إدخال كلمة مرور المستخدم الحالي ثانويًا |
| النقل | HTTPS + JWT Bearer Token |
| معرفات الواجهة | تشفير Hashids، لا يمكن استنتاج المعرف الحقيقي عكسيًا خارجيًا |
| جسم الطلب | تشفير AES-256-CBC للحقول الحساسة |
| قاعدة البيانات | مفتاح أساسي BIGINT (لا يكشف قيمة التزايد الذاتي) |
| قاعدة البيانات | تشفير AES-128-ECB وتخزين الحقول الحساسة |
| المصادقة | JWT HS256، انتهاء بعد ساعتين + refresh token |
| التفويض | RBAC، تحكم بالصلاحيات على مستوى method.path |
| التدقيق | يسجل OperationLog جميع العمليات (بما فيه الكشف التلقائي لطرف المصدر source) |

### 6.2 إدارة المفاتيح

```
JWT_SECRET          → حقن عبر متغير البيئة، سلسلة عشوائية بـ 64 حرفًا
HASHIDS_SALT        → ملح فريد، عند تسريبه يجب التغيير عالميًا
ENCRYPTION_KEY      → مفتاح تشفير نقل API، 32 بايت
ENCRYPTABLE_KEY     → مفتاح تشفير تخزين DB، مستقل عن مفتاح النقل
SCOUT_HOSTS         → عنوان ES، نشر داخلي
```

### 6.3 حماية البيانات الحساسة

| السيناريو | الحقل | الإجراء |
|------|------|------|
| عرض القائمة | phone | إخفاء: 138****1234 |
| عرض القائمة | email | إخفاء: a***@example.com |
| عرض التفاصيل | phone/email | يتطلب واجهة فك تشفير |
| تصدير Excel | phone/email | يُصدَّر بعد الإخفاء |
| تصدير PDF | كل الحقول | إخفاء + علامة حقوق نشر غير قابلة للإزالة |
| التخزين | phone/email/id_card | تشفير encryptable إلى نص مشفر |

## 7. تصميم التصدير

### 7.1 تصدير Excel

```
الطلب: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() استعلام البيانات (limit 10000)
  → إخفاء الحقول الحساسة
  → بناء PhpSpreadsheet (ترويسة بخلفية زرقاء ونص أبيض + تجميد الصف الأول + تصفية تلقائية)
  → الكتابة إلى runtime/tmp/ → استجابة download
```

### 7.2 تصدير PDF

```
الطلب: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + CSS مضمّن + حقوق نشر في الترويسة + حقوق نشر غير قابلة للإزالة في التذييل
  → عرض Dompdf A4 أفقي
  → الكتابة إلى runtime/tmp/ → استجابة download
```

## 8. بنية النشر

### 8.1 الطوبولوجيا الموصى بها

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                    الملفات الثابتة: Flutter Web build/
```

### 8.2 Docker Compose (موصى به للإنتاج)

ينظم `docker-compose.yml` في دليل جذر المشروع جميع خدمات الطوبولوجيا المذكورة:

| الخدمة | الصورة/البناء | المنفذ | الوصف |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | وكيل عكسي + ملفات ثابتة + Gzip |
| `app` | بناء محلي عبر `Dockerfile` | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | قاعدة البيانات الرئيسية، استمرارية عبر أحجام البيانات |
| `redis` | redis:7-alpine | 6379 | تخزين مؤقت / حد من المعدل / رموز تحقق |
| `elasticsearch` | elasticsearch:8.x | 9200 | بحث نصي كامل |

قبل الإقلاع، استبدل مفاتيح `JWT_SECRET` و`HASHIDS_SALT` و`ENCRYPTION_KEY` وغيرها في `docker-compose.yml` بسلاسل عشوائية.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

يُعرَّف التكامل المستمر في GitHub Actions عبر `.github/workflows/ci.yml`:
- فحص صياغة PHP (`php -l`)
- اختبارات PHPUnit الوحدوية
- التحليل الساكن لـ Flutter (`flutter analyze`)

### 8.4 النسخ الاحتياطي لقاعدة البيانات

`database/backup/backup.sh` — نسخ احتياطي عبر mysqldump + gzip، تنظيف تلقائي للنسخ الأقدم من 30 يومًا.
`database/backup/restore.sh` — اختيار تفاعلي واستعادة النسخ الاحتياطي.

### 8.5 المراقبة

يكشف نقطة النهاية `GET /metrics` (عبر `MetricsController`) 5 مقاييس gauge بصيغة Prometheus text: إجمالي طلبات HTTP، عدد المستخدمين النشطين، حالة اتصال قاعدة البيانات/Redis، استخدام الذاكرة.

### 8.6 المتطلبات البيئية

| المكوّن | الحد الأدنى | التكوين الموصى به |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ OPcache مفعّل |
| MySQL | 8.0+ | 8.0+ نسخ رئيسي-تابع |
| Elasticsearch | 7.x | 8.x كتلة من 3 عقد |
| Redis | 6.x | 7.x وضع الحراسة |
| Nginx | 1.20+ | وكيل عكسي + gzip + SSL |
| Flutter SDK | 3.41+ | أحدث إصدار مستقر |
| HarmonyOS | API 12 | DevEco Studio 5.x |
