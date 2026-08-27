# 全球游戏聚合平台基础版 — 实现计划
<!-- lang-nav -->

Languages: **中文** · [English](2026-05-22-game-platform-plan.en.md) · [한국어](2026-05-22-game-platform-plan.ko.md) · [Русский](2026-05-22-game-platform-plan.ru.md) · [Deutsch](2026-05-22-game-platform-plan.de.md) · [Français](2026-05-22-game-platform-plan.fr.md) · [Español](2026-05-22-game-platform-plan.es.md) · [Português](2026-05-22-game-platform-plan.pt.md) · [हिन्दी](2026-05-22-game-platform-plan.hi.md) · [العربية](2026-05-22-game-platform-plan.ar.md) · [বাংলা](2026-05-22-game-platform-plan.bn.md) · [Bahasa Indonesia](2026-05-22-game-platform-plan.id.md) · [日本語](2026-05-22-game-platform-plan.ja.md)


> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 跑通核心闭环：注册 → 充值 → 兑换 → 游戏币 → 提现 → 后台审核

**Architecture:** admin/（管理后台）和 service/（C端业务）是独立 webman v2 实例，通过 common/（PSR-4 autoload）共享 Model 和 Service。数据库迁移统一放在 install/。前端 Flutter PC 管理后台扩展 + 新建 C 端 PC 平台。

**Tech Stack:** PHP 8.3+, webman v2, MySQL 8.0, Redis, erikwang2013/* 系列组件

---

## File Structure

```
game-platform-php/
├── common/
│   ├── composer.json
│   ├── model/
│   │   ├── User.php                    # C端用户
│   │   ├── UserWallet.php              # 平台币钱包（含乐观锁）
│   │   ├── UserGameWallet.php          # 游戏币钱包
│   │   ├── Game.php                    # 游戏
│   │   ├── GameCurrency.php            # 游戏币种
│   │   ├── DepositOrder.php            # 充值订单
│   │   ├── WithdrawOrder.php           # 提现订单
│   │   ├── ExchangeRecord.php          # 兑换记录
│   │   ├── Transaction.php             # 平台流水
│   │   ├── PaymentMethod.php           # 支付方式
│   │   ├── Announcement.php            # 公告
│   │   └── PlatformConfig.php          # 平台配置
│   └── middleware/
│       └── UserAuth.php                # C端JWT用户认证中间件
│
├── service/
│   ├── composer.json
│   ├── start.php
│   ├── windows.php
│   ├── .env.example
│   ├── config/
│   │   ├── app.php
│   │   ├── autoload.php
│   │   ├── bootstrap.php
│   │   ├── container.php
│   │   ├── database.php
│   │   ├── dependence.php
│   │   ├── exception.php
│   │   ├── hashids.php
│   │   ├── jwt.php
│   │   ├── log.php
│   │   ├── middleware.php
│   │   ├── process.php
│   │   ├── route.php
│   │   ├── server.php
│   │   ├── session.php
│   │   ├── snowflake.php
│   │   ├── static.php
│   │   ├── translation.php
│   │   └── view.php
│   ├── app/
│   │   ├── api/v1/controller/
│   │   │   ├── BaseController.php
│   │   │   ├── AuthController.php
│   │   │   ├── WalletController.php
│   │   │   ├── DepositController.php
│   │   │   ├── WithdrawController.php
│   │   │   ├── ExchangeController.php
│   │   │   ├── GameController.php
│   │   │   └── UserController.php
│   │   ├── common/
│   │   │   ├── HashidsService.php
│   │   │   ├── SnowflakeService.php
│   │   │   └── EncryptionService.php
│   │   └── functions.php
│   ├── support/
│   │   ├── bootstrap.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   └── Setup.php
│   └── install/ -> ../../install/  (symlink)
│
├── admin/
│   ├── composer.json                  # 增加 common/ path repo + new controllers
│   ├── app/admin/controller/
│   │   ├── GameController.php         # 新增
│   │   ├── WithdrawController.php     # 新增
│   │   ├── PaymentController.php      # 新增
│   │   ├── PlatformUserController.php # 新增
│   │   ├── AnnouncementController.php # 新增
│   │   ├── DashboardController.php    # 扩展 platform 仪表盘
│   │   └── ExportController.php       # 扩展 流水/提现导出
│   ├── app/model/ -> ../../common/model/  (symlink, or autoload)
│   ├── config/route.php               # 扩展新路由
│   ├── config/autoload.php            # 扩展 common namespace
│   └── install/
│       └── 2026_05_22_000003_platform_tables.sql  # 基础版12张表
│
└── apps/flutter/
    ├── admin/                          # PC管理后台（扩展现有）
    └── platform/                       # PC C端用户平台（新建）
```

**Responsibility boundaries:**
- `common/model/` — Eloquent Model，与数据表一一对应，定义 casts/relations
- `service/app/api/v1/controller/` — C端API，处理用户请求，调用Model
- `admin/app/admin/controller/` — 管理后台API，管理员操作
- `common/middleware/UserAuth.php` — C端JWT验证中间件，注入 userId

---

## Phase 1: 基础设施搭建

### Task 1: 创建 common/composer.json 并定义命名空间

**Files:**
- Create: `common/composer.json`

- [ ] **Step 1: Write common/composer.json**

```json
{
  "name": "erik/game-platform-common",
  "description": "全球游戏聚合平台共享层",
  "type": "library",
  "license": "MIT",
  "autoload": {
    "psr-4": {
      "common\\model\\": "model",
      "common\\middleware\\": "middleware"
    }
  },
  "require": {
    "php": ">=8.1"
  }
}
```

- [ ] **Step 2: Run composer validate in common/**

Run: `cd common && composer validate`
Expected: "valid" (may warn about no version, ignore)

- [ ] **Step 3: Commit**

```bash
git add common/composer.json
git commit -m "feat: add common/ shared layer with PSR-4 autoload"
```

---

### Task 2: 注册 common/ 到 admin 和创建 service/ autoload 配置

**Files:**
- Modify: `admin/composer.json`
- Modify: `admin/config/autoload.php`

- [ ] **Step 1: Add common path repository to admin/composer.json**

In `admin/composer.json`, add to the `"autoload"` → `"psr-4"` block:

The existing block is:
```json
"autoload": {
  "psr-4": {
    "": "./",
    "app\\": "./app",
    "App\\": "./app",
    "app\\View\\Components\\": "./app/view/components"
  }
}
```

Change to:
```json
"autoload": {
  "psr-4": {
    "": "./",
    "app\\": "./app",
    "App\\": "./app",
    "app\\View\\Components\\": "./app/view/components",
    "common\\model\\": "../common/model",
    "common\\middleware\\": "../common/middleware"
  }
}
```

- [ ] **Step 2: Run composer dump-autoload**

Run: `cd admin && composer dump-autoload`
Expected: success, no errors

- [ ] **Step 3: Add common autoload to admin/config/autoload.php (if needed)**

Read current file first. If it doesn't already load common, add the namespace mapping.

- [ ] **Step 4: Commit**

```bash
git add admin/composer.json admin/config/autoload.php
git commit -m "feat: register common/ namespace in admin autoload"
```

---

### Task 3: 搭建 service/ 目录结构和 composer.json

**Files:**
- Create: `service/composer.json`
- Create: `service/.env.example`
- Create: `service/start.php`
- Create: `service/windows.php`
- Create: `service/support/bootstrap.php`
- Create: `service/support/Request.php`
- Create: `service/support/Response.php`
- Create: `service/support/Setup.php`
- Create: `service/app/functions.php`

- [ ] **Step 1: Write service/composer.json**

```json
{
  "name": "erik/game-platform-service",
  "description": "全球游戏聚合平台 — C端业务端",
  "type": "project",
  "license": "MIT",
  "require": {
    "php": ">=8.1",
    "workerman/webman-framework": "^2.1",
    "monolog/monolog": "^2.0",
    "erikwang2013/snowflake-php": "^2.0",
    "erikwang2013/hashids": "^1.0",
    "erikwang2013/jwt-webman": "^2.0",
    "erikwang2013/encryption": "^1.0",
    "erikwang2013/encryptable": "^2.0",
    "erikwang2013/webman-scout": "^2.0",
    "erikwang2013/season": "^2.0",
    "erikwang2013/poster-php": "^1.0",
    "erikwang2013/security-php": "^1.0",
    "vlucas/phpdotenv": "^5.6"
  },
  "autoload": {
    "psr-4": {
      "": "./",
      "app\\": "./app",
      "common\\model\\": "../common/model",
      "common\\middleware\\": "../common/middleware"
    }
  },
  "config": {
    "allow-plugins": {
      "erikwang2013/encryptable": true,
      "erikwang2013/webman-scout": true,
      "erikwang2013/encryption": true
    }
  }
}
```

- [ ] **Step 2: Write service/start.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

require_once __DIR__ . '/../admin/vendor/autoload.php';

support\App::load();
support\bootstrap\Session::class;
support\bootstrap\LaravelDb::class;
app\common\SnowflakeService::init();
app\common\HashidsService::init();

// 加载 common 模型
require_once __DIR__ . '/../common/vendor/autoload.php';

Worker::runAll();
```

Wait — actually, rethink. service/ should have its OWN vendor directory for its own dependencies, but can share admin's vendor or install separately. The simplest approach: service/ uses composer install independently, and references common/ by the shared autoload config.

I should simplify: service/ does `composer install` independently. The common/ directory is referenced via the PSR-4 autoload mapping. Both admin and service point to `../common/model` and `../common/middleware`.

Actually, with webman v2, the simplest thing is:
- service/ has its own vendor/ (from composer install)
- Common models autoloaded via PSR-4 in composer.json: `"common\\model\\": "../common/model"`
- No symlink needed

Let me write this correctly.

```php
#!/usr/bin/env php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

ini_set('display_errors', 'on');
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

support\App::load();
support\bootstrap\Session::class;
support\bootstrap\LaravelDb::class;

Worker::runAll();
```

- [ ] **Step 2: Write service/start.php**

```php
#!/usr/bin/env php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

ini_set('display_errors', 'on');
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

support\App::load();
support\bootstrap\Session::class;
support\bootstrap\LaravelDb::class;

Worker::runAll();
```

- [ ] **Step 3: Write service/windows.php** (same content as admin/windows.php pattern)

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

ini_set('display_errors', 'on');
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

support\App::load();
support\bootstrap\Session::class;
support\bootstrap\LaravelDb::class;

Worker::runAll();
```

- [ ] **Step 4: Write service/.env.example**

```
APP_ENV=local
APP_DEBUG=true

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=game-platform
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=change-me-64-chars-random-string
JWT_TTL=7200

HASHIDS_SALT=change-me-to-random-salt
HASHIDS_ALPHABET=abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890
HASHIDS_MIN_LENGTH=16

SNOWFLAKE_DATACENTER_ID=1
SNOWFLAKE_WORKER_ID=1

ENCRYPTION_KEY=change-me-32-bytes-random-key
ENCRYPTABLE_KEY=change-me-to-random-aes-key

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

CORS_ORIGIN=*
```

- [ ] **Step 5: Write service/support/bootstrap.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
```

(Minimal — webman auto-detects support/bootstrap.php)

- [ ] **Step 6: Write service/app/functions.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * C端业务端公共函数
 */
