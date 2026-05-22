<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class CorsDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'cors';
    }

    protected function patterns(): array
    {
        return [
            '/\r\nOrigin\s*:\s*null\s*\r/i'
                                                => ['severity' => 'critical', 'detail' => 'CORS null origin bypass'],
            '/\r\nOrigin\s*:\s*[^\r]+\s*\r/i'
                                                => ['severity' => 'high',     'detail' => 'Origin header injection via CRLF'],
            '/\r\nAccess-Control-Allow-Origin\s*:/i'
                                                => ['severity' => 'critical', 'detail' => 'CORS: ACAO header injection'],
            '/\r\nAccess-Control-Allow-Credentials\s*:/i'
                                                => ['severity' => 'critical', 'detail' => 'CORS: ACAC header injection'],
            '/\r\nAccess-Control-Allow-Methods\s*:/i'
                                                => ['severity' => 'high',     'detail' => 'CORS: ACAM header injection'],
            '/\r\nAccess-Control-Allow-Headers\s*:/i'
                                                => ['severity' => 'high',     'detail' => 'CORS: ACAH header injection'],
            '/\r\nAccess-Control-Expose-Headers\s*:/i'
                                                => ['severity' => 'medium',   'detail' => 'CORS: ACEH header injection'],
            '/\r\nAccess-Control-Max-Age\s*:/i'
                                                => ['severity' => 'low',      'detail' => 'CORS: ACMA header injection'],
            '/\r\nAccess-Control-Request-Method\s*:/i'
                                                => ['severity' => 'medium',   'detail' => 'CORS preflight method injection'],
            '/\r\nAccess-Control-Request-Headers\s*:/i'
                                                => ['severity' => 'medium',   'detail' => 'CORS preflight headers injection'],
        ];
    }
}
