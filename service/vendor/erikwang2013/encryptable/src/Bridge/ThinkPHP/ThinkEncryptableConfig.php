<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Bridge\ThinkPHP;

use Erikwang2013\Encryptable\Contracts\EncryptableConfigContract;
use Erikwang2013\Encryptable\Support\PreviousKeysParser;
use think\facade\Config;

class ThinkEncryptableConfig implements EncryptableConfigContract
{
    public function getKey(): ?string
    {
        $key = Config::get('encryptable.key');

        if ($key === null || $key === '') {
            return null;
        }

        return (string) $key;
    }

    public function getCipher(): ?string
    {
        $cipher = Config::get('encryptable.cipher', 'aes-256-gcm');

        if ($cipher === null || $cipher === '') {
            return null;
        }

        return (string) $cipher;
    }

    public function getPreviousKeys(): array
    {
        return PreviousKeysParser::parse(Config::get('encryptable.previous_keys', []));
    }
}