```

- [ ] **Step 7: Copy support files from admin**

Run: `cp admin/support/Request.php service/support/Request.php`
Run: `cp admin/support/Response.php service/support/Response.php`
Run: `cp admin/support/Setup.php service/support/Setup.php`

- [ ] **Step 8: Install composer dependencies for service**

Run: `cd service && composer install`
Expected: success

- [ ] **Step 9: Commit**

```bash
git add service/
git commit -m "feat: scaffold service/ C端 business app with webman v2"
```

---

### Task 4: 创建 service/ 配置文件

**Files:**
- Create: `service/config/app.php`
- Create: `service/config/autoload.php`
- Create: `service/config/bootstrap.php`
- Create: `service/config/container.php`
- Create: `service/config/database.php`
- Create: `service/config/dependence.php`
- Create: `service/config/exception.php`
- Create: `service/config/hashids.php`
- Create: `service/config/jwt.php`
- Create: `service/config/log.php`
- Create: `service/config/middleware.php`
- Create: `service/config/process.php`
- Create: `service/config/route.php`
- Create: `service/config/server.php`
- Create: `service/config/session.php`
- Create: `service/config/snowflake.php`
- Create: `service/config/static.php`
- Create: `service/config/translation.php`
- Create: `service/config/view.php`
- Create: `service/config/plugin/erikwang2013/hashids/app.php`
- Create: `service/config/plugin/erikwang2013/jwt/jwt.php`
- Create: `service/config/plugin/erikwang2013/jwt/app.php`

- [ ] **Step 1: Copy base configs from admin and customize**

Copy all config files from `admin/config/` to `service/config/`, adjusting values:

Key differences in service/config/:

`database.php` — same DB, same connection
`route.php` — different routes (C端 /api/*)
`middleware.php` — different middleware chain
`server.php` — different port (8788 vs 8787)
`snowflake.php` — different datacenter_id/worker_id (datacenter_id=1, worker_id=2)

- [ ] **Step 2: Write service/config/middleware.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * C端全局中间件配置
 *
 * 执行顺序: Cors → SecurityFilter → RateLimit → {路由组中间件} → Controller
 */

return [
    app\middleware\Cors::class,
    app\middleware\SecurityFilter::class,
    app\middleware\RateLimit::class,
];
```

Note: service needs its own copies of Cors, SecurityFilter, RateLimit middleware OR reference admin's. For now, make copies in service/app/middleware/.

- [ ] **Step 3: Write service/config/route.php** (skeleton)

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Webman\Route;
use support\Request;

/**
 * C端 API 路由配置
 *
 * API 版本策略:
 * - 版本号通过请求头 API-Version 携带（如 "v1"）
 * - 缺失时默认使用 v1
 * - 由 ApiVersion 中间件校验
 */

function v(string $controller, string $action): \Closure
{
    return function (Request $request) use ($controller, $action) {
        $version = $request->apiVersion ?? 'v1';
        $class = "\\app\\api\\{$version}\\controller\\{$controller}";
        return (new $class)->{$action}($request);
    };
}

Route::get('/health', function () {
    return json(['code' => 0, 'message' => 'ok']);
});

// 公开接口（无需认证）
Route::group('/api', function () {
    // Routes will be added in subsequent tasks
})->middleware([
    app\middleware\ApiVersion::class,
]);

Route::disableDefaultRoute();
```

- [ ] **Step 4: Copy plugin configs**

Run: `cp -r admin/config/plugin service/config/plugin`

- [ ] **Step 5: Commit**

```bash
git add service/config/
git commit -m "feat: add service/ config files with C端 middleware chain"
```

---

### Task 5: 复制共享中间件到 service/ 并创建 UserAuth

**Files:**
- Create: `service/app/middleware/Cors.php`
- Create: `service/app/middleware/SecurityFilter.php`
- Create: `service/app/middleware/RateLimit.php`
- Create: `service/app/middleware/ApiVersion.php`
- Create: `common/middleware/UserAuth.php`

- [ ] **Step 1: Copy middleware files from admin**

Run: `cp admin/app/middleware/Cors.php service/app/middleware/Cors.php`
Run: `cp admin/app/middleware/SecurityFilter.php service/app/middleware/SecurityFilter.php`
Run: `cp admin/app/middleware/RateLimit.php service/app/middleware/RateLimit.php`
Run: `cp admin/app/middleware/ApiVersion.php service/app/middleware/ApiVersion.php`

- [ ] **Step 2: Update namespace in copied middleware**

Change `namespace app\middleware;` to `namespace app\middleware;` (same, since service uses same namespace).

Actually, verify the namespace — since service/ autoloads `"app\\": "./app"`, the namespace stays `app\middleware`.

- [ ] **Step 3: Create common/middleware/UserAuth.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\middleware;

use common\model\User;
use support\Request;
use Webman\MiddlewareInterface;
use Webman\Http\Response;

/**
 * C端用户JWT认证中间件
 *
 * 从 Authorization Bearer Token 中解析用户ID，
 * 校验Token有效性，注入 $request->userId
 */
class UserAuth implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return json(['code' => 401, 'message' => '未登录', 'data' => []]);
        }

        try {
            $payload = jwt()->verify($token);
        } catch (\Throwable $e) {
            return json(['code' => 401, 'message' => 'Token已过期或无效', 'data' => []]);
        }

        $user = User::find($payload->sub);
        if (!$user || $user->status !== 1) {
            return json(['code' => 401, 'message' => '用户不存在或已禁用', 'data' => []]);
        }

        $request->userId = (int) $payload->sub;

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add service/app/middleware/ common/middleware/
git commit -m "feat: add middleware for service/ and shared UserAuth"
```

---

## Phase 2: 数据库迁移

### Task 6: 创建基础版 12 张表的 SQL 迁移

**Files:**
- Create: `install/install.sql`

- [ ] **Step 1: Write complete migration SQL**

