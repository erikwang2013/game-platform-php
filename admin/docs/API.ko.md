# API 参考文档
<!-- lang-nav -->

Languages: [中文](API.md) · [English](API.en.md) · **한국어** · [Русский](API.ru.md) · [Deutsch](API.de.md) · [Français](API.fr.md) · [Español](API.es.md) · [Português](API.pt.md) · [हिन्दी](API.hi.md) · [العربية](API.ar.md) · [বাংলা](API.bn.md) · [Bahasa Indonesia](API.id.md) · [日本語](API.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 개요

开放管理后台 (open-admin)는 webman v2 기반으로 구축된 RESTful JSON API를 제공합니다. 모든 관리자 인터페이스는 JWT 인증과 RBAC 권한 검증이 필요하며, 공개 인터페이스는 `/api/v1` 프리픽스 아래에, 관리 인터페이스는 `/admin/v1` 프리픽스 아래에 마운트되며 버전은 URL 경로로 결정되고 요청 헤더는 사용하지 않습니다.

- **기본 URL**: `http://localhost:8787`
- **API 버전**: URL 경로에 포함됩니다 — 공개 엔드포인트는 `/api/v1`, 관리 엔드포인트는 `/admin/v1` 아래. 버전 요청 헤더는 사용하지 않으며, 향후 v2는 `/api/v2` 그룹으로 등록됩니다

> **엔드포인트 개요**: 인증(5) | 대시보드(1) | 사용자(7) | 역할(4) | 권한(4) | 설정(4) | 로그(1) | 개인 센터(3) | 가져오기/내보내기(3) | 업로드(1) | 운영(4: health/metrics/docs/security.txt) | 총 37개 엔드포인트
- **인증**: `Authorization: Bearer <token>`（JWT）
- **응답 형식**: `{ "code": 0, "message": "success", "data": {...} }`
- **문서 엔드포인트**: `GET /api/docs`는 OpenAPI 3.0 JSON 규범을 반환

### 요청 요구사항

- `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` 메서드만 허용하며, 다른 HTTP 메서드(TRACE, CONNECT, PATCH 등)는 405를 반환
- 모든 `POST` / `PUT` 요청은 `Content-Type: application/json`을 설정해야 함 (파일 업로드 제외), 위반 시 415 반환
- 요청 본문 크기는 10MB를 초과할 수 없으며, 초과 시 413 반환
- 보안 필터가 모든 요청 입력에 대해 XSS, SQL 인젝션, 경로 탐색, 명령 인젝션 스캔 수행, 적중 시 403 반환
- 연속 5회 로그인 실패 시 계정 잠금(15분)이 트리거되며, 잠금 중 로그인 요청은 429 반환
- 동일 사용자는 최대 3개의 유효 토큰을 동시에 보유할 수 있으며, 초과 시 가장 오래된 토큰이 자동으로 블랙리스트에 추가

## 2. 오류 코드

| code | 의미 | 트리거 시나리오 |
|------|------|---------|
| 0 | 성공 | |
| 400 | 요청 파라미터 오류 | 요청 형식이 올바르지 않음 |
| 401 | 미인증 | 토큰 누락 / 만료 / 블랙리스트 등록 |
| 403 | 권한 없음 / 보안 차단 | RBAC 권한 부족 / SecurityFilter 적중 |
| 404 | 리소스 없음 | 조회/수정/삭제 대상이 존재하지 않음 |
| 405 | 요청 메서드 허용 안 됨 | GET/POST/PUT/DELETE/OPTIONS/HEAD만 허용, 비표준 메서드는 즉시 거부 |
| 413 | 요청 본문 과대 | Content-Length가 10MB 초과 |
| 415 | 지원하지 않는 미디어 타입 | POST/PUT 요청의 Content-Type이 JSON이 아니고 파일 업로드도 아님 |
| 422 | 파라미터 검증 실패 | 필수 필드 누락, 형식 불일치, 비즈니스 검증 통과 실패 |
| 429 | 요청 과다 | RateLimit 트리거 / 계정 잠금 (연속 5회 로그인 실패 시 15분 잠금) |
| 500 | 서버 내부 오류 | |

## 3. 공개 엔드포인트

모든 공개 엔드포인트는 `/api/v1` 프리픽스 아래, 관리 엔드포인트는 `/admin/v1` 프리픽스 아래에 마운트됩니다. 버전은 라우트 그룹 프리픽스로 결정되며 버전 요청 헤더는 사용하지 않습니다. 공개 컨트롤러 예: `app\api\v1\controller\AuthController`.

### 3.1 헬스 체크

```
GET /health
```

- **인증**: 불필요
- **속도 제한**: 없음

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.1",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

`database`, `redis`, `elasticsearch` 값: `"ok"` | `"unavailable"`. `elasticsearch`는 ES에 연결할 수 없으면 `"unavailable"`을 반환하고, 클러스터 건강 상태가 green/yellow가 아니면 실제 status 값(예: `"red"`)을 반환합니다.

### 3.2 API 문서

```
GET /api/docs
```

- **인증**: 불필요
- **속도 제한**: 전역 기본값 (60회/분)
- **응답**: OpenAPI 3.0.3 JSON 규범, 모든 엔드포인트 정의, 파라미터 및 Schema 포함

### 3.3 클릭 캡차 생성

```
POST /api/v1/captcha/generate
```

- **인증**: 불필요
- **속도 제한**: 전역 기본값 (60회/분)

**요청 본문**:
```json
{
  "difficulty": "medium"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| difficulty | string | 아니요 | `easy` / `medium` / `hard`, 기본 `medium` |

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| key | string | 캡차 식별자, 검증 시 회신 |
| image | string | base64 인코딩 PNG 이미지 |
| extra.targets[].order | int | 클릭 순서 |
| extra.targets[].text | string | 클릭 대상 안내 텍스트 |

### 3.4 클릭 캡차 검증

```
POST /api/v1/captcha/verify
```

- **인증**: 불필요
- **속도 제한**: 전역 기본값 (60회/분)

**요청 본문**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| key | string | 예 | 캡차 key, generate가 반환 |
| clicks | array{object} | 예 | 클릭 좌표 배열, 각 요소는 `x`（int）와 `y`（int）포함 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

검증 실패 시 `code`는 422, `message`는 `"验证失败，请重试"`, `data.valid`는 `false`.

### 3.5 로그인

```
POST /api/v1/auth/login
```

- **인증**: 불필요
- **속도 제한**: 10회/분 (IP + 경로 기준)

**요청 본문**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| username | string | 예 | min:3, max:50 | 사용자 이름 |
| password | string | 예 | min:6, max:32 | 비밀번호 |
| captcha_key | string | 예 | | 캡차 key |
| clicks | array{object} | 예 | min:2 | 클릭 좌표 배열 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| access_token | string | JWT 액세스 토큰 |
| refresh_token | string | JWT 갱신 토큰 |
| expires_in | int | 액세스 토큰 유효기간(초), 기본 7200 |
| user.id | string | hashid 암호화된 사용자 ID |
| user.username | string | 사용자 이름 |
| user.real_name | string | 실명 |

**가능한 오류**:
- 422: 파라미터 검증 실패 (필수 필드 누락, 형식 불일치)
- 422: 캡차 오류, 다시 시도하세요
- 401: 사용자 이름 또는 비밀번호 오류
- 403: 계정이 비활성화됨
- 429: 계정이 잠겼습니다. 15분 후 다시 시도하세요 (연속 5회 로그인 실패 시 트리거)

### 3.6 가입

```
POST /api/v1/auth/register
```

- **인증**: 불필요
- **속도 제한**: 5회/분 (IP + 경로 기준)

**요청 본문**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| username | string | 예 | min:3, max:50 | 사용자 이름 (고유) |
| password | string | 예 | min:6, max:32 | 비밀번호 (bcrypt 해시 저장) |
| real_name | string | 예 | max:50 | 실명 |
| captcha_key | string | 예 | | 캡차 key |
| clicks | array{object} | 예 | min:2 | 클릭 좌표 배열 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

가입 성공 후 JWT 토큰을 바로 반환하며, 사용자 상태는 기본적으로 활성화됩니다 (status=1).

### 3.7 토큰 갱신

```
POST /api/v1/auth/refresh
```

- **인증**: 불필요
- **속도 제한**: 전역 기본값 (60회/분)

**요청 본문**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| refresh_token | string | 예 | 로그인/가입 시 획득한 refresh_token |

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

갱신 성공 시 새 access_token과 refresh_token을 동시에 반환하며, 기존 토큰은 자동으로 무효화됩니다. 갱신 시 사용자의 마지막 로그인 시간과 IP가 업데이트됩니다.

**가능한 오류**:
- 422: 갱신 토큰 누락
- 401: 갱신 토큰이 무효하거나 만료됨

### 3.8 Prometheus 모니터링 지표

```
GET /metrics
```

- **인증**: 불필요
- **속도 제한**: 없음
- **응답 형식**: Prometheus text format (`text/plain; version=0.0.4`)

공개 Prometheus 모니터링 지표 엔드포인트로, Grafana/Prometheus가 수집합니다.

**응답 예시**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| 지표명 | 타입 | 설명 |
|------|------|------|
| `openadmin_http_requests_total` | gauge | 누적 HTTP 요청 총수 |
| `openadmin_active_users` | gauge | 현재 활성 사용자 수 (24시간 내 로그인) |
| `openadmin_db_connection_status` | gauge | 데이터베이스 연결 상태, 1=정상, 0=이상 |
| `openadmin_redis_connection_status` | gauge | Redis 연결 상태, 1=정상, 0=이상 |
| `openadmin_memory_usage_bytes` | gauge | PHP 프로세스 현재 메모리 사용량 (bytes) |

## 4. 대시보드

모든 관리자 인터페이스는 `/admin/v1` 프리픽스에 마운트되며, `AdminAuth`（JWT 인증）, `AdminPermission`（RBAC 권한 검증）, `OperationLog`（작업 기록）세 가지 미들웨어를 통과합니다.

### 4.1 대시보드 데이터

```
GET /admin/v1/dashboard
```

- **인증**: JWT + RBAC
- **캐시**: Redis 5분

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/v1/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| stats 필드 | 타입 | 설명 |
|------|------|------|
| label | string | 지표 이름 |
| value | string | 지표 값 (문자열 타입) |
| icon | string | Material 아이콘 이름 |
| color | string | 카드 색상 값 |
| trend | float? | 일간 전일 대비 성장률(퍼센트), "사용자 총수"에만 있음 |

| trends 필드 | 타입 | 설명 |
|------|------|------|
| dates | array{string} | 최근 30일 날짜 시퀀스 |
| series | array{object} | 추세선 데이터, 각 항목은 name（이름）, data（값 배열）, color（색상）포함 |

## 5. 사용자 관리

모든 사용자 관리 인터페이스가 반환하는 `id`는 hashid 암호화 문자열입니다. 비밀번호 필드는 응답에서 제외됩니다. 휴대폰 번호와 이메일은 목록 인터페이스에서 마스킹 표시되고, 상세 인터페이스에서는 평문으로 반환됩니다 (데이터베이스 암호화 필드는 Encryptable trait가 자동 복호화).

### 5.1 사용자 목록

```
GET /admin/v1/user
```

- **인증**: JWT + RBAC

**쿼리 파라미터**:

| 파라미터 | 타입 | 필수 | 기본값 | 설명 |
|------|------|------|------|------|
| page | int | 아니요 | 1 | 페이지 번호 |
| limit | int | 아니요 | 15 | 페이지당 항목 수 |
| keyword | string | 아니요 | | 검색 키워드, 사용자 이름과 실명 매칭 |
| status | int | 아니요 | | 상태 필터, 0=비활성, 1=활성 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| id | string | hashid 암호화된 사용자 ID |
| username | string | 사용자 이름 |
| real_name | string | 실명 |
| phone | string | 마스킹된 휴대폰 번호 (`138****5678` 형식) |
| email | string | 마스킹된 이메일 (`a***@example.com` 형식) |
| status | int | 1=활성, 0=비활성 |
| last_login_at | string | 마지막 로그인 시간 (datetime) |
| created_at | string | 생성 시간 (datetime) |

### 5.2 사용자 생성

```
POST /admin/v1/user
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| username | string | 예 | min:3, max:50 | 사용자 이름 (고유) |
| password | string | 예 | min:6, max:32 | 비밀번호 (bcrypt 저장) |
| real_name | string | 예 | max:50 | 실명 |
| phone | string | 아니요 | | 휴대폰 번호 (Encryptable 암호화 저장) |
| email | string | 아니요 | | 이메일 (Encryptable 암호화 저장) |
| status | int | 아니요 | in:0,1 | 상태, 기본 1（활성） |

**응답 예시**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**가능한 오류**:
- 422: 사용자 이름이 이미 존재함
- 422: 파라미터 검증 실패 (필수 필드 누락)

### 5.3 사용자 상세

```
GET /admin/v1/user/{id}
```

- **인증**: JWT + RBAC
- **경로 파라미터**: `{id}`는 hashid 암호화된 사용자 ID

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

상세 인터페이스에서 `phone`과 `email`은 평문으로 반환되며 (데이터베이스에는 암호화 저장, Encryptable cast가 자동 복호화), 마스킹하지 않습니다. `password`와 `id_card`는 항상 응답에 포함되지 않습니다.

**가능한 오류**:
- 404: 사용자가 존재하지 않음

### 5.4 사용자 수정

```
PUT /admin/v1/user/{id}
```

- **인증**: JWT + RBAC
- **경로 파라미터**: `{id}`는 hashid 암호화된 사용자 ID

**요청 본문**:
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| real_name | string | 아니요 | 실명, 미전달 시 기존 값 유지 |
| password | string | 아니요 | 새 비밀번호, 빈 문자열 또는 미전달 시 변경하지 않음 |
| phone | string | 아니요 | 휴대폰 번호 |
| email | string | 아니요 | 이메일 |
| status | int | 아니요 | 0=비활성, 1=활성 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**가능한 오류**:
- 404: 사용자가 존재하지 않음

### 5.5 사용자 삭제

```
DELETE /admin/v1/user/{id}
```

- **인증**: JWT + RBAC
- **경로 파라미터**: `{id}`는 hashid 암호화된 사용자 ID
- **민감 작업**: 비밀번호 2차 확인 필요

**요청 본문**:
```json
{
  "password": "admin_password"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| password | string | 예 | 현재 로그인 사용자 비밀번호 (2차 확인) |

**응답 예시**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

소프트 삭제를 수행합니다 (Eloquent SoftDeletes), 데이터에 deleted_at을 표시하고 물리적으로 삭제하지 않습니다.

**가능한 오류**:
- 404: 사용자가 존재하지 않음
- 422: 민감 작업은 비밀번호 확인이 필요합니다 (password가 비어 있음)
- 422: 비밀번호 검증 실패 (비밀번호 불일치)

### 5.6 사용자 일괄 삭제

```
POST /admin/v1/user/batch/destroy
```

- **인증**: JWT + RBAC
- **민감 작업**: 비밀번호 2차 확인 필요

**요청 본문**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| ids | array{string} | 예 | hashid 암호화된 사용자 ID 배열 |
| password | string | 예 | 현재 로그인 사용자 비밀번호 (2차 확인) |

**응답 예시**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

소프트 삭제를 수행하며, `data.count`는 실제 삭제된 수량입니다.

**가능한 오류**:
- 422: 삭제할 사용자를 선택하세요 (ids가 비어 있음)
- 422: 유효하지 않은 ID (hashid 디코딩 실패)
- 422: 비밀번호 검증 실패

### 5.7 사용자 일괄 활성/비활성

```
POST /admin/v1/user/batch/status
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| ids | array{string} | 예 | hashid 암호화된 사용자 ID 배열 |
| status | int | 예 | 0=비활성, 1=활성 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

message는 status 값에 따라 `"批量启用成功"` 또는 `"批量禁用成功"`로 동적으로 변경됩니다.

**가능한 오류**:
- 422: 사용자를 선택하세요 (ids가 비어 있음)
- 422: 상태 값이 유효하지 않음 (status가 0 또는 1이 아님)

## 6. 역할 관리

### 6.1 역할 목록

```
GET /admin/v1/role
```

- **인증**: JWT + RBAC

**쿼리 파라미터**:

| 파라미터 | 타입 | 필수 | 기본값 | 설명 |
|------|------|------|------|------|
| page | int | 아니요 | 1 | 페이지 번호 |
| limit | int | 아니요 | 15 | 페이지당 항목 수 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| id | string | hashid 암호화된 역할 ID |
| name | string | 역할 이름 |
| slug | string | 역할 식별자 (고유, 권한 판단에 사용) |
| description | string | 역할 설명 |
| status | int | 1=활성, 0=비활성 |
| users_count | int | 해당 역할을 가진 사용자 수 |

### 6.2 역할 생성

```
POST /admin/v1/role
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| name | string | 예 | max:50 | 역할 이름 |
| slug | string | 예 | max:50 | 역할 식별자 |
| description | string | 아니요 | | 역할 설명, 기본 빈 문자열 |
| status | int | 아니요 | | 상태, 기본 1 |
| permission_ids | array{int} | 아니요 | | 권한 ID 배열 (원시 INT ID, hashid 아님) |

**응답 예시**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 역할 수정

```
PUT /admin/v1/role/{id}
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| name | string | 아니요 | 역할 이름 |
| description | string | 아니요 | 설명 |
| status | int | 아니요 | 0=비활성, 1=활성 |
| permission_ids | array{int} | 아니요 | 권한 ID 배열, 전달 시 역할 권한을 동기화 (덮어쓰기) |

**응답 예시**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 역할 삭제

```
DELETE /admin/v1/role/{id}
```

- **인증**: JWT + RBAC
- **민감 작업**: 비밀번호 2차 확인 필요

**요청 본문**:
```json
{
  "password": "admin_password"
}
```

**응답 예시**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

삭제 시 역할과 모든 권한, 사용자의 연관 관계를 자동으로 해제한 후 역할 레코드를 물리적으로 삭제합니다.

## 7. 권한 관리

권한은 트리 구조 (parent_id 자기 참조)를 사용하며, 세 가지 유형으로 나뉩니다. 목록 인터페이스는 완전한 권한 트리를 반환합니다.

### 7.1 권한 트리

```
GET /admin/v1/permission
```

- **인증**: JWT + RBAC

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/v1/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/v1/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| id | string | hashid 암호화 |
| parent_id | string | 부모 권한 hashid, "0"은 루트 노드 |
| name | string | 권한 이름 |
| slug | string | 권한 식별자 (라우트/버튼 식별자) |
| type | int | 1=메뉴, 2=버튼, 3=인터페이스 |
| icon | string | 메뉴 아이콘 (Material 아이콘 이름) |
| path | string | 프론트엔드 라우트 경로 |
| sort | int | 정렬 값 (오름차순) |
| children | array? | 하위 권한 목록 (재귀), 하위 노드가 없으면 이 필드 미포함 |

### 7.2 권한 생성

```
POST /admin/v1/permission
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/v1/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| parent_id | int | 아니요 | | 부모 권한 ID (원시 INT 타입), 기본 0 |
| name | string | 예 | max:50 | 권한 이름 |
| slug | string | 예 | max:100 | 권한 식별자 |
| type | int | 예 | in:1,2,3 | 1=메뉴, 2=버튼, 3=인터페이스 |
| icon | string | 아니요 | | 메뉴 아이콘, 기본 빈 값 |
| path | string | 아니요 | | 프론트엔드 라우트 경로, 기본 빈 값 |
| sort | int | 아니요 | | 정렬 값, 기본 0 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/v1/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 권한 수정

```
PUT /admin/v1/permission/{id}
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| name | string | 아니요 | 권한 이름 |
| icon | string | 아니요 | 아이콘 |
| path | string | 아니요 | 라우트 경로 |
| sort | int | 아니요 | 정렬 값 |

### 7.4 권한 삭제

```
DELETE /admin/v1/permission/{id}
```

- **인증**: JWT + RBAC
- **민감 작업**: 비밀번호 2차 확인 필요

**요청 본문**:
```json
{
  "password": "admin_password"
}
```

**응답 예시**:
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

삭제 시 모든 하위 권한 (`parent_id` = 현재 권한 ID인 레코드)을 캐스케이드 삭제하고, 모든 역할과의 연관 관계도 해제합니다.

## 8. 시스템 설정

시스템 설정은 `group` + `key` 조합으로 고유합니다.

### 8.1 설정 목록

```
GET /admin/v1/config
```

- **인증**: JWT + RBAC

**쿼리 파라미터**:

| 파라미터 | 타입 | 필수 | 기본값 | 설명 |
|------|------|------|------|------|
| page | int | 아니요 | 1 | 페이지 번호 |
| limit | int | 아니요 | 15 | 페이지당 항목 수 |
| group | string | 아니요 | | 설정 그룹별 필터 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| id | string | hashid |
| group | string | 설정 그룹 (예: `system`, `email`, `storage`) |
| key | string | 설정 키 |
| value | string | 설정 값 |
| type | string | 값 타입 힌트 (`string`, `integer`, `boolean`, `json` 등) |
| description | string | 설정 설명 |

### 8.2 설정 생성

```
POST /admin/v1/config
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| group | string | 예 | max:100 | 설정 그룹 |
| key | string | 예 | max:100 | 설정 키 (같은 그룹 내 고유) |
| value | string | 예 | | 설정 값 |
| type | string | 아니요 | | 값 타입, 기본 `string` |
| description | string | 아니요 | | 설정 설명, 기본 빈 값 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**가능한 오류**:
- 422: 설정 항목이 이미 존재함 (같은 group + key)

### 8.3 설정 수정

```
PUT /admin/v1/config/{id}
```

- **인증**: JWT + RBAC

**요청 본문**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| value | string | 아니요 | 설정 값 수정 |
| type | string | 아니요 | 값 타입 수정 |
| description | string | 아니요 | 설명 텍스트 수정 |

### 8.4 설정 삭제

```
DELETE /admin/v1/config/{id}
```

- **인증**: JWT + RBAC
- **민감 작업**: 비밀번호 2차 확인 필요

**요청 본문**:
```json
{
  "password": "admin_password"
}
```

설정 레코드를 물리적으로 삭제합니다.

## 9. 작업 로그

작업 로그는 읽기 전용 인터페이스로, `OperationLog` 미들웨어가 POST/PUT/DELETE 요청마다 자동으로 기록하며, 저장 필드는 `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`입니다.

### 9.1 작업 로그 목록

```
GET /admin/v1/log
```

- **인증**: JWT + RBAC

**쿼리 파라미터**:

| 파라미터 | 타입 | 필수 | 기본값 | 설명 |
|------|------|------|------|------|
| page | int | 아니요 | 1 | 페이지 번호 |
| limit | int | 아니요 | 15 | 페이지당 항목 수 |
| user_id | int | 아니요 | | 사용자 ID별 정확 필터 (원시 INT 타입) |
| action | string | 아니요 | | 작업 동작별 정확 필터 |
| path | string | 아니요 | | 요청 경로별 부분 일치 필터 |
| start_date | string | 아니요 | | 시작 날짜 (Y-m-d 형식) |
| end_date | string | 아니요 | | 종료 날짜 (Y-m-d 형식) |

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/v1/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| id | string | hashid |
| user_name | string | 작업 사용자 이름 (user 연관으로 획득, 미로그인 작업은 "시스템" 표시) |
| action | string | 작업 동작 설명 |
| method | string | HTTP 메서드 (POST/PUT/DELETE) |
| path | string | 요청 경로 |
| ip | string | 클라이언트 IP |
| source | string | 요청 출처 |
| input | string | 요청 파라미터 JSON 문자열 (파일 제외) |
| created_at | string | 작업 시간 (datetime) |

## 10. 개인 센터

개인 센터 인터페이스는 JWT 인증만 필요합니다 (RBAC 권한 검증 불필요 — `AdminPermission` 미들웨어에서 화이트리스트에 추가해야 함).

### 10.1 개인 정보 수정

```
PUT /admin/v1/profile
```

- **인증**: JWT

**요청 본문**:
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| real_name | string | 아니요 | 실명 |
| phone | string | 아니요 | 휴대폰 번호 (Encryptable 암호화 저장) |
| email | string | 아니요 | 이메일 (Encryptable 암호화 저장) |

**응답 예시**:
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

응답에서 `phone`과 `email`은 평문으로 반환되고, `password`와 `id_card`는 제외됩니다.

### 10.2 비밀번호 변경

```
PUT /admin/v1/profile/password
```

- **인증**: JWT

**요청 본문**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| 필드 | 타입 | 필수 | 검증 규칙 | 설명 |
|------|------|------|---------|------|
| old_password | string | 예 | | 현재 비밀번호 |
| new_password | string | 예 | min:6, max:32 | 새 비밀번호 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**가능한 오류**:
- 422: 기존 비밀번호와 새 비밀번호를 입력하세요
- 422: 기존 비밀번호 오류
- 422: 새 비밀번호는 6-32자

### 10.3 로그아웃

```
POST /admin/v1/profile/logout
```

- **인증**: JWT

**요청 본문**: 없음 (requestBody 없음, Authorization 헤더에서 토큰 읽음)

**응답 예시**:
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

로그아웃 로직: JWT를 디코딩하여 남은 유효기간 (exp - now)을 구하고, 해당 토큰의 md5 해시를 Redis 블랙리스트 `jwt_blacklist:{md5}`에 TTL = 남은 유효기간으로 기록. 블랙리스트의 토큰은 `AdminAuth` 미들웨어에서 차단되어 401을 반환합니다.

토큰이 없으면 401을 반환합니다. 토큰이 만료/무효인 경우 (디코딩 예외 발생)에도 로그아웃 성공으로 간주합니다.

## 11. 가져오기/내보내기

### 11.1 Excel 내보내기

```
POST /admin/v1/export/excel
```

- **인증**: JWT + RBAC
- **응답 타입**: 파일 다운로드 (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**요청 본문**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| 필드 | 타입 | 필수 | 기본값 | 설명 |
|------|------|------|------|------|
| table | string | 아니요 | `admin_user` | 내보낼 테이블 이름. 지원: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | 아니요 | | 내보낼 열 필드 이름 배열, 비어 있으면 해당 테이블 전체 열 내보내기 |
| conditions | object | 아니요 | `{}` | 필터 조건, key-value 쌍, 값이 비어 있지 않으면 WHERE에 사용 |
| title | string | 아니요 | `数据导出` | Excel 제목 (Sheet 이름으로 표시) |

**지원 테이블과 열**:

| table | 사용 가능한 열 |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

민감 필드 `phone`, `email`, `id_card`는 내보내기 시 자동으로 마스킹 처리됩니다. 데이터 상한은 10000행입니다. Excel 첫 행 고정, 자동 필터.

### 11.2 PDF 내보내기

```
POST /admin/v1/export/pdf
```

- **인증**: JWT + RBAC
- **응답 타입**: 파일 다운로드 (`application/pdf`, A4 가로)

**요청 본문**:
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

또는 테이블 모드:
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| 필드 | 타입 | 필수 | 기본값 | 설명 |
|------|------|------|------|------|
| type | string | 아니요 | `table` | 내보내기 타입: `table` / `dashboard` |
| title | string | 아니요 | `数据导出` | PDF 제목 |
| data | object | 아니요 | `{}` | 내보내기 데이터 |

`type=dashboard`일 때 `data`는 `stats` 배열 (카드 형태 렌더링)을 포함해야 하고, `type=table`일 때 `data`는 `columns`와 `rows` 배열을 포함해야 합니다.

PDF 템플릿에는 저작권 정보와 내보내기 타임스탬프가 포함됩니다.

### 11.3 사용자 가져오기 (Excel)

```
POST /admin/v1/import/users
```

- **인증**: JWT + RBAC
- **요청 타입**: `multipart/form-data`（파일 업로드）

**폼 필드**:

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| file | file | 예 | `.xlsx` 또는 `.xls` 형식 |

**Excel 열 요구사항**:

| 열 이름 | 필수 | 설명 |
|------|------|------|
| username | 예 | 사용자 이름 (고유) |
| password | 예 | 비밀번호 (bcrypt 해시 저장) |
| real_name | 예 | 실명 |
| phone | 아니요 | 휴대폰 번호 |
| email | 아니요 | 이메일 |
| status | 아니요 | 상태, 기본 1 |

1행은 열 제목 (대소문자 구분 없음), 2행부터 데이터입니다.

**응답 예시**:
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| total | int | 총 행 수 (제목 행 제외) |
| success | int | 성공적으로 가져온 수 |
| failed | int | 실패한 수 |
| errors | array | 실패 상세, 각 항목은 row（Excel 행 번호）와 reason（실패 원인）포함 |

## 12. 파일 업로드

```
POST /admin/v1/upload
```

- **인증**: JWT + RBAC
- **요청 타입**: `multipart/form-data`

**폼 필드**:

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| file | file | 예 | 업로드 파일 |

**허용 파일 타입**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**최대 파일 크기**: 10MB

**응답 예시**:
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

파일은 날짜별 디렉터리 `public/upload/{Y-m-d}/`에 저장되며, 파일 이름은 `md5(uniqid) + 원본 확장자`입니다. `url`은 사이트 루트 경로 기준 상대 경로입니다.

**가능한 오류**:
- 422: 파일을 선택하세요 (업로드 안 됨)
- 422: 지원하지 않는 파일 타입
- 422: 파일 크기는 10MB를 초과할 수 없음
- 500: 파일 업로드 실패 (파일 무효)

## 13. 응답 헤더

모든 인터페이스 (전역 미들웨어 계층에서 주입)는 다음 응답 헤더를 포함합니다:

| 헤더 | 설명 |
|----|------|
| `X-RateLimit-Limit` | 속도 제한 상한 (횟수) |
| `X-RateLimit-Remaining` | 남은 요청 횟수 |
| `X-RateLimit-Reset` | 속도 제한 윈도우 리셋 타임스탬프 |
| `Retry-After` | 속도 제한 트리거 시에만 반환, 권장 대기 초수 |
| `X-Content-Type-Options` | `nosniff` (webman 기본, MIME 스니핑 금지) |
| `X-Frame-Options` | `DENY` (webman의 CORS 미들웨어/기본 설정 제공) |

속도 제한 상세:
- 기본 전역 제한: 60회/분 / IP+경로
- 로그인 엔드포인트 `/api/v1/auth/login`: 10회/분
- 가입 엔드포인트 `/api/v1/auth/register`: 5회/분
- Redis 원자화 슬라이딩 윈도우 알고리즘 (Lua ZSET) 사용, TOCTOU 경쟁 상태 방지
- Redis 사용 불가 시 fail-closed: 503 반환 (`Retry-After: 5`), 요청 통과시키지 않음

## 14. 데이터 분석 (Analytics)

모든 엔드포인트는 인증 (`AdminAuth` + `AdminPermission`) 필요, MySQL 실시간 집계, 총 12개:

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/v1/analytics/overview | 플랫폼 개요 (오늘/최근 7일) |
| GET | /admin/v1/analytics/game-ranking | 게임 랭킹 (?days=7) |
| GET | /admin/v1/analytics/dau-trend | DAU 추세 (?days=30) |
| GET | /admin/v1/analytics/hourly-trend | 시간대 추세 |
| GET | /admin/v1/analytics/action-distribution | 행동 분포 |
| GET | /admin/v1/analytics/revenue | 매출 분석 |
| GET | /admin/v1/analytics/conversion | 게임 전환율 |
| GET | /admin/v1/analytics/probability | 결합/조건부 확률 |
| GET | /admin/v1/analytics/retention | 리텐션 분석 D1/D3/D7/D30 |
| GET | /admin/v1/analytics/funnel | 전환 퍼널 |
| GET | /admin/v1/analytics/arpu | ARPU/ARPPU 추세 |
| GET | /admin/v1/analytics/economy | 게임 통화 경제 지표 |

## 15. 티켓 관리 (Ticket)

모든 엔드포인트는 인증 (`AdminAuth` + `AdminPermission`) 필요, 총 5개:

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/v1/ticket/list | 티켓 목록 (?page=&limit=&status=&type=) |
| GET | /admin/v1/ticket/{hashid} | 티켓 상세 (답변 포함) |
| POST | /admin/v1/ticket/{hashid}/reply | 티켓 답변 |
| POST | /admin/v1/ticket/{hashid}/close | 티켓 종료 |
| POST | /admin/v1/ticket/{hashid}/assign | 처리자 지정 (admin_id) |

## 16. 인증 흐름

완전한 인증 시퀀스:

```
1. 클라이언트가 POST /api/v1/captcha/generate 요청
    ↓
   서버 반환: key + base64 이미지 + 클릭 대상 안내
   
2. 사용자가 이미지의 대상 위치를 클릭, 프론트/클라이언트가 클릭 좌표 수집
   
3. 클라이언트가 POST /api/v1/auth/login 요청
   (요청 헤더: Content-Type: application/json)
   요청 본문: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   서버:
   a. 파라미터 검증 → 422
   b. 캡차 검증 → 422
   c. 사용자 자격 증명 검증 → 401
   d. 계정 상태 확인 → 403
   e. JWT 발급 (access + refresh) → 200
   f. last_login_at / last_login_ip 업데이트
    ↓
   클라이언트 저장: access_token, refresh_token, expires_in

4. 이후 요청에 JWT 포함
   요청 헤더: Authorization: Bearer <access_token>
    ↓
   AdminAuth 미들웨어:
   a. Bearer 토큰 추출
   b. 블랙리스트 확인 (Redis jwt_blacklist:{md5}) → 401
   c. JWT 디코딩, 만료 검증 → 401
   d. $request->adminId = sub 필드 설정
    ↓
   AdminPermission 미들웨어:
   a. 미로그인 (adminId 비어 있음) → 401
   b. 리소스 라우트에 대한 권한 식별자 해석
   c. 사용자 역할 조회 → 역할 권한 매칭
   d. 권한 없음 → 403
    ↓
   Controller가 요청 처리
    ↓
   Response + X-RateLimit-* 헤더

5. Access Token 만료 전 갱신
   클라이언트가 POST /api/v1/auth/refresh 요청
   요청 본문: { refresh_token: "..." }
    ↓
   서버가 refresh_token 디코딩 → 새 access + refresh 발급
    ↓
   클라이언트가 로컬 토큰 업데이트

6. 로그아웃
   클라이언트가 POST /admin/v1/profile/logout 요청
   요청 헤더: Authorization: Bearer <access_token>
    ↓
   서버:
   a. JWT 디코딩으로 남은 TTL 획득
   b. Redis 블랙리스트 기록: jwt_blacklist:{md5(token)} = 1, TTL = 남은 유효기간
   c. 성공 반환
```

### JWT 구조

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, 기본 TTL 7200초 (JWT 설정 `default_expire`로 제어)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, 기본 TTL 1209600초 (JWT 설정 `refresh_expire`로 제어, 즉 14일)

### 보안 관리

- 비밀번호는 `PASSWORD_BCRYPT` 해시로 저장
- 민감 필드 (phone, email, id_card)는 `erikwang2013/encryptable`로 데이터베이스 계층에서 투명하게 암복호화
- API 계층 ID는 `erikwang2013/hashids`로 암호화 전송, 원시 snowflake ID 시퀀스 노출 방지
- SecurityFilter가 전역으로 XSS, SQL 인젝션, 경로 탐색, 명령 인젝션 스캔, 동일 IP 5회/60초 시 임시 블랙리스트 15분
- 민감 작업 (사용자, 역할, 권한, 설정 삭제)은 현재 로그인 사용자 비밀번호 2차 확인 필요
- 동시 세션 제한: 동일 사용자 최대 3개 유효 토큰, 4번째 기기 로그인 시 가장 오래된 토큰이 강제로 블랙리스트에 추가
- 계정 잠금: 연속 5회 로그인 실패 시 15분 계정 잠금 트리거, 잠금 중 429 반환

## 15. 배포 운영

### Docker Compose

프로젝트 루트에 `docker-compose.yml` 제공, 5개 서비스 (Nginx, webman app, MySQL, Redis, Elasticsearch) 오케스트레이션. PHP는 `Dockerfile`로 빌드 (`php:8.3-cli` 기반, OPcache 활성화).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml`에 GitHub Actions 지속적 통합 파이프라인 정의:
- `php -l` 문법 검사
- PHPUnit 단위 테스트
- `flutter analyze` 정적 분석

### 데이터베이스 백업

`database/backup/` 디렉터리에서 백업/복원 스크립트 제공:
- `backup.sh` — mysqldump + gzip 압축 백업, 30일 전 오래된 백업 파일 자동 정리
- `restore.sh` — 인터랙티브 복원, 기존 백업 목록 표시

### Nginx 보안 설정

프로덕션 환경 배포 시 `docs/nginx-security.conf`를 참조하여 리버스 프록시 보안 강화 설정을 하세요.

## 16. 데이터 분석 (Analytics)

데이터 분석 인터페이스는 `AnalyticsController`가 제공하며, 모두 MySQL 실시간 집계 (`game_game_play_log` 게임 행동 로그 / `game_deposit_order` 충전 주문) 기반, 데이터베이스 장애 시 500이 아닌 빈 데이터를 반환합니다. 특별한 언급이 없으면 모두 JWT + RBAC 인증이 필요하며, 응답 포장 형식은 통일적으로 `{ "code": 0, "message": "success", "data": ... }`입니다.

### 16.1 플랫폼 개요

```
GET /admin/v1/analytics/overview
```

**응답**: `today` / `week` 각각 `dau`（활성 사용자 수）, `revenue`（확인된 충전 총액, 문자열）, `new_users`（신규 사용자 수）포함.

### 16.2 게임 랭킹

```
GET /admin/v1/analytics/game-ranking?days=7
```

**응답**: 게임 행동 횟수 내림차순 상위 10개, 각 항목은 `game_id`（hashid）, `name`, `plays`, `players` 포함.

### 16.3 DAU 추세

```
GET /admin/v1/analytics/dau-trend?days=30
```

**응답**: `{ "日期": 活跃数, ... }`, 누락된 날짜는 0으로 채움.

### 16.4 시간대 추세

```
GET /admin/v1/analytics/hourly-trend?game_id=<hashid>
```

**응답**: `{ "0": 次数, ... "23": 次数 }` 24개 정시 슬롯; `game_id`가 비어 있으면 전체 게임 집계.

### 16.5 행동 분포

```
GET /admin/v1/analytics/action-distribution?game_id=<hashid>&hours=24
```

**응답**: `{ "start": n, "end": n, "earn": n, "spend": n }` 네 가지 행동 카운트; `hours` 상한 168.

### 16.6 매출 개요

```
GET /admin/v1/analytics/revenue?days=7
```

**응답**: `{ "total": "总额", "trend": { "日期": "当日额", ... } }`, `status=confirmed` 주문만 집계.

### 16.7 게임 전환율

```
GET /admin/v1/analytics/conversion?days=30
```

**응답**: 각 게임은 `game_id`（hashid）, `game_name`, `players`（중복 제거 플레이어 수）, `depositors`（중복 제거 충전 인원）, `conversion_rate`（충전 전환율, 0~1）포함.

### 16.8 결합 확률

```
GET /admin/v1/analytics/probability?game_a=<hashid>&game_b=<hashid>
```

**응답**: `{ "joint": { "joint_probability": 0.12, "confidence": 0.3 } }` — Jaccard 계수 (두 게임 공동 플레이어 / 합집합 플레이어)와 신뢰도 (공동 플레이어 / A 게임 플레이어).

### 16.9 리텐션 분석

```
GET /admin/v1/analytics/retention?days=30
```

**응답**: `{ "D1": "8.5%", "D3": "...", "D7": "...", "D30": "..." }` 가입일 기준 다음날/3일/7일/30일 리텐션율.

### 16.10 전환 퍼널

```
GET /admin/v1/analytics/funnel?days=30
```

**응답**: 가입 → 첫 충전 → 첫 환전 → 첫 게임 네 단계의 `step`, `count`, `rate`（가입 수 대비 퍼센트）.

### 16.11 ARPU/ARPPU 추세

```
GET /admin/v1/analytics/arpu?days=30
```

**응답**: `{ "dates": [...], "arpu": [...], "arppu": [...] }` 일별 1인당 매출 (ARPU)과 유료 사용자 1인당 매출 (ARPPU).

### 16.12 게임 경제 지표

```
GET /admin/v1/analytics/economy
```

**응답**: `currencies` 배열, 각 항목은 `game_name`, `currency`, `symbol`, `total_minted`（발행 총량）, `total_burned`（소각 총량）, `circulation`（유통량）, `inflation_rate`（인플레이션율）포함, bcmath 고정밀 계산 사용.

## 17. 결제 관리 (Payment)

결제 수단 관리는 `PaymentController`가 제공하며, 5개 엔드포인트 모두 JWT + RBAC 인증이 필요합니다. `provider` 화이트리스트: `stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash`. `config`는 결제 설정 JSON 문자열(DB에 암호화 저장)입니다.

| 메서드 | 경로 | 설명 |
|------|------|------|
| GET | /admin/v1/payment/method/list | 결제 수단 목록(sort 오름차순) |
| POST | /admin/v1/payment/method/toggle | 결제 수단 활성/비활성 전환 |
| POST | /admin/v1/payment/method/create | 결제 수단 생성 |
| PUT | /admin/v1/payment/method/{hashid} | 결제 수단 업데이트 |
| DELETE | /admin/v1/payment/method/{hashid} | 결제 수단 삭제(pending 주문 존재 시 거부) |

### 17.1 결제 수단 목록

```
GET /admin/v1/payment/method/list
```

- **인증**: JWT + RBAC

**응답 예시**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "name": "Stripe 신용카드",
        "type": "fiat",
        "provider": "stripe",
        "status": 1,
        "sort": 1,
        "countries": ["US", "SG"],
        "currency": "USD",
        "min_amount": "10",
        "max_amount": "5000",
        "config": null,
        "created_at": "2026-08-29 10:00:00",
        "updated_at": "2026-08-29 10:00:00"
      }
    ]
  }
}
```

| 필드 | 타입 | 설명 |
|------|------|------|
| id | string | 결제 수단 ID(hashid 인코딩) |
| name | string | 결제 수단 이름 |
| type | string | `fiat`(법정화폐) / `crypto`(암호화폐) |
| provider | string | 게이트웨이 제공자: `stripe` / `paypal` / `nowpayments` / `coinbase` / `skrill` / `neteller` / `paysafecard` / `paytm` / `mercadopago` / `astropay` / `paypay` / `kakaopay` / `gcash` |
| status | int | 1=활성, 0=비활성 |
| sort | int | 정렬값(오름차순) |
| countries | array{string} | 표시 대상 국가 코드 배열(빈 배열=전 세계 표시) |
| currency | string | 통화(예: USD/USDT), 비어 있으면 제한 없음 |
| min_amount / max_amount | string | 금액 범위(정밀도 유지용 문자열), 0=제한 없음 |
| config | string? | 결제 설정 JSON(암호화, 미설정 시 null) |

### 17.2 결제 수단 활성/비활성 전환

```
POST /admin/v1/payment/method/toggle
```

**요청 본문**:
```json
{
  "id": "a1b2c3d4",
  "status": 1
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| id | string | 예 | 결제 수단 ID(hashid) |
| status | int | 예 | 0=비활성, 1=활성 |

**가능한 오류**:
- 422: 검증 실패(id/status 누락 또는 status가 0/1 아님)
- 404: 결제 수단이 존재하지 않음

### 17.3 결제 수단 생성

```
POST /admin/v1/payment/method/create
```

**요청 본문**:
```json
{
  "name": "USDT 암호화폐",
  "type": "crypto",
  "provider": "nowpayments",
  "status": 1,
  "sort": 2,
  "countries": [],
  "currency": "USDT",
  "min_amount": "10",
  "max_amount": "10000",
  "config": "{\"api_key\":\"...\"}"
}
```

| 필드 | 타입 | 필수 | 검증 | 설명 |
|------|------|------|---------|------|
| name | string | 예 | max:50 | 결제 수단 이름 |
| type | string | 예 | in:fiat,crypto | 유형: 법정화폐/암호화폐 |
| provider | string | 예 | in:stripe,paypal,nowpayments,coinbase,skrill,neteller,paysafecard,paytm,mercadopago,astropay,paypay,kakaopay,gcash | 게이트웨이 제공자 화이트리스트 |
| status | int | 예 | in:0,1 | 상태 |
| sort | int | 아니요 | integer,min:0 | 정렬값, 기본 0 |
| countries | array{string} | 아니요 | max:2 | 표시 국가 코드, 비어 있으면 전 세계 |
| currency | string | 아니요 | max:10 | 통화, 기본 비어 있음 |
| min_amount / max_amount | string | 아니요 | numeric,min:0 | 금액 범위, 기본 "0" |
| config | string | 아니요 | | 결제 설정 JSON(암호화), 빈 문자열은 NULL로 저장 |

**응답 예시**:
```json
{
  "code": 0,
  "message": "생성 성공",
  "data": { "id": "e5f6g7h8" }
}
```

**가능한 오류**:
- 422: 검증 실패

### 17.4 결제 수단 업데이트

```
PUT /admin/v1/payment/method/{hashid}
```

- **경로 파라미터**: `{hashid}`는 hashid 인코딩된 결제 수단 ID
- **요청 본문**: 생성(17.3)과 동일, 모든 필드 선택, 전달된 필드만 업데이트

**가능한 오류**:
- 404: 결제 수단이 존재하지 않음
- 422: 검증 실패

### 17.5 결제 수단 삭제

```
DELETE /admin/v1/payment/method/{hashid}
```

- **경로 파라미터**: `{hashid}`는 hashid 인코딩된 결제 수단 ID

**가능한 오류**:
- 404: 결제 수단이 존재하지 않음
- 422: 대기 중인 입금 주문(status=pending)이 존재하여 삭제 불가
