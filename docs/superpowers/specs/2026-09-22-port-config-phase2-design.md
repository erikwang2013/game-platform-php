# 端口/请求地址配置化 · 第二阶段设计

> **需求（用户原话）**：「把当前项目所有启动端口或请求地址放到对应的配置文件中」
>
> **范围裁决（2026-09-22，用户经 AskUserQuestion 确认）**
> 1. **只集中硬编码** —— 把散落的字面量搬进各端既有配置文件，默认值与运行行为不变；**不**引入环境变量层/多环境分层。
> 2. **`admin/apps/harmonyos` 一并改正** —— 那 6 处地址现指向 8792（C 端端口），而该树调用 `/admin/v1/*`（仅 admin 8789 注册），必然 404；集中时把值改成 8789。
> 3. **文档只修错** —— 只改事实性错误与与代码不一致处；不做 14 语言 × 数百处示例端口的机械替换。

---

## 1. 与上一阶段（2026-09-17）的边界

上一份计划 `docs/superpowers/plans/2026-09-17-port-config.md` 已处理：admin/service 监听端口（`APP_PORT` / `LEADERBOARD_WS_PORT` / `CHAT_WS_PORT`）、Docker 编排单源（根 `.env.example` → compose `${VAR:-默认}` → `nginx.conf.template` envsubst 渲染）、安装向导成功页链接。其 Self-Review 明确写着「不改：前端 apps、tests、docs 端口表」。

该计划内容已由 commit `8ff4e87` 落地，但文件内 27 个勾选框仍是空的（本阶段要修的「文档与事实不一致」之一）。

**本阶段 = 上述「范围外」的剩余部分 + 当时未列入的运行时旁路（session Redis、scout ES）。**

证据来源：只读调研 `/tmp/port-inventory.md`（286 行，2026-09-22；清单已按本节表格落入设计，不依赖该临时文件）。

## 2. 已经配置化、本阶段不动

- webman 监听：`APP_PORT`（service/admin）、`LEADERBOARD_WS_PORT`、`CHAT_WS_PORT`（`config/process.php` + `.env.example`）
- MySQL/Redis：`config/database.php`、`config/redis.php` 的 `getenv(...) ?: 默认`
- 对外地址：`SITE_URL`（18 个支付网关）、`APP_URL`/`PUBLIC_APP_URL`（`admin/config/app.php` → `DocsController` baseUrl）
- Docker 链：根 `.env.example` 单源 → compose 插值 → nginx 模板渲染（上一阶段成果）
- CORS：`CORS_ORIGIN`（service 侧已有键）

## 3. 本阶段改动清单（按树）

### 3.1 service（PHP）

| file:line | 现状 | 落点 | 默认值变化 |
|---|---|---|---|
| `service/config/session.php:34-35` | `'host' => '127.0.0.1', 'port' => 6379`（当前 `type=file`，休眠） | `getenv('REDIS_HOST') ?: '127.0.0.1'` / `(int)(getenv('REDIS_PORT') ?: 6379)` | 不变 |
| `service/config/session.php:41` | `['127.0.0.1:7000','127.0.0.1:7001','127.0.0.1:7001']`（休眠；7001 重复两遍） | `explode(',', getenv('REDIS_CLUSTER_NODES') ?: '127.0.0.1:7000,127.0.0.1:7001')` | 去掉重复项（等价） |
| `service/config/plugin/erikwang2013/webman-scout/app.php:265-267` | `'hosts' => ['http://127.0.0.1:9200']` 纯数组（service 用 opensearch 驱动，未踩到） | `explode(',', getenv('SCOUT_HOSTS') ?: 'http://127.0.0.1:9200')` | 不变 |
| `service/.env.example:156` | `SCOUT_HOSTS=http://localhost:9200`（死键，代码从不读） | 激活并加注释说明被读取 | 不变 |
| `service/.env.example:169` | `OPENSEARCH_HTTP_HOST=http://localhost:37831`（37831 全仓无来源；admin 与 compose ES 均为 9200） | 改 `http://127.0.0.1:9200` | **事实修正** |

