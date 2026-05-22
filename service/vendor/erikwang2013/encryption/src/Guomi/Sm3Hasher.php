<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Guomi;

use CryptoSm\SM3\Sm3;
use Erikwang2013\Encryption\Contract\HasherInterface;

/**
 * 国密 SM3 杂凑（依赖 pohoc/crypto-sm）。
 */
final class Sm3Hasher implements HasherInterface
{
    public function __construct(
        private readonly string $identifier = 'sm3',
    ) {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function digest(string $data): string
    {
        $hex = Sm3::hash($data);

        return hex2bin($hex);
    }

    public function digestHex(string $data): string
    {
        return Sm3::hash($data);
    }
}
