# Changelog
<!-- lang-nav -->

Languages: [中文](CHANGELOG.md) · **English** · [한국어](CHANGELOG.ko.md) · [Русский](CHANGELOG.ru.md) · [Deutsch](CHANGELOG.de.md) · [Français](CHANGELOG.fr.md) · [Español](CHANGELOG.es.md) · [Português](CHANGELOG.pt.md) · [हिन्दी](CHANGELOG.hi.md) · [العربية](CHANGELOG.ar.md) · [বাংলা](CHANGELOG.bn.md) · [Bahasa Indonesia](CHANGELOG.id.md) · [日本語](CHANGELOG.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Human-readable change log. PHP does not import this file. Corresponds to PROJECT-PLAN P2-21.

## [1.1] — 2026-08-07

- Redis plugin integration, analytics services, Redis degradation, test fixes.

## [1.1] security / ops — 2026-08-18

### Security

- Payment callback: provider whitelist (stripe/paypal), fail-closed signature verification, amount verification, transactional crediting, Stripe timestamp ±300s anti-replay.
- JWT: refuses to start when `JWT_SECRET_KEY` / `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY` are missing or set to default values.
- Apple id_token: JWKS (RS256) signature verification + aud/iss/exp.
- Webhook: only https public URLs, rejects intranet/reserved addresses (SSRF).
- 2FA: TOTP HMAC uses the RFC 4648 Base32-decoded key; `/api/2fa/verify` locks per-user after failures (5 times / 15 minutes, fail-closed on Redis failure).
- Withdrawal: atomic status flip for review/payout conditions; optional dual review (`withdraw.require_dual_review`); Redis user lock on the application side to prevent limit concurrency bypass.
- Rate limiting: fail-closed on Redis failure.

### Availability

- Admin analytics service: 12 `/admin/analytics/*` routes mounted.
- Models no longer have hardcoded `game_` prefixes; DepositLog audit persisted to DB; Test model removed.

### Observability

- `GET /metrics` adds pending withdrawals, today's confirmed deposits (COUNT query with Redis 30s cache), event emit/consume counters, `memory_usage`, `info version=1.1`.
- FeatureFlag: `inRollout` / `abTest` bucket by crc32 reading `feature.{name}_percent`.
- EventBus `emit` / `consume` INCR `metrics:event_emit_total` / `metrics:event_consume_total` in Redis.

### Client / Shared (completed the same day)

- Flutter Platform: `app_pages.dart` route table; added 2FA setup/verification, coupons, leaderboards, notifications, OAuth callback pages; lobby entry hooked to navigation.
- HarmonyOS C-end: `apps/harmonyos/` five pages (login/lobby/detail/wallet/profile), default `BASE_URL` points to service `8788`.
- Shared layer: `packages/platform-common` (`erik/platform-common` path repo) extracts DepositLog / GameDashboard / Probability / GamePlayLog; models still duplicated.
- ClickHouse: composer dependency removed; analytics continues with MySQL real-time aggregation.
- CI: admin / service run phpunit in separate jobs, failures block the pipeline.

### Remaining Gaps

- admin/service **models** are still duplicated (only part of `common/service` moved into the path package).
- `webman/queue` not wired up; probability/retention not migrated to OLAP.
- Parts of PROJECT-PLAN / VERSIONS / audit reports may still lag behind this CHANGELOG; this file and the disk state are authoritative.

## [1.1] resilience — 2026-08-27

### Stability

- Shared layer adds `CircuitBreaker` (Redis-backed state, threshold 5 / 30s window, fail-open when Redis is down) and `Retry` (exponential backoff, network-class exceptions only, max 5 attempts), in `packages/platform-common/src/`.
- Degradation switch `feature.provider_mock`: PushService (FCM/APNs/HarmonyOS), PayoutService (PayPal), ThirdPartyProvider short-circuit when `on`, skipping real network calls.
- Fixed 11 `getenv($name, '')` second-argument type defects (TypeError under strict_types); moved PushService mock check into try/catch.
- New tests: CircuitBreakerTest / RetryTest / ResilienceMockTest; service suite 45 → 60 cases all green (report: [test-reports/resilience.md](test-reports/resilience.md)).
