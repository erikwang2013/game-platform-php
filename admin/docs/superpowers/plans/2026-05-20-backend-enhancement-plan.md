# 后端增强 — 实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现 13 项后端增强：3 个新中间件 (CORS, RateLimit, OperationLog)、6 个新控制器、2 个模型修改、路由 + 中间件配置。

**Architecture:** 遵循现有 webman 模式 — 中间件实现 `MiddlewareInterface::process()`，控制器继承 `BaseController`，路由用闭包或 `[class, method]` 定义。全局中间件在 `config/middleware.php` 注册，路由级中间件在 `config/route.php` 路由组上挂载。

**Tech Stack:** PHP 8.3+, webman v2, Redis (扩展), PhpSpreadsheet (导入)

**依赖顺序:** 模型修改 → 中间件 → 控制器 → 路由/配置

---

### Task 1: AdminUser 模型加 SoftDeletes + Searchable trait

**Files:**
- Modify: `app/model/AdminUser.php`

- [ ] **Step 1: 修改 AdminUser 模型**

在 `app/model/AdminUser.php` 第 10 行后插入两个 `use` 导入，并在类体内最前面使用两个 trait：

```php
use Erikwang2013\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use support\Model;

class AdminUser extends Model
{
    use SoftDeletes;
    use Searchable;
```

同时在类的末尾（`roles()` 方法之后，类结束大括号之前）添加 `toSearchableArray()` 方法：

```php
    public function toSearchableArray(): array
    {
        return [
            'username'  => $this->username,
            'real_name' => $this->real_name,
        ];
    }
```

- [ ] **Step 2: 验证语法**

```bash
php -l app/model/AdminUser.php
```
Expected: `No syntax errors detected`

- [ ] **Step 3: 提交**

```bash
git add app/model/AdminUser.php
git commit -m "feat: AdminUser 模型添加 SoftDeletes 和 Searchable trait"
```

---

### Task 2: CORS 中间件

**Files:**
- Create: `app/middleware/Cors.php`

- [ ] **Step 1: 创建 Cors 中间件**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class Cors implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        if ($request->method() === 'OPTIONS') {
            return response('', 204, [
                'Access-Control-Allow-Origin'      => '*',
                'Access-Control-Allow-Methods'     => 'GET,POST,PUT,DELETE,OPTIONS',
                'Access-Control-Allow-Headers'     => 'Authorization,Content-Type,API-Version',
                'Access-Control-Max-Age'           => '86400',
            ]);
        }

        $response = $handler($request);
        $response->withHeaders([
            'Access-Control-Allow-Origin' => '*',
        ]);
        return $response;
    }
}
```

- [ ] **Step 2: 验证语法**

```bash
php -l app/middleware/Cors.php
```

- [ ] **Step 3: 提交**

```bash
git add app/middleware/Cors.php
git commit -m "feat: 添加 CORS 跨域中间件"
```

---

### Task 3: RateLimit 限流中间件

**Files:**
- Create: `app/middleware/RateLimit.php`

- [ ] **Step 1: 创建 RateLimit 中间件**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use support\Redis;

class RateLimit implements MiddlewareInterface
{
    private int $defaultLimit = 60;
    private int $defaultWindow = 60;

    private array $sensitive = [
        '/api/auth/login'    => ['limit' => 10, 'window' => 60],
        '/api/auth/register' => ['limit' => 5,  'window' => 60],
    ];

    public function process(Request $request, callable $handler): Response
    {
        $path = $request->path();
        $ip   = $request->getRealIp();

        $limit  = $this->defaultLimit;
        $window = $this->defaultWindow;

        foreach ($this->sensitive as $pattern => $cfg) {
            if (str_starts_with($path, $pattern)) {
                $limit  = $cfg['limit'];
                $window = $cfg['window'];
                break;
            }
        }

        $key         = "rate_limit:{$ip}:" . md5($path);
        $now         = (int) (microtime(true) * 1000);
        $windowStart = $now - $window * 1000;

        Redis::zremrangebyscore($key, 0, $windowStart);
        $count = Redis::zcard($key);

        if ($count >= $limit) {
            return json([
                'code'    => 429,
                'message' => '请求过于频繁，请稍后再试',
                'data'    => [],
            ])->withStatus(429);
        }

        Redis::zadd($key, $now, $now . '.' . mt_rand());
        Redis::expire($key, $window + 10);

        return $handler($request);
    }
}
```

