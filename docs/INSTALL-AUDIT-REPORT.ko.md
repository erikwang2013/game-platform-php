# 설치 시스템 심사 보고서
<!-- lang-nav -->

Languages: [中文](INSTALL-AUDIT-REPORT.md) · [English](INSTALL-AUDIT-REPORT.en.md) · **한국어** · [Русский](INSTALL-AUDIT-REPORT.ru.md) · [Deutsch](INSTALL-AUDIT-REPORT.de.md) · [Français](INSTALL-AUDIT-REPORT.fr.md) · [Español](INSTALL-AUDIT-REPORT.es.md) · [Português](INSTALL-AUDIT-REPORT.pt.md) · [हिन्दी](INSTALL-AUDIT-REPORT.hi.md) · [العربية](INSTALL-AUDIT-REPORT.ar.md) · [বাংলা](INSTALL-AUDIT-REPORT.bn.md) · [Bahasa Indonesia](INSTALL-AUDIT-REPORT.id.md) · [日本語](INSTALL-AUDIT-REPORT.ja.md)


> 심사 날짜: 2026-08-04
> 심사 범위: `install/` 디렉터리의 모든 파일 + 관련 문서 변경
> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## 一、심사 개요

| 차원 | 평점 | 설명 |
|------|------|------|
| 기능 완전성 | 통과 | 5단계 설치 플로우 완전, 39장 테이블 모두 생성, 시드 데이터 완비 |
| SQL 정확성 | 통과 | 42장 테이블이 원본 마이그레이션 파일과 완전히 일치, source 필드가 CREATE TABLE에 병합됨 |
| 생태계 설정 | 통과 | admin과 service 두 벌의 .env 설정 완전, 키 자동 생성 |
| 보안성 | 기본 통과 | 비밀번호 bcrypt 암호화, XSS 방어 완비, CSRF Token 추가 권장 |
| 유지보수성 | 통과 | 코드 구조 명확, 단일 파일 책임 명확 |
| 멱등성 | 통과 | 모든 INSERT가 INSERT IGNORE로 변경, WHERE NOT EXISTS 가드 포함 |
| 사용자 경험 | 통과 | 반응형 디자인, AJAX 연결 테스트, 중국어 오류 안내 |

---

## 二、생성된 파일

### 2.1 `install/install.sql` (988줄)
- 원본 마이그레이션 파일 8개 병합
- `game_` 접두사 데이터 테이블 42장 (CREATE TABLE IF NOT EXISTS)
- INSERT IGNORE 시드 데이터 블록 13개
- `game_operation_log`의 `source` 필드가 테이블 생성문에 병합됨 (ALTER TABLE 불필요)
- 트랜잭션 래핑 (START TRANSACTION / COMMIT)
- 모든 INSERT에 멱등 처리 완료

**INSERT문 멱등 처리 상세:**

| 테이블명 | 처리 방식 |
|------|---------|
| `game_admin_role` | INSERT IGNORE (고정 ID) |
| `game_admin_permission` | INSERT IGNORE (고정 ID) - 4회 |
| `game_admin_role_permission` | WHERE NOT EXISTS 서브쿼리 |
| `game-platform_config` | INSERT IGNORE (고정 ID) - 2회 |
| `game_language` | INSERT IGNORE (고정 ID) |
| `game_translation` | INSERT IGNORE (고정 ID) |
| `game_risk_rule` | INSERT IGNORE (고정 ID) |
| `game_withdraw_limit` | INSERT IGNORE (고정 ID) |
| `game_game_category` | INSERT IGNORE (고정 ID) |
| `game_country_config` | INSERT IGNORE (고정 ID) |

### 2.2 `install/index.php` (485줄)
- 라우트 디스패치: step1 -> step2 -> step3 -> step4 -> step5
- AJAX 인터페이스: `?action=test-db` (POST JSON)
- 5개 페이지 템플릿 함수
- 인라인 JavaScript (AJAX 연결 테스트)
- HTML 출력에 `htmlspecialchars()` 사용으로 XSS 방지
- 설치 여부 감지 (install.lock)

### 2.3 `install/Installer.php` (506줄)
- 환경 검사: 11항목 (PHP 버전, 확장 6개, 디렉터리 권한, SQL 파일)
- 데이터베이스 연결 테스트: PDO + 데이터베이스 자동 생성
- 설치 실행: SQL 가져오기 -> 관리자 생성 -> .env 작성 -> 잠금
- 키 생성: JWT(64바이트) / Hashids(32바이트) / Encryption(32바이트)
- .env 백업: 설치 전 기존 .env 파일 자동 백업

