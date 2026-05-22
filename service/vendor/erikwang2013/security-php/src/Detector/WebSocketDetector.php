<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class WebSocketDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'websocket';
    }

    protected function patterns(): array
    {
        return [
            '/\r\nUpgrade\s*:\s*websocket/i'
                                                => ['severity' => 'critical', 'detail' => 'WebSocket upgrade injection via CRLF'],
            '/\r\nConnection\s*:\s*Upgrade/i'
                                                => ['severity' => 'critical', 'detail' => 'Connection upgrade injection via CRLF'],
            '/\r\nSec-WebSocket-Key\s*:/i'
                                                => ['severity' => 'high',     'detail' => 'WebSocket key header injection'],
            '/\r\nSec-WebSocket-Protocol\s*:/i'
                                                => ['severity' => 'high',     'detail' => 'WebSocket protocol header injection'],
            '/\r\nSec-WebSocket-Version\s*:/i'
                                                => ['severity' => 'medium',   'detail' => 'WebSocket version header injection'],
            '/\r\nOrigin\s*:\s*null\s*\r/i'
                                                => ['severity' => 'high',     'detail' => 'WebSocket null-origin bypass'],
            '/\r\nOrigin\s*:\s*(?![\w.-]+\.[a-z]{2,})[^\r]*\r/i'
                                                => ['severity' => 'medium',   'detail' => 'WebSocket suspicious origin'],
            '/ws:\/\/[^\/]+\.[a-z]{2,}/i'
                                                => ['severity' => 'medium',   'detail' => 'Raw WebSocket URL in input'],
        ];
    }
}