- [ ] **Step 2: 验证语法**

```bash
php -l app/middleware/RateLimit.php
```

- [ ] **Step 3: 提交**

```bash
git add app/middleware/RateLimit.php
git commit -m "feat: 添加 Redis 限流中间件"
```

---

### Task 4: OperationLog 操作日志中间件

**Files:**
- Create: `app/middleware/OperationLog.php`

- [ ] **Step 1: 创建 OperationLog 中间件**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class OperationLog implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $method = $request->method();

        if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return $handler($request);
        }

        $response = $handler($request);

        $log = new \app\model\OperationLog();
        $log->user_id   = $request->adminId ?? 0;
        $log->action    = $method;
        $log->method    = $method;
        $log->path      = $request->path();
        $log->ip        = $request->getRealIp();
        $log->input     = json_encode($request->all(), JSON_UNESCAPED_UNICODE);
        $log->created_at = date('Y-m-d H:i:s');
        $log->save();

        return $response;
    }
}
```

- [ ] **Step 2: 验证语法**

```bash
php -l app/middleware/OperationLog.php
```

- [ ] **Step 3: 提交**

```bash
git add app/middleware/OperationLog.php
git commit -m "feat: 添加操作日志自动记录中间件"
```

---

### Task 5: 全局中间件配置

**Files:**
- Modify: `config/middleware.php`

- [ ] **Step 1: 注册全局中间件**

将 `config/middleware.php` 内容改为：

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

- [ ] **Step 2: 验证语法**

```bash
php -l config/middleware.php
```

- [ ] **Step 3: 提交**

```bash
git add config/middleware.php
git commit -m "feat: 注册 CORS 和 RateLimit 全局中间件"
```

---

### Task 6: 健康检查控制器

**Files:**
- Create: `app/admin/controller/HealthController.php`

- [ ] **Step 1: 创建 HealthController**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use support\Request;
use support\Response;
use support\Db;
use support\Redis;
use Throwable;

class HealthController
{
    public function index(Request $request): Response
    {
        return json([
            'code' => 0,
            'data' => [
                'app'           => 'open-admin',
                'version'       => '1.0',
                'php'           => PHP_VERSION,
                'database'      => $this->checkDb(),
                'redis'         => $this->checkRedis(),
                'elasticsearch' => $this->checkES(),
                'timestamp'     => time(),
            ],
        ]);
    }

    private function checkDb(): string
    {
        try {
            Db::select('SELECT 1');
            return 'ok';
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    private function checkRedis(): string
    {
        try {
            Redis::ping();
            return 'ok';
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    private function checkES(): string
    {
        try {
            $hosts = config('plugin.erikwang2013.webman-scout.scout.hosts', ['http://localhost:9200']);
            $client = new \GuzzleHttp\Client(['timeout' => 2]);
            $resp = $client->get(rtrim($hosts[0], '/') . '/_cluster/health');
            $body = json_decode((string) $resp->getBody(), true);
            return $body['status'] ?? 'unknown';
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }
}
```

- [ ] **Step 2: 验证语法**

```bash
php -l app/admin/controller/HealthController.php
```

- [ ] **Step 3: 提交**

```bash
git add app/admin/controller/HealthController.php
git commit -m "feat: 添加 /health 健康检查端点"
```

---

### Task 7: 系统配置 CRUD 控制器

**Files:**
- Create: `app/admin/controller/ConfigController.php`

- [ ] **Step 1: 创建 ConfigController**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\SystemConfig;
use support\Request;
use support\Response;

class ConfigController extends BaseController
{
    public function index(Request $request): Response
    {
        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $group = $request->input('group', '');

        $query = SystemConfig::query();
        if ($group !== '') {
            $query->where('group', $group);
        }

        $total = $query->count();
        $list  = $query->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->orderBy('group')
                       ->orderBy('key')
                       ->get()
                       ->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'group' => 'required|string|max:100',
            'key'   => 'required|string|max:100',
            'value' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $exists = SystemConfig::where('group', $request->input('group'))
                              ->where('key', $request->input('key'))
                              ->exists();
        if ($exists) {
            return $this->fail('配置项已存在', 422);
        }

        $config = new SystemConfig();
        $config->id          = $this->generateId();
        $config->group       = $request->input('group');
        $config->key         = $request->input('key');
        $config->value       = $request->input('value');
        $config->type        = $request->input('type', 'string');
        $config->description = $request->input('description', '');
        $config->save();

        return $this->success($this->encodeIds($config->toArray()), '创建成功');
    }

