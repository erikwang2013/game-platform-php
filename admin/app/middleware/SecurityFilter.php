<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Erikwang2013\Security\SecurityGuard;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 安全攻击检测拦截中间件
 *
 * 基于 erikwang2013/security-php，覆盖 30 类攻击检测（XSS、SQL注入、命令注入、
 * 路径遍历、SSRF、XXE、JWT攻击、反序列化、SSTI 等），内置 IP 黑名单与攻击升级。
 */
class SecurityFilter implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $data = $this->collectInputs($request);

        $threats = SecurityGuard::guard($data, [
            'ip'     => $request->getRealIp() ?: '0.0.0.0',
            'method' => $request->method(),
            'uri'    => $request->uri(),
        ]);

        if (!empty($threats) && SecurityGuard::shouldBlock($threats)) {
            return response(
                SecurityGuard::blockMessage(),
                SecurityGuard::blockStatusCode($threats)
            );
        }

        return $handler($request);
    }

    private function collectInputs(Request $request): array
    {
        return [
            'path'                   => $request->path(),
            'query'                  => $request->queryString(),
            'body'                   => $request->all(),
            'headers.Referer'        => $request->header('Referer', ''),
            'headers.User-Agent'     => $request->header('User-Agent', ''),
            'headers.Cookie'         => $request->header('Cookie', ''),
            'headers.X-Forwarded-For'=> $request->header('X-Forwarded-For', ''),
        ];
    }
}
