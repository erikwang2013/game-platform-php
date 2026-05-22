# Global Game Platform

[中文](README.md) | English

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

A global, internationalized game aggregation platform. Users register, deposit funds, exchange for game currencies, play games to earn coins, and withdraw earnings to their wallets. The admin panel provides complete game management, withdrawal review, user management, and payment management. Supports English/Chinese language switching.

## Version Strategy

| Version | Goal | Status |
|---------|------|--------|
| MVP | Core loop: Register → Deposit → Exchange → Play → Withdraw → Review | Complete |
| Standard | Production-ready: global payments, 3rd-party game SDK, risk control, 3 frontends | Complete |
| Full | Complete: leaderboards, coupons, categories, country config, ES search | Complete |

## Tech Stack

### Backend
- PHP 8.3+, webman v2 (workerman/webman)
- MySQL 8.0+ (table prefix `erik_`, BIGINT non-auto-increment primary keys)
- Redis (Session / Cache / Rate Limiting)
- Elasticsearch (Full-text Search)
- JWT Authentication + RBAC Authorization
- Data Encryption: API transport layer AES-256-CBC + DB storage layer AES-128-ECB

### Frontend
- Flutter 3.x (Web PC style)
- HarmonyOS ArkTS (Mobile)
- Responsive layout (Phone / Tablet / Desktop)
- i18n: English / Simplified Chinese switching

### Core Components
- `erikwang2013/snowflake-php` — Globally unique BIGINT ID generation
- `erikwang2013/hashids` — API layer ID encode/decode
- `erikwang2013/jwt-webman` — JWT authentication
- `erikwang2013/encryption` — API sensitive data encryption
- `erikwang2013/encryptable` — Database field encryption
- `erikwang2013/webman-scout` — Elasticsearch sync & query
- `erikwang2013/season` — Country flags
- `erikwang2013/security-php` — Security detection tools
- `erikwang2013/poster-php` — Random verification for sensitive operations

## Project Structure

```
game-platform-php/
├── admin/                     # Admin backend (webman v2, port 8787)
│   ├── app/admin/controller/  #   Admin controllers
│   ├── app/middleware/        #   Middleware (Cors/Security/RateLimit/Auth/Permission)
│   ├── config/                #   Configuration files
│   ├── database/migrations/   #   SQL migration files
│   └── apps/flutter/          #   Flutter Web PC admin panel
│
├── service/                   # User-facing API (webman v2, port 8788)
│   ├── app/api/v1/controller/ #   API controllers
│   ├── app/middleware/        #   Middleware (incl. LanguageMiddleware)
│   └── config/                #   Configuration files
│
├── common/                    # Shared layer (PSR-4 autoload)
│   ├── model/                 #   Data models (14)
│   ├── middleware/            #   Shared middleware (UserAuth)
│   └── service/               #   Shared services (TranslationService)
│
├── apps/
│   └── flutter/platform/      # Flutter Web PC user platform
│
├── docs/                      # Documentation
│   ├── ARCHITECTURE.md        #   Architecture document
│   ├── ARCHITECTURE-DESIGN.md #   Architecture design document
│   ├── FEATURES.md            #   Features document
│   ├── FEATURE-DESIGN.md      #   Feature design document
│   └── API.md                 #   API reference
│
└── admin/docs/superpowers/    # Development specs & plans
    ├── specs/                 #   Design specifications
    └── plans/                 #   Implementation plans
```

## Quick Start

### Requirements
- PHP 8.3+
- MySQL 8.0+
- Redis 6.0+
- Composer 2.x
- Flutter SDK 3.x (frontend)

### 1. Database Setup

```bash
# Create database
mysql -u root -e "CREATE DATABASE IF NOT EXISTS game_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations (in order)
mysql -u root game_platform < admin/database/migrations/2026_05_16_000000_init_tables.sql
mysql -u root game_platform < admin/database/migrations/2026_05_22_000003_platform_tables.sql
mysql -u root game_platform < admin/database/migrations/2026_05_22_000004_i18n_tables.sql
```

