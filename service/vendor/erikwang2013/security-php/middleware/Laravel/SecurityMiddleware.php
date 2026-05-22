<?php

declare(strict_types=1);

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
            $request->cookie() ?? [],
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
