<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class Cors implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        if ($request->method() === 'OPTIONS') {
            return response('', 204, [
                'Access-Control-Allow-Origin'      => '*',
                'Access-Control-Allow-Methods'     => 'GET,POST,PUT,DELETE,OPTIONS',
                'Access-Control-Allow-Headers'     => 'Authorization,Content-Type,API-Version',
                'Access-Control-Max-Age'           => '86400',
            ]);
        }

        $response = $handler($request);
        $origin = getenv('CORS_ORIGIN') ?: '*';
        $response = $response->withHeaders([
            'Access-Control-Allow-Origin'   => $origin,
            'X-Content-Type-Options'        => 'nosniff',
            'X-Frame-Options'               => 'DENY',
            'X-XSS-Protection'              => '1; mode=block',
            'Referrer-Policy'               => 'strict-origin-when-cross-origin',
            'Permissions-Policy'            => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy'       => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self' http: https:;",
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Strict-Transport-Security'     => 'max-age=31536000; includeSubDomains',
        ]);
        return $response;
    }
}
