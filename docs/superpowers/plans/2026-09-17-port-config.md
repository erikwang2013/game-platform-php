# 端口/请求地址配置化 实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 把 admin/service 的监听端口、Docker 编排端口、Nginx upstream 端口、安装向导后台链接全部收敛到配置文件，改一个端口只改一处。

**Architecture:** 应用侧沿用仓库既有 `getenv('KEY') ?: '默认值'` 惯例（admin 已有 13 个配置文件这么做），端口写在各 app 的 `.env`；Docker 侧新根 `.env`（模板 `.env.example`）为单源，compose 用 `${VAR:-默认}` 插值并把值透传给容器内 `APP_PORT`（`environment` 优先于 `env_file`，容器监听端口 = 宿主映射端口，不可能对不上）；`nginx.conf` 改为 `nginx.conf.template`，走官方 nginx 镜像自带 envsubst 机制（`NGINX_ENVSUBST_OUTPUT_DIR=/etc/nginx`），不写自定义脚本。

**Tech Stack:** PHP 8.3 / webman v2（workerman）、vlucas/phpdotenv、Docker Compose、nginx:alpine 官方镜像模板机制。

**执行前提（重要）：**
1. Task E（安装向导进度条）的独立验证 agent 仍在后台运行，其验证对象包含 `install/index.php`。**等它出结论（PASS）后再开始本计划**，避免验证移动目标；若 FAIL 先修 Task E。
2. 项目规则：**仅用户要求时提交** —— 本计划不含 git commit 步骤，全部改完由用户决定。
3. 用户当前有一个 dev admin 实例在运行（`admin/start.php`，监听 8789）。编辑 `admin/config/*` 时该实例的 Monitor 进程会自动 reload worker（正常行为，master 已绑定的监听不变）；新端口配置需 `php start.php restart` 才生效，本计划不做。
4. 本机无 Redis；已实测 service 应用在本机可正常启动（8790/8791/8792 全部绑定成功），Task 5 的真实启动测试可行。MySQL 测试凭据在 `/tmp/dbtest.creds`（不进仓库）。

---

## 文件结构（改动的责任划分）

| 文件 | 责任 |
|------|------|
| `admin/.env.example` | 新增 `APP_PORT`；`APP_URL` 已有（当前是死键，本计划激活） |
| `service/.env.example` | 新增 `APP_PORT` / `LEADERBOARD_WS_PORT` / `CHAT_WS_PORT` |
| `admin/config/process.php` | webman 监听端口读 `APP_PORT` |
| `service/config/process.php` | HTTP + 两个 WS 监听端口读各自键 |
| `admin/config/app.php` | 新增 `url` 键读 `APP_URL`（修活 `DocsController.php:30` 的 `config('app.url')`） |
| `install/index.php` | 成功页后台链接读生成后的 `admin/.env` 的 `APP_URL` |
| `nginx.conf` → `nginx.conf.template` | upstream 端口用 `${ADMIN_PORT}` / `${SERVICE_PORT}` |
| `docker-compose.yml` | 全部端口 `${VAR:-默认}`；app 容器透传 `APP_PORT` 等 |
| `.env.example`（根，新建） | Docker 部署端口单源模板 |
| `admin/docs/nginx-security.conf` | 修容器内坏掉的 `proxy_pass`（1 行） |
| `service/config/process.php.bak` | 删除（残留备份，gitignore 中） |

---

### Task 1: 应用侧端口配置（admin + service）

**Files:**
- Modify: `admin/.env.example:17`
- Modify: `service/.env.example:2`
- Modify: `admin/config/process.php:28`
- Modify: `service/config/process.php:28,45,50`
- Modify: `admin/config/app.php:24`
- Test: `/tmp/portcheck/probe.php`（新建，沙箱探针）

- [ ] **Step 1: `admin/.env.example` 新增 APP_PORT**

在 `APP_URL=http://localhost:8789` 之后插入：

```diff
 # 应用URL
 APP_URL=http://localhost:8789
+# 监听端口（webman HTTP），与 APP_URL 保持一致
+APP_PORT=8789
```

- [ ] **Step 2: `service/.env.example` 新增三个端口键**

在 `APP_DEBUG=true` 之后插入：

```diff
 APP_ENV=local
 APP_DEBUG=true
+# HTTP 监听端口
+APP_PORT=8792
+# WebSocket 端口: 排行榜推送 / 聊天（须与前端连接地址一致）
+LEADERBOARD_WS_PORT=8790
+CHAT_WS_PORT=8791
```

