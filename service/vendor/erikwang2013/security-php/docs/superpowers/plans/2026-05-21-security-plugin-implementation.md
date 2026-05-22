# Security Plugin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `erikwang2013/security-php` — a Composer-installable PHP security attack detection plugin with framework adapters for Laravel, Webman, ThinkPHP, Hyperf.

**Architecture:** Three-layer: middleware adapters extract request data per framework → SecurityGuard facade → DetectorChain runs independent detectors (XSS, SQLi, CMDi, path traversal, upload) → Logger records threats. Configurable per-detector block/log mode.

**Tech Stack:** Pure PHP 8.0+, no dependencies. PSR-4 autoloading. Framework-specific middleware adapters.

---

## File Map

```
erikwang2013/security-php/
  composer.json                          — package metadata, autoload
  config/security.php                    — commented config
  src/
    DetectorInterface.php                — interface for all detectors
    ThreatResult.php                     — immutable result DTO
    DetectorChain.php                    — ordered detector executor
    SecurityGuard.php                    — facade, single entry point
    Logger.php                           — file-based attack logger
    Detector/
      XssDetector.php                    — XSS pattern detection
      SqlInjectionDetector.php           — SQL injection detection
      CommandInjectionDetector.php       — command injection detection
      PathTraversalDetector.php          — path traversal detection
      UploadDetector.php                 — upload validation
    helpers.php                          — global functions
  middleware/
    Laravel/
      SecurityMiddleware.php             — Laravel middleware
      SecurityServiceProvider.php        — auto-registration
    Webman/
      SecurityMiddleware.php             — Webman middleware
    Thinkphp/
      SecurityMiddleware.php             — ThinkPHP middleware
    Hyperf/
      SecurityMiddleware.php             — Hyperf middleware
```

---

### Task 1: Initialize project (composer.json)

**Files:**
- Create: `composer.json`

- [ ] **Step 1: Write composer.json**

```json
{
    "name": "erikwang2013/security-php",
    "description": "PHP security attack detection plugin — XSS, SQL injection, command injection, path traversal, upload detection",
    "type": "library",
    "license": "MIT",
    "keywords": ["security", "xss", "sql-injection", "waf", "attack-detection"],
    "authors": [
        {
            "name": "erik",
            "email": "erik@erik.xyz",
            "homepage": "https://erik.xyz"
        }
    ],
    "require": {
        "php": ">=8.0"
    },
    "autoload": {
        "psr-4": {
            "Erikwang2013\\Security\\": "src/"
        },
        "files": [
            "src/helpers.php"
        ]
    },
    "extra": {
        "laravel": {
            "providers": [
                "Erikwang2013\\Security\\Middleware\\Laravel\\SecurityServiceProvider"
            ]
        }
    }
}
```

---

### Task 2: Config file

**Files:**
- Create: `config/security.php`

- [ ] **Step 1: Write config with full comments**

