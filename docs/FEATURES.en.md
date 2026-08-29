# Features Document
<!-- lang-nav -->

Languages: [中文](FEATURES.md) · **English** · [한국어](FEATURES.ko.md) · [Русский](FEATURES.ru.md) · [Deutsch](FEATURES.de.md) · [Français](FEATURES.fr.md) · [Español](FEATURES.es.md) · [Português](FEATURES.pt.md) · [हिन्दी](FEATURES.hi.md) · [العربية](FEATURES.ar.md) · [বাংলা](FEATURES.bn.md) · [Bahasa Indonesia](FEATURES.id.md) · [日本語](FEATURES.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Feature Overview

### Basic Version (MVP) — Completed

| Domain | Feature | Status |
|----|------|------|
| Users | Register/login/JWT/captcha | Completed |
| Wallet | Platform currency balance/transaction query | Completed |
| Deposit | Create deposit order (Stripe 125+ local APMs incl. Alipay/WeChat Pay / NOWPayments USDT TRC20·ERC20 / Coinbase USDC·BTC·ETH / PayPal callback) | Completed |
| Exchange | Platform currency⇄game currency (fixed rate + spread) | Completed |
| Withdrawal | Apply/query/global switch/auto review/manual review | Completed |
| Games | Admin CRUD/currency management/C-end list/detail/launch | Completed |
| Admin | Game management/withdrawal review/user management/payment management/announcement management | Completed |
| Dashboard | Platform dashboard (DAU/transactions/revenue/rankings) | Completed |
| Export | Excel export of users/transactions/withdrawals | Completed |
| i18n | Chinese/English switching, translation table, language detection middleware | Completed |
| Frontend | Flutter PC admin backend + C-end user platform (incl. i18n) | Completed |

### Standard Version — Completed

| Domain | Feature | Status |
|----|------|------|
| Users | OAuth login (Google/Facebook/Apple/Twitter/Microsoft/LinkedIn/GitHub) | Completed |
| Payments | Multi-channel auto callbacks (Stripe incl. Alipay/WeChat Pay APM / PayPal / NOWPayments IPN / Coinbase Webhook) | Completed |
| Games | Server management, game play log tracking | Completed |
| Withdrawal | KYC tiered limits (default/verified/vip) + fees | Completed |
| KYC | Real-name verification application + review | Completed |
| Risk control | IP blacklist/large-amount alerts/frequency/velocity detection | Completed |
| Statistics | Daily stats snapshots (users/deposits/withdrawals/exchanges/games) | Completed |
| Frontend | Admin: KYC review + risk logs / Platform: OAuth+KYC+game logs | Completed |

### Complete Version — Completed

| Domain | Feature | Status |
|----|------|------|
| Game lobby | 10 preset categories, category filtering, game-category relations | Completed |
| Leaderboards | Daily/weekly/monthly/total, Redis cache, multiple metrics | Completed |
| Coupons | Fixed amount + percentage discount, time/quantity limited, claim/usage tracking | Completed |
| Country config | 8 preset countries, differentiated payment/withdrawal methods, minimum deposit | Completed |
| Statistics | Daily stats snapshots + platform revenue tracking | Completed |
| Search | Elasticsearch full-text search (model layer integrated) | Completed |

### Production-Grade Upgrades — Completed

| Domain | Feature | Status |
|----|------|------|
| OAuth | Google/Facebook/Apple real token exchange | Completed |
| Payments | Payment callback signature verification (Stripe Webhook incl. Alipay/WeChat Pay APM, PayPal Webhook, NOWPayments IPN HMAC-SHA512, Coinbase HMAC-SHA256 base64 secret) | Completed |
| Captcha | poster-php click captcha | Completed |
| Notifications | In-app messages + email, auto notifications for deposit/withdrawal/KYC/coupon | Completed |
| 2FA | Google Authenticator TOTP + backup recovery codes | Completed |
| Referrals | Referral codes, signup rewards, deposit commissions | Completed |
| Search | ES search API + game suggestions + LIKE fallback | Completed |
| Leaderboards | WebSocket real-time push (port 8789) | Completed |
| CDN | Five-provider integration (Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS upload + purge + preload) | Completed |
| Deployment | Docker Compose 7 services + Nginx reverse proxy | Completed |
| Data | MySQL real-time aggregation analytics + joint/conditional probability | Completed |
| HarmonyOS | admin 8 pages; C-end `apps/harmonyos/` implements login/lobby/detail/wallet/profile (pointing to 8788) | Partially complete (project runs, device needs IP change) |
| API docs | hg/apidoc interactive documentation | Completed |
| One-click install | Browser install wizard: create admin, upgrade existing DB, install.lock prevents reinstall | Completed |
| Fault tolerance | CircuitBreaker + Retry + feature.provider_mock degradation switch | Completed |
| Payment methods | Admin CRUD + country visibility + amount range + currency restriction | Completed |
| CI | Auto-increment tag on push + GitHub Release | Completed |

### Ecosystem Expansion (v2.0) — Just Completed

| Domain | Feature | Status |
|----|------|------|
| Game integration | GameProvider abstraction layer (Self/ThirdParty) + HMAC-SHA256 signature | Completed |
| Game callbacks | Provider API gateway (balance/bet/settle/refund) + ProviderAuth middleware | Completed |
| Game sessions | Redis heartbeat + 15-minute timeout auto-settlement + GameSessionService | Completed |
| Ticket system | C-end create/reply + admin handle/assign/close, 5 ticket types | Completed |
| Email verification | 6-digit code, Redis 10-minute expiry, 60s resend limit | Completed |
| Push notifications | PushService (FCM/APNs/Huawei push) + DeviceToken model | Completed |
| VIP system | 5 levels (Normal/Silver/Gold/Platinum/Diamond) + EXP + auto upgrade | Completed |
| VIP benefits | Exchange discount 2-15%, withdrawal fee reduction 10-100%, rate bonus 0.1-1.0% | Completed |
| Achievement system | 12 built-in achievements; EventConsumer → AchievementService event-driven detection and VIP EXP | Completed |
| Friend system | Request/accept/reject/remove/search, pending/accepted/blocked states | Completed |
| DM/chat | REST DMs + WebSocket real-time messages (port 8790), friends only | Completed |
| Event bus | Redis Pub/Sub; emit + EventConsumer consuming achievements/Webhooks + metrics INCR | Completed |
| Feature flags | DB-based FeatureFlag; `inRollout`/`abTest` crc32 bucketing reads `feature.{name}_percent` | Completed |
| Advanced analytics | Retention/D1-D30, conversion funnel, ARPU/ARPPU, game currency economy metrics (MySQL real-time aggregation) | Completed |
| Webhooks | Subscription management + Redis Pub/Sub event delivery, 7 selectable events | Completed |
| Chat | REST DMs + WebSocket real-time messages (port 8791), friends only | Completed |
| Tournaments | Create/list/detail/join, FeatureFlag switch, leaderboards, player cap | Completed |
| Multi-level rebates | Two-level referral profit sharing, ReferralCommission model, configurable commission rates | Completed |
| Coupon conditions | min_deposit/first_user_only/game_id three condition types | Completed |
| SDK docs | Provider integration docs (PHP/Go/Python examples + 4 API endpoints) | Completed |
| Mini-game | Farm Match-3 P0 (domain engine + 4-level design, TypeScript/Vite/Vitest unit tests) | Completed |

## 2. C-end User Features

### 2.1 User Journey

```
注册 → 登录 → 邮箱/手机验证 → 浏览游戏大厅 → 进入游戏详情
                                           ↓
查看钱包 ← 玩游戏 ← 兑换游戏币 (VIP折扣) ← 充值平台币
    ↓
  提现 (VIP手续费减免) → 后台审核 → 到账
    ↓
好友系统 → 私信聊天 → 排行榜竞技 → 成就追踪
    ↓
工单支持
```

### 2.2 APIs

| Method | Path | Description | Auth |
|------|------|------|------|
| POST | /api/auth/register | User registration | No |
| POST | /api/auth/login | User login | No |
| POST | /api/auth/refresh | Refresh token | No |
| GET | /api/game/list | Game list | No |
| GET | /api/game/detail/{id} | Game detail | No |
| GET | /api/announcement/list | Announcement list | No |
| GET | /api/wallet/info | Wallet balance | Yes |
| GET | /api/wallet/transactions | Transaction records | Yes |
| POST | /api/deposit/create | Create deposit order | Yes |
| GET | /api/payment/methods | List payment methods (routed by country) | Yes |
| POST | /api/exchange/quote | Exchange quote (VIP discount) | Yes |
| POST | /api/exchange/buy | Buy game currency | Yes |
| POST | /api/exchange/sell | Sell game currency | Yes |
| POST | /api/withdraw/apply | Withdrawal application (VIP reduction) | Yes |
| POST | /api/game/launch | Launch game | Yes |
| GET | /api/game/play-logs | Game play logs | Yes |
| POST | /api/referral/apply | Apply referral code | Yes |
| POST | /api/verify/send-email | Send email verification code | Yes |
| POST | /api/verify/confirm-email | Confirm email | Yes |
| GET | /api/ticket/list | Ticket list | Yes |
| POST | /api/ticket/create | Create ticket | Yes |
| POST | /api/ticket/{id}/reply | Reply to ticket | Yes |

## 3. Admin Backend Features

### 3.1 APIs (New)

| Method | Path | Description |
|------|------|------|
| GET | /admin/dashboard/platform | Platform dashboard data |
| GET | /admin/analytics/overview | Platform overview (MySQL real-time aggregation) |
| GET | /admin/analytics/game-ranking | Game ranking |
| GET | /admin/analytics/dau-trend | DAU trend |
| GET | /admin/analytics/hourly-trend | Hourly trend |
| GET | /admin/analytics/action-distribution | Action distribution |
| GET | /admin/analytics/revenue | Revenue analysis |
| GET | /admin/analytics/conversion | Game conversion rate |
| GET | /admin/analytics/probability | Joint/conditional probability |
| GET | /admin/analytics/retention | Retention analysis D1/D3/D7/D30 |
| GET | /admin/analytics/funnel | Conversion funnel |
| GET | /admin/analytics/arpu | ARPU/ARPPU trend |
| GET | /admin/analytics/economy | Game currency economy metrics |
| GET | /admin/game/list | Game list |
| POST | /admin/game/create | Create game (incl. provider_config) |
| PUT | /admin/game/{id} | Edit game |
| GET | /admin/withdraw/orders | Withdrawal order list |
| PUT | /admin/withdraw/review | Review withdrawal |
| GET | /admin/ticket/list | Ticket list |
| GET | /admin/ticket/{id} | Ticket detail |
| POST | /admin/ticket/{id}/reply | Reply to ticket |
| POST | /admin/ticket/{id}/close | Close ticket |
| POST | /admin/ticket/{id}/assign | Assign handler |

## 4. Provider API (Game Provider Callbacks)

| Method | Path | Description | Auth |
|------|------|------|------|
| POST | /api/provider/balance | Query user balance | HMAC-SHA256 |
| POST | /api/provider/bet | Notify bet | HMAC-SHA256 |
| POST | /api/provider/settle | Notify settlement | HMAC-SHA256 |
| POST | /api/provider/refund | Notify refund | HMAC-SHA256 |

Signature algorithm: `HMAC-SHA256(game_id:timestamp:method:path:body, api_secret)`
Request headers: `X-Game-Id` + `X-Timestamp` + `X-Signature`
Time window: 5 minutes

## 5. VIP System

| Level | Cumulative EXP | Exchange Discount | Withdrawal Fee Reduction | Rate Bonus |
|------|---------|---------|-------------|---------|
| Normal | 0 | 0% | 0% | Baseline |
| Silver | 500 | 2% | 10% | +0.1% |
| Gold | 2,500 | 5% | 30% | +0.3% |
| Platinum | 12,500 | 10% | 50% | +0.5% |
| Diamond | 62,500 | 15% | 100% | +1.0% |

### Earning EXP

| Action | EXP |
|------|-----|
| Deposit 1 unit of currency | 10 |
| Daily login | 5 |
| Complete KYC | 50 |
| Invite a new user | 100 |
| Achieve an achievement | 10-100 |

## 6. Achievement List

| Achievement | Condition | Points |
|------|------|------|
| First Deposit | First deposit | 20 |
| Century Club | Cumulative deposits of 100 | 50 |
| High Roller | Cumulative deposits of 1000 | 100 |
| Trader | First exchange | 20 |
| Day Trader | 100 cumulative exchanges | 100 |
| Explorer | Played 3 games | 30 |
| Adventurer | Played 5 games | 50 |
| Conqueror | Played 10 games | 100 |
| Weekly Warrior | Logged in 7 consecutive days | 30 |
| Monthly Master | Logged in 30 consecutive days | 100 |
| Connector | Invited 1 friend | 30 |
| Influencer | Invited 10 friends | 100 |

## 7. Database Table List

### Ecosystem Expansion Additions (10 tables)

| Table | Description | Key Features |
|------|------|---------|
| game_ticket | Tickets | user_id+type+status index, assigned_to |
| game_ticket_reply | Ticket replies | ticket_id index, is_admin distinction |
| game_device_token | Device tokens | user_id+platform+token unique index |
| game_vip_level | VIP level definitions | level unique index, benefits JSON |
| game_user_vip | User VIP records | user_id unique index, level+exp+total_exp |
| game_exp_log | EXP logs | user_id+source composite index |
| game_achievement | Achievement definitions | key unique index, condition_json JSON |
| game_user_achievement | User achievements | user_id+achievement_id unique index |
| game_friend | Friend relations | user_id+friend_id unique index |
| game_message | DMs | from_user_id+to_user_id / to_user_id+is_read |

### Table Structure Changes

| Table | Change |
|------|------|
| game_game | +provider_config (JSON) |
| game_game_play_log | +round_id, +bet_amount, +win_amount |

**Total: 43 tables in install.sql** (the 10 ecosystem expansion tables live in `install/`, not merged into install.sql). Models are not shared: admin 46 / service 44, one copy each.

## 8. Test Coverage

| Test File | Cases | Coverage |
|---------|--------|---------|
| PlatformTest | 56 | bcmath precision/exchange calculations/withdrawal fees/limits/risk control/coupons/KYC/i18n |
| BackendEnhancementTest | 23 | encryption service/Hashids/Snowflake |
| CaptchaTest | 7 | captcha generation/verification |
| EncryptionServiceTest | 6 | AES encryption/decryption/masking |
| EnvConfigTest | 4 | environment variable configuration |
| HashidsServiceTest | 8 | ID encode/decode round-trip |
| SnowflakeServiceTest | 6 | ID generation uniqueness |

**Total: admin ~132 cases / 8 files; service 3 cases (WebhookUrlSafety + EventBusMessageFormat). service is not included in CI failure blocking.**

---

> **Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz**
