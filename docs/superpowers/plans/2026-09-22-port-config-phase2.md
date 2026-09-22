# 端口/请求地址配置化（第二阶段）实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**目标：** 把散落在 PHP 配置、四端客户端、测试基座与文档中的启动端口/请求地址字面量收进各自既有的配置文件；默认值与运行行为不变（仅三处取值修正：admin 鸿蒙 8792→8789、`OPENSEARCH_HTTP_HOST` 的 37831→9200、xiaoxiaole dev 端口避让）。

**架构：** 沿用各端既有机制，不引入新层——PHP 用 `getenv('KEY') ?: 默认`（与 `config/redis.php` 同款）；React 用文件顶部具名常量；Flutter 用既有 `String.fromEnvironment` 模式（`API_BASE_URL` 已有，聊天 WS 补 `CHAT_WS_BASE_URL`）；两棵鸿蒙树各建唯一的 `common/AppConfig.ets`。Docker 编排、nginx 模板、`/api/v1` 相对前缀、Angular `proxy.conf.json` 均不动。

**技术栈：** PHP 8.3 + webman v2（PHPUnit 11.5 service / 12.5 admin）、Vite + TS、Angular CLI、Flutter 3.47、HarmonyOS ArkTS（hvigor 5.19.2，工具链 `/home/component/command-line-tools`）。

---

## 前置条件（未满足不得开工）

1. **当前"修复轮"必须已收口**（5 个修复 agent + 独立验证 PASS）：本计划要写 `README`/`docs/`，与那轮 `docs-config` 的写入冲突。开工前先看 `git status`，确认 docs 相关文件已定稿。
2. **全程不 commit**：仓库现有未提交改动属当前工作流，提交时机由用户决定。本计划内所有"提交"动作一律替换为"运行校验命令"。
3. 禁止读写真实 `.env`（只动 `.env.example`）；不碰用户机器上的 nginx（`/etc/nginx`、reload/restart 都不许）；MySQL 凭据只在 `/tmp/dbtest.creds`。
4. 实施顺序：PHP → 客户端 → 测试基座 → 文档 → 收口（与设计 §6 一致）。
5. **本机 `admin/.env` 需由部署者本人补一行**（代理禁止读写真实 `.env`）：本阶段给 `admin/config/session.php` 新读了 `REDIS_CLUSTER_NODES`，而 `admin/tests/EnvConfigTest::config_env_keys_exist_in_dotenv` 校验的是**活的** `admin/.env`（不是 `.env.example`）。不补则该用例自 Task 2 起持续红（唯一红项，其余全绿），Task 10 的"两边 0 failure"闸门过不去。补法（用户自行执行）：

   ```bash
   printf '\n# Redis 集群节点（仅 session.type=redis_cluster 时生效），逗号分隔\nREDIS_CLUSTER_NODES=127.0.0.1:7000,127.0.0.1:7001\n' >> /home/wwwroot/game-platform-php/admin/.env
   ```

## 证据基线（2026-09-22 实测，改动前的解析值）

```bash
cd /home/wwwroot/game-platform-php/service && php /tmp/probe-cfg.php   # 探针见 Task 1 Step 1
```

```
getenv SCOUT_DRIVER='opensearch' | two-arg='opensearch'
scout.elasticsearch.hosts=["http://127.0.0.1:9200"]
scout.opensearch.host='http://localhost:37831'
session.type=file redis.host='127.0.0.1' redis.port=6379 cluster=["127.0.0.1:7000","127.0.0.1:7001","127.0.0.1:7001"]
```

**两条关键事实**（决定了本计划的落点）：

- service 的生效 scout 驱动是 **opensearch**（`SCOUT_DRIVER=opensearch`），地址来自 `opensearch.host`（即 `OPENSEARCH_HTTP_HOST`）；`elasticsearch.hosts` 在 service 是休眠路径，在 **admin 才是生效路径**（admin `.env.example` 写 `SCOUT_DRIVER=elasticsearch`）。两棵树都改。
- admin 鸿蒙树的 `ApiService.ets:12` / `LoginPage.ets:13` 常量值是 **8792（错）**，而该树调用 `/admin/v1/*` 与 `/api/v1/auth/*`——只有 admin(8789) 注册，必须改为 8789（用户已裁决"一并改正"）。

## 文件结构（责任划分）

| 文件 | 责任 | 动作 |
|---|---|---|
| `service/config/session.php` | 会话后端地址（redis / redis_cluster） | 改 2 处 |
| `service/config/plugin/erikwang2013/webman-scout/app.php` | ES 地址（elasticsearch hosts / opensearch host） | 改 2 处 |
| `admin/config/session.php` | 同 service | 改 2 处 |
| `admin/config/plugin/erikwang2013/webman-scout/app.php` | 同 service | 改 2 处 |
| `service/.env.example`、`admin/.env.example` | 键的登记与错值修正 | 4 处 |
| `service/tests/ConfigDefaultsTest.php`、`admin/tests/ConfigDefaultsTest.php` | 默认值回归（DB-free） | 新建 |
| `apps/react/vite.config.ts`、`admin/apps/react/vite.config.ts` | dev 端口 + 代理目标具名常量 | 各改 1 处 |
| `apps/flutter/platform/lib/app/services/chat_service.dart` | 聊天 WS 地址可覆盖 + 保留旧推导 | 改 1 处，加 1 个静态方法 |
| `apps/flutter/platform/test/chat_ws_url_test.dart` | 钉住默认推导值 | 新建 |
| `admin/apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart` | 自建 Dio 的 baseUrl 改读 `ApiService.baseUrl` | 改 1 处 |
| `apps/harmonyos/entry/src/main/ets/common/AppConfig.ets` | C 端全树唯一地址出处 | 新建 |
| `apps/harmonyos/entry/src/main/ets/service/ApiService.ets` | 改为 import AppConfig | 改 3 处 |
| `admin/apps/harmonyos/entry/src/main/ets/common/AppConfig.ets` | 管理台全树唯一地址出处（8789） | 新建 |
| `admin/apps/harmonyos/.../{service/ApiService.ets,pages/LoginPage.ets,pages/GameHallPage.ets,pages/GameDetailPage.ets}` | 6 处字面量改 import | 改 8 处 |
| `tests/api/admin_test.php` | 测试基座 Redis 地址读 env | 改 2 处 |
| `game/xiaoxiaole/vite.config.ts` | dev 端口避让 5173→5175 | 改 1 处 |
| `docs/test-reports/{api,ui,php-unit}.md`、`docs/superpowers/plans/2026-09-17-port-config.md` | 事实修正与复选框补勾 | 见 Task 9 |

