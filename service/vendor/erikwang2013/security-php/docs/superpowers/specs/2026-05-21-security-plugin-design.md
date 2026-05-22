# Security Plugin Design

## Overview

`erikwang2013/security-php` — PHP security attack detection plugin. Detects XSS, SQL injection, command injection, path traversal, and malicious file uploads. Compatible with webman, Laravel, ThinkPHP, Hyperf via framework-specific middleware adapters.

## Architecture

```
Request → Framework Middleware → SecurityGuard → DetectorChain → [Detectors] → ThreatResult[]
                                                    ↓
                                               Logger (file log)
```

Three layers:
- **Middleware layer** `middleware/` — extracts request data per framework, invokes core, returns block or pass
- **Core layer** `src/` — detection logic, detector chain, logging
- **Config layer** `config/` — main switch, per-detector mode, log channel, block response

## Data Flow

1. Middleware extracts `$_GET + $_POST + $_COOKIE + $_FILES + headers` into a flat key→value array
2. `SecurityGuard::guard($data, $meta)` invoked with request metadata (IP, method, URI)
3. `DetectorChain` runs each enabled detector in order
4. Each detector returns `null` (safe) or `ThreatResult` (threat found)
5. Based on per-detector mode config, either block (403) or log-only

## Components

### DetectorInterface
Each detector implements:
- `name(): string` — unique key matching config, e.g. 'xss', 'sql_injection'
- `detect(array $data): ?ThreatResult`

### ThreatResult (DTO)
Fields: `type`, `severity` (critical/high/medium/low), `field`, `payload`, `detail`

### DetectorChain
- Holds ordered list of enabled detectors
- Runs all detectors on each request (no early exit — one request can trigger multiple threat types)
- Returns `ThreatResult[]` (empty = safe)

### Five Detectors
| Detector | Patterns |
|---|---|
| XssDetector | `<script>`, `onerror=`, `javascript:`, `<img onload=` |
| SqlInjectionDetector | `union select`, `sleep(`, `-- `, `' or 1=1`, `benchmark(` |
| CommandInjectionDetector | backticks, `$()`, `; wget`, `| nc`, `/dev/tcp` |
| PathTraversalDetector | `../`, `..\\`, `/etc/passwd`, `php://filter` |
| UploadDetector | File extension whitelist, PHP tag header detection |

### SecurityGuard
Facade class, single entry point for all callers:
- `guard(array $data, array $meta): ThreatResult[]` — full scan
- `shouldBlock(ThreatResult[] $threats): bool` — checks per-detector mode config
- `blockResponse(): Response` — returns 403

### Logger
File-based attack log:
- Logs timestamp, IP, URI, method, threat type, severity, field, payload
- Log rotation by max file size
- Channel abstraction for future Redis/syslog

### Global Functions (helpers.php)
- `security_scan(array $data): array` — scan arbitrary data
- `security_scan_current_request(): array` — auto-extract from superglobals
- `security_is_safe(array $data): bool` — boolean check
- `security_guard(): void` — scan + die(403) on block

### Framework Middlewares
Each middleware:
1. Extracts input from framework-native Request object
2. Calls `SecurityGuard::guard()`
3. Checks `SecurityGuard::shouldBlock()`
4. Returns 403 or passes to next handler

## Configuration

`config/security.php`:
- `enabled` — global on/off switch
- `detectors.<name>.enabled` — per-detector on/off
- `detectors.<name>.mode` — 'block' or 'log'
- `block_status_code` — HTTP status for blocks (default 403)
- `block_message` — response message
- `log.enabled/channel/path/max_size` — logging config
- `whitelist_ips` — CIDR support
- `whitelist_fields` — skip scanning these field names

## Package Structure

```
erikwang2013/security-php/
  composer.json
  config/
    security.php
  src/
    DetectorInterface.php
    ThreatResult.php
    DetectorChain.php
    SecurityGuard.php
    Logger.php
    Detector/
      XssDetector.php
      SqlInjectionDetector.php
      CommandInjectionDetector.php
      PathTraversalDetector.php
      UploadDetector.php
    helpers.php
  middleware/
    Laravel/
      SecurityMiddleware.php
      SecurityServiceProvider.php
    Webman/
      SecurityMiddleware.php
    Thinkphp/
      SecurityMiddleware.php
    Hyperf/
      SecurityMiddleware.php
```

## Framework Compatibility

| Framework | Input Extraction | Registration |
|---|---|---|
| Laravel | `$request->all()` + `$request->file()` | Auto via ServiceProvider |
| Webman | `$request->post()` + `$request->get()` | Manual in config/middleware.php |
| ThinkPHP | `$request->param()` + `$request->file()` | Manual in app/middleware.php |
| Hyperf | `$request->getParsedBody()` + `$request->getUploadedFiles()` | Manual in config/middlewares.php |
