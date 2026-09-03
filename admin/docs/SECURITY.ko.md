# 安全架构设计文档
<!-- lang-nav -->

Languages: [中文](SECURITY.md) · [English](SECURITY.en.md) · **한국어** · [Русский](SECURITY.ru.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Español](SECURITY.es.md) · [Português](SECURITY.pt.md) · [हिन्दी](SECURITY.hi.md) · [العربية](SECURITY.ar.md) · [বাংলা](SECURITY.bn.md) · [Bahasa Indonesia](SECURITY.id.md) · [日本語](SECURITY.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. 심층 방어 전체 보기

시스템은 7계층 심층 방어 모델을 채택하여, 외부에서 내부로 악성 요청을 단계별로 필터링하며, 어느 한 계층이 무너져도 후속 방어선이 받쳐주도록 보장합니다.

전체 미들웨어 체인은 다음 순서로 실행됩니다 (`config/middleware.php` 참조):

```
请求 → Cors → SecurityFilter → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| 계층 | 미들웨어/메커니즘 | 방어 대상 |
|----|--------|---------|
| 1 | SecurityFilter | XSS / SQL 인젝션 / 경로 탐색 / 명령 인젝션 / CSRF 공격 차단 |
| 2 | Cors | 크로스 도메인 보안 + 응답 보안 헤더 주입 |
| 3 | RateLimit | Redis 슬라이딩 윈도우 속도 제한, 무차별 대입 방지 |
| 4 | AdminAuth | JWT 인증 + 블랙리스트 로그아웃 |
| 5 | AdminPermission | RBAC method.path 단위 인가 |
| 6 | OperationLog | 작업 감사 + 출처 추적 |
| 7 | 데이터 암호화 | Hashids ID 난독화 + Encryptable DB 암호화 + EncryptionService 전송 암호화 |

프론트엔드 3계층 (Flutter)은 별도의 입력 검증이 있으며, 백엔드는 신뢰하지 않고 각 계층이 독립적으로 방어합니다.

---

## 2. 공격 감지 엔진

### 2.0 HTTP 메서드 제한

SecurityFilter는 모든 공격 감지 이전에 HTTP 메서드를 먼저 검증하며, 다음 표준 메서드만 허용합니다:

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

비표준 메서드 (TRACE, CONNECT, PATCH, 사용자 정의 메서드 등)는 바로 **405 Method Not Allowed**를 반환하며, 응답 본문은 빈 HTML이고 후속 공격 감지나 비즈니스 로직으로 진행되지 않습니다.

이것은 심층 방어의 첫 번째 방어선으로, 다음을 효과적으로 차단합니다:
- TRACE 크로스 사이트 추적 공격 (XST)
- CONNECT 터널 프록시 남용
- 비표준 WebDAV 메서드 프로빙
- 자동화 스캐너의 HTTP 메서드 열거

### 2.1 XSS 크로스 사이트 스크립트

모든 정규식은 `SecurityFilter::PATTERNS['XSS']`에서 가져오며, 대소문자 구분 없이 매칭합니다.

| 감지 패턴 | 정규식 | 방어하는 공격 |
|----------|------|-----------|
| 스크립트 태그 | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` 등 공백 포함 변형 |
| 이벤트 속성 | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | `onclick="javascript:..."` 등 인라인 이벤트 |
| JS 유사 프로토콜 | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` 등 |
| Data URI XSS | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` 등 |
| 템플릿 인젝션 | `\{\{.*?\}\}` | `{{constructor}}`, `{{7*7}}` 등 서버/Angular/Vue 템플릿 인젝션 |

### 2.2 SQL 인젝션

| 감지 패턴 | 정규식 | 방어하는 공격 |
|----------|------|-----------|
| UNION 결합 조회 | `\bUNION\s+(?:ALL\s+)?SELECT\b` | `UNION SELECT`, `UNION ALL SELECT` 데이터 유출 |
| OR 항진 인젝션 | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| 테이블 구조 파괴 | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| 저장 프로시저 호출 | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | MSSQL 확장 저장 프로시저 명령 실행 |
| 메타데이터 프로빙 | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | MySQL/PG/SQLite/MSSQL 데이터베이스 구조 프로빙 |
| 주석 우회 | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | `'-- OR SELECT`, `'# AND UPDATE` 주석 우회 |

### 2.3 경로 탐색

| 감지 패턴 | 정규식 | 방어하는 공격 |
|----------|------|-----------|
| 디렉터리 역추적 | `\.\.[\/\\\\]{2,}` | `../`, `..\`, `....//` 다단계 디렉터리 역추적 |
| 민감 파일 프로빙 | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` 등 |
| 널 바이트 잘림 | `%00` | `../../../etc/passwd%00.jpg` 확장자 검증 우회 |

### 2.4 명령 인젝션

| 감지 패턴 | 정규식 | 방어하는 공격 |
|----------|------|-----------|
| 파이프/세미콜론 명령 | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| 백틱 치환 | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| $() 치환 | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| 원격 다운로드 파이프 | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF 크로스 사이트 요청 위조

검증 로직은 `SecurityFilter::checkCsrf()`에 구현되어 있습니다:

```php
// POST/PUT/DELETE에서만 검증 트리거
// Origin 헤더와 Referer 모두 비어 있음 → 통과 (비브라우저 클라이언트)
// Origin이 비어 있지 않음 → Origin 도메인을 파싱하여 Host와 비교
```

비교 규칙:
- Host의 `www.` 접두사를 제거한 뒤 Origin의 도메인과 정확히 비교
- Host가 Origin의 부모 도메인이면 (예: `Origin: app.example.com`, `Host: example.com` — `str_contains($originHost, '.' . $hostOnly)` 트리거), 통과
- 정확히 일치하지도 않고 하위 도메인도 아니면 → 403 반환, CSRF 공격으로 판정

주의: 비브라우저 클라이언트 (Origin/Referer가 없는 curl 등)는 바로 통과하며, CSRF 보호는 브라우저 환경에서만 유효합니다.

### 2.6 악성 파일 업로드

| 감지 패턴 | 정규식 | 방어하는 공격 |
|----------|------|-----------|
| 이중 확장자 위장 | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` 화이트리스트 우회 |
| PHP 확장자 | `\.php\s*$/m` | 요청 파라미터로 `.php` 경로 직접 전달 |

---

## 3. 공격 단계 상승과 IP 블랙리스트

SecurityFilter는 공격 단계 상승 메커니즘을 내장하여, 동일 IP의 지속적 스캔 공격을 방지합니다.

### 상승 흐름

```
第 1 次扫描命中 → Redis INCR security_escalate:{ip} = 1, TTL=60s
第 2 次扫描命中 → INCR → 2
...
第 5 次扫描命中 → INCR → 5
    → 触发封禁: SETEX security_ban:{ip} 900 1
    → 清除计数器 DEL security_escalate:{ip}
    → 写入安全日志: [SECURITY] IP banned 15min
```

### 차단 기간 중 동작

모든 요청은 SecurityFilter 진입 시 먼저 `isBanned()`를 확인합니다:

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

차단된 IP는 15분 동안 모든 요청(합법적 요청 포함)이 바로 403을 반환하며, 후속 비즈니스 로직을 완전히 건너뜁니다.

### 설정 상수

| 상수 | 값 | 의미 |
|------|-----|------|
| ESCALATE_LIMIT | 5 | 60초 윈도우 내 트리거 횟수 임계값 |
| ESCALATE_WINDOW | 60 | 카운터 윈도우 (초) |
| BAN_DURATION | 900 | 블랙리스트 지속 시간 (초), 즉 15분 |

### 보안 로그

파일 위치: `runtime/logs/security.log`

로그 형식 예시:
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/v1/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### 요청 본문 크기 제한

`Content-Length > 10MB`는 바로 413 Payload Too Large를 반환하여, DoS 대용량 요청 본문 공격을 방지합니다.

### Content-Type 검증

POST/PUT 요청은 **반드시** `Content-Type`을 `application/json` 또는 `application/x-www-form-urlencoded`로 선언해야 하며, 그렇지 않으면 415 Unsupported Media Type을 반환합니다. 파일 업로드 요청 (file 필드 포함)은 이 검사를 건너뜁니다.

---

## 4. 응답 보안 헤더

모든 헤더는 `Cors` 미들웨어에서 주입되며, `$response->withHeaders()`로 각 응답에 추가됩니다.

| 헤더 | 값 | 역할 |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | 모든 출처의 크로스 도메인 허용 (내부망 관리 백엔드 시나리오) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | 허용되는 메서드 집합 |
| Access-Control-Allow-Headers | `Authorization,Content-Type` | 허용되는 사용자 정의 헤더 |
| Access-Control-Max-Age | `86400` | 사전 요청 캐시 24시간 |
| X-Content-Type-Options | `nosniff` | 브라우저 MIME 스니핑 금지 |
| X-Frame-Options | `DENY` | 모든 iframe 삽입 금지, 클릭재킹 방지 |
| X-XSS-Protection | `1; mode=block` | 브라우저 내장 XSS 필터 활성화 및 페이지 렌더링 차단 |
| Referrer-Policy | `strict-origin-when-cross-origin` | 동일 출처는 전체 URL, 크로스 도메인은 도메인만 전송 |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | 사이트 전체에서 카메라/마이크/위치 API 비활성화 |

OPTIONS 사전 요청은 바로 204 빈 응답을 반환하며, 후속 미들웨어 체인으로 진행하지 않습니다.

### 4.2 Content-Security-Policy (CSP)

다른 보안 헤더와 함께 Cors 미들웨어에서 주입되며, 심층 방어를 제공하여 브라우저가 로드하고 실행할 수 있는 리소스 출처를 제한합니다.

| 헤더 | 값 | 역할 |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | 스크립트/스타일/이미지/연결/프레임/폼 등 리소스 출처 제한 |
| X-Permitted-Cross-Domain-Policies | `none` | Adobe Flash/PDF 등의 크로스 도메인 정책 파일 로드 금지 |

CSP 정책 요점:
- `default-src 'self'`: 기본적으로 동일 출처 리소스만 허용
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: 동일 출처 스크립트 + 인라인 스크립트 (Flutter Web 필수) + eval (Flutter Web 디버그 필수) 허용
- `frame-ancestors 'none'`: 어떤 페이지에서도 iframe 삽입 금지, X-Frame-Options: DENY와 이중 안전장치
- `base-uri 'self'`: `<base>` 태그가 동일 출처만 가리키도록 제한
- `form-action 'self'`: 폼이 동일 출처로만 제출되도록 제한

---

## 5. 속도 제한 정책

### 알고리즘

Redis Sorted Set 슬라이딩 윈도우 + Lua 원자화 스크립트, 핵심 작업:

```lua
-- 1. 清理窗口外的旧记录
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. 检查当前窗口计数
local count = redis.call('ZCARD', KEYS[1])
-- 3. 超限则返回 {0, count}，未超限则 ZADD 并返回 {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- 随机后缀避免同毫秒覆盖
redis.call('EXPIRE', KEYS[1], window + 10)
```

Lua 스크립트는 Redis 서버에서 단일 스레드로 실행되므로 **본질적으로 원자적**이며, TOCTOU (Time-of-check to Time-of-use) 경쟁 조건을 제거합니다.

### 속도 제한 설정

| 라우트 | 제한 | 윈도우 | 시나리오 |
|------|------|------|------|
| 기본 (모든 라우트) | 60회/분 | 60s | 일반 API |
| `/api/v1/auth/login` | 10회/분 | 60s | 로그인 (무차별 대입 방지) |
| `/api/v1/auth/register` | 5회/분 | 60s | 가입 (대량 가입 방지) |

### 응답 헤더

속도 제한 트리거 시 HTTP 429 및 JSON 본문 반환:
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

모든 응답 (정상 응답 포함)은 다음 헤더를 포함합니다:

| 헤더 | 설명 |
|----|------|
| X-RateLimit-Limit | 현재 윈도우에서 허용되는 최대 요청 수 |
| X-RateLimit-Remaining | 현재 윈도우에서 남은 사용 가능 요청 수 |
| X-RateLimit-Reset | 윈도우 리셋 Unix 타임스탬프 |
| Retry-After | 속도 제한 시에만 포함, 권장 대기 초수 |

### 다운그레이드 정책

Redis 이상 (연결 시간 초과, 사용 불가 등) 시 **fail-closed**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    // Redis down: 限流不可用即拒绝，避免安全防线失效（登录/支付回调限流为空转）
    return json(['code' => 503, 'message' => '服务暂不可用，请稍后再试', 'data' => []])
        ->withStatus(503)->withHeaders(['Retry-After' => '5']);
}
```

속도 제한은 로그인 무차별 대입 방지, 결제 콜백 리플레이 방지의 첫 번째 보안 방어선으로, Redis 장애 시 요청을 거부(503)하는 것이 통과시키는 것보다 낫습니다.

### 5.4 계정 잠금 메커니즘

로그인 인터페이스는 속도 제한에 더해 **계정 잠금** 메커니즘을 추가로 두어, 특정 사용자를 겨냥한 무차별 대입을 방지합니다.

**잠금 흐름**:

```
로그인 실패 → Redis INCR account_lockout:{userId} TTL=900s
연속 5회 실패 → Redis SETEX account_locked:{userId} 900 1
            → 429 "账号已被锁定，请15分钟后再试" 반환
            → 카운터 삭제 DEL account_lockout:{userId}
