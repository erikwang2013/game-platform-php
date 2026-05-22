# erikwang2013/jwt-webman

一款兼容 webman、Laravel、ThinkPHP、Hyperf 的 JWT 认证插件。适用于分布式部署，安装简单快捷。

作者：[艾瑞可erik](https://erik.xyz)

## 项目说明

`erikwang2013/jwt-webman` 是一个 PHP 多框架 JWT 认证插件，核心基于 `firebase/php-jwt` 封装。

### 定位

传统的 JWT 插件通常只绑定单一框架，在微服务或多项目架构中，不同框架之间需要各自对接不同的 JWT 实现，造成维护成本和认证逻辑不一致的风险。

本插件将核心逻辑与框架完全解耦，通过「一套核心 + 框架适配层」的架构，在 webman、Laravel、ThinkPHP、Hyperf 中提供一致的 API 体验。无论后端服务使用哪个框架，JWT 的编码、解码、刷新、黑名单逻辑完全一致，只需按照各框架的习惯方式进行配置和注入即可。

### 架构

```
src/erik-jwt/
├── JWT.php                   ← 核心：令牌编解码、刷新、黑名单
├── JWTFactory.php            ← 核心：工厂类，根据配置创建实例
├── Config.php                ← 核心：框架无关的配置容器
├── TokenStorageInterface.php ← 核心：存储抽象接口
├── RedisTokenStorage.php     ← 黑名单存储：Redis（注入 callable 连接）
├── DatabaseTokenStorage.php  ← 黑名单存储：数据库（注入 PDO 连接）
├── FileTokenStorage.php      ← 黑名单存储：文件系统
├── MemcachedTokenStorage.php ← 黑名单存储：Memcached
├── RetryTokenStorage.php     ← 装饰器：操作失败自动重试
├── JWTException.php          ← 异常层级：过期/无效/黑名单/存储/配置/网络
│
├── Webman/    → Middleware
├── Laravel/   → ServiceProvider / Facade / Middleware / InstallCommand
├── ThinkPHP/  → Service / Facade / Middleware / InstallCommand
└── Hyperf/    → ConfigProvider / Middleware / AOP Aspect / #[JWT] Attribute
```

核心层不依赖任何框架 helper，所有外部依赖（配置、日志、数据库连接、Redis 连接）通过构造函数或工厂方法注入。每个框架的适配层负责从框架容器中获取这些依赖，组装后传入核心工厂。

### 设计理念

- **框架无关核心**：核心代码零框架依赖，可在任何 PHP 7.4+ 项目中使用
- **原生深度集成**：每个框架适配层遵循各自的插件规范和惯用写法，而非生硬地统一封装
- **统一配置格式**：四个框架共用一套配置结构，仅环境变量读取方式略有不同
- **存储驱动可插拔**：黑名单支持 file / redis / database / memcached 四种后端，通过配置切换
- **渐进式接入**：可从最简单的 file 存储起步，业务增长后无缝切换到 redis 或 database

## 功能特性

- JWT 令牌生成（支持 HS256 / HS384 / HS512 / RS256 算法）
- 令牌验证（支持时间容差 leeway）
- 刷新令牌（Refresh Token）
- 令牌黑名单（支持 redis、database、memcached、file 四种存储驱动）
- 存储操作失败自动重试
- 多存储后端优雅降级
- 四框架深度集成：中间件、门面模式、安装命令

## 安装

```sh
composer require erikwang2013/jwt-webman
```

## 各框架使用说明

### Webman

`composer require` 后通过 webman 插件系统自动注册，无需手动配置。

**配置文件：** `config/plugin/erikwang2013/jwt/jwt.php`

**基本用法：**

```php
use Erikwang2013\Jwt\JWTFactory;

$jwt = JWTFactory::createFromConfig(
    config('plugin.erikwang2013.jwt.jwt'),
    null,
    [
        'redis' => fn() => \support\Redis::connection(),
        'pdo'   => \support\Db::connection()->getPdo(),
    ]
);

// 生成令牌
$token = $jwt->encode(['user_id' => 1]);

// 验证令牌
$payload = $jwt->decode($token);

// 拉黑令牌
$jwt->blacklist($token);
```

**中间件：** 在 `config/middleware.php` 中注册：

```php
return [
    '' => [
        \Erikwang2013\Jwt\Webman\Middleware::class,
    ],
];
```

在控制器中获取解析后的 payload：

```php
$payload = $request->jwt_payload;
$userId  = $payload['user_id'];
```

---

### Laravel

`composer require` 后通过 `extra.laravel` 自动发现 ServiceProvider。如果关闭了自动发现，手动在 `config/app.php` 中注册：

```php
'providers' => [
    Erikwang2013\Jwt\Laravel\JWTServiceProvider::class,
],
```

**安装命令：**

```sh
php artisan jwt:install
```

执行后会自动发布配置文件并生成 `JWT_SECRET_KEY` 写入 `.env`。

**配置文件：** `config/jwt.php`

**门面方式：**

```php
use Erikwang2013\Jwt\Laravel\Facade as JWT;

$token   = JWT::encode(['user_id' => 1]);
$payload = JWT::decode($token);
JWT::blacklist($token);
```

**辅助函数：**

```php
$token = jwt()->encode(['user_id' => 1]);
```

**依赖注入：**

```php
use Erikwang2013\Jwt\JWT;

public function __construct(JWT $jwt) {
    $this->jwt = $jwt;
}
```

**中间件：**

```php
// 路由中使用
Route::middleware('jwt')->group(function () {
    Route::get('/api/user', [UserController::class, 'index']);
});

// 控制器中获取 payload
public function index(Request $request) {
    $payload = $request->attributes->get('jwt_payload');
    $userId  = $payload['user_id'];
}
```

**手动发布配置：**

```sh
php artisan vendor:publish --tag=jwt-config
```

---

### ThinkPHP

`composer require` 后在 `app/service.php` 中注册服务：

```php
return [
    \Erikwang2013\Jwt\ThinkPHP\JWTService::class,
];
```

**安装命令：**

```sh
php think jwt:install
```

**配置文件：** `config/jwt.php`

**门面方式：**

```php
use Erikwang2013\Jwt\ThinkPHP\JWT;

$token   = JWT::encode(['user_id' => 1]);
$payload = JWT::decode($token);
```

**辅助函数：**

```php
$token = jwt()->encode(['user_id' => 1]);
```

**中间件：**

```php
// 路由中使用
Route::group(function () {
    Route::get('/api/user', 'UserController@index');
})->middleware('jwt');

// 控制器中获取 payload
public function index(Request $request) {
    $payload = $request->jwt_payload;
    $userId  = $payload['user_id'];
}
```

---

### Hyperf

`composer require` 后在 `config/autoload/dependencies.php` 中注册 ConfigProvider：

```php
return [
    \Erikwang2013\Jwt\Hyperf\ConfigProvider::class,
];
```

**安装命令：**

```sh
php bin/hyperf.php jwt:install
```

**配置文件：** `config/autoload/jwt.php`

**依赖注入：**

```php
use Erikwang2013\Jwt\JWT;
use Hyperf\Di\Annotation\Inject;

class UserController {
    #[Inject]
    protected JWT $jwt;

    public function index() {
        $token   = $this->jwt->encode(['user_id' => 1]);
        $payload = $this->jwt->decode($token);
    }
}
```

**中间件：** ConfigProvider 已自动注册，在 `config/autoload/middlewares.php` 中配置即可。

**AOP 注解方式（可选）：**

```php
use Erikwang2013\Jwt\Hyperf\JWT as JWTAuth;

class UserController {
    #[JWTAuth]
    public function index() {
        // 方法执行前自动校验 JWT
    }
}
```

---

## 配置文件参考

```php
return [
    // 签名密钥，至少16字符
    'secret_key'     => env('JWT_SECRET_KEY', ''),
    // 签名算法：HS256 / HS384 / HS512 / RS256
    'algorithm'      => env('JWT_ALGORITHM', 'HS256'),
    // 签发者标识
    'issuer'         => env('JWT_ISSUER', ''),
    // 受众标识
    'audience'       => env('JWT_AUDIENCE', ''),
    // 时间容差（秒），用于处理服务器时钟偏差
    'leeway'         => (int) env('JWT_LEEWAY', 0),
    // 默认令牌过期时间（秒）
    'default_expire' => (int) env('JWT_DEFAULT_EXPIRE', 3600),
    // 刷新令牌过期时间（秒）
    'refresh_expire' => (int) env('JWT_REFRESH_EXPIRE', 7200),
    // 黑名单存储配置
    'storage' => [
        // 存储类型：file / redis / database / memcached
        'type'     => env('JWT_STORAGE_TYPE', 'file'),
        // 缓存键前缀
        'prefix'   => env('JWT_STORAGE_PREFIX', 'jwt_blacklist:'),
        // Redis 数据库编号
        'database' => (int) env('JWT_STORAGE_DATABASE', 0),
    ],
    // 高级配置
    'advanced' => [
        // 操作失败重试次数
        'retry_attempts'   => (int) env('JWT_ADVANCED_RETRY_ATTEMPTS', 3),
        // 重试延迟（毫秒）
        'retry_delay'      => (int) env('JWT_ADVANCED_RETRY_DELAY', 100),
        // 是否自动清理过期条目
        'auto_cleanup'      => filter_var(env('JWT_AUTO_CLEANUP', false), FILTER_VALIDATE_BOOLEAN),
        // 自动清理间隔（秒）
        'cleanup_interval'  => (int) env('JWT_CLEANUP_INTERVAL', 3600),
    ],
    // 中间件配置
    'middleware' => [
        // 排除的路由路径（正则），这些路径不校验 JWT
        'except' => [],
    ],
];
```

## 存储驱动对比

| 驱动 | 适用场景 |
|------|----------|
| `file` | 单机部署、低并发 |
| `redis` | 分布式部署、高性能 |
| `database` | 需要持久化、跨数据中心 |
| `memcached` | 高吞吐量、自动过期 |

## 开源协议

MIT
