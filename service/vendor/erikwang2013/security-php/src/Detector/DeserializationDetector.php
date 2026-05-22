<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class DeserializationDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'deserialization';
    }

    protected function patterns(): array
    {
        return [
            '/O:\d+:"[^"]+"/'          => ['severity' => 'critical', 'detail' => 'PHP object serialization injection'],
            '/C:\d+:"[^"]+"/'          => ['severity' => 'critical', 'detail' => 'PHP custom object serialization'],
            '/a:\d+:\{/'               => ['severity' => 'high',     'detail' => 'Serialized array structure'],
            '/s:\d+:"[^"]*"/'          => ['severity' => 'medium',   'detail' => 'Serialized string structure'],
            '/O:\d+:"[^"]+":\d+:\{/'   => ['severity' => 'critical', 'detail' => 'Complete serialized object'],
            '/__wakeup|__destruct|__toString|__construct/'
                                        => ['severity' => 'high',     'detail' => 'PHP magic method reference'],
            '/Spl(?:ObjectStorage|Queue|Stack|Heap)/'
                                        => ['severity' => 'medium',   'detail' => 'SPL object in serialized data'],
            '/base64_decode\s*\([^)]+\)/'  => ['severity' => 'medium',   'detail' => 'Base64 decode call (may hide serialized payload)'],
            '/unserialize\s*\(/'       => ['severity' => 'critical', 'detail' => 'PHP unserialize function call'],
        ];
    }
}
