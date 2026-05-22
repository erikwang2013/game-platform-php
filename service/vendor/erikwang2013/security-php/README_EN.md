# Security PHP

> [中文文档](README.md)

A PHP security attack detection plugin featuring 27 threat detectors and compatibility with Laravel, Webman, ThinkPHP, and Hyperf.

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

---

## Overview

Security PHP is a lightweight PHP security middleware that detects common web attack payloads through regex pattern matching and structural analysis. Each detector is independently configurable (enable/disable + block/log mode), with IP whitelisting (IPv4/IPv6 CIDR), field whitelisting, log rotation, and deduplication.

### Supported Attack Types

#### Injection Attacks

| Detector | Coverage |
|---|---|
| `xss` | Cross-site scripting — `<script>`, event handlers `on[a-z]+=`, SVG/CSS injection, `javascript:` URI |
| `sql_injection` | SQL injection — UNION SELECT (incl. `/**/`, `(` bypass), sleep/benchmark/pg_sleep, boolean blind, schema enumeration, stored procedures |
| `command_injection` | Command injection — backticks, `$()`, pipes, `/dev/tcp`, PHP execution functions, chained commands |
| `nosql_injection` | NoSQL injection — MongoDB `$ne`/`$gt`/`$regex`/`$where` operators, auth bypass |
| `ldap_injection` | LDAP injection — filter operators `(|)`, `(&)`, `(!)`, wildcards, attribute enumeration, hex escape |
| `xpath_injection` | XPATH injection — boolean bypass `1=1`, `|` union, `count/string/substring` blind extraction |
| `jndi_injection` | JNDI/Log4Shell — `${jndi:ldap://`, `${lower:j}` obfuscation, `${env:}` env lookup |
| `ssi_injection` | Server-Side Includes — `<!--#exec cmd=`, `<!--#include file=`, `<!--#echo var=` |
| `graphql_injection` | GraphQL injection — introspection `__schema`/`__type`, deep nesting DoS, mutation detection |
| `ssti` | Server-Side Template Injection — Jinja2 `{{}}`, FreeMarker `${}`, ERB `<% %>`, Python MRO traversal |

#### Protocol & Request Attacks

| Detector | Coverage |
|---|---|
| `ssrf` | Server-Side Request Forgery — private IPs, cloud metadata (169.254.169.254), IPv6 loopback, gopher/dict schemes |
| `xxe` | XML External Entity — `<!ENTITY` SYSTEM/PUBLIC, parameter entities, DOCTYPE declarations |
| `header_injection` | HTTP header injection — CRLF (`%0d%0a` / `\r\n`), Set-Cookie/Location/Content-Length injection |
| `host_header` | Host header attacks — CRLF Host injection, `X-Forwarded-Host`/`X-Original-URL` poisoning |
| `request_smuggling` | HTTP request smuggling — TE/CL inconsistency, dual Transfer-Encoding, folded header obfuscation |
| `open_redirect` | Open redirect — protocol-relative `//evil.com`, `javascript:`/`data:` pseudo-protocols |
| `cors` | CORS bypass — `Origin: null`, `Access-Control-Allow-*` header injection, preflight poisoning |
| `websocket` | WebSocket hijacking — Upgrade header injection, null Origin bypass, `ws://` URL detection |
| `dns_rebinding` | DNS rebinding — Host header with private IPs, localhost, short hostnames without TLDs |

#### Data & Serialization Attacks

| Detector | Coverage |
|---|---|
| `deserialization` | PHP deserialization — `O:digit:` / `C:digit:` serialized objects, `unserialize()` calls, magic methods |
| `csv_injection` | CSV formula injection — `=cmd|`, `=powershell`, `HYPERLINK()` Excel formula attacks |
| `mail_header` | Email header injection — Bcc/Cc/From/To injection, MIME multipart injection |
| `jwt_attack` | JWT attacks — **structural header decoding**: `alg: none` bypass, `kid` path traversal, empty signature |
| `prototype_pollution` | JS prototype pollution — `__proto__`/`constructor` key detection, `__defineGetter__`/`__defineSetter__` |

#### File & Sensitive Data

