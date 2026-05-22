# Multi-Framework Adaptation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor the JWT plugin to support webman, Laravel, ThinkPHP, and Hyperf natively, each following its own plugin conventions with deep integration (ServiceProvider, Facade, Middleware, InstallCommand, config publishing).

**Architecture:** Core JWT logic becomes framework-agnostic (config array + PSR-3 logger + connection resolvers injected). Each framework gets a thin adapter layer under `src/erik-jwt/<Framework>/` that wires framework connections and registers itself via the framework's plugin mechanism.

**Tech Stack:** PHP >=7.4, firebase/php-jwt ^6.0, psr/log ^1.0|^2.0|^3.0, PDO, PSR-3

---

## File Structure

```
Modify: src/erik-jwt/JWT.php
Modify: src/erik-jwt/JWTFactory.php
Modify: src/erik-jwt/RedisTokenStorage.php
Modify: src/erik-jwt/DatabaseTokenStorage.php
Modify: composer.json
Create: src/erik-jwt/Webman/Middleware.php
Create: src/erik-jwt/Laravel/config/jwt.php
Create: src/erik-jwt/Laravel/JWTServiceProvider.php
Create: src/erik-jwt/Laravel/Facade.php
Create: src/erik-jwt/Laravel/Middleware.php
Create: src/erik-jwt/Laravel/InstallCommand.php
Create: src/erik-jwt/Laravel/helpers.php
Create: src/erik-jwt/ThinkPHP/config/jwt.php
Create: src/erik-jwt/ThinkPHP/JWTService.php
Create: src/erik-jwt/ThinkPHP/Facade.php
Create: src/erik-jwt/ThinkPHP/Middleware.php
Create: src/erik-jwt/ThinkPHP/InstallCommand.php
Create: src/erik-jwt/ThinkPHP/helpers.php
Create: src/erik-jwt/Hyperf/config/jwt.php
Create: src/erik-jwt/Hyperf/ConfigProvider.php
Create: src/erik-jwt/Hyperf/Middleware.php
Create: src/erik-jwt/Hyperf/JWTAspect.php
Create: src/erik-jwt/Hyperf/JWT.php
Create: src/erik-jwt/Hyperf/InstallCommand.php
Modify: README.md
Modify: examples/usage.php
Unchanged: Config.php, JWTException.php, TokenStorageInterface.php, FileTokenStorage.php, MemcachedTokenStorage.php, RetryTokenStorage.php
```

---

## Phase 1: Core Layer Refactoring

### Task 1: Refactor JWT.php — inject config array and PSR-3 LoggerInterface

**Files:** Modify: `src/erik-jwt/JWT.php`

**Change summary:** Replace `__construct(string $secretKey, string $algorithm, ...)` with `__construct(array $config, ?LoggerInterface $logger)`. Remove `use support\Log`. Replace all `Log::error()` with `$this->logger->error()`. Add `use Psr\Log\LoggerInterface; use Psr\Log\NullLogger;`.

- [ ] **Step 1: Edit constructor signature**

Replace the constructor block (lines 27-42):
```php
    public function __construct(
        string $secretKey,
        string $algorithm = 'HS256',
        TokenStorageInterface $tokenStorage = null,
        string $issuer = '',
        string $audience = '',
        int $leeway = 0
    ) {
        $this->secretKey = $secretKey;
        $this->algorithm = $algorithm;
        $this->tokenStorage = $tokenStorage ?? new FileTokenStorage();
        $this->issuer = $issuer;
        $this->audience = $audience;
        $this->leeway = $leeway;
        $this->config = config('plugin.erikwang2013.jwt.jwt');
    }
```

With:
```php
    public function __construct(
        array $config,
        ?LoggerInterface $logger = null
    ) {
        $this->config       = $config;
        $this->secretKey    = $config['secret_key'] ?? '';
        $this->algorithm    = $config['algorithm'] ?? 'HS256';
        $this->issuer       = $config['issuer'] ?? '';
        $this->audience     = $config['audience'] ?? '';
        $this->leeway       = (int)($config['leeway'] ?? 0);
        $this->tokenStorage = $config['_token_storage'] ?? new FileTokenStorage();
        $this->logger       = $logger ?? new NullLogger();
    }
```

- [ ] **Step 2: Add imports and logger property**

Replace lines 12-16:
```php
use Exception;
use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use support\Log;
```
With:
```php
use Exception;
use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
```

Add after `private $config;`:
```php
    private $logger;
```

- [ ] **Step 3: Replace all `Log::error(` with `$this->logger->error(`**

Replace all 8 occurrences across lines 86, 89, 103, 158, 162, 165, 179, 184.

- [ ] **Step 4: Verify syntax**

Run: `php -l src/erik-jwt/JWT.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add src/erik-jwt/JWT.php
git commit -m "refactor(JWT): inject config array and PSR-3 logger, remove webman dependency

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Refactor JWTFactory.php — accept connections parameter

**Files:** Modify: `src/erik-jwt/JWTFactory.php`

**Change summary:** Change `createFromConfig(?Config $config = null)` to `createFromConfig(array $config, ?LoggerInterface $logger = null, array $connections = [])`. Remove `use support\Db`, `use Memcached` (move inside). Add `use PDO`, `use Psr\Log\LoggerInterface`. Change `createTokenStorage` to accept `$connections`. Each `create*Storage` method uses injected connections.

- [ ] **Step 1: Update imports**

Replace lines 12-14:
```php
use support\Db;
use Memcached;
use Exception;
```
With:
```php
use Memcached;
use PDO;
use Psr\Log\LoggerInterface;
```

- [ ] **Step 2: Rewrite `createFromConfig` method**

Replace with:
```php
    public static function createFromConfig(
        array $config,
        ?LoggerInterface $logger = null,
        array $connections = []
    ): JWT {
        $secretKey = $config['secret_key'] ?? '';
        if (empty($secretKey) || strlen($secretKey) < 16) {
            throw JWTException::configError('Secret key must be at least 16 characters');
        }

        $tokenStorage = self::createTokenStorage($config, $connections);
        $advancedConfig = $config['advanced'] ?? [];
        $retryAttempts = (int)($advancedConfig['retry_attempts'] ?? 3);
        $retryDelay    = (int)($advancedConfig['retry_delay'] ?? 100);

        if ($retryAttempts > 1) {
            $tokenStorage = new RetryTokenStorage($tokenStorage, $retryAttempts, $retryDelay);
        }

        $config['_token_storage'] = $tokenStorage;
        $jwt = new JWT($config, $logger);

        $autoCleanup = $advancedConfig['auto_cleanup'] ?? false;
        if ($autoCleanup) {
            self::setupAutoCleanup($jwt, $advancedConfig);
        }

        return $jwt;
    }
