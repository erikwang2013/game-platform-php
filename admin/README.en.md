# Open Admin (open-admin)

## Project Mascot

<img src="../docs/mascot.svg" width="120" alt="Dicey"/>

**Dicey** — Platform mascot. The die represents games and probability-based gameplay, the coin represents the platform economy and multi-payment gateways, and the purple palette echoes the admin branding. SVG source: `docs/mascot.svg`, infinitely scalable for docs, logos and merchandise.
<!-- lang-nav -->

Languages: [中文](README.md) · **English** · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

A full-stack admin dashboard system built with webman v2 + Flutter.

> [English version](README.en.md) | [Architecture diagrams](docs/ARCHITECTURE.en.md) | [Design doc](docs/DESIGN.en.md) | [Security architecture](docs/SECURITY.en.md) | [API reference](docs/API.en.md)

## Feature List

| Business domain | Feature | Description |
|--------|------|------|
| 🔐 Authentication | Login/register/refresh token/logout | Click captcha + JWT + blacklist |
| | Account lockout | 15-minute lock after 5 failed attempts |
| | Concurrent session limit | Max 3 valid tokens per user |
| 📊 Dashboard | Real-time stats/trend charts/distribution charts/recent operations | Redis cache for 5 minutes |
| 📈 Data analytics | 12 endpoints: overview/rankings/DAU/hourly/behavior distribution/revenue/conversion/probability/retention/funnel/ARPU/economy metrics | MySQL real-time aggregation, returns empty data on DB failure |
| 👥 User management | CRUD + batch delete/enable-disable | Soft delete + password re-confirmation |
| | Excel batch import | Per-row validation + error report |
| 🔒 Roles & permissions | Role CRUD + permission tree | RBAC method.path granularity authorization |
| ⚙ System config | Key-value CRUD | Group management |
| 🖥 CDN Admin | Five-provider config CRUD + toggle + connectivity test | Credentials AES-encrypted, service reads from DB only |
| 📋 Operation audit | Log query + source detection | Automatic detection of 8 platforms |
| 📁 File management | Upload/Excel export/PDF export | Sensitive data automatically masked |
| 🛡 Security | 18-layer defense in depth | XSS/SQL injection/path traversal/command injection/CSRF/rate limit/CSP... |
| 🏥 Operations | Health check/metrics/API docs/security.txt | Prometheus + OpenAPI 3.0 |

## Tech Stack

| Layer | Technology | Description |
|---|------|------|
| Backend framework | webman v2 (workerman) | Ultra-high-performance PHP resident process framework |
| PHP version | 8.3+ | |
| Database | MySQL 8.0+ | Table prefix `game_`, BIGINT non-auto-increment primary keys |
| Search engine | Elasticsearch | Synced and queried via `webman-scout` |
| Admin frontend | Flutter 3.x | Web version uses PC admin dashboard style (`apps/flutter/`) |
| Mobile | HarmonyOS ArkTS | Native HarmonyOS client (`apps/harmonyos/`), supports phone/tablet/2in1 |

## Core Dependencies

| Package | Purpose |
|---|------|
| `erikwang2013/snowflake-php` | Snowflake algorithm generates globally unique BIGINT primary keys |
| `erikwang2013/hashids` | API-layer ID encryption/decryption, hides real database IDs |
| `erikwang2013/jwt-webman` | JWT authentication token issuance and verification |
| `erikwang2013/encryption` | Sensitive data encryption/decryption at the interface transport layer |
| `erikwang2013/encryptable` | Automatic encryption/decryption of sensitive fields at the DB storage layer |
| `erikwang2013/webman-scout` | Elasticsearch data sync and full-text search |
| `erikwang2013/season` | Country flag data |
| `erikwang2013/poster-php` | Click captcha generation and verification + poster generation |
| `phpoffice/phpspreadsheet` | Excel export |
| `barryvdh/laravel-dompdf` | PDF export (based on Dompdf) |

## Project Structure