## 与设计文档的四处差异（已复核，需你确认）

1. **补一条设计漏掉的活路径**：`opensearch.host` 的写法 `getenv('OPENSEARCH_HTTP_HOST', 'https://127.0.0.1:6205')` 是**双参误用**（第二参是 `local_only`，未设时返回 `bool(false)`，本机实测；仓库已因同类问题做过 `68c98d0` 修正）。这正是 service 的生效 ES 路径，故一并改成单参 + `?: 'http://127.0.0.1:9200'`（6205 在全仓无任何来源，与 37831 同属死值）。
2. **文档修错从 3 处扩到 6 处**：旧值 grep 还命中 `docs/test-reports/api.md:118`、`ui.md:55`（写 8787，实为 8789）、`php-unit.md:10,13`（写 8787/8788）。
3. **admin Flutter 控制台改法**：设计写"改用单例"，实际最小且与仓库既有写法一致的是 `Dio(BaseOptions(baseUrl: ApiService.baseUrl))`（同树 `pages/login/login_page.dart:22` 就是这么写的）。改成用单例的 `dio` 会额外引入 JWT 拦截器＝运行行为变化，超出"只集中硬编码"。
4. **回归测试直读配置文件，不经全局 `config()` 重载**（2026-09-22 执行期修订）：初版测试用 `\Webman\Config::clear()` + `support\App::loadAllConfig(['route'])` 重载全局配置来取 `config()` 值，实测会重新 include `config/container.php` 造出新的空容器——测试引导注册的容器绑定随之丢失，admin 树 `HashidsServiceTest` 5 例报 `Class 'hashids' not found`（`support\Container::instance()` 就是 `Config::get('container')`）；不清理全局配置又因 `array_replace_recursive` 对列表按下标覆盖而残留多余元素（`elasticsearch.hosts` 覆写 2 条、回落默认 1 条时留下第 2 条）。改为 `require` 目标配置文件、断言其返回数组：同样逐条钉住"默认值=旧字面量、env 可覆盖"，且零进程级副作用；两棵树的测试文件因此逐字节相同。

## 执行期修订记录（2026-09-22）

- **Task 1 Step 3/4 增补 `array_map('trim', ...)`**（质量审查意见，先于 Task 2 实施同步进本计划）：`session.php` 的 `redis_cluster.host` 与 scout `app.php` 的 `elasticsearch.hosts` 改为 `array_map('trim', explode(',', getenv(...) ?: '...'))`（仓库既有先例 `service/app/api/v1/controller/PaymentController.php:64`），并把两处测试输入改为逗号后带空格以钉住该行为；否则 admin 树按旧文本实现会与 service 双树漂移。
- **差异第 4 条（测试直读配置文件）**：修订缘起见该条；Task 1/2 的测试文本与预期断言数（各 10 条）已同步。
- **前置条件第 5 条（活 `admin/.env` 补键）**：`EnvConfigTest` 的设计契约是"config 读的每个 env 键都要能在 `.env` 里找到"，本阶段新增的 `REDIS_CLUSTER_NODES` 必须由部署者补进真实 `.env`；不改测试、不加豁免（那属削弱既有校验）。
- **Task 3 增补 `admin/.env.docker`**（原计划漏）：该文件是 `cp .env.docker .env` 的 docker 部署活 `.env` 来源，同样要给新键登记，否则 docker 部署日后跑 `EnvConfigTest` 会撞与前置条件 5 同一个红。CI 走的是 `.env.example`（`.github/workflows/ci.yml:104,153` 的 `test -f .env || cp .env.example .env`），故 CI 只需 `.env.example` 登记。

---

## Task 1: service 会话/ES 地址去字面量（TDD）

**Files:**
- Create: `service/tests/ConfigDefaultsTest.php`
- Modify: `service/config/session.php:33-42`
- Modify: `service/config/plugin/erikwang2013/webman-scout/app.php:265-268`、`:285`

- [x] **Step 1: 写失败测试**

新建 `service/tests/ConfigDefaultsTest.php`：

```php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * 配置默认值回归：端口/地址集中进配置后，未设 env 时解析值必须与改动前的字面量逐一相等；
 * 设了 env 时必须生效（否则"集中"是假的）。
 *
 * 直接 require 目标配置文件、断言其返回数组：`Config::clear()` + `App::loadAllConfig()`
 * 重载全局配置会重新 include `config/container.php` 造出空容器，丢掉测试引导注册的容器绑定
 * （admin `HashidsServiceTest` 曾因 `Class 'hashids' not found` 变红），不清理又会因
 * `array_replace_recursive` 对列表按下标覆盖而残留元素。DB-free，不连 MySQL/Redis。
 */
class ConfigDefaultsTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $saved = [];

    protected function tearDown(): void
    {
        foreach ($this->saved as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
                continue;
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
        $this->saved = [];
    }

    private function setEnv(string $key, ?string $value): void
    {
        if (!array_key_exists($key, $this->saved)) {
            $this->saved[$key] = getenv($key);
        }
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
            return;
        }
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    public function testSessionRedisDefaultsMatchLegacyLiterals(): void
    {
        $this->setEnv('REDIS_HOST', null);
        $this->setEnv('REDIS_PORT', null);
        $this->setEnv('REDIS_CLUSTER_NODES', null);

        $session = require __DIR__ . '/../config/session.php';
        $this->assertSame('127.0.0.1', $session['config']['redis']['host']);
        $this->assertSame(6379, $session['config']['redis']['port']);
        // 旧字面量是 ['127.0.0.1:7000','127.0.0.1:7001','127.0.0.1:7001']，第三个是重复项，去重后等价
        $this->assertSame(
            ['127.0.0.1:7000', '127.0.0.1:7001'],
            $session['config']['redis_cluster']['host']
        );
    }

    public function testSessionRedisHonoursEnv(): void
    {
        $this->setEnv('REDIS_HOST', '10.9.8.7');
        $this->setEnv('REDIS_PORT', '6390');
        $this->setEnv('REDIS_CLUSTER_NODES', '10.9.8.7:7000, 10.9.8.8:7000'); // 逗号后带空格：钉住 array_map('trim', ...)

        $session = require __DIR__ . '/../config/session.php';
        $this->assertSame('10.9.8.7', $session['config']['redis']['host']);
        $this->assertSame(6390, $session['config']['redis']['port']);
        $this->assertSame(['10.9.8.7:7000', '10.9.8.8:7000'], $session['config']['redis_cluster']['host']);
    }

    public function testScoutHostsDefaultsAndOverride(): void
    {
        $this->setEnv('SCOUT_HOSTS', null);
        $scout = require __DIR__ . '/../config/plugin/erikwang2013/webman-scout/app.php';
        $this->assertSame(['http://127.0.0.1:9200'], $scout['elasticsearch']['hosts']);

        $this->setEnv('SCOUT_HOSTS', 'http://es1:9200, http://es2:9200'); // 同上，空格须被 trim
        $scout = require __DIR__ . '/../config/plugin/erikwang2013/webman-scout/app.php';
        $this->assertSame(['http://es1:9200', 'http://es2:9200'], $scout['elasticsearch']['hosts']);
    }

    public function testOpenSearchHostDefaultsAndOverride(): void
    {
        $this->setEnv('OPENSEARCH_HTTP_HOST', null);
        $scout = require __DIR__ . '/../config/plugin/erikwang2013/webman-scout/app.php';
        $this->assertSame('http://127.0.0.1:9200', $scout['opensearch']['host']);

        $this->setEnv('OPENSEARCH_HTTP_HOST', 'http://os.internal:9200');
        $scout = require __DIR__ . '/../config/plugin/erikwang2013/webman-scout/app.php';
        $this->assertSame('http://os.internal:9200', $scout['opensearch']['host']);
    }
}
```