```

- [ ] **Step 3: Delete `getConfig()` method**

Remove the `public static function getConfig(): array` method (lines 19-22).

- [ ] **Step 4: Rewrite `createTokenStorage` and `create*Storage` methods**

Replace from `private static function createTokenStorage` through end of `createFileStorage` with:
```php
    private static function createTokenStorage(array $config, array $connections): TokenStorageInterface
    {
        $merged = array_merge(
            ['database' => 0, 'prefix' => 'jwt_blacklist:', 'path' => null, 'table_name' => 'jwt_blacklist', 'servers' => []],
            $config['storage'] ?? [],
            $config['storage']['config'] ?? []
        );
        $type = $merged['type'] ?? 'file';

        switch ($type) {
            case 'redis':
                return self::createRedisStorage($merged, $connections);
            case 'database':
                return self::createDatabaseStorage($merged, $connections);
            case 'memcached':
                return self::createMemcachedStorage($merged, $connections);
            case 'file':
            default:
                return self::createFileStorage($merged);
        }
    }

    private static function createRedisStorage(array $config, array $connections): RedisTokenStorage
    {
        $redisResolver = $connections['redis'] ?? null;
        if (!$redisResolver || !is_callable($redisResolver)) {
            throw JWTException::storageError('Redis resolver callable required when storage type is redis');
        }
        $prefix = $config['prefix'] ?? 'jwt_blacklist:';
        return new RedisTokenStorage($redisResolver, $prefix);
    }

    private static function createDatabaseStorage(array $config, array $connections): DatabaseTokenStorage
    {
        $pdo = $connections['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            throw JWTException::storageError('PDO instance required when storage type is database');
        }
        $tableName = $config['table_name'] ?? 'jwt_blacklist';
        return new DatabaseTokenStorage($pdo, $tableName);
    }

    private static function createMemcachedStorage(array $config, array $connections): MemcachedTokenStorage
    {
        $memcached = $connections['memcached'] ?? null;
        if (!$memcached instanceof Memcached) {
            $memcached = new Memcached();
            $servers = $config['servers'] ?? [['127.0.0.1', 11211]];
            $memcached->addServers($servers);
            if (isset($config['options'])) {
                $memcached->setOptions($config['options']);
            }
        }
        $prefix = $config['prefix'] ?? 'jwt_blacklist:';
        return new MemcachedTokenStorage($memcached, $prefix);
    }

    private static function createFileStorage(array $config): FileTokenStorage
    {
        $storagePath = $config['path'] ?? null;
        $gcProbability = $config['gc_probability'] ?? 0.1;
        $storage = new FileTokenStorage($storagePath);
        if (method_exists($storage, 'setGcProbability')) {
            $storage->setGcProbability($gcProbability);
        }
        return $storage;
    }
```

- [ ] **Step 5: Verify syntax**

Run: `php -l src/erik-jwt/JWTFactory.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add src/erik-jwt/JWTFactory.php
git commit -m "refactor(JWTFactory): accept connections parameter, remove webman helpers

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Refactor RedisTokenStorage.php — accept callable resolver

**Files:** Modify: `src/erik-jwt/RedisTokenStorage.php`

**Change summary:** Replace `use support\Redis` with callable injection. Constructor becomes `__construct(callable $redisResolver, string $prefix)`. Each method calls `($this->redisResolver)()` to get the Redis connection.

- [ ] **Step 1: Replace imports and constructor**

Replace lines 12-18:
```php
use support\Redis;
use Exception;

class RedisTokenStorage implements TokenStorageInterface
{
    private $prefix;
    private $connected = false;

    public function __construct(string $prefix = 'jwt_blacklist:')
    {
        $this->prefix = $prefix;
        $this->checkConnection();
    }
```

With:
```php
use Exception;

class RedisTokenStorage implements TokenStorageInterface
{
    private $redisResolver;
    private $prefix;
    private $connected = false;

    public function __construct(callable $redisResolver, string $prefix = 'jwt_blacklist:')
    {
        $this->redisResolver = $redisResolver;
        $this->prefix = $prefix;
        $this->checkConnection();
    }
```

- [ ] **Step 2: Update `checkConnection` to use resolver**

Replace the method body's `Redis::ping()` with `($this->redisResolver)()->ping()`.

- [ ] **Step 3: Update all `Redis::` calls**

In `ensureConnection`, `blacklist`, `isBlacklisted`, `reconnect` — replace `Redis::` with `($this->redisResolver)()`.

- [ ] **Step 4: Verify syntax**

Run: `php -l src/erik-jwt/RedisTokenStorage.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add src/erik-jwt/RedisTokenStorage.php
git commit -m "refactor(RedisTokenStorage): accept callable resolver, remove support\Redis

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Refactor DatabaseTokenStorage.php — accept PDO instance

**Files:** Modify: `src/erik-jwt/DatabaseTokenStorage.php`

**Change summary:** Replace `use support\Db` with PDO injection. Constructor becomes `__construct(PDO $pdo, string $tableName)`.

- [ ] **Step 1: Replace imports and constructor**

Replace lines 12-18:
```php
use support\Db;
use PDOException;

