<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use support\Log;
use support\Redis;

class RateLimit implements MiddlewareInterface
{
    private int $defaultLimit = 60;
    private int $defaultWindow = 60;

    private array $sensitive = [
        '/api/v1/auth/login'    => ['limit' => 10, 'window' => 60],
        '/api/v1/auth/register' => ['limit' => 5,  'window' => 60],
        '/api/v1/auth/oauth'    => ['limit' => 10, 'window' => 60],
        '/api/v1/2fa/verify'    => ['limit' => 10, 'window' => 60],
        '/api/v1/payment/callback' => ['limit' => 30, 'window' => 60],
    ];

    public function process(Request $request, callable $handler): Response
    {
        $path = $request->path();
        $ip   = $request->getRealIp();

        $limit  = $this->defaultLimit;
        $window = $this->defaultWindow;

        foreach ($this->sensitive as $pattern => $cfg) {
            if ($path === $pattern || str_starts_with($path, rtrim($pattern, '/') . '/')) {
                $limit  = $cfg['limit'];
                $window = $cfg['window'];
                break;
            }
        }

        $safePath = preg_replace('/[^a-zA-Z0-9_-]/', '_', $path);
        $key = "rate_limit:{$ip}:{$safePath}";
        $now = (int) (microtime(true) * 1000);
        $windowStart = $now - $window * 1000;

        // 原子化滑动窗口：Lua 脚本避免 TOCTOU 竞态
        $lua = <<<'LUA'
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, ARGV[1])
local count = redis.call('ZCARD', KEYS[1])
if count >= tonumber(ARGV[2]) then
    return {0, count}
end
redis.call('ZADD', KEYS[1], ARGV[3], ARGV[4])
redis.call('EXPIRE', KEYS[1], ARGV[5])
return {1, count + 1}
LUA;
        try {
            $result = Redis::eval($lua, 1, $key, $windowStart, $limit, $now, $now . '.' . mt_rand(), $window + 10);
        } catch (\Throwable $e) {
            // Redis 故障时 fail-closed：限流不可用即拒绝，避免安全防线失效（登录/支付回调限流为空转）
            Log::error('RateLimit redis unavailable: ' . $e->getMessage());
            return json([
                'code'    => 503,
                'message' => '服务暂不可用，请稍后再试',
                'data'    => [],
            ])->withStatus(503)->withHeaders(['Retry-After' => '5']);
        }
        $count     = (int) ($result[1] ?? 0);
        $remaining = max($limit - $count, 0);
        $reset     = time() + $window;

        if (empty($result[0])) {
            return json([
                'code'    => 429,
                'message' => '请求过于频繁，请稍后再试',
                'data'    => [],
            ])->withStatus(429)->withHeaders([
                'X-RateLimit-Limit'     => (string) $limit,
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset'     => (string) $reset,
                'Retry-After'           => (string) $window,
            ]);
        }

        $response = $handler($request);
        return $response->withHeaders([
            'X-RateLimit-Limit'     => (string) $limit,
            'X-RateLimit-Remaining' => (string) $remaining,
            'X-RateLimit-Reset'     => (string) $reset,
        ]);
    }
}
