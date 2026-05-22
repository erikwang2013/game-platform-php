<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class OpenRedirectDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'open_redirect';
    }

    protected function patterns(): array
    {
        return [
            '/^\s*\/\/\w+\.[a-z]{2,}/i' => ['severity' => 'high',     'detail' => 'Protocol-relative URL redirect (//domain)'],
            '/^https?:\/\/(?:[^\/]+\/)*@/i'
                                                => ['severity' => 'high',     'detail' => 'User-info redirect (URL credentials)'],
            '/^\s*javascript\s*:/i'     => ['severity' => 'critical', 'detail' => 'JavaScript URI redirect'],
            '/^\s*data\s*:\s*text\/html/i'
                                                => ['severity' => 'critical', 'detail' => 'Data URI redirect'],
            '/^\s*vbscript\s*:/i'       => ['severity' => 'critical', 'detail' => 'VBScript URI redirect'],
            '/\\\\x[0-9a-f]{2}/i'       => ['severity' => 'medium',   'detail' => 'Hex-encoded character in redirect'],
            '/%40\w+\.\w{2,}/i'         => ['severity' => 'medium',   'detail' => 'URL-encoded user-info redirect'],
            '/https?:\/\/(?:[^\/]+)@[^\/]+\.[a-z]{2,}/i'
                                                => ['severity' => 'high',     'detail' => 'Absolute URL with embedded credentials'],
        ];
    }
}