```

**잠금 중 동작**:

잠금 기간 중 모든 로그인 요청은 바로 429를 반환하며, 비밀번호 검증을 하지 않아 무차별 대입 시도를 완전히 차단합니다.

**설정 상수**:

| 상수 | 값 | 의미 |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | 최대 연속 실패 횟수 |
| LOCKOUT_DURATION | 900 | 잠금 지속 시간 (초), 즉 15분 |

주의: 계정 잠금은 `userId` 기반이므로 공격자가 IP를 바꿔도 잠금을 우회할 수 없습니다. IP 속도 제한 (10회/분)과 겹쳐 이중 방어를 형성합니다:
- IP 계층: 10회/분 속도 제한으로 분산 무차별 대입 차단
- 계정 계층: 5회 실패 잠금으로 특정 대상 무차별 대입 차단

---

## 6. 인증과 인가

### 6.1 JWT 인증

AdminAuth 미들웨어가 구현하며, 인증이 필요한 라우트 그룹에 마운트됩니다.

**파라미터 설정** (`config/plugin/erikwang2013/jwt/jwt`, `.env`에서 주입):

| 파라미터 | 값 | 설명 |
|------|-----|------|
| 알고리즘 | HS256 | HMAC-SHA256 대칭 서명 |
| 키 | `JWT_SECRET_KEY` | 환경 변수 주입, 누락되거나 기본값이면 **시작 거부** (fail-closed) |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_TTL` |
| 발급자 | `open-admin` | `JWT_ISSUER` |
| 대상 | `open-admin` | `JWT_AUDIENCE` |