| Detector | Coverage |
|---|---|
| `path_traversal` | Path traversal — `../`/`..\\`, `php://filter`/`php://input`, null byte, URL-encoded bypasses |
| `upload` | Malicious file upload — extension whitelist + PHP tag (`<?php`, `<?=`) content scanning |
| `data_leak` | Sensitive data exposure — credit card numbers, AWS access keys, private key headers, DB connection strings, API tokens, JWT secrets |

---

## Installation

```bash
composer require erikwang2013/security-php
```

Requires PHP >= 8.1.

---

## Usage

### Quick Start (Global Functions)

```php
<?php
require 'vendor/autoload.php';

// Scan current request (auto-extracts GET/POST/COOKIE/FILES)
$threats = security_scan_current_request();

if (!empty($threats)) {
    foreach ($threats as $threat) {
        echo "Threat detected: {$threat->type} - {$threat->detail}\n";
    }
}

// Or one-liner: scan + auto-block
security_guard();
```

### Laravel

Auto-discovered on install. Publish config:

```bash
php artisan vendor:publish --tag=security-config
```

The middleware alias `security` is registered automatically. Use in routes:

```php
Route::middleware('security')->group(function () {
    Route::post('/api', [ApiController::class, 'handle']);
});
```

Or register globally in `app/Http/Kernel.php`:

```php
protected $middleware = [
    \Erikwang2013\Security\Middleware\Laravel\SecurityMiddleware::class,
];
```

### Webman

In `config/middleware.php`:

```php
return [
    \Erikwang2013\Security\Middleware\Webman\SecurityMiddleware::class,
];
```

### ThinkPHP

In `app/middleware.php`:

```php
return [
    \Erikwang2013\Security\Middleware\Thinkphp\SecurityMiddleware::class,
];
```

### Hyperf

In `config/autoload/middlewares.php`:

```php
return [
    'http' => [
        \Erikwang2013\Security\Middleware\Hyperf\SecurityMiddleware::class,
    ],
];
```

### Manual Usage

```php
use Erikwang2013\Security\SecurityGuard;

$config = require 'config/security.php';
SecurityGuard::init($config);

$threats = SecurityGuard::guard(['input' => '<script>alert(1)</script>']);

if (!empty($threats) && SecurityGuard::shouldBlock($threats)) {
    http_response_code(SecurityGuard::blockStatusCode());
    die(SecurityGuard::blockMessage());
}
```

---

## Configuration

The config file lives at `config/security.php`. All options are documented with inline comments.

### Master Switch

```php
'enabled' => true,  // false to disable all detection
```

### Per-Detector Configuration

```php
'detectors' => [
    'xss' => [
        'enabled' => true,   // enable this detector
        'mode'    => 'block', // 'block' = intercept | 'log' = record only
    ],
    // ...
],
```

> **Note**: `header_injection`, `ssti`, and `nosql_injection` default to `log` mode to avoid false positives on legitimate content (multi-paragraph text, frontend templates, shell variables). Switch to `block` after verifying your use case.

### Block Response

```php
'block_status_code' => 403,
'block_message'     => 'Request blocked by security policy',
```

### Logging

```php
'log' => [
    'enabled'       => true,
    'channel'       => 'file',
    'path'          => '',      // empty = system temp directory
    'max_size'      => 10,      // MB, auto-rotates. 0 to disable rotation
    'dedup_seconds' => 5,       // dedup window — same attack within N seconds logged once per request
],
```

Log format:
```
[2026-05-21 14:22:32] 192.168.1.1 POST /api/login | sql_injection | critical | field=username payload=admin'-- detail=SQL comment termination
```

### IP Whitelist

```php
'whitelist_ips' => [
    '127.0.0.1',           // single IP
    '10.0.0.0/8',          // CIDR block
    '192.168.1.0/24',      // /24 subnet
    '::1',                 // IPv6 address
    'fe80::/10',           // IPv6 CIDR
],
```

### Field Whitelist

```php
'whitelist_fields' => ['_token', '_method', 'csrf_token'],
```

---

## Design

### Architecture

