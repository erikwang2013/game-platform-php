<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class LdapInjectionDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'ldap_injection';
    }

    protected function patterns(): array
    {
        return [
            '/\((?:\||\&|!)\s*/'       => ['severity' => 'high',     'detail' => 'LDAP filter operator injection'],
            '/\*(?:\s*\))/'            => ['severity' => 'high',     'detail' => 'LDAP wildcard + closing paren'],
            '/\(\s*\*\s*=\s*[^)]+\)/'  => ['severity' => 'high',     'detail' => 'LDAP wildcard match filter'],
            '/\(\s*(?:objectClass|uid|cn|sn|mail|userPassword)\s*=/i'
                                        => ['severity' => 'high',     'detail' => 'LDAP common attribute enumeration'],
            '/\\\\[0-9a-fA-F]{2}/'     => ['severity' => 'medium',   'detail' => 'LDAP hex-encoded character escape'],
            '/\(\s*(?:objectClass|uid|cn|sn)\s*=\s*\*\s*\)/i'
                                        => ['severity' => 'critical', 'detail' => 'LDAP wildcard attribute dump'],
            '/\!\s*\(\s*uid\s*=\s*\*/i'
                                        => ['severity' => 'critical', 'detail' => 'LDAP NOT-wildcard bypass'],
            '/\(\s*\|\s*\(.*?\)/'      => ['severity' => 'medium',   'detail' => 'LDAP OR filter chain'],
            '/\(\s*&\s*\(.*?\)/'       => ['severity' => 'medium',   'detail' => 'LDAP AND filter chain'],
        ];
    }
}
