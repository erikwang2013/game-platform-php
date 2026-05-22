# Open Admin (open-admin)

A full-stack admin dashboard built with webman v2 + Flutter.

> [中文文档](README.md) | [Architecture Diagrams](docs/ARCHITECTURE.md) | [Design Doc](docs/DESIGN.md) | [Security](docs/SECURITY.md) | [API Reference](docs/API.md)

## Features

| Domain | Feature | Notes |
|--------|---------|-------|
| 🔐 Auth | Login/Register/Refresh/Logout | Click captcha + JWT + blacklist |
| | Account lockout | 5 failures → 15 min lock |
| | Concurrent session limit | Max 3 active tokens per user |
| 📊 Dashboard | Real-time stats/trends/distribution/logs | Redis cached 5 min |
| 👥 Users | CRUD + batch delete/toggle status | Soft delete + password confirmation |
| | Excel batch import | Row-level validation + error report |
| 🔒 Roles & Perms | Role CRUD + permission tree | RBAC method.path granularity |
| ⚙ Config | Key-value CRUD | Grouped management |
| 📋 Audit | Log query + source detection | 8 platforms auto-detected |
| 📁 Files | Upload/Excel export/PDF export | Sensitive data auto-masked |
| 🛡 Security | 18-layer defense-in-depth | XSS/SQLi/path traversal/cmd injection/CSRF/rate limit/CSP... |
| 🏥 Ops | Health check/metrics/API docs/security.txt | Prometheus + OpenAPI 3.0 |

## Copyright

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

This copyright notice is permanent, must not be modified, removed, or reversed. All project files are protected under this copyright.

## Tech Stack

| Layer | Technology | Notes |
|---|------|------|
| Backend | webman v2 (workerman) | High-performance PHP daemon framework |
| PHP | 8.3+ | |
| Database | MySQL 8.0+ | Table prefix `erik_`, BIGINT non-auto-increment PKs |
| Search | Elasticsearch | Synced via `webman-scout` |
| Admin Frontend | Flutter 3.x | Web renders as desktop admin panel (`apps/flutter/`) |
| Mobile | HarmonyOS ArkTS | Native HarmonyOS client (`apps/harmonyos/`), supports phone/tablet/2in1 |

## Core Packages

| Package | Purpose |
|---|------|
| `erikwang2013/snowflake-php` | Globally unique BIGINT primary key generation |
| `erikwang2013/hashids` | API-layer ID encryption to hide real database IDs |
| `erikwang2013/jwt-webman` | JWT token issuance and verification |
| `erikwang2013/encryption` | Transport-layer sensitive data encryption |
| `erikwang2013/encryptable` | Database-layer sensitive field auto encryption |
| `erikwang2013/webman-scout` | Elasticsearch sync and full-text search |
| `erikwang2013/season` | Country flag data |
| `erikwang2013/poster-php` | Click captcha generation/verification + poster generation |
| `phpoffice/phpspreadsheet` | Excel export |
| `barryvdh/laravel-dompdf` | PDF export (Dompdf-based) |

## Project Structure

