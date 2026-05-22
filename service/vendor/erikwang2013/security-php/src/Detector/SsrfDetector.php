<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class SsrfDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'ssrf';
    }

    protected function patterns(): array
    {
        return [
            '/https?:\/\/127\.\d+\.\d+\.\d+/i'
                                        => ['severity' => 'critical', 'detail' => 'Loopback address SSRF'],
            '/https?:\/\/localhost\b/i' => ['severity' => 'critical', 'detail' => 'Localhost SSRF'],
            '/https?:\/\/10\.\d+\.\d+\.\d+/i'
                                        => ['severity' => 'critical', 'detail' => 'Private network SSRF (10.x)'],
            '/https?:\/\/172\.(?:1[6-9]|2\d|3[01])\.\d+\.\d+/i'
                                        => ['severity' => 'critical', 'detail' => 'Private network SSRF (172.16-31.x)'],
            '/https?:\/\/192\.168\.\d+\.\d+/i'
                                        => ['severity' => 'critical', 'detail' => 'Private network SSRF (192.168.x)'],
            '/https?:\/\/169\.254\.169\.254/i'
                                        => ['severity' => 'critical', 'detail' => 'Cloud metadata endpoint SSRF'],
            '/https?:\/\/0\.0\.0\.0/i'  => ['severity' => 'high',     'detail' => 'All-interfaces SSRF'],
            '/https?:\/\/\[::1\]/i'     => ['severity' => 'high',     'detail' => 'IPv6 loopback SSRF'],
            '/\/\/\[::ffff:127\.\d+\.\d+\.\d+\]/i'
                                        => ['severity' => 'high',     'detail' => 'IPv4-mapped IPv6 loopback SSRF'],
            '/(?:gopher|dict|file):\/\//i'
                                        => ['severity' => 'high',     'detail' => 'Dangerous URI scheme for SSRF'],
        ];
    }
}
