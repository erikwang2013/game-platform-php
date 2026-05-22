# 子项目 A：后端增强 — 设计规范

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 范围

本次为后端增强，共 15 个功能点，涉及 9 个新文件 + 4 个修改文件。

---

## 新增/修改文件清单

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

## 1. 中间件

### 1.1 CORS 中间件

**文件**: `app/middleware/Cors.php`

- OPTIONS 预检请求直接返回 204
- 非预检请求在响应头追加 `Access-Control-Allow-Origin: *`
- 允许头: `Authorization, Content-Type, API-Version`
- 最大缓存: 86400 秒

挂载：全局中间件（`config/middleware.php`）

### 1.2 限流中间件

**文件**: `app/middleware/RateLimit.php`

- 存储：Redis Sorted Set 滑动窗口
- 默认：60 次/分钟/IP/路由
- 敏感接口：
  - `/api/auth/login`: 10 次/分钟
  - `/api/auth/register`: 5 次/分钟
- 超限返回 `429 Too Many Requests`

挂载：全局中间件（`config/middleware.php`），在 Cors 之后、ApiVersion 之前

### 1.3 操作日志中间件

**文件**: `app/middleware/OperationLog.php`

- 仅记录 POST/PUT/DELETE
- 记录字段：user_id, action, method, path, ip, input(JSON)
- 在响应返回后异步写入（不阻塞）

挂载：`/admin` 路由组，在 AdminPermission 之后

### 1.4 全局中间件执行链

```
所有请求:
  Cors → RateLimit → ApiVersion → {Route 中间件} → Controller

/admin/* 请求:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 登出（JWT 黑名单）

**文件**: `app/middleware/AdminAuth.php`（修改）

**原理**：JWT 本身无状态，登出时将 token 加入 Redis 黑名单，AdminAuth 校验时先查黑名单。

**AdminAuth 改造**：
- `process()` 开头新增：从 Redis `jwt_blacklist` 集合中检查当前 token 是否在黑名单
- 命中黑名单返回 401

**登出路由**（个人中心下）：

| 方法 | 路由 | 说明 |
|------|------|------|
| `POST` | `/admin/profile/logout` | 将当前 Bearer token 加入 Redis 黑名单，TTL=token剩余有效期 |

**Logout 逻辑**：
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. 新控制器及现有改造

### 2.1 系统配置 CRUD (`ConfigController`)

继承 `BaseController`。

| 方法 | 路由 | 说明 |
|------|------|------|
| `index()` | GET `/admin/config` | 分页列表，可按 `group` 筛选，`page`/`limit` 分页 |
| `store()` | POST `/admin/config` | 创建配置项，必填: group, key, value |
| `update()` | PUT `/admin/config/{id}` | 更新配置项 value/type/description |
| `destroy()` | DELETE `/admin/config/{id}` | 删除配置项，需 `confirmPassword()` |

### 2.2 操作日志查询 (`LogController`)

继承 `BaseController`。

| 方法 | 路由 | 说明 |
|------|------|------|
| `index()` | GET `/admin/log` | 分页列表，支持筛选: user_id, action, path, created_at(范围) |

不提供增删改，日志由中间件自动记录。

### 2.3 个人中心 (`ProfileController`)

继承 `BaseController`。操作当前登录用户（`$request->adminId`）。

| 方法 | 路由 | 说明 |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | 更新 real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | 修改密码，需 old_password, new_password, new_password_confirmation |

### 2.4 文件上传 (`UploadController`)

继承 `BaseController`。

| 方法 | 路由 | 说明 |
|------|------|------|
| `upload()` | POST `/admin/upload` | 接收文件，支持 image/jpeg/png/gif/pdf/xlsx/docx |

- 最大 10MB
- 存储路径: `public/upload/{date}/{hash}.{ext}`
- 返回: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 仪表盘真实数据

**文件**: `app/admin/controller/DashboardController.php`（修改）

将当前硬编码假数据改为数据库实时统计：

| 指标 | 来源 | 说明 |
|------|------|------|
| 用户总数 | `AdminUser::count()` | 不含软删除 |
| 今日新增 | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| 角色总数 | `AdminRole::count()` | |
| 权限总数 | `AdminPermission::count()` | |
| 趋势数据 | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | 按日统计近7天新增 |
| 分布数据 | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | 按状态分布 |
| 最近操作 | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | 最近10条操作日志 |

### 2.6 用户批量操作

**文件**: `app/admin/controller/UserController.php`（修改，新增方法）

| 方法 | 路由 | 说明 |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | 批量删除，请求体 `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | 批量启用/禁用，请求体 `{ ids: [hashid, ...], status: 1|0 }` |

- 每个 id 先 `decodeId()` 转为 BIGINT
- `batchDestroy()` 须通过 `confirmPassword()` 校验

### 2.7 数据导入

**文件**: `app/admin/controller/ImportController.php`（新增）

| 方法 | 路由 | 说明 |
|------|------|------|
| `users()` | POST `/admin/import/users` | 上传 Excel 文件，批量创建用户 |

流程：
1. 接收 `.xlsx` 文件
2. PhpSpreadsheet 解析，预期列：`username, password, real_name, phone, email, status`
3. 逐行校验 + 创建（snowflake 生成 ID，bcrypt 密码，encryption 加密 phone/email）
4. 返回结果：`{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 健康检查

**文件**: `app/admin/controller/HealthController.php`（新增）

`GET /health`（无需认证，不计入操作日志）：

返回各组件连接状态：
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

- 组件检测失败时对应字段值为错误描述字符串
- 路由不挂 `/admin` 前缀，单独注册在全局

---

## 3. 模型修正

### 3.1 OperationLog 时间戳

**文件**: `app/model/OperationLog.php`（修改）

表 `erik_operation_log` 仅有 `created_at` 列（无 `updated_at`）。Eloquent 默认 `save()` 会尝试写入 `updated_at`，导致 SQL 错误。

修复：`public $timestamps = false;` + 写入时手动指定 `created_at`。

### 3.2 AdminUser 模型改造

- 加 `Searchable` trait
- 实现 `toSearchableArray()`: 返回 username, real_name
- `UserController::index()` 检测到关键词时使用 `AdminUser::search($kw)->get()` 而非 MySQL LIKE

ES 需先创建索引，可通过 Scout 命令:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. 路由变更

`config/route.php` 新增路由：

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

`config/middleware.php` 注册全局中间件：

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. 错误码补充

| code | 含义 | 触发场景 |
|------|------|---------|
| 429 | 请求过于频繁 | RateLimit 触发 |

---

## 6. 不包含在本次范围内

- 通知系统（需要消息队列 + 前端推送基础设施）
- Flutter 前端页面（子项目 B）
- HarmonyOS Token 刷新（子项目 C）
