# 국제화 게임 통합 플랫폼 (Global Game Platform)

## 프로젝트 마스코트

<img src="../mascot.svg" width="120" alt="Dicey"/>

**다이시(Dicey)** — 플랫폼 마스코트. 주사위는 게임과 확률 기반 게임플레이를, 코인은 플랫폼 경제와 다중 결제 게이트웨이를, 보라색 메인 컬러는 관리자 브랜드를 상징합니다. SVG 파일: `docs/mascot.svg`, 문서·로고·굿즈에 무제한 확대 가능.
<!-- lang-nav -->

Languages: [中文](../../README.md) · [English](README.en.md) · **한국어** · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

전 세계에서 사용할 수 있는 국제화 게임 통합 플랫폼입니다. 사용자가 가입한 후 플랫폼에 충전하여 게임 코인으로 환전하고, 게임 코인으로 게임을 즐기며 게임 코인을 획득할 수 있으며, 게임 코인은 다시 지갑으로 환전하여 출금할 수 있습니다. 관리 백엔드는 완전한 게임 관리, 출금 심사, 사용자 관리 및 결제 관리 기능을 제공합니다. 다국어 전환(영어/중국어)을 지원합니다.

## 버전 정책

| 버전 | 목표 | 상태 |
|------|------|------|
| 전체 버전 | 완전체: 랭킹, 쿠폰, 게임 분류, 국가 설정, ES 검색 | 완료 |
| 생태계 확장 | v2.0: 게임 Provider 연동, 티켓, VIP, 업적, 소셜, 이벤트 버스 | 완료 |
| v1.3.15-22 (8개 버전) | 대사/정산, 리스크 관리 심화, 통합 지갑, 활동 엔진, 안티치트, 소셜 성장, Adyen/GrabPay | 완료 |

## 기술 스택

### 백엔드
- PHP 8.3+, webman v2 (workerman/webman)
- MySQL 8.0+ (테이블 접두사 `game_`, BIGINT 비자동증가 기본키)
- Redis (Session / 캐시 / 속도 제한)
- ClickHouse (OLAP 분석 / 확률 계산)
- Elasticsearch (전문 검색)
- JWT 인증 + RBAC 권한 제어
- 데이터 암호화: API 전송 계층 AES-256-CBC + 데이터베이스 저장 계층 AES-128-ECB

### 프론트엔드

프런트엔드는 두 개의 별도 디렉터리 트리로 나뉘며, **각각 자기 쪽 백엔드만 호출하고** 서로 교차하지 않습니다:

| 디렉터리 트리 | 역할 | 요청 접두사 | 대응 백엔드 | 기술 스택 |
|--------|------|---------|---------|--------|
| `apps/*` | **C측 플레이어 플랫폼** | `/api/v1/...` | service (기본 8792) | Flutter Web / React 19 (Vite) / Angular 21 / HarmonyOS ArkTS |
| `admin/apps/*` | **관리 콘솔** | `/admin/v1/...` | admin (기본 8789) | Flutter Web / React 19 (Vite) / Angular 21 / HarmonyOS ArkTS |

- 반응형 레이아웃 (Phone / Tablet / Desktop)
- 국제화 (i18n): 영어 / 중국어 간체 전환

### 핵심 컴포넌트
- `erikwang2013/snowflake-php` — 전역 고유 BIGINT ID 생성
- `erikwang2013/hashids` — API 계층 ID 암복호화
- `erikwang2013/jwt-webman` — JWT 인증
- `erikwang2013/encryption` — API 민감 데이터 암복호화
- `erikwang2013/encryptable` — 데이터베이스 민감 필드 암복호화
- `erikwang2013/webman-scout` — Elasticsearch 동기화 및 검색
- `erikwang2013/season` — 국가 국기
- `erikwang2013/security-php` — 보안 도구 감지
- `erikwang2013/poster-php` — 민감 작업 무작위 검증
- `erikwang2013/clickhouse-php` — ClickHouse 연결 및 확률 계산

## 프로젝트 구조