    public function update(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $config = SystemConfig::find($id);
        if (!$config) {
            return $this->fail('配置项不存在', 404);
        }

        if ($request->has('value')) {
            $config->value = $request->input('value');
        }
        if ($request->has('type')) {
            $config->type = $request->input('type');
        }
        if ($request->has('description')) {
            $config->description = $request->input('description');
        }

        $config->save();

        return $this->success($this->encodeIds($config->toArray()), '更新成功');
    }

    public function destroy(Request $request, string $hashid): Response
    {
        $id     = $this->decodeId($hashid);
        $config = SystemConfig::find($id);
        if (!$config) {
            return $this->fail('配置项不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error   = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $config->delete();
        return $this->success([], '删除成功');
    }
}
```

- [ ] **Step 2: 验证语法**

```bash
php -l app/admin/controller/ConfigController.php
```

- [ ] **Step 3: 提交**

```bash
git add app/admin/controller/ConfigController.php
git commit -m "feat: 添加系统配置 CRUD 控制器"
```

---

### Task 8: 操作日志查询控制器

**Files:**
- Create: `app/admin/controller/LogController.php`

- [ ] **Step 1: 创建 LogController**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\OperationLog;
use support\Request;
use support\Response;

class LogController extends BaseController
{
    public function index(Request $request): Response
    {
        $page      = (int) $request->input('page', 1);
        $limit     = (int) $request->input('limit', 15);
        $userId    = $request->input('user_id');
        $action    = $request->input('action');
        $path      = $request->input('path');
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $query = OperationLog::with('user');

        if ($userId) {
            $query->where('user_id', $userId);
        }
        if ($action) {
            $query->where('action', $action);
        }
        if ($path) {
            $query->where('path', 'like', "%{$path}%");
        }
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $total = $query->count();
        $list  = $query->offset(($page - 1) * $limit)
                       ->limit($limit)
                       ->orderBy('id', 'desc')
                       ->get()
                       ->map(function ($log) {
                           $data = $log->toArray();
                           $data['id']      = $this->encodeId($data['id']);
                           $data['user_name'] = $log->user->username ?? '系统';
                           unset($data['user']);
                           return $data;
                       });

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }
}
```

- [ ] **Step 2: 验证语法**

```bash
php -l app/admin/controller/LogController.php
```

- [ ] **Step 3: 提交**

```bash
git add app/admin/controller/LogController.php
git commit -m "feat: 添加操作日志查询控制器"
```

---

### Task 9: 个人中心控制器（含登出）

**Files:**
- Create: `app/admin/controller/ProfileController.php`
- Modify: `app/middleware/AdminAuth.php` (JWT 黑名单校验)

- [ ] **Step 1: 修改 AdminAuth 中间件，添加 JWT 黑名单校验**

在 `AdminAuth::process()` 方法中，token 提取之后、JWT 解码之前插入黑名单检查：

```php
// 在第 30-31 行之间（token 提取后、JWT 解码前）插入:
        // 检查 JWT 黑名单
        $blacklistKey = 'jwt_blacklist:' . md5($token);
        if (Redis::get($blacklistKey)) {
            return json(['code' => 401, 'message' => 'Token已失效，请重新登录', 'data' => []]);
        }
```

需要同时添加 Redis 导入。修改文件头：

```php
use support\Request;
use support\Response;
use support\Redis;
use Erikwang2013\Jwt\JWT;
```

- [ ] **Step 2: 创建 ProfileController**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\common\EncryptionService;
use app\model\AdminUser;
use support\Container;
use support\Request;
use support\Response;
use support\Redis;
use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTFactory;

class ProfileController extends BaseController
{
    private static ?JWT $jwt = null;

    private static function getJWT(): JWT
    {
        if (self::$jwt === null) {
            $config = config('plugin.erikwang2013.jwt.jwt', []);
            self::$jwt = JWTFactory::createFromConfig($config);
        }
        return self::$jwt;
    }

    public function updateProfile(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        $user    = AdminUser::find($adminId);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        if ($request->has('real_name')) {
            $user->real_name = $request->input('real_name');
        }
        if ($request->has('phone')) {
            $user->phone = EncryptionService::encrypt($request->input('phone', ''));
        }
        if ($request->has('email')) {
            $user->email = EncryptionService::encrypt($request->input('email', ''));
        }

        $user->save();

        $data = $user->toArray();
        unset($data['password'], $data['id_card']);
        if (!empty($data['phone'])) {
            $data['phone'] = EncryptionService::decrypt($data['phone']);
        }
        if (!empty($data['email'])) {
            $data['email'] = EncryptionService::decrypt($data['email']);
        }

        return $this->success($this->encodeIds($data), '更新成功');
    }

    public function updatePassword(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        $user    = AdminUser::find($adminId);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        $oldPassword = $request->input('old_password', '');
        $newPassword = $request->input('new_password', '');

        if (empty($oldPassword) || empty($newPassword)) {
            return $this->fail('请填写旧密码和新密码', 422);
        }

        if (!password_verify($oldPassword, $user->password)) {
            return $this->fail('旧密码错误', 422);
        }

        if (strlen($newPassword) < 6 || strlen($newPassword) > 32) {
            return $this->fail('新密码长度 6-32 位', 422);
        }

        $user->password = password_hash($newPassword, PASSWORD_BCRYPT);
        $user->save();

        return $this->success([], '密码修改成功');
    }

    public function logout(Request $request): Response
    {
        $token = $request->header('Authorization', '');
        $token = str_replace('Bearer ', '', $token);

        if (empty($token)) {
            return $this->fail('未登录', 401);
        }

        try {
            $payload = self::getJWT()->decode($token);
            $ttl     = max((int)($payload['exp'] ?? 0) - time(), 0);
            Redis::setex('jwt_blacklist:' . md5($token), $ttl, '1');
        } catch (\Throwable $e) {
            // token 无效也视为登出成功
        }

        return $this->success([], '已登出');
    }
}
```

- [ ] **Step 3: 验证语法**

```bash
php -l app/admin/controller/ProfileController.php && php -l app/middleware/AdminAuth.php
```

- [ ] **Step 4: 提交**

```bash
git add app/admin/controller/ProfileController.php app/middleware/AdminAuth.php
git commit -m "feat: 添加个人中心控制器 + JWT 黑名单登出"
```

---

### Task 10: 文件上传控制器

**Files:**
- Create: `app/admin/controller/UploadController.php`

- [ ] **Step 1: 创建 UploadController**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use support\Request;
use support\Response;

class UploadController extends BaseController
{
    private array $allowExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'xlsx', 'docx'];
    private int $maxSize = 10 * 1024 * 1024; // 10MB

    public function upload(Request $request): Response
    {
        $file = $request->file('file');
        if (!$file) {
            return $this->fail('请选择文件', 422);
        }

        if (!$file->isValid()) {
            return $this->fail('文件上传失败', 500);
        }

        $ext = strtolower($file->getUploadExtension() ?: 'bin');
        if (!in_array($ext, $this->allowExts, true)) {
            return $this->fail('不支持的文件类型: .' . $ext, 422);
        }

        if ($file->getSize() > $this->maxSize) {
            return $this->fail('文件大小不能超过 10MB', 422);
        }

        $dateDir  = date('Y-m-d');
        $filename = md5(uniqid((string) mt_rand(), true)) . '.' . $ext;
        $relativePath = "/upload/{$dateDir}/{$filename}";
        $absoluteDir  = public_path() . "/upload/{$dateDir}";

        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        $file->move($absoluteDir . '/' . $filename);

        return $this->success(['url' => $relativePath], '上传成功');
    }
}
```

- [ ] **Step 2: 验证语法**

```bash
php -l app/admin/controller/UploadController.php
```

- [ ] **Step 3: 提交**

```bash
git add app/admin/controller/UploadController.php
git commit -m "feat: 添加文件上传控制器"
```

---

### Task 11: 用户批量操作

**Files:**
- Modify: `app/admin/controller/UserController.php`

- [ ] **Step 1: 在 UserController 类末尾添加两个方法（`destroy` 方法之后）**

```php
    /**
     * 批量删除
     * POST /admin/user/batch/destroy
     */
    public function batchDestroy(Request $request): Response
    {
        $ids      = $request->input('ids', []);
        $password = $request->input('password', '');

        if (empty($ids) || !is_array($ids)) {
            return $this->fail('请选择要删除的用户', 422);
        }

        $adminId = $request->adminId ?? 0;
        $error   = $this->confirmPassword($adminId, $password, $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $decodedIds = array_map(fn($hashid) => $this->decodeId($hashid), $ids);
        AdminUser::whereIn('id', $decodedIds)->delete();

        return $this->success(['count' => count($decodedIds)], '删除成功');
    }

    /**
     * 批量启用/禁用
     * POST /admin/user/batch/status
     */
    public function batchStatus(Request $request): Response
    {
        $ids    = $request->input('ids', []);
        $status = (int) $request->input('status', 0);

        if (empty($ids) || !is_array($ids)) {
            return $this->fail('请选择用户', 422);
        }

        if (!in_array($status, [0, 1], true)) {
            return $this->fail('状态值无效', 422);
        }

        $decodedIds = array_map(fn($hashid) => $this->decodeId($hashid), $ids);
        AdminUser::whereIn('id', $decodedIds)->update(['status' => $status]);

        $label = $status === 1 ? '启用' : '禁用';
        return $this->success(['count' => count($decodedIds)], "批量{$label}成功");
    }
```

- [ ] **Step 2: 验证语法**

```bash
php -l app/admin/controller/UserController.php
```

- [ ] **Step 3: 提交**

```bash
git add app/admin/controller/UserController.php
git commit -m "feat: 添加用户批量删除和批量启用/禁用"
```

---

### Task 12: Excel 导入控制器

**Files:**
- Create: `app/admin/controller/ImportController.php`

- [ ] **Step 1: 创建 ImportController**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\common\EncryptionService;
use app\model\AdminUser;
use PhpOffice\PhpSpreadsheet\IOFactory;
use support\Request;
use support\Response;

class ImportController extends BaseController
{
    public function users(Request $request): Response
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->fail('请上传 Excel 文件', 422);
        }

        $ext = strtolower($file->getUploadExtension() ?: '');
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            return $this->fail('仅支持 .xlsx 或 .xls 文件', 422);
        }

