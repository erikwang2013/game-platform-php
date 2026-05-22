<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Encryptor;

use Erikwang2013\Encryption\Contract\EncryptorInterface;
use Erikwang2013\Encryption\Exception\EncryptionException;

/**
 * libsodium XChaCha20-Poly1305 IETF；载荷格式：Nonce(24) | Ciphertext。
 * 需 ext-sodium。
 */
final class SodiumXChaCha20Encryptor implements EncryptorInterface
{
    private const PREFIX = 'v1';

    public function __construct(
        private readonly string $key,
        private readonly string $identifier = 'sodium-xchacha20',
    ) {
        if (!extension_loaded('sodium')) {
            throw new EncryptionException('ext-sodium is required for SodiumXChaCha20Encryptor.');
        }
        $len = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;
        if (strlen($this->key) !== $len) {
            throw new EncryptionException(sprintf('XChaCha20 key must be exactly %d bytes.', $len));
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ad = '';
        $ct = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $ad,
            $nonce,
            $this->key
        );
        if ($ct === false) {
            throw new EncryptionException('XChaCha20-Poly1305 encryption failed.');
        }

        return self::PREFIX . $nonce . $ct;
    }

    public function decrypt(string $ciphertext): string
    {
        if (!str_starts_with($ciphertext, self::PREFIX)) {
            throw new EncryptionException('Invalid ciphertext prefix for XChaCha20.');
        }
        $blob = substr($ciphertext, strlen(self::PREFIX));
        $n = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($blob) < $n + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new EncryptionException('Ciphertext too short.');
        }
        $nonce = substr($blob, 0, $n);
        $ct = substr($blob, $n);
        $ad = '';
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ct,
            $ad,
            $nonce,
            $this->key
        );
        if ($plain === false) {
            throw new EncryptionException('XChaCha20-Poly1305 decryption failed.');
        }

        return $plain;
    }
}
