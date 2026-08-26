# Subproject A: Backend Enhancement — Design Spec
<!-- lang-nav -->

Languages: [中文](2026-05-20-backend-enhancement-design.md) · **English** · [한국어](2026-05-20-backend-enhancement-design.ko.md) · [Русский](2026-05-20-backend-enhancement-design.ru.md) · [Deutsch](2026-05-20-backend-enhancement-design.de.md) · [Français](2026-05-20-backend-enhancement-design.fr.md) · [Español](2026-05-20-backend-enhancement-design.es.md) · [Português](2026-05-20-backend-enhancement-design.pt.md) · [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) · [العربية](2026-05-20-backend-enhancement-design.ar.md) · [বাংলা](2026-05-20-backend-enhancement-design.bn.md) · [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) · [日本語](2026-05-20-backend-enhancement-design.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Scope

This is a backend enhancement with 15 feature points total, involving 9 new files + 4 modified files.

---

## List of New/Modified Files

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

## 1. Middleware

### 1.1 CORS Middleware

**File**: `app/middleware/Cors.php`

- OPTIONS preflight requests return 204 directly
- Non-preflight requests append `Access-Control-Allow-Origin: *` to response headers
- Allowed headers: `Authorization, Content-Type, API-Version`
- Max cache: 86400 seconds

Mount: global middleware (`config/middleware.php`)

### 1.2 Rate Limit Middleware

**File**: `app/middleware/RateLimit.php`

- Storage: Redis Sorted Set sliding window
- Default: 60 requests/minute/IP/route
- Sensitive endpoints:
  - `/api/auth/login`: 10 requests/minute
  - `/api/auth/register`: 5 requests/minute
- Over limit returns `429 Too Many Requests`

Mount: global middleware (`config/middleware.php`), after Cors and before ApiVersion

### 1.3 Operation Log Middleware

**File**: `app/middleware/OperationLog.php`

- Records only POST/PUT/DELETE
- Recorded fields: user_id, action, method, path, ip, input(JSON)
- Written asynchronously after the response returns (non-blocking)

Mount: `/admin` route group, after AdminPermission

### 1.4 Global Middleware Execution Chain

```
所有请求:
  Cors → RateLimit → ApiVersion → {Route 中间件} → Controller

/admin/* 请求:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Logout (JWT Blacklist)

**File**: `app/middleware/AdminAuth.php` (modified)

**Principle**: JWT is stateless by itself; on logout the token is added to a Redis blacklist, and AdminAuth checks the blacklist first during validation.

**AdminAuth changes**:
- Add at the start of `process()`: check from the Redis `jwt_blacklist` set whether the current token is on the blacklist
- Return 401 if blacklisted

**Logout route** (under profile center):

| Method | Route | Description |
|------|------|------|
| `POST` | `/admin/profile/logout` | Add the current Bearer token to the Redis blacklist, TTL=remaining token validity |

**Logout logic**:
```php
// Parse remaining token validity
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// Add to blacklist
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. New Controllers and Existing Modifications

### 2.1 System Config CRUD (`ConfigController`)

Extends `BaseController`.

| Method | Route | Description |
|------|------|------|
| `index()` | GET `/admin/config` | Paginated list, filterable by `group`, paginated with `page`/`limit` |
| `store()` | POST `/admin/config` | Create config item, required: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Update config item value/type/description |
| `destroy()` | DELETE `/admin/config/{id}` | Delete config item, requires `confirmPassword()` |

### 2.2 Operation Log Query (`LogController`)

Extends `BaseController`.

| Method | Route | Description |
|------|------|------|
| `index()` | GET `/admin/log` | Paginated list, supports filters: user_id, action, path, created_at (range) |

No create/update/delete provided; logs are recorded automatically by the middleware.

### 2.3 Profile Center (`ProfileController`)

Extends `BaseController`. Operates on the currently logged-in user (`$request->adminId`).

| Method | Route | Description |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Update real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Change password, requires old_password, new_password, new_password_confirmation |

### 2.4 File Upload (`UploadController`)

Extends `BaseController`.

| Method | Route | Description |
|------|------|------|
| `upload()` | POST `/admin/upload` | Accepts files, supports image/jpeg/png/gif/pdf/xlsx/docx |

- Max 10MB
- Storage path: `public/upload/{date}/{hash}.{ext}`
- Returns: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Dashboard Real Data

**File**: `app/admin/controller/DashboardController.php` (modified)

Replace the current hardcoded fake data with real-time DB statistics:

| Metric | Source | Description |
|------|------|------|
| Total users | `AdminUser::count()` | Excludes soft-deleted |
| New today | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Total roles | `AdminRole::count()` | |
| Total permissions | `AdminPermission::count()` | |
| Trend data | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | New users per day for the last 7 days |
| Distribution data | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Distribution by status |
| Recent operations | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | Latest 10 operation logs |

### 2.6 User Batch Operations

**File**: `app/admin/controller/UserController.php` (modified, new methods)

| Method | Route | Description |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Batch delete, request body `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Batch enable/disable, request body `{ ids: [hashid, ...], status: 1|0 }` |

- Each id is first converted to BIGINT via `decodeId()`
- `batchDestroy()` must pass `confirmPassword()` validation

### 2.7 Data Import

**File**: `app/admin/controller/ImportController.php` (new)

| Method | Route | Description |
|------|------|------|
| `users()` | POST `/admin/import/users` | Upload Excel file, batch create users |

Flow:
1. Accept `.xlsx` file
2. Parse with PhpSpreadsheet; expected columns: `username, password, real_name, phone, email, status`
3. Validate + create row by row (snowflake-generated IDs, bcrypt passwords, phone/email encrypted with encryption)
4. Return result: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Health Check

**File**: `app/admin/controller/HealthController.php` (new)

`GET /health` (no auth required, not counted in operation logs):

Returns the connection status of each component:
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

- When a component check fails, the corresponding field value is the error description string
- The route is not mounted under the `/admin` prefix; registered separately as global

---

## 3. Model Fixes

### 3.1 OperationLog Timestamps

**File**: `app/model/OperationLog.php` (modified)

Table `erik_operation_log` has only a `created_at` column (no `updated_at`). Eloquent's default `save()` tries to write `updated_at`, causing an SQL error.

Fix: `public $timestamps = false;` + manually specify `created_at` on write.

### 3.2 AdminUser Model Changes

- Add `Searchable` trait
- Implement `toSearchableArray()`: returns username, real_name
- `UserController::index()` uses `AdminUser::search($kw)->get()` instead of MySQL LIKE when a keyword is detected

ES needs the index created first, via Scout commands:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Route Changes

New routes in `config/route.php`:

```php
// Added inside the /admin route group:
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

// Health check (global route, not inside the /admin group)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// Middleware:
append app\middleware\OperationLog::class to the /admin group middleware
```

`config/middleware.php` registers global middleware:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Additional Error Codes

| code | Meaning | Trigger Scenario |
|------|------|---------|
| 429 | Too Many Requests | RateLimit triggered |

---

## 6. Out of Scope for This Round

- Notification system (requires message queue + frontend push infrastructure)
- Flutter frontend pages (subproject B)
- HarmonyOS token refresh (subproject C)