- [ ] **Step 3: `admin/config/process.php` 读 APP_PORT**

```diff
     'webman' => [
         'handler' => Http::class,
-        'listen' => 'http://0.0.0.0:8789',
+        // 监听端口由 admin/.env 的 APP_PORT 配置，默认 8789
+        'listen' => 'http://0.0.0.0:' . (getenv('APP_PORT') ?: '8789'),
         'count' => 3,//cpu_count() * 4,
```

- [ ] **Step 4: `service/config/process.php` 读三个端口键**

```diff
     'webman' => [
         'handler' => Http::class,
-        'listen' => 'http://0.0.0.0:8792',
+        // 监听端口由 service/.env 的 APP_PORT 配置，默认 8792
+        'listen' => 'http://0.0.0.0:' . (getenv('APP_PORT') ?: '8792'),
         'count' => 3,//cpu_count() * 4,
```

```diff
     'leaderboard-ws' => [
         'handler' => app\process\LeaderboardWebSocket::class,
-        'listen' => 'websocket://0.0.0.0:8790',
+        // 端口由 service/.env 的 LEADERBOARD_WS_PORT 配置，默认 8790
+        'listen' => 'websocket://0.0.0.0:' . (getenv('LEADERBOARD_WS_PORT') ?: '8790'),
         'count' => 1,
     ],
     'chat-ws' => [
         'handler' => app\process\ChatWebSocket::class,
-        'listen' => 'websocket://0.0.0.0:8791',
+        // 端口由 service/.env 的 CHAT_WS_PORT 配置，默认 8791
+        'listen' => 'websocket://0.0.0.0:' . (getenv('CHAT_WS_PORT') ?: '8791'),
         'count' => 1,
     ],
```

- [ ] **Step 5: `admin/config/app.php` 新增 url 键**

在 `'default_timezone' => 'Asia/Shanghai',` 之后插入：

```diff
     'default_timezone' => 'Asia/Shanghai',
+    // 应用对外地址（API 文档 baseUrl 等），由 admin/.env 的 APP_URL 配置
+    'url' => getenv('APP_URL') ?: 'http://localhost:8789',
     'request_class' => Request::class,
```

- [ ] **Step 6: 写配置解析探针 `/tmp/portcheck/probe.php`**

以最小 stub 加载 webman 配置文件（不启动任何服务），完整内容：

```php
<?php
// 探针: 以最小 stub 加载 webman 配置文件，断言端口解析（不依赖起服务）
namespace support {
    class Log
    {
        public static function channel(string $name)
        {
            return null;
        }
    }
}

namespace {
    function app_path() { return '/tmp/portcheck/app'; }
    function config_path() { return '/tmp/portcheck/config'; }
    function base_path($suffix = '') { return '/tmp/portcheck' . $suffix; }
    function public_path() { return '/tmp/portcheck/public'; }

    $app = $argv[1];
    $cfg = require "/home/wwwroot/game-platform-php/{$app}/config/process.php";
    foreach (['webman', 'leaderboard-ws', 'chat-ws'] as $k) {
        if (isset($cfg[$k]['listen'])) {
            echo $k . '=' . $cfg[$k]['listen'] . "\n";
        }
    }
}
```

- [ ] **Step 7: 运行探针（默认值）**

```bash
mkdir -p /tmp/portcheck && php /tmp/portcheck/probe.php admin
```

Expected:
```
webman=http://0.0.0.0:8789
```

```bash
php /tmp/portcheck/probe.php service
```

Expected:
```
webman=http://0.0.0.0:8792
leaderboard-ws=websocket://0.0.0.0:8790
chat-ws=websocket://0.0.0.0:8791
```

- [ ] **Step 8: 运行探针（自定义端口）**

```bash
APP_PORT=19001 php /tmp/portcheck/probe.php admin
```

Expected: `webman=http://0.0.0.0:19001`

```bash
APP_PORT=19012 LEADERBOARD_WS_PORT=19010 CHAT_WS_PORT=19011 php /tmp/portcheck/probe.php service
```

Expected:
```
webman=http://0.0.0.0:19012
leaderboard-ws=websocket://0.0.0.0:19010
chat-ws=websocket://0.0.0.0:19011
```

- [ ] **Step 9: 验证 app.php 的 url 键 + 语法**

