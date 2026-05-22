# Multi-Framework Adaptation Design

Date: 2026-05-14

## Goal

Adapt `erikwang2013/jwt-webman` JWT plugin to support four PHP frameworks natively: webman, Laravel, ThinkPHP, Hyperf. Each framework gets deep integration following its own plugin conventions, including: ServiceProvider/ConfigProvider, Facade, Middleware, install command, config publishing.

## Architecture Overview

```
src/erik-jwt/
├── JWT.php                          # Core: config array + PSR-3 LoggerInterface
├── JWTFactory.php                   # Core: config array + connection resolvers
├── Config.php                       # Core: framework-agnostic config container（keep）
├── JWTException.php                 # Core: exception hierarchy（keep）
├── TokenStorageInterface.php        # Core（keep）
├── RedisTokenStorage.php            # Core: accepts callable $redisResolver
├── DatabaseTokenStorage.php         # Core: accepts PDO $pdo
├── FileTokenStorage.php             # Core（keep）
├── MemcachedTokenStorage.php        # Core（keep）
├── RetryTokenStorage.php            # Core（keep）
│
├── Webman/                          # Webman adapter
│   ├── Install.php
│   ├── Middleware.php
│   └── config/plugin/erikwang2013/jwt/
│       ├── app.php
│       └── jwt.php
│
├── Laravel/                         # Laravel adapter
│   ├── JWTServiceProvider.php
│   ├── Facade.php
│   ├── Middleware.php
│   ├── InstallCommand.php
│   ├── helpers.php
│   └── config/jwt.php
│
├── ThinkPHP/                        # ThinkPHP adapter
│   ├── JWTService.php
│   ├── Facade.php
│   ├── Middleware.php
│   ├── InstallCommand.php
│   ├── helpers.php
│   └── config/jwt.php
│
└── Hyperf/                          # Hyperf adapter
    ├── ConfigProvider.php
    ├── Middleware.php
    ├── JWTAspect.php
    ├── JWT.php                      # #[JWT] attribute for AOP
    ├── InstallCommand.php
    └── config/jwt.php
```

## Core Layer Changes

### Decoupling webman dependencies

Each framework adapter provides three things the core needs:
1. **Config** — an array, formatted the same regardless of framework
2. **Logger** — PSR-3 LoggerInterface
3. **Connections** — Redis resolver (callable returning object with ping/setex/exists), PDO instance, or Memcached instance

### JWT.php

- Constructor: `__construct(array $config, ?LoggerInterface $logger = null)`
- All `Log::error()` → `$this->logger->error()` with NullLogger fallback
- Remove `config()` call, use passed-in `$config` array only
- Remove `support\Log` import

### JWTFactory.php

- `createFromConfig(array $config, ?LoggerInterface $logger, array $connections = [])`
- `$connections` shape: `['redisResolver' => callable, 'pdo' => \PDO, 'memcached' => Memcached]`
- Each adapter provides its own resolvers
- Remove `support\Redis`, `support\Db`, `support\Log`, `config()` helpers

### RedisTokenStorage.php

- Constructor: `__construct(callable $redisResolver, string $prefix = 'jwt_blacklist:')`
- Internal: call `$redisResolver()` to get the Redis connection on each operation
- Remove `support\Redis` import

### DatabaseTokenStorage.php

- Constructor: `__construct(\PDO $pdo, string $tableName = 'jwt_blacklist')`
- Remove `support\Db` import, use `$this->pdo->prepare()`, `$this->pdo->exec()`

### FileTokenStorage.php, MemcachedTokenStorage.php, RetryTokenStorage.php, TokenStorageInterface.php, JWTException.php, Config.php

No changes needed — already framework-agnostic.

## Framework Adapters

### Webman

Files:
- `Webman/Install.php` — `WEBMAN_PLUGIN = true`, copies config to `config/plugin/erikwang2013/jwt/`
- `Webman/Middleware.php` — webman middleware, uses `support\Log`, `support\Redis` natively
- `Webman/config/...` — existing config files updated

JWTFactory call inside each adapter:
```php
// In Webman bootstrap
$config = config('plugin.erikwang2013.jwt.jwt');
$jwt = JWTFactory::createFromConfig(
    $config,
    new \Monolog\Logger('jwt'), // or support\Log adapter
    [
        'redisResolver' => fn() => \support\Redis::class,
        'pdo' => \support\Db::connection()->getPdo(),
    ]
);
```

### Laravel

Files:
- `Laravel/JWTServiceProvider.php`
  - `register()`: merge config from `config/jwt.php`, singleton bind `erik.jwt` to container, register facade alias, register `jwt` middleware alias to router
  - `boot()`: publish config via `vendor:publish`
- `Laravel/Facade.php` — extends `Illuminate\Support\Facades\Facade`, getFacadeAccessor returns `erik.jwt`
- `Laravel/Middleware.php` — `handle($request, Closure $next)`, extracts Bearer token from `Authorization` header, decodes via JWT, attaches `jwt_payload` to request
- `Laravel/InstallCommand.php` — `php artisan jwt:install`, publishes config and generates JWT_SECRET_KEY in .env
- `Laravel/helpers.php` — global `jwt()` function
- `Laravel/config/jwt.php` — returns `env('JWT_*')` defaults

Connection injection:
```php
$config = config('jwt');
$jwt = JWTFactory::createFromConfig($config, \Log::channel(), [
    'redisResolver' => fn() => \Illuminate\Support\Facades\Redis::connection()->client(),
    'pdo' => \Illuminate\Support\Facades\DB::connection()->getPdo(),
]);
```

