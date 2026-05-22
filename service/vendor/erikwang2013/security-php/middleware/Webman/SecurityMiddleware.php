<?php

declare(strict_types=1);

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
            $request->cookie() ?? [],
            $request->get() ?? [],
            $request->post() ?? [],
        );

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
