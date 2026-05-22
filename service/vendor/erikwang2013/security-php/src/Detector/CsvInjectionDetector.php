<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class CsvInjectionDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'csv_injection';
    }

    protected function patterns(): array
    {
        return [
            '/^[=\+\-@\t\r]*(?:cmd|SYSTEM|EXEC|DDE|HYPERLINK)/i'
                                                => ['severity' => 'critical', 'detail' => 'CSV formula command injection'],
            '/^=\s*(?:cmd\||powershell|bash|sh|wscript|rundll32)/i'
                                                => ['severity' => 'critical', 'detail' => 'CSV Excel formula shell exec'],
            '/^=\s*(?:HYPERLINK|WEBSERVICE|FILENAME|INFO)\s*\(/i'
                                                => ['severity' => 'high',     'detail' => 'CSV Excel dangerous function'],
            '/^=\s*(?:SUM|AVERAGE|COUNT|MIN|MAX)\s*\(/i'
                                                => ['severity' => 'low',      'detail' => 'CSV formula numeric function'],
            '/^\+?=?\s*HYPERLINK\s*\(\s*"https?:\/\//i'
                                                => ['severity' => 'high',     'detail' => 'CSV HYPERLINK to external URL'],
            '/^\-?\d+\+\d+/'            => ['severity' => 'medium',   'detail' => 'CSV formula injection (calculation)'],
            '/^@SUM\s*\(/i'             => ['severity' => 'low',      'detail' => 'CSV @SUM formula'],
        ];
    }
}