- [x] **Step 2: 运行，确认失败在正确的地方**

```bash
cd /home/wwwroot/game-platform-php/service
php vendor/bin/phpunit --filter ConfigDefaultsTest --do-not-cache-result --testdox
```

预期：`testSessionRedisDefaultsMatchLegacyLiterals` **通过**（配置里还是硬编码字面量，未设 env 时值相同）；`testSessionRedisHonoursEnv`、`testScoutHostsDefaultsAndOverride`、`testOpenSearchHostDefaultsAndOverride` **失败**——分别报 `Failed asserting that '127.0.0.1' is identical to '10.9.8.7'` 一类。这四条正是本任务要修的"不读 env"。

- [x] **Step 3: 改 `service/config/session.php`**

把 `:33-42` 的两块替换为（并加一行中文注释）：

```php
        // 会话后端地址：type=redis / redis_cluster 时生效，可用 REDIS_HOST / REDIS_PORT / REDIS_CLUSTER_NODES 覆盖
        'redis' => [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'auth' => '',
            'timeout' => 2,
            'database' => '',
            'prefix' => 'game-platform:session:',
        ],
        'redis_cluster' => [
            'host' => array_map('trim', explode(',', getenv('REDIS_CLUSTER_NODES') ?: '127.0.0.1:7000,127.0.0.1:7001')),
            'timeout' => 2,
            'auth' => '',
            'prefix' => 'game-platform:session:',
        ]
```

- [x] **Step 4: 改 `service/config/plugin/erikwang2013/webman-scout/app.php`**

`:265-268` 改为：

```php
    'elasticsearch' => [
        'hosts' => array_map('trim', explode(',', getenv('SCOUT_HOSTS') ?: 'http://127.0.0.1:9200')),
```

`:285` 改为（单参 getenv，见"与设计的差异"第 1 条）：

```php
        'host' => getenv('OPENSEARCH_HTTP_HOST') ?: 'http://127.0.0.1:9200',
```

- [x] **Step 5: 运行本任务的测试，全绿**

```bash
php vendor/bin/phpunit --filter ConfigDefaultsTest --do-not-cache-result --testdox
```

预期：`OK (4 tests, 10 assertions)`。

- [x] **Step 6: 全量 service 套件无回归**

```bash
php vendor/bin/phpunit --do-not-cache-result 2>&1 | tail -6
```

预期：`Failures: 0, Errors: 0`；`Skipped: 3` 左右（DB 依赖用例在无库时跳过）；总数 = 基线 + 本任务 4 例。

## Task 2: admin 会话/ES 地址去字面量（TDD）

**Files:**
- Create: `admin/tests/ConfigDefaultsTest.php`
- Modify: `admin/config/session.php:33-42`
- Modify: `admin/config/plugin/erikwang2013/webman-scout/app.php:265-268`、`:285`

- [x] **Step 1: 写失败测试**

新建 `admin/tests/ConfigDefaultsTest.php`——与 Task 1 Step 1 **逐字节相同**（`__DIR__ . '/../config/...'` 相对路径，两棵树内容可完全一致；改测试取值的两种替代做法为何被否，见"与设计文档的四处差异"第 4 条与"执行期修订记录"）。

- [x] **Step 2: 运行，确认失败**

```bash
cd /home/wwwroot/game-platform-php/admin
php vendor/bin/phpunit --filter ConfigDefaultsTest --do-not-cache-result --testdox
```

预期：与 Task 1 Step 2 相同的"默认值用例通过、override 用例失败"模式。

- [x] **Step 3: 改 `admin/config/session.php`**

与 Task 1 Step 3 完全相同的替换（`redis` 与 `redis_cluster` 两块 + 顶部中文注释）。

- [x] **Step 4: 改 `admin/config/plugin/erikwang2013/webman-scout/app.php`**

`:265-268` 与 `:285` 与 Task 1 Step 4 完全相同（admin 的 `SCOUT_DRIVER=elasticsearch` 使 `elasticsearch.hosts` 成为生效路径；`opensearch` 块保留同款写法以免两棵树漂移）。

- [x] **Step 5: 运行本任务测试与全量 admin 套件**

```bash
php vendor/bin/phpunit --filter ConfigDefaultsTest --do-not-cache-result --testdox
php vendor/bin/phpunit --do-not-cache-result 2>&1 | tail -6
```