class DatabaseTokenStorage implements TokenStorageInterface
{
    private $pdo;
    private $tableName;

    public function __construct(string $tableName = 'jwt_blacklist')
    {
        $this->tableName = $tableName;
        $this->createTableIfNotExists();
    }
```

With:
```php
use PDO;
use PDOException;

class DatabaseTokenStorage implements TokenStorageInterface
{
    private $pdo;
    private $tableName;

    public function __construct(PDO $pdo, string $tableName = 'jwt_blacklist')
    {
        $this->pdo       = $pdo;
        $this->tableName = $tableName;
        $this->createTableIfNotExists();
    }
```

- [ ] **Step 2: Update `createTableIfNotExists`**

Replace `Db::exec($sql)` with `$this->pdo->exec($sql)`.

- [ ] **Step 3: Update `blacklist`, `isBlacklisted`, `cleanup`**

Replace `Db::prepare($sql)` with `$this->pdo->prepare($sql)` and `$stmt->execute(...)` calls remain unchanged.

- [ ] **Step 4: Verify syntax**

Run: `php -l src/erik-jwt/DatabaseTokenStorage.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add src/erik-jwt/DatabaseTokenStorage.php
git commit -m "refactor(DatabaseTokenStorage): accept PDO instance, remove support\Db

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: Update composer.json

**Files:** Modify: `composer.json`

- [ ] **Step 1: Update dependencies**

Replace the `require` block — remove `workerman/webman`, `webman/redis`, `monolog/monolog`. Add `psr/log`. Convert removed deps to `suggest`. Add `extra.laravel` for auto-discovery. Update keywords.

New composer.json:
```json
{
    "name": "erikwang2013/jwt-webman",
    "description": "A JWT plugin compatible with webman, Laravel, ThinkPHP, and Hyperf. Suitable for distributed deployment, simple and fast installation.",
    "keywords": ["erik","erikwang2013","jwt","webman","laravel","thinkphp","hyperf"],
    "type": "library",
    "license": "MIT",
    "minimum-stability": "dev",
    "authors": [
        {
            "name": "erik",
            "email": "erik@erik.xyz",
            "homepage": "https://erik.xyz",
            "role": "Developer"
        }
    ],
    "support": {
        "email": "erik@erik.xyz",
        "issues": "https://github.com/erikwang2013/jwt-webman/issues",
        "source": "https://github.com/erikwang2013/jwt-webman"
    },
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
            "providers": ["Erikwang2013\Jwt\\Laravel\\JWTServiceProvider"],
            "aliases": {"JWT": "Erikwang2013\Jwt\\Laravel\\Facade"}
        }
    },
    "repositories": {
        "packagist": {
            "type": "composer",
            "url": "https://repo.packagist.org/"
        }
    }
}
```

- [ ] **Step 2: Validate**

Run: `composer validate`
Expected: valid

- [ ] **Step 3: Commit**

```bash
git add composer.json
git commit -m "refactor(composer): relax framework deps, add psr/log, frameworks to suggest

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Phase 2: Webman Adapter

### Task 6: Create Webman Middleware

**Files:** Create: `src/erik-jwt/Webman/Middleware.php`

- [ ] **Step 1: Create directory and file**

```bash
mkdir -p src/erik-jwt/Webman
```

```php
<?php
/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik
 * Author: erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Webman;

use Erikwang2013\Jwt\JWTFactory;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class Middleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $config = config('plugin.erikwang2013.jwt.jwt', []);

        $except = $config['middleware']['except'] ?? [];
        $path   = $request->path();
        foreach ($except as $pattern) {
            if (preg_match('#^' . $pattern . '$#', $path)) {
                return $next($request);
            }
        }

        $token = $request->header('Authorization', '');
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        if (empty($token)) {
            return new Response(401, ['Content-Type' => 'application/json'],
                json_encode(['code' => 401, 'msg' => 'Token not provided', 'data' => null]));
        }

        try {
            $jwt = JWTFactory::createFromConfig($config, null, [
                'redis' => fn() => \support\Redis::class,
                'pdo'   => \support\Db::connection()->getPdo(),
            ]);
            $payload = $jwt->decode($token);
            $request->jwt_payload = $payload;
        } catch (\Erikwang2013\Jwt\JWTException $e) {
            return new Response(401, ['Content-Type' => 'application/json'],
                json_encode(['code' => 401, 'msg' => $e->getMessage(), 'data' => null]));
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Verify**

Run: `php -l src/erik-jwt/Webman/Middleware.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/erik-jwt/Webman/
git commit -m "feat(webman): add JWT middleware with except route patterns

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```


---

## Phase 3: Laravel Adapter

### Task 7: Create Laravel config

**Files:** Create: `src/erik-jwt/Laravel/config/jwt.php`

- [ ] **Step 1: Create directory and file**

```bash
mkdir -p src/erik-jwt/Laravel/config
```

```php
<?php

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
        'database' => (int) env('JWT_STORAGE_DATABASE', 0),
    ],
    'advanced' => [
        'retry_attempts'   => (int) env('JWT_ADVANCED_RETRY_ATTEMPTS', 3),
        'retry_delay'      => (int) env('JWT_ADVANCED_RETRY_DELAY', 100),
        'auto_cleanup'      => filter_var(env('JWT_AUTO_CLEANUP', false), FILTER_VALIDATE_BOOLEAN),
        'cleanup_interval'  => (int) env('JWT_CLEANUP_INTERVAL', 3600),
    ],
    'middleware' => [
        'except' => env('JWT_MIDDLEWARE_EXCEPT', []),
    ],
];
```

- [ ] **Step 2: Commit**

```bash
git add src/erik-jwt/Laravel/
git commit -m "feat(laravel): add config file

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 8: Create Laravel JWTServiceProvider

**Files:** Create: `src/erik-jwt/Laravel/JWTServiceProvider.php`

- [ ] **Step 1: Create the service provider**

```php
<?php

namespace Erikwang2013\Jwt\Laravel;

use Erikwang2013\Jwt\JWTFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;

class JWTServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/jwt.php', 'jwt');

        $this->app->singleton('erik.jwt', function ($app) {
            $config = $app['config']->get('jwt', []);
            return JWTFactory::createFromConfig($config, $app['log']->channel(), [
                'redis' => fn() => Redis::connection()->client(),
                'pdo'   => DB::connection()->getPdo(),
            ]);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/jwt.php' => config_path('jwt.php'),
            ], 'jwt-config');

            $this->commands([InstallCommand::class]);
        }

