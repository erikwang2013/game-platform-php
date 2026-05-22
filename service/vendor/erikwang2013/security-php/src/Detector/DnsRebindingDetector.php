<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class DnsRebindingDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'dns_rebinding';
    }

    protected function patterns(): array
    {
        return [
            '/\r\nHost\s*:\s*\d+\.\d+\.\d+\.\d+/i'
                                                => ['severity' => 'critical', 'detail' => 'Host header with raw IP (rebinding)'],
            '/\r\nHost\s*:\s*127\.\d+\.\d+\.\d+/i'
                                                => ['severity' => 'critical', 'detail' => 'Host header loopback IP'],
            '/\r\nHost\s*:\s*10\.\d+\.\d+\.\d+/i'
                                                => ['severity' => 'critical', 'detail' => 'Host header private IP (10.x)'],
            '/\r\nHost\s*:\s*172\.(?:1[6-9]|2\d|3[01])\.\d+\.\d+/i'
                                                => ['severity' => 'critical', 'detail' => 'Host header private IP (172.16-31)'],
            '/\r\nHost\s*:\s*192\.168\.\d+\.\d+/i'
                                                => ['severity' => 'critical', 'detail' => 'Host header private IP (192.168)'],
            '/\r\nHost\s*:\s*\[::1\]/i' => ['severity' => 'critical', 'detail' => 'Host header IPv6 loopback'],
            '/\r\nHost\s*:\s*0\.0\.0\.0/i'
                                                => ['severity' => 'high',     'detail' => 'Host header all-interfaces IP'],
            '/\r\nHost\s*:\s*localhost\b/i'
                                                => ['severity' => 'high',     'detail' => 'Host header localhost (rebinding)'],
            '/\r\nHost\s*:\s*[^.\r]+\.[^.\r]+\.[^.\r]+\.[^.\r]+/i'
                                                => ['severity' => 'high',     'detail' => 'Host header with IP-like pattern'],
            '/\r\nHost\s*:\s*[^.\r]+\.[^.\r]+\.[^.\r]+/i'
                                                => ['severity' => 'medium',   'detail' => 'Host header short hostname (no TLD)'],
        ];
    }
}