```php
<?php

/**
 * Security Plugin Configuration
 * 
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * 
 * This file controls all detection behavior.
 * Publish to your project config directory and customize as needed.
 */

return [
    /*
     * 总开关
     * 设为 false 可以临时关闭所有安全检测功能
     * 建议在调试或特定内部环境时设为 false
     */
    'enabled' => true,

    /*
     * 检测器配置
     * 每个检测器可以独立控制启用状态和处理模式
     * 
     * enabled: true=启用检测, false=跳过
     * mode:
     *   'block'  — 检测到攻击时拦截请求，返回 403
     *   'log'    — 仅记录日志，不拦截，适合监控模式
     */
    'detectors' => [
        // XSS 跨站脚本攻击检测
        // 检测 <script>、onerror=、javascript: 等注入模式
        'xss' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // SQL 注入检测
        // 检测 union select、sleep(、-- 注释、or 1=1 等注入模式
        'sql_injection' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // 命令注入检测
        // 检测反引号、$()、管道符、/dev/tcp 等命令执行模式
        'command_injection' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // 路径遍历检测
        // 检测 ../、..\\、/etc/passwd、php://filter 等文件包含模式
        'path_traversal' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // 恶意文件上传检测
        // 检测文件扩展名是否在允许的白名单内，以及 PHP 标签头
        'upload' => [
            'enabled' => true,
            'mode'    => 'block',
        ],
    ],

    /*
     * 拦截响应配置
     * 当检测器的 mode 为 'block' 时生效
     */
    // HTTP 状态码，通常使用 403（禁止访问）或 406（不可接受）
    'block_status_code' => 403,

    // 返回给客户端的内容，{type} 会被替换为攻击类型标识
    'block_message' => 'Request blocked by security policy',

    /*
     * 日志配置
     * 
     * enabled: 是否记录攻击日志
     * channel: 日志通道
     *   'file' — 写入文件（推荐）
     * path: 日志文件路径，留空则使用 sys_get_temp_dir() . '/security.log'
     * max_size: 单个日志文件最大体积，单位 MB，超过后自动轮转
     */
    'log' => [
        'enabled'  => true,
        'channel'  => 'file',
        'path'     => '',
        'max_size' => 10,
    ],

    /*
     * IP 白名单
     * 白名单内的 IP 地址不进行安全检测
     * 格式：支持单个 IP 和 CIDR 网段
     * 示例：
     *   '127.0.0.1',         — 单个 IP
     *   '10.0.0.0/8',        — CIDR 网段
     *   '192.168.1.0/24',    — /24 子网
     */
    'whitelist_ips' => [],

    /*
     * 字段白名单
     * 这些字段名的值将跳过检测，不报告威胁
     * 框架自带的 token 字段、表单辅助字段等应当加入
     * 
     * 例如 Laravel 的 _token（CSRF token）可能包含随机字符串，
     * 加入白名单可以避免误报
     */
    'whitelist_fields' => ['_token', '_method', 'csrf_token'],
];
```

- [ ] **Step 2: Verify file is valid PHP**

```bash
php -l config/security.php
```

---

### Task 3: Core interfaces and DTOs

**Files:**
- Create: `src/DetectorInterface.php`
- Create: `src/ThreatResult.php`

- [ ] **Step 1: Write DetectorInterface**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security;

interface DetectorInterface
{
    /**
     * Unique name matching config detector key (e.g. 'xss', 'sql_injection').
     */
    public function name(): string;

    /**
     * Scan flat key=>value array for attack patterns.
     * Returns ThreatResult if threat found, null if safe.
     */
    public function detect(array $data): ?ThreatResult;
}
```

- [ ] **Step 2: Write ThreatResult**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security;

class ThreatResult
{
    public function __construct(
        public readonly string $type,
        public readonly string $severity,
        public readonly string $field,
        public readonly string $payload,
        public readonly string $detail,
    ) {}
}
```

- [ ] **Step 3: Verify syntax**

```bash
php -l src/DetectorInterface.php && php -l src/ThreatResult.php
```

---

### Task 4: Logger

**Files:**
- Create: `src/Logger.php`

- [ ] **Step 1: Write Logger**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security;

class Logger
{
    private string $path;
    private int $maxSize;
    private bool $enabled;

    public function __construct(array $config)
    {
        $this->enabled = $config['enabled'] ?? true;
        $this->maxSize = (int) ($config['max_size'] ?? 10);
        $this->path = $config['path'] ?: sys_get_temp_dir() . '/security.log';
    }

    public function log(ThreatResult $threat, array $meta): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->rotateIfNeeded();

        $line = sprintf(
            "[%s] %s %s %s | %s | %s | field=%s payload=%s detail=%s",
            date('Y-m-d H:i:s'),
            $meta['ip'] ?? '-',
            $meta['method'] ?? '-',
            $meta['uri'] ?? '-',
            $threat->type,
            $threat->severity,
            $threat->field,
            $this->truncate($threat->payload, 200),
            $threat->detail,
        );

