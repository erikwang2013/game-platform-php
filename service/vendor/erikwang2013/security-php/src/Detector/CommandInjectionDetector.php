<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class CommandInjectionDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'command_injection';
    }

    protected function patterns(): array
    {
        return [
            '/`[^`]+`/'                 => ['severity' => 'critical', 'detail' => 'Backtick command substitution'],
            '/\$\([^)]+\)/'             => ['severity' => 'critical', 'detail' => 'Dollar-parenthesis command substitution'],
            '/;\s*(?:wget|curl|fetch|lynx)\b/i'
                                        => ['severity' => 'high',     'detail' => 'Download command after semicolon'],
            '/\|\s*(?:nc|netcat|ncat)\b/i'
                                        => ['severity' => 'high',     'detail' => 'Netcat pipe injection'],
            '/\b(?:wget|curl)\s+http/i' => ['severity' => 'high',     'detail' => 'Remote resource download'],
            '/\/dev\/tcp\//i'           => ['severity' => 'critical', 'detail' => 'Bash TCP reverse shell device'],
            '/\/dev\/udp\//i'           => ['severity' => 'critical', 'detail' => 'Bash UDP reverse shell device'],
            '/>\s*\/dev\/null/i'        => ['severity' => 'low',      'detail' => 'Output redirection to /dev/null'],
            '/\b(?:system|exec|passthru|shell_exec|popen|proc_open|pcntl_exec)\s*\(/i'
                                        => ['severity' => 'critical', 'detail' => 'PHP code execution function'],
            '/\|\s*\|\s*/'              => ['severity' => 'medium',   'detail' => 'OR operator chain'],
            '/&&\s*(?:wget|curl|nc|bash|sh|python|perl|ruby|php)/i'
                                        => ['severity' => 'high',     'detail' => 'Chained command execution'],
            '/;\s*(?:bash|sh|python|perl|ruby|php)\b/i'
                                        => ['severity' => 'high',     'detail' => 'Interpreter execution after semicolon'],
        ];
    }
}
