<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class JndiInjectionDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'jndi_injection';
    }

    protected function patterns(): array
    {
        return [
            '/\$\{jndi:(?:ldap|ldaps|rmi|dns|iiop|corba|nis|nds|http)s?:/i'
                                                => ['severity' => 'critical', 'detail' => 'Log4Shell JNDI injection'],
            '/\$\{(?:lower|upper)\s*:\s*j/i'
                                                => ['severity' => 'critical', 'detail' => 'Log4j lower/upper obfuscation'],
            '/\$\{::-j\}/i'             => ['severity' => 'critical', 'detail' => 'Log4j empty-lookup obfuscation'],
            '/\$\{env:[^}]*\}/i'        => ['severity' => 'high',     'detail' => 'Log4j environment variable lookup'],
            '/\$\{sys:[^}]*\}/i'        => ['severity' => 'high',     'detail' => 'Log4j system property lookup'],
            '/\$\{java:[^}]*\}/i'       => ['severity' => 'high',     'detail' => 'Log4j JVM property lookup'],
            '/\$\{(?:date|ctx|main|marker|log4j|spring|sd):/i'
                                                => ['severity' => 'medium',   'detail' => 'Log4j lookup pattern'],
            '/\$\{[^}]*:\/\/[^}]*\}/'  => ['severity' => 'critical', 'detail' => 'JNDI remote resource lookup'],
            '/ldaps?:\/\/[^\/]+\./'    => ['severity' => 'high',     'detail' => 'Raw LDAP URL (JNDI indicator)'],
            '/rmi:\/\/[^\/]+\./i'      => ['severity' => 'high',     'detail' => 'Raw RMI URL (JNDI indicator)'],
        ];
    }
}
