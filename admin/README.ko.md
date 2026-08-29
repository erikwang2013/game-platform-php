# 开放管理后台 (open-admin)

## 프로젝트 마스코트

<img src="../docs/mascot.svg" width="120" alt="Dicey"/>

**다이시(Dicey)** — 플랫폼 마스코트. 주사위는 게임과 확률 기반 게임플레이를, 코인은 플랫폼 경제와 다중 결제 게이트웨이를, 보라색 메인 컬러는 관리자 브랜드를 상징합니다. SVG 파일: `docs/mascot.svg`, 문서·로고·굿즈에 무제한 확대 가능.
<!-- lang-nav -->

Languages: [中文](README.md) · [English](README.en.md) · **한국어** · [Русский](README.ru.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Español](README.es.md) · [Português](README.pt.md) · [हिन्दी](README.hi.md) · [العربية](README.ar.md) · [বাংলা](README.bn.md) · [Bahasa Indonesia](README.id.md) · [日本語](README.ja.md)

webman v2 + Flutter 기반의 풀스택 관리 백엔드 시스템입니다.

> [English version](README.en.md) | [아키텍처 설계도](docs/ARCHITECTURE.ko.md) | [설계 문서](docs/DESIGN.ko.md) | [보안 아키텍처](docs/SECURITY.ko.md) | [API 레퍼런스](docs/API.ko.md)

## 기능 목록

| 비즈니스 영역 | 기능 | 설명 |
|--------|------|------|
| 🔐 인증 | 로그인/가입/토큰 갱신/로그아웃 | 클릭 캡차 + JWT + 블랙리스트 |
| | 계정 잠금 | 5회 실패 시 15분 잠금 |
| | 동시 세션 제한 | 동일 사용자 최대 3개 유효 토큰 |
| 📊 대시보드 | 실시간 통계/추세 그래프/분포 그래프/최근 작업 | Redis 캐시 5분 |
| 📈 데이터 분석 | 12개 엔드포인트: 개요/랭킹/DAU/시간/행동 분포/매출/전환/확률/유지/퍼널/ARPU/경제 지표 | MySQL 실시간 집계, DB 장애 시 빈 데이터 반환 |
| 👥 사용자 관리 | CRUD + 일괄 삭제/활성·비활성 | 소프트 삭제 + 비밀번호 2차 확인 |
| | Excel 일괄 가져오기 | 행 단위 검증 + 오류 보고서 |
| 🔒 역할 권한 | 역할 CRUD + 권한 트리 | RBAC method.path 단위 인가 |
| ⚙ 시스템 설정 | 키-값 CRUD | 그룹 관리 |
| 📋 작업 감사 | 로그 조회 + 출처 감지 | 8개 플랫폼 자동 인식 |
| 📁 파일 관리 | 업로드/Excel 내보내기/PDF 내보내기 | 민감 데이터 자동 마스킹 |
| 🛡 보안 방어 | 18계층 심층 방어 | XSS/SQL 인젝션/경로 탐색/명령 인젝션/CSRF/속도 제한/CSP... |
| 🏥 운영 | 헬스 체크/metrics/API 문서/security.txt | Prometheus + OpenAPI 3.0 |

## 기술 스택

| 계층 | 기술 | 설명 |
|---|------|------|
| 백엔드 프레임워크 | webman v2 (workerman) | 초고성능 PHP 상주 프로세스 프레임워크 |
| PHP 버전 | 8.3+ | |
| 데이터베이스 | MySQL 8.0+ | 테이블 접두사 `game_`, BIGINT 비자동증가 기본키 |
| 검색 엔진 | Elasticsearch | `webman-scout`을 통한 동기화 및 검색 |
| 관리자 프론트엔드 | Flutter 3.x | 웹 버전은 PC 관리 백엔드 스타일 (`apps/flutter/`) |
| 모바일 | HarmonyOS ArkTS | 하모니OS 네이티브 클라이언트 (`apps/harmonyos/`), 폰/태블릿/2in1 지원 |

## 핵심 의존성

| 패키지 | 용도 |
|---|------|
| `erikwang2013/snowflake-php` | Snowflake 알고리즘으로 전역 고유 BIGINT 기본키 생성 |
| `erikwang2013/hashids` | API 계층 ID 암복호화, 실제 데이터베이스 ID 숨김 |
| `erikwang2013/jwt-webman` | JWT 인증 토큰 발급 및 검증 |
| `erikwang2013/encryption` | 인터페이스 전송 계층 민감 데이터 암복호화 |
| `erikwang2013/encryptable` | 데이터베이스 저장 계층 민감 필드 자동 암복호화 |
| `erikwang2013/webman-scout` | Elasticsearch 데이터 동기화 및 전문 검색 |
| `erikwang2013/season` | 국가 국기 데이터 |
| `erikwang2013/poster-php` | 클릭 캡차 생성/검증 + 포스터 생성 |
| `phpoffice/phpspreadsheet` | Excel 내보내기 |
| `barryvdh/laravel-dompdf` | PDF 내보내기 (Dompdf 기반) |

