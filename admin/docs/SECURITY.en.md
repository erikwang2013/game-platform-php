# Security Architecture Design Document
<!-- lang-nav -->

Languages: [中文](SECURITY.md) · **English** · [한국어](SECURITY.ko.md) · [Русский](SECURITY.ru.md) · [Deutsch](SECURITY.de.md) · [Français](SECURITY.fr.md) · [Español](SECURITY.es.md) · [Português](SECURITY.pt.md) · [हिन्दी](SECURITY.hi.md) · [العربية](SECURITY.ar.md) · [বাংলা](SECURITY.bn.md) · [Bahasa Indonesia](SECURITY.id.md) · [日本語](SECURITY.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Defense-in-Depth Overview

The system adopts a 7-layer defense-in-depth model, filtering malicious requests layer by layer from the outside in, ensuring that even if any single layer fails, subsequent defense lines still provide a safety net.

The entire middleware chain executes in the following order (see `config/middleware.php`):

```
请求 → Cors → SecurityFilter → RateLimit → [路由组中间件: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Layer | Middleware/Mechanism | Defense target |
|----|--------|---------|
| 1 | SecurityFilter | XSS / SQL injection / path traversal / command injection / CSRF attack blocking |
| 2 | Cors | Cross-origin security + security response header injection |
| 3 | RateLimit | Redis sliding window rate limiting, anti-brute-force |
| 4 | AdminAuth | JWT authentication + blacklist logout |
| 5 | AdminPermission | RBAC method.path granularity authorization |
| 6 | OperationLog | Operation audit + source tracking |
| 7 | Data encryption | Hashids ID obfuscation + Encryptable DB encryption + EncryptionService transport encryption |

The frontend three layers (Flutter) have their own independent input validation; the backend never trusts the frontend, and each layer defends independently.

---

## 2. Attack Detection Engine

### 2.0 HTTP Method Restriction

SecurityFilter validates the HTTP method before all attack detection, allowing only the following standard methods:

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

Non-standard methods (e.g. TRACE, CONNECT, PATCH, custom methods, etc.) are directly rejected with **405 Method Not Allowed** and an empty HTML response body, without entering subsequent attack detection or business logic.

This is the first line of defense in depth, effectively blocking:
- TRACE cross-site tracing attacks (XST)
- CONNECT tunnel proxy abuse
- Non-standard WebDAV method probing
- HTTP method enumeration by automated scanners

### 2.1 XSS Cross-Site Scripting

All regexes come from `SecurityFilter::PATTERNS['XSS']`, matched case-insensitively.

| Detection pattern | Regex | Attack defended |
|----------|------|-----------|
| Script tags | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` and other whitespace variants |
| Event attributes | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | Inline events like `onclick="javascript:..."` |
| JS pseudo-protocol | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` etc. |
| Data URI XSS | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` etc. |
| Template injection | `\{\{.*?\}\}` | `{{constructor}}`, `{{7*7}}` and other server-side/Angular/Vue template injection |

### 2.2 SQL Injection

| Detection pattern | Regex | Attack defended |
|----------|------|-----------|
| UNION queries | `\bUNION\s+(?:ALL\s+)?SELECT\b` | `UNION SELECT`, `UNION ALL SELECT` data extraction |
| OR always-true injection | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| Table structure destruction | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| Stored procedure calls | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | MSSQL extended stored procedure command execution |
| Metadata probing | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | MySQL/PG/SQLite/MSSQL database structure probing |
| Comment bypass | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | `'-- OR SELECT`, `'# AND UPDATE` comment bypass |

### 2.3 Path Traversal

| Detection pattern | Regex | Attack defended |
|----------|------|-----------|
| Directory backtracking | `\.\.[\/\\\\]{2,}` | `../`, `..\`, `....//` multi-level directory traversal |
| Sensitive file probing | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` etc. |
| Null byte truncation | `%00` | `../../../etc/passwd%00.jpg` bypassing extension validation |

### 2.4 Command Injection

| Detection pattern | Regex | Attack defended |
|----------|------|-----------|
| Pipe/semicolon commands | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| Backtick substitution | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| $() substitution | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| Remote download pipe | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF Cross-Site Request Forgery

The validation logic is implemented in `SecurityFilter::checkCsrf()`:

```php
// 仅 POST/PUT/DELETE 触发校验
// Origin 头和 Referer 均为空 → 放行（非浏览器客户端）
// Origin 非空 → 解析 Origin 域名与 Host 比对
```

Comparison rules:
- Strip the `www.` prefix from Host and compare exactly with the Origin domain
- If Host is a parent domain of Origin (e.g. `Origin: app.example.com`, `Host: example.com` — triggers `str_contains($originHost, '.' . $hostOnly)`), allow
- Neither exact match nor subdomain → return 403, judged as a CSRF attack

Note: non-browser clients (e.g. curl without Origin/Referer) pass through directly; CSRF protection is only effective for browser environments.

### 2.6 Malicious File Upload

| Detection pattern | Regex | Attack defended |
|----------|------|-----------|
| Double extension disguise | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` bypassing the whitelist |
| PHP extension | `\.php\s*$/m` | Passing `.php` paths directly in request parameters |

---

## 3. Attack Escalation and IP Blacklist

SecurityFilter has a built-in attack escalation mechanism to prevent continuous scanning attacks from the same IP.

### Escalation Flow

```
第 1 次扫描命中 → Redis INCR security_escalate:{ip} = 1, TTL=60s
第 2 次扫描命中 → INCR → 2
...
第 5 次扫描命中 → INCR → 5
    → 触发封禁: SETEX security_ban:{ip} 900 1
    → 清除计数器 DEL security_escalate:{ip}
    → 写入安全日志: [SECURITY] IP banned 15min
```

### Behavior During a Ban

Every request first checks `isBanned()` when entering SecurityFilter:

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

All requests from a banned IP (including legitimate ones) directly return 403 for 15 minutes, completely skipping subsequent business logic.

### Config Constants

| Constant | Value | Meaning |
|------|-----|------|
| ESCALATE_LIMIT | 5 | Trigger threshold within the 60s window |
| ESCALATE_WINDOW | 60 | Counter window (seconds) |
| BAN_DURATION | 900 | Blacklist duration (seconds), i.e. 15 minutes |

### Security Log

File location: `runtime/logs/security.log`

Example log format:
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### Request Body Size Limit

`Content-Length > 10MB` directly returns 413 Payload Too Large, defending against DoS oversized request body attacks.

### Content-Type Validation

POST/PUT requests **must** declare `Content-Type` as `application/json` or `application/x-www-form-urlencoded`, otherwise 415 Unsupported Media Type is returned. File upload requests (with a file field) skip this check.

---

## 4. Security Response Headers

All headers are injected in the `Cors` middleware via `$response->withHeaders()` on every response.

| Header | Value | Purpose |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Allow cross-origin from any origin (intranet admin backend scenario) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Allowed method set |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | Allowed custom headers |
| Access-Control-Max-Age | `86400` | Preflight request cache for 24 hours |
| X-Content-Type-Options | `nosniff` | Prevents browser MIME sniffing |
| X-Frame-Options | `DENY` | Forbids all iframe embedding, preventing clickjacking |
| X-XSS-Protection | `1; mode=block` | Enables the browser's built-in XSS filter and blocks page rendering |
| Referrer-Policy | `strict-origin-when-cross-origin` | Full URL on same origin, domain only on cross-origin |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Disables camera/microphone/geolocation APIs site-wide |

OPTIONS preflight requests directly return a 204 empty response without entering the subsequent middleware chain.

### 4.2 Content-Security-Policy (CSP)

Injected together with the other security headers in the Cors middleware, providing defense in depth by restricting the resource origins the browser may load and execute.

| Header | Value | Purpose |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Restricts script/style/image/connect/frame/form resource origins |
| X-Permitted-Cross-Domain-Policies | `none` | Forbids cross-domain policy file loading by Adobe Flash/PDF etc. |

Key CSP policy points:
- `default-src 'self'`: only same-origin resources allowed by default
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: allows same-origin scripts + inline scripts (required by Flutter Web) + eval (required by Flutter Web debugging)
- `frame-ancestors 'none'`: forbids iframe embedding by any page, double protection with X-Frame-Options: DENY
- `base-uri 'self'`: restricts `<base>` tags to same-origin only
- `form-action 'self'`: restricts forms to submit same-origin only

---

## 5. Rate Limit Strategy

### Algorithm

Redis Sorted Set sliding window + atomic Lua script, key operations:

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

The Lua script executes single-threaded on the Redis server side, **naturally atomic**, eliminating TOCTOU (Time-of-check to Time-of-use) race conditions.

### Rate Limit Configuration

| Route | Limit | Window | Scenario |
|------|------|------|------|
| Default (all routes) | 60/min | 60s | General API |
| `/api/auth/login` | 10/min | 60s | Login (anti-brute-force) |
| `/api/auth/register` | 5/min | 60s | Registration (anti-batch-registration) |

### Response Headers

Returns HTTP 429 with a JSON body when rate limited:
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

All responses (including normal ones) carry the following headers:

| Header | Description |
|----|------|
| X-RateLimit-Limit | Max requests allowed in the current window |
| X-RateLimit-Remaining | Remaining requests available in the current window |
| X-RateLimit-Reset | Unix timestamp of window reset |
| Retry-After | Only present when rate limited, suggested wait seconds |

### Degradation Strategy

**Fail-closed** when Redis is abnormal (connection timeout, unavailable, etc.):

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    // Redis down: 限流不可用即拒绝，避免安全防线失效（登录/支付回调限流为空转）
    return json(['code' => 503, 'message' => '服务暂不可用，请稍后再试', 'data' => []])
        ->withStatus(503)->withHeaders(['Retry-After' => '5']);
}
```

Rate limiting is the first security line of defense against brute-force login and payment callback replay; when Redis fails, requests are refused (503) rather than let through.

### 5.4 Account Lockout Mechanism

On top of rate limiting, the login endpoint adds an **account lockout** mechanism to prevent targeted brute-force attacks against specific users.

**Lockout flow**:

```
登录失败 → Redis INCR account_lockout:{userId} TTL=900s
连续 5 次失败 → Redis SETEX account_locked:{userId} 900 1
            → 返回 429 "账号已被锁定，请15分钟后再试"
            → 清除计数器 DEL account_lockout:{userId}
