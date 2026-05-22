<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Config;

use Erikwang2013\Encryptable\Contracts\EncryptableConfigContract;
use Erikwang2013\Encryptable\Support\PreviousKeysParser;

class EnvEncryptableConfig implements EncryptableConfigContract
{
    public function getKey(): ?string
    {
        $value = $_ENV['ENCRYPTION_KEY'] ?? $_SERVER['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY');

        if ($value === false || $value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function getCipher(): ?string
    {
        $value = $_ENV['ENCRYPTION_CIPHER'] ?? $_SERVER['ENCRYPTION_CIPHER'] ?? getenv('ENCRYPTION_CIPHER');

        if ($value === false || $value === null || $value === '') {
            return 'aes-256-gcm';
        }

        return (string) $value;
    }

    public function getPreviousKeys(): array
    {
        $raw = $_ENV['ENCRYPTION_PREVIOUS_KEYS'] ?? $_SERVER['ENCRYPTION_PREVIOUS_KEYS'] ?? getenv('ENCRYPTION_PREVIOUS_KEYS');

        if ($raw === false) {
            $raw = null;
        }

        return PreviousKeysParser::parse($raw);
    }
}