        $tmpPath = $file->getRealPath();
        $spreadsheet = IOFactory::load($tmpPath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray();

        if (count($rows) < 2) {
            return $this->fail('Excel 文件无数据', 422);
        }

        // 第一行为表头: username, password, real_name, phone, email, status
        $headers = array_map('strtolower', array_map('trim', $rows[0]));
        $colMap  = array_flip($headers);

        $required   = ['username', 'password', 'real_name'];
        foreach ($required as $col) {
            if (!isset($colMap[$col])) {
                return $this->fail("缺少必填列: {$col}", 422);
            }
        }

        $total  = 0;
        $success = 0;
        $failed  = 0;
        $errors  = [];

        foreach ($rows as $idx => $row) {
            if ($idx === 0) continue; // skip header
            $total++;

            $username = trim((string) ($row[$colMap['username']] ?? ''));
            $password = trim((string) ($row[$colMap['password']] ?? ''));
            $realName = trim((string) ($row[$colMap['real_name']] ?? ''));
            $phone    = trim((string) ($row[$colMap['phone']] ?? ''));
            $email    = trim((string) ($row[$colMap['email']] ?? ''));
            $status   = isset($colMap['status']) ? (int) ($row[$colMap['status']] ?? 1) : 1;

            if (empty($username)) {
                $failed++;
                $errors[] = ['row' => $idx + 1, 'reason' => '用户名为空'];
                continue;
            }

            if (AdminUser::where('username', $username)->exists()) {
                $failed++;
                $errors[] = ['row' => $idx + 1, 'reason' => "用户名 {$username} 已存在"];
                continue;
            }

            try {
                $user = new AdminUser();
                $user->id        = $this->generateId();
                $user->username  = $username;
                $user->password  = password_hash($password, PASSWORD_BCRYPT);
                $user->real_name = $realName;
                $user->status    = in_array($status, [0, 1], true) ? $status : 1;
                $user->phone     = EncryptionService::encrypt($phone);
                $user->email     = EncryptionService::encrypt($email);
                $user->save();
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $idx + 1, 'reason' => $e->getMessage()];
            }
        }