```
game-platform-php/
├── admin/                     # 관리 백엔드 (webman v2, 기본 포트 8789, APP_PORT로 변경 가능)
│   ├── app/admin/v1/controller/  #   관리 측 컨트롤러
│   ├── app/middleware/        #   미들웨어 (Cors/SecurityFilter/RateLimit/AdminAuth/AdminPermission/OperationLog)
│   ├── app/model/             #   admin 전용 모델 (8개, 나머지 52개 공유 모델은 packages/에 있음)
│   ├── app/service/           #   admin 전용 서비스 (WalletService/WalletScope/RiskSandboxService)
│   ├── app/process/           #   상주 프로세스 (Http/Monitor/RiskIpCron)
│   ├── app/provider/          #   게임 Provider 계층 (Self/ThirdParty/Factory)
│   ├── app/activity/          #   액티비티 엔진 (출석/초대/일일 작업)
│   ├── app/event/             #   이벤트 버스 (EventBus Redis Pub/Sub)
│   ├── config/                #   설정 파일
│   └── apps/                  #   관리 프런트엔드 (4종, /admin/v1 → admin:8789 호출)
│       ├── flutter/           #     Flutter Web PC 관리 백엔드
│       ├── react/             #     React 19 (Vite) 관리 콘솔
│       ├── angular/           #     Angular 21 관리 콘솔
│       └── harmonyos/         #     HarmonyOS ArkTS 관리 콘솔 (.hap, nginx를 거치지 않음)
│
├── service/                   # C측 비즈니스 서버 (webman v2, 기본 포트 8792, APP_PORT로 변경 가능)
│   ├── app/api/v1/controller/ #   C측 API 컨트롤러
│   ├── app/middleware/        #   미들웨어 (TraceId/Cors/SecurityFilter/RateLimit/LanguageMiddleware/UserAuth/ProviderAuth/SdkSessionAuth)
│   ├── app/model/             #   service 전용 모델 (10개, 나머지 52개 공유 모델은 packages/에 있음)
│   ├── app/service/           #   service 전용 서비스 (지갑/리스크/컴플라이언스/대사/푸시/업적/부정행위 방지 등)
│   ├── app/payment/           #   18개 결제 게이트웨이 어댑터 (Stripe/PayPal/Adyen/NowPayments/Skrill…) + GatewayFactory
│   ├── app/cdn/               #   5개 사업자 CDN 어댑터 (Cloudflare/CloudFront/Alibaba/Tencent/Huawei) + CdnFactory
│   ├── app/process/           #   상주 프로세스 (Http/Monitor/LeaderboardWS:8790/ChatWS:8791/EventConsumer/EventSubscriber/AntiCheatWorker/GroupSweepWorker/Health)
│   ├── app/provider/          #   게임 Provider 계층
│   ├── app/activity/          #   액티비티 엔진
│   ├── app/event/             #   이벤트 버스 (EventBus Redis Pub/Sub)
│   └── config/                #   설정 파일
│
├── packages/platform-common/  # 공유 계층: admin과 service가 composer path 저장소로 가져와 두 벌의 사본을 피함
│   ├── src/model/             #   공유 Eloquent 모델 (52개, 양쪽 동일 소스)
│   ├── src/service/           #   공유 서비스 (DepositLogService / VipService 등 11개, ClickHouse 확률 계산 포함)
│   ├── src/BcMath.php         #   금액/환율 고정밀 연산 (bcmath 래퍼), 반올림, 백분율
│   ├── src/EncryptionService.php  #   AES 암호화/복호화 및 마스킹
│   ├── src/CircuitBreaker.php #   서킷 브레이커 (재시도용 Retry.php 별도)
│   ├── src/HashidsService.php #   API 계층 ID 인코딩/디코딩
│   └── src/SnowflakeService.php   #   전역 고유 BIGINT ID
│
├── apps/                      # C측 플레이어 프런트엔드 (4종, /api/v1 → service:8792 호출)
│   ├── flutter/platform/      #   Flutter Web PC C측 사용자 플랫폼
│   ├── react/                 #   React 19 (Vite) C측
│   ├── angular/               #   Angular 21 C측
│   └── harmonyos/             #   HarmonyOS ArkTS C측 (.hap, nginx를 거치지 않음)
│
├── game/xiaoxiaole/           # 내장 미니게임 「전원 소소락」: TypeScript + Vite + Vitest, src/domain 엔진 + 4개 레벨 설계 + tests/, 13개 언어 설계 문서
│
├── install/                   # 원클릭 설치 마법사 + 데이터베이스 초기화 SQL
│   ├── index.php              #   설치 진입점
│   ├── Installer.php          #   설치 핵심 로직
│   ├── install.sql            #   통합 설치 SQL（78개 테이블 + 시드 데이터）
│   ├── clickhouse.sql         #   ClickHouse 분석용 DDL (독립 엔진, 별도로 가져옴)
│   ├── test-data.sql          #   데모/테스트 데이터
│   ├── migrations/            #   기존 데이터베이스용 증분 업그레이드 스크립트 (*.sql)
│   ├── lang/ + lang.php       #   설치 마법사 UI 번역 (13개 언어)
│   └── assets/                #   정적 리소스
│
├── docs/                      # 프로젝트 문서 (본문은 모두 13개 언어: .md는 중국어 원본이며 같은 디렉터리에 .{lang}.md 번역이 있음)
│   ├── ARCHITECTURE.md        #   아키텍처 문서
│   ├── ARCHITECTURE-DESIGN.md #   아키텍처 설계 문서
│   ├── FEATURES.md            #   기능 문서
│   ├── FEATURE-DESIGN.md      #   기능 설계 문서
│   ├── API.md                 #   API 문서
│   ├── DEPLOYMENT.md          #   배포 문서 (Docker/수동/포트 구성)
│   ├── PROVIDER-SDK.md        #   서드파티 게임 연동 가이드 (서명 알고리즘 + PHP/Go/Python 예제)
│   ├── CLICKHOUSE_INSTALL.md  #   ClickHouse 설치/설정/마이그레이션/검증
│   ├── CLICKHOUSE_USAGE.md    #   ClickHouse의 4개 서비스 API와 관리 대시보드
│   ├── translations/          #   이 README의 12개 언어 번역
│   ├── diagrams/              #   아키텍처/흐름/기능/수명주기/보안/생태계 확장 SVG (각 13개 언어)
│   ├── test-reports/          #   테스트 보고서 (php-unit / api / resilience / ui / SUMMARY)
│   └── superpowers/           #   이 저장소의 설계 명세와 구현 계획 (역사 기록)
│
├── scripts/                   # 운영 스크립트 (모델 드리프트 점검 / apidoc 애노테이션 마이그레이션 / exchange 출금 의미론 마이그레이션 / 서명 검증)
├── tests/api/                 # API 자동 테스트 (run_all.sh)
├── runtime/                   # webman 런타임 디렉터리 (로그/pid, 런타임에 생성)
│
├── docker-compose.yml         # Docker Compose 오케스트레이션 (기본 포트는 루트 .env에서)
├── nginx.conf.template        # Nginx 구성 템플릿 (upstream 포트는 envsubst로 렌더링)
├── .env.example               # 루트 .env 템플릿 (Docker 포트 변수, 사용하려면 .env로 복사)
└── admin/docs/superpowers/    # 개발 규범 및 계획
    ├── specs/                 #   설계 규범
    └── plans/                 #   구현 계획
```

