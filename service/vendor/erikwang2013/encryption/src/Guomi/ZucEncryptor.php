<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Guomi;

use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;
use Erikwang2013\Encryption\Guomi\Internal\ZucEngine;

/**
 * ZUC-128 流密码 + HMAC-SHA256（encrypt-then-mac）：载荷格式 v1 | IV(16) | MAC(32) | 密文（与密钥流 XOR）。
 * 密钥长度 16 字节；IV 每次加密随机生成并随密文携带。
 */
final class ZucEncryptor implements EncryptorInterface
{
    private const PREFIX = 'v1';
    private const MAC_LEN = 32;
    private const IV_LEN = 16;

    public function __construct(
        private readonly string $key,
        private readonly string $identifier = 'zuc-128',
    ) {
        if (strlen($this->key) !== 16) {
            throw new EncryptionException('ZUC key must be exactly 16 bytes.');
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(self::IV_LEN);
        $ct = $this->xorKeystream($this->key, $iv, $plaintext);

        $macKey = hash_hmac('sha256', $this->key, 'dgn:enc:hmac', true);
        $mac = hash_hmac('sha256', $iv . $ct, $macKey, true);
        if (strlen($mac) !== self::MAC_LEN) {
            throw new EncryptionException('ZUC HMAC generation failed.');
        }

        return self::PREFIX . $iv . $mac . $ct;
    }

    public function decrypt(string $ciphertext): string
    {
        if (!str_starts_with($ciphertext, self::PREFIX)) {
            throw new EncryptionException('Invalid ZUC ciphertext prefix.');
        }
        $blob = substr($ciphertext, strlen(self::PREFIX));
        if (strlen($blob) < self::IV_LEN + self::MAC_LEN) {
            throw new EncryptionException('ZUC ciphertext too short.');
        }
        $iv = substr($blob, 0, self::IV_LEN);
        $mac = substr($blob, self::IV_LEN, self::MAC_LEN);
        $ct = substr($blob, self::IV_LEN + self::MAC_LEN);

        $macKey = hash_hmac('sha256', $this->key, 'dgn:enc:hmac', true);
        $expected = hash_hmac('sha256', $iv . $ct, $macKey, true);
        if (!hash_equals($expected, $mac)) {
            throw new EncryptionException('ZUC MAC verification failed.');
        }

        return $this->xorKeystream($this->key, $iv, $ct);
    }

    private function xorKeystream(string $key, string $iv, string $data): string
    {
        $engine = new ZucEngine($key, $iv);
        $out = '';
        $len = strlen($data);
        $i = 0;
        while ($i < $len) {
            $word = $engine->nextKey();
            for ($j = 0; $j < 4 && $i < $len; $j++, $i++) {
                $byte = ($word >> (8 * (3 - $j))) & 0xff;
                $out .= chr(ord($data[$i]) ^ $byte);
            }
        }

        return $out;
    }
}