        file_put_contents($this->path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->path)) {
            return;
        }
        $size = filesize($this->path);
        if ($size === false) {
            return;
        }
        $maxBytes = $this->maxSize * 1024 * 1024;
        if ($size >= $maxBytes) {
            rename($this->path, $this->path . '.' . date('YmdHis'));
        }
    }

    private function truncate(string $s, int $max): string
    {
        return strlen($s) > $max ? substr($s, 0, $max) . '...' : $s;
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l src/Logger.php
```

---

### Task 5: Detectors (all five)

**Files:**
- Create: `src/Detector/XssDetector.php`
- Create: `src/Detector/SqlInjectionDetector.php`
- Create: `src/Detector/CommandInjectionDetector.php`
- Create: `src/Detector/PathTraversalDetector.php`
- Create: `src/Detector/UploadDetector.php`

- [ ] **Step 1: Write XssDetector**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

use Erikwang2013\Security\DetectorInterface;
use Erikwang2013\Security\ThreatResult;

class XssDetector implements DetectorInterface
{
    public function name(): string
    {
        return 'xss';
    }

    public function detect(array $data): ?ThreatResult
    {
        $patterns = [
            '/<script\b/i'              => ['severity' => 'critical', 'detail' => 'Script tag injection'],
            '/<iframe\b/i'              => ['severity' => 'high',     'detail' => 'Iframe injection'],
            '/on(load|error|click|mouseover|focus|blur)\s*=/i'
                                        => ['severity' => 'high',     'detail' => 'Event handler injection'],
            '/javascript\s*:/i'         => ['severity' => 'high',     'detail' => 'JavaScript URI scheme'],
            '/<embed\b/i'               => ['severity' => 'medium',   'detail' => 'Embed tag injection'],
            '/<object\b/i'              => ['severity' => 'medium',   'detail' => 'Object tag injection'],
            '/<link\b/i'                => ['severity' => 'medium',   'detail' => 'Link tag injection'],
            '/<meta\b/i'                => ['severity' => 'low',      'detail' => 'Meta tag injection'],
            '/expression\s*\(/i'        => ['severity' => 'medium',   'detail' => 'CSS expression injection'],
            '/<svg\b/i'                 => ['severity' => 'low',      'detail' => 'SVG tag injection'],
        ];

        foreach ($data as $field => $value) {
            if (!is_string($value)) {
                continue;
            }
            foreach ($patterns as $pattern => $info) {
                if (preg_match($pattern, $value)) {
                    return new ThreatResult(
                        type: 'xss',
                        severity: $info['severity'],
                        field: (string) $field,
                        payload: $value,
                        detail: $info['detail'],
                    );
                }
            }
        }

        return null;
    }
}
```

- [ ] **Step 2: Write SqlInjectionDetector**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

use Erikwang2013\Security\DetectorInterface;
use Erikwang2013\Security\ThreatResult;

class SqlInjectionDetector implements DetectorInterface
{
    public function name(): string
    {
        return 'sql_injection';
    }

    public function detect(array $data): ?ThreatResult
    {
        $patterns = [
            '/(?:^|\s)union\s+select\b/i'
                                        => ['severity' => 'critical', 'detail' => 'UNION SELECT injection'],
            '/(?:^|\s)select\b.*\bfrom\b.*\bwhere\b/i'
                                        => ['severity' => 'high',     'detail' => 'SELECT FROM WHERE pattern'],
            '/\b(?:sleep|benchmark)\s*\(/i'
                                        => ['severity' => 'critical', 'detail' => 'Time-based blind injection'],
            '/\b(?:or|and)\s+\d+\s*=\s*\d+/i'
                                        => ['severity' => 'high',     'detail' => 'Boolean-based injection'],
            "/\b(?:or|and)\s+'[^']*'\s*=\s*'[^']*/i"
                                        => ['severity' => 'high',     'detail' => 'String-based injection'],
            '/\b(?:or|and)\s+\d+\s*>\s*\d+/i'
                                        => ['severity' => 'medium',   'detail' => 'Numeric comparison injection'],
            '/--\s*$|--\+|#$/m'         => ['severity' => 'medium',   'detail' => 'SQL comment termination'],
            '/\/\*!.*?\*\//i'           => ['severity' => 'medium',   'detail' => 'MySQL special comment'],
            '/\b(?:information_schema|pg_catalog|sys\.|sqlite_master)\b/i'
                                        => ['severity' => 'high',     'detail' => 'Schema enumeration'],
            '/\b(?:load_file|into\s+(?:out|dump)file|pg_read_file)\b/i'
                                        => ['severity' => 'critical', 'detail' => 'File read/write via SQL'],
            '/\b(?:exec|xp_cmdshell|sp_executesql|execute\s+immediate)\b/i'
                                        => ['severity' => 'critical', 'detail' => 'Stored procedure execution'],
            '/\b(?:waitfor|delay)\b/i'  => ['severity' => 'critical', 'detail' => 'Time delay injection'],
            '/<>\b/i'                   => ['severity' => 'low',      'detail' => 'SQL inequality test'],
        ];

        foreach ($data as $field => $value) {
            if (!is_string($value)) {
                continue;
            }
            foreach ($patterns as $pattern => $info) {
                if (preg_match($pattern, $value)) {
                    return new ThreatResult(
                        type: 'sql_injection',
                        severity: $info['severity'],
                        field: (string) $field,
                        payload: $value,
                        detail: $info['detail'],
                    );
                }
            }
        }

        return null;
    }
}
```

- [ ] **Step 3: Write CommandInjectionDetector**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

use Erikwang2013\Security\DetectorInterface;
use Erikwang2013\Security\ThreatResult;

class CommandInjectionDetector implements DetectorInterface
{
    public function name(): string
    {
        return 'command_injection';
    }

    public function detect(array $data): ?ThreatResult
    {
        $patterns = [
            '/`[^`]+`/'                 => ['severity' => 'critical', 'detail' => 'Backtick command substitution'],
            '/\$\([^)]+\)/'             => ['severity' => 'critical', 'detail' => 'Dollar-parenthesis command substitution'],
            '/;\s*(?:wget|curl|wget|fetch|lynx)\b/i'
                                        => ['severity' => 'high',     'detail' => 'Download command after semicolon'],
            '/\|\s*(?:nc|netcat|ncat)\b/i'
                                        => ['severity' => 'high',     'detail' => 'Netcat pipe injection'],
            '/\b(?:wget|curl)\s+http/i' => ['severity' => 'high',     'detail' => 'Remote resource download'],
            '/\/dev\/tcp\//i'           => ['severity' => 'critical', 'detail' => 'Bash TCP reverse shell device'],
            '/\/dev\/udp\//i'           => ['severity' => 'critical', 'detail' => 'Bash UDP reverse shell device'],
            '/>\s*\/dev\/null/i'        => ['severity' => 'low',      'detail' => 'Output redirection to /dev/null'],
            '/\b(?:system|exec|passthru|shell_exec|popen|proc_open|pcntl_exec)\s*\(/i'
                                        => ['severity' => 'critical', 'detail' => 'PHP code execution function'],
            '/\|\s*\|\s*/'              => ['severity' => 'medium',    'detail' => 'OR operator chain'],
            '/&&\s*(?:wget|curl|nc|bash|sh|python|perl|ruby|php)/i'
                                        => ['severity' => 'high',     'detail' => 'Chained command execution'],
            '/;\s*(?:bash|sh|python|perl|ruby|php)\b/i'
                                        => ['severity' => 'high',     'detail' => 'Interpreter execution after semicolon'],
        ];

        foreach ($data as $field => $value) {
            if (!is_string($value)) {
                continue;
            }
            foreach ($patterns as $pattern => $info) {
                if (preg_match($pattern, $value)) {
                    return new ThreatResult(
                        type: 'command_injection',
                        severity: $info['severity'],
                        field: (string) $field,
                        payload: $value,
                        detail: $info['detail'],
                    );
                }
            }
        }

        return null;
    }
}
```

- [ ] **Step 4: Write PathTraversalDetector**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

use Erikwang2013\Security\DetectorInterface;
use Erikwang2013\Security\ThreatResult;

class PathTraversalDetector implements DetectorInterface
{
    public function name(): string
    {
        return 'path_traversal';
    }

    public function detect(array $data): ?ThreatResult
    {
        $patterns = [
            '/\.\.\//'                  => ['severity' => 'high',     'detail' => 'Directory traversal ../'],
            '/\.\.\\\\/'                => ['severity' => 'high',     'detail' => 'Directory traversal ..\\'],
            '/%2e%2e%2f/i'             => ['severity' => 'high',     'detail' => 'URL-encoded traversal %2e%2e%2f'],
            '/%2e%2e%5c/i'             => ['severity' => 'high',     'detail' => 'URL-encoded traversal %2e%2e%5c'],
            '/\/etc\/(?:passwd|shadow|hosts|group)\b/i'
                                        => ['severity' => 'critical', 'detail' => 'Linux system file access'],
            '/C:\\\\Windows\\\\(?:System32|win\.ini)\b/i'
                                        => ['severity' => 'critical', 'detail' => 'Windows system file access'],
            '/php:\/\/filter/i'         => ['severity' => 'critical', 'detail' => 'PHP filter wrapper'],
            '/php:\/\/input/i'          => ['severity' => 'critical', 'detail' => 'PHP input wrapper'],
            '/data:\/\/text/i'          => ['severity' => 'high',     'detail' => 'Data URI injection'],
            '/expect:\/\//i'            => ['severity' => 'critical', 'detail' => 'Expect wrapper command execution'],
            '/phar:\/\//i'              => ['severity' => 'high',     'detail' => 'Phar wrapper deserialization'],
            '/(?:%00|\x00)/'            => ['severity' => 'high',     'detail' => 'Null byte injection'],
            '/file:\/\//i'              => ['severity' => 'high',     'detail' => 'File URI scheme'],
        ];

        foreach ($data as $field => $value) {
            if (!is_string($value)) {
                continue;
            }
            foreach ($patterns as $pattern => $info) {
                if (preg_match($pattern, $value)) {
                    return new ThreatResult(
                        type: 'path_traversal',
                        severity: $info['severity'],
                        field: (string) $field,
                        payload: $value,
                        detail: $info['detail'],
                    );
                }
            }
        }

        return null;
    }
}
```

