# 프로젝트 종합 계획 (Project Plan)
<!-- lang-nav -->

Languages: [中文](PROJECT-PLAN.md) · [English](PROJECT-PLAN.en.md) · **한국어** · [Русский](PROJECT-PLAN.ru.md) · [Deutsch](PROJECT-PLAN.de.md) · [Français](PROJECT-PLAN.fr.md) · [Español](PROJECT-PLAN.es.md) · [Português](PROJECT-PLAN.pt.md) · [हिन्दी](PROJECT-PLAN.hi.md) · [العربية](PROJECT-PLAN.ar.md) · [বাংলা](PROJECT-PLAN.bn.md) · [Bahasa Indonesia](PROJECT-PLAN.id.md) · [日本語](PROJECT-PLAN.ja.md)


> 생성일: 2026-08-16 · 6인 팀 (researcher/architect/backend-dev/frontend-dev/tester/reviewer) 기반 읽기 전용 점검 + 핵심 진단 실측 검증
> 범위: 현황 요약 / 문제와 리스크 / P0-P1-P2 로드맵 / 문서 수정 / 품질 게이트

---

## 一、프로젝트 현황

**글로벌 게임 통합 플랫폼** — PHP 8.3 + webman v2, 이중 애플리케이션 monorepo:
`admin/`(8787 관리 백오피스) + `service/`(8788 C단) + `apps/`(Flutter + HarmonyOS) + `install/`(설치 마법사 43 테이블).

| 차원 | 실측 규모 |
|------|---------|
| 컨트롤러 | admin 32 + service 30 = 62 |
| API 엔드포인트 | ~149 (admin 103 / service 88, Webhook/Provider 콜백 포함) |
| 데이터 모델 | admin 46 / service 44, admin/service **중복 복사** (공유 레이어 없음) |
| 테스트 | 132 케이스 / 8 파일 (admin 프로젝트), service 프로젝트 **테스트 0개** |
| 버전 | v1.1 (2026-08-07): Redis 플러그인, 분석 서비스, Redis 디그레이드, 테스트 수정 |

구현 완료 능력: JWT+RBAC, 지갑 낙관적 잠금, 충전(Stripe/PayPal/NowPayments/Coinbase 검증), 환전 차액, 출금 심사+PayPal 지급, 게임 CRUD/Provider 게이트웨이(HMAC), 쿠폰/VIP/업적/티켓/추천 커미션/2FA/소셜(친구/채팅 WS)/토너먼트/Webhook/푸시(FCM/APNs/华为)/i18n 이중 언어.

---

## 二、문제와 리스크（실측 검증 완료）

### CRITICAL — 자금 안전

| # | 문제 | 위치 |
|---|------|------|
| C1 | 결제 콜백 `provider`를 클라이언트가 전달, stripe/paypal 외에는 **검증을 완전히 건너뜀**, 위조 콜백으로 바로 입금 | service/.../PaymentController.php:36-42 |
| C2 | 검증 fail-open: `STRIPE_WEBHOOK_SECRET` 미설정 → `return true`; PayPal 모든 예외 → `return true`. 공격 체인: 자체 충전 주문 생성→위조 콜백→무제한 충전 | PaymentController.php:167, 225 |
| C3 | `JWT_SECRET_KEY` 기본값이 공개 하드코딩 키 `open-admin-jwt-secret-change-in-production`으로 폴백, 프로덕션 env 미설정 시 관리자 Token 위조 가능 | admin+service config/plugin/erikwang2013/jwt/jwt.php:12 |

### HIGH — 정확성/일관성

