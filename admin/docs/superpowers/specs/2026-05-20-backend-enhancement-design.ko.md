# 서브 프로젝트 A: 백엔드 강화 — 설계 명세
<!-- lang-nav -->

Languages: [中文](2026-05-20-backend-enhancement-design.md) · [English](2026-05-20-backend-enhancement-design.en.md) · **한국어** · [Русский](2026-05-20-backend-enhancement-design.ru.md) · [Deutsch](2026-05-20-backend-enhancement-design.de.md) · [Français](2026-05-20-backend-enhancement-design.fr.md) · [Español](2026-05-20-backend-enhancement-design.es.md) · [Português](2026-05-20-backend-enhancement-design.pt.md) · [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) · [العربية](2026-05-20-backend-enhancement-design.ar.md) · [বাংলা](2026-05-20-backend-enhancement-design.bn.md) · [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) · [日本語](2026-05-20-backend-enhancement-design.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 범위

이번은 백엔드 강화로 총 15개 기능 포인트, 신규 파일 9개 + 수정 파일 4개가 포함됩니다.

---

## 신규/수정 파일 목록

```
app/middleware/
├── OperationLog.php          # 신규: 작업 로그 자동 기록
├── Cors.php                  # 신규: 크로스 도메인
└── RateLimit.php             # 신규: Redis 레이트 리밋
app/admin/controller/
├── ConfigController.php      # 신규: 시스템 설정 CRUD
├── LogController.php         # 신규: 작업 로그 조회
├── ProfileController.php     # 신규: 개인 센터 (로그아웃 포함)
├── UploadController.php      # 신규: 파일 업로드
├── ImportController.php      # 신규: Excel 사용자 가져오기
└── HealthController.php      # 신규: 헬스 체크
app/model/
├── AdminUser.php             # 수정: SoftDeletes + Searchable trait 추가
└── OperationLog.php          # 수정: public $timestamps = false 추가
app/middleware/
└── AdminAuth.php             # 수정: JWT 블랙리스트 검증
app/admin/controller/
├── DashboardController.php   # 수정: 데이터베이스 실시간 통계로 변경
└── UserController.php        # 수정: 배치 처리 액션 추가
config/
└── route.php                 # 수정: 라우트 + 미들웨어 추가
```

---

## 1. 미들웨어

### 1.1 CORS 미들웨어

**파일**: `app/middleware/Cors.php`

- OPTIONS 프리플라이트 요청은 바로 204 반환
- 프리플라이트가 아닌 요청은 응답 헤더에 `Access-Control-Allow-Origin: *` 추가
- 허용 헤더: `Authorization, Content-Type, API-Version`
- 최대 캐시: 86400초

마운트: 전역 미들웨어 (`config/middleware.php`)

### 1.2 레이트 리밋 미들웨어

**파일**: `app/middleware/RateLimit.php`

- 저장소: Redis Sorted Set 슬라이딩 윈도우
- 기본값: 60회/분/IP/라우트
- 민감 인터페이스:
  - `/api/auth/login`: 10회/분
  - `/api/auth/register`: 5회/분
- 초과 시 `429 Too Many Requests` 반환

마운트: 전역 미들웨어 (`config/middleware.php`), Cors 이후, ApiVersion 이전

### 1.3 작업 로그 미들웨어

**파일**: `app/middleware/OperationLog.php`

- POST/PUT/DELETE만 기록
- 기록 필드: user_id, action, method, path, ip, input(JSON)
- 응답 반환 후 비동기로 기록 (블로킹 없음)

마운트: `/admin` 라우트 그룹, AdminPermission 이후

### 1.4 전역 미들웨어 실행 체인

```
모든 요청:
  Cors → RateLimit → ApiVersion → {Route 미들웨어} → Controller

/admin/* 요청:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 로그아웃 (JWT 블랙리스트)

**파일**: `app/middleware/AdminAuth.php` (수정)

**원리**: JWT 자체는 무상태이므로 로그아웃 시 token을 Redis 블랙리스트에 추가하고, AdminAuth 검증 시 먼저 블랙리스트를 확인합니다.

**AdminAuth 개조**:
- `process()` 시작 부분에 추가: Redis `jwt_blacklist` 집합에서 현재 token이 블랙리스트에 있는지 확인
- 블랙리스트 적중 시 401 반환

**로그아웃 라우트** (개인 센터 하위):

| 메서드 | 라우트 | 설명 |
|------|------|------|
| `POST` | `/admin/profile/logout` | 현재 Bearer token을 Redis 블랙리스트에 추가, TTL=token 남은 유효 기간 |

**Logout 로직**:
```php
// token 남은 유효 기간 파싱
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 블랙리스트에 추가
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. 신규 컨트롤러 및 기존 개조

### 2.1 시스템 설정 CRUD (`ConfigController`)

`BaseController` 상속.

| 메서드 | 라우트 | 설명 |
|------|------|------|
| `index()` | GET `/admin/config` | 페이지네이션 목록, `group` 필터 및 `page`/`limit` 페이지네이션 지원 |
| `store()` | POST `/admin/config` | 설정 항목 생성, 필수: group, key, value |
| `update()` | PUT `/admin/config/{id}` | 설정 항목 value/type/description 업데이트 |
| `destroy()` | DELETE `/admin/config/{id}` | 설정 항목 삭제, `confirmPassword()` 필요 |

### 2.2 작업 로그 조회 (`LogController`)

`BaseController` 상속.

| 메서드 | 라우트 | 설명 |
|------|------|------|
| `index()` | GET `/admin/log` | 페이지네이션 목록, 필터 지원: user_id, action, path, created_at(범위) |

증가/삭제/수정은 제공하지 않으며, 로그는 미들웨어가 자동으로 기록합니다.

### 2.3 개인 센터 (`ProfileController`)

`BaseController` 상속. 현재 로그인한 사용자(`$request->adminId`)를 대상으로 동작합니다.

| 메서드 | 라우트 | 설명 |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | real_name, phone, email 업데이트 |
| `updatePassword()` | PUT `/admin/profile/password` | 비밀번호 변경, old_password, new_password, new_password_confirmation 필요 |

### 2.4 파일 업로드 (`UploadController`)

`BaseController` 상속.

| 메서드 | 라우트 | 설명 |
|------|------|------|
| `upload()` | POST `/admin/upload` | 파일 수신, image/jpeg/png/gif/pdf/xlsx/docx 지원 |

- 최대 10MB
- 저장 경로: `public/upload/{date}/{hash}.{ext}`
- 반환: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 대시보드 실제 데이터

**파일**: `app/admin/controller/DashboardController.php` (수정)

현재 하드코딩된 가짜 데이터를 데이터베이스 실시간 통계로 변경:

| 지표 | 출처 | 설명 |
|------|------|------|
| 사용자 총수 | `AdminUser::count()` | 소프트 삭제 제외 |
| 오늘 신규 | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| 역할 총수 | `AdminRole::count()` | |
| 권한 총수 | `AdminPermission::count()` | |
| 추세 데이터 | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | 일별 최근 7일 신규 통계 |
| 분포 데이터 | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | 상태별 분포 |
| 최근 작업 | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | 최근 10건 작업 로그 |

### 2.6 사용자 배치 작업

**파일**: `app/admin/controller/UserController.php` (수정, 신규 메서드)

| 메서드 | 라우트 | 설명 |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | 일괄 삭제, 요청 본문 `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | 일괄 활성화/비활성화, 요청 본문 `{ ids: [hashid, ...], status: 1|0 }` |

- 각 id는 먼저 `decodeId()`로 BIGINT 변환
- `batchDestroy()`는 `confirmPassword()` 검증 통과 필요

### 2.7 데이터 가져오기

**파일**: `app/admin/controller/ImportController.php` (신규)

| 메서드 | 라우트 | 설명 |
|------|------|------|
| `users()` | POST `/admin/import/users` | Excel 파일 업로드, 사용자 일괄 생성 |

플로우:
1. `.xlsx` 파일 수신
2. PhpSpreadsheet 파싱, 예상 컬럼: `username, password, real_name, phone, email, status`
3. 행 단위 검증 + 생성 (snowflake로 ID 생성, bcrypt 비밀번호, encryption으로 phone/email 암호화)
4. 결과 반환: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 헬스 체크

**파일**: `app/admin/controller/HealthController.php` (신규)

`GET /health` (인증 불필요, 작업 로그 미기록):

각 컴포넌트 연결 상태 반환:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- 컴포넌트 감지 실패 시 해당 필드 값은 오류 설명 문자열
- 라우트는 `/admin` 접두사를 붙이지 않고 전역에 별도 등록

---

## 3. 모델 수정

### 3.1 OperationLog 타임스탬프

**파일**: `app/model/OperationLog.php` (수정)

테이블 `game_operation_log`에는 `created_at` 컬럼만 있습니다(`updated_at` 없음). Eloquent 기본 `save()`가 `updated_at`을 쓰려고 시도하여 SQL 오류가 발생합니다.

수정: `public $timestamps = false;` + 기록 시 `created_at`을 수동으로 지정.

### 3.2 AdminUser 모델 개조

- `Searchable` trait 추가
- `toSearchableArray()` 구현: username, real_name 반환
- `UserController::index()`에서 키워드 감지 시 MySQL LIKE 대신 `AdminUser::search($kw)->get()` 사용

ES는 먼저 인덱스를 생성해야 하며, Scout 명령으로 가능:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. 라우트 변경

`config/route.php` 신규 라우트:

```php
// /admin 라우트 그룹 내 신규:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// 헬스 체크 (전역 라우트, /admin 그룹 아님)
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// 미들웨어:
/admin 그룹 미들웨어에 app\middleware\OperationLog::class 추가
```

`config/middleware.php` 전역 미들웨어 등록:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. 오류 코드 보충

| code | 의미 | 트리거 시나리오 |
|------|------|---------|
| 429 | 요청이 너무 빈번함 | RateLimit 트리거 |

---

## 6. 이번 범위에 포함되지 않음

- 알림 시스템 (메시지 큐 + 프론트엔드 푸시 인프라 필요)
- Flutter 프론트엔드 페이지 (서브 프로젝트 B)
- HarmonyOS Token 리프레시 (서브 프로젝트 C)