预期：前者 `OK (4 tests, 10 assertions)`；后者 `Tests: 190, Assertions: 437, Skipped: 3`，`Errors: 0`——`Failures` 取决于前置条件 5：本机 `admin/.env` 未补 `REDIS_CLUSTER_NODES` 时恰有 1 个 failure（`EnvConfigTest::config_env_keys_exist_in_dotenv`，由本次新读的 env 键引入），补齐后 `Failures: 0`。判定本任务合格看 `Errors: 0` + 除该用例外全绿；不得为凑绿改 `EnvConfigTest` 或给它加豁免。

## Task 3: 两个 `.env.example` 的键登记与错值修正

**Files:**
- Modify: `service/.env.example:156`、`:169`、`:34` 附近
- Modify: `admin/.env.example:73`、`:122`、末尾
- Modify: `admin/.env.docker:51` 附近（新键登记；该文件由 `cp .env.docker .env` 灌进 docker 部署的活 `.env`，缺键会撞与前置条件 5 同一个红）

- [x] **Step 1: `service/.env.example` 三处**

`:156` 上方的注释行 `# ES 主机地址，多节点逗号分隔` 改为：

```
# ES 主机地址，多节点逗号分隔（仅 elasticsearch 驱动读取；opensearch 驱动读下方 OPENSEARCH_HTTP_HOST）
```

`:169` 的 `OPENSEARCH_HTTP_HOST=http://localhost:37831` 改为（与 admin 同名键的取值对齐）：

```
OPENSEARCH_HTTP_HOST=http://localhost:9200
```

`REDIS_PORT=6379` 之后插入：

```
# Redis 集群节点（仅 session.type=redis_cluster 时生效），逗号分隔
REDIS_CLUSTER_NODES=127.0.0.1:7000,127.0.0.1:7001
```

- [x] **Step 2: `admin/.env.example` 三处**

`:73` 上方注释 `# ES 主机地址，多节点逗号分隔` 改为：

```
# ES 主机地址，多节点逗号分隔（elasticsearch 驱动读取）
```

`:122` `REDIS_PORT=6379` 之后插入：

```
# Redis 集群节点（仅 session.type=redis_cluster 时生效），逗号分隔
REDIS_CLUSTER_NODES=127.0.0.1:7000,127.0.0.1:7001
```

`REDIS_DATABASE=0` 之后插入（补文档，行为不变）：

```
# ── CORS ──
# 允许的跨域来源（* 表示不限；admin/app/middleware/Cors.php 读取，缺省即 *）
CORS_ORIGIN=*
```

- [x] **Step 3: `admin/.env.docker` 登记同一新键**

`REDIS_PORT=6379` 之后插入（docker 内网地址风格；compose 编排为单节点 redis，集群未启用时该键不生效）：

```
# Redis 集群节点（仅 session.type=redis_cluster 时生效）；docker 编排为单节点 redis，如需集群请改指真实节点
REDIS_CLUSTER_NODES=redis:7000,redis:7001
```

- [x] **Step 4: 校验**

```bash
cd /home/wwwroot/game-platform-php
grep -n '37831' service/.env.example admin/.env.example ; echo "exit=$?  # 期望 1（无命中）"
grep -n 'CORS_ORIGIN\|REDIS_CLUSTER_NODES' service/.env.example admin/.env.example admin/.env.docker
```

预期：第一条无输出、`exit=1`；第二条列出 5 行（`.env.example` 每树 2 个键 + `.env.docker` 1 个键）。

## Task 4: React 两棵树——dev 端口与代理目标提成具名常量

**Files:**
- Modify: `apps/react/vite.config.ts`
- Modify: `admin/apps/react/vite.config.ts`

- [x] **Step 1: `apps/react/vite.config.ts` 改为**

```ts
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// 开发服务器端口与后端地址集中在此；产物用相对路径 /api/v1，由 nginx 同源转发
const DEV_PORT = 5173
const API_TARGET = 'http://localhost:8792'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    port: DEV_PORT,
    // 前端一律用相对路径 /api/v1/...，由 dev server 转发到 service 应用
    proxy: {
      '/api': { target: API_TARGET, changeOrigin: true },
    },
  },
})
```

- [x] **Step 2: `admin/apps/react/vite.config.ts` 改为**

```ts
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// 管理台后端地址与 dev 端口集中在此（4 条代理规则共用 TARGET）
const TARGET = 'http://localhost:8789'
const DEV_PORT = 5273

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    port: DEV_PORT,
    proxy: {
      '/admin/v1': TARGET,
      '/api/v1': TARGET,
      '/health': TARGET,
      '/metrics': TARGET,
    },
  },
})
```

- [x] **Step 3: 两棵树各自类型检查 + 构建**

```bash
cd /home/wwwroot/game-platform-php/apps/react
./node_modules/.bin/tsc -b && ./node_modules/.bin/vite build --base=/app-react/
cd /home/wwwroot/game-platform-php/admin/apps/react
./node_modules/.bin/tsc -b && ./node_modules/.bin/vite build
```

预期：两条命令均无 TS 报错并输出 `✓ built in …`。`apps/react` 必须带 `--base=/app-react/`（仓库产物约定）。构建只改 config，产物内容应与改动前一致（可用 md5 比对，见 Task 10）。

## Task 5: Flutter 两棵树——聊天 WS 可覆盖、控制台 baseUrl 归口

**Files:**
- Modify: `apps/flutter/platform/lib/app/services/chat_service.dart`
- Create: `apps/flutter/platform/test/chat_ws_url_test.dart`
- Modify: `admin/apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart`

- [x] **Step 1: 写"默认推导不变"的测试**

新建 `apps/flutter/platform/test/chat_ws_url_test.dart`：

```dart
// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'package:flutter_test/flutter_test.dart';
import 'package:game_platform/app/services/chat_service.dart';

void main() {
  test('未传 CHAT_WS_BASE_URL 时，聊天 WS 地址沿用 ApiService.baseUrl 的 host + 8791', () {
    // ApiService.baseUrl 默认 http://localhost:8792 → 推导值必须逐字节不变
    expect(ChatService.resolveChatUri().toString(), 'ws://localhost:8791');
  });
}
```

- [x] **Step 2: 运行，确认失败**

```bash
cd /home/wwwroot/game-platform-php/apps/flutter/platform
flutter test test/chat_ws_url_test.dart --reporter compact
```