## 빠른 시작

### 환경 요구사항
- PHP 8.1+
- MySQL 8.0+
- Redis 6.0+
- Composer 2.x
- Flutter SDK 3.x (프론트엔드, 선택)

### 방법 1: 원클릭 설치 마법사 (권장)

```bash
# 1. 설치 마법사 시작
php -S 0.0.0.0:8888 -t install/

# 2. 브라우저에서 http://localhost:8888 열기
#    마법사 안내에 따라: 환경 점검 → 데이터베이스 설정 → 관리자 계정 설정 → 자동 설치

# 3. 의존성 설치
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 4. 서비스 시작 (기본 포트 admin 8789 / service 8792, 각 .env의 APP_PORT에서 변경 가능)
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 5. 관리 백엔드 접속: http://localhost:8789 (기본 포트)
#    설치 시 설정한 관리자 계정/비밀번호로 로그인

# 6. 설치 완료 후 설치 디렉터리 삭제 (보안)
rm -rf install/
```

설치 마법사가 자동으로 수행하는 작업:
- 환경 점검 (PHP 버전, 확장, 디렉터리 권한)
- 데이터베이스 및 테이블 생성 (통합 SQL, 78개 테이블 + 시드 데이터)
- 슈퍼 관리자 계정 생성 (bcrypt 암호화)
- JWT/암호화 키 자동 생성 및 .env 파일에 기록
- install.lock 생성으로 중복 설치 방지

### 방법 2: 수동 설치

<details>
<summary>수동 설치 단계 펼치기</summary>

#### 1. 데이터베이스 초기화

```bash
# 통합 SQL 원클릭 임포트
mysql -u root -e "CREATE DATABASE IF NOT EXISTS game-platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root game-platform < install/install.sql
```

#### 2. 환경 변수 설정

```bash
# 관리 백엔드
cd admin
cp .env.example .env
# .env의 데이터베이스 연결 정보와 키 편집

# C측 비즈니스 서버
cd ../service
cp .env.example .env
# .env의 데이터베이스 연결 정보와 키 편집
```

#### 3. 백엔드 시작

```bash
cd admin && composer install && php start.php start -d
cd ../service && composer install && php start.php start -d
```

#### 4. 관리자 생성

데이터베이스에 관리자 계정을 직접 삽입해야 합니다 (비밀번호는 bcrypt로 암호화).

</details>

### 프론트엔드 시작 (선택)

