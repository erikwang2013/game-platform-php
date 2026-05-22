<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class SsiInjectionDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'ssi_injection';
    }

    protected function patterns(): array
    {
        return [
            '/<!--#exec\s+cmd=/i'       => ['severity' => 'critical', 'detail' => 'SSI exec command injection'],
            '/<!--#exec\s+cgi=/i'       => ['severity' => 'critical', 'detail' => 'SSI exec cgi injection'],
            '/<!--#include\s+(?:file|virtual)=/i'
                                                => ['severity' => 'high',     'detail' => 'SSI include directive'],
            '/<!--#echo\s+var=/i'       => ['severity' => 'medium',   'detail' => 'SSI echo variable access'],
            '/<!--#printenv\s*-->/i'    => ['severity' => 'medium',   'detail' => 'SSI printenv info disclosure'],
            '/<!--#config\s+errmsg=/i'  => ['severity' => 'medium',   'detail' => 'SSI config error manipulation'],
            '/<!--#fsize\s+(?:file|virtual)=/i'
                                                => ['severity' => 'low',      'detail' => 'SSI file size probe'],
            '/<!--#flastmod\s+(?:file|virtual)=/i'
                                                => ['severity' => 'low',      'detail' => 'SSI file last modified probe'],
            '/<!--#set\s+var=\w+\s+value=/i'
                                                => ['severity' => 'medium',   'detail' => 'SSI set variable manipulation'],
        ];
    }
}