```
open-admin/
├── app/
│   ├── admin/controller/       # Admin controllers
│   │   ├── DashboardController.php # Dashboard (Redis cached)
│   │   ├── UserController.php      # User CRUD + batch ops
│   │   ├── RoleController.php      # Role CRUD
│   │   ├── PermissionController.php# Permission CRUD
│   │   ├── ConfigController.php    # System config CRUD
│   │   ├── LogController.php       # Operation log viewer
│   │   ├── ProfileController.php   # Profile + logout
│   │   ├── ExportController.php    # Excel/PDF export
│   │   ├── ImportController.php    # Excel import users
│   │   ├── UploadController.php    # File upload
│   │   ├── HealthController.php    # Health check
│   │   └── DocsController.php      # OpenAPI docs
│   ├── api/
│   │   └── v1/controller/          # API v1 (version via API-Version header)
│   │       ├── CaptchaController.php
│   │       └── AuthController.php
│   ├── middleware/             # Middleware
│   │   ├── Cors.php            # CORS
│   │   ├── SecurityFilter.php  # Attack detection (HTTP method restriction/XSS/SQLi/path traversal/cmd injection/CSRF)
│   │   ├── RateLimit.php       # Redis rate limiting
│   │   ├── ApiVersion.php      # API version validation
│   │   ├── AdminAuth.php       # JWT auth + blacklist
│   │   ├── AdminPermission.php # RBAC authorization
│   │   └── OperationLog.php    # Auto operation logging (with source detection)
│   └── model/                  # Eloquent models
├── apps/
│   ├── flutter/                # Flutter Web admin panel
│   └── harmonyos/              # HarmonyOS client (auto token refresh)
├── config/                     # Config files
├── database/migrations/        # SQL migrations (incl. permission seeds)
└── vendor/                     # Composer dependencies
```

## Requirements

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (frontend development only)
- Elasticsearch >= 7.x (optional, for search)

## Quick Start

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

```bash
cp .env.example .env
```

Key environment variables:

| Variable | Description | Default |
|---------|-------------|---------|
| `JWT_SECRET` | JWT signing key | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids salt | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API encryption key | 32-byte default |
| `SNOWFLAKE_DATACENTER_ID` | Datacenter ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | Worker ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES hosts | `http://localhost:9200` |

**Always change all keys to random strings in production.**

### 3. Initialize Database

Run the SQL migration files in order:

```bash
# Create tables
mysql -u root -p < database/migrations/2026_05_16_000000_init_tables.sql
# Seed permissions
mysql -u root -p < database/migrations/2026_05_20_000001_seed_permissions.sql
```

### 4. Start Server

```bash
php start.php start
```

Default: `http://0.0.0.0:8787`.

### 5. Start Frontend (Optional)