### 3.2 admin（PHP）

| file:line | 现状 | 落点 |
|---|---|---|
| `admin/config/session.php:34-35`、`:41` | 同 service（含 7001 重复） | 同 3.1 前两行 |
| `admin/config/plugin/erikwang2013/webman-scout/app.php:265-267` | ES hosts 纯数组，**且是生效路径**（`SCOUT_DRIVER=elasticsearch`） | 同 3.1 第三行 |
| `admin/.env.example` | 缺 `CORS_ORIGIN` 键，但 `admin/app/middleware/Cors.php:19,29` 在读它 | 补 `CORS_ORIGIN=*`（补文档，行为不变） |

### 3.3 apps/react

`vite.config.ts:8`（`port: 5173`）与 `:11`（proxy target `http://localhost:8792`）→ 提成文件顶部具名常量 `DEV_PORT` / `API_TARGET`（值不变）。
`src/lib/api.ts:10` 的 `const BASE = '/api/v1'` **不动**：同源相对前缀、单点常量，设计如此（产物靠 nginx 同源代理）。

### 3.4 apps/angular

**无改动。** `proxy.conf.json:3` 与 `angular.json:64` 本身就是「对应的配置文件」；`api.service.ts:132` 是相对前缀。
**不引入** `src/environments/*.ts`——当前没有任何绝对地址需要它承载（YAGNI；将来出现多环境需求再说）。

### 3.5 apps/flutter/platform

`lib/app/services/chat_service.dart:25`：`'$scheme://${baseUri.host}:8791'`（8791 无任何覆盖入口，且丢掉了 baseUrl 的端口）→ 新增 `String.fromEnvironment('CHAT_WS_BASE_URL')`；为空时**退回现有推导**（默认行为逐字节不变）。
`lib/app/services/api_service.dart:12` 已有 `API_BASE_URL` 机制（默认 `http://localhost:8792`），不动。

### 3.6 apps/harmonyos

新建 `entry/src/main/ets/common/AppConfig.ets`：`export const API_BASE_URL = 'http://10.0.2.2:8792';`（保留模拟器/真机注释）；`service/ApiService.ets:12` 改为从它 import。全树当前仅此 1 处字面量。

### 3.7 admin/apps/react

`vite.config.ts:4`（`const TARGET = 'http://localhost:8789'`，4 条规则共用）已是具名常量；把 `:10` 的 `port: 5273` 同样提成常量。值不变。

### 3.8 admin/apps/angular

**无改动**（同 3.4 理由）。

### 3.9 admin/apps/flutter

`lib/app/pages/dashboard/dashboard_controller.dart:12` 自建 `Dio(BaseOptions(baseUrl: 'http://localhost:8789'))`，是 49 处调用里唯一绕过 `ApiService.baseUrl` 单例的地方 → 改用单例。默认值不变，恢复 `--dart-define=API_BASE_URL` 可覆盖。

### 3.10 admin/apps/harmonyos（最乱的一棵）

新建 `entry/src/main/ets/common/AppConfig.ets`：`export const API_BASE_URL = 'http://10.0.2.2:8789';`（**值改正**：原 8792）。6 处字面量全部改为从它读取：

- `service/ApiService.ets:12`（常量）、`:82`（`/api/v1/auth/refresh` 拼 base —— 路径保留，admin 侧也注册了 `/api/v1` 认证）
- `pages/LoginPage.ets:13`（自建重复常量）、`:61`（captcha）、`:124`（login）
- `pages/GameHallPage.ets:25`（内联裸 URL）
- `pages/GameDetailPage.ets:38,68`（内联裸 URL）

### 3.11 测试基座

