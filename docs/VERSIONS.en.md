# Version Comparison
<!-- lang-nav -->

Languages: [中文](VERSIONS.md) · **English** · [한국어](VERSIONS.ko.md) · [Русский](VERSIONS.ru.md) · [Deutsch](VERSIONS.de.md) · [Français](VERSIONS.fr.md) · [Español](VERSIONS.es.md) · [Português](VERSIONS.pt.md) · [हिन्दी](VERSIONS.hi.md) · [العربية](VERSIONS.ar.md) · [বাংলা](VERSIONS.bn.md) · [Bahasa Indonesia](VERSIONS.id.md) · [日本語](VERSIONS.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Overview

| | Lite | Standard | Full |
|------|------|------|------|
| Data tables (install.sql) | 19 | 29 | **43** (not the 52 previously claimed in docs) |
| API endpoints | 38 | 54 | ~149 (admin+service, incl. Webhook/Provider) |
| Backend controllers | 14 | 22 | admin 32 + service 30 |
| Data models | Not shared | Not shared | **admin 46 / service 44, one copy each, no shared layer** |
| Shared services | No shared layer | No shared layer | Single shared package `packages/platform-common` |
| Admin frontend pages | 11 | 13 | 15 |
| Platform frontend pages | 8 | 10 | 10 |
| HarmonyOS (admin) | - | Login + dashboard | **8 pages** `admin/apps/harmonyos/` |
| HarmonyOS (C-end) | - | - | **5 pages** `apps/harmonyos/` (login/game lobby/detail/wallet/profile) |
| Docker services | - | - | **7** (nginx/admin/service/leaderboard-ws/mysql/redis/elasticsearch) |
| Test cases | 60 | 60 | admin ~132; service 3 |

---

## User Authentication

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| Username/password register/login | ✓ | ✓ | ✓ |
| JWT Token (2h+14d) | ✓ | ✓ | ✓ |
| Click captcha | stub | stub | ✓ poster-php |
| Account lockout (5 attempts/15 min) | ✓ | ✓ | ✓ |
| Session limit (3 concurrent) | ✓ | ✓ | ✓ |
| OAuth (Google/Facebook/Apple) | - | mock | ✓ 7 platforms (incl. X/MS/LinkedIn/GitHub) |
| 2FA TOTP two-factor authentication | - | - | ✓ |
| GDPR data export/deletion | - | - | ✓ |

---

## Wallet & Funds

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| Platform currency wallet | ✓ | ✓ | ✓ |
| Wallet optimistic lock | ✓ | ✓ | ✓ |
| Transaction records | ✓ | ✓ | ✓ |
| Game currency wallet | ✓ | ✓ | ✓ |
| Deposit order creation | ✓ | ✓ | ✓ |
| Deposit callback auto-credit | - | ✓ manual | ✓ Stripe/PayPal verification |
| Exchange quote/buy/sell | ✓ | ✓ | ✓ |
| Exchange spread revenue | ✓ | ✓ | ✓ |
| Withdrawal application | ✓ | ✓ | ✓ |
| Global withdrawal switch | ✓ | ✓ | ✓ |
| Withdrawal review | ✓ manual | ✓ manual | ✓ batch + manual |
| KYC tiered limits | - | ✓ 3 levels | ✓ |
| Withdrawal fee | - | - | ✓ |
| PDF receipts | - | - | ✓ |

---

## Game Management

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| Game CRUD | ✓ | ✓ | ✓ |
| Game currency management | ✓ | ✓ | ✓ |
| C-end game list/detail | ✓ | ✓ | ✓ |
| Game launch | ✓ | ✓ | ✓ |
| Game categories (10) | - | - | ✓ |
| Category filtering | - | - | ✓ |
| Game server management | - | ✓ | ✓ |
| Game play log tracking | - | ✓ | ✓ |
| ES full-text search | - | - | ✓ |
| Search suggestions | - | - | ✓ |
| Third-party game Provider SDK | - | - | ✓ HMAC-SHA256 |

---

## Operations Tools

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| Announcement management | ✓ | ✓ | ✓ |
| Dashboard | ✓ admin | ✓ admin | ✓ admin + platform |
| Excel export | ✓ | ✓ | ✓ |
| PDF export | ✓ | ✓ | ✓ |
| Real dashboard charts | - | - | ✓ fl_chart |
| Coupon system | - | - | ✓ |
| Leaderboards (daily/weekly/monthly/total) | - | - | ✓ Redis cache |
| WebSocket real-time leaderboard | - | - | ✓ port 8789 |
| Notification system (in-app + email) | - | - | ✓ |
| Referral commissions | - | - | ✓ |
| Daily stats snapshot | - | ✓ | ✓ |
| Platform revenue tracking | - | - | ✓ |

---

## Security & Compliance

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| 18-layer defense in depth | ✓ | ✓ | ✓ |
| RBAC permission control | ✓ | ✓ | ✓ |
| Operation audit logs | ✓ | ✓ | ✓ |
| 8-platform source detection | ✓ | ✓ | ✓ |
| Redis sliding window rate limit | ✓ | ✓ | ✓ |
| KYC real-name verification | - | ✓ | ✓ |
| Risk engine (4 rules) | - | ✓ | ✓ |
| Payment callback signature verification | - | - | ✓ |

---

## Internationalization

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| Multi-language support | CN/EN | 4 languages | 4 languages |
| Translation table + cache | ✓ | ✓ | ✓ |
| Language auto-detection | ✓ | ✓ | ✓ |
| Country-differentiated config | - | - | ✓ 8 countries |

---

## Deployment & Operations

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| webman standalone deployment | ✓ | ✓ | ✓ |
| Docker Compose | - | - | ✓ 7 services |
| Nginx reverse proxy | - | - | ✓ |
| Crontab scheduled tasks | - | ✓ | ✓ |
| Prometheus monitoring | ✓ | ✓ | ✓ `/metrics` business gauges + event counters |
| Health checks | ✓ | ✓ | ✓ |
| hg/apidoc online docs | - | - | ✓ 41 controllers |

---

## Clients

| Feature | Lite | Standard | Full |
|------|--------|--------|--------|
| Flutter Web PC admin backend | ✓ 5 pages | ✓ 11 pages | ✓ 15 pages |
| Flutter Web PC user platform | ✓ 5 pages | ✓ 8 pages | ✓ 10 pages |
| HarmonyOS admin | - | ✓ login + dashboard | ✓ 8 pages `admin/apps/harmonyos/` |
| HarmonyOS C-end | - | - | ✓ 5 pages `apps/harmonyos/` |

---

## Database Tables

### Lite (19 tables)
```
管理后台 (7):  erik_admin_user, erik_admin_role, erik_admin_permission,
               erik_admin_user_role, erik_admin_role_permission,
               erik_operation_log, erik_system_config

平台核心 (12): erik_user, erik_user_wallet, erik_user_game_wallet,
               erik_game, erik_game_currency, erik_deposit_order,
               erik_withdraw_order, erik_exchange_record, erik_transaction,
               erik_payment_method, erik_announcement, erik_platform_config
```

### Standard additions (10 tables)
```
erik_user_identity, erik_user_oauth, erik_user_payment_account,
erik_user_session, erik_game_server, erik_game_play_log,
erik_withdraw_limit, erik_risk_rule, erik_risk_log, erik_stat_daily
```

### Full additions (13 tables)
```
erik_game_category, erik_game_category_rel, erik_leaderboard,
erik_coupon, erik_user_coupon, erik_language, erik_translation,
erik_country_config, erik_platform_revenue,
erik_notification, erik_referral, erik_referral_reward, erik_user_2fa
```

---

## API Endpoints

| Module | Lite | Standard | Full |
|------|--------|--------|--------|
| Auth | 3 | 3 | 7 (+OAuth×2 +2FA×5) |
| Wallet | 2 | 2 | 3 (+deposit callback) |
| Exchange | 4 | 4 | 4 |
| Withdrawal | 2 | 2 | 8 (+batch+limits+review) |
| Games | 3 | 4 | 7 (+servers+logs+search) |
| Users | 2 | 2 | 7 (+KYC+GDPR+privacy) |
| Admin | 18 | 25 | 79 |
| Operations tools | - | - | 30 (+leaderboards+coupons+notifications+referrals) |
| i18n | 2 | 2 | 4 (+country config) |
| **Total** | **38** | **54** | **129** |

---

## Ecosystem Expansion (v2.0) — New Additions

| Feature | Description |
|------|------|
| GameProvider abstraction layer | SelfProvider (DB transactions) + ThirdPartyProvider (HTTP+signature) |
| Provider API gateway | balance/bet/settle/refund callbacks + ProviderAuth middleware |
| Ticket system | C-end create/reply + admin handle/assign/close |
| Email verification | 6-digit code, Redis 10-minute expiry, 60s resend limit |
| Push notifications | PushService (FCM/APNs/Huawei push) |
| VIP system | 5 levels, EXP accumulation, auto upgrade, exchange discounts, withdrawal fee reduction, rate bonus |
| Achievement system | 12 built-in achievements, event-driven detection, progress tracking |
| Friend system | request/accept/reject/remove/search |
| DMs/chat | REST + WebSocket real-time messages (port 8790) |
| Event bus | Redis Pub/Sub; emit INCR `metrics:event_*`; consumer process `EventConsumer` landed |
| Feature flags | DB-based FeatureFlag; `inRollout`/`abTest` reads `feature.{name}_percent` |
| Webhook | - | - | ✓ 7 event types + Pub/Sub delivery |
| Chat | - | - | ✓ REST+WebSocket :8791 |
| Tournaments | - | - | ✓ FeatureFlag+tournament |
| Coupon conditions | - | - | ✓ min_deposit/first_user/game_id |
| Multi-level commissions | - | - | ✓ two-level profit sharing |
| SDK docs | - | - | ✓ PHP/Go/Python |
| Advanced analytics | Retention/D1-D30, conversion funnel, ARPU/ARPPU |

### New Data Tables (10 tables)
```
erik_ticket, erik_ticket_reply, erik_device_token,
erik_vip_level, erik_user_vip, erik_exp_log,
erik_achievement, erik_user_achievement,
erik_friend, erik_message
```

### New Provider API Endpoints (4)
```
POST /api/provider/balance  — 查询余额
POST /api/provider/bet      — 通知下注
POST /api/provider/settle   — 通知结算
POST /api/provider/refund   — 通知退款
```

### New C-end API Endpoints (8)
```
POST /api/verify/send-email    — 发送邮箱验证码
POST /api/verify/confirm-email — 确认邮箱
GET  /api/ticket/list             — 工单列表
POST /api/ticket/create           — 创建工单
GET  /api/ticket/{id}             — 工单详情
POST /api/ticket/{id}/reply       — 回复工单
GET  /api/user/vip-status         — VIP状态
GET  /api/user/achievements       — 成就列表
```

### New Admin API Endpoints (6)
```
GET  /admin/ticket/list          — 工单列表
GET  /admin/ticket/{id}          — 工单详情
POST /admin/ticket/{id}/reply    — 回复工单
POST /admin/ticket/{id}/close    — 关闭工单
POST /admin/ticket/{id}/assign   — 指定处理人
GET  /admin/analytics/retention  — 留存分析
GET  /admin/analytics/funnel     — 转化漏斗
GET  /admin/analytics/arpu       — ARPU趋势
GET  /admin/analytics/economy    — 经济指标
```
