# 子项目 A：后端增强 — 设计规范
<!-- lang-nav -->

Languages: [中文](2026-05-20-backend-enhancement-design.md) · [English](2026-05-20-backend-enhancement-design.en.md) · [한국어](2026-05-20-backend-enhancement-design.ko.md) · [Русский](2026-05-20-backend-enhancement-design.ru.md) · [Deutsch](2026-05-20-backend-enhancement-design.de.md) · [Français](2026-05-20-backend-enhancement-design.fr.md) · [Español](2026-05-20-backend-enhancement-design.es.md) · [Português](2026-05-20-backend-enhancement-design.pt.md) · [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) · [العربية](2026-05-20-backend-enhancement-design.ar.md) · **বাংলা** · [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) · [日本語](2026-05-20-backend-enhancement-design.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## পরিধি

এটি ব্যাকএন্ড এনহ্যান্সমেন্ট, মোট ১৫টি ফিচার পয়েন্ট, ৯টি নতুন ফাইল + ৪টি পরিবর্তিত ফাইল জড়িত।

---

## নতুন/পরিবর্তিত ফাইলের তালিকা

```
app/middleware/
├── OperationLog.php          # 新增：操作日志自动记录
├── Cors.php                  # 新增：跨域
└── RateLimit.php             # 新增：Redis 限流
app/admin/controller/
├── ConfigController.php      # 新增：系统配置 CRUD
├── LogController.php         # 新增：操作日志查询
├── ProfileController.php     # 新增：个人中心（含登出）
├── UploadController.php      # 新增：文件上传
├── ImportController.php      # 新增：Excel 导入用户
└── HealthController.php      # 新增：健康检查
app/model/
├── AdminUser.php             # 修改：加 SoftDeletes + Searchable trait
└── OperationLog.php          # 修改：加 public $timestamps = false
app/middleware/
└── AdminAuth.php             # 修改：JWT 黑名单校验
app/admin/controller/
├── DashboardController.php   # 修改：改为数据库实时统计
└── UserController.php        # 修改：新增批处理动作
config/
└── route.php                 # 修改：新增路由 + 中间件
```

---

## 1. মিডলওয়্যার

### 1.1 CORS মিডলওয়্যার

**ফাইল**: `app/middleware/Cors.php`

- OPTIONS প্রি-ফ্লাইট অনুরোধ সরাসরি 204 রিটার্ন করে
- প্রি-ফ্লাইট নয় এমন অনুরোধের রেসপন্স হেডারে `Access-Control-Allow-Origin: *` যোগ হয়
- অনুমোদিত হেডার: `Authorization, Content-Type, API-Version`
- সর্বোচ্চ ক্যাশ: 86400 সেকেন্ড

মাউন্ট: গ্লোবাল মিডলওয়্যার (`config/middleware.php`)

### 1.2 রেট লিমিট মিডলওয়্যার

**ফাইল**: `app/middleware/RateLimit.php`

- স্টোরেজ: Redis Sorted Set স্লাইডিং উইন্ডো
- ডিফল্ট: ৬০ বার/মিনিট/IP/রুট
- সংবেদনশীল ইন্টারফেস:
  - `/api/auth/login`: ১০ বার/মিনিট
  - `/api/auth/register`: ৫ বার/মিনিট
- সীমা ছাড়ালে `429 Too Many Requests` রিটার্ন করে

মাউন্ট: গ্লোবাল মিডলওয়্যার (`config/middleware.php`), Cors-এর পরে, ApiVersion-এর আগে

### 1.3 অপারেশন লগ মিডলওয়্যার

**ফাইল**: `app/middleware/OperationLog.php`

- শুধুমাত্র POST/PUT/DELETE রেকর্ড করে
- রেকর্ড করা ক্ষেত্র: user_id, action, method, path, ip, input(JSON)
- রেসপন্স ফেরতের পর অ্যাসিঙ্ক্রোনাসভাবে লেখা হয় (ব্লক করে না)

মাউন্ট: `/admin` রুট গ্রুপে, AdminPermission-এর পরে

### 1.4 গ্লোবাল মিডলওয়্যার এক্সিকিউশন চেইন

```
所有请求:
  Cors → RateLimit → ApiVersion → {Route 中间件} → Controller

/admin/* 请求:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 লগআউট (JWT ব্ল্যাকলিস্ট)

**ফাইল**: `app/middleware/AdminAuth.php` (পরিবর্তিত)

**নীতি**: JWT নিজে state-less; লগআউটের সময় token-কে Redis ব্ল্যাকলিস্টে যোগ করা হয়, AdminAuth যাচাইয়ের সময় আগে ব্ল্যাকলিস্ট পরীক্ষা করে।

**AdminAuth পরিবর্তন**:
- `process()`-এর শুরুতে নতুন: Redis `jwt_blacklist` সেট থেকে বর্তমান token ব্ল্যাকলিস্টে আছে কিনা পরীক্ষা
- ব্ল্যাকলিস্টে থাকলে 401 রিটার্ন

**লগআউট রুট** (ব্যক্তিগত কেন্দ্রের অধীনে):

| মেথড | রুট | বিবরণ |
|------|------|------|
| `POST` | `/admin/profile/logout` | বর্তমান Bearer token-কে Redis ব্ল্যাকলিস্টে যোগ করে, TTL=token-এর অবশিষ্ট বৈধতা সময় |

**Logout লজিক**:
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. নতুন কন্ট্রোলার ও বিদ্যমান পরিবর্তন

### 2.1 সিস্টেম কনফিগারেশন CRUD (`ConfigController`)

`BaseController` থেকে ইনহেরিট করে।

| মেথড | রুট | বিবরণ |
|------|------|------|
| `index()` | GET `/admin/config` | পেজিনেটেড তালিকা, `group` দিয়ে ফিল্টার করা যায়, `page`/`limit` পেজিনেশন |
| `store()` | POST `/admin/config` | কনফিগারেশন আইটেম তৈরি, বাধ্যতামূলক: group, key, value |
| `update()` | PUT `/admin/config/{id}` | কনফিগারেশন আইটেমের value/type/description আপডেট |
| `destroy()` | DELETE `/admin/config/{id}` | কনফিগারেশন আইটেম মুছে ফেলে, `confirmPassword()` প্রয়োজন |

### 2.2 অপারেশন লগ কুয়েরি (`LogController`)

`BaseController` থেকে ইনহেরিট করে।

| মেথড | রুট | বিবরণ |
|------|------|------|
| `index()` | GET `/admin/log` | পেজিনেটেড তালিকা, ফিল্টার সাপোর্ট: user_id, action, path, created_at(পরিসর) |

কোনো যোগ/পরিবর্তন/মুছে ফেলা দেওয়া হয় না, লগ মিডলওয়্যার দ্বারা স্বয়ংক্রিয়ভাবে রেকর্ড হয়।

### 2.3 ব্যক্তিগত কেন্দ্র (`ProfileController`)

`BaseController` থেকে ইনহেরিট করে। বর্তমান লগইন করা ব্যবহারকারীকে (`$request->adminId`) পরিচালনা করে।

| মেথড | রুট | বিবরণ |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | real_name, phone, email আপডেট |
| `updatePassword()` | PUT `/admin/profile/password` | পাসওয়ার্ড পরিবর্তন, old_password, new_password, new_password_confirmation প্রয়োজন |

### 2.4 ফাইল আপলোড (`UploadController`)

`BaseController` থেকে ইনহেরিট করে।

| মেথড | রুট | বিবরণ |
|------|------|------|
| `upload()` | POST `/admin/upload` | ফাইল গ্রহণ করে, image/jpeg/png/gif/pdf/xlsx/docx সাপোর্ট |

- সর্বোচ্চ 10MB
- স্টোরেজ পাথ: `public/upload/{date}/{hash}.{ext}`
- রিটার্ন: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 ড্যাশবোর্ড বাস্তব ডেটা

**ফাইল**: `app/admin/controller/DashboardController.php` (পরিবর্তিত)

বর্তমান হার্ডকোড করা ফেক ডেটাকে ডেটাবেস রিয়েল-টাইম পরিসংখ্যানে পরিবর্তন:

| মেট্রিক | উৎস | বিবরণ |
|------|------|------|
| মোট ব্যবহারকারী | `AdminUser::count()` | সফট ডিলিট বাদে |
| আজকের নতুন | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| মোট ভূমিকা | `AdminRole::count()` | |
| মোট পারমিশন | `AdminPermission::count()` | |
| ট্রেন্ড ডেটা | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | দৈনিক ভিত্তিতে সাম্প্রতিক ৭ দিনের নতুন হিসাব |
| ডিস্ট্রিবিউশন ডেটা | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | স্ট্যাটাস অনুযায়ী বিতরণ |
| সাম্প্রতিক অপারেশন | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | সাম্প্রতিক ১০টি অপারেশন লগ |

### 2.6 ব্যবহারকারী ব্যাচ অপারেশন

**ফাইল**: `app/admin/controller/UserController.php` (পরিবর্তিত, নতুন মেথড)

| মেথড | রুট | বিবরণ |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | ব্যাচ মুছে ফেলা, রিকোয়েস্ট বডি `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | ব্যাচ এনাবল/ডিসেবল, রিকোয়েস্ট বডি `{ ids: [hashid, ...], status: 1|0 }` |

- প্রতিটি id প্রথমে `decodeId()` দিয়ে BIGINT-এ রূপান্তর
- `batchDestroy()` অবশ্যই `confirmPassword()` যাচাইয়ের মধ্য দিয়ে যেতে হবে

### 2.7 ডেটা ইমপোর্ট

**ফাইল**: `app/admin/controller/ImportController.php` (নতুন)

| মেথড | রুট | বিবরণ |
|------|------|------|
| `users()` | POST `/admin/import/users` | Excel ফাইল আপলোড, ব্যাচে ব্যবহারকারী তৈরি |

প্রক্রিয়া:
1. `.xlsx` ফাইল গ্রহণ
2. PhpSpreadsheet পার্সিং, প্রত্যাশিত কলাম: `username, password, real_name, phone, email, status`
3. সারি প্রতি যাচাই + তৈরি (snowflake দিয়ে ID, bcrypt পাসওয়ার্ড, encryption দিয়ে phone/email এনক্রিপ্ট)
4. ফলাফল রিটার্ন: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 স্বাস্থ্য পরীক্ষা

**ফাইল**: `app/admin/controller/HealthController.php` (নতুন)

`GET /health` (অথেনটিকেশন ছাড়া, অপারেশন লগে ধরা হয় না):

প্রতিটি কম্পোনেন্টের সংযোগ অবস্থা রিটার্ন করে:
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

- কম্পোনেন্ট চেক ব্যর্থ হলে সংশ্লিষ্ট ফিল্ডের মান ত্রুটি বিবরণ স্ট্রিং হয়
- রুট `/admin` প্রিফিক্সের অধীনে নয়, আলাদাভাবে গ্লোবালে রেজিস্টার করা হয়

---

## 3. মডেল সংশোধন

### 3.1 OperationLog টাইমস্ট্যাম্প

**ফাইল**: `app/model/OperationLog.php` (পরিবর্তিত)

টেবিল `game_operation_log`-এ শুধুমাত্র `created_at` কলাম আছে (`updated_at` নেই)। Eloquent-এর ডিফল্ট `save()` `updated_at` লেখার চেষ্টা করে, যার ফলে SQL ত্রুটি হয়।

ফিক্স: `public $timestamps = false;` + লেখার সময় ম্যানুয়ালি `created_at` নির্ধারণ।

### 3.2 AdminUser মডেল পরিবর্তন

- `Searchable` trait যোগ
- `toSearchableArray()` বাস্তবায়ন: username, real_name রিটার্ন করে
- `UserController::index()` কীওয়ার্ড শনাক্ত করলে MySQL LIKE-এর বদলে `AdminUser::search($kw)->get()` ব্যবহার করে

ES-এ আগে ইন্ডেক্স তৈরি করতে হবে, Scout কমান্ড দিয়ে:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. রুট পরিবর্তন

`config/route.php`-এ নতুন রুট:

```php
// /admin 路由组内新增:
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

// 健康检查（全局路由，非 /admin 组内）
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// 中间件:
/admin 组中间件追加 app\middleware\OperationLog::class
```

`config/middleware.php` গ্লোবাল মিডলওয়্যার রেজিস্টার:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. ত্রুটি কোড সংযোজন

| code | অর্থ | ট্রিগার পরিস্থিতি |
|------|------|---------|
| 429 | অনুরোধ খুব ঘন ঘন | RateLimit ট্রিগার |

---

## 6. এই পরিসরে অন্তর্ভুক্ত নয়

- নোটিফিকেশন সিস্টেম (মেসেজ কিউ + ফ্রন্টএন্ড পুশ অবকাঠামো প্রয়োজন)
- Flutter ফ্রন্টএন্ড পেজ (সাবপ্রজেক্ট B)
- HarmonyOS Token রিফ্রেশ (সাবপ্রজেক্ট C)
