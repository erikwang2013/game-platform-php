<?php

declare(strict_types=1);

namespace CryptoSm\Interface;

interface CipherInterface
{
    public static function encrypt(string $data, string $key, mixed $options = null): string;

    public static function decrypt(string $data, string $key, mixed $options = null): string;
}
