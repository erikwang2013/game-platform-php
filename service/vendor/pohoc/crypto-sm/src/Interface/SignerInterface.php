<?php

declare(strict_types=1);

namespace CryptoSm\Interface;

interface SignerInterface
{
    public static function sign(string $data, string $privateKey, mixed $options = null): string;

    public static function verify(string $data, string $signature, string $publicKey, mixed $options = null): bool;
}