## 프로젝트 구조

```
open-admin/
├── app/
│   ├── admin/controller/       # 관리자 컨트롤러
│   │   ├── DashboardController.php # 대시보드 (Redis 캐시)
│   │   ├── UserController.php      # 사용자 CRUD + 일괄 작업
│   │   ├── RoleController.php      # 역할 CRUD
│   │   ├── PermissionController.php# 권한 CRUD
│   │   ├── ConfigController.php    # 시스템 설정 CRUD
│   │   ├── LogController.php       # 작업 로그 조회
│   │   ├── ProfileController.php   # 개인 센터 + 로그아웃
│   │   ├── ExportController.php    # Excel/PDF 내보내기
│   │   ├── ImportController.php    # Excel 사용자 가져오기
│   │   ├── UploadController.php    # 파일 업로드
│   │   ├── HealthController.php    # 헬스 체크
│   │   ├── DocsController.php      # OpenAPI 문서
│   │   └── BaseController.php      # 기본 컨트롤러
│   ├── api/
│   │   └── v1/controller/          # API v1 컨트롤러 (버전은 요청 헤더 API-Version으로 제어)
│   │       ├── CaptchaController.php # 클릭 캡차
│   │       └── AuthController.php    # 로그인/가입/토큰 갱신
│   ├── common/                 # 공용 유틸리티 클래스
│   │   ├── HashidsService.php  # ID 인코딩/디코딩
│   │   ├── SnowflakeService.php# Snowflake ID 생성
│   │   └── EncryptionService.php # 데이터 암복호화 + 마스킹
│   ├── middleware/             # 미들웨어
│   │   ├── Cors.php            # 크로스 도메인
│   │   ├── SecurityFilter.php  # 공격 감지 차단 (HTTP 메서드 제한/XSS/SQL 인젝션/경로 탐색/명령 인젝션/CSRF)
│   │   ├── RateLimit.php       # Redis 속도 제한 (슬라이딩 윈도우 + 응답 헤더)
│   │   ├── ApiVersion.php      # API 버전 검증
│   │   ├── AdminAuth.php       # JWT 인증 + 블랙리스트
│   │   ├── AdminPermission.php # RBAC 권한 검증
│   │   └── OperationLog.php    # 작업 로그 자동 기록 (출처 감지 포함)
│   └── model/                  # 데이터 모델
├── apps/
│   ├── flutter/                # Flutter 웹 관리 백엔드 (PC 스타일)
│   │   └── lib/app/
│   │       ├── pages/          # 완전한 페이지들 (대시보드/사용자/역할/설정/로그/개인 센터)
│   │       ├── services/       # ApiService (JWT 인터셉터) + AuthService (토큰 영속화)
│   │       └── layouts/        # 반응형 관리 백엔드 레이아웃 (사이드바+상단바+콘텐츠 영역)
│   └── harmonyos/              # HarmonyOS 네이티브 클라이언트 (토큰 무감각 갱신)
├── config/                     # 설정 파일 (중국어 주석 포함)
│   ├── route.php               # 라우트 + API 버전 정책
│   ├── middleware.php           # 전역 미들웨어 등록
│   └── ...                     # 각 컴포넌트 설정
├── install/        # SQL 마이그레이션 파일 (권한 시드 데이터 포함)
├── public/                     # 공용 진입점
├── runtime/                    # 런타임 파일
└── vendor/                     # Composer 의존성
```

## 환경 요구사항

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (프론트엔드 개발에만 필요)
- Elasticsearch >= 7.x (선택, 검색 기능에 필요)

## 빠른 시작

### 1. 의존성 설치

```bash
composer install
```

### 2. 환경 변수 설정

환경 변수를 복사하고 수정합니다 (선택, 설정하지 않으면 `config/*.php`의 기본값 사용):

```bash
cp .env.example .env
```

주요 설정 항목:

| 환경 변수 | 설명 | 기본값 |
|---------|------|--------|
| `JWT_SECRET` | JWT 서명 키 | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids 솔트 | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API 암호화 키 | 32바이트 기본값 |
| `SNOWFLAKE_DATACENTER_ID` | 데이터센터 ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | 작업 노드 ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES 주소 | `http://localhost:9200` |

