# 项目全面规划 (Project Plan)
<!-- lang-nav -->

Languages: [中文](PROJECT-PLAN.md) · **English** · [한국어](PROJECT-PLAN.ko.md) · [Русский](PROJECT-PLAN.ru.md) · [Deutsch](PROJECT-PLAN.de.md) · [Français](PROJECT-PLAN.fr.md) · [Español](PROJECT-PLAN.es.md) · [Português](PROJECT-PLAN.pt.md) · [हिन्दी](PROJECT-PLAN.hi.md) · [العربية](PROJECT-PLAN.ar.md) · [বাংলা](PROJECT-PLAN.bn.md) · [Bahasa Indonesia](PROJECT-PLAN.id.md) · [日本語](PROJECT-PLAN.ja.md)


> Generated: 2026-08-16 · Based on a read-only audit by a 6-member team (researcher/architect/backend-dev/frontend-dev/tester/reviewer) plus hands-on verification of key claims
> Covers: current state summary / issues and risks / P0-P1-P2 roadmap / doc fixes / quality gates

---

## 1. Project Current State

**Global Game Aggregation Platform** — PHP 8.3 + webman v2, dual-application monorepo:
`admin/`(8787 admin backend) + `service/`(8788 C-end) + `apps/`(Flutter + HarmonyOS) + `install/`(install wizard, 43 tables).

| Dimension | Measured Scale |
|------|---------|
| Controllers | admin 32 + service 30 = 62 |
| API endpoints | ~149 (admin 103 / service 88, incl. Webhook/Provider callbacks) |
| Data models | admin 46 / service 44, admin/service **duplicated copies** (no shared layer) |
| Tests | 132 cases / 8 files (admin project), service project **zero tests** |
| Version | v1.1 (2026-08-07): Redis plugin, analytics service, Redis degradation, test fixes |

Implemented capabilities: JWT+RBAC, wallet optimistic lock, deposits (Stripe/PayPal signature verification), exchange spread, withdrawal review + PayPal payout, game CRUD/Provider gateway (HMAC), coupons/VIP/achievements/tickets/referral commissions/2FA/social (friends/chat WS)/tournaments/Webhooks/push (FCM/APNs/Huawei)/i18n bilingual.

---

## 2. Issues and Risks (Hands-On Verified)

### CRITICAL — Fund Safety

| # | Issue | Location |
|---|------|------|
| C1 | Payment callback `provider` comes from the client; when not stripe/paypal, **signature verification is completely skipped** — forged callbacks credit directly | service/.../PaymentController.php:36-42 |
| C2 | Signature verification fails open: `STRIPE_WEBHOOK_SECRET` not configured → `return true`; any PayPal exception → `return true`. Attack chain: create deposit order → forge callback → unlimited deposits | PaymentController.php:167, 225 |
| C3 | `JWT_SECRET_KEY` defaults to the public hardcoded key `open-admin-jwt-secret-change-in-production`; production without the env var can forge admin tokens | admin+service config/plugin/erikwang2013/jwt/jwt.php:12 |

### HIGH — Correctness/Consistency

| # | Issue | Location |
|---|------|------|
| H1 | AnalyticsService AnalyticsController: all 12 methods implemented but **zero routes**, all 404 dead code, while VERSIONS.md claims they were delivered | admin/config/route.php (0 analytics entries) |
| H2 | Event bus broken link: 4 emit call sites (game.played/withdraw.completed/exchange.completed/referral.applied), `subscribe()` has no process registered, events are lost on publish; VIP/achievement/notification engines all dangling | admin+service app/event/EventBus.php |
| H3 | common/ and model/ duplicated twice and already drifted (DepositLogService has two different copies, User.php inconsistent), single-point fixes become double work. **common/service already extracted** to `packages/platform-common` (erik/platform-common, former common-php merged in); model and app/common wrappers still duplicated | admin/common vs service/common → packages/platform-common |
| H4 | ~~HarmonyOS C-end `apps/harmonyos/` is an empty directory, 0 pages vs the 5 pages VERSIONS.md claims~~ — landed (2026-08-18: 5 pages implemented in `apps/harmonyos/`) | apps/harmonyos/ |
| H5 | Stripe callback does not validate `t=` timestamp tolerance (replayable), and credited amount not checked against the gateway's actual paid amount | PaymentController.php:191-194 |
| H6 | Apple id_token only base64-decodes the payload, no signature verification, no aud/iss/exp validation, cross-app identity confusion risk | OAuthController.php:376-380 |

### MEDIUM — Reliability/Implementation Defects