**Token 추출**: `Authorization: Bearer <token>` 헤더에서 추출, `Bearer ` 접두사를 제거하면 원시 JWT를 얻습니다.

**인증 흐름**:
1. 빈 토큰 → 바로 401 `{"code": 401, "message": "未登录"}`
2. Redis 블랙리스트 `jwt_blacklist:{md5(token)}` 확인 → 적중 → 401 `Token已失效，请重新登录`
3. JWT decode → 실패 (만료/서명 불일치) → 401 `Token已过期或无效`
4. 성공 → `$request->adminId` 및 `$request->adminUsername` 주입

**블랙리스트 메커니즘**: 사용자 로그아웃 시 `md5(token)`을 Redis에 기록하고, TTL을 JWT 남은 유효기간으로 설정합니다. Redis 장애 시 블랙리스트 확인은 건너뜁니다 (fail-open). 이때 이미 로그아웃한 토큰도 단기간 사용될 수 있지만, JWT 자체의 짧은 유효기간 (2h)이 마지막 보호 장치 역할을 합니다.

**Token 갱신**: `POST /api/v1/auth/refresh`는 기존 refresh token (`token_type=refresh`이고 만료/블랙리스트가 아닌 경우)을 검증한 후에만 교체 발급하며, `sub`가 유효한 사용자 ID인지도 검증합니다 — **sub=null인 refresh token은 더 이상 발급하지 않으며**, 갱신 실패 시 바로 401을 반환합니다.