| # | 문제 | 위치 |
|---|------|------|
| H1 | 분석 서비스 AnalyticsController 12개 메서드 전부 구현됐지만 **라우트 0개**, 전부 404 죽은 코드, VERSIONS.md는 배포 완료라 주장 | admin/config/route.php (analytics 0건) |
| H2 | 이벤트 버스 단절: emit 호출 4곳(game.played/withdraw.completed/exchange.completed/referral.applied), `subscribe()`에 등록된 프로세스 없음, 이벤트 발행 즉시 유실; VIP/업적/알림 엔진 전부 공중에 떠 있음 | admin+service app/event/EventBus.php |
| H3 | common/과 model/이 이중 복사되고 이미 분기됨 (DepositLogService 두 파일 내용 다름, User.php 불일치), 단일 수정이 이중 작업. **common/service는 이미 추출됨** `packages/platform-common`（erik/platform-common, 기존 common-php는 통합됨）; model과 app/common 래퍼는 여전히 이중 | admin/common vs service/common → packages/platform-common |
| H4 | ~~HarmonyOS C단 `apps/harmonyos/`가 빈 디렉터리, 0페이지 vs VERSIONS.md의 5페이지 주장~~ — 이미 구축됨（2026-08-18: 5페이지 구현이 `apps/harmonyos/`에 있음） | apps/harmonyos/ |
| H5 | Stripe 콜백이 `t=` 타임스탬프 허용 오차 미검증（리플레이 가능）, 입금 금액도 게이트웨이 실제 결제 금액과 대조하지 않음 | PaymentController.php:191-194 |
| H6 | Apple id_token은 payload만 base64 디코딩, 서명 검증 없음, aud/iss/exp 미검증, 크로스 앱 신원 혼동 리스크 | OAuthController.php:376-380 |

### MEDIUM — 신뢰성/구현 결함

| # | 문제 |
|---|------|
| M1 | 2FA 결함 이중: `/api/2fa/verify` 공개·사용자별 시도 잠금 없음（oracle 공격）; TOTP가 Base32 문자열을 그대로 HMAC 키로 사용（미디코딩）, Authenticator와 불일치 → **2FA 실제로 사용 불가** |
| M2 | 출금 심사/지급이 check-then-act로 원자적 상태 업데이트 없음, 동시성으로 중복 지급 가능; 이중 심사 없음 |
| M3 | Webhook 콜백 URL을 filter_var로만 검증, 내부 IP 지정 가능（SSRF）, dispatch가 임의 URL로 POST |
| M4 | 출금 일/월 한도가 "조회 후 삽입" 방식으로 비원자적, 동시성으로 한도 돌파 가능 |
| M5 | Redis 장애 fail-open 통일 추상화 없음: JWT 블랙리스트 로그아웃 무효화, 레이트 리밋 조용히 무력화; 디그레이드 공백: PayoutService::getAccessToken, ChatWebSocket brpop, OAuth state 저장·조회 |
| M6 | ClickHouse 전혀 미사용: 확률 계산이 실제로 MySQL 실시간 COUNT(DISTINCT)+서브쿼리 JOIN, 대형 테이블 O(n²) 리스크; composer 점유 의존성이 능력 없음 |
| M7 | 큐 반제품: admin/app/queue에 ComputeDailyStats + ES 작업 3개 있지만 webman/queue 미설치, process.php 미등록, 호출자 전무 |
| M8 | 죽은 코드: Vip/Achievement/Notification/FeatureFlag 서비스 호출자 0명; DepositLogService::log() 빈 구현; Test model 잔존; 리텐션 알고리즘 단일 cohort 추정이 조악 |

### LOW
- 출금에 2FA/KYC 강제 없이 임의 PayPal 이메일로 지급 가능; 심사 메모가 알림 문구에 들어감（XSS 노출면）
- 문서와 실제 불일치: install.sql 43 테이블 vs 문서의 52 표기; docker-compose 7 서비스 vs FEATURES.md의 8 표기; "공유 Model 34" 부실（admin 46 / service 44 각각 1벌, 공유 레이어 없음）. CHANGELOG 보완 완료, `docs/CHANGELOG.md` 참조.

### 통과 항목（보안 심사 확인, 문제 없음）
지갑 낙관적 잠금+버전 조건 업데이트 정확; 콜백 멱등 `where status=pending` 조건 업데이트 정확; 전부 ORM으로 직접 SQL 조합 없음; .env git 미포함; admin 전체 라우트에 AdminAuth+RBAC 기본 거부; OAuth state 검증+단일 소비 정확.