| # | Issue |
|---|------|
| M1 | 2FA defect double hit: `/api/2fa/verify` public with no per-user attempt lockout (brute-force oracle); TOTP uses the Base32 string directly as HMAC key (not decoded), mismatches Authenticator → **2FA actually unusable** |
| M2 | Withdrawal review/payout is check-then-act with no atomic state update, concurrent duplicate payouts possible; no dual review |
| M3 | Webhook callback URL only validated with filter_var, can point at internal IPs (SSRF), dispatch POSTs to arbitrary URLs |
| M4 | Withdrawal daily/monthly limits "query then insert" non-atomic, limits can be exceeded concurrently |
| M5 | Redis failure fails open with no unified abstraction: JWT blacklist logout ineffective, rate limiting silently disabled; degradation gaps: PayoutService::getAccessToken, ChatWebSocket brpop, OAuth state get/set |
| M6 | ClickHouse unused: probability computation is actually MySQL real-time COUNT(DISTINCT) + subquery JOIN, O(n²) risk on large tables; composer placeholder dependency without capability |
| M7 | Queue half-done: admin/app/queue has ComputeDailyStats + 3 ES tasks, but webman/queue not installed, process.php has no registration, all without callers |
| M8 | Dead code: Vip/Achievement/Notification/FeatureFlag services zero callers; DepositLogService::log() empty implementation; Test model leftover; retention algorithm rough single-cohort estimate |

### LOW
- Withdrawals can be paid to any PayPal email without 2FA/KYC enforcement; review notes enter notification text (XSS surface)
- Docs out of sync with reality: install.sql 43 tables vs docs previously saying 52; docker-compose 7 services vs FEATURES.md previously saying 8; "Shared Models 34" untrue (admin 46 / service 44, one copy each, no shared layer). CHANGELOG updated, see `docs/CHANGELOG.md`.

### Passed Items (Security review confirmed no issues)
Wallet optimistic lock + version conditional update correct; callback idempotency `where status=pending` conditional update correct; all ORM, no direct SQL concatenation; .env not in git; all admin routes behind AdminAuth+RBAC default-deny; OAuth state validation + single consumption correct.

> **2026-08-18 fix status**: C1/C2/C3/H1/H5/H6 fixed; H2 event bus: `process.php` now registers `event-consumer` and the consumer class `EventConsumer` landed, emits have consumers; M1 Base32 + per-user lockout fixed; M2 withdrawal state atomicity + optional dual review done; M3 Webhook SSRF blocked; M4 Redis user lock on withdrawal apply done; M5 partially done (RateLimit fail-closed); P2-19 business metrics + FeatureFlag rollout landed. The issue list is retained as historical audit conclusions.

---

## 3. Roadmap

### P0 — Fund Safety + Correctness (First, Blocks Launch)

1. **Payment callback fail-closed**: provider whitelist (stripe/paypal only) + missing keys must reject with 500 + PayPal exceptions must reject (C1/C2) — ✅ Done (2026-08-18: provider whitelist + cross-channel impersonation validation + optional source IP validation + transactional callback crediting)
2. **JWT startup validation**: refuse to start when env has no `JWT_SECRET_KEY` (C3) — ✅ Done (2026-08-18: refuses to start when JWT_SECRET_KEY missing or default `open-admin-jwt-secret-change-in-production`, consistent across admin/service)
3. **Mount analytics routes**: register 12 analytics routes + permission points, honor the VERSIONS.md promise (H1) — ✅ Done (2026-08-18: admin/config/route.php registers 12 `/admin/analytics/*` routes)
4. **Event bus end-to-end**: register a resident subscriber process, or switch to synchronous direct calls; persist events + retry on failure (H2) — ✅ Done (2026-08-18: emit/consume INCR Redis counters; `service/config/process.php` registers `event-consumer`, `service/app/process/EventConsumer.php` consumes events)
5. **Apple id_token verification**: JWKS validation + aud/iss/exp (H6) — ✅ Done (2026-08-18: RS256 JWKS + kid refresh + aud/iss/exp)
6. **Stripe replay and amount verification**: timestamp tolerance + compare against gateway amount (H5) — ✅ Done (2026-08-18: t= timestamp ±300s anti-replay + bccomp precision amount check + missing secret/webhook_id or verification exceptions all rejected)

### P1 — Reliability + Consistency