개발 시 각 프런트엔드는 자체 dev 서버를 띄우고, 요청은 그 서버가 해당 백엔드로 프록시합니다 (각 디렉터리의 `proxy.conf.json` / `vite.config.ts` 참고):

```bash
# --- C측 플레이어 플랫폼 (/api/v1 → service:8792) ---
cd apps/react            && npm install && npm run dev      # http://localhost:5173
cd apps/angular          && npm install && npm start        # http://localhost:4200
cd apps/flutter/platform && flutter pub get && flutter run -d chrome

# --- 관리 콘솔 (/admin/v1 → admin:8789) ---
cd admin/apps/react      && npm install && npm run dev      # http://localhost:5273
cd admin/apps/angular    && npm install && npm start        # http://localhost:4300
cd admin/apps/flutter    && flutter pub get && flutter run -d chrome
```

> Angular dev 서버 포트: 관리 콘솔은 `angular.json`에 4300을 명시했고, C측은 Angular 기본값 4200을 그대로 씁니다. 둘을 동시에 띄우려면 한쪽에 `--port`를 주세요.
> HarmonyOS 대상(`apps/harmonyos`, `admin/apps/harmonyos`)은 DevEco Studio로 열어 빌드합니다;
> 에뮬레이터에서 호스트 백엔드로는 `http://10.0.2.2:<port>`로 접근합니다 (각 `ApiService.ets` 상단 상수 참고).

### 프론트엔드 배포 (Docker/Nginx)

`docker-compose.yml` 의 nginx 서비스가 각 프론트엔드의 빌드 산출물을 읽기 전용으로 컨테이너에 마운트하고, `nginx.conf.template` 이 아래 경로로 제공합니다.
산출물을 빌드하지 않으면 디렉터리가 비어 있어 경로 요청은 404, 디렉터리 자체 요청(예: `/app-react/`)은 403 을 반환합니다.

| URL | 산출물 마운트 지점 | 빌드 명령 |
|-----|-----------|---------|
| `/` | `apps/flutter/platform/build/web` | `flutter build web` |
| `/app-react/` | `apps/react/dist` | `npm run build` (스크립트에 `--base=/app-react/` 포함) |
| `/app-angular/` | `apps/angular/dist/game-client-angular/browser` | `npm run build` (스크립트에 `--base-href=/app-angular/` 포함) |
| `/admin-panel/` | `admin/public` | 범용 배치 슬롯: 아무 콘솔 산출물이나 `admin/public` 에 복사하면 됩니다. 넣지 않으면 마찬가지로 404 (디렉터리 자체는 403)를 반환합니다. 산출물은 반드시 `--base=/admin-panel/` (Flutter 는 `--base-href=/admin-panel/`) 로 빌드해야 하며, 그렇지 않으면 리소스가 원래 접두사를 가리켜 404 가 됩니다. 슬래시 없는 형식은 이 주소로 301 되며, `nginx.conf.template` 에 `absolute_redirect off` 가 설정되어 있어 이 리디렉션은 상대 Location 이므로 80 이외의 포트에 배포해도 포트를 잃지 않습니다 |
| `/admin-react/` | `admin/apps/react/dist` | `npm run build` (스크립트에 `--base=/admin-react/` 포함) |
| `/admin-angular/` | `admin/apps/angular/dist/game-admin-angular/browser` | `npm run build` (스크립트에 `--base-href=/admin-angular/` 포함) |
| `/admin-flutter/` | `admin/apps/flutter/build/web` | `flutter build web --base-href=/admin-flutter/` |

`/admin/` (API) → admin 컨테이너, `/api/` (API) → service 컨테이너이며, HarmonyOS 단말은 `.hap` 패키지로 배포되어 nginx 를 거치지 않습니다.

### 검증

```bash
# 관리 백엔드 테스트 (기본 포트 8789)
curl http://localhost:8789/health

# C측 비즈니스 테스트 (기본 포트 8792)
curl http://localhost:8792/health

# 사용자 가입 테스트
curl -X POST http://localhost:8792/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"Abcdef12"}'
```

## 보안 기능