```

**Behavior during lockout**:

All login requests directly return 429 during the lockout without password validation, completely blocking brute-force attempts.

**Config constants**:

| Constant | Value | Meaning |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Max consecutive failures |
| LOCKOUT_DURATION | 900 | Lockout duration (seconds), i.e. 15 minutes |

Note: account lockout is based on `userId` rather than IP, so attackers cannot bypass the lockout by changing IPs. Combined with IP rate limiting (10/min), this forms dual protection:
- IP layer: 10/min rate limit blocks distributed brute force
- Account layer: lockout after 5 failures blocks targeted brute force

---

## 6. Authentication and Authorization

### 6.1 JWT Authentication

Implemented by the AdminAuth middleware, mounted on route groups requiring authentication.

**Parameter config** (`config/plugin/erikwang2013/jwt/jwt`, injected from `.env`):

| Parameter | Value | Description |
|------|-----|------|
| Algorithm | HS256 | HMAC-SHA256 symmetric signing |
| Secret | `JWT_SECRET_KEY` | Injected via environment variable; **refuses to start** when missing or still the default (fail-closed) |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Issuer | `open-admin` | `JWT_ISSUER` |
| Audience | `open-admin` | `JWT_AUDIENCE` |

**Token extraction**: extracted from the `Authorization: Bearer <token>` header, stripping the `Bearer ` prefix to get the raw JWT.

**Authentication flow**:
1. Empty token → directly 401 `{"code": 401, "message": "未登录"}`
2. Check the Redis blacklist `jwt_blacklist:{md5(token)}` → hit → 401 `Token已失效，请重新登录`
3. JWT decode → failure (expired/signature mismatch) → 401 `Token已过期或无效`
4. Success → inject `$request->adminId` and `$request->adminUsername`

**Blacklist mechanism**: on user logout, `md5(token)` is written to Redis with TTL set to the JWT's remaining validity. On Redis failure, the blacklist check is skipped (fail-open); logged-out tokens remain usable for a short time, but the JWT's own short validity (2h) acts as a backstop.

**Token refresh**: `POST /api/auth/refresh` validates the original refresh token (with `token_type=refresh`, not expired, not blacklisted) before rotating and issuing new tokens, and validates that `sub` must be a valid user ID — **refresh tokens with sub=null are no longer issued**; refresh failure directly returns 401.

### 6.2 Concurrent Session Limit

To prevent multi-device abuse after a Token leak, the system limits the number of valid tokens a user can hold concurrently.

**Limit logic**:

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

**Config constants**:

| Constant | Value | Meaning |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Max concurrent tokens per user |

**Forced logout scenario**: when a user logs in on a 4th device, the token on the 1st device is forcibly blacklisted, and subsequent requests return 401 `Token已失效，请重新登录`.

On logout, the current token is removed from the set. When a token naturally expires, the Redis key automatically expires and the set membership decreases accordingly.

### 6.3 RBAC Permission Model

Implemented by the AdminPermission middleware.

**Data model**: three-layer User -> Role -> Permission association

- `game_admin_user` (user table)
- `game_admin_user_role` (user-role junction table)
- `game_admin_role` (role table)
- `game_admin_role_permission` (role-permission junction table)
- `game_admin_permission` (permission table)

**Permission types**:
| type | Meaning | Example |
|------|------|------|
| 1 | Menu permission | Controls left navigation visibility |
| 2 | Button permission | Controls in-page operation buttons (create/edit/delete) |
| 3 | API permission | Controls backend endpoint access |

API permission identifier format: `{method}.{path}`

For example:
- `post.admin/user` — create user
- `put.admin/user` — edit user
- `delete.admin/user` — delete user
- `get.admin/user` — view user list

**Authorization flow**:
1. `$request->adminId` empty (not logged in) → directly 401 `{"code": 401, "message": "未登录"}`, no longer let through
2. Get user → roles (skipping disabled roles with `status=0`) → permission list
3. Super admin (`slug = '*'`) → let through directly
4. Build `strtolower(method) . '.' . trim(path, '/')` → compare against the permission list
5. No match → 403 `{"code": 403, "message": "无权限访问"}`

**Re-confirmation**: BaseController provides the `confirmPassword()` method; sensitive operations (deleting users, data export, etc.) additionally require entering the current password at the Controller layer, preventing unauthorized operations after session hijacking.

### 6.4 Payment Callback Verification (fail-closed)

`POST /api/payment/callback` (Stripe/PayPal deposit callback) verification is **fail-closed**: any missing config or validation anomaly rejects the callback:

| Scenario | Behavior |
|------|------|
| Stripe `STRIPE_WEBHOOK_SECRET` not configured | Rejected (403), unsigned callbacks no longer accepted |
| Stripe signature missing / verification failed | Rejected (403) |
| Stripe timestamp `t=` missing or differs from server time by **> ±5 minutes** | Rejected (403), anti-replay |
| PayPal `PAYPAL_WEBHOOK_ID` not configured | Rejected (403) |
| PayPal verification call abnormal / not SUCCESS | Rejected (403) |
| Optional `CALLBACK_TRUSTED_IPS` configured and source IP not in whitelist | Rejected (403) |
| Callback provider mismatches the order's payment method / payment method does not exist | Rejected (403) |

Callback crediting (status update + balance + transaction record) completes within the same database transaction; any step failing rolls back the whole thing, preventing partial crediting.

---

## 7. Audit Logs

### 7.1 Operation Logs

The OperationLog middleware automatically records operation logs for POST / PUT / DELETE requests. GET requests are not recorded.

**Recorded fields**:

| Field | Source | Description |
|------|------|------|
| id | SnowflakeService::generate() | Globally unique ID |
| user_id | `$request->adminId` | Operator ID, 0 when not logged in |
| action | `$request->method()` | Same as method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Request path |
| ip | `$request->getRealIp()` | Client real IP |
| source | detectSource() | Client source platform |
| input | Request body (masked JSON) | Submitted operation data |
| created_at | `date('Y-m-d H:i:s')` | Operation time |

**Sensitive field filtering**: the request body is recursively traversed and the values of the following fields are replaced with `***`:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Source detection** (`detectSource()`), by priority:

1. First reads the `X-Client-Platform` custom header (explicitly declared by native clients)
2. Falls back to User-Agent string inference (detection order in the `detectSource()` method):

| Platform | UA keyword |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Fallback default |

**Fault tolerance**: log write failures do not block business requests (`catch (\Throwable)` silently swallows them).

### 7.2 Security Logs

**File location**: `runtime/logs/security.log`

**Recorded content**:
- Attack blocking logs: attack category, IP, path, field, source, payload fragment (first 200 characters)
- IP ban notifications: banned IP, trigger count

Logs use `FILE_APPEND | LOCK_EX` permissions to ensure concurrency-safe writes.

---

## 8. Data Protection

The system adopts a three-layer data protection strategy corresponding to the three stages of data flow.

### 8.1 Transport Layer — EncryptionService

`EncryptionService` uses the `erikwang2013/encryption` package to encrypt/decrypt sensitive fields in API requests/responses.

**Technical details**:
- Algorithm: `aes-256-cbc-hmac` (built-in HMAC signing against tampering)
- Key: `ENCRYPTION_KEY` environment variable, auto-aligned to 32 bytes
- Used for: transporting fields like phone numbers and ID card numbers between clients and the API

**Masking utility methods**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (username over 2 characters) or `a**@example.com`

### 8.2 Storage Layer — Encryptable Cast

The `AdminUser` model uses the `Erikwang2013\Encryptable\Encryptable` Eloquent cast, for the corresponding fields:

- `email` → cast to Encryptable, auto encrypt/decrypt
- `phone` → cast to Encryptable, auto encrypt/decrypt
- `id_card` → cast to Encryptable, auto encrypt/decrypt

Written to the database auto-encrypted as ciphertext, read out auto-decrypted as plaintext. The database column type is `VARCHAR(500)`, storing ciphertext in base64.

**Key system**: uses an independent `ENCRYPTABLE_KEY` from the transport layer encryption (`ENCRYPTION_KEY`); a leak of one key does not compromise the other layer.

Key rotation: the `ENCRYPTION_PREVIOUS_KEYS` environment variable supports a history key list (comma-separated); when reading old data, historical keys are attempted for decryption, and on write-back the current key is used for re-encryption.

### 8.3 Presentation Layer — ID Obfuscation and Masking

**Hashids ID obfuscation**: `HashidsService` uses the `erikwang2013/hashids` package.

- Database BIGINT IDs returned by external APIs are encoded into hash strings (e.g. `xK3mN9qR2pL7wV8b`)
- Clients pass the hash string in requests, and the backend auto-decodes it to the raw ID
- The salt `HASHIDS_SALT` is injected via environment variable; different salts produce completely different encode/decode results
- Minimum hash length is 16 characters, using a 62-character alphanumeric charset
- BaseController provides the `encodeId()`, `decodeId()`, `encodeIds()` convenience methods

**Export masking**: on Excel/PDF export (ExportController), sensitive fields are uniformly masked:
- Phone number: `138****1234`
- Email: `a***@example.com`
- ID card: fully covered as `********`

---

## 9. Key Management

All keys are injected via `.env` environment variables; config files read them with `getenv()` and have built-in fallback defaults (safe only for development).

| Environment variable | Purpose | Package | Production requirement |
|----------|------|-----|---------|
| JWT_SECRET_KEY | JWT signing secret | erikwang2013/jwt-webman | 64+ character random string; refuses to start when missing or default |
| JWT_ALGORITHM | JWT signing algorithm | same as above | keep HS256 |
| HASHIDS_SALT | ID encoding salt | erikwang2013/hashids | random string |
| SNOWFLAKE_DATACENTER_ID | Datacenter ID (0-31) | erikwang2013/snowflake-php | keep default for single datacenter |
| ENCRYPTION_KEY | API transport layer encryption key | erikwang2013/encryption | 32-byte random string |
| ENCRYPTABLE_KEY | DB storage layer encryption key | erikwang2013/encryptable | 32-byte random string, different from the transport key |

**Security requirements**:
- The `.env` file is in `.gitignore` and must never be committed to the repository
- `.env.example` is a public template file that contains no real secrets
- Production **must** replace all default keys with random strings
- Recommended key generation: `openssl rand -base64 32`

### Key Storage Isolation

| Layer | Config key | Secret environment variable |
|----|--------|-------------|
| Transport encryption | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Storage encryption | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| ID obfuscation | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| JWT signing | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET_KEY` |

