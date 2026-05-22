<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Bridge\Laravel;

use Erikwang2013\Encryptable\Contracts\EncryptableConfigContract;
use Erikwang2013\Encryptable\Support\PreviousKeysParser;

class IlluminateEncryptableConfig implements EncryptableConfigContract
{
    public function getKey(): ?string
    {
        $key = config('encryptable.key');

        if ($key === null || $key === '') {
            return null;
        }

        return (string) $key;
    }

    public function getCipher(): ?string
    {
        $cipher = config('encryptable.cipher', 'aes-256-gcm');

        if ($cipher === null || $cipher === '') {
            return null;
        }

        return (string) $cipher;
    }

    public function getPreviousKeys(): array
    {
        return PreviousKeysParser::parse(config('encryptable.previous_keys', []));
    }
}
