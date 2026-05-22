<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Encryptor;

use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * AES-256-GCM（OpenSSL），认证加密；载荷格式：IV(12) | Tag(16) | Ciphertext。
 */
final class Aes256GcmEncryptor implements EncryptorInterface
{
    private const IV_LEN = 12;
    private const TAG_LEN = 16;
    private const KEY_LEN = 32;
    private const PREFIX = 'v1';

    public function __construct(
        private readonly string $key,
        private readonly string $identifier = 'aes-256-gcm',
    ) {
        if (strlen($this->key) !== self::KEY_LEN) {
            throw new EncryptionException(sprintf('AES-256-GCM key must be exactly %d bytes.', self::KEY_LEN));
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(self::IV_LEN);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN
        );
        if ($ciphertext === false || strlen($tag) !== self::TAG_LEN) {
            throw new EncryptionException('AES-256-GCM encryption failed.');
        }

        return self::PREFIX . $iv . $tag . $ciphertext;
    }

    public function decrypt(string $ciphertext): string
    {
        if (!str_starts_with($ciphertext, self::PREFIX)) {
            throw new EncryptionException('Invalid ciphertext prefix for AES-256-GCM.');
        }
        $blob = substr($ciphertext, strlen(self::PREFIX));
        if (strlen($blob) < self::IV_LEN + self::TAG_LEN) {
            throw new EncryptionException('Ciphertext too short.');
        }
        $iv = substr($blob, 0, self::IV_LEN);
        $tag = substr($blob, self::IV_LEN, self::TAG_LEN);
        $ct = substr($blob, self::IV_LEN + self::TAG_LEN);
        $plain = openssl_decrypt(
            $ct,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            ''
        );
        if ($plain === false) {
            throw new EncryptionException('AES-256-GCM decryption failed.');
        }

        return $plain;
    }
}