- [ ] **Step 5: Write UploadDetector**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

use Erikwang2013\Security\DetectorInterface;
use Erikwang2013\Security\ThreatResult;

class UploadDetector implements DetectorInterface
{
    public function name(): string
    {
        return 'upload';
    }

    /**
     * Whitelist of safe file extensions.
     */
    const SAFE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'md', 'json', 'xml', 'yaml', 'yml',
        'zip', 'tar', 'gz', 'rar', '7z',
        'mp3', 'mp4', 'avi', 'mov', 'wav',
        'css', 'js', 'ts', 'html', 'htm',
    ];

    public function detect(array $data): ?ThreatResult
    {
        // Only scan file upload fields ($_FILES structure)
        foreach ($data as $field => $file) {
            if (!$this->isUploadFile($file)) {
                continue;
            }

            $name = $file['name'] ?? 'unknown';
            $tmpPath = $file['tmp_name'] ?? null;

            // Check extension
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== '' && !in_array($ext, self::SAFE_EXTENSIONS, true)) {
                return new ThreatResult(
                    type: 'upload',
                    severity: 'high',
                    field: (string) $field,
                    payload: $name,
                    detail: "Forbidden file extension: .{$ext}",
                );
            }

            // Check file content for PHP tags
            if ($tmpPath && file_exists($tmpPath)) {
                $head = file_get_contents($tmpPath, false, null, 0, 1024);
                if ($head !== false && preg_match('/<\?(?:php|=)/i', $head)) {
                    return new ThreatResult(
                        type: 'upload',
                        severity: 'critical',
                        field: (string) $field,
                        payload: $name,
                        detail: 'PHP code detected in file content',
                    );
                }
            }
        }

        return null;
    }

    private function isUploadFile(mixed $value): bool
    {
        return is_array($value)
            && isset($value['tmp_name'], $value['name'])
            && is_string($value['tmp_name'])
            && $value['tmp_name'] !== '';
    }
}
```

- [ ] **Step 6: Verify all detector syntax**

```bash
php -l src/Detector/XssDetector.php && php -l src/Detector/SqlInjectionDetector.php && php -l src/Detector/CommandInjectionDetector.php && php -l src/Detector/PathTraversalDetector.php && php -l src/Detector/UploadDetector.php
```

---

### Task 6: DetectorChain

**Files:**
- Create: `src/DetectorChain.php`

- [ ] **Step 1: Write DetectorChain**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security;

class DetectorChain
{
    private array $detectors = [];

    public function add(DetectorInterface $detector): self
    {
        $this->detectors[] = $detector;
        return $this;
    }

    /**
     * Run all registered detectors against the data.
     * Returns all threats found (empty array = safe).
     */
    public function scan(array $data): array
    {
        $threats = [];
        foreach ($this->detectors as $detector) {
            $result = $detector->detect($data);
            if ($result !== null) {
                $threats[] = $result;
            }
        }
        return $threats;
    }

    /**
     * Get registered detector count.
     */
    public function count(): int
    {
        return count($this->detectors);
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l src/DetectorChain.php
```

