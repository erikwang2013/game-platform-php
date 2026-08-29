# Changelog
<!-- lang-nav -->

Languages: [中文](CHANGELOG.md) · [English](CHANGELOG.en.md) · **한국어** · [Русский](CHANGELOG.ru.md) · [Deutsch](CHANGELOG.de.md) · [Français](CHANGELOG.fr.md) · [Español](CHANGELOG.es.md) · [Português](CHANGELOG.pt.md) · [हिन्दी](CHANGELOG.hi.md) · [العربية](CHANGELOG.ar.md) · [বাংলা](CHANGELOG.bn.md) · [Bahasa Indonesia](CHANGELOG.id.md) · [日本語](CHANGELOG.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

사람이 읽을 수 있는 변경 기록. PHP는 이 파일을 import하지 않습니다. PROJECT-PLAN P2-21에 해당합니다.

## [1.1] — 2026-08-07

- Redis 플러그인 연동, 분석 서비스, Redis 디그레이드, 테스트 수정.

## [1.1] security / ops — 2026-08-18

### 보안

- 결제 콜백: provider 화이트리스트(stripe/paypal), fail-closed 서명 검증, 금액 대조, 입금 트랜잭션화, Stripe 타임스탬프 ±300s 리플레이 방지.
- JWT: `JWT_SECRET_KEY` / `ADMIN_JWT_SECRET_KEY` / `SERVICE_JWT_SECRET_KEY` 누락 또는 기본값이면 기동 거부.
- Apple id_token: JWKS(RS256) 서명 검증 + aud/iss/exp.
- Webhook: https 공개 URL만 허용, 내부/예약 주소 거부(SSRF).
- 2FA: TOTP HMAC은 RFC 4648 Base32 디코딩된 키 사용; `/api/2fa/verify` 사용자별 실패 잠금(5회 / 15분, Redis 장애 시 fail-closed).
- 출금: 심사/지급 조건의 UPDATE 상태 원자적 전환; 선택적 이중 심사(`withdraw.require_dual_review`); 신청 쪽 Redis 사용자 잠금으로 한도 동시성 돌파 방지.
- 레이트 리밋: Redis 장애 시 fail-closed.

### 가용성

- admin 분석 서비스 12개 `/admin/analytics/*` 라우트 마운트.
- 모델에서 하드코딩된 `game_` 접두사 제거; DepositLog 감사 로그 저장; Test model 삭제.

### 관측성

- `GET /metrics`에 심사 대기 출금, 오늘 확정 충전(COUNT 쿼리 Redis 30s 캐시), 이벤트 emit/consume 카운트, `memory_usage`, `info version=1.1` 추가.
- FeatureFlag: `inRollout` / `abTest`가 crc32 버킷으로 `feature.{name}_percent` 읽기.
- EventBus `emit` / `consume`이 Redis `metrics:event_emit_total` / `metrics:event_consume_total`을 INCR.

### 클라이언트 / 공유 (동일 날짜 보완)

- Flutter Platform: `app_pages.dart` 라우트 테이블; 2FA 설정/검증, 쿠폰, 리더보드, 알림, OAuth 콜백 페이지 보완; 로비 진입점에 내비게이션 연결.
- HarmonyOS C단: `apps/harmonyos/` 5페이지(로그인/로비/상세/지갑/개인), 기본 `BASE_URL`이 service `8788`을 가리킴.
- 공유 레이어: `packages/platform-common`(`erik/platform-common` path repo)에 DepositLog / GameDashboard / Probability / GamePlayLog 추출; 모델은 여전히 2벌.
- ClickHouse: composer 의존성 제거; 분석은 MySQL 실시간 집계로 계속.
- CI: admin / service가 job을 나눠 phpunit 실행, 실패 시 차단.

### 여전히 남은 격차

- admin/service **모델**이 여전히 2벌(일부 `common/service`만 path 패키지로 편입).
- `webman/queue` 미연결; 확률/리텐션이 OLAP로 미이전.
- PROJECT-PLAN / VERSIONS / 감사 보고서 일부 문단이 본 CHANGELOG보다 늦을 수 있으며, 본 파일과 디스크가 기준입니다.

## [1.1] resilience — 2026-08-27

### 안정성

- 공유 레이어에 `CircuitBreaker`(Redis 상태 저장, 임계값 5 / 창 30초, Redis 중단 시 fail-open)와 `Retry`(지수 백오프, 네트워크 예외만 재시도, 최대 5회) 추가, `packages/platform-common/src/`.
- 디그레이션 스위치 `feature.provider_mock`: PushService(FCM/APNs/HarmonyOS), PayoutService(PayPal), ThirdPartyProvider가 `on`이면 단락되어 실제 네트워크 호출 생략.
- `getenv($name, '')` 두 번째 인자 타입 결함 11곳 수정(strict_types에서 TypeError); PushService mock 확인을 try/catch로 이동.
- 신규 테스트: CircuitBreakerTest / RetryTest / ResilienceMockTest; service 스위트 45 → 60 케이스 전부 통과(보고서: [test-reports/resilience.md](test-reports/resilience.md)).

## [1.1] payments — 2026-08-29

- 다중 결제 게이트웨이: Stripe Checkout / NOWPayments(USDT TRC20+ERC20) / Coinbase Commerce(USDC) + Alipay/WeChat Pay(Stripe Checkout APM) 연동.
- 관리자 결제수단 CRUD + 국가별 표시 + 금액 범위; 충전 주문 생성 시 checkout_url / expires_at 즉시 기록.
- 새 마이그레이션 install/migrations/2026_08_29_multi_payment.sql(실행 필요).

## [1.1] features — 2026-08-29

- Farm 매치-3 P0 미니게임: 도메인 엔진 + 4레벨 설계 + Vitest 단위 테스트(`game/xiaoxiaole/`).
- 원클릭 설치 마법사: 브라우저에서 관리자 생성, 기존 DB 업그레이드(HY093 바인딩 파라미터 불일치, Unknown column 'countries' 수정), install.lock으로 재설치 방지.
- CI: push 시 자동 증분 tag + GitHub Release 게시.
- 인프라: 데이터베이스 명칭 game-platform으로 변경, `game_` 테이블 접두사 통일.
- 문서 동기화: FEATURES.md 13개 언어로 내결함성(서킷 브레이커/재시도/폴백 스위치), 결제수단 관리 CRUD, 미니게임, 원클릭 설치, CI 행 보완(위 [1.1] resilience / payments 항목 대응).
