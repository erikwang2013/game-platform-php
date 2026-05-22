<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Middleware\Thinkphp;

use think\Request;
use think\Response;
use Erikwang2013\Security\SecurityGuard;

class SecurityMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $data = array_merge(
            $request->cookie() ?? [],
            $request->param() ?? [],
        );

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
                'text/plain; charset=utf-8',
                SecurityGuard::blockStatusCode()
            );
        }

        return $next($request);
    }
}