```
open-admin/
├── app/
│   ├── admin/controller/       # Admin controllers
│   │   ├── DashboardController.php # Dashboard (Redis cache)
│   │   ├── UserController.php      # User CRUD + batch operations
│   │   ├── RoleController.php      # Role CRUD
│   │   ├── PermissionController.php# Permission CRUD
│   │   ├── ConfigController.php    # System config CRUD
│   │   ├── LogController.php       # Operation log query
│   │   ├── ProfileController.php   # Profile + logout
│   │   ├── ExportController.php    # Excel/PDF export
│   │   ├── ImportController.php    # Excel user import
│   │   ├── UploadController.php    # File upload
│   │   ├── HealthController.php    # Health check
│   │   ├── DocsController.php      # OpenAPI docs
│   │   └── BaseController.php      # Base controller
│   ├── api/
│   │   └── v1/controller/          # API v1 controllers (version controlled by the API-Version request header)
│   │       ├── CaptchaController.php # Click captcha
│   │       └── AuthController.php    # Login/register/refresh token
│   ├── common/                 # Common utility classes
│   │   ├── HashidsService.php  # ID encode/decode
│   │   ├── SnowflakeService.php# Snowflake ID generation
│   │   └── EncryptionService.php # Data encryption/decryption + masking
│   ├── middleware/             # Middleware
│   │   ├── Cors.php            # Cross-origin
│   │   ├── SecurityFilter.php  # Attack detection and blocking (HTTP method restriction/XSS/SQL injection/path traversal/command injection/CSRF)
│   │   ├── RateLimit.php       # Redis rate limiting (sliding window + response headers)
│   │   ├── ApiVersion.php      # API version validation
│   │   ├── AdminAuth.php       # JWT authentication + blacklist
│   │   ├── AdminPermission.php # RBAC permission validation
│   │   └── OperationLog.php    # Automatic operation log recording (incl. source detection)
│   └── model/                  # Data models
├── apps/
│   ├── flutter/                # Flutter Web admin backend (PC style)
│   │   └── lib/app/
│   │       ├── pages/          # 5 complete pages (dashboard/users/roles/config/logs/profile)
│   │       ├── services/       # ApiService (JWT interceptor) + AuthService (Token persistence)
│   │       └── layouts/        # Responsive admin layout (sidebar + top bar + content area)
│   └── harmonyos/              # Native HarmonyOS client (seamless Token refresh)
├── config/                     # Config files (with Chinese comments)
│   ├── route.php               # Routes + API version strategy
│   ├── middleware.php           # Global middleware registration
│   └── ...                     # Component configs
├── install/        # SQL migration files (incl. permission seed data)
├── public/                     # Public entry
├── runtime/                    # Runtime files
└── vendor/                     # Composer dependencies
```

## Environment Requirements

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (only needed for frontend development)
- Elasticsearch >= 7.x (optional, needed for search features)

## Quick Start

### 1. Install dependencies

```bash
composer install
```

### 2. Configure environment variables

Copy and modify the environment variables (optional; defaults from `config/*.php` are used if not configured):

```bash
cp .env.example .env
```

Key config items:

| Environment variable | Description | Default value |
|---------|------|--------|
| `JWT_SECRET` | JWT signing secret | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids salt | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API encryption key | 32-byte default value |
| `SNOWFLAKE_DATACENTER_ID` | Datacenter ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | Worker node ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES address | `http://localhost:9200` |

**Be sure to change all secrets to random strings in production.**

### 3. Initialize the database

Run the SQL files under `install/` in order:

```bash
mysql -u root -p < install/install.sql
```

### 4. Start the service

```bash
php start.php start
```

Listens on `http://0.0.0.0:8787` by default.

### 5. Start the frontend (optional)