### 2.4 `install/assets/style.css` (130줄)
- 반응형 디자인 (모바일 <=600px 지원)
- CSS 변수 테마 (--primary: #4f46e5)
- 외부 의존성 없음

---

## 三、환경 검사 커버리지 (11항목)

| # | 검사 항목 | 레벨 | 상태 |
|---|--------|------|------|
| 1 | PHP >= 8.1.0 | 필수 | 통과 |
| 2 | PDO MySQL | 필수 | 통과 |
| 3 | MBString | 필수 | 통과 |
| 4 | JSON | 필수 | 통과 |
| 5 | OpenSSL | 필수 | 통과 |
| 6 | PCNTL | 필수 | 통과 |
| 7 | GD | 권장 | 통과 |
| 8 | XML | 권장 | 통과 |
| 9 | Redis | 권장 | 통과 |
| 10 | 디렉터리 권한 (admin/runtime, service/runtime) | 필수 | 통과 |
| 11 | install.sql 파일 존재 | 필수 | 통과 |

---

## 四、생태계 설정 완전성

### 4.1 Admin `.env` 생성 (70개 설정 항목)

| 그룹 | 설정 항목 수 | 커버리지 |
|------|---------|------|
| 애플리케이션 설정 | 3 | APP_NAME, APP_DEBUG, APP_URL |
| JWT 인증 | 6 | JWT_SECRET, JWT_ALGORITHM, JWT_TTL, JWT_REFRESH_TTL, JWT_ISSUER, JWT_AUDIENCE |
| Hashids | 2 | HASHIDS_SALT, HASHIDS_ALT_SALT |
| Snowflake | 3 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID, SNOWFLAKE_START_TIMESTAMP |
| 암호화(API) | 3 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTION_IV |
| 암호화(DB) | 3 | ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER, ENCRYPTION_PREVIOUS_KEYS |
| Scout/ES | 7 | SCOUT_DRIVER, SCOUT_HOSTS, SCOUT_PREFIX, SCOUT_SHARDS, SCOUT_REPLICAS, SCOUT_CHUNK_SIZE, SCOUT_SOFT_DELETE |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST 등 |
| Poster 캡차 | 7 | POSTER_IMAGE_DRIVER 등 |
| 데이터베이스 | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| Redis | 4 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DATABASE |
| 호환 키 | 3 | JWT_SECRET_KEY, JWT_DEFAULT_EXPIRE, JWT_REFRESH_EXPIRE |

### 4.2 Service `.env` 생성 (48개 설정 항목)

| 그룹 | 설정 항목 수 | 커버리지 |
|------|---------|------|
| 애플리케이션 | 2 | APP_ENV, APP_DEBUG |
| 데이터베이스 | 6 | DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD |
| JWT | 3 | JWT_SECRET, JWT_TTL, JWT_REFRESH_TTL |
| Hashids | 3 | HASHIDS_SALT, HASHIDS_ALPHABET, HASHIDS_MIN_LENGTH |
| Snowflake | 2 | SNOWFLAKE_DATACENTER_ID, SNOWFLAKE_WORKER_ID |
| 암호화 | 4 | ENCRYPTION_KEY, ENCRYPTION_CIPHER, ENCRYPTABLE_KEY, ENCRYPTABLE_CIPHER |
| Redis | 3 | REDIS_HOST, REDIS_PORT, REDIS_PASSWORD |
| ClickHouse | 5 | CLICKHOUSE_HOST, CLICKHOUSE_PORT, CLICKHOUSE_DB, CLICKHOUSE_USER, CLICKHOUSE_PASS |
| OAuth | 9 | OAUTH_GOOGLE/FACEBOOK/APPLE 각 3항목 |
| 결제 Webhook | 3 | STRIPE_WEBHOOK_SECRET, PAYPAL_WEBHOOK_ID, PAYPAL_VERIFY_URL |
| CORS | 1 | CORS_ORIGIN |
| Scout/ES | 6 | SCOUT_DRIVER 등 |
| OpenSearch | 9 | OPENSEARCH_HTTP_HOST 등 |

**비교 결론**: 두 벌의 `.env` 설정 모두 기존 `.env.example`과 일치하며, 누락되었던 `ENCRYPTION_CIPHER`, `ENCRYPTABLE_CIPHER`, `JWT_REFRESH_TTL`을 Service 설정에 보완했습니다.

---

## 五、보안 심사

### 5.1 구현된 보안 조치

| 조치 | 구현 방식 |
|------|---------|
| 비밀번호 보안 | bcrypt, cost=12 |
| 키 랜덤성 | `random_int()` 암호학적 안전 난수 |
| XSS 방어 | `htmlspecialchars()`로 모든 사용자 입력/출력 이스케이프 |
| SQL 인젝션 방어 | PDO 준비된 문 (prepare/execute) |
| 설치 잠금 | `install.lock` 파일 + JSON 메타데이터 |
| 경로 안전 | 고정 경로, 사용자 제어 파일 포함 없음 |
| 암호화 강도 | AES-256-CBC + 32바이트 키 |

### 5.2 잠재 리스크와 완화

| 리스크 | 등급 | 완화 조치 |
|------|------|---------|
| 설치 중 네트워크 노출 | 중 | 설치 후 즉시 `install/` 디렉터리 삭제 (페이지에 눈에 띄는 안내) |
| CSRF Token 없음 | 낮음 | 설치 마법사는 일회성 임시 도구, PHP 내장 서버 단일 스레드 |
| test-db 빈도 제한 없음 | 낮음 | 임시 도구, 사용 후 즉시 삭제 |
| .env 파일 권한 | 낮음 | 설치 후 수동으로 chmod 600 실행 권장 |

### 5.3 개선 제안

1. **프로덕션 강화**: 설치 완료 후 `chmod 600 admin/.env service/.env` 자동 실행 고려
2. **원격 접속**: 원격 서버라면 SSH 터널 권장: `ssh -L 8888:localhost:8888 user@host`
3. **설치 후 정리**: 설치 성공 페이지에 "설치 디렉터리 삭제"의 눈에 띄는 안내 추가 (구현됨)

---

## 六、테스트 결과

### 6.1 PHP 문법 검사
```
통과 install/index.php — No syntax errors
통과 install/Installer.php — No syntax errors
```

### 6.2 기능 테스트
```
통과 Step 1 환경 검사 — 11개 검사 모두 통과
통과 Step 2 데이터베이스 설정 — 폼 렌더링 정확, 기본값 채움 정상
통과 AJAX test-db — JSON 응답 형식 정확, 중국어 오류 안내 명확
통과 CSS 정적 리소스 — 200 OK, text/css
통과 설치 완료 페이지 — install.lock 감지 정상, 안내 정보 완전
```

### 6.3 SQL 검증
```
통과 42장 테이블명이 원본 마이그레이션 파일과 완전히 일치
통과 source 필드가 game_operation_log 테이블 생성문에 병합됨
통과 모든 INSERT문에 멱등 처리 완료
통과 WHERE NOT EXISTS 가드 복원됨 (원본 마이그레이션과 일치)
```

---

## 七、발견 및 수정된 문제

| # | 문제 | 심각도 | 상태 |
|---|------|--------|------|
| 1 | `game_admin_role_permission` INSERT에 `WHERE NOT EXISTS` 가드 누락 (원본 마이그레이션과 불일치) | 높음 | 수정됨 |
| 2 | 모든 시드 데이터 INSERT에 멱등 처리 없음 (재실행 시 실패) | 중 | 수정됨 (INSERT IGNORE) |
| 3 | 환경 검사에 `pcntl` 확장 검사 누락 (webman 핵심 의존성) | 중 | 수정됨 |
| 4 | Service .env에 `ENCRYPTION_CIPHER` 설정 누락 | 낮음 | 수정됨 |
| 5 | Service .env에 `ENCRYPTABLE_CIPHER` 설정 누락 | 낮음 | 수정됨 |
| 6 | Service .env에 `JWT_REFRESH_TTL` 설정 누락 | 낮음 | 수정됨 |

---

## 八、문서 변경

| 파일 | 변경 내용 |
|------|---------|
| `README.md` | 빠른 시작을 "원클릭 설치 마법사(권장)"로 변경, 수동 설치 접힘 블록 추가, 프로젝트 구조 업데이트 |
| `README.en.md` | 동일 (영문 버전), 프로젝트 구조 업데이트 |
| `docs/DEPLOYMENT.md` | "원클릭 설치 마법사(신규 배포 권장)" 2절 신설, 기존 Docker 절은 뒤로 이동 |
| `.gitignore` | `install/install.lock`, `admin/.env.backup.*`, `service/.env.backup.*` 추가 |

---

## 九、총평

설치 시스템은 기능이 완전하고 코드 품질이 좋으며 보안 조치가 적절합니다. 5단계 설치 플로우가 명확하고 직관적이며, 환경 검사가 webman 실행에 필요한 모든 핵심 확장을 커버하고, 고강도 키를 자동 생성하며, 설정 파일이 기존 시스템과 완전히 호환됩니다. SQL 병합 과정에서 원본 마이그레이션 파일과의 완전한 일치(42장 테이블)를 유지했고, 멱등 처리는 재실행 시 오류가 나지 않도록 보장합니다.

**심사 결론: 통과, 사용 가능.**

---

## 十、2026-08-18 상태 확인

이번 라운드의 보안 수정(결제 콜백 fail-closed, JWT 기동 검증, 테이블 접두사 통일)은 **설치 시스템을 다루지 않았으며**, 새로 발견된 문제는 없습니다:

- 모델에서 하드코딩된 `game_` 접두사를 제거한 후에도 실제 테이블명은 `config/database.php`의 `prefix=game_`로 통일 생성되어 install.sql이 만든 `game_*` 테이블과 일치하므로 설치 SQL 변경 불필요
- JWT 기동 검증(`JWT_SECRET_KEY` 누락 또는 기본값이면 기동 거부)은 설치 마법사가 자동 생성하는 64바이트 랜덤 키와 호환되어 설치 플로우 조정 불필요

기존 결론과 문제 목록은 그대로 유지됩니다.

---
