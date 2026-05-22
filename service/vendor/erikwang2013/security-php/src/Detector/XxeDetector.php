<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class XxeDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'xxe';
    }

    protected function patterns(): array
    {
        return [
            '/<!ENTITY\s+\w+\s+(?:SYSTEM|PUBLIC)\s+/i'
                                        => ['severity' => 'critical', 'detail' => 'XML external entity declaration'],
            '/<!ENTITY\s+%\s+\w+\s+(?:SYSTEM|PUBLIC)\s+/i'
                                        => ['severity' => 'critical', 'detail' => 'Parameter entity XXE'],
            '/<!DOCTYPE\s+\w+\s+\[/i'  => ['severity' => 'high',  'detail' => 'DOCTYPE with internal subset'],
            '/<!DOCTYPE\s+\w+\s+SYSTEM\s+/i'
                                        => ['severity' => 'critical', 'detail' => 'DOCTYPE with SYSTEM identifier'],
            '/<!DOCTYPE\s+\w+\s+PUBLIC\s+/i'
                                        => ['severity' => 'critical', 'detail' => 'DOCTYPE with PUBLIC identifier'],
            '/xmlns:xsi\s*=\s*"http:\/\/www\.w3\.org/i'
                                        => ['severity' => 'low',   'detail' => 'XSI namespace (XML context indicator)'],
        ];
    }
}