### 6.2 동시 세션 제한

Token 유출 후 여러 기기에서 남용되는 것을 방지하기 위해, 시스템은 동일 사용자가 동시에 보유한 유효 토큰 수를 제한합니다.

**제한 로직**:

```
登录成功 → 签发新 Token
         → 查询当前用户有效 Token 数量: Redis SCARD user_tokens:{userId}
         → 若数量 >= 3（MAX_CONCURRENT_SESSIONS）:
            → 按创建时间升序排列，移除最旧的 Token:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → 将新 Token 加入集合: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**설정 상수**:

| 상수 | 값 | 의미 |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | 동일 사용자 최대 동시 토큰 수 |

**강제 로그아웃 시나리오**: 사용자가 4번째 기기에서 로그인하면 1번째 기기의 토큰이 강제로 블랙리스트에 추가되고, 이후 요청은 401 "Token已失效，请重新登录"을 반환합니다.

로그아웃 시 현재 토큰이 집합에서 제거됩니다. 토큰이 자연 만료되면 Redis 키가 자동으로 사라지고 집합 구성원도 줄어듭니다.

### 6.3 RBAC 권한 모델

AdminPermission 미들웨어가 구현합니다.

**데이터 모델**: User -> Role -> Permission 3계층 연관

- `game_admin_user` (사용자 테이블)
- `game_admin_user_role` (사용자-역할 연관 테이블)
- `game_admin_role` (역할 테이블)
- `game_admin_role_permission` (역할-권한 연관 테이블)
- `game_admin_permission` (권한 테이블)

**권한 유형**:
| type | 의미 | 예시 |
|------|------|------|
| 1 | 메뉴 권한 | 왼쪽 내비게이션 표시 여부 제어 |
| 2 | 버튼 권한 | 페이지 내 작업 버튼 제어 (생성/수정/삭제) |
| 3 | API 권한 | 백엔드 인터페이스 호출 제어 |

API 권한 식별자 형식: `{method}.{path}`

예를 들어:
- `post.admin/user` — 사용자 생성
- `put.admin/user` — 사용자 수정
- `delete.admin/user` — 사용자 삭제
- `get.admin/user` — 사용자 목록 조회

**인가 흐름**:
1. `$request->adminId`가 비어 있음 (미로그인) → 바로 401 `{"code": 401, "message": "未登录"}`, 더 이상 통과시키지 않음
2. 사용자 → 역할 ( `status=0` 비활성 역할 건너뜀) → 권한 목록 조회
3. 슈퍼 관리자 (`slug = '*'`) → 바로 통과
4. `strtolower(method) . '.' . trim(path, '/')` 구성 → 권한 목록과 비교
5. 매칭 실패 → 403 `{"code": 403, "message": "无权限访问"}`

**2차 확인**: BaseController가 `confirmPassword()` 메서드를 제공하며, 민감 작업 (사용자 삭제, 데이터 내보내기 등)은 Controller 계층에서 현재 비밀번호 입력을 추가로 요구하여 세션 하이재킹 후 무단 작업을 방지합니다.

### 6.4 결제 콜백 서명 검증 (fail-closed)

`POST /api/v1/payment/callback` (Stripe/PayPal 충전 콜백) 서명 검증은 **fail-closed** 방식으로, 어떤 설정 누락이나 검증 이상도 콜백을 거부합니다:

| 시나리오 | 동작 |
|------|------|
| Stripe에 `STRIPE_WEBHOOK_SECRET` 미설정 | 거부 (403), 무서명 콜백 더 이상 수락 안 함 |
| Stripe 서명 누락 / 서명 검증 실패 | 거부 (403) |
| Stripe 타임스탬프 `t=` 누락 또는 서버 시간과 **> ±5분** 차이 | 거부 (403), 리플레이 방지 |
| PayPal에 `PAYPAL_WEBHOOK_ID` 미설정 | 거부 (403) |
| PayPal 역조회 검증 이상 / SUCCESS 아님 | 거부 (403) |
| 선택적 `CALLBACK_TRUSTED_IPS` 설정 후 출처 IP가 화이트리스트에 없음 | 거부 (403) |
| 콜백 provider와 주문 결제 수단 불일치 / 결제 수단 없음 | 거부 (403) |

콜백 입금 (상태 업데이트 + 잔액 + 거래 내역)은 동일 데이터베이스 트랜잭션 내에서 완료되며, 어느 한 단계라도 실패하면 전체가 롤백되어 반쪽 입금을 방지합니다.

---

## 7. 감사 로그

### 7.1 작업 로그

OperationLog 미들웨어는 POST / PUT / DELETE 요청에 대해 작업 로그를 자동으로 기록합니다. GET 요청은 기록하지 않습니다.

**기록 필드**:

| 필드 | 출처 | 설명 |
|------|------|------|
| id | SnowflakeService::generate() | 전역 고유 ID |
| user_id | `$request->adminId` | 작업자 ID, 미로그인 시 0 |
| action | `$request->method()` | method와 동일 |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | 요청 경로 |
| ip | `$request->getRealIp()` | 클라이언트 실제 IP |
| source | detectSource() | 클라이언트 출처 플랫폼 |
| input | 요청 body (마스킹 후 JSON) | 작업 제출 데이터 |
| created_at | `date('Y-m-d H:i:s')` | 작업 시간 |

**민감 필드 필터**: 요청 본문을 재귀적으로 순회하며, 다음 필드의 값은 `***`로 대체:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**출처 감지** (`detectSource()`): 우선순위 순:

1. `X-Client-Platform` 사용자 정의 헤더 먼저 읽기 (네이티브 클라이언트가 명시적으로 선언)
2. 없으면 User-Agent 문자열로 추론 (`detectSource()` 메서드 감지 순서):

| 플랫폼 | UA 키워드 |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | 기본값 (fallback) |

**내결함성**: 로그 쓰기 예외는 비즈니스 요청을 차단하지 않습니다 (`catch (\Throwable)`로 조용히 삼킴).

### 7.2 보안 로그

**파일 위치**: `runtime/logs/security.log`

**기록 내용**:
- 공격 차단 로그: 공격 유형, IP, 경로, 필드, 출처, payload 일부 (앞 200자)
- IP 차단 알림: 차단된 IP, 트리거 횟수

로그 권한은 `FILE_APPEND | LOCK_EX`로, 동시 쓰기 안전을 보장합니다.

---

## 8. 데이터 보호

시스템은 데이터 흐름의 세 단계에 대응하는 3계층 데이터 보호 전략을 채택합니다.

### 8.1 전송 계층 — EncryptionService

`EncryptionService`는 `erikwang2013/encryption` 패키지를 사용하여 API 요청/응답의 민감 필드를 암복호화합니다.

**기술 상세**:
- 알고리즘: `aes-256-cbc-hmac` (HMAC 서명 내장으로 변조 방지)
- 키: `ENCRYPTION_KEY` 환경 변수, 자동으로 32바이트 정렬
- 용도: 클라이언트와 API 간 휴대폰 번호, 주민등록번호 등 필드 전송

**마스킹 유틸리티 메서드**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (사용자 이름이 2자 초과) 또는 `a**@example.com`

### 8.2 저장 계층 — Encryptable Cast

`AdminUser` 모델은 `Erikwang2013\Encryptable\Encryptable` Eloquent cast를 사용하며, 해당 필드:

- `email` → Encryptable로 cast, 자동 암복호화
- `phone` → Encryptable로 cast, 자동 암복호화
- `id_card` → Encryptable로 cast, 자동 암복호화

데이터베이스에 쓸 때 자동으로 암호문으로 암호화되고, 읽을 때 자동으로 평문으로 복호화됩니다. 데이터베이스 저장 컬럼 타입은 `VARCHAR(500)`이며, 암호문은 base64 형식으로 저장됩니다.

**키 체계**: 전송 계층 암호화 (`ENCRYPTION_KEY`)와 독립적으로 `ENCRYPTABLE_KEY`를 사용하므로, 한 키가 유출되어도 다른 계층이 무너지지 않습니다.

키 로테이션: `ENCRYPTION_PREVIOUS_KEYS` 환경 변수가 이전 키 목록 (쉼표 구분)을 지원하며, 이전 데이터를 읽을 때 이전 키로 복호화를 시도하고, 다시 쓸 때는 현재 키로 재암호화합니다.

### 8.3 표시 계층 — ID 난독화와 마스킹

**Hashids ID 난독화**: `HashidsService`는 `erikwang2013/hashids` 패키지를 사용합니다.

- 외부 API가 반환하는 데이터베이스 BIGINT ID를 hash 문자열로 인코딩 (예: `xK3mN9qR2pL7wV8b`)
- 클라이언트가 hash 문자열을 전달하면, 백엔드가 자동으로 원래 ID로 디코딩
- 솔트는 `HASHIDS_SALT` 환경 변수로 주입, 솔트가 다르면 인코딩/디코딩 결과가 완전히 다름
- hash 최소 길이 16자, 62개 영숫자 문자 집합 사용
- BaseController가 `encodeId()`, `decodeId()`, `encodeIds()` 편의 메서드 제공

**내보내기 마스킹**: Excel/PDF 내보내기 시 (ExportController), 민감 필드를 일괄 마스킹:
- 휴대폰 번호: `138****1234`
- 이메일: `a***@example.com`
- 주민등록번호: `********`로 완전 가림

---

## 9. 키 관리

모든 키는 `.env` 환경 변수로 주입되며, 설정 파일은 `getenv()`로 읽고 기본값 (개발 환경에서만 안전)을 내장합니다.

| 환경 변수 | 용도 | 패키지 | 프로덕션 요구사항 |
|----------|------|-----|---------|
| JWT_SECRET_KEY | JWT 서명 키 | erikwang2013/jwt-webman | 64+자 무작위 문자열; 누락 또는 기본값이면 시작 거부 |
| JWT_ALGORITHM | JWT 서명 알고리즘 | 동일 | HS256 유지 |
| HASHIDS_SALT | ID 인코딩 솔트 | erikwang2013/hashids | 무작위 문자열 |
| SNOWFLAKE_DATACENTER_ID | 데이터센터 ID (0-31) | erikwang2013/snowflake-php | 단일 서버실에서는 기본값 유지 |
| ENCRYPTION_KEY | API 전송 계층 암호화 키 | erikwang2013/encryption | 32바이트 무작위 문자열 |
| ENCRYPTABLE_KEY | DB 저장 계층 암호화 키 | erikwang2013/encryptable | 32바이트 무작위 문자열, 전송 키와 다름 |

**보안 요구사항**:
- `.env` 파일은 `.gitignore`에 추가되어 있으며, 버전 관리에 절대 커밋 금지
- `.env.example`은 공개 템플릿 파일이며, 실제 키를 포함하지 않음
- 프로덕션에서는 **반드시** 모든 기본 키를 무작위 문자열로 교체
- `openssl rand -base64 32`로 키 생성 권장

### 키 저장 격리

| 계층 | 설정 키 | 키 환경 변수 |
|----|--------|-------------|
| 전송 암호화 | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| 저장 암호화 | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| ID 난독화 | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| JWT 서명 | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET_KEY` |