预期：编译失败/报错 `The method 'resolveChatUri' isn't defined for the type 'ChatService'`。

- [x] **Step 3: 改 `chat_service.dart`**

在 `class ChatService extends GetxService {` 内、`Future<void> connect()` 之前插入：

```dart
  /// 聊天 WS 地址：默认沿用 ApiService.baseUrl 的 host + 8791（与服务端 CHAT_WS_PORT 对应），
  /// 可用 --dart-define=CHAT_WS_BASE_URL=ws://host:port 整串覆盖。
  static const String _chatWsBaseUrl = String.fromEnvironment('CHAT_WS_BASE_URL');

  static Uri resolveChatUri() {
    if (_chatWsBaseUrl.isNotEmpty) {
      return Uri.parse(_chatWsBaseUrl);
    }
    final baseUri = Uri.parse(ApiService.baseUrl);
    final scheme = baseUri.scheme == 'https' ? 'wss' : 'ws';
    return Uri.parse('$scheme://${baseUri.host}:8791');
  }
```

把 `connect()` 里的

```dart
      final baseUri = Uri.parse(ApiService.baseUrl);
      final scheme = baseUri.scheme == 'https' ? 'wss' : 'ws';
      _channel = WebSocketChannel.connect(Uri.parse('$scheme://${baseUri.host}:8791'));
```

替换为：

```dart
      _channel = WebSocketChannel.connect(ChatService.resolveChatUri());
```

- [x] **Step 4: 运行 Flutter 测试（本树全部）**

```bash
cd /home/wwwroot/game-platform-php/apps/flutter/platform
flutter test --reporter compact
```

预期：`All tests passed!`，用例数 = 原 2 例 + 本任务 1 例。

- [x] **Step 5: 改 `admin/apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart`**

顶部 import 区加：

```dart
import '../../services/api_service.dart';
```

`:12` 的

```dart
  final Dio _dio = Dio(BaseOptions(baseUrl: 'http://localhost:8789'));
```

改为（与本树 `pages/login/login_page.dart:22` 同款写法）：

```dart
  final Dio _dio = Dio(BaseOptions(baseUrl: ApiService.baseUrl));
```

- [x] **Step 6: 运行 admin Flutter 测试**

```bash
cd /home/wwwroot/game-platform-php/admin/apps/flutter
flutter test --reporter compact
```

预期：`All tests passed!`（4 个测试文件 + 1 个 `test_helpers.dart`，数量不变）。

## Task 6: HarmonyOS 两棵树——各建唯一 `AppConfig.ets`

**Files:**
- Create: `apps/harmonyos/entry/src/main/ets/common/AppConfig.ets`
- Modify: `apps/harmonyos/entry/src/main/ets/service/ApiService.ets:9-12,49,108`
- Create: `admin/apps/harmonyos/entry/src/main/ets/common/AppConfig.ets`
- Modify: `admin/apps/harmonyos/entry/src/main/ets/service/ApiService.ets:9-12,46,82`
- Modify: `admin/apps/harmonyos/entry/src/main/ets/pages/LoginPage.ets:10-13,61,124`
- Modify: `admin/apps/harmonyos/entry/src/main/ets/pages/GameHallPage.ets:25`
- Modify: `admin/apps/harmonyos/entry/src/main/ets/pages/GameDetailPage.ets:38,68`

- [x] **Step 1: C 端新建 `AppConfig.ets`**

```ts
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 全树唯一的服务地址出处 — 真机部署时只改这里。
 * 模拟器: http://10.0.2.2:8792  （映射宿主机 localhost:8792，C 端 service 端口）
 * 真机:   http://<电脑局域网IP>:8792  （与手机同一局域网，并放行 8792）
 */
export const API_BASE_URL: string = 'http://10.0.2.2:8792';
```

- [x] **Step 2: C 端 `ApiService.ets` 改 import 与两处引用**

删掉 `:9-12`（3 行注释 + `const BASE_URL = ...`），在 `import { TokenManager } from '../utils/TokenManager';` 之后加：

```ts
import { API_BASE_URL } from '../common/AppConfig';
```

`:49` `${BASE_URL}${path}` → `${API_BASE_URL}${path}`；`:108` `${BASE_URL}/api/v1/auth/refresh` → `${API_BASE_URL}/api/v1/auth/refresh`。

- [x] **Step 3: admin 树新建 `AppConfig.ets`（值改正为 8789）**

```ts
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 全树唯一的服务地址出处 — 真机部署时只改这里。
 * 模拟器: http://10.0.2.2:8789  （映射宿主机 localhost:8789，管理端 admin 端口）
 * 真机:   http://<服务器IP>:8789
 */
export const API_BASE_URL: string = 'http://10.0.2.2:8789';
```

- [x] **Step 4: admin 树 `service/ApiService.ets`**

删掉 `:9-12` 的注释与常量，加 `import { API_BASE_URL } from '../common/AppConfig';`；`:46` 与 `:82` 的 `BASE_URL` → `API_BASE_URL`。

- [x] **Step 5: admin 树 `pages/LoginPage.ets`**

删掉 `:10-13`（三行注释 + 常量），加 `import { API_BASE_URL } from '../common/AppConfig';`；`:61` 与 `:124` 的 `BASE_URL` → `API_BASE_URL`。

- [x] **Step 6: admin 树 `pages/GameHallPage.ets`**

加 `import { API_BASE_URL } from '../common/AppConfig';`；`:25` 改为：

```ts
      const response = await request.request(`${API_BASE_URL}/api/v1/game/list`, {
```

- [x] **Step 7: admin 树 `pages/GameDetailPage.ets`**

加 `import { API_BASE_URL } from '../common/AppConfig';`；`:38` 与 `:68` 改为：

```ts
        `${API_BASE_URL}/api/v1/game/${this.gameId}`,
```

```ts
        `${API_BASE_URL}/api/v1/game/launch`,
```

- [x] **Step 8: grep 断言"唯一出处"**

