<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable;

use Erikwang2013\Encryptable\Exceptions\DecryptException;
use Erikwang2013\Encryptable\Exceptions\EncryptException;
use Erikwang2013\Encryptable\Utils\Serializer;

class PHPEncrypter extends Encrypter
{
    private const FORMAT_V1 = "\x01";
    private const HMAC_ALGO = 'sha256';
    private const HMAC_LENGTH = 32;

    public function encrypt(mixed $value, bool $serialize = true): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if ($this->isEncrypted($value)) {
            return $value;
        }

        if ($serialize) {
            $value = Serializer::serialize($value);
        }

        $value = $this->addDirtyBit($value);

        $cipher = $this->getEncryptionCipher();
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = $ivLength > 0 ? random_bytes($ivLength) : '';

        $ciphertext = openssl_encrypt(
            $value,
            $cipher,
            $this->getEncryptionKey(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($ciphertext === false) {
            throw new EncryptException(
                'OpenSSL encryption failed: ' . (openssl_error_string() ?: 'unknown error')
            );
        }

        $hmac = hash_hmac(self::HMAC_ALGO, $iv . $ciphertext, $this->getHmacKey(), true);

        return $this->base64Encode(self::FORMAT_V1 . $iv . $ciphertext . $hmac);
    }

    public function decrypt(?string $payload, bool $unserialize = true): mixed
    {
        if (is_null($payload)) {
            return null;
        }

        if (! is_string($payload)) {
            return $payload;
        }

        try {
            $decoded = $this->base64Decode($payload);
        } catch (DecryptException) {
            return $payload;
        }

        $plain = $this->tryOpenSSLDecrypt($decoded);

        if ($plain === null) {
            return $payload;
        }

        $plain = $this->removeDirtyBit($plain);

        if ($unserialize) {
            $plain = Serializer::unserialize($plain);
        }

        return $plain;
    }

    /**
     * Decrypt with any key in the ring, then encrypt with the current primary key (for gradual re-encryption).
     */
    public function rotateToCurrentKey(?string $payload, bool $serialize = true): ?string
    {
        if ($payload === null) {
            return null;
        }

        if (! $this->isEncrypted($payload)) {
            return $payload;
        }

        $plain = $this->decrypt($payload, $serialize);

        return $this->encrypt($plain, $serialize);
    }

    public function isEncrypted(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        try {
            $decoded = $this->base64Decode($value);
        } catch (DecryptException) {
            return false;
        }

        return $this->tryOpenSSLDecrypt($decoded) !== null;
    }

    /**
     * Attempt decryption with all keys in the ring. Returns plaintext on success, null on failure.
     */
    private function tryOpenSSLDecrypt(string $decoded): ?string
    {
        $cipher = $this->getEncryptionCipher();

        if (str_starts_with($decoded, self::FORMAT_V1)) {
            return $this->tryDecryptV1($decoded, $cipher);
        }

        return $this->tryDecryptV0($decoded, $cipher);
    }

    private function tryDecryptV1(string $data, string $cipher): ?string
    {
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = $ivLength > 0 ? substr($data, 1, $ivLength) : '';
        $hmac = substr($data, -self::HMAC_LENGTH);
        $ciphertext = substr($data, 1 + $ivLength, -self::HMAC_LENGTH);

        foreach ($this->getDecryptionKeyRing() as $key) {
            $expectedHmac = hash_hmac(self::HMAC_ALGO, $iv . $ciphertext, self::deriveHmacKey($key), true);
            if (! hash_equals($expectedHmac, $hmac)) {
                continue;
            }

            $plain = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
            if ($plain !== false && str_starts_with($plain, self::DIRTY_BIT_KEY)) {
                return $plain;
            }
        }

        return null;
    }

    private function tryDecryptV0(string $data, string $cipher): ?string
    {
        foreach ($this->getDecryptionKeyRing() as $key) {
            $plain = openssl_decrypt($data, $cipher, $key, OPENSSL_RAW_DATA);
            if ($plain !== false && str_starts_with($plain, self::DIRTY_BIT_KEY)) {
                return $plain;
            }
        }

        return null;
    }

    protected function addDirtyBit(string $value): string
    {
        $prefix = self::DIRTY_BIT_KEY;

        if (! str_starts_with($value, $prefix)) {
            return $prefix . $value;
        }

        return $value;
    }

    protected function base64Encode(string $value): string
    {
        $encoded = base64_encode($value);

        if ($encoded === false) {
            throw new EncryptException('Base64 encoding failed.');
        }

        return $encoded;
    }

    protected function base64Decode(string $payload): string
    {
        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            throw new DecryptException('Base64 decoding failed.');
        }

        return $decoded;
    }

    protected function removeDirtyBit(string $payload): string
    {
        if (! str_starts_with($payload, self::DIRTY_BIT_KEY)) {
            throw new DecryptException('Decryption failed: missing integrity marker.');
        }

        return substr($payload, strlen(self::DIRTY_BIT_KEY));
    }

    private function getHmacKey(): string
    {
        return self::deriveHmacKey($this->getEncryptionKey());
    }

    private static function deriveHmacKey(string $encryptionKey): string
    {
        return hash('sha256', $encryptionKey . ':hmac', true);
    }
}