        $this->app['router']->aliasMiddleware('jwt', Middleware::class);
    }
}
```

- [ ] **Step 2: Verify**

Run: `php -l src/erik-jwt/Laravel/JWTServiceProvider.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/erik-jwt/Laravel/JWTServiceProvider.php
git commit -m "feat(laravel): add JWTServiceProvider with singleton binding and config publish

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 9: Create Laravel Facade

**Files:** Create: `src/erik-jwt/Laravel/Facade.php`

- [ ] **Step 1: Create facade**

```php
<?php

namespace Erikwang2013\Jwt\Laravel;

use Illuminate\Support\Facades\Facade as LaravelFacade;

/**
 * @method static string encode(array $payload, int $expire = 0, array $headers = [])
 * @method static array  decode(string $token)
 * @method static bool   validate(string $token)
 * @method static string refresh(string $token, int $newExpire = 3600)
 * @method static bool   blacklist(string $token)
 * @method static bool   isBlacklisted(string $token)
 */
class Facade extends LaravelFacade
{
    protected static function getFacadeAccessor(): string
    {
        return 'erik.jwt';
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/erik-jwt/Laravel/Facade.php
git commit -m "feat(laravel): add JWT facade

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 10: Create Laravel Middleware

**Files:** Create: `src/erik-jwt/Laravel/Middleware.php`

- [ ] **Step 1: Create middleware**

```php
<?php

namespace Erikwang2013\Jwt\Laravel;

use Closure;
use Erikwang2013\Jwt\JWTException;
use Illuminate\Http\Request;

class Middleware
{
    public function handle(Request $request, Closure $next)
    {
        $jwt = app('erik.jwt');

        $except = config('jwt.middleware.except', []);
        $path   = $request->path();
        foreach ($except as $pattern) {
            if (preg_match('#^' . $pattern . '$#', $path)) {
                return $next($request);
            }
        }

        $token = $request->header('Authorization', '');
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        if (empty($token)) {
            return response()->json([
                'code' => 401, 'msg' => 'Token not provided', 'data' => null
            ], 401);
        }

        try {
            $payload = $jwt->decode($token);
            $request->attributes->set('jwt_payload', $payload);
        } catch (JWTException $e) {
            return response()->json([
                'code' => 401, 'msg' => $e->getMessage(), 'data' => null
            ], 401);
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Verify**

Run: `php -l src/erik-jwt/Laravel/Middleware.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/erik-jwt/Laravel/Middleware.php
git commit -m "feat(laravel): add JWT middleware with except route patterns

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 11: Create Laravel InstallCommand

**Files:** Create: `src/erik-jwt/Laravel/InstallCommand.php`

- [ ] **Step 1: Create install command**

```php
<?php

namespace Erikwang2013\Jwt\Laravel;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature   = 'jwt:install';
    protected $description = 'Install erik JWT: publish config and generate secret key';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'jwt-config']);

        $secretKey = bin2hex(random_bytes(32));
        $envPath   = base_path('.env');

        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if (strpos($envContent, 'JWT_SECRET_KEY=') !== false) {
                $envContent = preg_replace(
                    '/^JWT_SECRET_KEY=.*$/m',
                    'JWT_SECRET_KEY=' . $secretKey,
                    $envContent
                );
            } else {
                $envContent .= "\nJWT_SECRET_KEY={$secretKey}\n";
            }
            file_put_contents($envPath, $envContent);
        }

        $this->info('JWT plugin installed successfully!');
        $this->info("JWT_SECRET_KEY: {$secretKey}");

        return 0;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/erik-jwt/Laravel/InstallCommand.php
git commit -m "feat(laravel): add jwt:install artisan command

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 12: Create Laravel helpers

**Files:** Create: `src/erik-jwt/Laravel/helpers.php`

- [ ] **Step 1: Create helpers**

```php
<?php

if (!function_exists('jwt')) {
    function jwt(): \Erikwang2013\Jwt\JWT
    {
        return app('erik.jwt');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/erik-jwt/Laravel/helpers.php
git commit -m "feat(laravel): add jwt() helper function

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```


---

## Phase 4: ThinkPHP Adapter

### Task 13: Create ThinkPHP config

**Files:** Create: `src/erik-jwt/ThinkPHP/config/jwt.php`

- [ ] **Step 1: Create directory and config**

```bash
mkdir -p src/erik-jwt/ThinkPHP/config
```

```php
<?php

return [
    'secret_key'     => env('JWT.SECRET_KEY', ''),
    'algorithm'      => env('JWT.ALGORITHM', 'HS256'),
    'issuer'         => env('JWT.ISSUER', ''),
    'audience'       => env('JWT.AUDIENCE', ''),
    'leeway'         => (int) env('JWT.LEEWAY', 0),
    'default_expire' => (int) env('JWT.DEFAULT_EXPIRE', 3600),
    'refresh_expire' => (int) env('JWT.REFRESH_EXPIRE', 7200),
    'storage' => [
        'type'     => env('JWT.STORAGE_TYPE', 'file'),
        'prefix'   => env('JWT.STORAGE_PREFIX', 'jwt_blacklist:'),
        'database' => (int) env('JWT.STORAGE_DATABASE', 0),
    ],
    'advanced' => [
        'retry_attempts'   => (int) env('JWT.ADVANCED_RETRY_ATTEMPTS', 3),
        'retry_delay'      => (int) env('JWT.ADVANCED_RETRY_DELAY', 100),
        'auto_cleanup'      => filter_var(env('JWT.AUTO_CLEANUP', false), FILTER_VALIDATE_BOOLEAN),
        'cleanup_interval'  => (int) env('JWT.CLEANUP_INTERVAL', 3600),
    ],
    'middleware' => [
        'except' => env('JWT.MIDDLEWARE_EXCEPT', []),
    ],
];
```

