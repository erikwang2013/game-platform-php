# service/ — C-Side User Platform API Service
<!-- lang-nav -->

Languages: [中文](README.md) · **English** · [한국어](README.ko.md) · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

The C-side user platform API service, a high-performance PHP backend built on webman v2 (Workerman), provides users with the full game aggregation platform experience: registration and login, wallet, deposit, withdrawal, exchange, games, leaderboards, coupons, support tickets, VIP, achievements, social features and announcements.

## Feature List

| Module | Description |
|------|------|
| Users | Register/login (username/password + 7-platform OAuth + 2FA TOTP), profile |
| Wallet | Platform token wallet (optimistic lock) + game currency wallet + transaction history |
| Deposit | 13 payment gateways (Stripe/PayPal/NowPayments/Coinbase, etc.) with callback signature verification and automatic crediting |
| Withdrawal | Application → review → payout, KYC tiered limits |
| Exchange | Real-time platform token ⇄ game currency quotes, VIP discounts and rate bonuses |
| Games | Game list/categories/search, game records, Provider settlement callbacks |
| Leaderboards | Daily/weekly/monthly/all-time + WebSocket real-time push |
| Coupons | Fixed amount + percentage discounts, time and quantity limited |
| Tickets | Users create/reply to support tickets |
| VIP | 5-tier loyalty, experience accumulation, exchange discounts |
| Achievements | 12 built-in achievements, event-driven detection |
| Social | Friend system + WebSocket real-time messaging |
| Announcements | In-app announcements + notifications/email |

## Tech Stack

- PHP 8.3+ / webman v2 (workerman/webman)
- MySQL 8.0+ (table prefix `game_`, BIGINT non-auto-increment primary keys)
- Redis (Session / Cache / Rate limiting)
- ClickHouse (OLAP analytics / probability calculations)
- Elasticsearch (full-text search)
- JWT authentication + HMAC-SHA256 Provider signing

## Project Structure

```
service/
├── app/
│   ├── api/v1/controller/  # C-side API controllers (35)
│   ├── middleware/         # Middleware (Cors/SecurityFilter/RateLimit/ApiVersion/UserAuth/ProviderAuth)
│   ├── model/              # Data models
│   ├── service/            # Business services (VIP/leaderboard/risk/notification, etc.)
│   ├── event/              # Event bus (EventBus Redis Pub/Sub)
│   ├── provider/           # Game Provider layer
│   └── payment/            # Payment gateways
├── common/                 # Shared services directory (implemented in erik/platform-common package)
├── config/                 # Configuration files
├── public/                 # Web entry
├── tests/                  # PHPUnit tests
├── start.php               # Startup entry
└── composer.json
```

## One-Click Installation

Use the one-click installation wizard at the project root (run from the project root):

```bash
# 1. Start the installation wizard
php -S 0.0.0.0:8888 -t install/

# 2. Open http://localhost:8888 in your browser
#    Follow the wizard: environment check → database config → admin account setup → auto-install
```

Or start everything with Docker Compose (project root):

```bash
docker compose up -d
```

## Manual Installation

```bash
# 1. Install dependencies
cd service && composer install

# 2. Configure environment variables
cp .env.example .env
# Edit .env: database connection, JWT keys, etc.

# 3. Start the service (default port 8788)
php start.php start        # foreground
php start.php start -d     # background (daemon)
```

## Usage

- API reference: `docs/API.md` (complete API reference)
- Online docs: http://localhost:8788/apidoc/ (hg/apidoc interactive docs)
- Health check: `GET http://localhost:8788/health`
- C-side frontend: `apps/flutter/platform/` (Flutter Web user platform)
- Admin backend: `admin/` (admin backend and `admin/apps/flutter/` frontend)

## Tests

```bash
cd service
SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
```
