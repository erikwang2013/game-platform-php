<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class SstiDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'ssti';
    }

    protected function patterns(): array
    {
        return [
            '/\{\{.*?\}\}/'             => ['severity' => 'high',     'detail' => 'Jinja2/Twig SSTI ({{ }})'],
            '/\{\%.*?\%\}/'             => ['severity' => 'high',     'detail' => 'Jinja2/Twig control flow ({% %})'],
            '/\$\{.*?\}/'               => ['severity' => 'high',     'detail' => 'FreeMarker/Velocity SSTI (${ })'],
            '/\$\{[^}]*7\s*\*\s*7[^}]*\}/'
                                        => ['severity' => 'critical', 'detail' => 'SSTI test payload (7*7)'],
            '/\{\{7\s*\*\s*7\}\}/'     => ['severity' => 'critical', 'detail' => 'Twig SSTI test ({{7*7}})'],
            '/\{\{config\}\}/i'         => ['severity' => 'critical', 'detail' => 'Flask config SSTI'],
            '/\{\{(?:self|request|app|session)\./i'
                                        => ['severity' => 'critical', 'detail' => 'Flask/Jinja2 object traversal SSTI'],
            '/#\{.*?\}/'                => ['severity' => 'medium',   'detail' => 'Java EL SSTI (#{ })'],
            '/<%[=@]?\s*.*?%>/'        => ['severity' => 'medium',   'detail' => 'ERB SSTI (<% %>)'],
            '/\{\{\s*lipsum\s*\(/'     => ['severity' => 'critical', 'detail' => 'Jinja2 lipsum function SSTI'],
            '/\{\{\s*cycler\s*\(/'     => ['severity' => 'critical', 'detail' => 'Jinja2 cycler function SSTI'],
            '/\{\{\s*joiner\s*\(/'     => ['severity' => 'critical', 'detail' => 'Jinja2 joiner function SSTI'],
            '/__class__|__bases__|__subclasses__|__mro__/'
                                        => ['severity' => 'critical', 'detail' => 'Python MRO traversal SSTI'],
            '/self\._TemplateReference__context/i'
                                        => ['severity' => 'critical', 'detail' => 'Twig internal context access'],
        ];
    }
}
