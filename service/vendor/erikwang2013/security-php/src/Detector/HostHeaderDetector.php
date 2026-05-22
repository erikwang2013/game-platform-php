<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class HostHeaderDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'host_header';
    }

    protected function patterns(): array
    {
        return [
            '/\r\nHost\s*:\s*[^\r\n]+/i'
                                                => ['severity' => 'critical', 'detail' => 'Host header injection via CRLF'],
            '/\nHost\s*:\s*[^\r\n]+/i'  => ['severity' => 'critical', 'detail' => 'Host header injection via LF'],
            '/%0[dD]%0[aA]Host\s*:/i'  => ['severity' => 'critical', 'detail' => 'Host header injection (encoded CRLF)'],
            '/X-Forwarded-Host\s*:\s*[^\r\n]+/i'
                                                => ['severity' => 'high',     'detail' => 'X-Forwarded-Host header injection'],
            '/X-Real-IP\s*:\s*[^\r\n]+/i'
                                                => ['severity' => 'medium',   'detail' => 'X-Real-IP header injection'],
            '/X-Forwarded-For\s*:\s*[^\r\n]+/i'
                                                => ['severity' => 'medium',   'detail' => 'X-Forwarded-For header injection'],
            '/X-Original-URL\s*:\s*[^\r\n]+/i'
                                                => ['severity' => 'high',     'detail' => 'X-Original-URL header injection'],
            '/X-Rewrite-URL\s*:\s*[^\r\n]+/i'
                                                => ['severity' => 'high',     'detail' => 'X-Rewrite-URL header injection'],
            '/Forwarded\s*:\s*for=/i'  => ['severity' => 'low',      'detail' => 'Forwarded header injection'],
            '/\r\n\r\nHTTP\/1\.[01]\s+200/i'
                                                => ['severity' => 'critical', 'detail' => 'HTTP response splitting via host'],
        ];
    }
}