```bash
APP_URL=https://admin.example.com:8443 php -r '
function app_path(){return "/tmp";} function config_path(){return "/tmp";} function base_path($s=""){return "/tmp".$s;} function public_path(){return "/tmp";}
$c = require "/home/wwwroot/game-platform-php/admin/config/app.php";
echo $c["url"], PHP_EOL;'
```

Expected: `https://admin.example.com:8443`；不带 `APP_URL=` 前缀再跑一次 Expected `http://localhost:8789`。

```bash
php -l admin/config/process.php && php -l service/config/process.php && php -l admin/config/app.php
```

Expected: 三行 `No syntax errors detected`。

---

### Task 2: 安装向导成功页链接读 APP_URL

**Files:**
- Modify: `install/index.php:642-646`（新增 `envConfigValue()` 函数 + 改 `$nextUrl`）
- Test: `/tmp/instsim2/run_e2e.sh`（沙箱 E2E，扩展 2 处）

- [ ] **Step 1: `install/index.php` 新增读取函数**

在 `function step5Page(array $result): string` 之前插入：

```php
/**
 * 读取生成的 .env 中的配置值（安装成功页展示用）；文件不存在或键缺失时返回空串
 */
function envConfigValue(string $file, string $key): string
{
    if (!is_file($file)) {
        return '';
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/', $line, $m)) {
            return trim($m[1], " \t\"'");
        }
    }
    return '';
}
```

- [ ] **Step 2: `install/index.php` 改 $nextUrl**

```diff
 function step5Page(array $result): string
 {
     $stepsHtml = renderSteps($result['steps']);
     $indicator = stepIndicator(5);
-    $nextUrl = 'http://localhost:8789';
+    // 后台地址跟随生成的 admin/.env 的 APP_URL（用户在模板里改域名/端口，向导链接同步）
+    $nextUrl = envConfigValue(dirname(__DIR__) . '/admin/.env', 'APP_URL') ?: 'http://localhost:8789';
```

- [ ] **Step 3: 扩展沙箱 E2E `/tmp/instsim2/run_e2e.sh`（沙箱副本，非仓库文件）**

在第 26 行（`cp -r $SRC/install/assets ...` 那一行）之后插入：

```bash
sed -i 's|^APP_URL=.*|APP_URL=http://127.0.0.1:19999|' $SB/admin/.env.example
```

在第 105 行（`t $SB/ndjson.txt '"key":"step_lock"' "末步 key=step_lock"`）之后插入：

```bash
t $SB/ndjson.txt 'http://127.0.0.1:19999' "成功页链接跟随 admin/.env 的 APP_URL"
```

- [ ] **Step 4: 跑 E2E**

```bash
bash /tmp/instsim2/run_e2e.sh
```

Expected: 新增一行 `PASS 成功页链接跟随 admin/.env 的 APP_URL`，末尾 `E2E: ALL PASS`（沙箱内 admin/.env 与模板的逐字节比对仍通过——sed 改的是沙箱模板，安装从模板生成 .env，两侧一致）。

---

### Task 3: 部署编排（compose + nginx 模板 + 根 .env.example）

**Files:**
- Rename: `nginx.conf` → `nginx.conf.template`（`git mv`）
- Modify: `nginx.conf.template:20,25`
- Rewrite: `docker-compose.yml`（全文如下）
- Create: `.env.example`（根）

- [ ] **Step 1: 重命名 nginx 配置**

```bash
git mv nginx.conf nginx.conf.template
```

- [ ] **Step 2: `nginx.conf.template` 两处 upstream 改插值**

```diff
     # 管理后台 API
     upstream admin_api {
-        server admin:8789;
+        server admin:${ADMIN_PORT};
     }
 
     # C端业务 API
     upstream service_api {
-        server service:8792;
+        server service:${SERVICE_PORT};
     }
```

（文件其余 `$host`、`$remote_addr` 等 nginx 变量不受官方镜像 envsubst 影响——它只替换容器环境里存在的变量名。）

- [ ] **Step 3: 重写 `docker-compose.yml` 为以下完整内容**