- **18계층 심층 방어**: XSS/SQL 인젝션/CSRF/경로 탐색/명령 인젝션 감지 차단
- **HTTP 메서드 화이트리스트**: GET/POST/PUT/DELETE/OPTIONS/HEAD만 허용
- **JWT 인증**: access_token 2시간 + refresh_token 14일, 동시 세션 제한
- **JWT 키 시작 검증**: admin 측 `ADMIN_JWT_SECRET_KEY`, service 측 `SERVICE_JWT_SECRET_KEY` 독립 키, 누락되거나 기본값이면 시작 거부
- **결제 콜백 fail-closed**: provider 화이트리스트 (stripe/paypal만) + 키 미설정/서명 검증 실패/타임스탬프 초과는 모두 거부 + bccomp 금액 대조 + 콜백 입금 트랜잭션 처리
- **RBAC 권한**: method.path 단위 권한 제어, Redis 60초 캐시
- **클릭 캡차**: 로그인/가입 시 필수 사람-기계 검증
- **비밀번호 2차 확인**: 민감 작업 시 비밀번호 입력 확인
- **데이터 암호화**: 전송 계층 AES-256-CBC + 저장 계층 AES-128-ECB
- **ID 암호화**: Snowflake 생성 + Hashids 인코딩, 외부에서 역추적 불가
- **지갑 낙관적 잠금**: 동시 출금/중복 입금 방지
- **작업 감사**: 전체 작업 로그, 8개 플랫폼 출처 자동 감지
- **속도 제한**: Redis 슬라이딩 윈도우, Lua 원자화
- **CSP 헤더**: Content-Security-Policy로 XSS 방지
- **계정 보안**: 연속 5회 로그인 실패 시 15분 잠금

## 테스트

테스트 보고서 (로컬 저장): [docs/test-reports/](../test-reports/)

| 테스트 유형 | 케이스/커버리지 | 결과 |
|---------|----------|------|
| PHP 단위 테스트 | 현재 측정 `phpunit --list-tests`: admin 200 + service 273 케이스 (보고서 `docs/test-reports/php-unit.md`에 09-22 재실행 admin 190 + service 273, 08-27 스냅샷 admin 153 + service 45 기재. admin 측은 아직 확충 중) | service 전부 통과 (701 어서션, 3 skipped, 2 warnings + 35 deprecations). admin 437 어서션, 3 skipped, 1건 실패 (`EnvConfigTest`가 실제 `admin/.env`를 검증하며 `REDIS_CLUSTER_NODES` 누락을 발견. 추가하면 초록이 됩니다) |
| 안정성 메커니즘 테스트 | 서킷 브레이커/재시도/디그레이드 스위치 15 케이스 (CircuitBreakerTest/RetryTest/ResilienceMockTest) | 전부 통과 |
| API 자동 테스트 | 187 엔드포인트 (출처: `docs/test-reports/api.md`, 2026-08-27). 현재 route.php는 261개 엔드포인트 등록 | 171 통과 / 50 실패 / 4 건너뜀 (실패는 모두 확정된 결함, 보고서 참고) |
| Flutter UI 테스트 | 12 케이스 (로그인/대시보드/내비게이션/언어 전환) | 전부 통과 |
| Go/Rust | 저장소에 Go/Rust 코드 없음 | 건너뜀, 기록됨 |

```bash
# PHP 단위 테스트 (먼저 JWT 시크릿 환경 변수를 내보내세요)
cd admin && ADMIN_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
cd service && SERVICE_JWT_SECRET_KEY=test-jwt-secret-change-me php vendor/bin/phpunit
# API 자동 테스트 (서비스가 실행 중이어야 합니다, tests/api/run_all.sh 참고)
bash tests/api/run_all.sh
# Flutter UI 테스트
cd admin/apps/flutter && flutter test --timeout 300s
```

상세 보고서:
- [PHP 단위 테스트 보고서](../test-reports/php-unit.md)
- [안정성 메커니즘 테스트 보고서 (서킷 브레이커/재시도/디그레이드)](../test-reports/resilience.md)
- [API 자동 테스트 보고서](../test-reports/api.md)
- [Flutter UI 테스트 보고서](../test-reports/ui.md)

## 플랫폼 기능 개요