- [ ] **Step 2: Commit**

```bash
git add src/erik-jwt/ThinkPHP/
git commit -m "feat(thinkphp): add config file

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 14: Create ThinkPHP JWTService

**Files:** Create: `src/erik-jwt/ThinkPHP/JWTService.php`

- [ ] **Step 1: Create service**

```php
<?php

namespace Erikwang2013\Jwt\ThinkPHP;

use Erikwang2013\Jwt\JWTFactory;
use think\Service;

class JWTService extends Service
{
    public function register(): void
    {
        $this->app->bind('erik.jwt', function ($app) {
            $config = $app->config->get('jwt', []);

            $connections = [];
            if (($config['storage']['type'] ?? '') === 'redis') {
                $connections['redis'] = fn() => \think\facade\Cache::store('redis')->handler();
            }
            if (($config['storage']['type'] ?? '') === 'database') {
                $connections['pdo'] = \think\facade\Db::connect()->getPdo();
            }

            return JWTFactory::createFromConfig($config, null, $connections);
        });
    }

    public function boot(): void
    {
        $this->app->middleware->alias('jwt', Middleware::class);
    }
}
```

- [ ] **Step 2: Verify**

Run: `php -l src/erik-jwt/ThinkPHP/JWTService.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/erik-jwt/ThinkPHP/JWTService.php
git commit -m "feat(thinkphp): add JWTService with container binding

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 15: Create ThinkPHP Facade

**Files:** Create: `src/erik-jwt/ThinkPHP/Facade.php`

- [ ] **Step 1: Create facade**

```php
<?php

namespace Erikwang2013\Jwt\ThinkPHP;

use think\Facade;

/**
 * @method static string encode(array $payload, int $expire = 0, array $headers = [])
 * @method static array  decode(string $token)
 * @method static bool   validate(string $token)
 * @method static string refresh(string $token, int $newExpire = 3600)
 * @method static bool   blacklist(string $token)
 * @method static bool   isBlacklisted(string $token)
 */
class JWT extends Facade
{
    protected static function getFacadeClass(): string
    {
        return 'erik.jwt';
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/erik-jwt/ThinkPHP/Facade.php
git commit -m "feat(thinkphp): add JWT facade

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 16: Create ThinkPHP Middleware

**Files:** Create: `src/erik-jwt/ThinkPHP/Middleware.php`

- [ ] **Step 1: Create middleware**

```php
<?php

namespace Erikwang2013\Jwt\ThinkPHP;

use Closure;
use Erikwang2013\Jwt\JWTException;
use think\Request;
use think\Response;

class Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $jwt    = app('erik.jwt');
        $config = config('jwt');

        $except = $config['middleware']['except'] ?? [];
        $path   = $request->pathinfo();
        foreach ($except as $pattern) {
            if (preg_match('#^' . $pattern . '$#', $path)) {
                return $next($request);
            }
        }

        $token = $request->header('Authorization', '');
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        if (empty($token)) {
            return json(['code' => 401, 'msg' => 'Token not provided', 'data' => null])->code(401);
        }

        try {
            $payload = $jwt->decode($token);
            $request->jwt_payload = $payload;
        } catch (JWTException $e) {
            return json(['code' => 401, 'msg' => $e->getMessage(), 'data' => null])->code(401);
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Verify**

Run: `php -l src/erik-jwt/ThinkPHP/Middleware.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/erik-jwt/ThinkPHP/Middleware.php
git commit -m "feat(thinkphp): add JWT middleware with except route patterns

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 17: Create ThinkPHP InstallCommand

**Files:** Create: `src/erik-jwt/ThinkPHP/InstallCommand.php`

- [ ] **Step 1: Create install command**

```php
<?php

namespace Erikwang2013\Jwt\ThinkPHP;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class InstallCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('jwt:install')
             ->setDescription('Install erik JWT: publish config and generate secret key');
    }

    protected function execute(Input $input, Output $output): int
    {
        $source = __DIR__ . '/config/jwt.php';
        $dest   = app()->getConfigPath() . 'jwt.php';

        if (!file_exists($dest)) {
            copy($source, $dest);
            $output->info("Config published to: {$dest}");
        } else {
            $output->warning("Config already exists at: {$dest}");
        }

        $secretKey = bin2hex(random_bytes(32));
        $envPath   = app()->getRootPath() . '.env';

        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if (strpos($envContent, 'JWT.SECRET_KEY=') !== false) {
                $envContent = preg_replace(
                    '/^JWT\.SECRET_KEY=.*$/m',
                    'JWT.SECRET_KEY=' . $secretKey,
                    $envContent
                );
            } else {
                $envContent .= "\n[JWT]\nSECRET_KEY={$secretKey}\n";
            }
            file_put_contents($envPath, $envContent);
        }

        $output->info('JWT plugin installed successfully!');
        $output->info("JWT.SECRET_KEY: {$secretKey}");

        return 0;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/erik-jwt/ThinkPHP/InstallCommand.php
git commit -m "feat(thinkphp): add jwt:install command

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 18: Create ThinkPHP helpers

**Files:** Create: `src/erik-jwt/ThinkPHP/helpers.php`

- [ ] **Step 1: Create helpers**

```php
<?php

if (!function_exists('jwt')) {
    function jwt(): \Erikwang2013\Jwt\JWT
    {
        return app('erik.jwt');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/erik-jwt/ThinkPHP/helpers.php
git commit -m "feat(thinkphp): add jwt() helper function

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```


---

## Phase 5: Hyperf Adapter

