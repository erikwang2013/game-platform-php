<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class XpathInjectionDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'xpath_injection';
    }

    protected function patterns(): array
    {
        return [
            "/'\s+or\s+'1'\s*=\s*'1/i" => ['severity' => 'critical', 'detail' => 'XPATH boolean bypass (1=1)'],
            "/'\s+or\s+'x'\s*=\s*'x/i"  => ['severity' => 'critical', 'detail' => 'XPATH boolean bypass (x=x)'],
            "/'\s*\]\s*\|\s*[^']/i"    => ['severity' => 'high',     'detail' => 'XPATH union operator injection'],
            "/(?:\]|')\s*\|\s*(?:\/\/|count|string|concat|substring|contains|translate)/i"
                                                => ['severity' => 'high',     'detail' => 'XPATH function call injection'],
            "/\/\/\s*\w+\[\s*'\w+'\s*=\s*'/i"
                                                => ['severity' => 'high',     'detail' => 'XPATH recursive traversal with filter'],
            "/count\s*\(\s*\/\/\w+/i"   => ['severity' => 'medium',   'detail' => 'XPATH count() enumeration'],
            "/string\s*\(\s*\/\/\w+/i"  => ['severity' => 'medium',   'detail' => 'XPATH string() extraction'],
            "/substring\s*\(/i"         => ['severity' => 'medium',   'detail' => 'XPATH substring() extraction (blind)'],
            "/contains\s*\(/i"          => ['severity' => 'medium',   'detail' => 'XPATH contains() extraction (blind)'],
            "/translate\s*\(/i"         => ['severity' => 'medium',   'detail' => 'XPATH translate() extraction (blind)'],
            "/\/\*\s*\[\s*'[^']*'\s*=\s*'[^']*'\s*\]/i"
                                                => ['severity' => 'high',     'detail' => 'XPATH wildcard filter attack'],
            "/\<\!\-\-/i"               => ['severity' => 'low',      'detail' => 'XML comment in XPATH query'],
        ];
    }
}