```yaml
version: '3.8'

# ============================================================
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
# 全球游戏聚合平台 — Docker Compose 完整部署
# 端口全部来自根目录 .env（模板 .env.example）：cp .env.example .env
# 应用端口由 ADMIN_PORT 等透传为容器内 APP_PORT，监听/映射/Nginx 三处一致
# ============================================================

services:
  # Nginx 反向代理
  nginx:
    image: nginx:alpine
    container_name: game-platform-nginx
    environment:
      # 模板渲染 upstream 端口（官方镜像 envsubst 机制，输出到 /etc/nginx/nginx.conf）
      - ADMIN_PORT=${ADMIN_PORT:-8789}
      - SERVICE_PORT=${SERVICE_PORT:-8792}
      - NGINX_ENVSUBST_OUTPUT_DIR=/etc/nginx
    ports:
      - "${NGINX_HTTP_PORT:-80}:80"
      - "${NGINX_HTTPS_PORT:-443}:443"
    volumes:
      - ./nginx.conf.template:/etc/nginx/templates/nginx.conf.template:ro
      - ./admin/public:/var/www/admin/public:ro
      - ./apps/flutter/platform/build/web:/var/www/platform:ro
      - ./ssl:/etc/nginx/ssl:ro
    depends_on:
      - admin
      - service
    restart: unless-stopped

  # 管理后台 (默认端口 8789，根 .env 的 ADMIN_PORT 可改)
  admin:
    build:
      context: ./admin
      dockerfile: Dockerfile
    container_name: game-platform-admin
    environment:
      # 容器内监听端口 = 宿主映射端口（environment 优先于 env_file）
      - APP_PORT=${ADMIN_PORT:-8789}
    ports:
      - "${ADMIN_PORT:-8789}:${ADMIN_PORT:-8789}"
    volumes:
      - ./admin:/app
      - ./common:/common
    env_file:
      - ./admin/.env
    depends_on:
      - mysql
      - redis
    restart: unless-stopped

  # C端业务端 (默认端口 8792，根 .env 的 SERVICE_PORT 可改)
  service:
    build:
      context: ./service
      dockerfile: Dockerfile
    container_name: game-platform-service
    environment:
      - APP_PORT=${SERVICE_PORT:-8792}
      - LEADERBOARD_WS_PORT=${LEADERBOARD_WS_PORT:-8790}
      - CHAT_WS_PORT=${CHAT_WS_PORT:-8791}
    ports:
      - "${SERVICE_PORT:-8792}:${SERVICE_PORT:-8792}"
    volumes:
      - ./service:/app
      - ./common:/common
    env_file:
      - ./service/.env
    depends_on:
      - mysql
      - redis
      - elasticsearch
    restart: unless-stopped

  # WebSocket 排行榜/聊天 (默认端口 8790/8791，根 .env 可改)
  leaderboard-ws:
    build:
      context: ./service
      dockerfile: Dockerfile
    container_name: game-platform-ws
    command: php start.php start
    environment:
      - APP_PORT=${SERVICE_PORT:-8792}
      - LEADERBOARD_WS_PORT=${LEADERBOARD_WS_PORT:-8790}
      - CHAT_WS_PORT=${CHAT_WS_PORT:-8791}
    ports:
      - "${LEADERBOARD_WS_PORT:-8790}:${LEADERBOARD_WS_PORT:-8790}"
      - "${CHAT_WS_PORT:-8791}:${CHAT_WS_PORT:-8791}"
    volumes:
      - ./service:/app
      - ./common:/common
    env_file:
      - ./service/.env
    depends_on:
      - mysql
      - redis
    restart: unless-stopped

  # MySQL 8.0
  mysql:
    image: mysql:8.0
    container_name: game-platform-mysql
    ports:
      - "${MYSQL_PORT:-3306}:3306"
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD:-root}
      MYSQL_DATABASE: ${DB_DATABASE:-game-platform}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./install/install.sql:/docker-entrypoint-initdb.d/install.sql:ro
    command: --default-authentication-plugin=mysql_native_password --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
    restart: unless-stopped

  # Redis 7
  redis:
    image: redis:7-alpine
    container_name: game-platform-redis
    ports:
      - "${REDIS_PORT:-6379}:6379"
    volumes:
      - redis_data:/data
    command: redis-server --appendonly yes
    restart: unless-stopped

  # Elasticsearch 8
  elasticsearch:
    image: elasticsearch:8.11.0
    container_name: game-platform-es
    ports:
      - "${ES_PORT:-9200}:9200"
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
      - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
    volumes:
      - es_data:/usr/share/elasticsearch/data
    restart: unless-stopped

volumes:
  mysql_data:
  redis_data:
  es_data:
```

- [ ] **Step 4: 新建根 `.env.example`**