```bash
cd /home/wwwroot/game-platform-php
grep -rn '10\.0\.2\.2' apps/harmonyos/entry/src/main/ets | grep -v 'common/AppConfig.ets' ; echo "C端 exit=$?  # 期望 1"
grep -rn '10\.0\.2\.2' admin/apps/harmonyos/entry/src/main/ets | grep -v 'common/AppConfig.ets' ; echo "admin exit=$?  # 期望 1"
grep -rn '8792' admin/apps/harmonyos/entry/src/main/ets ; echo "admin 8792 exit=$?  # 期望 1（全树不再有 8792）"
```

（`apps/harmonyos/entry/src/main/resources/base/profile/network_config.json` 里的 `10.0.2.2` 是明文 HTTP 放行名单，**保留**，不在断言范围内。）

- [x] **Step 9: 用沙箱做 clean 构建（两棵树）**

C 端沙箱 `/tmp/hm-check` 已存在（4 处构建修补已应用），只需刷新源码：

```bash
cd /home/wwwroot/game-platform-php
rm -rf /tmp/hm-check/entry/src && cp -r apps/harmonyos/entry/src /tmp/hm-check/entry/src
cd /tmp/hm-check && /home/component/command-line-tools/bin/hvigorw clean --no-daemon >/tmp/hm-c.log 2>&1 && \
  /home/component/command-line-tools/bin/hvigorw assembleHap --no-daemon >/tmp/hm-b.log 2>&1; echo "exit=$?"
grep -E 'ERROR|BUILD' /tmp/hm-b.log | tail -5
ls -l entry/build/default/outputs/default/*.hap
```

admin 树沙箱需新建（同款 4 处修补）：

```bash
cd /home/wwwroot/game-platform-php
rm -rf /tmp/hm-check-admin && cp -r admin/apps/harmonyos /tmp/hm-check-admin
cd /tmp/hm-check-admin
# ① hvigor 依赖指向本机离线包
python3 - <<'PY'
import json,re,io
p='hvigor/hvigor-config.json5'
s=open(p).read()
s=s.replace('"dependencies": {', '"dependencies": {\n    "@ohos/hvigor": "file:/home/component/command-line-tools/hvigor/hvigor",')
open(p,'w').write(s)
PY
# ② oh-package.json5 加 modelVersion + 本地包
python3 - <<'PY'
import json,re
p='oh-package.json5'
s=open(p).read()
if '"modelVersion"' not in s:
    s=s.replace('{', '{\n  "modelVersion": "5.0.0",', 1)
s=re.sub(r'"@ohos/hvigor[^"]*": "[^"]*"', '"@ohos/hvigor": "file:/home/component/command-line-tools/hvigor/hvigor"', s)
s=re.sub(r'"@ohos/hvigor-ohos-plugin": "[^"]*"', '"@ohos/hvigor-ohos-plugin": "file:/home/component/command-line-tools/hvigor/hvigor-ohos-plugin"', s)
open(p,'w').write(s)
PY
# ③ AppScope/app.json5 加 apiReleaseType
python3 - <<'PY'
p='AppScope/app.json5'
s=open(p).read()
if 'apiReleaseType' not in s:
    s=s.replace('"bundleName"', '"apiReleaseType": "Release1",\n  "bundleName"', 1)
open(p,'w').write(s)
PY
# ④ entry/build-profile.json5：targets[0].source 改 sourceRoots，删 runtimeOnly
python3 - <<'PY'
import re
p='entry/build-profile.json5'
s=open(p).read()
s=re.sub(r'"source"\s*:\s*\{[^}]*\}', '"source": {\n        "sourceRoots": ["./src/main"]\n      }', s, count=1)
s=re.sub(r',?\s*"runtimeOnly"\s*:\s*\{[^{}]*\}', '', s)
open(p,'w').write(s)
PY
/home/component/command-line-tools/bin/hvigorw clean --no-daemon >/tmp/hma-c.log 2>&1 && \
  /home/component/command-line-tools/bin/hvigorw assembleHap --no-daemon >/tmp/hma-b.log 2>&1; echo "exit=$?"
grep -E 'ERROR|BUILD' /tmp/hma-b.log | tail -5
```

预期：两棵树均以 `BUILD SUCCESSFUL` 结束；C 端树因修复轮已修完全部 ArkTS 错误，`grep -c ERROR` 应为 0（工具汇总计数比列出的条目多 1，是既有现象，以列出的条目为准）；admin 树**先记录改动前的 ERROR 集**，改动后不得新增——若基线本就非 0，把两侧条目贴进收口报告。
产物：`entry/build/default/outputs/default/entry-default-unsigned.hap`（未签名，属预期；C 端约 461 KB）。

## Task 7: 测试基座——`tests/api/admin_test.php` 的 Redis 地址读 env

**Files:**
- Modify: `tests/api/admin_test.php:16`、`:36`

- [x] **Step 1: 两处 `new Redis()` 的连接改 env**

`:16`：

```php
$rl->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379));
```

`:36`：

```php
    $redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379));
```

- [x] **Step 2: 语法检查（该脚本需要在线服务，不能真跑）**

```bash
cd /home/wwwroot/game-platform-php
php -l tests/api/admin_test.php
grep -n "connect(" tests/api/admin_test.php
```

预期：`No syntax errors detected`；两处 connect 都是 `getenv(...) ?: 默认` 形式。

## Task 8: 小游戏 dev 端口避让

**Files:**
- Modify: `game/xiaoxiaole/vite.config.ts:6`

- [x] **Step 1: 端口 5173 → 5175（与 `apps/react` 的 5173 撞车）**

```ts
  server: {
    port: 5175,
  },
```

- [x] **Step 2: 构建验证**

```bash
cd /home/wwwroot/game-platform-php/game/xiaoxiaole
npm run build 2>&1 | tail -4
```

预期：`vite v… building for production…` 后 `✓ built in …`（无默认端口冲突提示）。

## Task 9: 文档只修错

**Files:**
- Modify: `docs/test-reports/api.md:5,114,118,195`
- Modify: `docs/test-reports/ui.md:55`
- Modify: `docs/test-reports/php-unit.md:10,13,42-43`
- Modify: `README.md:270`
- Modify: `docs/superpowers/plans/2026-09-17-port-config.md`（27 个复选框 + 落地注记）

- [x] **Step 1: `docs/test-reports/api.md` 四处**

`:5` 的 `service`(服务端 API, 端口 8795/8796/8797) 改为 `service`(服务端 API, 端口 8792；WebSocket 8790/8791)。