### Task 19: Create Hyperf config

**Files:** Create: `src/erik-jwt/Hyperf/config/jwt.php`

- [ ] **Step 1: Create directory and config**

```bash
mkdir -p src/erik-jwt/Hyperf
```

```php
<?php

declare(strict_types=1);

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
        'database' => (int) env('JWT_STORAGE_DATABASE', 0),
    ],
    'advanced' => [
        'retry_attempts'   => (int) env('JWT_ADVANCED_RETRY_ATTEMPTS', 3),
        'retry_delay'      => (int) env('JWT_ADVANCED_RETRY_DELAY', 100),
        'auto_cleanup'      => filter_var(env('JWT_AUTO_CLEANUP', false), FILTER_VALIDATE_BOOLEAN),
        'cleanup_interval'  => (int) env('JWT_CLEANUP_INTERVAL', 3600),
    ],
    'middleware' => [
        'except' => env('JWT_MIDDLEWARE_EXCEPT', []),
    ],
];
```

- [ ] **Step 2: Commit**

```bash
git add src/erik-jwt/Hyperf/
git commit -m "feat(hyperf): add config file

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 20: Create Hyperf #[JWT] Attribute

**Files:** Create: `src/erik-jwt/Hyperf/JWT.php`

- [ ] **Step 1: Create Attribute**

```php
<?php

declare(strict_types=1);

namespace Erikwang2013\Jwt\Hyperf;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_METHOD)]
class JWT extends AbstractAnnotation
{
}
```

- [ ] **Step 2: Commit**

```bash
git add src/erik-jwt/Hyperf/JWT.php
git commit -m "feat(hyperf): add #[JWT] attribute for AOP

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 21: Create Hyperf ConfigProvider

**Files:** Create: `src/erik-jwt/Hyperf/ConfigProvider.php`

- [ ] **Step 1: Create ConfigProvider**

```php
<?php

declare(strict_types=1);

namespace Erikwang2013\Jwt\Hyperf;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                \Erikwang2013\Jwt\JWT::class => function (ContainerInterface $container) {
                    $config = $container->get(ConfigInterface::class)->get('jwt', []);
                    $logger = $container->get(\Psr\Log\LoggerInterface::class);

                    $connections = [];
                    if (($config['storage']['type'] ?? '') === 'redis') {
                        $connections['redis'] = fn() => $container->get(\Hyperf\Redis\Redis::class);
                    }
                    if (($config['storage']['type'] ?? '') === 'database') {
                        $connections['pdo'] = $container->get(\Hyperf\DbConnection\Db::class)->connection()->getPdo();
                    }

                    return \Erikwang2013\Jwt\JWTFactory::createFromConfig($config, $logger, $connections);
                },
            ],
            'middlewares' => [
                'http' => [\Erikwang2013\Jwt\Hyperf\Middleware::class],
            ],
            'commands' => [
                InstallCommand::class,
            ],
            'publish' => [
                [
                    'id'          => 'config',
                    'description' => 'JWT config file.',
                    'source'      => __DIR__ . '/config/jwt.php',
                    'destination' => BASE_PATH . '/config/autoload/jwt.php',
                ],
            ],
        ];
    }
}
```

- [ ] **Step 2: Verify**

Run: `php -l src/erik-jwt/Hyperf/ConfigProvider.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/erik-jwt/Hyperf/ConfigProvider.php
git commit -m "feat(hyperf): add ConfigProvider with DI, middleware, and command

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 22: Create Hyperf Middleware

**Files:** Create: `src/erik-jwt/Hyperf/Middleware.php`

- [ ] **Step 1: Create middleware**

```php
<?php

declare(strict_types=1);

namespace Erikwang2013\Jwt\Hyperf;

use Erikwang2013\Jwt\JWT as JWTInstance;
use Erikwang2013\Jwt\JWTException;
use Hyperf\Contract\ConfigInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Middleware implements MiddlewareInterface
{
    protected JWTInstance $jwt;
    protected ConfigInterface $config;

    public function __construct(JWTInstance $jwt, ConfigInterface $config)
    {
        $this->jwt    = $jwt;
        $this->config = $config;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $config = $this->config->get('jwt', []);

        $except = $config['middleware']['except'] ?? [];
        $path   = $request->getUri()->getPath();
        foreach ($except as $pattern) {
            if (preg_match('#^' . $pattern . '$#', $path)) {
                return $handler->handle($request);
            }
        }

        $token = $request->getHeaderLine('Authorization');
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        if (empty($token)) {
            $response = new \Hyperf\HttpMessage\Server\Response();
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401)
                ->withBody(new \Hyperf\HttpMessage\Stream\SwooleStream(
                    json_encode(['code' => 401, 'msg' => 'Token not provided', 'data' => null])
                ));
        }

        try {
            $payload = $this->jwt->decode($token);
            $request = $request->withAttribute('jwt_payload', $payload);
        } catch (JWTException $e) {
            $response = new \Hyperf\HttpMessage\Server\Response();
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401)
                ->withBody(new \Hyperf\HttpMessage\Stream\SwooleStream(
                    json_encode(['code' => 401, 'msg' => $e->getMessage(), 'data' => null])
                ));
        }

        return $handler->handle($request);
    }
}
```

- [ ] **Step 2: Verify**

Run: `php -l src/erik-jwt/Hyperf/Middleware.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/erik-jwt/Hyperf/Middleware.php
git commit -m "feat(hyperf): add PSR-15 middleware with constructor DI

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 23: Create Hyperf JWTAspect

**Files:** Create: `src/erik-jwt/Hyperf/JWTAspect.php`

- [ ] **Step 1: Create AOP aspect**