```bash
# ============================================================
# 全球游戏聚合平台 — Docker 部署环境变量模板
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
#
# 使用方法: 复制为 .env（docker compose 自动读取），按需修改端口
#   cp .env.example .env
#
# 说明: 仅 Docker 部署使用本文件；裸机部署改 admin/.env、service/.env
# ADMIN_PORT 等会透传为容器内 APP_PORT，应用监听、端口映射、
# Nginx upstream 三处始终一致，改端口只需改这一处
# ============================================================

# ── Nginx 对外端口（宿主侧） ──
NGINX_HTTP_PORT=80
NGINX_HTTPS_PORT=443

# ── 应用端口（容器内监听 = 宿主映射） ──
ADMIN_PORT=8789
SERVICE_PORT=8792
LEADERBOARD_WS_PORT=8790
CHAT_WS_PORT=8791

# ── 数据服务端口（宿主侧） ──
MYSQL_PORT=3306
REDIS_PORT=6379
ES_PORT=9200

# ── MySQL 初始化（首次启动建库用） ──
DB_PASSWORD=root
DB_DATABASE=game-platform
```

- [ ] **Step 5: 校验 compose 插值（默认值）**

```bash
cd /home/wwwroot/game-platform-php && docker compose config > /tmp/portcheck/compose_default.yml 2>/tmp/portcheck/compose_default.err; echo "exit=$?"; grep -c '\${' /tmp/portcheck/compose_default.yml
```

Expected: `exit=0`；grep 输出 `0`（无未解析变量）。

- [ ] **Step 6: 校验 compose 插值（自定义端口走通全链路）**

```bash
cd /home/wwwroot/game-platform-php && ADMIN_PORT=19001 SERVICE_PORT=19002 LEADERBOARD_WS_PORT=19010 CHAT_WS_PORT=19011 NGINX_HTTP_PORT=8080 docker compose config | grep -E '"(19001|19002|19010|19011|8080)"'
```

Expected: 输出包含 `published: "19001"`、`target: 19001`（admin 两处）、19002、19010、19011、8080 等行，全部为自定义值。

- [ ] **Step 7: 校验 nginx 模板渲染（替换 + 语法）**

```bash
cd /home/wwwroot/game-platform-php && docker run --rm \
  -v "$PWD/nginx.conf.template:/etc/nginx/templates/nginx.conf.template:ro" \
  -e ADMIN_PORT=19001 -e SERVICE_PORT=19002 -e NGINX_ENVSUBST_OUTPUT_DIR=/etc/nginx \
  --entrypoint sh nginx:alpine -c '/docker-entrypoint.d/20-envsubst-on-templates.sh && grep -E "server (admin|service):" /etc/nginx/nginx.conf'
```

Expected:
```
server admin:19001;
server service:19002;
```

```bash
cd /home/wwwroot/game-platform-php && docker run --rm \
  -v "$PWD/nginx.conf.template:/etc/nginx/templates/nginx.conf.template:ro" \
  -e ADMIN_PORT=8789 -e SERVICE_PORT=8792 -e NGINX_ENVSUBST_OUTPUT_DIR=/etc/nginx \
  nginx:alpine nginx -t
```

Expected: `syntax is ok` + `test is successful`（首次运行会拉取 nginx:alpine 镜像）。

---

### Task 4: 残留清理

**Files:**
- Modify: `admin/docs/nginx-security.conf:78`
- Delete: `service/config/process.php.bak`

- [ ] **Step 1: 修 `admin/docs/nginx-security.conf` 的 proxy_pass**

该文件被 `admin/docker-compose.yml` 挂载为 nginx 真实配置，容器内 `127.0.0.1` 指向 nginx 自己，必然 502；compose 中应用服务名为 `app`：

```diff
 # --- 反向代理到 webman ---
 location / {
-    proxy_pass http://127.0.0.1:8789;
+    proxy_pass http://app:8789;
```

- [ ] **Step 2: 删除残留备份**

该文件在 `.gitignore:32:*.bak` 内（未跟踪），直接删除：

```bash
rm /home/wwwroot/game-platform-php/service/config/process.php.bak
```

- [ ] **Step 3: 验证**

```bash
cd /home/wwwroot/game-platform-php && grep -n 'proxy_pass' admin/docs/nginx-security.conf && ls service/config/process.php.bak 2>&1
```

Expected: `78:    proxy_pass http://app:8789;` 和 `ls: cannot access 'service/config/process.php.bak': No such file or directory`。

---

### Task 5: 回归与综合验证