**Flutter admin panel (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (desktop admin panel style)
```

**HarmonyOS client (Mobile):**

Open `apps/harmonyos/` in DevEco Studio and run on a device or emulator.

### 6. Docker Compose (Recommended for Production)

Full Docker orchestration with 5 services: Nginx, PHP (webman app), MySQL, Redis, Elasticsearch.

```bash
# 1. Configure Docker environment variables
cp .env.docker .env

# 2. Start all services
docker-compose up -d

# 3. Initialize database (run inside the app container)
docker-compose exec app mysql -h mysql -u root -p < database/migrations/2026_05_16_000000_init_tables.sql
docker-compose exec app mysql -h mysql -u root -p < database/migrations/2026_05_20_000001_seed_permissions.sql

# 4. Access
# http://localhost:8787  (webman)
# http://localhost:8080  (Nginx reverse proxy)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, based on `php:8.3-cli`
- `docker-compose.yml`: 5 services, isolated network, persistent volumes
- `.env.docker`: Docker-specific environment variables

## Database Conventions

- **Prefix**: `erik_`
- **Primary Key**: `id BIGINT UNSIGNED NOT NULL`, **NO AUTO_INCREMENT**
- **ID Generation**: PKs are generated at the application layer via `SnowflakeService::generate()`
- **Required Columns**: Every table must have `id`, `created_at`, `updated_at`
- **Soft Delete**: Add `deleted_at DATETIME DEFAULT NULL` where needed
- **Sensitive Fields**: Phone, email, ID card — stored as ciphertext via the `encryptable` plugin, database column type `VARCHAR(500)`

## API Conventions

### Response Format

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Error Codes

| Code | Meaning |
|------|---------|
| `0` | Success |
| `400` | Bad request |
| `401` | Unauthenticated |
| `403` | Forbidden / Security blocked | RBAC / SecurityFilter attack detected |
| `404` | Not found |
| `422` | Validation failed |
| `413` | Payload too large | SecurityFilter triggered, >10MB |
| `405` | Method not allowed | SecurityFilter triggered, only GET/POST/PUT/DELETE/OPTIONS/HEAD permitted |
| `415` | Unsupported media type | SecurityFilter triggered, non-JSON Content-Type |
| `429` | Rate limited | RateLimit triggered / Account locked (5 failed logins, 15 min lockout) |
| `500` | Server error |

### ID Handling

- **API request/response IDs**: Encrypted to hashid strings, real DB IDs never exposed
- **URL paths**: `GET /admin/user/{hashid}` — the `{id}` parameter is a hashid
- **Database storage**: BIGINT raw values generated by snowflake

### API Versioning

The API version is specified via a request header — **not in the URL path**:

```http
API-Version: v1
```

- Defaults to `v1` when the header is absent
- Unsupported versions return `400 Bad Request`
- To add a new version, create `app/api/{version}/controller/` and register it in the middleware

### Rate Limiting

Redis sliding-window algorithm, default 60 req/min/IP/route. Stricter limits for auth:
- Login: 10 req/min
- Register: 5 req/min

Responses include `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` headers. 429 responses include `Retry-After`.

### Middleware Architecture

Global middleware runs for every request in order:

```
Cors (preflight + response headers)
  → SecurityFilter (HTTP method restriction/body size/Content-Type check/XSS/SQLi/path traversal/cmd injection/CSRF blocking)
  → RateLimit (Redis sliding-window + account lockout: 5 failed logins = 15 min lock)
  → ApiVersion (API version validation, /api group)
  → AdminAuth (JWT + blacklist, /admin group)
  → AdminPermission (RBAC, /admin group)
  → OperationLog (auto-log POST/PUT/DELETE with source detection, /admin group)
```

`/health` and `/api/docs` are public, only passing through `Cors → SecurityFilter → RateLimit`.

Security enhancements:
- **Account lockout**: 5 consecutive failed login attempts lock the account for 15 minutes; login returns 429 during lockout
- **Concurrent session limit**: Max 3 active tokens per user; exceeding this blacklists the oldest token automatically
- **security.txt**: `GET /.well-known/security.txt` provides RFC 9116 standard security contact information
- **Nginx security config**: See `docs/nginx-security.conf` for a complete reverse-proxy security hardening reference

### Authentication

Login and registration require **click captcha** verification:

1. Client requests `POST /api/captcha/generate` to get a captcha image (base64 PNG) and target word list
2. User clicks the corresponding word positions on the image in order
3. Login request includes `captcha_key` and `clicks` array — server verifies captcha before credentials

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

All admin endpoints require a JWT token:

```http
Authorization: Bearer <token>
```

Login returns an `access_token` (2h TTL) and a `refresh_token` (14d TTL).

Logout blacklists the token in Redis for its remaining TTL: `POST /admin/profile/logout`

### Sensitive Operation Confirmation

Destructive operations (delete user, role, permission) require the current user's `password` in the request body for identity re-verification:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API Reference

> All `/api/*` endpoints require the `API-Version: v1` header (defaults to v1 if absent).

### Public Endpoints

| Method | Path | Description |
|-----|------|------|
| `GET` | `/health` | Health check (DB/Redis/ES status) |
| `GET` | `/api/docs` | OpenAPI 3.0 specification |
| `POST` | `/api/captcha/generate` | Generate click captcha |
| `POST` | `/api/captcha/verify` | Verify click positions |
| `POST` | `/api/auth/login` | Login (requires captcha) |
| `POST` | `/api/auth/register` | Register (requires captcha) |
| `POST` | `/api/auth/refresh` | Refresh token |
| `GET` | `/metrics` | Prometheus metrics |

### Admin Endpoints (requires JWT + RBAC)

| Method | Path | Description |
|-----|------|------|
| `GET` | `/admin/dashboard` | Dashboard data (stats, trends, distribution) |
| `GET` | `/admin/user` | User list (paginated + search) |
| `POST` | `/admin/user` | Create user |
| `GET` | `/admin/user/{id}` | User detail |
| `PUT` | `/admin/user/{id}` | Update user |
| `DELETE` | `/admin/user/{id}` | Delete user (soft delete, requires password) |
| `POST` | `/admin/user/batch/destroy` | Batch delete users |
| `POST` | `/admin/user/batch/status` | Batch update user status |
| `GET` | `/admin/role` | Role list |
| `POST` | `/admin/role` | Create role |
| `GET` | `/admin/role/{id}` | Role detail |
| `PUT` | `/admin/role/{id}` | Update role |
| `DELETE` | `/admin/role/{id}` | Delete role (requires password) |
| `GET` | `/admin/permission` | Permission tree |
| `POST` | `/admin/permission` | Create permission |
| `GET` | `/admin/permission/{id}` | Permission detail |
| `PUT` | `/admin/permission/{id}` | Update permission |
| `DELETE` | `/admin/permission/{id}` | Delete permission (cascades children, requires password) |
| `GET` | `/admin/config` | Config list |
| `POST` | `/admin/config` | Create config |
| `PUT` | `/admin/config/{id}` | Update config |
| `DELETE` | `/admin/config/{id}` | Delete config |
| `GET` | `/admin/log` | Operation log list (paginated) |
| `PUT` | `/admin/profile` | Update profile |
| `PUT` | `/admin/profile/password` | Change password |
| `POST` | `/admin/profile/logout` | Logout (blacklist token) |
| `POST` | `/admin/export/excel` | Export to Excel |
| `POST` | `/admin/export/pdf` | Export to PDF |
| `POST` | `/admin/import/users` | Import users from Excel |
| `POST` | `/admin/upload` | Upload file |

## Frontend Notes

### Flutter Admin Panel (Desktop Style)

- **Layout**: Collapsible sidebar (64px/240px) + header + content area, responsive breakpoints (phone/tablet/desktop)
- **Pages**: Login, Dashboard, User Management, Roles & Permissions, System Config, Operation Logs, Profile
- **State**: GetX (`ApiService` singleton + `AuthService` token persistence)
- **Dashboard**: Stats cards, trend line chart (fl_chart), pie chart, recent activity log
- **Export**: Excel/PDF with non-removable copyright info
- **Batch Ops**: Multi-select batch delete, batch enable/disable
- **Theme**: Material 3 light/dark dual theme

### HarmonyOS Mobile Client

- **Pages**: Login, Dashboard, User List/Detail, Profile
- **Auth**: JWT Bearer + silent token refresh on 401, auto-redirect to login on refresh failure
- **Storage**: Token managed via AppStorage

## Development Rules

- No leading `\` on global function/class references — use `use` imports
- All PHP files must include the copyright header
- All config files must include inline comments
- Primary keys must be generated at the application layer via snowflake — no auto-increment
- All IDs in API parameters and responses must be encoded/decoded via hashids
- AdminPermission middleware uses Redis cache for user permissions (TTL=60s), eliminating N+1 query bottlenecks

## Deployment

### Docker Compose (Recommended)

`docker-compose.yml` in the project root orchestrates 5 services:

| Service | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | Local `Dockerfile` build | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

The PHP image is built from `Dockerfile`, based on `php:8.3-cli` with OPcache enabled.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

GitHub Actions CI pipeline: `.github/workflows/ci.yml`

- PHP syntax check (`php -l`)
- PHPUnit tests
- Flutter static analysis (`flutter analyze`)

### Database Backup

`database/backup/` directory:

- `backup.sh` — mysqldump + gzip backup, auto-clears backups older than 30 days
- `restore.sh` — interactive restore, lists available backups for selection

### Nginx Security

See `docs/nginx-security.conf` for production reverse-proxy security hardening.

## 开源不易，欢迎支持

| 微信 | 支付宝 |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
