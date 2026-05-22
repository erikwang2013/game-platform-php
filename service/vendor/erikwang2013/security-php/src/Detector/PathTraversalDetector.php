<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class PathTraversalDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'path_traversal';
    }

    protected function patterns(): array
    {
        return [
            '/\.\.\//'                  => ['severity' => 'high',     'detail' => 'Directory traversal ../'],
            '/\.\.\\\\/'                => ['severity' => 'high',     'detail' => 'Directory traversal ..\\'],
            '/%2e%2e%2f/i'             => ['severity' => 'high',     'detail' => 'URL-encoded traversal %2e%2e%2f'],
            '/%2e%2e%5c/i'             => ['severity' => 'high',     'detail' => 'URL-encoded traversal %2e%2e%5c'],
            '/\/etc\/(?:passwd|shadow|hosts|group)\b/i'
                                        => ['severity' => 'critical', 'detail' => 'Linux system file access'],
            '/C:\\\\Windows\\\\(?:System32|win\.ini)\b/i'
                                        => ['severity' => 'critical', 'detail' => 'Windows system file access'],
            '/php:\/\/filter/i'         => ['severity' => 'critical', 'detail' => 'PHP filter wrapper'],
            '/php:\/\/input/i'          => ['severity' => 'critical', 'detail' => 'PHP input wrapper'],
            '/data:\/\/text/i'          => ['severity' => 'high',     'detail' => 'Data URI injection'],
            '/expect:\/\//i'            => ['severity' => 'critical', 'detail' => 'Expect wrapper command execution'],
            '/phar:\/\//i'              => ['severity' => 'high',     'detail' => 'Phar wrapper deserialization'],
            '/(?:%00|\x00)/'            => ['severity' => 'high',     'detail' => 'Null byte injection'],
            '/file:\/\//i'              => ['severity' => 'high',     'detail' => 'File URI scheme'],
        ];
    }
}