> **2026-08-18 수정 상태**: C1/C2/C3/H1/H5/H6 수정 완료; H2 이벤트 버스: `process.php`에 `event-consumer` 등록, 소비 클래스 `EventConsumer` 구축 완료, emit에 소비자 존재; M1 Base32 + 사용자별 잠금 수정 완료; M2 출금 상태 원자화 + 선택적 이중 심사 완료; M3 Webhook SSRF 차단 완료; M4 출금 신청 Redis 사용자 잠금 완료; M5 일부 완료（RateLimit fail-closed）; P2-19 비즈니스 지표 + FeatureFlag 그레이 구축 완료. 문제 목록은 역사적 심사 결론으로 유지.

---

## 三、로드맵

### P0 — 자금 안전 + 정확성（우선, 출시 차단）

1. **결제 콜백 fail-closed**: provider 화이트리스트（stripe/paypal/nowpayments/coinbase만）+ 키 누락 시 반드시 500 거부 + PayPal 예외 반드시 거부（C1/C2） — ✅ 완료（2026-08-18: provider 화이트리스트 + 크로스 채널 도용 검증 + 소스 IP 선택 검증 + 콜백 입금 트랜잭션화）
2. **JWT 기동 검증**: env에 `JWT_SECRET_KEY` 없으면 기동 거부（C3） — ✅ 완료（2026-08-18: JWT_SECRET_KEY 누락 또는 기본값 `open-admin-jwt-secret-change-in-production`이면 기동 거부, admin/service 일관）
3. **분석 서비스 라우트 연결**: analytics 12 라우트 + 권한 포인트 등록, VERSIONS.md 약속 수정（H1） — ✅ 완료（2026-08-18: admin/config/route.php에 `/admin/analytics/*` 라우트 12개 등록）
4. **이벤트 버스 연결**: 상주 구독 프로세스 등록으로 소비, 또는 동기 직접 호출로 변경; 이벤트 적재 + 실패 재시도（H2） — ✅ 완료（2026-08-18: emit/consume INCR Redis 카운트; `service/config/process.php`에 `event-consumer` 등록, `service/app/process/EventConsumer.php`가 이벤트 소비）
5. **Apple id_token 검증**: JWKS 검증 + aud/iss/exp（H6） — ✅ 완료（2026-08-18: RS256 JWKS + kid 갱신 + aud/iss/exp）
6. **Stripe 리플레이와 금액 대조**: 타임스탬프 허용 오차 + 게이트웨이 금액 비교（H5） — ✅ 완료（2026-08-18: t= 타임스탬프 ±300s 리플레이 방지 + bccomp 정밀 금액 대조 + secret/webhook_id 미설정 또는 검증 예외 시 전부 거부）

### P1 — 신뢰성 + 일관성

7. **공유 레이어 중복 제거**: common/model을 composer path repo（또는 심볼릭 링크）로 추출, 이중 분기 해소（H3） — 🔶 부분 완료（2026-08-18: `common/service`를 단일 `packages/platform-common` / `erik/platform-common` path repo로 추출（기존 `common-php` 통합됨）, admin+service 참조; model과 host 종속 `app/common` 래퍼는 여전히 이중, `packages/platform-common/DUAL_MODELS.md` 참조）
8. **통일 Redis 디그레이드 래퍼**: fail 정책 명시화 + 조용한 경고 금지; PayoutService/OAuth/ChatWebSocket 폴백 보강（M5） — 🔶 부분 완료（RateLimit fail-closed 구축 완료: Redis 장애 시 레이트 리밋 거부로 처리, 조용한 통과 아님; 나머지 미완료）
9. **webman/queue 배선**: 이벤트와 webhook 전달 담당（소비 재시도, 데드 레터）, ComputeDailyStats/ES 작업 활성화 또는 삭제（M7） — ⬜ 미완료
10. **2FA 수정**: Base32 디코딩 + verify에 로그인 상태와 사용자별 시도 잠금（M1） — ✅ 완료（2026-08-18: RFC 4648 Base32 디코딩 후 HMAC; `/api/2fa/verify` 5회 실패 시 15분 잠금, Redis 장애 fail-closed）
11. **출금 원자화**: 심사/지급 조건 업데이트 + 이중 심사; 한도 Redis Lua/유일 제약（M2/M4） — 🔶 부분 완료（2026-08-18: pending→approved/rejected, approved→processing 조건 UPDATE; 선택적 이중 심사 `withdraw.require_dual_review`; 신청 측 Redis 사용자 잠금. Lua 한도/유일 제약 없음）
12. **Webhook SSRF 차단**: 내부/예약 주소 거부（M3） — ✅ 완료（2026-08-18: `isSafeWebhookUrl()`가 https 공개망만 허용）
13. **ClickHouse 택일**: 실제 연동 또는 의존성 제거 + 문서 수정（M6） — ⬜ 미완료
14. **죽은 코드 정리**: Vip/Achievement/Notification/FeatureFlag 배선 또는 삭제; Test model 삭제; DepositLog 감사 적재（M8） — 🔶 부분 완료（2026-08-18: Test model 삭제, DepositLog 감사 적재; Vip/FeatureFlag/Notification 호출자 있음; AchievementService는 EventConsumer가 호출）
15. **service 테스트 + CI 게이트**: 콜백 검증/출금 플로우/Redis 디그레이드/확률 계산/낙관적 잠금 동시성 통합 테스트; phpunit 실패 차단; service CI 편입（현재 `|| echo warning`으로 실패 허용） — 🔶 부분 완료（service에 WebhookUrlSafety / EventBusMessageFormat 있음; CI `phpunit-service` job 편입으로 실패 차단）