---

### Task 7: SecurityGuard (facade)

**Files:**
- Create: `src/SecurityGuard.php`

- [ ] **Step 1: Write SecurityGuard**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security;

class SecurityGuard
{
    private static ?DetectorChain $chain = null;
    private static ?Logger $logger = null;
    private static ?array $config = null;

    /**
     * Initialize the guard with config.
     * Called once by middleware or bootstrap.
     */
    public static function init(array $config): void
    {
        self::$config = $config;

        if (empty($config['enabled'])) {
            return;
        }

        self::$chain = new DetectorChain();
        self::$logger = new Logger($config['log'] ?? []);

        $detectorsConfig = $config['detectors'] ?? [];
        $detectorMap = [
            'xss'                => Detector\XssDetector::class,
            'sql_injection'      => Detector\SqlInjectionDetector::class,
            'command_injection'  => Detector\CommandInjectionDetector::class,
            'path_traversal'     => Detector\PathTraversalDetector::class,
            'upload'             => Detector\UploadDetector::class,
        ];

        foreach ($detectorMap as $key => $class) {
            $cfg = $detectorsConfig[$key] ?? null;
            if ($cfg && !empty($cfg['enabled'])) {
                self::$chain->add(new $class());
            }
        }
    }

    /**
     * Full scan with metadata.
     * Returns ThreatResult[]. Empty array = safe.
     */
    public static function guard(array $data, array $meta = []): array
    {
        if (self::$config === null) {
            // Not initialized — load default config
            $defaultConfig = require dirname(__DIR__) . '/config/security.php';
            self::init($defaultConfig);
        }

        if (empty(self::$config['enabled']) || self::$chain === null) {
            return [];
        }

        // IP whitelist check
        $ip = $meta['ip'] ?? '';
        if ($ip && self::isWhitelistedIp($ip)) {
            return [];
        }

        // Remove whitelisted fields
        $filtered = self::filterWhitelistFields($data);

        $threats = self::$chain->scan($filtered);

        // Log all threats
        foreach ($threats as $threat) {
            if (self::$logger !== null) {
                self::$logger->log($threat, $meta);
            }
        }

        return $threats;
    }