---

## 10. security.txt (RFC 9116)

시스템은 `/.well-known/security.txt`에 RFC 9116 표준에 부합하는 보안 연락 정보 엔드포인트를 제공하여, 보안 연구자가 취약점 발견 시 신고 채널을 빠르게 찾을 수 있게 합니다.

**접근 방식**:

```
GET /.well-known/security.txt
```

**응답 내용**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**필드 설명**:

| 필드 | 설명 |
|------|------|
| Contact | 보안 취약점 신고 연락처 |
| Expires | 파일 만료 시간, 정기 업데이트 필요 |
| Preferred-Languages | 선호 소통 언어 |
| Canonical | 이 파일의 표준 URL |
| Policy | 보안 정책/취약점 공개 정책 링크 |

이 엔드포인트는 속도 제한, 인증 등의 미들웨어 제한을 받지 않으며, 누구나 직접 접근할 수 있습니다.

---

## 11. Nginx 보안 설정

프로젝트는 `docs/nginx-security.conf`를 프로덕션 환경 Nginx 리버스 프록시의 보안 강화 참조 설정으로 제공합니다.

**포함된 보안 조치**:

| 설정 항목 | 역할 |
|--------|------|
| `server_tokens off` | Nginx 버전 번호 숨기기 |
| `client_max_body_size 10m` | 요청 본문 크기 제한, SecurityFilter와 협력 |
| `limit_req_zone` | Nginx 계층의 요청 빈도 제한 |
| `limit_conn_zone` | 동시 연결 수 제한 |
| `add_header` 보안 헤더 | Nginx 계층에서 X-XSS-Protection 등 보안 헤더 추가 |
| `if ($request_method)` | Nginx 계층에서 비표준 HTTP 메서드 거부 |
| SSL/TLS 설정 | 최신 TLS 1.2/1.3 설정, 취약 암호화 제품군 비활성화 |
| 백엔드 헤더 숨기기 | `proxy_hide_header`로 webman 버전 등 민감 헤더 제거 |