**이번 라운드（2026-08-18）추가 완료（원래 번호에 없음）**:
- **테이블 접두사 수정**: 52 모델에서 하드코딩 `game_` 접두사 제거, `game_game_` 이중 접두사 해소; DB 접두사는 config/database.php `prefix=game_`로 통일 제공, install.sql 변경 불필요
- **refresh token 재작성**: service AuthController 갱신 토큰 로직 재작성
- **DepositLogService service 버전 이식**: service/common/service/DepositLogService.php 보완（admin/service 이중 분기 중 하나 해소）

### P2 — 관측 / 확장 / 경험

16. **HarmonyOS C단** 5페이지를 처음부터 구현（로그인/로비/상세/지갑/개인）（H4） — ✅ 완료（2026-08-18: `apps/harmonyos/entry/src/main/ets/pages/` 5페이지가 저장소에 있음）
17. **프론트엔드 보완**: 2FA 검증 페이지, 쿠폰/랭킹/알림 진입점, ES 검색 UI; main.dart/app_pages.dart 라우트 소스 통합; OAuth 실제 콜백; 프론트 AES 전송 계층
18. **확률 계산 ClickHouse 이전** 또는 MySQL 구체화 통계 테이블 + 캐시; 리텐션을 실제 cohort 기준 재계산
19. **Prometheus 비즈니스 지표**（이벤트 전달/소비율, 큐 깊이）+ 그레이 AB 분산 미들웨어（FeatureFlag 재사용） — 🔶 부분 완료（2026-08-18: `GET /metrics` 심사 대기 출금/오늘 확정 충전/이벤트 emit·consume 카운트; FeatureFlag `inRollout`/`abTest` crc32 버킷. 큐 깊이 미완료）
20. **WebSocket 데이터 링크 폐루프**: 랭킹/채팅 영속화 확인
21. **문서 정렬**: 테이블 수/서비스 수/공유 레이어 설명 수정, API 문서와 구현 정렬, CHANGELOG 보완 — ✅ 완료（2026-08-18: `docs/CHANGELOG.md`, FEATURES/VERSIONS/PROJECT-PLAN/심사 보고서 §십 참조）

---

## 四、품질 게이트（팀 협업）

- 코드 변경마다: admin 전체 테스트 `vendor/bin/phpunit` 반드시 통과（`|| echo warning` 제거）
- 신규 민감 경로（결제/출금/인증）는 반드시 테스트 포함
- common/model 변경 시 admin+service 양쪽 동기화（공유 레이어 구축 전）
- 심사 보고서 추천 중점: ProviderAuth 서명, AES 암호화, ProbabilityService 수제 SQL

## 五、팀

game-platform 팀（6명: researcher/architect/backend-dev/frontend-dev/tester/reviewer）준비 완료, P0 바로 실행 가능.
