<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Hash;

use Erikwang2013\Encryption\Contract\HasherInterface;

/**
 * SHA-256 杂凑（PHP 内置 hash）。
 */
final class Sha256Hasher implements HasherInterface
{
    public function __construct(
        private readonly string $identifier = 'sha256',
    ) {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function digest(string $data): string
    {
        return hash('sha256', $data, true);
    }

    public function digestHex(string $data): string
    {
        return hash('sha256', $data, false);
    }
}