**프로덕션 환경에서는 반드시 모든 키를 무작위 문자열로 변경하세요.**

### 3. 데이터베이스 초기화

`install/` 아래의 SQL 파일을 순서대로 실행합니다:

```bash
mysql -u root -p < install/install.sql
```

### 4. 서비스 시작

```bash
php start.php start
```

기본적으로 `http://0.0.0.0:8787`을 수신합니다.

### 5. 프론트엔드 시작 (선택)

**Flutter 관리 백엔드 (웹):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # 웹 (PC 관리 백엔드 스타일)
```

**HarmonyOS 클라이언트 (모바일):**

DevEco Studio로 `apps/harmonyos/` 디렉터리를 열고, 실기기 또는 에뮬레이터에 연결하여 실행합니다.

### 6. Docker Compose 원클릭 배포 (프로덕션 권장)

프로젝트는 5개 서비스로 구성된 완전한 Docker 오케스트레이션을 제공합니다: Nginx, PHP (webman app), MySQL, Redis, Elasticsearch.

```bash
# 1. Docker 환경 변수 설정
cp .env.docker .env

# 2. 모든 서비스 시작
docker-compose up -d

# 3. 데이터베이스 초기화 (app 컨테이너에서 실행)
docker-compose exec app mysql -h mysql -u root -p < install/install.sql

# 4. 접속
# http://localhost:8787  (webman)
# http://localhost:8080  (Nginx 리버스 프록시)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, `php:8.3-cli` 기반
- `docker-compose.yml`: 5개 서비스 오케스트레이션, 네트워크 격리, 데이터 볼륨 영속화
- `.env.docker`: Docker 환경 전용 환경 변수

## 데이터베이스 규범

- **테이블 접두사**: `game_`
- **기본키**: 모든 테이블 기본키는 `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT 사용 금지**
- **ID 생성**: 기본키 ID는 애플리케이션 계층 `SnowflakeService::generate()`로 생성, 분산 고유
- **필수 필드**: 모든 테이블은 `id`, `created_at`, `updated_at`을 반드시 포함
- **소프트 삭제**: 소프트 삭제가 필요한 테이블은 `deleted_at DATETIME DEFAULT NULL` 추가
- **민감 필드**: 휴대폰 번호, 이메일, 주민등록번호 등은 `encryptable` 플러그인으로 자동 암복호화, 데이터베이스 필드는 `VARCHAR(500)`으로 암호문 저장

## API 규범

### 통일 응답 형식

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### 비즈니스 오류 코드

| 오류 코드 | 의미 | 설명 |
|-------|------|------|
| `0` | 성공 | |
| `400` | 요청 파라미터 오류 | |
| `401` | 미로그인 (토큰 무효 또는 만료) | |
| `403` | 권한 없음 / 보안 차단 | RBAC 인가 실패 / SecurityFilter 공격 감지 |
| `404` | 리소스 없음 | |
| `422` | 파라미터 검증 실패 | |
| `413` | 요청 본문 과대 | SecurityFilter 트리거, 10MB 초과 |
| `405` | 허용되지 않은 요청 메서드 | SecurityFilter 트리거, GET/POST/PUT/DELETE/OPTIONS/HEAD만 허용 |
| `415` | 지원하지 않는 미디어 타입 | SecurityFilter 트리거, Content-Type이 JSON이 아님 |
| `429` | 요청 과다 | RateLimit 트리거 / 계정 잠금 (5회 로그인 실패 시 15분 잠금) |
| `500` | 서버 내부 오류 | |

### ID 처리

- **요청/응답의 ID**: hashids로 문자열 암호화, 실제 데이터베이스 ID 노출 방지
- **인터페이스 경로**: `GET /admin/user/{hashid}` — 경로의 `{id}`는 hashid 문자열
- **데이터베이스 저장**: BIGINT 원값, snowflake로 생성

### API 버전

API 버전은 요청 헤더로 제어하며, **URL에 표시하지 않습니다**:

```http
API-Version: v1
```

- 버전 미지정 시 기본 `v1` 사용
- 지원하지 않는 버전은 `400 Bad Request` 반환
- 새 버전 추가 시 `app/api/{version}/controller/` 디렉터리를 만들고 미들웨어에 새 버전을 등록하면 됩니다

### 속도 제한

Redis 슬라이딩 윈도우 알고리즘 기반, 기본 60회/분/IP/라우트. 민감 인터페이스는 더 엄격:
- 로그인: 10회/분
- 가입: 5회/분

응답 헤더에 `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` 포함. 초과 시 429 반환 및 `Retry-After` 첨부.

### 미들웨어 아키텍처

전역 미들웨어는 모든 요청에 적용되며 순서대로 실행됩니다:

```
Cors（크로스 도메인 전처리 + 응답 헤더）
  → SecurityFilter（HTTP 메서드 제한/요청 본문 크기/Content-Type 검증/XSS/SQL 인젝션/경로 탐색/명령 인젝션/CSRF 공격 차단）
  → RateLimit（Redis 슬라이딩 윈도우 속도 제한 + 계정 잠금: 5회 로그인 실패 시 15분 잠금）
  → ApiVersion（API 버전 검증, /api 라우트 그룹）
  → AdminAuth（JWT 인증 + 블랙리스트, /admin 라우트 그룹）
  → AdminPermission（RBAC 인가, /admin 라우트 그룹）
  → OperationLog（POST/PUT/DELETE 자동 기록, 출처 감지 포함, /admin 라우트 그룹）