        return $this->success([
            'total'   => $total,
            'success' => $success,
            'failed'  => $failed,
            'errors'  => $errors,
        ], '导入完成');
    }
}
```

- [ ] **Step 2: 验证语法**

```bash
php -l app/admin/controller/ImportController.php
```

- [ ] **Step 3: 提交**

```bash
git add app/admin/controller/ImportController.php
git commit -m "feat: 添加 Excel 导入用户控制器"
```

---

### Task 13: 路由更新 — 所有新路由 + 中间件绑定

**Files:**
- Modify: `config/route.php`

- [ ] **Step 1: 更新路由配置**

在 `/admin` 路由组内新增路由，同时追加 OperationLog 中间件。完整路由文件：

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Webman\Route;
use support\Request;

/**
 * API 路由配置
 *
 * 路由分组说明:
 * - /admin/*  管理端接口，需要 JWT 认证 + 权限校验
 * - /api/*    客户端接口（部分白名单，部分需认证）
 * - /health   健康检查（无需认证）
 *
 * API 版本策略:
 * - 版本号通过请求头 API-Version 携带（如 "v1"、"v2"），不在 URL 中体现
 * - 缺失时默认使用 v1
 * - 由 ApiVersion 中间件校验，路由闭包按版本解析对应控制器
 */

/**
 * 创建版本化 API 路由闭包
 *
 * 根据请求头 API-Version 动态解析控制器类。
 * 控制器目录结构: app/api/{version}/controller/{Controller}.php
 */
function v(string $controller, string $action): \Closure
{
    return function (Request $request) use ($controller, $action) {
        $version = $request->apiVersion ?? 'v1';
        $class = "\\app\\api\\{$version}\\controller\\{$controller}";
        return (new $class)->{$action}($request);
    };
}

// ============================================================
// 健康检查（全局，无需认证）
// ============================================================
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// ============================================================
// 管理端路由
// ============================================================
Route::group('/admin', function () {
    // 仪表盘
    Route::get('/dashboard', [app\admin\controller\DashboardController::class, 'index']);

    // 用户管理
    Route::resource('/user', app\admin\controller\UserController::class);
    Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
    Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

    // 角色管理
    Route::resource('/role', app\admin\controller\RoleController::class);

    // 权限管理
    Route::resource('/permission', app\admin\controller\PermissionController::class);

    // 系统配置
    Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
    Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
    Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
    Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

    // 操作日志
    Route::get('/log', [app\admin\controller\LogController::class, 'index']);

    // 个人中心
    Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
    Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
    Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

    // 导出
    Route::post('/export/excel', [app\admin\controller\ExportController::class, 'excel']);
    Route::post('/export/pdf', [app\admin\controller\ExportController::class, 'pdf']);

    // 导入
    Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

    // 文件上传
    Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);
})->middleware([
    app\middleware\AdminAuth::class,
    app\middleware\AdminPermission::class,
    app\middleware\OperationLog::class,
]);

// ============================================================
// 公开接口（通过 API-Version 头路由到版本化控制器）
// ============================================================
Route::group('/api', function () {
    // 点击验证码
    Route::post('/captcha/generate', v('CaptchaController', 'generate'));
    Route::post('/captcha/verify', v('CaptchaController', 'verify'));

    // 认证
    Route::post('/auth/login', v('AuthController', 'login'));
    Route::post('/auth/register', v('AuthController', 'register'));
    Route::post('/auth/refresh', v('AuthController', 'refresh'));
})->middleware([
    app\middleware\ApiVersion::class,
]);

// 关闭默认路由
Route::disableDefaultRoute();
```

- [ ] **Step 2: 验证语法**

```bash
php -l config/route.php
```

- [ ] **Step 3: 提交**

```bash
git add config/route.php
git commit -m "feat: 更新路由配置，添加所有新端点"
```

---

### 验证清单

实现完成后，逐项验证：

```bash
# 1. 所有文件语法检查
find app -name "*.php" -newer app/model/AdminUser.php -exec php -l {} \;
php -l config/route.php
php -l config/middleware.php

# 2. 启动服务验证
php start.php start -d
sleep 2
php start.php status

# 3. 健康检查
curl http://localhost:8787/health

# 4. 验证码 + 登录
curl -X POST http://localhost:8787/api/captcha/generate -H "API-Version: v1"

# 5. CORS 预检
curl -X OPTIONS http://localhost:8787/api/auth/login -H "Origin: http://example.com" -I

# 6. 停止服务
php start.php stop
```
