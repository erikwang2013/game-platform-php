# Open Admin — Design Document
<!-- lang-nav -->

Languages: [中文](DESIGN.md) · **English** · [한국어](DESIGN.ko.md) · [Русский](DESIGN.ru.md) · [Deutsch](DESIGN.de.md) · [Français](DESIGN.fr.md) · [Español](DESIGN.es.md) · [Português](DESIGN.pt.md) · [हिन्दी](DESIGN.hi.md) · [العربية](DESIGN.ar.md) · [বাংলা](DESIGN.bn.md) · [Bahasa Indonesia](DESIGN.id.md) · [日本語](DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> For detailed Mermaid architecture diagrams, see [ARCHITECTURE.en.md](ARCHITECTURE.en.md) (auto-rendered in GitHub/GitLab/VS Code).

## 1. System Architecture

> **Feature list**: auth (login/register/refresh/logout + account lockout + session limit) | dashboard (Redis cache) | user CRUD + batch + import | roles & permissions (RBAC) | system config | operation audit (8 platform sources) | files (upload + export + masking) | security (18-layer defense) | operations (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Backend Architecture

### 2.1 Layered Design

| Layer | Directory | Responsibility |
|---|------|------|
| Routing | `config/route.php` | URL-to-controller mapping, middleware binding, versioned routes |
| Middleware | `app/middleware/` | Attack blocking (SecurityFilter), rate limiting (RateLimit), auth (JWT), authorization (RBAC), API version (ApiVersion) |
| Controllers | 30: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs/Metrics/Analytics/Game/Payment/Withdraw... (admin) + Captcha/Auth (API v1) | Request parameter validation, business logic invocation, response formatting |
| Business services | `common/service/` | Data analytics: GameDashboardService (overview/rankings/trends), DepositLogService (revenue/conversion), ProbabilityService (joint/conditional probability, SQL builder); returns empty data instead of errors on DB failure |
| Data models | `app/model/` | ORM mapping, relations, field encryption/decryption |
| Common utilities | `app/common/` | Hashids, Snowflake, Encryption services |

### 2.2 Request Lifecycle

```
客户端请求
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 匹配
  │
  ▼
中间件链:
  SecurityFilter ──────► HTTP方法检查 → 405 (仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截 (403)
  ▼
  RateLimit ───────────► Redis 滑动窗口限流
  │ (失败返回 429 + Retry-After 头)
  ▼
  ApiVersion ─────────► API-Version 头校验，注入 $request->apiVersion
  │ (失败返回 400)
  ▼
  AdminAuth ──────────► JWT 验证，注入 $request->adminId
  │ (失败返回 401)
  ▼
  AdminPermission ────► RBAC 权限校验（Redis 60s 缓存）
  │ (失败返回 403)
  ▼
  OperationLog ───────► 操作日志记录 (POST/PUT/DELETE)，自动检测来源端
  │
  ▼
Controller::method()
  │
  ├─► 参数验证 (validator)
  ├─► 敏感操作确认 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 操作 (自动 encryptable 加解密)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 ID Lifecycle

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Data Encryption System

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-128-ECB，独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. Database Design

### 3.1 ER Relationships

```
erik_admin_user ──┬── erik_admin_user_role ──┬── erik_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    erik_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    erik_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           erik_operation_log
             (操作日志)

erik_system_config (系统配置) — 独立表
```

### 3.2 Core Table Structures

| Table | Field count | Description |
|------|-------|------|
| `erik_admin_user` | 14 | Admin users, phone/email/id_card stored encrypted, supports soft delete |
| `erik_admin_role` | 7 | Roles, slug unique |
| `erik_admin_permission` | 10 | Permission tree (parent_id self-reference), type: 1=menu 2=button 3=API |
| `erik_admin_user_role` | 2 | User-role many-to-many junction table |
| `erik_admin_role_permission` | 2 | Role-permission many-to-many junction table |
| `erik_system_config` | 8 | Key-value config, group+key composite unique |
| `erik_operation_log` | 9 | Operation audit log (incl. source) |

### 3.3 Primary Key Conventions

- Type: `BIGINT UNSIGNED NOT NULL`
- Characteristic: **non-auto-increment**, generated at the application layer by the Snowflake algorithm
- Advantages: globally unique, distributed-friendly, trend-incremental for index friendliness, does not expose business volume
- Config: datacenter_id(0-31) + worker_id(0-31), supports 1024 concurrent nodes

## 4. API Design

### 4.1 URL Conventions

```
公开接口:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

管理端:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

资源路由:
  GET    /admin/user          → 列表
  POST   /admin/user          → 创建
  GET    /admin/user/{hashid} → 详情
  PUT    /admin/user/{hashid} → 更新
  DELETE /admin/user/{hashid} → 删除（需密码确认）

系统配置:  /admin/config[/{hashid}]
操作日志:  /admin/log
个人中心:  /admin/profile[/password|/logout]
导入:     /admin/import/users
上传:     /admin/upload
批量:     /admin/user/batch/{destroy|status}
文档:     /api/docs     (OpenAPI 3.0)
健康:     /health
```

### 4.2 API Version Strategy

API versions are controlled via a request header, **not reflected in the URL path**:

```http
API-Version: v1
```

| Mechanism | Description |
|------|------|
| Default version | Defaults to `v1` when the `API-Version` header is absent |
| Validation | Validated by the `ApiVersion` middleware, unsupported versions return 400 |
| Routing | The `v()` helper resolves the controller class dynamically based on version |
| Directory | Controllers organized by version: `app/api/{version}/controller/` |

Extension example — adding a v2 API:
1. Create `app/api/v2/controller/AuthController.php`
2. Add `'v2'` to the `SUPPORTED` constant of the `ApiVersion` middleware
3. No route definition changes needed

```bash
# Use v1
curl -H "API-Version: v1" /api/auth/login

# Use v2
curl -H "API-Version: v2" /api/auth/login

# No header, defaults to v1
curl /api/auth/login
```

### 4.3 Rate Limit Strategy

Based on the Redis Sorted Set sliding window algorithm, executed atomically with Lua scripts:

| Endpoint | Limit |
|------|------|
| Default | 60/min/IP/route |
| POST /api/auth/login | 10/min |
| POST /api/auth/register | 5/min |

Exceeding the limit returns 429, with X-RateLimit-Limit / Remaining / Reset / Retry-After response headers.

### 4.4 Unified Response

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Meaning | Trigger scenario |
|------|------|---------|
| 0 | Success | Normal response |
| 400 | Parameter error | Incorrect request format |
| 401 | Not authenticated | Token missing/expired/invalid |
| 403 | No permission | User roles do not include the required permission |
| 404 | Not found | Resource not found |
| 422 | Validation failed | Form parameters do not conform to rules / password confirmation failed |
| 500 | Server error | Unexpected exception |

### 4.5 Authentication Flow (with Click Captcha)

```
客户端                               服务端
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② 用户点击图中文字位置              │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 Permission Model (RBAC)

```
  用户 ──┬── 角色 ──┬── 权限
  User     Role      Permission
                 │
                 ├── type=1: 菜单 (控制侧边栏可见)
                 ├── type=2: 按钮 (控制页面内操作)
                 └── type=3: API  (控制接口访问)

  权限标识格式: {method}.{path}
  例: get.admin/user  post.admin/user  delete.admin/user
  超级管理员标识: * (跳过所有权限检查)
```

### 4.7 Re-confirmation for Sensitive Operations

Sensitive operations like deleting users, roles, and permissions require passing the current user's password in the request body for identity re-verification:

```
客户端                           服务端
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 密码错误返回 422
  │                                │ → 密码正确继续执行
  │◄── 200 { code: 0 }           │
```

The frontend pops up a confirmation dialog before triggering delete operations, and sends the request after collecting the user's password.

## 5. Frontend Design

### 5.1 Flutter Web Admin Backend

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

Features: collapsible sidebar, Material 3 dual themes, high-density data tables, modal dialogs, hover interactions

### 5.2 HarmonyOS Mobile Client

Page routes:

| Page | Route | Description |
|------|------|------|
| LoginPage | `pages/LoginPage` | Username/password + click captcha login |
| DashboardPage | `pages/DashboardPage` | Stat cards + recent operations |
| UserListPage | `pages/UserListPage` | User list, search + pull-to-refresh + scroll-to-load-more |
| UserDetailPage | `pages/UserDetailPage` | Create/edit/view/delete (AlertDialog confirmation) |
| ProfilePage | `pages/ProfilePage` | Profile, logout (AlertDialog confirmation) |

Data flow: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Security Design

### 6.1 Defense in Depth

| Layer | Measure |
|------|------|
| Method restriction | SecurityFilter HTTP method whitelist, only GET/POST/PUT/DELETE/OPTIONS/HEAD allowed, non-standard methods return 405 |
| Attack blocking | SecurityFilter middleware, XSS/SQL injection/path traversal/command injection/CSRF detection and blocking |
| Human verification | Click captcha, mandatory for login/registration |
| Account lockout | 5 consecutive failed logins lock the account for 15 minutes, returning 429 during the lockout |
| Session limit | Max 3 concurrent tokens per user; the oldest token is auto-blacklisted when exceeded |
| Rate limiting | RateLimit middleware, Redis sliding window, Lua atomic |
| CSP | Content-Security-Policy header restricts resource origins, preventing XSS and data injection |
| Operation confirmation | Sensitive operations like delete require the current user's password for re-confirmation |
| Transport | HTTPS + JWT Bearer Token |
| API IDs | Hashids encryption, real IDs not reversible externally |
| Request body | AES-256-CBC encryption of sensitive fields |
| Database | BIGINT primary keys (no auto-increment exposure) |
| Database | AES-128-ECB encrypted storage of sensitive fields |
| Authentication | JWT HS256, 2h expiry + refresh token |
| Authorization | RBAC, method.path granularity permission control |
| Audit | OperationLog records all operations (incl. automatic source detection) |

### 6.2 Key Management

```
JWT_SECRET          → 环境变量注入，64位随机字符串
HASHIDS_SALT        → 唯一盐值，泄漏后需全局更换
ENCRYPTION_KEY      → API 传输加密密钥，32字节
ENCRYPTABLE_KEY     → DB 存储加密密钥，与传输密钥独立
SCOUT_HOSTS         → ES 地址，内网部署
```

### 6.3 Sensitive Data Protection

| Scenario | Field | Measure |
|------|------|------|
| List display | phone | Masked: 138****1234 |
| List display | email | Masked: a***@example.com |
| Detail view | phone/email | Requires decryption endpoint |
| Excel export | phone/email | Masked before export |
| PDF export | all fields | Masked + non-removable copyright watermark |
| Storage | phone/email/id_card | encryptable encrypted as ciphertext |

## 7. Export Design

### 7.1 Excel Export

```
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 PDF Export

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. Deployment Architecture

### 8.1 Recommended Topology

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                    静态文件: Flutter Web build/
```

### 8.2 Docker Compose (Recommended for Production)

The `docker-compose.yml` at the project root orchestrates all services in the topology above:

| Service | Image/Build | Port | Description |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Reverse proxy + static files + Gzip |
| `app` | built from local `Dockerfile` | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Primary database, persistent data volume |
| `redis` | redis:7-alpine | 6379 | Cache / rate limiting / captcha |
| `elasticsearch` | elasticsearch:8.x | 9200 | Full-text search |

Before startup, replace the `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY` etc. secrets in `docker-compose.yml` with random strings.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

The GitHub Actions continuous integration is defined in `.github/workflows/ci.yml`:
- PHP syntax check (`php -l`)
- PHPUnit unit tests
- Flutter static analysis (`flutter analyze`)

### 8.4 Database Backup

`database/backup/backup.sh` — mysqldump + gzip backup, auto-cleans backups older than 30 days.
`database/backup/restore.sh` — interactive backup selection and restore.

### 8.5 Monitoring

The `GET /metrics` endpoint (`MetricsController`) exposes 5 gauge metrics in Prometheus text format: total HTTP requests, active users, database/Redis connection status, memory usage.

### 8.6 Environment Requirements

| Component | Minimum version | Recommended configuration |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ OPcache enabled |
| MySQL | 8.0+ | 8.0+ master-slave replication |
| Elasticsearch | 7.x | 8.x 3-node cluster |
| Redis | 6.x | 7.x sentinel mode |
| Nginx | 1.20+ | Reverse proxy + gzip + SSL |
| Flutter SDK | 3.41+ | Latest stable |
| HarmonyOS | API 12 | DevEco Studio 5.x |
