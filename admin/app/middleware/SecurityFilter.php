<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use support\Redis;

/**
 * Web/API 安全攻击检测拦截中间件
 *
 * 检测并拦截: XSS、SQL注入、路径遍历、命令注入、CSRF
 * 攻击升级: 同 IP 5次/60秒触发攻击检测 → 临时黑名单 15 分钟
 * 输入校验: Content-Type 验证、请求体大小限制
 * 全局执行，在 Cors 之后、RateLimit 之前
 */
class SecurityFilter implements MiddlewareInterface
{
    private const BLOCK_CODE = 403;
    private const MAX_BODY_SIZE = 10 * 1024 * 1024; // 10MB
    private const ESCALATE_LIMIT = 5;   // 60秒内触发次数
    private const ESCALATE_WINDOW = 60;
    private const BAN_DURATION = 900;   // 黑名单 15 分钟

    private const PATTERNS = [
        'XSS' => [
            '/<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b/i',
            '/\bon\w+\s*=\s*[\"\']?\s*(?:javascript|vbscript):/i',
            '/(?:javascript|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval|alert|prompt|confirm|document\.cookie|location\s*=)/i',
            '/data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,/i',
            '/\{\{.*?\}\}/',
        ],
        'SQL注入' => [
            '/\bUNION\s+(?:ALL\s+)?SELECT\b/i',
            '/(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)/i',
            '/\b(?:DROP|ALTER|TRUNCATE)\s+(?:TABLE|DATABASE|INDEX|VIEW)\b/i',
            '/\b(?:xp_cmdshell|sp_executesql|sp_addsrvrolemember)\b/i',
            '/\b(?:INFORMATION_SCHEMA|sys\.(?:tables|columns|databases)|pg_class|sqlite_master|mysql\.(?:user|db))\b/i',
            '/(?:[\"\'])\s*(?:--|#)\s*[\"\']?\s*(?:OR|AND|SELECT|INSERT|UPDATE|DELETE|DROP)/i',
        ],
        '路径遍历' => [
            '/\.\.[\/\\\\]{2,}/',
            '/\/(?:etc\/(?:passwd|shadow|hosts)|proc\/self|boot\.ini|win\.ini|WEB-INF|\.env|\.git\/)/i',
            '/%00/',
        ],
        '命令注入' => [
            '/[;|&]\s*(?:ls|cat|rm|wget|curl|nc|bash|sh|cmd|powershell|python|perl)\b/i',
            '/`[^`]*\b(?:cat|ls|id|whoami|pwd|rm|wget|curl)\b[^`]*`/',
            '/\$\(\s*(?:cat|ls|id|whoami|rm|wget|curl)\b/i',
            '/(?:wget|curl)\s+.*(?:\b-o\b|\b-O\b|pipe|bash|python).*\bhttps?:\/\//i',
        ],
        '恶意文件上传' => [
            '/\.(?:php\d?|phtml|phar|cgi|pl|py|jsp|asp)x?\.(?:png|jpg|gif|pdf)/i',
            '/\.php\s*$/m',
        ],
    ];

