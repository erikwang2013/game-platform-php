# Version Comparison

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Overview

| | Lite (MVP) | Standard | Full |
|------|------|------|------|
| Tables | 19 | 29 | 42 |
| API Endpoints | 38 | 54 | 129 |
| Controllers | 14 | 22 | 48 |
| Models | 14 | 24 | 34 |
| Services | 1 | 2 | 4 |
| Admin Pages | 11 | 13 | 15 |
| Platform Pages | 8 | 10 | 10 |
| HarmonyOS Pages | 2 | 2 | 5 |
| Docker Services | - | - | 7 |
| Tests | 60 | 60 | 116 |

---

## Authentication

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| Username/password | ✓ | ✓ | ✓ |
| JWT (2h+14d) | ✓ | ✓ | ✓ |
| Click captcha | stub | stub | ✓ poster-php |
| Account lockout | ✓ | ✓ | ✓ |
| Session limit | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ real |
| 2FA TOTP | - | - | ✓ |
| GDPR export/delete | - | - | ✓ |

---

## Wallet & Finance

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| Platform wallet | ✓ | ✓ | ✓ |
| Optimistic locking | ✓ | ✓ | ✓ |
| Transaction history | ✓ | ✓ | ✓ |
| Game currency wallet | ✓ | ✓ | ✓ |
| Deposit orders | ✓ | ✓ | ✓ |
| Auto-credit callback | - | ✓ manual | ✓ verified |
| Exchange quote/buy/sell | ✓ | ✓ | ✓ |
| Spread revenue | ✓ | ✓ | ✓ |
| Withdraw apply | ✓ | ✓ | ✓ |
| Global withdraw switch | ✓ | ✓ | ✓ |
| Withdraw review | ✓ manual | ✓ manual | ✓ batch+manual |
| KYC tiered limits | - | ✓ 3 tiers | ✓ |
| Withdraw fees | - | - | ✓ |
| PDF receipts | - | - | ✓ |

---

## Games

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| Game CRUD | ✓ | ✓ | ✓ |
| Currency management | ✓ | ✓ | ✓ |
| Game list/detail | ✓ | ✓ | ✓ |
| Game launch | ✓ | ✓ | ✓ |
| Categories (10) | - | - | ✓ |
| Category filter | - | - | ✓ |
| Game servers | - | ✓ | ✓ |
| Play log tracking | - | ✓ | ✓ |
| ES full-text search | - | - | ✓ |
| Search suggestions | - | - | ✓ |

---

## Operations

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| Announcements | ✓ | ✓ | ✓ |
| Dashboard | ✓ admin | ✓ admin | ✓ admin+platform |
| Excel export | ✓ | ✓ | ✓ |
| PDF export | ✓ | ✓ | ✓ |
| Real charts | - | - | ✓ fl_chart |
| Coupons | - | - | ✓ |
| Leaderboards | - | - | ✓ Redis |
| WebSocket push | - | - | ✓ port 8789 |
| Notifications | - | - | ✓ in-app+email |
| Referral system | - | - | ✓ |
| Daily stats | - | ✓ | ✓ |
| Revenue tracking | - | - | ✓ |

---

## Security & Compliance

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| 18-layer defense | ✓ | ✓ | ✓ |
| RBAC | ✓ | ✓ | ✓ |
| Audit logging | ✓ | ✓ | ✓ |
| 8-platform detection | ✓ | ✓ | ✓ |
| Rate limiting | ✓ | ✓ | ✓ |
| KYC verification | - | ✓ | ✓ |
| Risk engine (4 rules) | - | ✓ | ✓ |
| Webhook verification | - | - | ✓ |

---

## i18n

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| Languages | en/zh | 4 lang | 4 lang |
| Translation DB+Cache | ✓ | ✓ | ✓ |
| Auto-detection | ✓ | ✓ | ✓ |
| Country config | - | - | ✓ 8 countries |

---

## Deployment

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| Standalone webman | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ 7 services |
| Nginx proxy | - | - | ✓ |
| Crontab | - | ✓ | ✓ |
| Prometheus | ✓ | ✓ | ✓ |
| Health check | ✓ | ✓ | ✓ |
| hg/apidoc docs | - | - | ✓ 41 controllers |

---

## Clients

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| Flutter Admin | ✓ 5p | ✓ 11p | ✓ 15p |
| Flutter Platform | ✓ 5p | ✓ 8p | ✓ 10p |
| HarmonyOS | - | ✓ login+dashboard | ✓ 5p |

---

## Database Tables

### Lite (19)
```
admin (7):  erik_admin_user, erik_admin_role, erik_admin_permission,
           erik_admin_user_role, erik_admin_role_permission,
           erik_operation_log, erik_system_config

platform (12): erik_user, erik_user_wallet, erik_user_game_wallet,
               erik_game, erik_game_currency, erik_deposit_order,
               erik_withdraw_order, erik_exchange_record, erik_transaction,
               erik_payment_method, erik_announcement, erik_platform_config
```

### Standard (+10)
```
erik_user_identity, erik_user_oauth, erik_user_payment_account,
erik_user_session, erik_game_server, erik_game_play_log,
erik_withdraw_limit, erik_risk_rule, erik_risk_log, erik_stat_daily
```

### Full (+13)
```
erik_game_category, erik_game_category_rel, erik_leaderboard,
erik_coupon, erik_user_coupon, erik_language, erik_translation,
erik_country_config, erik_platform_revenue,
erik_notification, erik_referral, erik_referral_reward, erik_user_2fa
```

---

## API Endpoints by Module

| Module | Lite | Standard | Full |
|------|--------|--------|--------|
| Auth | 3 | 3 | 7 (+OAuth +2FA) |
| Wallet | 2 | 2 | 3 (+callback) |
| Exchange | 4 | 4 | 4 |
| Withdraw | 2 | 2 | 8 (+batch, limits, review) |
| Games | 3 | 4 | 7 (+servers, logs, search) |
| User | 2 | 2 | 7 (+KYC, GDPR, privacy) |
| Admin | 18 | 25 | 79 |
| Operations | - | - | 30 (+leaderboard, coupons, notifications, referral) |
| i18n | 2 | 2 | 4 (+country config) |
| **Total** | **38** | **54** | **129** |