---

## 10. security.txt (RFC 9116)

The system provides an RFC 9116-compliant security contact information endpoint at `/.well-known/security.txt`, letting security researchers quickly find the reporting channel when they discover vulnerabilities.

**Access method**:

```
GET /.well-known/security.txt
```

**Response content**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Field descriptions**:

| Field | Description |
|------|------|
| Contact | Security vulnerability reporting contact |
| Expires | File expiry time, needs periodic updates |
| Preferred-Languages | Preferred communication languages |
| Canonical | Canonical URL of this file |
| Policy | Link to the security policy / vulnerability disclosure policy |

This endpoint is not subject to rate limiting, authentication, or other middleware; anyone can access it directly.

---

## 11. Nginx Security Config

The project provides `docs/nginx-security.conf` as a reference security-hardening config for Nginx reverse proxy in production.

**Security measures included**:

| Config item | Purpose |
|--------|------|
| `server_tokens off` | Hides the Nginx version number |
| `client_max_body_size 10m` | Limits request body size, working with SecurityFilter |
| `limit_req_zone` | Request frequency limiting at the Nginx layer |
| `limit_conn_zone` | Concurrent connection limit |
| `add_header` security headers | Appends X-XSS-Protection and other security headers at the Nginx layer |
| `if ($request_method)` | Rejects non-standard HTTP methods at the Nginx layer |
| SSL/TLS config | Modern TLS 1.2/1.3 config, disables weak cipher suites |
| Hide backend headers | `proxy_hide_header` removes sensitive headers like the webman version |

