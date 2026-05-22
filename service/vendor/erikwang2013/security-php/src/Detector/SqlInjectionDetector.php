<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class SqlInjectionDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'sql_injection';
    }

    protected function patterns(): array
    {
        return [
            '#\bunion\s+(?:all\s+)?select\b|\bunion/\*\*/select\b|union\(\s*select\b#i'
                                        => ['severity' => 'critical', 'detail' => 'UNION SELECT injection (with bypass detection)'],
            '/(?:^|\s)select\b[^;]*\bfrom\b[^;]*\bwhere\b/i'
                                        => ['severity' => 'high',     'detail' => 'SELECT FROM WHERE pattern'],
            '/\b(?:sleep|benchmark|pg_sleep)\s*\(/i'
                                        => ['severity' => 'critical', 'detail' => 'Time-based blind injection (sleep/benchmark/pg_sleep)'],
            '/\b(?:or|and)\s+\d+\s*=\s*\d+/i'
                                        => ['severity' => 'high',     'detail' => 'Boolean-based injection'],
            "/\b(?:or|and)\s+'[^']*'\s*=\s*'[^']*/i"
                                        => ['severity' => 'high',     'detail' => 'String-based injection'],
            '/\b(?:or|and)\s+\d+\s*>\s*\d+/i'
                                        => ['severity' => 'medium',   'detail' => 'Numeric comparison injection'],
            '/--\s*$|--\+|#$/m'         => ['severity' => 'medium',   'detail' => 'SQL comment termination'],
            '/\/\*!.*?\*\//i'           => ['severity' => 'medium',   'detail' => 'MySQL special comment'],
            '/\b(?:information_schema|pg_catalog|sys\.|sqlite_master)\b/i'
                                        => ['severity' => 'high',     'detail' => 'Schema enumeration'],
            '/\b(?:load_file|into\s+(?:out|dump)file|pg_read_file)\b/i'
                                        => ['severity' => 'critical', 'detail' => 'File read/write via SQL'],
            '/\b(?:exec|xp_cmdshell|sp_executesql|execute\s+immediate)\b/i'
                                        => ['severity' => 'critical', 'detail' => 'Stored procedure execution'],
            '/\b(?:waitfor|delay)\b/i'  => ['severity' => 'critical', 'detail' => 'Time delay injection'],
            '/<>\b/i'                   => ['severity' => 'low',      'detail' => 'SQL inequality test'],
        ];
    }
}
