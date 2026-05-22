<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Detector;

class MailHeaderDetector extends AbstractRegexDetector
{
    public function name(): string
    {
        return 'mail_header';
    }

    protected function patterns(): array
    {
        return [
            '/%0[dD]%0[aA]/'           => ['severity' => 'critical', 'detail' => 'Email CRLF injection (encoded)'],
            "/\r\n(?i:(?:b?cc|to|from|reply-to|subject)\s*:)/"
                                        => ['severity' => 'critical', 'detail' => 'Email header injection via CRLF'],
            '/\n(?i:(?:b?cc|to|from|reply-to)\s*:)/'
                                        => ['severity' => 'critical', 'detail' => 'Email header injection via LF'],
            '/(?:^|\r?\n)Bcc\s*:\s*[^\r\n]+/im'
                                        => ['severity' => 'critical', 'detail' => 'BCC header injection'],
            '/(?:^|\r?\n)Cc\s*:\s*[^\r\n]+/im'
                                        => ['severity' => 'high',     'detail' => 'CC header injection'],
            '/(?:^|\r?\n)From\s*:\s*[^\r\n]+/im'
                                        => ['severity' => 'high',     'detail' => 'From header injection'],
            '/(?:^|\r?\n)To\s*:\s*[^\r\n]+/im'
                                        => ['severity' => 'high',     'detail' => 'To header injection'],
            '/(?:^|\r?\n)Content-Type\s*:/im'
                                        => ['severity' => 'medium',   'detail' => 'Content-Type header injection'],
            '/(?:^|\r?\n)MIME-Version\s*:/im'
                                        => ['severity' => 'medium',   'detail' => 'MIME-Version header injection'],
            '/multipart\/(?:mixed|alternative|related)/i'
                                        => ['severity' => 'medium',   'detail' => 'MIME multipart injection attempt'],
        ];
    }
}