```

`/health`와 `/api/docs`는 공개 엔드포인트로, `Cors → SecurityFilter → RateLimit`만 통과합니다.

보안 강화:
- **계정 잠금**: 연속 5회 로그인 실패 시 계정 자동 15분 잠금, 잠금 중 로그인은 429 반환
- **동시 세션 제한**: 동일 사용자 최대 3개 유효 토큰, 초과 시 가장 오래된 토큰이 자동으로 블랙리스트에 추가
- **security.txt**: `GET /.well-known/security.txt`로 RFC 9116 표준 보안 연락 정보 제공
- **Nginx 보안 설정**: `docs/nginx-security.conf` 참조, 완전한 리버스 프록시 보안 강화 예시 제공

### 인증

로그인과 가입은 먼저 **클릭 캡차** 검증을 통과해야 합니다:

1. 클라이언트가 `POST /api/captcha/generate`로 캡차 이미지 (base64 PNG)와 텍스트 대상 목록 요청
2. 사용자가 그림의 해당 텍스트 위치를 순서대로 클릭, 클릭 좌표 `[{x, y}, ...]` 수집
3. 로그인 시 `captcha_key`와 `clicks`를 함께 제출, 서버가 캡차를 먼저 검증한 후 자격 증명을 검증

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

관리자 인터페이스는 JWT 인증이 필요합니다:

```http
Authorization: Bearer <token>
```

로그인 성공 후 access_token 반환, 유효기간 2시간; refresh_token도 반환, 유효기간 14일.

로그아웃 시 토큰이 Redis 블랙리스트에 추가되어 유효기간 내 재사용 불가. POST /admin/profile/logout

### 민감 작업 2차 확인

사용자, 역할, 권한 삭제 등 민감 작업은 요청 본문에 현재 로그인 사용자의 `password`를 전달하여 신원 2차 확인을 해야 합니다:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API 목록

> 모든 `/api/*` 인터페이스는 요청 헤더에 `API-Version: v1`을 포함해야 합니다 (미전달 시 기본 v1).

### 공개 인터페이스

| 메서드 | 경로 | 설명 |
|-----|------|------|
| `GET` | `/health` | 헬스 체크 (DB/Redis/ES 상태) |
| `GET` | `/api/docs` | OpenAPI 3.0 규범 문서 |
| `POST` | `/api/captcha/generate` | 클릭 캡차 생성 |
| `POST` | `/api/captcha/verify` | 클릭 캡차 검증 |
| `POST` | `/api/auth/login` | 로그인 (캡차 필요) |
| `POST` | `/api/auth/register` | 가입 (캡차 필요) |
| `POST` | `/api/auth/refresh` | 토큰 갱신 |
| `GET` | `/metrics` | Prometheus 모니터링 지표 |

### 관리자 인터페이스 (JWT + RBAC 필요)

| 메서드 | 경로 | 설명 |
|-----|------|------|
| `GET` | `/admin/dashboard` | 대시보드 데이터 (Redis 캐시 5분) |
| `GET` | `/admin/user` | 사용자 목록 (페이지네이션 + 검색) |
| `POST` | `/admin/user` | 사용자 생성 |
| `GET` | `/admin/user/{id}` | 사용자 상세 |
| `PUT` | `/admin/user/{id}` | 사용자 수정 |
| `DELETE` | `/admin/user/{id}` | 사용자 삭제 (소프트 삭제, 비밀번호 확인 필요) |
| `POST` | `/admin/user/batch/destroy` | 사용자 일괄 삭제 (비밀번호 확인 필요) |
| `POST` | `/admin/user/batch/status` | 사용자 일괄 활성/비활성 |
| `GET` | `/admin/role` | 역할 목록 |
| `POST` | `/admin/role` | 역할 생성 |
| `PUT` | `/admin/role/{id}` | 역할 수정 |
| `DELETE` | `/admin/role/{id}` | 역할 삭제 (비밀번호 확인 필요) |
| `GET` | `/admin/permission` | 권한 트리 |
| `POST` | `/admin/permission` | 권한 생성 |
| `PUT` | `/admin/permission/{id}` | 권한 수정 |
| `DELETE` | `/admin/permission/{id}` | 권한 삭제 (하위 권한 캐스케이드, 비밀번호 확인 필요) |
| `GET` | `/admin/config` | 시스템 설정 목록 |
| `POST` | `/admin/config` | 설정 항목 생성 |
| `PUT` | `/admin/config/{id}` | 설정 항목 수정 |
| `DELETE` | `/admin/config/{id}` | 설정 항목 삭제 (비밀번호 확인 필요) |
| `GET` | `/admin/log` | 작업 로그 (페이지네이션 + 필터) |
| `PUT` | `/admin/profile` | 개인 정보 수정 |
| `PUT` | `/admin/profile/password` | 비밀번호 변경 |
| `POST` | `/admin/profile/logout` | 로그아웃 (JWT 블랙리스트) |
| `POST` | `/admin/export/excel` | Excel 내보내기 |
| `POST` | `/admin/export/pdf` | PDF 내보내기 |
| `POST` | `/admin/import/users` | Excel 사용자 가져오기 |
| `POST` | `/admin/upload` | 파일 업로드 (이미지/문서, 최대 10MB) |

## 프론트엔드 설명

### Flutter 관리 백엔드 (PC 스타일)

- **레이아웃**: 사이드바 (접이식 64px/240px) + 상단바 + 콘텐츠 영역, 반응형 3개 브레이크포인트 (모바일/태블릿/데스크톱)
- **페이지**: 로그인, 대시보드, 사용자 관리, 역할 권한, 시스템 설정, 작업 로그, 개인 센터
- **상태 관리**: GetX (`ApiService` 싱글턴 + `AuthService` 토큰 영속화)
- **대시보드**: 통계 카드, 추세 꺾은선 그래프 (fl_chart), 파이 그래프, 최근 작업 로그
- **내보내기**: Excel/PDF 내보내기, PDF에는 제거 불가능한 저작권 정보 포함
- **일괄 작업**: 다중 선택 일괄 삭제, 일괄 활성/비활성
- **테마**: Material 3 라이트/다크 이중 테마

### HarmonyOS 모바일

- **페이지**: 로그인, 대시보드, 사용자 목록/상세, 개인 센터
- **인증**: JWT Bearer + 401 시 자동 무감각 토큰 갱신, 갱신 실패 시 자동으로 로그인 페이지 리다이렉트
- **저장**: 토큰은 AppStorage로 관리

## 개발 규범

- 전역 함수/클래스 참조 시 앞에 `\`를 붙이지 않고, 통일적으로 `use`로 임포트
- 모든 PHP 파일 상단에는 저작권 표시를 반드시 포함
- 모든 설정 파일에는 중국어 주석 설명을 반드시 포함
- 데이터베이스 기본키는 반드시 애플리케이션 계층 snowflake로 생성, 자동증가 금지
- API 계층의 모든 파라미터와 응답의 ID는 반드시 hashids로 암복호화
- AdminPermission 미들웨어는 Redis로 사용자 권한 캐시 (TTL=60s), N+1 조회 병목 제거

## 배포

### Docker Compose (권장)

프로젝트 루트에 `docker-compose.yml` 제공, 5개 서비스 오케스트레이션:

| 서비스 | 이미지 | 포트 |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | 로컬 `Dockerfile` 빌드 | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

PHP 이미지는 `Dockerfile`로 빌드하며, 기본 이미지 `php:8.3-cli`, OPcache 활성화.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

GitHub Actions 지속적 통합 파이프라인: `.github/workflows/ci.yml`

- PHP 문법 검사 (`php -l`)
- PHPUnit 단위 테스트
- Flutter 정적 분석 (`flutter analyze`)

### 데이터베이스 백업

`database/backup/` 디렉터리:

- `backup.sh` — mysqldump + gzip 백업, 30일 전 오래된 백업 자동 정리
- `restore.sh` — 인터랙티브 복원, 사용 가능한 백업 목록 표시

### Nginx 보안 설정

프로덕션 배포 시 `docs/nginx-security.conf`를 참조하여 리버스 프록시 보안을 강화하세요.

## 오픈소스는 쉽지 않습니다, 지원을 환영합니다

| 위챗 | 알리페이 |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