**Files:** 无新改动；验证范围 = 全部改动文件。

- [ ] **Step 1: service 真实启动验证自定义端口（本机实测可行）**

```bash
cd /home/wwwroot/game-platform-php/service
APP_PORT=19012 LEADERBOARD_WS_PORT=19010 CHAT_WS_PORT=19011 php start.php start > /tmp/portcheck/service_boot.log 2>&1 &
SVPID=$!
for i in $(seq 1 20); do ss -ltn | grep -q ':19012' && break; sleep 0.5; done
ss -ltn | grep -E ':(19010|19011|19012)\b'
kill $SVPID
sleep 2
ss -ltn | grep -E ':(19010|19011|19012)\b' || echo "stopped OK"
```

Expected: 三行 LISTEN（19010/19011/19012），随后 `stopped OK`。若进程未退净：`php start.php stop`。（shell 传入的 env 优先于 .env——`createUnsafeImmutable` 不覆盖既有环境变量。）

- [ ] **Step 2: 重跑浏览器套件（Task E 无回归 + 成功页新链接渲染）**

```bash
bash /tmp/pwtest/run_browser_test.sh
```

Expected: `BROWSER: ALL PASS`（34 项，含弹框进度、原生回退、localStorage 清理、已安装页拦截）。

- [ ] **Step 3: 最终硬编码审计**

```bash
cd /home/wwwroot/game-platform-php && grep -rn --include='*.php' --include='*.yml' --include='*.conf' -E '\b(8789|8790|8791|8792)\b' admin/config service/config docker-compose.yml nginx.conf.template install/index.php admin/docs/nginx-security.conf admin/Dockerfile service/Dockerfile
```

Expected 剩余项（全部是"默认值/回退"，即配置化后的合法形态）：
- `admin/config/process.php`：`?: '8789'`
- `service/config/process.php`：`?: '8792'`、`?: '8790'`、`?: '8791'`
- `admin/config/app.php`：`?: 'http://localhost:8789'`
- `install/index.php`：`?: 'http://localhost:8789'`（`.env` 缺失时的回退）
- `docker-compose.yml`：各 `${VAR:-默认值}` 的默认值
- `admin/config/plugin/erikwang2013/apidoc/app.php:8` 与 `service/config/plugin/.../apidoc/app.php:8`：注释（不参与运行，保留）
- `admin/Dockerfile:58` / `service/Dockerfile:57`：`EXPOSE`（镜像元数据，默认端口，保留）
- `admin/docs/nginx-security.conf:78`：`http://app:8789`（容器内固定端口，与 admin/docker-compose.yml 的 internal 端口一致）

任何此列表之外的新命中 = 遗漏，需处理。

- [ ] **Step 4: 独立验证 agent**

按契约派 `verification` 子代理：传原始需求（"所有启动端口或请求地址放到对应的配置文件中"，范围=后端+部署编排）、全部改动文件清单（Task 1-4）、方法与证据（探针输出、compose config、nginx 模板测试、E2E、浏览器套件）。FAIL 则修复后重验，PASS 后抽查 2-3 条命令复跑。

---

## Self-Review

- **Spec coverage**：后端运行时端口（Task 1）✓；安装向导链接与死 APP_URL（Task 1 Step 5 + Task 2）✓；compose 全端口（Task 3 Step 3）✓；nginx upstream（Task 3 Step 2）✓；两个残留（Task 4）✓；不改：前端 apps、tests、docs 端口表（用户选定范围外），运行中的注释类残留已在 Task 5 Step 3 显式列出并说明保留理由。
- **Placeholder scan**：无 TBD/TODO；每步含完整代码与预期输出。
- **Type consistency**：键名全程一致——`APP_PORT` / `LEADERBOARD_WS_PORT` / `CHAT_WS_PORT`（app 侧）与 `ADMIN_PORT` / `SERVICE_PORT` / `LEADERBOARD_WS_PORT` / `CHAT_WS_PORT`（根 .env 侧）；`envConfigValue()` 在 Task 2 定义并只在该任务使用；`NGINX_ENVSUBST_OUTPUT_DIR` 在 compose 与验证命令中一致。
- **已知取舍**：`nginx.conf.template` 的 80/443 是容器内固定端口（宿主侧 `NGINX_HTTP(S)_PORT` 可配），符合 Docker 常规；`admin/docker-compose.yml`（子项目编排）不改，其容器间用固定 8789（Task 4 已修 proxy_pass 指到 `app`）。