    public function process(Request $request, callable $handler): Response
    {
        // 0. HTTP 方法限制 — 仅允许标准方法
        $method = $request->method();
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], true)) {
            return response('<h1>405 Method Not Allowed</h1>', 405, ['Allow' => 'GET,POST,PUT,DELETE,OPTIONS']);
        }

        $ip = $request->getRealIp();

        // 1. IP 黑名单检查（攻击升级后的临时封禁）
        if ($this->isBanned($ip)) {
            return response('<h1>403 Forbidden</h1>', self::BLOCK_CODE);
        }

        // 2. 请求体大小限制
        $length = (int) $request->header('Content-Length', '0');
        if ($length > self::MAX_BODY_SIZE) {
            return response('<h1>413 Payload Too Large</h1>', 413);
        }

        // 3. Content-Type 校验（写操作必须声明类型）
        $method = $request->method();
        if (in_array($method, ['POST', 'PUT'], true)) {
            $ct = $request->header('Content-Type', '');
            // 文件上传跳过
            if ($request->file('file')) {
                // OK
            } elseif ($ct === '' || (!str_contains($ct, 'application/json') && !str_contains($ct, 'application/x-www-form-urlencoded'))) {
                return response('<h1>415 Unsupported Media Type</h1>', 415);
            }
        }

        // 4. 输入扫描
        $inputs = $this->collectInputs($request);
        foreach ($inputs as $source => $values) {
            if (!is_array($values) && !is_string($values)) continue;
            if (is_string($values)) $values = [$values];

            foreach ($values as $key => $value) {
                if (!is_string($value) || empty($value)) continue;
                $blocked = $this->scan($value);
                if ($blocked !== null) {
                    $this->logBlock($request, $blocked, (string) $key, $source, substr($value, 0, 200));
                    // 攻击升级：计入 Redis，超阈值封禁
                    $this->escalate($ip);
                    return response('<h1>403 Forbidden</h1>', self::BLOCK_CODE);
                }
            }
        }

        // 5. CSRF 检查
        if ($this->checkCsrf($request)) {
            return response('<h1>403 Forbidden</h1>', self::BLOCK_CODE);
        }

        return $handler($request);
    }

    /**
     * 检查 IP 是否在临时黑名单中
     */
    private function isBanned(string $ip): bool
    {
        try {
            return (bool) Redis::get("security_ban:{$ip}");
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 攻击升级：记录次数，超阈值封禁
     */
    private function escalate(string $ip): void
    {
        try {
            $key = "security_escalate:{$ip}";
            $count = Redis::incr($key);
            if ($count === 1) {
                Redis::expire($key, self::ESCALATE_WINDOW);
            }
            if ($count >= self::ESCALATE_LIMIT) {
                Redis::setex("security_ban:{$ip}", self::BAN_DURATION, '1');
                Redis::del($key);
                $this->logBan($ip, $count);
            }
        } catch (\Throwable) {}
    }

    private function logBan(string $ip, int $count): void
    {
        @file_put_contents(
            runtime_path() . '/logs/security.log',
            date('Y-m-d H:i:s') . " [SECURITY] IP banned 15min | IP: {$ip} | Triggers: {$count}\n",
            FILE_APPEND | LOCK_EX
        );
    }

    private function collectInputs(Request $request): array
    {
        return [
            'path'  => $request->path(),
            'query' => $request->queryString(),
            'body'  => $request->all(),
            'headers.Referer'   => $request->header('Referer', ''),
            'headers.User-Agent' => $request->header('User-Agent', ''),
            'headers.Cookie'    => $request->header('Cookie', ''),
            'headers.X-Forwarded-For' => $request->header('X-Forwarded-For', ''),
        ];
    }

    private function scan(string $value): ?string
    {
        foreach (self::PATTERNS as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (@preg_match($pattern, $value) === 1) {
                    return $category;
                }
            }
        }
        return null;
    }

    private function checkCsrf(Request $request): bool
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'DELETE'], true)) {
            return false;
        }
        $host = $request->host(true);
        $origin = $request->header('Origin', '');
        if ($origin === '' && $request->header('Referer', '') === '') {
            return false;
        }
        if ($origin !== '') {
            $originHost = parse_url($origin, PHP_URL_HOST);
            $hostOnly = ltrim(parse_url('http://' . $host, PHP_URL_HOST) ?: $host, 'www.');
            if ($originHost && $originHost !== $hostOnly && !str_contains($originHost, '.' . $hostOnly)) {
                return true;
            }
        }
        return false;
    }

    private function logBlock(Request $request, string $category, string $field, string $source, string $payload): void
    {
        $logData = sprintf(
            "[SECURITY] %s attack blocked | IP: %s | Path: %s | Field: %s | Source: %s | Payload: %s",
            $category,
            $request->getRealIp(),
            $request->path(),
            "{$source}.{$field}",
            $source,
            $payload
        );
        @file_put_contents(
            runtime_path() . '/logs/security.log',
            date('Y-m-d H:i:s') . ' ' . $logData . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