```php
<?php

declare(strict_types=1);

namespace Erikwang2013\Jwt\Hyperf;

use Erikwang2013\Jwt\JWTException;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;

#[Aspect]
class JWTAspect extends AbstractAspect
{
    public array $annotations = [
        JWT::class,
    ];

    public function __construct(
        protected ContainerInterface $container,
        protected RequestInterface $request,
        protected ResponseInterface $response
    ) {
    }

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $jwt    = $this->container->get(\Erikwang2013\Jwt\JWT::class);
        $config = $this->container->get(\Hyperf\Contract\ConfigInterface::class)->get('jwt', []);

        $except = $config['middleware']['except'] ?? [];
        $path   = $this->request->getUri()->getPath();
        foreach ($except as $pattern) {
            if (preg_match('#^' . $pattern . '$#', $path)) {
                return $proceedingJoinPoint->process();
            }
        }

        $token = $this->request->getHeaderLine('Authorization');
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        if (empty($token)) {
            return $this->response->json([
                'code' => 401, 'msg' => 'Token not provided', 'data' => null
            ])->withStatus(401);
        }

        try {
            $payload = $jwt->decode($token);
            // Note: AOP aspect can't directly modify request attributes for downstream.
            // Use Middleware for attribute injection; Aspect here serves as guard.
        } catch (JWTException $e) {
            return $this->response->json([
                'code' => 401, 'msg' => $e->getMessage(), 'data' => null
            ])->withStatus(401);
        }

        return $proceedingJoinPoint->process();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/erik-jwt/Hyperf/JWTAspect.php
git commit -m "feat(hyperf): add AOP aspect for #[JWT] attribute guard

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 24: Create Hyperf InstallCommand

**Files:** Create: `src/erik-jwt/Hyperf/InstallCommand.php`

- [ ] **Step 1: Create install command**

```php
<?php

declare(strict_types=1);

namespace Erikwang2013\Jwt\Hyperf;

use Hyperf\Command\Command;
use Psr\Container\ContainerInterface;

class InstallCommand extends Command
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct('jwt:install');
        $this->container = $container;
    }

    public function configure(): void
    {
        parent::configure();
        $this->setDescription('Install erik JWT: publish config and generate secret key');
    }

    public function handle(): void
    {
        $source = __DIR__ . '/config/jwt.php';
        $dest   = BASE_PATH . '/config/autoload/jwt.php';

        if (!file_exists($dest)) {
            copy($source, $dest);
            $this->info("Config published to: {$dest}");
        } else {
            $this->warn("Config already exists at: {$dest}");
        }

        $secretKey = bin2hex(random_bytes(32));
        $envPath   = BASE_PATH . '/.env';

        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if (strpos($envContent, 'JWT_SECRET_KEY=') !== false) {
                $envContent = preg_replace(
                    '/^JWT_SECRET_KEY=.*$/m',
                    'JWT_SECRET_KEY=' . $secretKey,
                    $envContent
                );
            } else {
                $envContent .= "\nJWT_SECRET_KEY={$secretKey}\n";
            }
            file_put_contents($envPath, $envContent);
        }

        $this->info('JWT plugin installed successfully!');
        $this->info("JWT_SECRET_KEY: {$secretKey}");
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/erik-jwt/Hyperf/InstallCommand.php
git commit -m "feat(hyperf): add jwt:install command

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```


---

## Phase 6: Documentation and Final Verification

### Task 25: Update README.md with all framework usage

**Files:** Modify: `README.md`

- [ ] **Step 1: Rewrite README**

Replace the entire README.md content with:

```markdown
# erikwang2013/jwt-webman

A JWT authentication plugin compatible with webman, Laravel, ThinkPHP, and Hyperf. Suitable for distributed deployment, with simple and fast installation.