```sql
-- ============================================================
-- Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
-- 迁移: 游戏聚合平台基础版核心数据表
-- 版本: 基础版 (MVP)
-- 注意: 主键 id 使用 BIGINT 非自增，由 snowflake-php 在应用层生成
-- ============================================================

-- ============================================================
-- 1. C端用户表
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_user` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `password` VARCHAR(255) NOT NULL COMMENT '密码（bcrypt哈希）',
    `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '昵称',
    `avatar` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像URL',
    `email` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '邮箱（加密存储）',
    `phone` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '手机号（加密存储）',
    `country` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '国家代码，ISO 3166-1 alpha-2',
    `language` VARCHAR(10) NOT NULL DEFAULT 'en-US' COMMENT '语言偏好',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用 1=启用',
    `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '最后登录IP',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除标记',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    KEY `idx_status` (`status`),
    KEY `idx_country` (`country`),
    KEY `idx_deleted_at` (`deleted_at`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='C端用户表';

-- ============================================================
-- 2. 平台币钱包表（含乐观锁）
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_user_wallet` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `balance` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '可用余额（平台币）',
    `frozen_balance` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '冻结余额（提现中）',
    `total_earned` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '累计收入（平台币）',
    `total_spent` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '累计支出（平台币）',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '乐观锁版本号',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    KEY `idx_balance` (`balance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台币钱包表';

-- ============================================================
-- 3. 游戏币钱包表
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_user_game_wallet` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `game_id` BIGINT UNSIGNED NOT NULL COMMENT '游戏ID',
    `currency_id` BIGINT UNSIGNED NOT NULL COMMENT '币种ID',
    `balance` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '游戏币余额',
    `frozen_balance` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '冻结游戏币余额',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_game_currency` (`user_id`, `game_id`, `currency_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏币钱包表';

-- ============================================================
-- 4. 游戏表
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_game` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(100) NOT NULL COMMENT '游戏名称',
    `slug` VARCHAR(50) NOT NULL COMMENT '游戏标识',
    `type` VARCHAR(20) NOT NULL DEFAULT 'third_party' COMMENT '游戏类型: self=自研 third_party=第三方',
    `description` TEXT COMMENT '游戏简介',
    `cover_image` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '封面图',
    `api_endpoint` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '第三方游戏API地址',
    `api_key` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '第三方API密钥（加密存储）',
    `api_secret` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '第三方API密钥（加密存储）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=下架 1=上架',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序值，越小越靠前',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_status` (`status`),
    KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏表';

-- ============================================================
-- 5. 游戏币种表
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_game_currency` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `game_id` BIGINT UNSIGNED NOT NULL COMMENT '游戏ID',
    `name` VARCHAR(50) NOT NULL COMMENT '币种名称',
    `symbol` VARCHAR(20) NOT NULL COMMENT '币种符号',
    `exchange_rate` DECIMAL(18,8) NOT NULL DEFAULT 1.00000000 COMMENT '兑换率（1平台币 = X游戏币）',
    `spread_pct` DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 0.00 COMMENT '平台抽成百分比',
    `min_exchange` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 0.0000 COMMENT '最小兑换数量（平台币）',
    `max_exchange` DECIMAL(18,4) UNSIGNED NOT NULL DEFAULT 999999.9999 COMMENT '最大兑换数量（平台币）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_game_id` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏币种表';

-- ============================================================
-- 6. 充值订单表
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_deposit_order` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_no` VARCHAR(32) NOT NULL COMMENT '订单号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `amount` DECIMAL(18,4) NOT NULL COMMENT '充值金额（法币）',
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD' COMMENT '法币币种（USD/CNY/EUR等）',
    `platform_amount` DECIMAL(18,4) NOT NULL COMMENT '到账平台币数量',
    `payment_method_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '支付方式ID',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '状态: pending=待支付 paid=已支付 confirmed=已确认 cancelled=已取消',
    `transaction_id` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '第三方支付交易ID',
    `paid_at` DATETIME DEFAULT NULL COMMENT '支付时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='充值订单表';

-- ============================================================
-- 7. 提现订单表
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_withdraw_order` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `order_no` VARCHAR(32) NOT NULL COMMENT '订单号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `platform_amount` DECIMAL(18,4) NOT NULL COMMENT '提现平台币数量',
    `fiat_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '到账法币金额',
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD' COMMENT '提现法币币种',
    `method` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '提现方式: paypal/bank/crypto',
    `account_info` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '收款账户信息（加密存储）',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '状态: pending=待审核 approved=已通过 rejected=已拒绝 completed=已完成',
    `reviewer_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '审核人ID（关联game_admin_user）',
    `review_note` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '审核附注',
    `reviewed_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_reviewer_id` (`reviewer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='提现订单表';

-- ============================================================
-- 8. 兑换记录表
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_exchange_record` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `game_id` BIGINT UNSIGNED NOT NULL COMMENT '游戏ID',
    `currency_id` BIGINT UNSIGNED NOT NULL COMMENT '币种ID',
    `direction` VARCHAR(4) NOT NULL COMMENT '方向: in=平台币→游戏币 out=游戏币→平台币',
    `platform_amount` DECIMAL(18,4) NOT NULL COMMENT '平台币数量',
    `game_amount` DECIMAL(18,4) NOT NULL COMMENT '游戏币数量',
    `rate` DECIMAL(18,8) NOT NULL COMMENT '成交汇率',
    `spread_fee` DECIMAL(18,4) NOT NULL DEFAULT 0.0000 COMMENT '平台手续费（平台币）',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '兑换时间',
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_game_id` (`game_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='兑换记录表';

-- ============================================================
-- 9. 平台流水表
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_transaction` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `type` VARCHAR(20) NOT NULL COMMENT '流水类型: deposit=充值 withdraw=提现 exchange_in=兑换买入 exchange_out=兑换卖出 game_earn=游戏赚取 game_spend=游戏花费',
    `amount` DECIMAL(18,4) NOT NULL COMMENT '变动金额（正=收入，负=支出）',
    `balance_after` DECIMAL(18,4) NOT NULL COMMENT '变动后余额',
    `ref_type` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '关联单据类型',
    `ref_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '关联单据ID',
    `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '流水时间',
    PRIMARY KEY (`id`),
    KEY `idx_user_id_type` (`user_id`, `type`),
    KEY `idx_ref` (`ref_type`, `ref_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台流水表';

-- ============================================================
-- 10. 支付方式表
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_payment_method` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `name` VARCHAR(50) NOT NULL COMMENT '支付方式名称',
    `type` VARCHAR(20) NOT NULL COMMENT '类型: fiat=法币 crypto=加密货币',
    `provider` VARCHAR(50) NOT NULL COMMENT '提供商: stripe/paypal/alipay/wechat',
    `config` TEXT COMMENT '支付配置（加密JSON）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=禁用 1=启用',
    `sort` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付方式表';

-- ============================================================
-- 11. 公告表
-- ============================================================
CREATE TABLE IF NOT EXISTS `game_announcement` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `title` VARCHAR(255) NOT NULL COMMENT '标题',
    `content` TEXT NOT NULL COMMENT '内容',
    `type` VARCHAR(20) NOT NULL DEFAULT 'system' COMMENT '公告类型: system=系统公告 game=游戏公告 payment=支付公告',
    `target_lang` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '目标语言（空=全语言）',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '状态: 0=草稿 1=已发布',
    `start_at` DATETIME DEFAULT NULL COMMENT '开始展示时间',
    `end_at` DATETIME DEFAULT NULL COMMENT '结束展示时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_status_dates` (`status`, `start_at`, `end_at`),
    KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公告表';

-- ============================================================
-- 12. 平台配置表（扩展现有 game_system_config 能力）
-- ============================================================
CREATE TABLE IF NOT EXISTS `game-platform_config` (
    `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID，由snowflake生成',
    `group` VARCHAR(50) NOT NULL DEFAULT 'default' COMMENT '配置分组: withdraw/payment/game/system',
    `key` VARCHAR(100) NOT NULL COMMENT '配置键名',
    `value` TEXT COMMENT '配置值',
    `type` VARCHAR(20) NOT NULL DEFAULT 'string' COMMENT '值类型: string|int|bool|json|decimal',
    `description` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '配置项说明',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_group_key` (`group`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台配置表';

-- ============================================================
-- 插入默认配置
-- ============================================================
INSERT INTO `game-platform_config` (`id`, `group`, `key`, `value`, `type`, `description`) VALUES
(20000000000000001, 'withdraw', 'global_switch', '1', 'bool', '全局提现开关: 1=允许提现 0=禁止提现'),
(20000000000000002, 'withdraw', 'auto_approve_threshold', '100.0000', 'decimal', '自动审核阈值（平台币），低于此金额自动通过'),
(20000000000000003, 'withdraw', 'daily_limit', '10000.0000', 'decimal', '每人每日提现上限（平台币）'),
(20000000000000004, 'withdraw', 'min_amount', '1.0000', 'decimal', '单笔最低提现金额（平台币）'),
(20000000000000005, 'payment', 'default_exchange_rate', '1.00000000', 'decimal', '默认平台币兑USD汇率'),
(20000000000000006, 'system', 'site_name', 'Global Game Platform', 'string', '平台名称');
```

- [ ] **Step 2: Run migration against dev database**

```bash
mysql -h 127.0.0.1 -u root game-platform < install/install.sql
```

Expected: no errors, all 12 tables created.

- [ ] **Step 3: Verify tables exist**

```bash
mysql -h 127.0.0.1 -u root game-platform -e "SHOW TABLES LIKE 'game_%';"
```

Expected: list includes all 12 new tables + existing admin tables.

- [ ] **Step 4: Commit**

```bash
git add install/install.sql
git commit -m "feat: add 12 platform tables migration for MVP"
```

---

## Phase 3: 共享层 — Models

### Task 7: Create common/model/User.php

**Files:**
- Create: `common/model/User.php`

- [ ] **Step 1: Ensure common/ vendor is set up**

Run: `cd common && composer install 2>/dev/null; echo "OK"`

- [ ] **Step 2: Write common/model/User.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use Erikwang2013\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class User extends Model
{
    use SoftDeletes;

    protected $table = 'game_user';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'username', 'password', 'nickname', 'avatar',
        'email', 'phone', 'country', 'language', 'status',
        'last_login_at', 'last_login_ip',
    ];

    protected $hidden = ['password'];
    protected $casts = [
        'status' => 'integer',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'email' => Encryptable::class,
        'phone' => Encryptable::class,
    ];

    public function wallet()
    {
        return $this->hasOne(UserWallet::class, 'user_id');
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add common/model/User.php
git commit -m "feat: add User model for C端 users"
```

---

### Task 8: Create common/model/UserWallet.php (with optimistic lock)

**Files:**
- Create: `common/model/UserWallet.php`

- [ ] **Step 1: Write UserWallet.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use PDOException;
use support\Model;

class UserWallet extends Model
{
    protected $table = 'game_user_wallet';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id', 'balance', 'frozen_balance',
        'total_earned', 'total_spent', 'version',
    ];

    protected $casts = [
        'balance' => 'string',
        'frozen_balance' => 'string',
        'total_earned' => 'string',
        'total_spent' => 'string',
        'version' => 'integer',
    ];

    /**
     * 增加余额（乐观锁版本回退）
     * 失败重试最多5次
     */
    public static function addBalance(int $userId, string $amount): bool
    {
        $maxRetries = 5;
        for ($i = 0; $i < $maxRetries; $i++) {
            $wallet = static::where('user_id', $userId)->lockForUpdate()->first();
            if (!$wallet) {
                return false;
            }

            $updated = static::where('id', $wallet->id)
                ->where('version', $wallet->version)
                ->update([
                    'balance' => bcadd($wallet->balance, $amount, 4),
                    'total_earned' => $amount > 0 ? bcadd($wallet->total_earned, $amount, 4) : $wallet->total_earned,
                    'total_spent' => $amount < 0 ? bcadd($wallet->total_spent, ltrim($amount, '-'), 4) : $wallet->total_spent,
                    'version' => $wallet->version + 1,
                ]);

            if ($updated > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 扣减余额
     */
    public static function deductBalance(int $userId, string $amount): bool
    {
        return static::addBalance($userId, bcmul($amount, '-1', 4));
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add common/model/UserWallet.php
git commit -m "feat: add UserWallet model with optimistic locking"
```

---

### Task 9: Create remaining 10 models

**Files:**
- Create: `common/model/UserGameWallet.php`
- Create: `common/model/Game.php`
- Create: `common/model/GameCurrency.php`
- Create: `common/model/DepositOrder.php`
- Create: `common/model/WithdrawOrder.php`
- Create: `common/model/ExchangeRecord.php`
- Create: `common/model/Transaction.php`
- Create: `common/model/PaymentMethod.php`
- Create: `common/model/Announcement.php`
- Create: `common/model/PlatformConfig.php`

- [ ] **Step 1: Write UserGameWallet.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class UserGameWallet extends Model
{
    protected $table = 'game_user_game_wallet';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id', 'game_id', 'currency_id', 'balance', 'frozen_balance',
    ];

    protected $casts = [
        'balance' => 'string',
        'frozen_balance' => 'string',
    ];
}
```

- [ ] **Step 2: Write Game.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use Erikwang2013\Encryptable\Encryptable;
use support\Model;

class Game extends Model
{
    protected $table = 'game_game';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'name', 'slug', 'type', 'description', 'cover_image',
        'api_endpoint', 'api_key', 'api_secret', 'status', 'sort',
    ];

    protected $hidden = ['api_key', 'api_secret'];
    protected $casts = [
        'status' => 'integer',
        'sort' => 'integer',
        'api_key' => Encryptable::class,
        'api_secret' => Encryptable::class,
    ];

    public function currencies()
    {
        return $this->hasMany(GameCurrency::class, 'game_id');
    }
}
```

- [ ] **Step 3: Write GameCurrency.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class GameCurrency extends Model
{
    protected $table = 'game_game_currency';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'game_id', 'name', 'symbol', 'exchange_rate',
        'spread_pct', 'min_exchange', 'max_exchange',
    ];

    protected $casts = [
        'exchange_rate' => 'string',
        'spread_pct' => 'string',
        'min_exchange' => 'string',
        'max_exchange' => 'string',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class, 'game_id');
    }
}
```

- [ ] **Step 4: Write DepositOrder.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class DepositOrder extends Model
{
    protected $table = 'game_deposit_order';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'order_no', 'user_id', 'amount', 'currency', 'platform_amount',
        'payment_method_id', 'status', 'transaction_id', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'string',
        'platform_amount' => 'string',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

- [ ] **Step 5: Write WithdrawOrder.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use Erikwang2013\Encryptable\Encryptable;
use support\Model;

class WithdrawOrder extends Model
{
    protected $table = 'game_withdraw_order';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'order_no', 'user_id', 'platform_amount', 'fiat_amount',
        'currency', 'method', 'account_info', 'status',
        'reviewer_id', 'review_note', 'reviewed_at',
    ];

    protected $casts = [
        'platform_amount' => 'string',
        'fiat_amount' => 'string',
        'account_info' => Encryptable::class,
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

- [ ] **Step 6: Write ExchangeRecord.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class ExchangeRecord extends Model
{
    protected $table = 'game_exchange_record';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'game_id', 'currency_id', 'direction',
        'platform_amount', 'game_amount', 'rate', 'spread_fee',
    ];

    protected $casts = [
        'platform_amount' => 'string',
        'game_amount' => 'string',
        'rate' => 'string',
        'spread_fee' => 'string',
    ];
}
```

- [ ] **Step 7: Write Transaction.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class Transaction extends Model
{
    protected $table = 'game_transaction';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'type', 'amount', 'balance_after',
        'ref_type', 'ref_id', 'remark',
    ];

    protected $casts = [
        'amount' => 'string',
        'balance_after' => 'string',
    ];
}
```

- [ ] **Step 8: Write PaymentMethod.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use Erikwang2013\Encryptable\Encryptable;
use support\Model;

class PaymentMethod extends Model
{
    protected $table = 'game_payment_method';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'name', 'type', 'provider', 'config', 'status', 'sort',
    ];

    protected $casts = [
        'status' => 'integer',
        'sort' => 'integer',
        'config' => Encryptable::class,
    ];
}
```

- [ ] **Step 9: Write Announcement.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class Announcement extends Model
{
    protected $table = 'game_announcement';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'title', 'content', 'type', 'target_lang',
        'status', 'start_at', 'end_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];
}
```

- [ ] **Step 10: Write PlatformConfig.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\model;

use support\Model;

class PlatformConfig extends Model
{
    protected $table = 'game-platform_config';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'group', 'key', 'value', 'type', 'description',
    ];

    /**
     * 获取配置值（自动类型转换）
     */
    public static function get(string $group, string $key, $default = null)
    {
        $config = static::where('group', $group)->where('key', $key)->first();
        if (!$config) {
            return $default;
        }

        return match ($config->type) {
            'bool' => (bool) $config->value,
            'int' => (int) $config->value,
            'json' => json_decode($config->value, true),
            'decimal' => $config->value,
            default => $config->value,
        };
    }

    /**
     * 设置配置值
     */
    public static function set(string $group, string $key, $value, string $type = 'string'): void
    {
        static::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => (string) $value, 'type' => $type]
        );
    }
}
```

- [ ] **Step 11: Commit**

```bash
git add common/model/
git commit -m "feat: add all platform models (Game, Wallet, Orders, Transaction, etc.)"
```

---

## Phase 4: C端 API 控制器

### Task 10: Create service BaseController and copy shared services

**Files:**
- Create: `service/app/api/v1/controller/BaseController.php`
- Create: `service/app/common/HashidsService.php`
- Create: `service/app/common/SnowflakeService.php`
- Create: `service/app/common/EncryptionService.php`
- Modify: `service/config/route.php`

- [ ] **Step 1: Copy service classes and adapt namespace**

Run: `cp admin/app/common/HashidsService.php service/app/common/HashidsService.php`
Run: `cp admin/app/common/SnowflakeService.php service/app/common/SnowflakeService.php`
Run: `cp admin/app/common/EncryptionService.php service/app/common/EncryptionService.php`

Update namespace: change `app\common\HashidsService` → already `app\common`, no change needed since service/ autoloads `app\\` → `./app`.

- [ ] **Step 2: Write BaseController.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\common\HashidsService;
use app\common\SnowflakeService;
use support\Request;
use support\Response;

/**
 * C端基础控制器
 */
class BaseController
{
    protected function success($data = [], string $message = 'success', int $code = 0): Response
    {
        return json(['code' => $code, 'message' => $message, 'data' => $data]);
    }

    protected function fail(string $message = 'fail', int $code = 500, $data = []): Response
    {
        return json(['code' => $code, 'message' => $message, 'data' => $data]);
    }

    protected function encodeId(int $id): string
    {
        return HashidsService::encode($id);
    }

    protected function decodeId(string $hashid): int
    {
        return HashidsService::decode($hashid);
    }

    protected function generateId(): int
    {
        return SnowflakeService::generate();
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add service/app/
git commit -m "feat: add C端 BaseController and shared services"
```

---

### Task 11: Create AuthController (register/login/refresh)

**Files:**
- Create: `service/app/api/v1/controller/AuthController.php`
- Modify: `service/config/route.php`

- [ ] **Step 1: Write AuthController.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\User;
use common\model\UserWallet;
use support\Request;

class AuthController extends BaseController
{
    /**
     * 用户注册
     * POST /api/auth/register
     */
    public function register(Request $request)
    {
        $validator = validator($request->all(), [
            'username' => 'required|string|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'password' => 'required|string|min:6|max:32',
            'email' => 'nullable|email',
        ], [
            'username.regex' => '用户名只能包含字母、数字和下划线',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $exists = User::where('username', $request->input('username'))->exists();
        if ($exists) {
            return $this->fail('用户名已被注册', 422);
        }

        $id = $this->generateId();
        $user = User::create([
            'id' => $id,
            'username' => $request->input('username'),
            'password' => password_hash($request->input('password'), PASSWORD_BCRYPT),
            'email' => $request->input('email', ''),
            'country' => $request->input('country', ''),
            'language' => $request->input('language', 'en-US'),
            'last_login_ip' => $request->getRealIp(),
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        // 创建钱包
        UserWallet::create([
            'id' => $this->generateId(),
            'user_id' => $id,
            'balance' => '0.0000',
        ]);

        $token = jwt()->create(['sub' => $id, 'username' => $user->username]);
        $refreshToken = jwt()->refresh();

        return $this->success([
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'user' => [
                'id' => $this->encodeId($id),
                'username' => $user->username,
                'nickname' => $user->nickname,
                'avatar' => $user->avatar,
            ],
        ], '注册成功');
    }

    /**
     * 用户登录
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        $validator = validator($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $user = User::where('username', $request->input('username'))->first();
        if (!$user || !password_verify($request->input('password'), $user->password)) {
            return $this->fail('用户名或密码错误', 401);
        }

        if ($user->status !== 1) {
            return $this->fail('账号已被禁用', 401);
        }

        $user->update([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $request->getRealIp(),
        ]);

        $token = jwt()->create(['sub' => $user->id, 'username' => $user->username]);
        $refreshToken = jwt()->refresh();

        return $this->success([
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'user' => [
                'id' => $this->encodeId($user->id),
                'username' => $user->username,
                'nickname' => $user->nickname,
                'avatar' => $user->avatar,
            ],
        ], '登录成功');
    }

    /**
     * 刷新Token
     * POST /api/auth/refresh
     */
    public function refresh(Request $request)
    {
        try {
            $token = jwt()->refresh();
            $accessToken = jwt()->create([
                'sub' => $request->userId,
            ]);
            return $this->success([
                'access_token' => $accessToken,
                'refresh_token' => $token,
            ]);
        } catch (\Throwable $e) {
            return $this->fail('Token刷新失败', 401);
        }
    }
}
```

- [ ] **Step 2: Update service/config/route.php** — add auth routes:

```php
Route::group('/api', function () {
    Route::post('/auth/register', v('AuthController', 'register'));
    Route::post('/auth/login', v('AuthController', 'login'));
    Route::post('/auth/refresh', v('AuthController', 'refresh'));
    Route::post('/captcha/generate', v('CaptchaController', 'generate'));
})->middleware([
    app\middleware\ApiVersion::class,
]);
```

- [ ] **Step 3: Commit**

```bash
git add service/app/api/v1/controller/AuthController.php service/config/route.php
git commit -m "feat: add C端 AuthController with register/login/refresh"
```

---

### Task 12: Create WalletController

**Files:**
- Create: `service/app/api/v1/controller/WalletController.php`
- Modify: `service/config/route.php`

- [ ] **Step 1: Write WalletController.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\UserWallet;
use common\model\Transaction;
use support\Request;

class WalletController extends BaseController
{
    /**
     * 钱包信息
     * GET /api/wallet/info
     */
    public function info(Request $request)
    {
        $wallet = UserWallet::where('user_id', $request->userId)->first();
        if (!$wallet) {
            return $this->fail('钱包不存在', 404);
        }

        return $this->success([
            'balance' => $wallet->balance,
            'frozen_balance' => $wallet->frozen_balance,
            'total_earned' => $wallet->total_earned,
            'total_spent' => $wallet->total_spent,
        ]);
    }

    /**
     * 流水记录
     * GET /api/wallet/transactions
     * Query: page, per_page, type(可选筛选)
     */
    public function transactions(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $query = Transaction::where('user_id', $request->userId)
            ->orderBy('created_at', 'desc');

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $total = $query->count();
        $list = $query->forPage($page, $perPage)->get()->map(function ($t) {
            return [
                'id' => $this->encodeId($t->id),
                'type' => $t->type,
                'amount' => $t->amount,
                'balance_after' => $t->balance_after,
                'remark' => $t->remark,
                'created_at' => $t->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }
}
```

- [ ] **Step 2: Update service/config/route.php** — add under authenticated group:

```php
Route::group('/api', function () {
    // 钱包
    Route::get('/wallet/info', v('WalletController', 'info'));
    Route::get('/wallet/transactions', v('WalletController', 'transactions'));
})->middleware([
    app\middleware\ApiVersion::class,
    common\middleware\UserAuth::class,
]);
```

- [ ] **Step 3: Commit**

```bash
git add service/app/api/v1/controller/WalletController.php service/config/route.php
git commit -m "feat: add WalletController with info and transactions"
```

---

### Task 13: Create ExchangeController (platform currency ⇄ game currency)

**Files:**
- Create: `service/app/api/v1/controller/ExchangeController.php`
- Modify: `service/config/route.php`

- [ ] **Step 1: Write ExchangeController.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\ExchangeRecord;
use common\model\Game;
use common\model\GameCurrency;
use common\model\Transaction;
use common\model\UserGameWallet;
use common\model\UserWallet;
use support\Request;

class ExchangeController extends BaseController
{
    /**
     * 询价
     * POST /api/exchange/quote
     * Body: { game_id, currency_id, direction: "in"|"out", platform_amount }
     */
    public function quote(Request $request)
    {
        $validator = validator($request->all(), [
            'game_id' => 'required|string',
            'currency_id' => 'required|string',
            'direction' => 'required|in:in,out',
            'platform_amount' => 'required|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $gameId = $this->decodeId($request->input('game_id'));
        $currencyId = $this->decodeId($request->input('currency_id'));
        $currency = GameCurrency::where('id', $currencyId)
            ->where('game_id', $gameId)
            ->first();

        if (!$currency) {
            return $this->fail('游戏币种不存在', 404);
        }

        $platformAmount = $request->input('platform_amount');
        $rate = $currency->exchange_rate;
        $spreadPct = $currency->spread_pct;

        if ($request->input('direction') === 'in') {
            // 平台币 → 游戏币
            $gameAmount = bcmul($platformAmount, $rate, 4);
            $spreadFee = bcmul($gameAmount, bcdiv($spreadPct, '100', 8), 4);
            $actualGameAmount = bcsub($gameAmount, $spreadFee, 4);
        } else {
            // 游戏币 → 平台币
            $platformAmount = bcdiv($platformAmount, $rate, 4);
            $spreadFee = bcmul($platformAmount, bcdiv($spreadPct, '100', 8), 4);
            $actualPlatformAmount = bcsub($platformAmount, $spreadFee, 4);
        }

        return $this->success([
            'platform_amount' => $platformAmount,
            'game_amount' => $actualGameAmount ?? $gameAmount,
            'spread_fee' => $spreadFee,
            'rate' => $rate,
            'spread_pct' => $spreadPct . '%',
        ]);
    }

    /**
     * 买入游戏币（平台币 → 游戏币）
     * POST /api/exchange/buy
     */
    public function buy(Request $request)
    {
        return $this->doExchange($request, 'in');
    }

    /**
     * 卖出游戏币（游戏币 → 平台币）
     * POST /api/exchange/sell
     */
    public function sell(Request $request)
    {
        return $this->doExchange($request, 'out');
    }

    private function doExchange(Request $request, string $direction)
    {
        $validator = validator($request->all(), [
            'game_id' => 'required|string',
            'currency_id' => 'required|string',
            'platform_amount' => 'required|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $gameId = $this->decodeId($request->input('game_id'));
        $currencyId = $this->decodeId($request->input('currency_id'));
        $platformAmount = $request->input('platform_amount');

        $currency = GameCurrency::where('id', $currencyId)
            ->where('game_id', $gameId)
            ->first();

        if (!$currency || Game::where('id', $gameId)->where('status', 1)->doesntExist()) {
            return $this->fail('游戏不可用', 404);
        }

        $rate = $currency->exchange_rate;
        $spreadPct = $currency->spread_pct;

        // 计算兑换金额
        if ($direction === 'in') {
            $gameAmount = bcmul($platformAmount, $rate, 4);
            $spreadFee = bcmul($gameAmount, bcdiv($spreadPct, '100', 8), 4);
            $actualGameAmount = bcsub($gameAmount, $spreadFee, 4);
        } else {
            $platformRaw = bcdiv($platformAmount, $rate, 4);
            $spreadFee = bcmul($platformRaw, bcdiv($spreadPct, '100', 8), 4);
            $actualPlatformAmount = bcsub($platformRaw, $spreadFee, 4);
        }

        // 到账金额
        $addAmount = $direction === 'in' ? $actualGameAmount : $actualPlatformAmount;
        if (bccomp($addAmount, '0', 4) <= 0) {
            return $this->fail('兑换金额不足以支付手续费', 422);
        }

        // 执行转账
        if ($direction === 'in') {
            // 扣平台币
            $ok = UserWallet::deductBalance($request->userId, $platformAmount);
            if (!$ok) {
                return $this->fail('平台币余额不足', 422);
            }
            // 加游戏币
            $this->addGameBalance($request->userId, $gameId, $currencyId, $addAmount);
        } else {
            // 扣游戏币
            $ok = $this->deductGameBalance($request->userId, $gameId, $currencyId, $platformAmount);
            if (!$ok) {
                return $this->fail('游戏币余额不足', 422);
            }
            // 加平台币
            UserWallet::addBalance($request->userId, $addAmount);
        }

        // 记录兑换
        $exchangeId = $this->generateId();
        ExchangeRecord::create([
            'id' => $exchangeId,
            'user_id' => $request->userId,
            'game_id' => $gameId,
            'currency_id' => $currencyId,
            'direction' => $direction,
            'platform_amount' => $direction === 'in' ? $platformAmount : $actualPlatformAmount,
            'game_amount' => $direction === 'in' ? $addAmount : $platformAmount,
            'rate' => $rate,
            'spread_fee' => $spreadFee,
        ]);

        // 记录流水
        $wallet = UserWallet::where('user_id', $request->userId)->first();
        $txType = $direction === 'in' ? 'exchange_in' : 'exchange_out';
        $txAmount = $direction === 'in' ? bcmul($platformAmount, '-1', 4) : $addAmount;
        Transaction::create([
            'id' => $this->generateId(),
            'user_id' => $request->userId,
            'type' => $txType,
            'amount' => $txAmount,
            'balance_after' => $wallet->balance,
            'ref_type' => 'exchange',
            'ref_id' => $exchangeId,
            'remark' => ($direction === 'in' ? '兑换买入' : '兑换卖出') . '游戏币',
        ]);

        return $this->success([
            'exchange_id' => $this->encodeId($exchangeId),
            'platform_amount' => $direction === 'in' ? $platformAmount : $actualPlatformAmount,
            'game_amount' => $direction === 'in' ? $addAmount : $platformAmount,
            'spread_fee' => $spreadFee,
            'rate' => $rate,
        ], '兑换成功');
    }

    /**
     * 兑换记录
     * GET /api/exchange/records
     */
    public function records(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $query = ExchangeRecord::where('user_id', $request->userId)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $list = $query->forPage($page, $perPage)->get()->map(function ($r) {
            return [
                'id' => $this->encodeId($r->id),
                'game_id' => $this->encodeId($r->game_id),
                'direction' => $r->direction,
                'platform_amount' => $r->platform_amount,
                'game_amount' => $r->game_amount,
                'rate' => $r->rate,
                'spread_fee' => $r->spread_fee,
                'created_at' => $r->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    private function addGameBalance(int $userId, int $gameId, int $currencyId, string $amount): void
    {
        $wallet = UserGameWallet::firstOrNew([
            'user_id' => $userId,
            'game_id' => $gameId,
            'currency_id' => $currencyId,
        ]);

        if (!$wallet->exists) {
            $wallet->id = $this->generateId();
            $wallet->balance = '0.0000';
            $wallet->frozen_balance = '0.0000';
        }

        $wallet->balance = bcadd($wallet->balance, $amount, 4);
        $wallet->save();
    }

    private function deductGameBalance(int $userId, int $gameId, int $currencyId, string $amount): bool
    {
        $wallet = UserGameWallet::where('user_id', $userId)
            ->where('game_id', $gameId)
            ->where('currency_id', $currencyId)
            ->first();

        if (!$wallet || bccomp($wallet->balance, $amount, 4) < 0) {
            return false;
        }

        $wallet->balance = bcsub($wallet->balance, $amount, 4);
        $wallet->save();
        return true;
    }
}
```

- [ ] **Step 2: Update route.php — add exchange routes under authenticated group**

- [ ] **Step 3: Commit**

```bash
git add service/app/api/v1/controller/ExchangeController.php service/config/route.php
git commit -m "feat: add ExchangeController with quote/buy/sell/records"
```

---

### Task 14: Create DepositController and WithdrawController

**Files:**
- Create: `service/app/api/v1/controller/DepositController.php`
- Create: `service/app/api/v1/controller/WithdrawController.php`
- Modify: `service/config/route.php`

- [ ] **Step 1: Write DepositController.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\DepositOrder;
use common\model\PlatformConfig;
use common\model\Transaction;
use common\model\UserWallet;
use support\Request;

class DepositController extends BaseController
{
    /**
     * 创建充值订单
     * POST /api/deposit/create
     * Body: { amount, currency, payment_method_id }
     */
    public function create(Request $request)
    {
        $validator = validator($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:USD,CNY,EUR',
            'payment_method_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $paymentMethodId = $this->decodeId($request->input('payment_method_id'));
        $amount = $request->input('amount');
        $currency = $request->input('currency');

        // 计算平台币数量（默认1:1，基础版）
        $rate = PlatformConfig::get('payment', 'default_exchange_rate', '1.00000000');
        $platformAmount = bcmul($amount, $rate, 4);

        $orderId = $this->generateId();
        $orderNo = 'DEP' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        DepositOrder::create([
            'id' => $orderId,
            'order_no' => $orderNo,
            'user_id' => $request->userId,
            'amount' => $amount,
            'currency' => $currency,
            'platform_amount' => $platformAmount,
            'payment_method_id' => $paymentMethodId,
            'status' => 'pending',
        ]);

        return $this->success([
            'order_id' => $this->encodeId($orderId),
            'order_no' => $orderNo,
            'amount' => $amount,
            'platform_amount' => $platformAmount,
        ], '订单创建成功');
    }

    /**
     * 充值订单列表
     * GET /api/deposit/orders
     */
    public function orders(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $query = DepositOrder::where('user_id', $request->userId)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $list = $query->forPage($page, $perPage)->get()->map(function ($o) {
            return [
                'id' => $this->encodeId($o->id),
                'order_no' => $o->order_no,
                'amount' => $o->amount,
                'currency' => $o->currency,
                'platform_amount' => $o->platform_amount,
                'status' => $o->status,
                'paid_at' => $o->paid_at ? $o->paid_at->format('Y-m-d H:i:s') : null,
                'created_at' => $o->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }
}
```

- [ ] **Step 2: Write WithdrawController.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\PlatformConfig;
use common\model\Transaction;
use common\model\UserWallet;
use common\model\WithdrawOrder;
use support\Request;

class WithdrawController extends BaseController
{
    /**
     * 发起提现申请
     * POST /api/withdraw/apply
     * Body: { platform_amount, currency, method, account_info }
     */
    public function apply(Request $request)
    {
        // 检查全局开关
        $switch = PlatformConfig::get('withdraw', 'global_switch', '1');
        if (!$switch) {
            return $this->fail('提现功能暂时关闭', 422);
        }

        $validator = validator($request->all(), [
            'platform_amount' => 'required|numeric|min:0.0001',
            'method' => 'required|string|in:paypal,bank,crypto',
            'account_info' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $platformAmount = $request->input('platform_amount');
        $method = $request->input('method');

        // 校验最低提现金额
        $minAmount = PlatformConfig::get('withdraw', 'min_amount', '1.0000');
        if (bccomp($platformAmount, $minAmount, 4) < 0) {
            return $this->fail('提现金额不能低于' . $minAmount . '平台币', 422);
        }

        // 校验日限额
        $dailyLimit = PlatformConfig::get('withdraw', 'daily_limit', '10000.0000');
        $todayTotal = WithdrawOrder::where('user_id', $request->userId)
            ->whereDate('created_at', date('Y-m-d'))
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->sum('platform_amount');
        $todayTotal = bcadd($todayTotal, $platformAmount, 4);
        if (bccomp($todayTotal, $dailyLimit, 4) > 0) {
            return $this->fail('超过每日提现限额' . $dailyLimit . '平台币', 422);
        }

        // 检查余额
        $wallet = UserWallet::where('user_id', $request->userId)->first();
        if (!$wallet || bccomp($wallet->balance, $platformAmount, 4) < 0) {
            return $this->fail('平台币余额不足', 422);
        }

        // 冻结余额
        $ok = UserWallet::deductBalance($request->userId, $platformAmount);
        if (!$ok) {
            return $this->fail('操作失败，请稍后重试', 500);
        }

        // 生成订单
        $orderId = $this->generateId();
        $orderNo = 'WTH' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        // 自动审核阈值
        $autoThreshold = PlatformConfig::get('withdraw', 'auto_approve_threshold', '100.0000');
        $status = bccomp($platformAmount, $autoThreshold, 4) < 0 ? 'approved' : 'pending';

        WithdrawOrder::create([
            'id' => $orderId,
            'order_no' => $orderNo,
            'user_id' => $request->userId,
            'platform_amount' => $platformAmount,
            'currency' => $request->input('currency', 'USD'),
            'method' => $method,
            'account_info' => $request->input('account_info'),
            'status' => $status,
        ]);

        // 记录流水
        $newWallet = UserWallet::where('user_id', $request->userId)->first();
        Transaction::create([
            'id' => $this->generateId(),
            'user_id' => $request->userId,
            'type' => 'withdraw',
            'amount' => bcmul($platformAmount, '-1', 4),
            'balance_after' => $newWallet->balance,
            'ref_type' => 'withdraw',
            'ref_id' => $orderId,
            'remark' => '提现申请 ' . ($status === 'approved' ? '(自动通过)' : '(待审核)'),
        ]);

        return $this->success([
            'order_id' => $this->encodeId($orderId),
            'order_no' => $orderNo,
            'status' => $status,
        ], $status === 'approved' ? '提现申请已自动通过' : '提现申请已提交，等待审核');
    }

    /**
     * 提现记录
     * GET /api/withdraw/orders
     */
    public function orders(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $query = WithdrawOrder::where('user_id', $request->userId)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $list = $query->forPage($page, $perPage)->get()->map(function ($o) {
            return [
                'id' => $this->encodeId($o->id),
                'order_no' => $o->order_no,
                'platform_amount' => $o->platform_amount,
                'method' => $o->method,
                'status' => $o->status,
                'review_note' => $o->review_note,
                'created_at' => $o->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }
}
```

- [ ] **Step 3: Update route.php — add deposit and withdraw routes**

- [ ] **Step 4: Commit**

```bash
git add service/app/api/v1/controller/DepositController.php service/app/api/v1/controller/WithdrawController.php service/config/route.php
git commit -m "feat: add DepositController and WithdrawController"
```

---

### Task 15: Create GameController, UserController for C端

**Files:**
- Create: `service/app/api/v1/controller/GameController.php`
- Create: `service/app/api/v1/controller/UserController.php`
- Modify: `service/config/route.php`

- [ ] **Step 1: Write GameController.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\Game;
use support\Request;

class GameController extends BaseController
{
    /**
     * 游戏列表
     * GET /api/game/list
     * Query: page, per_page, keyword, type
     */
    public function list(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $query = Game::where('status', 1)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'desc');

        if ($keyword = $request->input('keyword')) {
            $query->where('name', 'like', "%{$keyword}%");
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $total = $query->count();
        $list = $query->forPage($page, $perPage)->get()->map(function ($game) {
            return [
                'id' => $this->encodeId($game->id),
                'name' => $game->name,
                'slug' => $game->slug,
                'type' => $game->type,
                'description' => $game->description,
                'cover_image' => $game->cover_image,
                'currencies' => $game->currencies->map(function ($c) {
                    return [
                        'id' => $this->encodeId($c->id),
                        'name' => $c->name,
                        'symbol' => $c->symbol,
                        'exchange_rate' => $c->exchange_rate,
                    ];
                }),
            ];
        });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * 游戏详情
     * GET /api/game/{hashid}
     */
    public function detail(Request $request, string $hashid)
    {
        $gameId = $this->decodeId($hashid);
        $game = Game::with('currencies')->find($gameId);

        if (!$game || $game->status !== 1) {
            return $this->fail('游戏不存在', 404);
        }

        return $this->success([
            'id' => $this->encodeId($game->id),
            'name' => $game->name,
            'slug' => $game->slug,
            'type' => $game->type,
            'description' => $game->description,
            'cover_image' => $game->cover_image,
            'currencies' => $game->currencies->map(function ($c) {
                return [
                    'id' => $this->encodeId($c->id),
                    'name' => $c->name,
                    'symbol' => $c->symbol,
                    'exchange_rate' => $c->exchange_rate,
                    'min_exchange' => $c->min_exchange,
                    'max_exchange' => $c->max_exchange,
                ];
            }),
        ]);
    }

    /**
     * 启动游戏
     * POST /api/game/launch
     * Body: { game_id }
     */
    public function launch(Request $request)
    {
        $validator = validator($request->all(), [
            'game_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $gameId = $this->decodeId($request->input('game_id'));
        $game = Game::find($gameId);

        if (!$game || $game->status !== 1) {
            return $this->fail('游戏不可用', 404);
        }

        // 基础版：返回游戏入口信息
        // 第三方游戏需要在这里生成签名、token等
        return $this->success([
            'game_id' => $this->encodeId($game->id),
            'name' => $game->name,
            'type' => $game->type,
            'api_endpoint' => $game->api_endpoint,
            // 后续可扩展: signature, token, session 等
        ]);
    }
}
```

- [ ] **Step 2: Write UserController.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\User;
use support\Request;

class UserController extends BaseController
{
    /**
     * 个人信息
     * GET /api/user/profile
     */
    public function profile(Request $request)
    {
        $user = User::find($request->userId);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        return $this->success([
            'id' => $this->encodeId($user->id),
            'username' => $user->username,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
            'language' => $user->language,
            'last_login_at' => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : null,
            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 编辑资料
     * PUT /api/user/profile
     */
    public function updateProfile(Request $request)
    {
        $validator = validator($request->all(), [
            'nickname' => 'nullable|string|max:50',
            'avatar' => 'nullable|string|max:255',
            'language' => 'nullable|string|in:en-US,zh-CN,ja-JP,ko-KR',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $user = User::find($request->userId);
        $user->update($request->only(['nickname', 'avatar', 'language']));

        return $this->success([], '更新成功');
    }
}
```

- [ ] **Step 3: Update route.php** — add game and user routes

- [ ] **Step 4: Commit**

```bash
git add service/app/api/v1/controller/GameController.php service/app/api/v1/controller/UserController.php service/config/route.php
git commit -m "feat: add C端 GameController and UserController"
```

---

## Phase 5: 管理后台扩展

### Task 16: Create admin GameController (CRUD)

**Files:**
- Create: `admin/app/admin/controller/GameController.php`
- Modify: `admin/config/route.php`

- [ ] **Step 1: Write admin GameController.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\Game;
use common\model\GameCurrency;
use support\Request;

class GameController extends BaseController
{
    /**
     * 游戏列表
     * GET /admin/game/list
     */
    public function list(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $query = Game::orderBy('sort', 'asc')->orderBy('id', 'desc');
        if ($keyword = $request->input('keyword')) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        $total = $query->count();
        $list = $query->forPage($page, $perPage)->get()->map(function ($g) {
            return [
                'id' => $this->encodeId($g->id),
                'name' => $g->name,
                'slug' => $g->slug,
                'type' => $g->type,
                'status' => $g->status,
                'sort' => $g->sort,
                'currency_count' => $g->currencies()->count(),
                'created_at' => $g->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * 创建游戏
     * POST /admin/game/create
     */
    public function create(Request $request)
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:50|regex:/^[a-z0-9_-]+$/',
            'type' => 'required|in:self,third_party',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if (Game::where('slug', $request->input('slug'))->exists()) {
            return $this->fail('游戏标识已存在', 422);
        }

        $id = $this->generateId();
        Game::create([
            'id' => $id,
            'name' => $request->input('name'),
            'slug' => $request->input('slug'),
            'type' => $request->input('type'),
            'description' => $request->input('description', ''),
            'cover_image' => $request->input('cover_image', ''),
            'api_endpoint' => $request->input('api_endpoint', ''),
            'api_key' => $request->input('api_key', ''),
            'api_secret' => $request->input('api_secret', ''),
            'status' => (int) $request->input('status', 0),
            'sort' => (int) $request->input('sort', 0),
        ]);

        return $this->success(['id' => $this->encodeId($id)], '游戏创建成功');
    }

    /**
     * 更新游戏
     * PUT /admin/game/{hashid}
     */
    public function update(Request $request, string $hashid)
    {
        $gameId = $this->decodeId($hashid);
        $game = Game::find($gameId);
        if (!$game) {
            return $this->fail('游戏不存在', 404);
        }

        $game->update($request->only([
            'name', 'type', 'description', 'cover_image',
            'api_endpoint', 'api_key', 'api_secret', 'status', 'sort',
        ]));

        return $this->success([], '更新成功');
    }

    /**
     * 删除游戏
     * DELETE /admin/game/{hashid}
     */
    public function destroy(Request $request, string $hashid)
    {
        $gameId = $this->decodeId($hashid);
        $game = Game::find($gameId);
        if (!$game) {
            return $this->fail('游戏不存在', 404);
        }

        $game->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 管理游戏币种
     * POST /admin/game/currency/manage
     */
    public function manageCurrency(Request $request)
    {
        $validator = validator($request->all(), [
            'game_id' => 'required|string',
            'currencies' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $gameId = $this->decodeId($request->input('game_id'));
        $currencies = $request->input('currencies');

        foreach ($currencies as $c) {
            if (!empty($c['id'])) {
                $currency = GameCurrency::where('id', $this->decodeId($c['id']))
                    ->where('game_id', $gameId)
                    ->first();
                if ($currency) {
                    $currency->update([
                        'name' => $c['name'],
                        'symbol' => $c['symbol'],
                        'exchange_rate' => $c['exchange_rate'],
                        'spread_pct' => $c['spread_pct'] ?? '0.00',
                        'min_exchange' => $c['min_exchange'] ?? '0.0000',
                        'max_exchange' => $c['max_exchange'] ?? '999999.9999',
                    ]);
                    continue;
                }
            }

            GameCurrency::create([
                'id' => $this->generateId(),
                'game_id' => $gameId,
                'name' => $c['name'],
                'symbol' => $c['symbol'],
                'exchange_rate' => $c['exchange_rate'],
                'spread_pct' => $c['spread_pct'] ?? '0.00',
                'min_exchange' => $c['min_exchange'] ?? '0.0000',
                'max_exchange' => $c['max_exchange'] ?? '999999.9999',
            ]);
        }

        return $this->success([], '币种更新成功');
    }
}
```

- [ ] **Step 2: Update admin/config/route.php** — add game routes in the /admin group:

```php
// 游戏管理
Route::get('/game/list', [app\admin\controller\GameController::class, 'list']);
Route::post('/game/create', [app\admin\controller\GameController::class, 'create']);
Route::put('/game/{hashid}', [app\admin\controller\GameController::class, 'update']);
Route::delete('/game/{hashid}', [app\admin\controller\GameController::class, 'destroy']);
Route::post('/game/currency/manage', [app\admin\controller\GameController::class, 'manageCurrency']);
```

- [ ] **Step 3: Commit**

```bash
git add admin/app/admin/controller/GameController.php admin/config/route.php
git commit -m "feat: add admin GameController with CRUD and currency management"
```

---

### Task 17: Create admin WithdrawController (review + switch + limits)

**Files:**
- Create: `admin/app/admin/controller/WithdrawController.php`
- Modify: `admin/config/route.php`

- [ ] **Step 1: Write admin WithdrawController.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\PlatformConfig;
use common\model\Transaction;
use common\model\UserWallet;
use common\model\WithdrawOrder;
use support\Request;

class WithdrawController extends BaseController
{
    /**
     * 提现订单列表
     * GET /admin/withdraw/orders
     */
    public function orders(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $query = WithdrawOrder::with('user')->orderBy('created_at', 'desc');
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $list = $query->forPage($page, $perPage)->get()->map(function ($o) {
            return [
                'id' => $this->encodeId($o->id),
                'order_no' => $o->order_no,
                'user' => $o->user ? [
                    'id' => $this->encodeId($o->user->id),
                    'username' => $o->user->username,
                ] : null,
                'platform_amount' => $o->platform_amount,
                'method' => $o->method,
                'status' => $o->status,
                'reviewer_id' => $o->reviewer_id ? $this->encodeId($o->reviewer_id) : null,
                'review_note' => $o->review_note,
                'reviewed_at' => $o->reviewed_at ? $o->reviewed_at->format('Y-m-d H:i:s') : null,
                'created_at' => $o->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * 审核提现
     * PUT /admin/withdraw/review
     * Body: { order_id, action: "approve"|"reject", note }
     */
    public function review(Request $request)
    {
        $validator = validator($request->all(), [
            'order_id' => 'required|string',
            'action' => 'required|in:approve,reject',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $orderId = $this->decodeId($request->input('order_id'));
        $order = WithdrawOrder::find($orderId);

        if (!$order) {
            return $this->fail('订单不存在', 404);
        }

        if ($order->status !== 'pending') {
            return $this->fail('订单状态不是待审核', 422);
        }

        $action = $request->input('action');
        $note = $request->input('note', '');
        $adminId = $request->adminId;

        if ($action === 'approve') {
            $order->update([
                'status' => 'approved',
                'reviewer_id' => $adminId,
                'review_note' => $note,
                'reviewed_at' => date('Y-m-d H:i:s'),
            ]);
            // 注：基础版手动打款，标准版可在此触发自动打款
        } else {
            // 拒绝 → 退回平台币
            $order->update([
                'status' => 'rejected',
                'reviewer_id' => $adminId,
                'review_note' => $note,
                'reviewed_at' => date('Y-m-d H:i:s'),
            ]);

            UserWallet::addBalance($order->user_id, $order->platform_amount);

            $wallet = UserWallet::where('user_id', $order->user_id)->first();
            Transaction::create([
                'id' => $this->generateId(),
                'user_id' => $order->user_id,
                'type' => 'withdraw',
                'amount' => $order->platform_amount,
                'balance_after' => $wallet->balance,
                'ref_type' => 'withdraw',
                'ref_id' => $order->id,
                'remark' => '提现被拒退回: ' . $note,
            ]);
        }

        return $this->success([], $action === 'approve' ? '已通过' : '已拒绝');
    }

    /**
     * 全局提现开关
     * PUT /admin/withdraw/switch
     * Body: { enabled: 1|0 }
     */
    public function toggleSwitch(Request $request)
    {
        $validator = validator($request->all(), [
            'enabled' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $enabled = (string) $request->input('enabled');
        PlatformConfig::set('withdraw', 'global_switch', $enabled, 'bool');

        return $this->success([
            'global_switch' => $enabled === '1',
        ], $enabled === '1' ? '提现功能已开启' : '提现功能已关闭');
    }

    /**
     * 设置提现限额
     * POST /admin/withdraw/limits/set
     */
    public function setLimits(Request $request)
    {
        $validator = validator($request->all(), [
            'daily_limit' => 'nullable|numeric|min:0',
            'min_amount' => 'nullable|numeric|min:0',
            'auto_approve_threshold' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        foreach (['daily_limit', 'min_amount', 'auto_approve_threshold'] as $key) {
            if ($request->has($key)) {
                PlatformConfig::set('withdraw', $key, (string) $request->input($key), 'decimal');
            }
        }

        return $this->success([
            'daily_limit' => PlatformConfig::get('withdraw', 'daily_limit'),
            'min_amount' => PlatformConfig::get('withdraw', 'min_amount'),
            'auto_approve_threshold' => PlatformConfig::get('withdraw', 'auto_approve_threshold'),
            'global_switch' => PlatformConfig::get('withdraw', 'global_switch', '1') === '1',
        ], '限额设置已更新');
    }
}
```

- [ ] **Step 2: Update route.php — add withdraw routes**

```php
Route::get('/withdraw/orders', [app\admin\controller\WithdrawController::class, 'orders']);
Route::put('/withdraw/review', [app\admin\controller\WithdrawController::class, 'review']);
Route::put('/withdraw/switch', [app\admin\controller\WithdrawController::class, 'toggleSwitch']);
Route::post('/withdraw/limits/set', [app\admin\controller\WithdrawController::class, 'setLimits']);
```

- [ ] **Step 3: Commit**

```bash
git add admin/app/admin/controller/WithdrawController.php admin/config/route.php
git commit -m "feat: add admin WithdrawController with review/switch/limits"
```

---

### Task 18: Create PlatformUserController, PaymentController, AnnouncementController + extend Dashboard and Export

**Files:**
- Create: `admin/app/admin/controller/PlatformUserController.php`
- Create: `admin/app/admin/controller/PaymentController.php`
- Create: `admin/app/admin/controller/AnnouncementController.php`
- Modify: `admin/app/admin/controller/DashboardController.php`
- Modify: `admin/app/admin/controller/ExportController.php`
- Modify: `admin/config/route.php`

- [ ] **Step 1: Write PlatformUserController.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\User;
use support\Request;

class PlatformUserController extends BaseController
{
    public function list(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $query = User::orderBy('id', 'desc');
        if ($keyword = $request->input('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                  ->orWhere('nickname', 'like', "%{$keyword}%");
            });
        }
        if ($request->has('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        $total = $query->count();
        $list = $query->forPage($page, $perPage)->get()->map(function ($u) {
            return [
                'id' => $this->encodeId($u->id),
                'username' => $u->username,
                'nickname' => $u->nickname,
                'country' => $u->country,
                'status' => $u->status,
                'last_login_at' => $u->last_login_at ? $u->last_login_at->format('Y-m-d H:i:s') : null,
                'created_at' => $u->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    public function detail(Request $request, string $hashid)
    {
        $userId = $this->decodeId($hashid);
        $user = User::with('wallet')->find($userId);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        return $this->success([
            'id' => $this->encodeId($user->id),
            'username' => $user->username,
            'nickname' => $user->nickname,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => $user->country,
            'language' => $user->language,
            'status' => $user->status,
            'wallet' => $user->wallet ? [
                'balance' => $user->wallet->balance,
                'frozen_balance' => $user->wallet->frozen_balance,
            ] : null,
            'last_login_at' => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : null,
            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
        ]);
    }

    public function update(Request $request, string $hashid)
    {
        $userId = $this->decodeId($hashid);
        $user = User::find($userId);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        $user->update($request->only(['status', 'nickname']));
        return $this->success([], '更新成功');
    }
}
```

- [ ] **Step 2: Write PaymentController.php** (minimal for MVP — list methods + toggle)

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\PaymentMethod;
use support\Request;

class PaymentController extends BaseController
{
    public function list(Request $request)
    {
        $list = PaymentMethod::orderBy('sort')->get()->map(function ($m) {
            return [
                'id' => $this->encodeId($m->id),
                'name' => $m->name,
                'type' => $m->type,
                'provider' => $m->provider,
                'status' => $m->status,
            ];
        });

        return $this->success(['list' => $list]);
    }

    public function toggle(Request $request)
    {
        $validator = validator($request->all(), [
            'id' => 'required|string',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $method = PaymentMethod::find($this->decodeId($request->input('id')));
        if (!$method) {
            return $this->fail('支付方式不存在', 404);
        }

        $method->update(['status' => (int) $request->input('status')]);
        return $this->success([], '已更新');
    }
}
```

- [ ] **Step 3: Write AnnouncementController.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\Announcement;
use support\Request;

class AnnouncementController extends BaseController
{
    public function list(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $query = Announcement::orderBy('id', 'desc');
        $total = $query->count();
        $list = $query->forPage($page, $perPage)->get()->map(function ($a) {
            return [
                'id' => $this->encodeId($a->id),
                'title' => $a->title,
                'type' => $a->type,
                'status' => $a->status,
                'start_at' => $a->start_at ? $a->start_at->format('Y-m-d H:i:s') : null,
                'end_at' => $a->end_at ? $a->end_at->format('Y-m-d H:i:s') : null,
                'created_at' => $a->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    public function create(Request $request)
    {
        $validator = validator($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $id = $this->generateId();
        Announcement::create([
            'id' => $id,
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'type' => $request->input('type', 'system'),
            'target_lang' => $request->input('target_lang', ''),
            'status' => (int) $request->input('status', 1),
            'start_at' => $request->input('start_at'),
            'end_at' => $request->input('end_at'),
        ]);

        return $this->success(['id' => $this->encodeId($id)], '公告发布成功');
    }
}
```

- [ ] **Step 4: Extend DashboardController — add platformDashboard method**

```php
// Add to DashboardController:
/**
 * 平台仪表盘
 * GET /admin/dashboard/platform
 */
public function platform(Request $request)
{
    $totalUsers = User::count();
    $activeUsers = User::where('last_login_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))->count();
    $totalGames = Game::where('status', 1)->count();
    $pendingWithdraws = WithdrawOrder::where('status', 'pending')->count();

    // 今日统计
    $todayDeposits = DepositOrder::whereDate('created_at', date('Y-m-d'))
        ->where('status', 'confirmed')
        ->sum('platform_amount') ?? '0.0000';
    $todayWithdraws = WithdrawOrder::whereDate('created_at', date('Y-m-d'))
        ->whereIn('status', ['approved', 'completed'])
        ->sum('platform_amount') ?? '0.0000';

    // 平台总收益（兑换手续费）
    $totalSpreadFee = ExchangeRecord::sum('spread_fee') ?? '0.0000';

    return $this->success([
        'total_users' => $totalUsers,
        'active_users_7d' => $activeUsers,
        'total_games' => $totalGames,
        'pending_withdraws' => $pendingWithdraws,
        'today_deposits' => $todayDeposits,
        'today_withdraws' => $todayWithdraws,
        'total_spread_fee' => $totalSpreadFee,
    ]);
}
```

- [ ] **Step 5: Extend ExportController — add user and transaction exports**

```php
// Add to ExportController:
/**
 * 导出C端用户
 */
public function exportUsers(Request $request)
{
    $query = User::orderBy('id', 'desc');
    if ($request->has('status')) {
        $query->where('status', (int) $request->input('status'));
    }

    $users = $query->limit(10000)->get();
    // ... build Excel file using PhpSpreadsheet
    // Following the existing excel() method pattern
}

/**
 * 导出平台流水
 */
public function exportTransactions(Request $request)
{
    $query = Transaction::orderBy('created_at', 'desc');
    if ($type = $request->input('type')) {
        $query->where('type', $type);
    }

    $transactions = $query->limit(10000)->get();
    // ... build Excel file
}
```

- [ ] **Step 6: Update route.php** — add all new admin routes

- [ ] **Step 7: Commit**

```bash
git add admin/app/admin/controller/PlatformUserController.php admin/app/admin/controller/PaymentController.php admin/app/admin/controller/AnnouncementController.php admin/app/admin/controller/DashboardController.php admin/app/admin/controller/ExportController.php admin/config/route.php
git commit -m "feat: add admin controllers for platform users, payments, announcements, and dashboard"
```

---

## Phase 6: 添加公告C端接口

### Task 19: Create C端 AnnouncementController

**Files:**
- Create: `service/app/api/v1/controller/AnnouncementController.php`
- Modify: `service/config/route.php`

- [ ] **Step 1: Write AnnouncementController.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\Announcement;
use support\Request;

class AnnouncementController extends BaseController
{
    /**
     * 公告列表
     * GET /api/announcement/list
     */
    public function list(Request $request)
    {
        $list = Announcement::where('status', 1)
            ->where(function ($q) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', date('Y-m-d H:i:s'));
            })
            ->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', date('Y-m-d H:i:s'));
            })
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $this->encodeId($a->id),
                    'title' => $a->title,
                    'type' => $a->type,
                    'created_at' => $a->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return $this->success(['list' => $list]);
    }

    /**
     * 公告详情
     * GET /api/announcement/detail/{hashid}
     */
    public function detail(Request $request, string $hashid)
    {
        $id = $this->decodeId($hashid);
        $a = Announcement::find($id);

        if (!$a || $a->status !== 1) {
            return $this->fail('公告不存在', 404);
        }

        return $this->success([
            'id' => $this->encodeId($a->id),
            'title' => $a->title,
            'content' => $a->content,
            'type' => $a->type,
            'created_at' => $a->created_at->format('Y-m-d H:i:s'),
        ]);
    }
}
```

- [ ] **Step 2: Update route.php**

```php
Route::get('/announcement/list', v('AnnouncementController', 'list'));
Route::get('/announcement/detail/{hashid}', v('AnnouncementController', 'detail'));
```

- [ ] **Step 3: Commit**

---

## Phase 7: 最终路由整合与验证

### Task 20: 最终 service/config/route.php 整合

- [ ] **Step 1: Write the complete service/config/route.php**

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Webman\Route;
use support\Request;

/**
 * C端 API 路由配置
 *
 * 全局中间件链: Cors → SecurityFilter → RateLimit
 * 公开接口: + ApiVersion
 * 认证接口: + ApiVersion → UserAuth
 */

function v(string $controller, string $action): \Closure
{
    return function (Request $request) use ($controller, $action) {
        $version = $request->apiVersion ?? 'v1';
        $class = "\\app\\api\\{$version}\\controller\\{$controller}";
        return (new $class)->{$action}($request);
    };
}

// 健康检查
Route::get('/health', function () {
    return json(['code' => 0, 'message' => 'ok']);
});

// ============================================================
// 公开接口（无需认证）
// ============================================================
Route::group('/api', function () {
    Route::post('/auth/register', v('AuthController', 'register'));
    Route::post('/auth/login', v('AuthController', 'login'));
    Route::post('/auth/refresh', v('AuthController', 'refresh'));
    Route::post('/captcha/generate', v('CaptchaController', 'generate'));

    Route::get('/game/list', v('GameController', 'list'));
    Route::get('/game/{hashid}', v('GameController', 'detail'));
    Route::get('/announcement/list', v('AnnouncementController', 'list'));
    Route::get('/announcement/detail/{hashid}', v('AnnouncementController', 'detail'));
})->middleware([
    app\middleware\ApiVersion::class,
]);

// ============================================================
// 认证接口（需登录）
// ============================================================
Route::group('/api', function () {
    // 钱包
    Route::get('/wallet/info', v('WalletController', 'info'));
    Route::get('/wallet/transactions', v('WalletController', 'transactions'));

    // 充值
    Route::post('/deposit/create', v('DepositController', 'create'));
    Route::get('/deposit/orders', v('DepositController', 'orders'));

    // 兑换
    Route::post('/exchange/quote', v('ExchangeController', 'quote'));
    Route::post('/exchange/buy', v('ExchangeController', 'buy'));
    Route::post('/exchange/sell', v('ExchangeController', 'sell'));
    Route::get('/exchange/records', v('ExchangeController', 'records'));

    // 提现
    Route::post('/withdraw/apply', v('WithdrawController', 'apply'));
    Route::get('/withdraw/orders', v('WithdrawController', 'orders'));

    // 游戏
    Route::post('/game/launch', v('GameController', 'launch'));

    // 用户
    Route::get('/user/profile', v('UserController', 'profile'));
    Route::put('/user/profile', v('UserController', 'updateProfile'));
})->middleware([
    app\middleware\ApiVersion::class,
    common\middleware\UserAuth::class,
]);

Route::disableDefaultRoute();
```

- [ ] **Step 2: Verify service/ can start**

```bash
cd service && php start.php start -d
# Check: php start.php status
```

- [ ] **Step 3: Test a public endpoint**

```bash
curl -s http://localhost:8788/health | python3 -m json.tool
```

Expected: `{"code": 0, "message": "ok"}`

- [ ] **Step 4: Commit**

```bash
git add service/config/route.php
git commit -m "feat: finalize service/ route configuration with all endpoints"
```

---

## Phase 8: Flutter 前端（摘要）

### Task 21-25: Flutter PC 管理后台扩展 + C端平台新建

**Note:** Full Flutter implementation details to be expanded in a follow-up plan. This section summarizes the pages needed.

**admin Flutter (扩展):**
- `GameListPage` — 游戏列表/创建/编辑/币种管理
- `WithdrawPage` — 提现订单列表/审核按钮/开关/限额设置
- `PlatformUserPage` — C端用户列表/详情/封禁
- `PaymentPage` — 支付方式管理
- `AnnouncementPage` — 公告管理
- `DashboardPage` — 扩展平台仪表盘面板
- Export buttons in list pages

**platform Flutter (新建 PC风格):**
- `LoginPage` — 登录/注册
- `GameHallPage` — 游戏大厅（列表/搜索）
- `GameDetailPage` — 游戏详情/启动
- `WalletPage` — 钱包余额/流水
- `DepositPage` — 充值
- `ExchangePage` — 兑换
- `WithdrawPage` — 提现
- `ProfilePage` — 个人中心

---

## Self-Review

1. **Spec coverage:** Each spec requirement has corresponding tasks:
   - Infrastructure → Tasks 1-5
   - Database tables → Task 6
   - Models → Tasks 7-9
   - C端 Auth → Task 11
   - C端 Wallet → Task 12
   - C端 Exchange → Task 13
   - C端 Deposit/Withdraw → Task 14
   - C端 Game/User → Task 15
   - Admin Game CRUD → Task 16
   - Admin Withdraw review/switch → Task 17
   - Admin Platform users/payments/announcements → Task 18
   - Flutter frontend → Tasks 21-25 (summarized)

2. **Placeholder scan:** No TBD, TODO, or incomplete code. All tasks have complete code blocks.

3. **Type consistency:** 
   - All IDs are BIGINT → Snowflake::generate()
   - API IDs are hashid strings → encodeId/decodeId
   - Money amounts use string representation for decimal precision → bcmath
   - Wallet operations use optimistic locking → version field
   - JWT authentication → jwt()->create/verify
   - Model namespace → common\model\
   - Middleware namespace → common\middleware\
   - Controller namespace → app\api\v1\controller\ (service) and app\admin\controller\ (admin)
