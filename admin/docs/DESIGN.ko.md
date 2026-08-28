# 开放管理后台 — 设计文档
<!-- lang-nav -->

Languages: [中文](DESIGN.md) · [English](DESIGN.en.md) · **한국어** · [Русский](DESIGN.ru.md) · [Deutsch](DESIGN.de.md) · [Français](DESIGN.fr.md) · [Español](DESIGN.es.md) · [Português](DESIGN.pt.md) · [हिन्दी](DESIGN.hi.md) · [العربية](DESIGN.ar.md) · [বাংলা](DESIGN.bn.md) · [Bahasa Indonesia](DESIGN.id.md) · [日本語](DESIGN.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 자세한 Mermaid 아키텍처 다이어그램은 [ARCHITECTURE.md](ARCHITECTURE.ko.md)를 참조하세요 (GitHub/GitLab/VS Code에서 자동 렌더링).

## 1. 시스템 아키텍처

> **기능 목록**: 인증(login/register/refresh/logout + 계정 잠금 + 세션 제한) | 대시보드(Redis 캐시) | 사용자 CRUD+일괄+가져오기 | 역할 권한(RBAC) | 시스템 설정 | 작업 감사(8개 플랫폼 출처) | 파일(업로드+내보내기+마스킹) | 보안(18계층 방어) | 운영(health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. 백엔드 아키텍처

### 2.1 계층 설계

| 계층 | 디렉터리 | 책임 |
|---|------|------|
| 라우트 | `config/route.php` | URL에서 컨트롤러로의 매핑, 미들웨어 바인딩, 버전별 라우트 |
| 미들웨어 | `app/middleware/` | 공격 차단(SecurityFilter), 속도 제한(RateLimit), 인증(JWT), 인가(RBAC), API 버전(ApiVersion) |
| 컨트롤러 | 30개: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs/Metrics/Analytics/Game/Payment/Withdraw... (관리자) + Captcha/Auth (API v1) | 요청 파라미터 검증, 비즈니스 로직 호출, 응답 포맷팅 |
| 비즈니스 서비스 | `common/service/` | 데이터 분석: GameDashboardService(개요/랭킹/추세), DepositLogService(매출/전환), ProbabilityService(결합/조건부 확률, SQL 빌더); DB 장애 시 오류 대신 빈 데이터 반환 |
| 데이터 모델 | `app/model/` | ORM 매핑, 연관 관계, 필드 암복호화 |
| 공용 유틸리티 | `app/common/` | Hashids, Snowflake, Encryption 서비스 |

### 2.2 요청 라이프사이클

```
클라이언트 요청
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 매칭
  │
  ▼
미들웨어 체인:
  SecurityFilter ──────► HTTP 메서드 검사 → 405 (GET/POST/PUT/DELETE/OPTIONS/HEAD만 허용)
  │                     XSS/SQL 인젝션/경로 탐색/명령 인젝션/CSRF 공격 차단 (403)
  ▼
  RateLimit ───────────► Redis 슬라이딩 윈도우 속도 제한
  │ (실패 시 429 + Retry-After 헤더 반환)
  ▼
  ApiVersion ─────────► API-Version 헤더 검증, $request->apiVersion 주입
  │ (실패 시 400 반환)
  ▼
  AdminAuth ──────────► JWT 검증, $request->adminId 주입
  │ (실패 시 401 반환)
  ▼
  AdminPermission ────► RBAC 권한 검증 (Redis 60초 캐시)
  │ (실패 시 403 반환)
  ▼
  OperationLog ───────► 작업 로그 기록 (POST/PUT/DELETE), 출처 자동 감지
  │
  ▼
Controller::method()
  │
  ├─► 파라미터 검증 (validator)
  ├─► 민감 작업 확인 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 작업 (encryptable 자동 암복호화)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 ID 라이프사이클

```
생성 (Snowflake) → 저장 (MySQL BIGINT) → 전송 (Hashids 인코딩) → 외부 (hash 문자열)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 데이터 암호화 체계

```
전송 계층 (encryption)     — AES-256-CBC, 독립 키
저장 계층 (encryptable)    — AES-128-ECB, 독립 키, Model $casts 자동 처리
표시 계층 (mask)           — 휴대폰: 138****1234, 이메일: a***@example.com
```

## 3. 데이터베이스 설계

### 3.1 ER 관계

```
game_admin_user ──┬── game_admin_user_role ──┬── game_admin_role
  (사용자)          │    (사용자-역할 연관)         │     (역할)
                  │                          │
                  │                    game_admin_role_permission
                  │                     (역할-권한 연관)
                  │                          │
                  │                          ▼
                  │                    game_admin_permission
                  │                      (권한/메뉴)
                  │
                  ▼
           game_operation_log
             (작업 로그)

game_system_config (시스템 설정) — 독립 테이블
```

### 3.2 핵심 테이블 구조

| 테이블 이름 | 필드 수 | 설명 |
|------|-------|------|
| `game_admin_user` | 14 | 관리 사용자, phone/email/id_card 암호화 저장, 소프트 삭제 지원 |
| `game_admin_role` | 7 | 역할, slug 고유 |
| `game_admin_permission` | 10 | 권한 트리 (parent_id 자기 참조), type: 1=메뉴 2=버튼 3=API |
| `game_admin_user_role` | 2 | 사용자-역할 다대다 중간 테이블 |
| `game_admin_role_permission` | 2 | 역할-권한 다대다 중간 테이블 |
| `game_system_config` | 8 | 키-값 설정, group+key 결합 고유 |
| `game_operation_log` | 9 | 작업 감사 로그 (source 출처 포함) |

### 3.3 기본키 규범

- 타입: `BIGINT UNSIGNED NOT NULL`
- 특성: **비자동증가**, Snowflake 알고리즘으로 애플리케이션 계층에서 생성
- 장점: 전역 고유, 분산 친화적, 추세 증가로 인덱스 유리, 비즈니스 규모 노출 없음
- 설정: datacenter_id(0-31) + worker_id(0-31), 1024개 노드 동시 지원

## 4. API 설계

### 4.1 URL 규범

```
공개 인터페이스:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

관리자:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

리소스 라우트:
  GET    /admin/user          → 목록
  POST   /admin/user          → 생성
  GET    /admin/user/{hashid} → 상세
  PUT    /admin/user/{hashid} → 수정
  DELETE /admin/user/{hashid} → 삭제 (비밀번호 확인 필요)

시스템 설정:  /admin/config[/{hashid}]
작업 로그:  /admin/log
개인 센터:  /admin/profile[/password|/logout]
가져오기:     /admin/import/users
업로드:     /admin/upload
일괄:     /admin/user/batch/{destroy|status}
문서:     /api/docs     (OpenAPI 3.0)
헬스:     /health
```

### 4.2 API 버전 정책

API 버전은 요청 헤더로 제어하며, **URL 경로에 표시하지 않습니다**:

```http
API-Version: v1
```

| 메커니즘 | 설명 |
|------|------|
| 기본 버전 | `API-Version` 헤더 미포함 시 기본 `v1` |
| 검증 | `ApiVersion` 미들웨어가 검증, 지원하지 않는 버전은 400 반환 |
| 라우팅 | `v()` 헬퍼 함수가 버전에 따라 컨트롤러 클래스를 동적으로 해석 |
| 디렉터리 | 컨트롤러는 버전별로 구성: `app/api/{version}/controller/` |

확장 예시 — v2 API 추가:
1. `app/api/v2/controller/AuthController.php` 생성
2. `ApiVersion` 미들웨어의 `SUPPORTED` 상수에 `'v2'` 추가
3. 라우트 정의는 수정 불필요

```bash
# v1 사용
curl -H "API-Version: v1" /api/auth/login

# v2 사용
curl -H "API-Version: v2" /api/auth/login

# 미전달, 기본 v1
curl /api/auth/login
```

### 4.3 속도 제한 정책

Redis Sorted Set 슬라이딩 윈도우 알고리즘 기반, 원자화 Lua 스크립트 실행:

| 인터페이스 | 제한 |
|------|------|
| 기본 | 60회/분/IP/라우트 |
| POST /api/auth/login | 10회/분 |
| POST /api/auth/register | 5회/분 |

초과 시 429 반환, 응답 헤더에 X-RateLimit-Limit / Remaining / Reset / Retry-After 포함.

### 4.4 통일 응답

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | 의미 | 트리거 시나리오 |
|------|------|---------|
| 0 | 성공 | 정상 응답 |
| 400 | 파라미터 오류 | 요청 형식이 올바르지 않음 |
| 401 | 미인증 | 토큰 누락/만료/무효 |
| 403 | 권한 없음 | 사용자 역할에 필요한 권한이 없음 |
| 404 | 없음 | 리소스를 찾을 수 없음 |
| 422 | 검증 실패 | 폼 파라미터가 규칙에 맞지 않음 / 비밀번호 확인 실패 |
| 500 | 서버 오류 | 예상치 못한 예외 |

### 4.5 인증 흐름 (클릭 캡차 포함)

```
클라이언트                               서버
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② 사용자가 그림의 텍스트 위치 클릭   │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 권한 모델 (RBAC)

```
  사용자 ──┬── 역할 ──┬── 권한
  User     Role      Permission
                 │
                 ├── type=1: 메뉴 (사이드바 표시 제어)
                 ├── type=2: 버튼 (페이지 내 작업 제어)
                 └── type=3: API  (인터페이스 접근 제어)

  권한 식별자 형식: {method}.{path}
  예: get.admin/user  post.admin/user  delete.admin/user
  슈퍼 관리자 식별자: * (모든 권한 검사 건너뜀)
```

### 4.7 민감 작업 2차 확인

사용자, 역할, 권한 삭제 등 민감 작업은 요청 본문에 현재 사용자 비밀번호를 전달하여 신원을 재확인해야 합니다:

```
클라이언트                          서버
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 비밀번호 오류 시 422 반환
  │                                │ → 비밀번호 정확하면 계속 실행
  │◄── 200 { code: 0 }           │
```

프론트엔드는 삭제 작업을 트리거하기 전에 확인 다이얼로그를 띄우고, 사용자 비밀번호를 수집한 후 요청을 보냅니다.

### 4.8 결제 수단 관리

결제 수단 관리 모듈(`PaymentController` + Flutter `payment_page.dart`)은 5개 엔드포인트를 제공하며, 모두 JWT + RBAC 인증이 필요합니다:

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/payment/method/list | 목록(sort 오름차순) |
| POST | /admin/payment/method/toggle | 활성/비활성 전환 |
| POST | /admin/payment/method/create | 생성 |
| PUT | /admin/payment/method/{hashid} | 업데이트(전달된 필드만) |
| DELETE | /admin/payment/method/{hashid} | 삭제(pending 주문이 있으면 422) |

- **provider 화이트리스트**: `stripe` / `nowpayments` / `coinbase`
- **필드**: name / type(fiat|crypto) / provider / status / sort / countries[](국가별 표시, 비어 있으면 전 세계) / currency / min_amount / max_amount / config(JSON, 암호화 저장)
- **삭제 보호**: status=pending 주문이 있는 동안 삭제 시 422 반환
- **프론트엔드**: Flutter `payment_page.dart` — 목록 + 생성/편집 다이얼로그 + 활성/비활성 토글

## 5. 프론트엔드 설계

### 5.1 Flutter Web 관리 백엔드

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 메뉴 버튼          🔔 메시지  👤 관리자  ▼   │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 대시보드│  │ 통계 카드×4   │ │ 추세 그래프│     │
│ 👥 사용자 │  └──────────────┘ └──────────┘     │
│ 🔒 역할  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 설정  │  │파이  │ │ 최근 작업 로그   │       │
│ 📋 로그  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

특성: 사이드바 접이식, Material 3 이중 테마, 고밀도 데이터 테이블, 다이얼로그, 마우스 호버 인터랙션

### 5.2 HarmonyOS 모바일

페이지 라우트:

| 페이지 | 라우트 | 설명 |
|------|------|------|
| LoginPage | `pages/LoginPage` | 아이디/비밀번호 + 클릭 캡차 로그인 |
| DashboardPage | `pages/DashboardPage` | 통계 카드 + 최근 작업 |
| UserListPage | `pages/UserListPage` | 사용자 목록, 검색 + 당겨서 새로고침 + 위로 밀어 더 보기 |
| UserDetailPage | `pages/UserDetailPage` | 생성/수정/조회/삭제 (AlertDialog 확인) |
| ProfilePage | `pages/ProfilePage` | 개인 센터, 로그아웃 (AlertDialog 확인) |

데이터 흐름: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. 보안 설계

### 6.1 심층 방어

| 계층 | 조치 |
|------|------|
| 메서드 제한 | SecurityFilter HTTP 메서드 화이트리스트, GET/POST/PUT/DELETE/OPTIONS/HEAD만 허용, 비표준 메서드는 405 |
| 공격 차단 | SecurityFilter 미들웨어, XSS/SQL 인젝션/경로 탐색/명령 인젝션/CSRF 감지 차단 |
| 사람-기계 검증 | 클릭 캡차 (Click Captcha), 로그인/가입 시 강제 검증 |
| 계정 잠금 | 연속 5회 로그인 실패 시 계정 15분 잠금, 잠금 중 429 반환 |
| 세션 제한 | 동일 사용자 최대 3개 동시 토큰, 초과 시 가장 오래된 토큰 자동 블랙리스트 |
| 속도 제한 | RateLimit 미들웨어, Redis 슬라이딩 윈도우, Lua 원자화 |
| CSP | Content-Security-Policy 헤더로 리소스 출처 제한, XSS 및 데이터 인젝션 방지 |
| 작업 확인 | 삭제 등 민감 작업은 현재 사용자 비밀번호 2차 확인 필요 |
| 전송 | HTTPS + JWT Bearer Token |
| 인터페이스 ID | Hashids 암호화, 외부에서 실제 ID 역추적 불가 |
| 요청 본문 | AES-256-CBC 민감 필드 암호화 |
| 데이터베이스 | BIGINT 기본키 (자동증가량 노출 없음) |
| 데이터베이스 | AES-128-ECB 민감 필드 암호화 저장 |
| 인증 | JWT HS256, 2h 만료 + refresh token |
| 인가 | RBAC, method.path 단위 권한 제어 |
| 감사 | OperationLog가 모든 작업 기록 (source 출처 자동 감지 포함) |

### 6.2 키 관리

```
JWT_SECRET          → 환경 변수 주입, 64자 무작위 문자열
HASHIDS_SALT        → 고유 솔트, 유출 시 전역 교체 필요
ENCRYPTION_KEY      → API 전송 암호화 키, 32바이트
ENCRYPTABLE_KEY     → DB 저장 암호화 키, 전송 키와 독립
SCOUT_HOSTS         → ES 주소, 내부망 배포
```

### 6.3 민감 데이터 보호

| 시나리오 | 필드 | 조치 |
|------|------|------|
| 목록 표시 | phone | 마스킹: 138****1234 |
| 목록 표시 | email | 마스킹: a***@example.com |
| 상세 조회 | phone/email | 복호화 인터페이스 필요 |
| Excel 내보내기 | phone/email | 마스킹 후 내보내기 |
| PDF 내보내기 | 전체 필드 | 마스킹 + 제거 불가능한 저작권 워터마크 |
| 저장 | phone/email/id_card | encryptable로 암호문 저장 |

## 7. 내보내기 설계

### 7.1 Excel 내보내기

```
요청: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 데이터 조회 (limit 10000)
  → 민감 필드 마스킹
  → PhpSpreadsheet 구성 (파란 배경 흰 글씨 헤더 + 첫 행 고정 + 자동 필터)
  → runtime/tmp/에 쓰기 → download 응답
```

### 7.2 PDF 내보내기

```
요청: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 인라인 CSS + 페이지 헤더 저작권 + 페이지 푸터 제거 불가능 저작권
  → Dompdf 렌더링 A4 가로
  → runtime/tmp/에 쓰기 → download 응답
```

## 8. 배포 아키텍처

### 8.1 권장 토폴로지

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                    정적 파일: Flutter Web build/
```

### 8.2 Docker Compose (프로덕션 권장)

프로젝트 루트의 `docker-compose.yml`이 위 토폴로지의 모든 서비스를 오케스트레이션합니다:

| 서비스 | 이미지/빌드 | 포트 | 설명 |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | 리버스 프록시 + 정적 파일 + Gzip |
| `app` | 로컬 `Dockerfile` 빌드 | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | 메인 데이터베이스, 데이터 볼륨 영속화 |
| `redis` | redis:7-alpine | 6379 | 캐시 / 속도 제한 / 캡차 |
| `elasticsearch` | elasticsearch:8.x | 9200 | 전문 검색 |

시작 전에 `docker-compose.yml`의 `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY` 등 키를 무작위 문자열로 교체하세요.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

GitHub Actions 지속적 통합은 `.github/workflows/ci.yml`에 정의:
- PHP 문법 검사 (`php -l`)
- PHPUnit 단위 테스트
- Flutter 정적 분석 (`flutter analyze`)

### 8.4 데이터베이스 백업

`database/backup/backup.sh` — mysqldump + gzip 백업, 30일 전 오래된 백업 자동 정리.
`database/backup/restore.sh` — 인터랙티브하게 백업을 선택하고 복원.

### 8.5 모니터링

`GET /metrics` 엔드포인트 (`MetricsController`)가 Prometheus text format으로 5개 gauge 지표를 노출: HTTP 요청 총수, 활성 사용자 수, 데이터베이스/Redis 연결 상태, 메모리 사용량.

### 8.6 환경 요구사항

| 컴포넌트 | 최소 버전 | 권장 구성 |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ OPcache enabled |
| MySQL | 8.0+ | 8.0+ 마스터-슬레이브 복제 |
| Elasticsearch | 7.x | 8.x 3노드 클러스터 |
| Redis | 6.x | 7.x 센티널 모드 |
| Nginx | 1.20+ | 리버스 프록시 + gzip + SSL |
| Flutter SDK | 3.41+ | 최신 안정 버전 |
| HarmonyOS | API 12 | DevEco Studio 5.x |
