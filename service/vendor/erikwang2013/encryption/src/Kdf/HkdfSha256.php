<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Kdf;

use Erikwang2013\Encryption\Contract\KeyDerivationInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * HKDF-SHA256（RFC 5869），使用 PHP {@see hash_hkdf}。
 */
final class HkdfSha256 implements KeyDerivationInterface
{
    public function __construct(
        private readonly string $identifier = 'hkdf-sha256',
    ) {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function derive(string $ikm, string $salt, int $length, string $info = ''): string
    {
        if ($length < 1) {
            throw new EncryptionException('HKDF output length must be positive.');
        }
        $out = @hash_hkdf('sha256', $ikm, $length, $info, $salt !== '' ? $salt : null);
        if ($out === false) {
            throw new EncryptionException('HKDF failed.');
        }

        return $out;
    }
}