`:114` 的整行注释改为：

```bash
# 服务端 (8792；WS 8790/8791，均可由 .env 的 APP_PORT / LEADERBOARD_WS_PORT / CHAT_WS_PORT 覆盖)
```

`:118` 的 `curl -s http://127.0.0.1:8789/health; curl -s http://127.0.0.1:8795/health` 改为 `curl -s http://127.0.0.1:8789/health; curl -s http://127.0.0.1:8792/health`。

`:195` 第 8 条整条替换为：

```
8. **端口已配置化(2026-09-22 复核)**: admin 8789、service 8792(WS 8790/8791) 均由 `.env` 的 APP_PORT / LEADERBOARD_WS_PORT / CHAT_WS_PORT 控制, 三处(监听/映射/Nginx)一致; 本报告 2026-08-27 实测的 8795/8796/8797 与当时的 README 8788/8790/8791 均为迁移前旧值。
```

- [x] **Step 2: `docs/test-reports/ui.md:55`**

把 `（硬编码 \`http://localhost:8787\`）` 改为 `（自建 Dio，原硬编码 http://localhost:8787；2026-09-22 起读取 ApiService.baseUrl，默认 http://localhost:8789）`。

- [x] **Step 3: `docs/test-reports/php-unit.md:10,13`**

`# admin（端口 8787）` → `# admin`；`# service（端口 8788）` → `# service`（phpunit 不监听端口，原注记既无意义又过时）。

- [x] **Step 4: 同步测试计数（Tasks 1/2 各新增 4 例，README 与 php-unit.md 的数字已过期）**

```bash
cd /home/wwwroot/game-platform-php/service && php vendor/bin/phpunit --do-not-cache-result 2>&1 | tail -2
cd /home/wwwroot/game-platform-php/admin && php vendor/bin/phpunit --do-not-cache-result 2>&1 | tail -2
```

预期：service `Tests: 273, Assertions: 701`；admin `Tests: 190, Assertions: 437`（`Failures` 见下）——**以实测为准**。service 若未导出 `GP_DB_USER`/`GP_DB_PASS` 会多 26 个 skip、断言偏低，先 `set -a && . /tmp/dbtest.creds && set +a`。admin 的 `Failures` 取决于前置条件 5（活 `.env` 补键）；若用户尚未补，则如实写 1 failure（`EnvConfigTest`），不要写成 0。

把实测数同步两处（skipped 仍各 3）：

- `README.md:270`：`service 269 用例`/`service 691 断言`、`admin 186 用例`/`admin 427 断言` → 实测值。
- `docs/test-reports/php-unit.md:42-43`：两行改为实测值，并在「复跑记录」说明列表追加一行：本阶段新增的 4(admin) + 4(service) 个配置默认值回归用例已计入。

- [x] **Step 5: `docs/superpowers/plans/2026-09-17-port-config.md` 补勾**

先复核落地证据（以下命令的输出必须与"已落地"一致，再勾选框）：

```bash
cd /home/wwwroot/game-platform-php
grep -n 'APP_PORT' admin/config/process.php service/config/process.php | head -4
grep -n "'url'" admin/config/app.php
grep -n 'envConfigValue' install/index.php | head -3
ls -1 docker-compose.yml .env.example nginx.conf.template
grep -c ':-' docker-compose.yml
grep -n 'server admin:\${ADMIN_PORT}\|server service:\${SERVICE_PORT}' nginx.conf.template
grep -n 'proxy_pass' admin/docs/nginx-security.conf
```

预期：admin/service 的 process.php 均读 APP_PORT；`app.php` 有 `url` 键；`install/index.php` 有 `envConfigValue`；四个文件都在；compose 有多处 `${VAR:-默认}`；模板 upstream 用插值；`nginx-security.conf` 的 `proxy_pass` 为 `http://app:8789`。

然后把该文件 27 个 `- [ ]` 勾成 `- [x]`（`sed -i 's/^- \[ \]/- [x]/' docs/superpowers/plans/2026-09-17-port-config.md`），并在标题行下方插入：

```markdown
> **落地注记（2026-09-22 补记）**：本计划内容已由 commit `8ff4e87` 落地；上式复选框于 2026-09-22 对照磁盘产物逐条复核后补勾。前端 apps、tests、docs 端口表当时明确不在范围内，由《端口/请求地址配置化（第二阶段）实现计划》（`2026-09-22-port-config-phase2.md`）承接。
```

- [x] **Step 6: 旧值 grep 断言**

```bash
cd /home/wwwroot/game-platform-php
grep -rn '879[567]\|8788' docs/test-reports/ ; echo "exit=$?  # 期望 1（无命中）"
grep -rn '8787' docs/test-reports/ ; echo "exit=$?  # 期望 1（无命中）"
```

预期：两条都无输出、`exit=1`。（`docs/superpowers/` 与 `admin/docs/superpowers/` 里的历史计划/设计稿不改。）

## Task 10: 收口——全量校验 + 独立验证

- [x] **Step 1: 硬编码残留审计**

```bash
cd /home/wwwroot/game-platform-php
grep -rn --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=dist --exclude-dir=build \
  --exclude-dir=.hvigor --exclude-dir=oh_modules --exclude-dir=runtime --exclude-dir=vendor \
  --exclude-dir=public --exclude-dir=superpowers --exclude-dir=.claude-flow \
  --include='*.php' --include='*.ts' --include='*.tsx' --include='*.dart' --include='*.ets' \
  -E ':87(8[789]|9[012])|:517[0-9]' . | grep -vE '\.env\.example|proxy\.conf\.json|angular\.json|README'
```

预期：剩余命中只允许出现在——`apps/harmonyos`/`admin/apps/harmonyos` 的 `AppConfig.ets`（唯一出处）、`apps/flutter/platform/lib/app/services/api_service.dart:12` 与 `chat_service.dart` 的推导默认、`admin/apps/flutter/lib/app/services/api_service.dart:14`、`process.php`/`config/*.php` 的 `?: '默认'` 兜底、`install/index.php:665` 的兜底。逐条对不上就要解释。

- [x] **Step 2: PHP 两棵树全量**

```bash
cd /home/wwwroot/game-platform-php/service && php vendor/bin/phpunit --do-not-cache-result 2>&1 | tail -4
cd /home/wwwroot/game-platform-php/admin && php vendor/bin/phpunit --do-not-cache-result 2>&1 | tail -4
```