### ThinkPHP

Files:
- `ThinkPHP/JWTService.php` — extends `think\Service`, register/boot hooks
- `ThinkPHP/Facade.php` — extends `think\Facade`, getFacadeClass returns the bound class
- `ThinkPHP/Middleware.php` — `handle($request, Closure $next)`, Bearer extraction + JWT decode
- `ThinkPHP/InstallCommand.php` — `php think jwt:install`
- `ThinkPHP/helpers.php` — global `jwt()` function
- `ThinkPHP/config/jwt.php` — TP config format

Connection injection:
```php
$jwt = JWTFactory::createFromConfig(\think\facade\Config::get('jwt'), null, [
    'redisResolver' => fn() => \think\facade\Cache::store('redis')->handler(),
    'pdo' => \think\facade\Db::connect()->getPdo(),
]);
```

### Hyperf

Files:
- `Hyperf/ConfigProvider.php` — defines DI bindings:
  ```php
  \Erikwang2013\Jwt\JWT::class => \Erikwang2013\Jwt\Hyperf\JWTFactory::class, // factory method
  ```
  Registers middleware, registers `InstallCommand`
- `Hyperf/Middleware.php` — implements `Hyperf\Contract\MiddlewareInterface`, `process()` method
- `Hyperf/JWTAspect.php` — AOP aspect around `#[JWT]` attribute, auto-decodes before controller method
- `Hyperf/JWT.php` — `#[Attribute]` class for marking controller methods
- `Hyperf/InstallCommand.php` — `php bin/hyperf.php jwt:install`
- `Hyperf/config/jwt.php` — Hyperf config format

Connection injection:
```php
$jwt = JWTFactory::createFromConfig(
    $container->get(\Hyperf\Contract\ConfigInterface::class)->get('jwt'),
    $container->get(\Psr\Log\LoggerInterface::class),
    [
        'redisResolver' => fn() => $container->get(\Hyperf\Redis\Redis::class),
        'pdo' => $container->get(\Hyperf\DbConnection\Db::class)->connection()->getPdo(),
    ]
);
```

## Middleware Behavior (all frameworks)

All four middleware classes share the same logic:
1. Read `Authorization` header
2. Strip `Bearer ` prefix if present
3. Call `$jwt->decode($token)`
4. On success: attach payload array to request (framework-specific way)
5. On failure: return 401 JSON `{"code": 401, "msg": "Unauthorized", "data": null}`
6. Optional config key `except` — array of route path regex patterns to skip (e.g. `['/api/public/.*', '/health']`, matched via `preg_match` against request path)

## Config File Format (unified)

All framework config files share the same structure, only framework-specific config access differs:

```php
return [
    'secret_key'     => env('JWT_SECRET_KEY', ''),
    'algorithm'      => env('JWT_ALGORITHM', 'HS256'),
    'issuer'         => env('JWT_ISSUER', ''),
    'audience'       => env('JWT_AUDIENCE', ''),
    'leeway'         => (int) env('JWT_LEEWAY', 0),
    'default_expire' => (int) env('JWT_DEFAULT_EXPIRE', 3600),
    'refresh_expire' => (int) env('JWT_REFRESH_EXPIRE', 7200),
    'storage' => [
        'type'     => env('JWT_STORAGE_TYPE', 'file'),
        'prefix'   => env('JWT_STORAGE_PREFIX', 'jwt_blacklist:'),
        'database' => env('JWT_STORAGE_DATABASE', 0),
    ],
    'advanced' => [
        'retry_attempts'   => (int) env('JWT_ADVANCED_RETRY_ATTEMPTS', 3),
        'retry_delay'      => (int) env('JWT_ADVANCED_RETRY_DELAY', 100),
        'auto_cleanup'      => filter_var(env('JWT_AUTO_CLEANUP', false), FILTER_VALIDATE_BOOLEAN),
        'cleanup_interval'  => (int) env('JWT_CLEANUP_INTERVAL', 3600),
    ],
    'middleware' => [
        'except' => env('JWT_MIDDLEWARE_EXCEPT', []), // route patterns to skip
    ],
];
```

## composer.json Changes

Relax framework dependencies — `workerman/webman`, `webman/redis`, `monolog/monolog` move from `require` to `suggest`:

```json
{
    "require": {
        "php": ">=7.4",
        "firebase/php-jwt": "^6.0",
        "ext-json": "*",
        "psr/log": "^1.0 | ^2.0 | ^3.0"
    },
    "suggest": {
        "workerman/webman": "For webman framework integration",
        "laravel/framework": "For Laravel framework integration",
        "topthink/framework": "For ThinkPHP framework integration",
        "hyperf/framework": "For Hyperf framework integration"
    },
    "autoload": {
        "psr-4": {
            "Erikwang2013\Jwt\\": "src/erik-jwt"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Erikwang2013\Jwt\\Laravel\\JWTServiceProvider"
            ],
            "aliases": {
                "JWT": "Erikwang2013\Jwt\\Laravel\\Facade"
            }
        }
    }
}
```

## Test Plan

- Each framework adapter gets a smoke test that verifies: encode → decode → blacklist → blocked
- Core JWT class tests with NullLogger
- Middleware tests: valid token → passes, invalid token → 401, blacklisted token → 401
- Config: verify all framework config files parse correctly