**사용 방법**: `docs/nginx-security.conf`의 설정을 Nginx server 블록에 병합하고, 실제 도메인과 인증서 경로에 맞게 조정하세요.

---

## 12. 위협 모델

### 12.1 방어된 위협

| 위협 유형 | 공격 벡터 | 방어 계층 |
|----------|---------|---------|
| HTTP 메서드 남용 | TRACE/TRACK XST 공격, CONNECT 터널 프록시, WebDAV 메서드 프로빙 | SecurityFilter 405 메서드 화이트리스트 (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| 특정 대상 무차별 대입 | 특정 사용자를 겨냥한 반복 비밀번호 시도 | 계정 잠금 (5회 실패 시 15분 잠금) + RateLimit (로그인 10/min) + Captcha |
| 무차별 대입 | 분산 IP로 아이디/비밀번호 반복 시도 | RateLimit (로그인 10/min) + Captcha |
| XSS 크로스 사이트 스크립트 | `<script>`, onerror, javascript: | SecurityFilter (5가지 패턴) + X-XSS-Protection 응답 헤더 + CSP |
| SQL 인젝션 | UNION SELECT, OR 1=1, 주석 우회 | SecurityFilter (6가지 패턴) + Eloquent ORM 파라미터화 쿼리 |
| CSRF 크로스 사이트 요청 위조 | 악성 사이트가 대신 요청 발송 | SecurityFilter Origin/Referer 검증 |
| 경로 탐색 | `../../etc/passwd` | SecurityFilter 경로 탐색 패턴 + UploadController 확장자 화이트리스트 |
| 명령 인젝션 | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4가지 패턴) |
| 세션 하이재킹 | JWT Token 탈취 | JWT 단기 유효 (2h) + 블랙리스트 로그아웃 + 민감 작업 2차 비밀번호 확인 |
| ID 열거 | 숫자 ID 순회로 데이터 규모 추측 | Hashids로 무작위 문자열 난독화 |
| 데이터 유출 | DB 유출 / 중간자 / 로그 유출 | 3계층 암호화/마스킹 + OperationLog 민감 필드 필터 |
| DoS 공격 | 대용량 요청 본문 / 고빈도 요청 | 요청 본문 10MB 제한 + RateLimit 60/min + IP 블랙리스트 |
| 권한 상승 | 낮은 권한 사용자가 관리 인터페이스 접근 | RBAC method.path 단위 인가 |
| 파일 업로드 공격 | shell.php.png 이중 확장자 | SecurityFilter 악성 파일 감지 |