### 2. Backend Start

```bash
# Admin backend (port 8787)
cd admin
cp .env.example .env   # Edit database connection settings
composer install
php start.php start -d

# User-facing API (port 8788)
cd service
cp .env.example .env   # Edit database connection settings
composer install
php start.php start -d
```

### 3. Frontend Start

```bash
# Admin panel (Flutter Web PC)
cd admin/apps/flutter
flutter pub get
flutter run -d chrome

# User platform (Flutter Web PC)
cd apps/flutter/platform
flutter pub get
flutter run -d chrome
```

### 4. Verification

```bash
# Test admin backend
curl http://localhost:8787/health

# Test user API
curl http://localhost:8788/health

# Test user registration
curl -X POST http://localhost:8788/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"123456"}'
```

## Security Features

- **18-layer defense-in-depth**: XSS/SQL injection/CSRF/path traversal/command injection detection
- **HTTP method whitelist**: Only GET/POST/PUT/DELETE/OPTIONS/HEAD allowed
- **JWT authentication**: access_token 2h + refresh_token 14d, concurrent session limit
- **RBAC authorization**: method.path granularity, Redis 60s cache
- **Click captcha**: Required for login/registration
- **Password confirmation**: Required for sensitive operations
- **Data encryption**: Transport AES-256-CBC + Storage AES-128-ECB
- **ID encryption**: Snowflake generation + Hashids encoding, non-reversible externally
- **Wallet optimistic locking**: Prevents concurrent deduction/duplicate crediting
- **Operation audit**: Full operation logging, 8-platform source detection
- **Rate limiting**: Redis sliding window, Lua atomic
- **CSP headers**: Content-Security-Policy anti-XSS
- **Account security**: 5 failed login attempts → 15-minute lockout

## Testing

```bash
cd admin
phpunit --bootstrap tests/bootstrap.php tests/
```

- PHPUnit 12.x, 116 test cases
- 56 business logic tests (PlatformTest) + 60 infrastructure tests
- Coverage: bcmath precision, exchange calculation, withdraw fees, limits, risk control, coupons, KYC, i18n

## Business Model

```
Fiat (USD/CNY/EUR...)
  │  Deposit (Stripe/PayPal/Alipay/WeChat)
  ▼
Platform Currency (unified, precision decimal(18,4))
  │  Exchange (with rate + platform spread)
  ▼
Game Currency (per-game, independent rates)
  │  Earn/spend by playing
  ▼
Platform Currency ← Convert back → Withdraw (review/auto)
```

## Supported Languages

| Code | Name | Native Name | Status |
|------|------|-------------|--------|
| en-US | English | English | Active |
| zh-CN | Chinese (Simplified) | 简体中文 | Active |
| ja-JP | Japanese | 日本語 | Active |
| ko-KR | Korean | 한국어 | Active |

Automatic language detection via `X-Language` header or `Accept-Language` header. Manual switching via `POST /api/language/switch`.

## Documentation Index

| Document | Description |
|----------|-------------|
| [Architecture Design](docs/ARCHITECTURE-DESIGN.md) | Architecture decisions and rationale |
| [Architecture](docs/ARCHITECTURE.md) | System topology, module architecture, data flow |
| [Feature Design](docs/FEATURE-DESIGN.md) | Business models, feature specs, workflow design |
| [Features](docs/FEATURES.md) | Feature catalog, module descriptions, user journeys |
| [API Reference](docs/API.md) | Complete API reference (20 user + 18 admin endpoints) |
| [Design Spec](admin/docs/superpowers/specs/2026-05-22-game-platform-design.md) | Full design specification |
| [Implementation Plan](admin/docs/superpowers/plans/2026-05-22-game-platform-plan.md) | Detailed implementation plan |