**Flutter admin backend (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (PC admin dashboard style)
```

**HarmonyOS client (mobile):**

Open the `apps/harmonyos/` directory with DevEco Studio and run on a real device or emulator.

### 6. Docker Compose one-click deployment (recommended for production)

The project ships a complete Docker orchestration with 5 services: Nginx, PHP (webman app), MySQL, Redis, Elasticsearch.

```bash
# 1. Configure Docker environment variables
cp .env.docker .env

# 2. Start all services
docker-compose up -d

# 3. Initialize the database (run inside the app container)
docker-compose exec app mysql -h mysql -u root -p < install/install.sql

# 4. Access
# http://localhost:8787  (webman)
# http://localhost:8080  (Nginx reverse proxy)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, based on `php:8.3-cli`
- `docker-compose.yml`: 5-service orchestration, network isolation, persistent data volumes
- `.env.docker`: environment variables dedicated to the Docker environment

## Database Conventions

- **Table prefix**: `game_`
- **Primary key**: all table primary keys are `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT is forbidden**
- **ID generation**: primary key IDs are generated by the application layer via `SnowflakeService::generate()`, distributed unique
- **Required fields**: every table must include `id`, `created_at`, `updated_at`
- **Soft delete**: tables needing soft delete add `deleted_at DATETIME DEFAULT NULL`
- **Sensitive fields**: phone numbers, emails, ID card numbers etc. use the `encryptable` plugin for automatic encryption/decryption; the DB field stores ciphertext in `VARCHAR(500)`

## API Conventions

### Unified Response Format

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Business Error Codes

| Error code | Meaning | Description |
|-------|------|------|
| `0` | Success | |
| `400` | Request parameter error | |
| `401` | Not logged in (invalid or expired Token) | |
| `403` | No permission / security block | RBAC authorization failure / SecurityFilter attack detection |
| `404` | Resource not found | |
| `422` | Parameter validation failed | |
| `413` | Request body too large | Triggered by SecurityFilter, over 10MB |
| `405` | Method not allowed | Triggered by SecurityFilter, only GET/POST/PUT/DELETE/OPTIONS/HEAD allowed |
| `415` | Unsupported media type | Triggered by SecurityFilter, Content-Type is not JSON |
| `429` | Too many requests | Triggered by RateLimit / account lockout (15-min lock after 5 failed logins) |
| `500` | Internal server error | |

### ID Handling

- **IDs in requests/responses**: encrypted into strings with hashids, real database IDs are never exposed
- **API paths**: `GET /admin/user/{hashid}` — the `{id}` in the path is a hashid string
- **Database storage**: raw BIGINT value generated by snowflake

### API Versioning

API versions are controlled via a request header, **not reflected in the URL**:

```http
API-Version: v1
```

- Defaults to `v1` when no version header is present
- Unsupported versions return `400 Bad Request`
- To add a version, just create the `app/api/{version}/controller/` directory and register the new version in the middleware

### Rate Limiting

Based on the Redis sliding window algorithm, default 60 requests/minute/IP/route. Stricter for sensitive endpoints:
- Login: 10/minute
- Registration: 5/minute

Response headers include `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. Exceeding the limit returns 429 with a `Retry-After` header.

### Middleware Architecture

Global middleware applies to all requests, executed in order:

```
Cors (CORS preprocessing + response headers)
  → SecurityFilter (HTTP method restriction/request body size/Content-Type validation/XSS/SQL injection/path traversal/command injection/CSRF attack blocking)
  → RateLimit (Redis sliding window rate limiting + account lockout: 15-min lock after 5 failed logins)
  → ApiVersion (API version validation, /api route group)
  → AdminAuth (JWT authentication + blacklist, /admin route group)
  → AdminPermission (RBAC authorization, /admin route group)
  → OperationLog (automatic POST/PUT/DELETE logging incl. source detection, /admin route group)
```

`/health` and `/api/docs` are public endpoints, passing only through `Cors → SecurityFilter → RateLimit`.

Security enhancements:
- **Account lockout**: after 5 consecutive failed logins, the account is locked for 15 minutes; logins during the lockout return 429
- **Concurrent session limit**: max 3 valid tokens per user; the oldest token is automatically blacklisted when exceeded
- **security.txt**: `GET /.well-known/security.txt` provides RFC 9116 standard security contact info
- **Nginx security config**: see `docs/nginx-security.conf` for a complete reverse-proxy security hardening example

### Authentication

Login and registration require passing the **click captcha** check first:

1. The client requests `POST /api/captcha/generate` to get the captcha image (base64 PNG) and the list of target words
2. The user clicks the matching word positions in the image in order, collecting click coordinates `[{x, y}, ...]`
3. Login submits `captcha_key` and `clicks` together; the server validates the captcha first, then the credentials

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

Subsequent admin endpoints require JWT authentication:

```http
Authorization: Bearer <token>
```

On successful login, an access_token is returned with a 2-hour validity; a refresh_token is also returned with a 14-day validity.

On logout the Token is added to the Redis blacklist and cannot be reused within its validity period. POST /admin/profile/logout

### Re-confirmation for Sensitive Operations

Sensitive operations like deleting users, roles, and permissions require passing the current logged-in user's `password` in the request body for secondary identity confirmation:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API List

> All `/api/*` endpoints require the `API-Version: v1` request header (defaults to v1 if absent).

### Public Endpoints

| Method | Path | Description |
|-----|------|------|
| `GET` | `/health` | Health check (DB/Redis/ES status) |
| `GET` | `/api/docs` | OpenAPI 3.0 spec docs |
| `POST` | `/api/captcha/generate` | Generate click captcha |
| `POST` | `/api/captcha/verify` | Verify click captcha |
| `POST` | `/api/auth/login` | Login (captcha required) |
| `POST` | `/api/auth/register` | Register (captcha required) |
| `POST` | `/api/auth/refresh` | Refresh token |
| `GET` | `/metrics` | Prometheus metrics |

### Admin Endpoints (JWT + RBAC required)

| Method | Path | Description |
|-----|------|------|
| `GET` | `/admin/dashboard` | Dashboard data (Redis cache for 5 minutes) |
| `GET` | `/admin/user` | User list (pagination + search) |
| `POST` | `/admin/user` | Create user |
| `GET` | `/admin/user/{id}` | User detail |
| `PUT` | `/admin/user/{id}` | Update user |
| `DELETE` | `/admin/user/{id}` | Delete user (soft delete, password confirmation required) |
| `POST` | `/admin/user/batch/destroy` | Batch delete users (password confirmation required) |
| `POST` | `/admin/user/batch/status` | Batch enable/disable users |
| `GET` | `/admin/role` | Role list |
| `POST` | `/admin/role` | Create role |
| `PUT` | `/admin/role/{id}` | Update role |
| `DELETE` | `/admin/role/{id}` | Delete role (password confirmation required) |
| `GET` | `/admin/permission` | Permission tree |
| `POST` | `/admin/permission` | Create permission |
| `PUT` | `/admin/permission/{id}` | Update permission |
| `DELETE` | `/admin/permission/{id}` | Delete permission (cascades to child permissions, password confirmation required) |
| `GET` | `/admin/config` | System config list |
| `POST` | `/admin/config` | Create config item |
| `PUT` | `/admin/config/{id}` | Update config item |
| `DELETE` | `/admin/config/{id}` | Delete config item (password confirmation required) |
| `GET` | `/admin/log` | Operation logs (pagination + filters) |
| `PUT` | `/admin/profile` | Update profile |
| `PUT` | `/admin/profile/password` | Change password |
| `POST` | `/admin/profile/logout` | Logout (JWT blacklist) |
| `POST` | `/admin/export/excel` | Export Excel |
| `POST` | `/admin/export/pdf` | Export PDF |
| `POST` | `/admin/import/users` | Import users from Excel |
| `POST` | `/admin/upload` | File upload (images/documents, max 10MB) |

## Frontend Notes

### Flutter Admin Backend (PC Style)

- **Layout**: sidebar (collapsible 64px/240px) + top bar + content area, responsive at three breakpoints (phone/tablet/desktop)
- **Pages**: login, dashboard, user management, roles & permissions, system config, operation logs, profile
- **State management**: GetX (`ApiService` singleton + `AuthService` Token persistence)
- **Dashboard**: stat cards, trend line charts (fl_chart), pie charts, recent operation logs
- **Export**: Excel/PDF export, PDF includes non-removable copyright info
- **Batch operations**: multi-select batch delete, batch enable/disable
- **Theme**: Material 3 light/dark dual themes

### HarmonyOS Mobile Client

- **Pages**: login, dashboard, user list/detail, profile
- **Authentication**: JWT Bearer + automatic seamless Token refresh on 401; auto-redirects to login on refresh failure
- **Storage**: Token managed via AppStorage

## Development Conventions

- Global functions/classes are referenced without a leading `\`, always imported via `use`
- All PHP files must start with a copyright notice
- All config files must include Chinese comments explaining each item
- Database primary keys must be generated by the application-layer snowflake, auto-increment is forbidden
- All IDs in API-layer parameters and responses must be encrypted/decrypted via hashids
- The AdminPermission middleware caches user permissions in Redis (TTL=60s), eliminating the N+1 query bottleneck

## Deployment

### Docker Compose (Recommended)

A `docker-compose.yml` is provided at the project root, orchestrating 5 services:

| Service | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | built from local `Dockerfile` | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

The PHP image is built via the `Dockerfile` on top of `php:8.3-cli`, with OPcache enabled.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

GitHub Actions continuous integration pipeline: `.github/workflows/ci.yml`

- PHP syntax check (`php -l`)
- PHPUnit unit tests
- Flutter static analysis (`flutter analyze`)

### Database Backup

`database/backup/` directory:

- `backup.sh` — mysqldump + gzip backup, auto-cleans backups older than 30 days
- `restore.sh` — interactive restore, lists available backups for selection

### Nginx Security Config

For production deployment, see `docs/nginx-security.conf` for reverse-proxy security hardening.

## Open Source Is Not Easy, Support Welcome

| WeChat Pay | Alipay |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "WeChat Pay") | ![支付宝](./docs/alipay.png "Alipay") |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