### 12.2 알려진 한계

| 한계 | 영향 범위 | 완화 조치 |
|------|---------|---------|
| CSRF 보호는 브라우저에서만 유효 | 비브라우저 클라이언트 (curl, Postman, 모바일 앱)는 Origin/Referer 검사를 건너뛸 수 있음 | 비브라우저 클라이언트는 본질적으로 CSRF 공격에 안전; Cookie 대신 JWT 인증에 의존 |
| Redis 사용 불가 시 속도 제한 fail-closed (503), 블랙리스트 확인 fail-open | 속도 제한 기간 일부 요청 거부; 로그아웃한 토큰 단기 사용 가능 | Redis 가용성 모니터링 알람; JWT 단기 유효기간이 마지막 방어선 |
| 독립 WAF 엔진 없음 | SecurityFilter는 `@preg_match` 정규식 매칭, 전용 WAF 규칙 엔진 아님 | 프로덕션에서는 Nginx ModSecurity 또는 Cloudflare WAF 앞단 배치 권장 |
| JWT 무상태로 능동 무효화 불가 | 토큰 만료 전에는 서버에서 능동적으로 폐기 불가 (블랙리스트 제외) | 블랙리스트 + 단기 2h TTL로 위험 창 줄임 |
| IP 블랙리스트는 메모리 저장뿐 | Redis 재시작 시 블랙리스트 유실 | 차단 시간이 15분뿐이라 영향 제한적 |
| 관리자 엔드포인트 특별 속도 제한 없음 | 관리자 인터페이스가 일반 인터페이스와 60/min 기본 제한 공유 | 관리자 작업 빈도는 본질적으로 낮아 구분 불필요 |
| `@preg_match` 오류 억제 | 비정상 정규식 입력 시 조용히 무효화 | `preg_last_error()`로 모니터링 가능, 현재 미구현 |