| 기능 | 설명 |
|------|------|
| 사용자 인증 | 아이디/비밀번호 + 7개 플랫폼 OAuth (Google/Facebook/Apple/X(Twitter)/Microsoft/LinkedIn/GitHub) + 2FA TOTP |
| 지갑 | 플랫폼 코인 지갑(낙관적 잠금) + 게임 코인 지갑 + 거래 내역 기록 |
| 충전 | 주문 생성 + Stripe/PayPal 콜백 서명 검증 + 자동 입금 |
| 환전 | 플랫폼 코인⇄게임 코인, 실시간 견적, 차액 수익 |
| 출금 | 신청→심사→송금, 전역 스위치, KYC 단계별 한도+수수료 |
| KYC | 실명 인증 제출+심사, 승인 후 출금 한도 상향 |
| 게임 | CRUD + 분류(10종) + 서버 + 게임 기록 추적 |
| 검색 | Elasticsearch 전문 검색(LIKE 폴백 포함) |
| 랭킹 | 일/주/월/전체 랭킹, Redis 캐시, WebSocket 실시간 푸시 (기본 포트 8790, LEADERBOARD_WS_PORT로 변경 가능) |
| CDN | 5개 업체 연동 (Cloudflare R2 / AWS S3 / Aliyun OSS / Tencent COS / Huawei OBS 업로드+퍼지+프리로드) + 관리자 설정/활성화/연결 테스트 |
| 쿠폰 | 고정 금액+비율 할인, 기간/수량 한정, 사용 추적 |
| 알림 | 사이트 내 메시지+이메일, 충전/출금/KYC/쿠폰 자동 알림 |
| 추천 | 추천 코드, 가입 보상, 충전 수수료 리베이트 |
| 리스크 관리 | IP 블랙리스트/대금 경고/빈도/속도 감지 |
| 리스크 관리 심화 | 디바이스 핑거프린트/IP 평판/계정 연관 그래프 + 규칙 엔진 + 리스크 대시보드 + AML/KYC/신뢰 점수 |
| 안티치트 | 안티치트 이벤트 수집 + 일일 통계 + 수동 검토 |
| 대사/정산 | 일일 대사 배치 + 차액 상세 + 스테이트먼트 대사 |
| 통합 지갑 | WalletScope 통합 지갑 스코프 |
| 활동 엔진 | 활동 생성/참여/보상 + 체크인 |
| 소셜 성장 | 그룹 + 공유 링크 추적 |
| 결제 게이트웨이 | 신규 Adyen / GrabPay 게이트웨이 (L1) |
| 국제화 | 4개 언어(en-US/zh-CN/ja-JP/ko-KR), 번역 테이블+캐시 |
| 국가 설정 | 18개국 차등 결제/출금 방식, 최소 충전액 |
| 통계 | 일일 통계 스냅샷(5종 지표) + 플랫폼 수익 추적 |
| 캡차 | 클릭식 사람-기계 검증(poster-php) |
| 게임 연동 | Provider SDK (Self+ThirdParty) + HMAC-SHA256 서명 + 콜백 게이트웨이 |
| 티켓 | C측 생성/답변 + 관리측 처리/배정/종료 |
| VIP | 5단계 충성도, 경험치 누적, 환전 할인/출금 감면/환율 가산 |
| 업적 | 12개 내장 업적, 이벤트 기반 감지, 진행 추적 |
| 소셜 | 친구 시스템 + WebSocket 실시간 쪽지 (기본 포트 8791, CHAT_WS_PORT로 변경 가능), 친구만 발송 가능 |
| 대회 | 토너먼트 시스템 (FeatureFlag 스위치) + 랭킹 + 인원 상한 |
| 리베이트 | 2단계 추천 수익 배분 (커미션율 설정 가능) |
| 쿠폰 | 조건 제한 (min_deposit/first_user/game_id) |
| 이벤트 | Redis Pub/Sub 이벤트 버스 + Webhook 구독 전달 (7종 이벤트) |
| 배포 | Docker Compose 7개 서비스 오케스트레이션 (포트는 루트 .env에서 구성) + Nginx 리버스 프록시 |
| 클라이언트 | 관리 4종 (Flutter/React/Angular/HarmonyOS) + C측 4종 (Flutter/React/Angular/HarmonyOS) |

## 비즈니스 모델

```
법정화폐 (USD/CNY/EUR...)
  │  충전(Stripe/PayPal/알리페이/위챗페이)
  ▼
플랫폼 코인 (통일, 정밀도 decimal(18,4))
  │  환전 (환율 + 플랫폼 수수료 차액 포함)
  ▼
게임 코인 (게임마다 독립, 독립 환율)
  │  게임으로 획득/사용
  ▼
플랫폼 코인 ← 환전 → 출금 (심사/자동)
```

## 다중 통화 정산

플랫폼은 「법정화폐 → 플랫폼 코인 → 게임 코인」 3계층 통화 분리 정산 체계를 채택합니다: USD/CNY/EUR/JPY/KRW/GBP/BRL/INR 다중 법정화폐 충전을 지원하며, 각 게임은 독립적인 결제 통화를 보유합니다. 금액 계산은 전 과정에서 bcmath 고정밀 연산을 사용하여 부동소수점 오차를 방지합니다.

### 3계층 통화 모델

| 계층 | 통화 | 설명 |
|------|------|------|
| 법정화폐 계층 | USD / CNY / EUR / JPY / KRW / GBP / BRL / INR | 사용자 충전/출금의 실제 결제 통화, Stripe / PayPal이 처리 |
| 플랫폼 코인 계층 | 플랫폼 코인 (전 플랫폼 통일) | 내부 통일 정산 통화 (decimal(18,4)), 지갑 낙관적 잠금으로 동시 출금/중복 입금 방지 |
| 게임 코인 계층 | 게임마다 독립 통화 | 게임마다 독립 `exchange_rate` 환율과 `spread_pct` 스프레드, 독립 게임 코인 지갑 |