Author: [艾瑞可erik](https://erik.xyz)

## Features

- JWT token generation (HS256/HS384/HS512/RS256)
- Token validation with time tolerance
- Refresh token support
- Token blacklist (redis, database, memcached, file)
- Automatic retry on storage failure
- Graceful degradation with fallback storage
- Multi-framework deep integration: Middleware, Facade, InstallCommand

## Installation

```sh
composer require erikwang2013/jwt-webman
```

## Framework Integration

### Webman

After `composer require`, the plugin auto-registers via webman's plugin system.

**Config:** `config/plugin/erikwang2013/jwt/jwt.php`

**Usage:**

```php
use Erikwang2013\Jwt\JWTFactory;

$jwt = JWTFactory::createFromConfig(
    config('plugin.erikwang2013.jwt.jwt'),
    null,
    [
        'redis' => fn() => \support\Redis::class,
        'pdo'   => \support\Db::connection()->getPdo(),
    ]
);

$token   = $jwt->encode(['user_id' => 1]);
$payload = $jwt->decode($token);
$jwt->blacklist($token);
```

**Middleware:** Register in `config/middleware.php`:

```php
return [
    '' => [
        \Erikwang2013\Jwt\Webman\Middleware::class,
    ],
];
```

---

### Laravel

After `composer require`, Laravel auto-discovers the ServiceProvider via `extra.laravel` in composer.json. If auto-discovery is disabled, add to `config/app.php`:

```php
'providers' => [
    Erikwang2013\Jwt\Laravel\JWTServiceProvider::class,
],
```

**Install:**

```sh
php artisan jwt:install
```

**Config:** `config/jwt.php`

**Usage — Facade:**

```php
use Erikwang2013\Jwt\Laravel\Facade as JWT;

$token   = JWT::encode(['user_id' => 1]);
$payload = JWT::decode($token);
JWT::blacklist($token);
```

**Usage — Helper:**

```php
$token = jwt()->encode(['user_id' => 1]);
```

**Usage — Dependency Injection:**

```php
use Erikwang2013\Jwt\JWT;

public function __construct(JWT $jwt) {
    $this->jwt = $jwt;
}
```

**Middleware:**

```php
Route::middleware('jwt')->group(function () {
    Route::get('/api/user', [UserController::class, 'index']);
});

// In controller
public function index(Request $request) {
    $payload = $request->attributes->get('jwt_payload');
}
```

**Config publishing:**

```sh
php artisan vendor:publish --tag=jwt-config
```

---

### ThinkPHP

Register the service in `app/service.php` after `composer require`:

```php
return [
    \Erikwang2013\Jwt\ThinkPHP\JWTService::class,
];
```

**Install:**

```sh
php think jwt:install
```

**Config:** `config/jwt.php`

**Usage — Facade:**

```php
use Erikwang2013\Jwt\ThinkPHP\JWT;

$token   = JWT::encode(['user_id' => 1]);
$payload = JWT::decode($token);
```

**Usage — Helper:**

```php
$token = jwt()->encode(['user_id' => 1]);
```

**Middleware:**

```php
Route::group(function () {
    Route::get('/api/user', 'UserController@index');
})->middleware('jwt');

// In controller
public function index(Request $request) {
    $payload = $request->jwt_payload;
}
```

---

### Hyperf

After `composer require`, register ConfigProvider in `config/autoload/dependencies.php`:

```php
return [
    \Erikwang2013\Jwt\Hyperf\ConfigProvider::class,
];
```

**Install:**

```sh
php bin/hyperf.php jwt:install
```

**Config:** `config/autoload/jwt.php`

**Usage — Dependency Injection:**

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

**Middleware:** already registered by ConfigProvider in `config/autoload/middlewares.php`.

**AOP Annotation:**

```php
use Erikwang2013\Jwt\Hyperf\JWT as JWTAuth;

class UserController {
    #[JWTAuth]
    public function index() {
        // Auto validates JWT before execution
    }
}
```

---

## Config Reference

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
        'database' => (int) env('JWT_STORAGE_DATABASE', 0),
    ],
    'advanced' => [
        'retry_attempts'   => (int) env('JWT_ADVANCED_RETRY_ATTEMPTS', 3),
        'retry_delay'      => (int) env('JWT_ADVANCED_RETRY_DELAY', 100),
        'auto_cleanup'      => filter_var(env('JWT_AUTO_CLEANUP', false), FILTER_VALIDATE_BOOLEAN),
        'cleanup_interval'  => (int) env('JWT_CLEANUP_INTERVAL', 3600),
    ],
    'middleware' => [
        'except' => [],
    ],
];
```

| Storage Type | Best For |
|-------------|----------|
| `file` | Single-server, low traffic |
| `redis` | Distributed, high performance |
| `database` | Persistent, cross-datacenter |
| `memcached` | High throughput, auto-expiry |

## License

MIT
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: add multi-framework usage guide for webman, Laravel, ThinkPHP, Hyperf

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 26: Update examples/usage.php

**Files:** Modify: `examples/usage.php`

- [ ] **Step 1: Rewrite example to be framework-agnostic**

Replace the entire file content:

```php
<?php
/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik
 * Author: erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Erikwang2013\Jwt\Config;
use Erikwang2013\Jwt\JWTFactory;
use Erikwang2013\Jwt\JWTException;

// Framework-agnostic example
$config = [
    'secret_key'     => 'test-secret-key-at-least-16-chars',
    'algorithm'      => 'HS256',
    'issuer'         => 'test-app',
    'audience'       => 'test-users',
    'leeway'         => 60,
    'default_expire' => 3600,
    'refresh_expire' => 7200,
    'storage'        => ['type' => 'file'],
    'advanced'       => [
        'retry_attempts' => 1,
        'auto_cleanup'   => false,
    ],
];

try {
    $jwt = JWTFactory::createFromConfig($config);

    $token = $jwt->encode(['user_id' => 123, 'username' => 'testuser']);
    echo "Token generated: " . substr($token, 0, 50) . "...\n";

    $refreshToken = $jwt->encode(['user_id' => 123, 'token_type' => 'refresh'], 86400);
    echo "Refresh token generated\n";

    $payload = $jwt->decode($token);
    echo "Token validated for user: " . $payload['username'] . "\n";

    echo "Token valid: " . ($jwt->validate($token) ? 'yes' : 'no') . "\n";

    $jwt->blacklist($token);
    echo "Token blacklisted\n";

    if ($jwt->isBlacklisted($token)) {
        echo "Token correctly identified as blacklisted\n";
    }

} catch (JWTException $e) {
    switch ($e->getCode()) {
        case JWTException::STORAGE_ERROR:
            echo "Storage error: " . $e->getMessage() . "\n";
            break;
        case JWTException::NETWORK_ERROR:
            echo "Network error: " . $e->getMessage() . "\n";
            break;
        case JWTException::CONFIG_ERROR:
            echo "Configuration error: " . $e->getMessage() . "\n";
            break;
        default:
            echo "JWT error: " . $e->getMessage() . "\n";
            break;
    }
} catch (Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
}
```

- [ ] **Step 2: Verify syntax**

Run: `php -l examples/usage.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add examples/usage.php
git commit -m "docs: update example to work framework-agnostic

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 27: Final verification

- [ ] **Step 1: Syntax check all new and modified files**

```bash
php -l src/erik-jwt/JWT.php
php -l src/erik-jwt/JWTFactory.php
php -l src/erik-jwt/RedisTokenStorage.php
php -l src/erik-jwt/DatabaseTokenStorage.php
php -l src/erik-jwt/Webman/Middleware.php
php -l src/erik-jwt/Laravel/JWTServiceProvider.php
php -l src/erik-jwt/Laravel/Middleware.php
php -l src/erik-jwt/ThinkPHP/JWTService.php
php -l src/erik-jwt/ThinkPHP/Middleware.php
php -l src/erik-jwt/Hyperf/ConfigProvider.php
php -l src/erik-jwt/Hyperf/Middleware.php
php -l examples/usage.php
```

Expected: All return `No syntax errors detected`

- [ ] **Step 2: composer validate**

```bash
composer validate
```

Expected: `./composer.json is valid`

- [ ] **Step 3: Run example**

```bash
php examples/usage.php
```

Expected: Successful token generation, validation, and blacklist output.

- [ ] **Step 4: Final commit**

```bash
git status
git commit -m "chore: final verification pass

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```