预期：两边 `Failures: 0, Errors: 0`；service 总数 = 本轮修复后基线 + 4，admin 同理。admin 的 0 failure **要求前置条件 5 已完成**（用户已把 `REDIS_CLUSTER_NODES` 补进真实 `admin/.env`）；若未完成，如实记录"仅 `EnvConfigTest` 1 例因活 `.env` 未同步而红"，并列为环境前置项，不得改测试凑绿。

- [x] **Step 3: 客户端四棵树构建/测试**

```bash
cd /home/wwwroot/game-platform-php/apps/react && ./node_modules/.bin/vite build --base=/app-react/ 2>&1 | tail -2
cd /home/wwwroot/game-platform-php/admin/apps/react && ./node_modules/.bin/vite build 2>&1 | tail -2
cd /home/wwwroot/game-platform-php/apps/angular && node node_modules/@angular/cli/bin/ng.js build --base-href=/app-angular/ 2>&1 | tail -3
cd /home/wwwroot/game-platform-php/admin/apps/angular && node node_modules/@angular/cli/bin/ng.js build 2>&1 | tail -3
cd /home/wwwroot/game-platform-php/apps/flutter/platform && flutter test --reporter compact 2>&1 | tail -2
cd /home/wwwroot/game-platform-php/admin/apps/flutter && flutter test --reporter compact 2>&1 | tail -2
```

预期：四条构建均成功；两条 Flutter 测试 `All tests passed!`。

- [x] **Step 4: 派独立验证子代理**

按契约派 `verification` 子代理，传：原始需求（"把当前项目所有启动端口或请求地址放到对应的配置文件中"，及三条范围裁决）＋ 本计划改动文件清单 ＋ 方法证据（上面各步命令与输出）＋ 本文件的"与设计的三处差异"。特别要求它核验：(a) PHP 默认值确实与旧字面量逐一相等（重跑 `ConfigDefaultsTest`）；(b) 两棵鸿蒙树 clean 构建不新增 ERROR、`AppConfig.ets` 是唯一出处；(c) 客户端产物与源码可重建（React 带 `--base=/app-react/`）；(d) docs 旧值 grep 断言成立。

- [x] **Step 5: 抽查验证者报告**

复跑它报告里 2-3 条命令（至少含一条 PHP 测试与一条构建），输出逐条对齐后收口；未对齐则退回验证者。

## 执行结果（2026-09-22 收口）

**独立验证：PASS。** verifier 子代理 read-only 核验，仓库零改动、零构建泄漏（untracked 48 个文件前后一致）。主会话抽查复核其报告：admin 套件 `190/437/1 failure/3 skipped` 逐字一致；两棵 `ConfigDefaultsTest.php` 均 `OK (4 tests, 10 assertions)` 且 md5 同为 `89bc646808833d5c3c2e59c7b0f5c08c`；admin 树 `AppConfig.ets` = `http://10.0.2.2:8789`、`entry/src` 内 `8792` 零命中；F1 的路由表论断经独立复核成立。

| 项 | 结果 |
|---|---|
| service PHPUnit | 273 tests / 701 assertions / 3 skipped，0 失败 |
| admin PHPUnit | 190 / 437 / 3 skipped，**0 失败**（收口时为 1 例红＝`EnvConfigTest` 缺 `REDIS_CLUSTER_NODES`；部署者已把该键补进活 `admin/.env`，复跑转绿，测试未改） |
| Angular ×2 | 两端 `Application bundle generation complete.`，exit 0 |
| React ×2 | `tsc -b` exit 0；`vite build` ✓ |
| Flutter C 端 / admin | `+3: All tests passed!` / `+10 -3`（3 例见 F2） |
| xiaoxiaole | `✓ built in 6.90s`，exit 0 |
| HarmonyOS C 端 / admin | 沙箱 clean 构建 BUILD SUCCESSFUL、0 ERROR / ArkTS 错误 HEAD 与工作区逐条相同（35 条），零新增 |
| 两参 `getenv()` 新增误用 | 新增行 grep 零命中 |
| 硬编码残留 | 无在范围字面量遗留；余项为配置文件自身、`?:` 兜底或设计显式排除（Angular `proxy.conf.json` 等） |

**验证者发现（均非本阶段引入，待你裁决）**

- **F1（已于 2026-09-22 落地）**：admin 树鸿蒙的 `GameHallPage`/`GameDetailPage` 请求 `/api/v1/game/*`，但 admin 的 `/api/v1` 组只注册了 captcha 与 auth（`admin/config/route.php:301-309`）——本阶段按裁决把 8792 改 8789 后，这两屏由"打到 C 端能通"变为 404。同一裁决同时修掉真问题：admin 的 `ApiService`/`LoginPage` 原指 8792，等于用管理端壳登录 C 端用户表。
  - 裁决：挂在 `/admin/v1` 下走 admin 鉴权（不放 `/api/v1`，那是无鉴权公开组）。admin 补 `GET /admin/v1/game/{hashid}` 详情与 `POST /admin/v1/game/launch` 试玩预览——后者刻意零用户侧写入（管理端身份只有 `adminId`，没有 C 端 `userId`，照搬 C 端 launch 会写出归属错误的 `game_play_log`）；客户端三处 URL 改指 `/admin/v1/game/*`。`list` 复用既有端点。
- **F2 / F3**：`admin/apps/flutter` 3 例失败、`admin/apps/harmonyos` 缺 `resources/base/media` 致 HEAD 即不可构建，均经 HEAD 对照证明先于本阶段。
- **F4**：新增解析器边界（`SCOUT_HOSTS='…,'` 产生空元素；`REDIS_PORT` 非数字 → 0）沿用 `PaymentController.php:64` 既有先例，未加校验。
- **F5**：工作区另含本阶段清单外改动（docker-compose / `PUBLIC_APP_URL` / `bootstrap.php` / 13 个 DEPLOYMENT 多语言重构 / 钱包兑换），属另一批工作，本阶段未验证。
- **F6**：验证者一次宽 grep 匹配到真实 `admin/.env` 并回显两行非敏感内容（`APP_URL`、`APP_PORT`）；无凭据泄露，已在其报告内主动披露。