### 정산 경로

- **충전 정산**: 사용자가 법정화폐로 결제 (Stripe / PayPal 콜백 서명 검증, 멱등성 방지) → `default_exchange_rate`에 따라 플랫폼 코인 입금, 충전 주문에 `amount + currency + platform_amount` 동시 기록
- **환전 정산**: 플랫폼 코인 ⇄ 게임 코인을 게임 통화 환율로 실시간 견적(quote), `spread_pct` 스프레드를 플랫폼 차액 수익으로 차감, VIP는 환전 할인과 환율 가산 혜택
- **게임 정산**: 게임 Provider가 `/api/provider/settle` 콜백으로 사용자 게임 코인 증감 (HMAC-SHA256 서명), 게임 세션 타임아웃 시 자동 정산
- **출금 정산**: 플랫폼 코인 차감 → 출금 주문 생성 (`platform_amount / fiat_amount / currency` 기록) → 관리측 승인 → PayPal Payout 송금 → 배치 상태 완료로 동기화

### 정산 흐름도

```mermaid
flowchart LR
    subgraph FIAT["법정화폐 계층 Fiat"]
        A["사용자 충전<br/>USD / CNY / EUR / JPY / KRW / GBP / BRL / INR<br/>Stripe / PayPal"]
        H["출금 지급<br/>PayPal Payout"]
    end

    subgraph PLAT["플랫폼 코인 계층 Platform Token"]
        B["플랫폼 코인 지갑<br/>decimal(18,4) 낙관적 잠금"]
        E["출금 주문<br/>platform_amount<br/>fiat_amount / currency"]
    end

    subgraph GAME["게임 코인 계층 Game Currency"]
        D["게임 통화<br/>exchange_rate<br/>spread_pct"]
        C["게임 코인 지갑<br/>UserGameWallet"]
        G["게임 Provider<br/>settle 정산 콜백"]
    end

    A -->|"충전 콜백 서명 검증<br/>플랫폼 코인 = 법정화폐 × default_exchange_rate"| B
    B -->|"환전 매수 in<br/>스프레드 차감"| C
    C -->|"환전 매도 out<br/>환율로 환산"| B
    D -.->|"독립 환율 + VIP 가산"| C
    G <-->|"게임으로 획득/사용"| C
    B -->|"출금 신청 (차감)"| E
    E -->|"관리단 승인<br/>PayPal Payout 송금"| H
```

## 아키텍처 다이어그램

![시스템 아키텍처 다이어그램](../diagrams/architecture-ko.svg)

## 핵심 비즈니스 흐름

![비즈니스 흐름 다이어그램](../diagrams/flow-ko.svg)

## 기능 전체 보기

![기능 전체 다이어그램](../diagrams/features-ko.svg)

## 라이프사이클

![라이프사이클 다이어그램](../diagrams/lifecycle-ko.svg)

## 보안 아키텍처

![보안 아키텍처 다이어그램](../diagrams/security-ko.svg)

## 생태계 확장 (v2.0)

![생태계 확장 아키텍처 다이어그램](../diagrams/ecosystem-expansion-ko.svg)

## 문서 색인

