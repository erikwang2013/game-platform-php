<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Encryptor;

use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * AES-256-CBC + HMAC-SHA256（encrypt-then-mac）；载荷格式：IV(16) | MAC(32) | Ciphertext。
 * 兼容旧环境；新系统优先用 AES-GCM 或 Sodium。
 */
final class OpenSslAes256CbcEncryptor implements EncryptorInterface
{
    private const IV_LEN = 16;
    private const MAC_LEN = 32;
    private const KEY_LEN = 32;
    private const PREFIX = 'v1';

    public function __construct(
        private readonly string $key,
        private readonly string $identifier = 'aes-256-cbc-hmac',
    ) {
        if (strlen($this->key) !== self::KEY_LEN) {
            throw new EncryptionException(sprintf('AES-256-CBC key must be exactly %d bytes.', self::KEY_LEN));
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(self::IV_LEN);
        $ct = openssl_encrypt($plaintext, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv);
        if ($ct === false) {
            throw new EncryptionException('AES-256-CBC encryption failed.');
        }
        $macKey = hash_hmac('sha256', $this->key, 'dgn:enc:hmac', true);
        $mac = hash_hmac('sha256', $iv . $ct, $macKey, true);
        if (strlen($mac) !== self::MAC_LEN) {
            throw new EncryptionException('HMAC generation failed.');
        }

        return self::PREFIX . $iv . $mac . $ct;
    }

    public function decrypt(string $ciphertext): string
    {
        if (!str_starts_with($ciphertext, self::PREFIX)) {
            throw new EncryptionException('Invalid ciphertext prefix for AES-256-CBC.');
        }
        $blob = substr($ciphertext, strlen(self::PREFIX));
        if (strlen($blob) < self::IV_LEN + self::MAC_LEN) {
            throw new EncryptionException('Ciphertext too short.');
        }
        $iv = substr($blob, 0, self::IV_LEN);
        $mac = substr($blob, self::IV_LEN, self::MAC_LEN);
        $ct = substr($blob, self::IV_LEN + self::MAC_LEN);
        $macKey = hash_hmac('sha256', $this->key, 'dgn:enc:hmac', true);
        $expected = hash_hmac('sha256', $iv . $ct, $macKey, true);
        if (!hash_equals($expected, $mac)) {
            throw new EncryptionException('MAC verification failed.');
        }
        $plain = openssl_decrypt($ct, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new EncryptionException('AES-256-CBC decryption failed.');
        }

        return $plain;
    }
}