`tests/api/admin_test.php:16,36` 的 `$redis->connect('127.0.0.1', 6379)` → `getenv('REDIS_HOST') ?: '127.0.0.1'` / `(int)(getenv('REDIS_PORT') ?: 6379)`（默认不变）。
`tests/api/harness.php:18`、`tests/api/service_test.php:12` 已支持 env 覆盖 + 默认兜底 → 不动。

### 3.12 小游戏 dev 端口

`game/xiaoxiaole/vite.config.ts:6` 的 `port: 5173` 与 `apps/react/vite.config.ts:8` 撞车 → 让开到 `5175`。

### 3.13 文档（只修错）

| 位置 | 问题 | 处理 |
|---|---|---|
| `docs/test-reports/api.md:5,114,195` | 称 service 端口为 8795/8796/8797、README 为 8787/8788（迁移前旧值） | 改为 8792 / 8789+8792 |
| `docs/superpowers/plans/2026-09-17-port-config.md` | 27 个勾选框全空，但内容已由 `8ff4e87` 落地 | 勾选并注明落地 commit |

## 4. 验证策略

- **PHP**：全量 `cd service && php vendor/bin/phpunit --do-not-cache-result` 保持绿；新增一个 **DB-free 配置默认值回归测试**（env 未设时，session/scout 解析结果与旧字面量逐一相等），把上一阶段的 /tmp 探针做法落成正式测试。
- **HarmonyOS 两棵**：用 `/tmp/hm-check` 既有配方（本地 file: 包 + 4 处环境修补）做 clean 构建，断言 **0 ERROR**；并用 grep 断言全树（含管理台那棵）再无 `10.0.2.2:87` 以外的字面量、且 `AppConfig.ets` 是唯一出处。
- **Flutter 两棵**：`flutter test --reporter compact` + `flutter analyze`；断言未传新 define 时 chat WS 推导与旧值一致。
- **React/Angular 四棵**：`vite build` / `ng build` + `tsc`；`apps/react` 必须 `--base=/app-react/`（否则与仓库既有产物约定不一致）。
- **文档**：grep 断言旧值（8795/8796/8797/8787/8788）不再出现于 `docs/test-reports/`。
- **收口**：按契约派独立验证子代理（传原始需求 + 全部改动文件 + 方法证据），PASS 后抽查 2-3 条命令复跑。

## 5. 明确不做

- Docker 构建接线 `--dart-define`（Flutter web 产物会烘死默认值；正确值取决于公开域名，属部署决策）→ 本阶段既不接线也不新增文档说明（`String.fromEnvironment` 的用法在源码里自明）；
- Angular `environments/*.ts`；相对前缀 `/api/v1` 与构建 base `--base=/app-*`；
- `nginx.conf.template` 容器内 `listen 80`、两个 `Dockerfile` 的 `EXPOSE`（容器内固定值，与宿主映射配对成立）；
- `NGINX_HTTPS_PORT`（预留未接线的死键，属另一件事）；
- `service/.env.example` 的 ClickHouse 死键（无任何 PHP 读取点，`clickhouse-php` 插件目录为空）；
- `admin/.env.docker:8` 的 `APP_URL=http://localhost`（服务于 `admin/docker-compose.yml` 的 nginx:80 → app:8789 形态，无端口可能是对的，语义待确认）；
- `admin/docker-compose.yml`（子项目编排，上一阶段已决定不动；其 `:98` healthcheck 同此）；
- apidoc 插件配置注释里的地址（纯注释不参与运行，上一阶段已决定保留）；
- 14 语言文档里的示例端口字面量（用户裁决：只修错）。

## 6. 实施顺序

1. 本设计获批 → 用 writing-plans 出实现计划；
2. **等当前「修复轮」（5 个 agent + 独立验证）收口后再开工**——避免与 `docs-config` 对 README/docs 的写入撞车；
3. 按树实施：PHP → 客户端树 → 测试基座 → 文档；
4. 独立验证 + 抽查后收口。全程**不 commit**（仓库现有未提交改动属本工作流，由用户决定提交时机）。