| 문서 | 설명 |
|------|------|
| [버전 비교](../VERSIONS.ko.md) | 기본/표준/전체 버전 기능 비교 |
| [아키텍처 설계 문서](../ARCHITECTURE-DESIGN.ko.md) | 아키텍처 선정 이유와 설계 결정 |
| [아키텍처 문서](../ARCHITECTURE.ko.md) | 시스템 토폴로지, 모듈 아키텍처, 데이터 흐름 |
| [기능 설계 문서](../FEATURE-DESIGN.ko.md) | 비즈니스 모델, 기능 사양, 프로세스 설계 |
| [기능 문서](../FEATURES.ko.md) | 기능 목록, 모듈 설명, 사용자 여정 |
| [API 문서](../API.ko.md) | 전체 API 레퍼런스 (146개 인터페이스) |
| [온라인 문서](http://localhost:8792/apidoc/) | erikwang2013/apidoc-php 인터랙티브 문서 (C측) |
| [온라인 문서](http://localhost:8789/apidoc/) | erikwang2013/apidoc-php 인터랙티브 문서 (관리 백엔드) |
| [ClickHouse 설치](../CLICKHOUSE_INSTALL.ko.md) | ClickHouse 설치/설정/마이그레이션/검증 |
| [Provider SDK 연동 문서](../PROVIDER-SDK.ko.md) | 제3자 게임 연동 가이드 (서명 알고리즘+PHP/Go/Python 예제) |
| [ClickHouse 사용](../CLICKHOUSE_USAGE.ko.md) | 4개 ClickHouse 서비스 API와 백엔드 대시보드 |
| [배포 문서](../DEPLOYMENT.ko.md) | 배포 가이드 (Docker + 수동 + Nginx + 모니터링) |
| [설계 규범](../../admin/docs/superpowers/specs/2026-05-22-game-platform-design.ko.md) | 전체 설계 규범 |
| [구현 계획](../../admin/docs/superpowers/plans/2026-05-22-game-platform-plan.ko.md) | 상세 구현 계획 |

---

## 프로젝트 지원

이 프로젝트가 도움이 되었다면 작성자에게 커피 한 잔을 선물해 주세요 ☕

<p align="center">
  <table align="center" border="0" cellspacing="0" cellpadding="0">
    <tr>
      <td align="center" width="200">
        <img src="../weixinpay-130.png" width="130" height="130" alt="위쳇페이"><br>
        <b>위챗페이</b>
      </td>
      <td align="center" width="200">
        <img src="../alipay-130.png" width="130" height="130" alt="알리페이"><br>
        <b>알리페이</b>
      </td>
    </tr>
  </table>
</p>

### 글로벌 송금 (Global Bank Transfer)

**수취인 정보 (Recipient)**

| 항목 | 내용 |
|----|------|
| 수취인 이름 (Beneficiary Name) | WANG KEXUN |
| 수취 계좌번호 (Account Number) | 881015918251 |

**수취 은행 (Beneficiary Bank)**

| 항목 | 내용 |
|----|------|
| SWIFT Code | AABLHKHHXXX |
| 은행 이름 (Bank Name) | ZA Bank Limited |
| 은행 코드 (Bank Code) | 387 |
| 은행 주소 (Bank Address) | Core F, Cyberport 3, 100 Cyberport Road, Hong Kong |

**해외 송금 중개 은행 (Correspondent Bank, 필요한 경우)**

> 참고: 이 정보는 해외 송금 중개 은행(중계 은행) 정보이며, 수취 은행 정보가 아닙니다. 송금 은행에 중개 은행 정보가 필요한지 문의하시기 바랍니다.

- **홍콩달러, 위안화 및 달러 입금 시 중개 은행은 Citibank입니다:**
  - 은행 이름: Citibank N.A. Hong Kong
  - SWIFT Code: CITIHKHXXXX
  - 은행 코드: 006
  - 지점 이름: Hong Kong Branch
  - 지점 번호: 391
  - 은행 주소: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **기타 통화 입금 시 중개 은행은 BNY Mellon입니다:**
  - 은행 이름: THE BANK OF NEW YORK MELLON
  - SWIFT Code: IRVTUS3NXXX
  - 은행 주소: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

### 암호화폐 후원 (Crypto Donation)

이 프로젝트가 도움이 되셨다면, QR 코드를 스캔하여 후원해 주세요. 감사합니다!

| 네트워크 (Network) | QR 코드 (QR Code) | 지갑 주소 (Wallet Address) |
|---|---|---|
| BNB Smart Chain (BEP20) | [<img src="../coin/1.jpg" width="150" alt="BNB Smart Chain (BEP20)">](../coin/1.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Tron (TRC20) | [<img src="../coin/2.jpg" width="150" alt="Tron (TRC20)">](../coin/2.jpg) | `TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| Ethereum (ERC20) | [<img src="../coin/3.jpg" width="150" alt="Ethereum (ERC20)">](../coin/3.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Aptos | [<img src="../coin/4.jpg" width="150" alt="Aptos">](../coin/4.jpg) | `0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| Plasma | [<img src="../coin/5.jpg" width="150" alt="Plasma">](../coin/5.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Polygon POS | [<img src="../coin/6.jpg" width="150" alt="Polygon POS">](../coin/6.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| Solana | [<img src="../coin/7.jpg" width="150" alt="Solana">](../coin/7.jpg) | `2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` |
| The Open Network (TON) | [<img src="../coin/8.jpg" width="150" alt="The Open Network (TON)">](../coin/8.jpg) | `UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| Arbitrum One | [<img src="../coin/9.jpg" width="150" alt="Arbitrum One">](../coin/9.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |
| AVAX C-Chain | [<img src="../coin/10.jpg" width="150" alt="AVAX C-Chain">](../coin/10.jpg) | `0x355d429f97511897ccb4e271ec888205f9ab6629` |