7. **Shared layer dedup**: extract common/model as composer path repo (or symlink), eliminate double drift (H3) — 🔶 Partially done (2026-08-18: `common/service` extracted to single `packages/platform-common` / `erik/platform-common` path repo (former `common-php` merged in), referenced by admin+service; model and host-bound `app/common` wrappers still duplicated, see `packages/platform-common/DUAL_MODELS.md`)
8. **Unified Redis degradation wrapper**: explicit fail policy + alerts not silent; add PayoutService/OAuth/ChatWebSocket fallbacks (M5) — 🔶 Partially done (RateLimit fail-closed landed: rate limiting rejects instead of silently allowing when Redis fails; rest not done)
9. **webman/queue wiring**: carry events and webhook delivery (consumer retries, dead letters), enable or delete ComputeDailyStats/ES tasks (M7) — ⬜ Not done
10. **2FA fix**: Base32 decoding + verify requires login state + per-user attempt lockout (M1) — ✅ Done (2026-08-18: RFC 4648 Base32 decoded then HMAC; `/api/2fa/verify` locks for 15 minutes after 5 failures, fail-closed on Redis failure)
11. **Withdrawal atomicity**: conditional updates for review/payout + dual review; limit Redis Lua/unique constraint (M2/M4) — 🔶 Partially done (2026-08-18: pending→approved/rejected, approved→processing conditional UPDATE; optional dual review `withdraw.require_dual_review`; Redis user lock on apply. No Lua limits/unique constraint)
12. **Webhook SSRF blocking**: reject internal/reserved addresses (M3) — ✅ Done (2026-08-18: `isSafeWebhookUrl()` https public internet only)
13. **ClickHouse either-or**: real integration or remove the dependency + revise docs (M6) — ⬜ Not done
14. **Dead code cleanup**: wire or delete Vip/Achievement/Notification/FeatureFlag; delete Test model; DepositLog audit persisted (M8) — 🔶 Partially done (2026-08-18: Test model deleted, DepositLog audit persisted; Vip/FeatureFlag/Notification have callers; AchievementService called by EventConsumer)
15. **service tests + CI gate**: integration tests for callback verification/withdrawal flow/Redis degradation/probability computation/optimistic-lock concurrency; phpunit failure blocks; service into CI (currently `|| echo warning` allows failure) — 🔶 Partially done (service has WebhookUrlSafety / EventBusMessageFormat; included in CI `phpunit-service` job, failure blocks)

**Additional items completed this round (2026-08-18, outside the original numbering)**:
- **Table prefix fix**: 52 models removed hardcoded `erik_` prefix, eliminating the `erik_erik_` double prefix; DB prefix uniformly provided by config/database.php `prefix=erik_`, install.sql unchanged
- **refresh token rewrite**: service AuthController refresh token logic rewritten
- **DepositLogService service port**: service/common/service/DepositLogService.php completed (eliminating one admin/service duplicate drift)

### P2 — Observability / Expansion / Experience

16. **HarmonyOS C-end** implement 5 pages from scratch (login/lobby/detail/wallet/profile) (H4) — ✅ Done (2026-08-18: `apps/harmonyos/entry/src/main/ets/pages/` 5 pages in repo)
17. **Frontend completion**: 2FA verification page, coupon/leaderboard/notification entries, ES search UI; merge main.dart/app_pages.dart route sources; real OAuth callbacks; frontend AES transport layer
18. **Probability computation to ClickHouse** or MySQL materialized stats tables + cache; retention recomputed by real cohort
19. **Prometheus business metrics** (event delivery/consumption rates, queue depth) + rollout AB split middleware (reusing FeatureFlag) — 🔶 Partially done (2026-08-18: `GET /metrics` pending withdrawals/today's confirmed deposits/event emit·consume counters; FeatureFlag `inRollout`/`abTest` crc32 bucketing. Queue depth not done)
20. **WebSocket data pipeline closed loop**: leaderboard/chat persistence confirmed
21. **Doc alignment**: fix table counts/service counts/shared layer descriptions, align API docs with implementation, add CHANGELOG — ✅ Done (2026-08-18: see `docs/CHANGELOG.md`, FEATURES/VERSIONS/PROJECT-PLAN/audit report §10)

---

## 4. Quality Gates (Team Collaboration)

- Every code change: admin full test suite `vendor/bin/phpunit` must pass (drop `|| echo warning`)
- New sensitive paths (payment/withdrawal/auth) must ship with tests
- When changing common/model, sync both admin+service sides (until shared layer lands)
- Review report focus: ProviderAuth signature, AES encryption, ProbabilityService handwritten SQL

## 5. Team

The game-platform team (6 members: researcher/architect/backend-dev/frontend-dev/tester/reviewer) is ready to execute P0 directly.
