<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Kdf;

use Erikwang2013\Encryption\Contract\PasswordBasedKdfInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * PBKDF2-HMAC-SHA256，迭代次数可在构造时固定（默认参考 OWASP 建议量级，请按环境调整）。
 */
final class Pbkdf2Sha256 implements PasswordBasedKdfInterface
{
    public function __construct(
        private readonly int $iterations = 310_000,
        private readonly string $identifier = 'pbkdf2-sha256',
    ) {
        if ($this->iterations < 1) {
            throw new EncryptionException('PBKDF2 iterations must be positive.');
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function deriveFromPassword(string $password, string $salt, int $length): string
    {
        if ($length < 1) {
            throw new EncryptionException('PBKDF2 output length must be positive.');
        }
        if ($salt === '') {
            throw new EncryptionException('PBKDF2 salt must not be empty.');
        }

        return hash_pbkdf2('sha256', $password, $salt, $this->iterations, $length, true);
    }
}