```
HTTP Request
  │
  ▼
┌─────────────────┐
│ Middleware Layer │  Extract GET/POST/COOKIE/FILES from framework Request → flatten to key-value
│ (4 adapters)    │  Invoke SecurityGuard::guard()
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  SecurityGuard   │  Facade: IP whitelist → field whitelist → nested flattening → backtrack limit → scan
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  DetectorChain   │  Chain of Responsibility: invoke all enabled detectors in order, collect ThreatResult[]
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  27 Detectors    │  Each extends AbstractRegexDetector, defines only name() + patterns()
│  (strategy)      │  3 special detectors override detect(): Upload (file content scan),
│                 │  JwtAttack (JWT header decoding), PrototypePollution (key-name inspection)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│     Logger       │  Attack log: fopen+flock atomic writes, size-based rotation, CRLF sanitization, dedup
└─────────────────┘
```

### Design Decisions

**1. Abstract Detector Base Class**

23 of 27 detectors extend `AbstractRegexDetector`. Each is ~15 lines defining only `name()` and `patterns()`. Eliminates ~500 lines of duplicated scan loops. A change to the scanning logic (e.g., adding nested array support) modifies a single file.

```php
class XssDetector extends AbstractRegexDetector
{
    public function name(): string { return 'xss'; }

    protected function patterns(): array
    {
        return [
            '/<script\b/i'    => ['severity' => 'critical', 'detail' => 'Script tag injection'],
            '/\bon[a-z]+\s*=/i' => ['severity' => 'high', 'detail' => 'Event handler injection'],
        ];
    }
}
```

**2. Nested Array Flattening**

`SecurityGuard::flattenData()` recursively processes nested JSON request bodies:

```php
['user' => ['name' => '<script>x</script>']]
→ ['user.name' => '<script>x</script>']
```

Array values are JSON-encoded as strings for detector scanning. Field names use dot-separated paths (e.g. `user.profile.bio`).

**3. Security Hardening**

| Measure | Location | Detail |
|---|---|---|
| PCRE backtrack limit | SecurityGuard::guard() | Sets `pcre.backtrack_limit=1000000` before scan, restores in `finally` |
| Regex error detection | AbstractRegexDetector | Logs to `error_log` on `preg_match === false` (malformed pattern) |
| Log injection prevention | Logger::sanitize() | `\r\n` → `\\r\\n`, `|` → space |
| Atomic log writes | Logger::log() | `fopen`+`flock`+`fwrite` — no TOCTOU race |
| Sensitive data masking | DataLeakDetector | AWS keys appear as `AKIAIOS***XAMPLE` in logs |
| IP whitelist CIDR | SecurityGuard | IPv4 via `ip2long` + bitmask, IPv6 via `inet_pton` + binary comparison |
| Default log mode | config | High-FP detectors default to record-only |

**4. Framework Adapter Strategy**

- Middleware layer has a single responsibility: extract data from framework Request → invoke SecurityGuard
- Core detection logic is zero-dependency, framework-agnostic, requires only PHP 8.1 standard library
- Laravel auto-discovered via `extra.laravel.providers`
- Webman/ThinkPHP/Hyperf registered manually in middleware config
- Global function `security_guard()` supports non-framework projects

**5. Adding a New Detector**

```php
// 1. Create src/Detector/MyDetector.php
class MyDetector extends AbstractRegexDetector
{
    public function name(): string { return 'my_detector'; }
    protected function patterns(): array {
        return [
            '/attack_pattern/i' => ['severity' => 'high', 'detail' => 'Description'],
        ];
    }
}

// 2. Register in SecurityGuard::$detectorMap
'my_detector' => Detector\MyDetector::class,

// 3. Add config in config/security.php
'my_detector' => ['enabled' => true, 'mode' => 'block'],
```

## 开源不易，欢迎支持

| 微信 | 支付宝 |
|:---:|:---:|
| ![微信](./docs/weixinpay.png "微信") | ![支付宝](./docs/alipay.png "支付宝") |

---

### Dependencies

- PHP >= 8.1
- Zero external dependencies

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

```
OK (163 tests, 497 assertions)
```

## License

MIT License — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