**Usage**: merge the config in `docs/nginx-security.conf` into your Nginx server block, adjusting for your actual domain and certificate paths.

---

## 12. Threat Model

### 12.1 Protected Threats

| Threat type | Attack vector | Defense layers |
|----------|---------|---------|
| HTTP method abuse | TRACE/TRACK XST attacks, CONNECT tunnel proxy, WebDAV method probing | SecurityFilter 405 method whitelist (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| Targeted brute force | Repeatedly trying passwords for a specific user | Account lockout (15-min lock after 5 failures) + RateLimit (login 10/min) + Captcha |
| Brute force | Distributed IPs repeatedly trying usernames/passwords | RateLimit (login 10/min) + Captcha |
| XSS cross-site scripting | `<script>`, onerror, javascript: | SecurityFilter (5 patterns) + X-XSS-Protection response header + CSP |
| SQL injection | UNION SELECT, OR 1=1, comment bypass | SecurityFilter (6 patterns) + Eloquent ORM parameterized queries |
| CSRF cross-site request forgery | Malicious sites forging requests | SecurityFilter Origin/Referer validation |
| Path traversal | `../../etc/passwd` | SecurityFilter path traversal patterns + UploadController extension whitelist |
| Command injection | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 patterns) |
| Session hijacking | Stealing JWT tokens | Short JWT validity (2h) + blacklist logout + password re-confirmation for sensitive operations |
| ID enumeration | Iterating numeric IDs to guess data volume | Hashids obfuscation into random strings |
| Data leakage | DB theft / man-in-the-middle / log leakage | Three-layer encryption/masking + OperationLog sensitive field filtering |
| DoS attacks | Oversized request bodies / high-frequency requests | 10MB request body limit + RateLimit 60/min + IP blacklist |
| Privilege escalation | Low-privilege users accessing admin endpoints | RBAC method.path granularity authorization |
| File upload attacks | shell.php.png double extension | SecurityFilter malicious file detection |

