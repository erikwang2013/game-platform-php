<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

class CdnException extends \RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $operation,
        string $message
    ) {
        parent::__construct("[{$provider}:{$operation}] {$message}");
    }
}
