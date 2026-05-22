<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class HeaderInjectionDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'header_injection';
    }

    protected function patterns(): array
    {
        return [
            '/%0[dD]%0[aA]/'           => ['severity' => 'critical', 'detail' => 'URL-encoded CRLF injection'],
            "/\r\n[^\s:]+:\s*[^\r\n]+/" => ['severity' => 'critical', 'detail' => 'Raw CRLF header injection'],
            "/\r\n\r\n/"                => ['severity' => 'critical', 'detail' => 'Double CRLF body separation'],
            '/%0[dD]/'                  => ['severity' => 'high',     'detail' => 'URL-encoded carriage return'],
            '/%0[aA]/'                  => ['severity' => 'high',     'detail' => 'URL-encoded line feed'],
            '/Set-Cookie\s*:\s*[^\r\n]+/i'
                                        => ['severity' => 'critical', 'detail' => 'Cookie injection via header'],
            '/Content-Length\s*:\s*\d+/i'
                                        => ['severity' => 'high',     'detail' => 'Content-Length header injection'],
            '/Transfer-Encoding\s*:\s*[^\r\n]+/i'
                                        => ['severity' => 'high',     'detail' => 'Transfer-Encoding header injection'],
            '/Location\s*:\s*https?:\/\//i'
                                        => ['severity' => 'high',     'detail' => 'Open redirect via header injection'],
        ];
    }
}