### 12.2 Known Limitations

| Limitation | Impact scope | Mitigation |
|------|---------|---------|
| CSRF protection only works for browsers | Non-browser clients (curl, Postman, mobile apps) can skip the Origin/Referer check | Non-browser clients are naturally not subject to CSRF; relies on JWT auth instead of cookies |
| Rate limit fail-closed (503) and blacklist check fail-open when Redis is unavailable | Some requests rejected during rate limiting; logged-out tokens usable short-term | Monitor Redis availability with alerts; JWT short validity as backstop |
| No dedicated WAF engine | SecurityFilter uses `@preg_match` regex matching, not a dedicated WAF rule engine | Recommend fronting with Nginx ModSecurity or Cloudflare WAF in production |
| Stateless JWT cannot be actively invalidated | Tokens cannot be actively revoked server-side before expiry (other than the blacklist) | Blacklist + short 2h TTL reduces the risk window |
| IP blacklist only in-memory storage | Blacklist lost after Redis restart | Ban duration is only 15 minutes, limited impact |
| No special rate limit for admin endpoints | Admin endpoints share the default 60/min limit with normal endpoints | Admin operation frequency is naturally low; no differentiation needed for now |
| `@preg_match` suppresses errors | Silently fails on malformed regex input | `preg_last_error()` could be monitored, not implemented currently |