    /**
     * Determine if any threat should cause a block.
     * Returns true if at least one threat's detector is in 'block' mode.
     */
    public static function shouldBlock(array $threats): bool
    {
        $detectorsConfig = self::$config['detectors'] ?? [];
        foreach ($threats as $threat) {
            $mode = $detectorsConfig[$threat->type]['mode'] ?? 'log';
            if ($mode === 'block') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get configured block HTTP status code.
     */
    public static function blockStatusCode(): int
    {
        return (int) (self::$config['block_status_code'] ?? 403);
    }

    /**
     * Get configured block response message.
     */
    public static function blockMessage(): string
    {
        return (string) (self::$config['block_message'] ?? 'Request blocked by security policy');
    }

    private static function isWhitelistedIp(string $ip): bool
    {
        $whitelist = self::$config['whitelist_ips'] ?? [];
        if (empty($whitelist)) {
            return false;
        }
        foreach ($whitelist as $allowed) {
            if (self::ipMatches($ip, $allowed)) {
                return true;
            }
        }
        return false;
    }

    private static function ipMatches(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }
        [$subnet, $bits] = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $mask = -1 << (32 - (int) $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    private static function filterWhitelistFields(array $data): array
    {
        $whitelist = self::$config['whitelist_fields'] ?? [];
        if (empty($whitelist)) {
            return $data;
        }
        return array_diff_key($data, array_flip($whitelist));
    }

    /**
     * Reset state (useful for testing).
     */
    public static function reset(): void
    {
        self::$chain = null;
        self::$logger = null;
        self::$config = null;
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l src/SecurityGuard.php
```

---

### Task 8: Global helper functions

**Files:**
- Create: `src/helpers.php`

- [ ] **Step 1: Write helpers**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * 
 * Global helper functions for manual security scanning.
 * No framework dependency — works with plain PHP arrays.
 */

use Erikwang2013\Security\SecurityGuard;

if (!function_exists('security_scan')) {
    /**
     * Scan an arbitrary key=>value array for security threats.
     * Returns ThreatResult[] — empty array means safe.
     */
    function security_scan(array $data): array
    {
        return SecurityGuard::guard($data);
    }
}

if (!function_exists('security_scan_current_request')) {
    /**
     * Scan the current HTTP request superglobals.
     * Extracts GET, POST, COOKIE, and FILES automatically.
     */
    function security_scan_current_request(): array
    {
        $data = array_merge(
            $_GET,
            $_POST,
            $_COOKIE,
        );

        if (!empty($_FILES)) {
            foreach ($_FILES as $key => $file) {
                $data[$key] = [
                    'name'     => $file['name'] ?? '',
                    'tmp_name' => $file['tmp_name'] ?? '',
                ];
            }
        }

        return SecurityGuard::guard($data, [
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri'    => $_SERVER['REQUEST_URI'] ?? '/',
        ]);
    }
}

if (!function_exists('security_is_safe')) {
    /**
     * Quick check: is the given data safe?
     */
    function security_is_safe(array $data): bool
    {
        return security_scan($data) === [];
    }
}

if (!function_exists('security_guard')) {
    /**
     * Scan current request and die with 403 if any detector is in block mode.
     * Suitable for use in non-framework projects or bootstrap files.
     */
    function security_guard(): void
    {
        $threats = security_scan_current_request();

        if (!empty($threats) && SecurityGuard::shouldBlock($threats)) {
            http_response_code(SecurityGuard::blockStatusCode());
            header('Content-Type: text/plain; charset=utf-8');
            die(SecurityGuard::blockMessage());
        }
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l src/helpers.php
```

---

### Task 9: Framework middlewares — Laravel

**Files:**
- Create: `middleware/Laravel/SecurityMiddleware.php`
- Create: `middleware/Laravel/SecurityServiceProvider.php`

- [ ] **Step 1: Write Laravel SecurityMiddleware**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Middleware\Laravel;

use Closure;
use Erikwang2013\Security\SecurityGuard;
use Illuminate\Http\Request;

class SecurityMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $data = array_merge(
            $request->all(),
            $this->extractFiles($request),
        );

        $threats = SecurityGuard::guard($data, [
            'ip'     => $request->ip() ?? '0.0.0.0',
            'method' => $request->method(),
            'uri'    => $request->path(),
        ]);

        if (!empty($threats) && SecurityGuard::shouldBlock($threats)) {
            return response(
                SecurityGuard::blockMessage(),
                SecurityGuard::blockStatusCode(),
                ['Content-Type' => 'text/plain; charset=utf-8']
            );
        }

        return $next($request);
    }

    private function extractFiles(Request $request): array
    {
        $files = [];
        foreach ($request->allFiles() as $key => $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $files[$key] = [
                    'name'     => $file->getClientOriginalName(),
                    'tmp_name' => $file->getPathname(),
                ];
            }
        }
        return $files;
    }
}
```

- [ ] **Step 2: Write Laravel SecurityServiceProvider**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Middleware\Laravel;

use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Publish config
        $this->mergeConfigFrom(
            dirname(__DIR__, 2) . '/config/security.php',
            'security'
        );
    }

    public function boot(): void
    {
        // Publish config to app config directory
        $this->publishes([
            dirname(__DIR__, 2) . '/config/security.php' => config_path('security.php'),
        ], 'security-config');

        // Initialize SecurityGuard with config
        \Erikwang2013\Security\SecurityGuard::init(config('security'));

        // Register middleware
        $this->app['router']->aliasMiddleware('security', SecurityMiddleware::class);
    }
}
```

- [ ] **Step 3: Verify both files**

```bash
php -l middleware/Laravel/SecurityMiddleware.php && php -l middleware/Laravel/SecurityServiceProvider.php
```

---

### Task 10: Framework middlewares — Webman

**Files:**
- Create: `middleware/Webman/SecurityMiddleware.php`

- [ ] **Step 1: Write Webman SecurityMiddleware**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Middleware\Webman;

use Erikwang2013\Security\SecurityGuard;
use Webman\MiddlewareInterface;
use Webman\Http\Request;
use Webman\Http\Response;

class SecurityMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $data = array_merge(
            $request->get() ?? [],
            $request->post() ?? [],
        );

        // Extract files
        foreach ($request->file() ?? [] as $key => $file) {
            if (is_array($file) && isset($file['tmp_name'], $file['name'])) {
                $data[$key] = [
                    'name'     => $file['name'],
                    'tmp_name' => $file['tmp_name'],
                ];
            }
        }

        $threats = SecurityGuard::guard($data, [
            'ip'     => $request->getRealIp() ?? '0.0.0.0',
            'method' => $request->method(),
            'uri'    => $request->path(),
        ]);

        if (!empty($threats) && SecurityGuard::shouldBlock($threats)) {
            return new Response(
                SecurityGuard::blockStatusCode(),
                ['Content-Type' => 'text/plain; charset=utf-8'],
                SecurityGuard::blockMessage()
            );
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l middleware/Webman/SecurityMiddleware.php
```

---

### Task 11: Framework middlewares — ThinkPHP

**Files:**
- Create: `middleware/Thinkphp/SecurityMiddleware.php`

- [ ] **Step 1: Write ThinkPHP SecurityMiddleware**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Middleware\Thinkphp;

use think\middleware;
use think\Request;
use think\Response;
use Erikwang2013\Security\SecurityGuard;

class SecurityMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $data = array_merge(
            $request->param() ?? [],
        );

        // Extract files
        $files = $request->file();
        if (!empty($files)) {
            foreach ($files as $key => $file) {
                if ($file instanceof \think\File) {
                    $data[$key] = [
                        'name'     => $file->getOriginalName(),
                        'tmp_name' => $file->getPathname(),
                    ];
                }
            }
        }

        $threats = SecurityGuard::guard($data, [
            'ip'     => $request->ip() ?? '0.0.0.0',
            'method' => $request->method(),
            'uri'    => $request->pathinfo(),
        ]);

        if (!empty($threats) && SecurityGuard::shouldBlock($threats)) {
            return Response::create(
                SecurityGuard::blockMessage(),
                'html',
                SecurityGuard::blockStatusCode()
            );
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l middleware/Thinkphp/SecurityMiddleware.php
```

---

### Task 12: Framework middlewares — Hyperf

**Files:**
- Create: `middleware/Hyperf/SecurityMiddleware.php`

- [ ] **Step 1: Write Hyperf SecurityMiddleware**

```php
<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Middleware\Hyperf;

use Erikwang2013\Security\SecurityGuard;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SecurityMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = array_merge(
            $request->getParsedBody() ?? [],
            $request->getQueryParams() ?? [],
        );

        // Extract files
        $uploadedFiles = $request->getUploadedFiles();
        foreach ($uploadedFiles as $key => $file) {
            if ($file instanceof \Hyperf\HttpMessage\Upload\UploadedFile) {
                $data[$key] = [
                    'name'     => $file->getClientFilename() ?? '',
                    'tmp_name' => $file->getStream()->getMetadata('uri') ?? '',
                ];
            }
        }

        $serverParams = $request->getServerParams();

        $threats = SecurityGuard::guard($data, [
            'ip'     => $serverParams['remote_addr'] ?? '0.0.0.0',
            'method' => $request->getMethod(),
            'uri'    => $request->getUri()->getPath(),
        ]);

        if (!empty($threats) && SecurityGuard::shouldBlock($threats)) {
            $statusCode = SecurityGuard::blockStatusCode();
            $message = SecurityGuard::blockMessage();

            $response = new \Hyperf\HttpMessage\Server\Response();
            return $response
                ->withStatus($statusCode)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                ->withBody(new \Hyperf\HttpMessage\Stream\SwooleStream($message));
        }

        return $handler->handle($request);
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l middleware/Hyperf/SecurityMiddleware.php
```

---

### Task 13: Final verification

- [ ] **Step 1: Verify all files pass lint**

```bash
find . -name "*.php" -exec php -l {} \;
```

- [ ] **Step 2: Verify composer.json is valid**

```bash
composer validate
```

- [ ] **Step 3: Install and test autoloading**

```bash
composer install
php -r "require 'vendor/autoload.php'; echo 'Autoload OK\n';"
```
