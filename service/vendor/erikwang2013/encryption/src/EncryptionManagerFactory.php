<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption;

use Erikwang2013\Encryption\Encryptor\Aes256GcmEncryptor;
use Erikwang2013\Encryption\Encryptor\OpenSslAes256CbcEncryptor;
use Erikwang2013\Encryption\Encryptor\SodiumXChaCha20Encryptor;

/**
 * 从 32 字节主密钥快速构建常用组合（各算法独立派生子密钥，避免同一密钥跨算法复用）。
 */
final class EncryptionManagerFactory
{
    /**
     * @param string $masterKey 32 字节主密钥
     * @param string $default 默认算法标识，如 aes-256-gcm、sodium-xchacha20
     */
    public static function fromMasterKey(string $masterKey, string $default = 'aes-256-gcm'): EncryptionManager
    {
        if (strlen($masterKey) !== 32) {
            throw new \InvalidArgumentException('Master key must be exactly 32 bytes.');
        }

        $registry = new EncryptorRegistry();

        $aesKey = hash_hmac('sha256', $masterKey, 'dgn:derive:aes-gcm', true);
        $registry->register(new Aes256GcmEncryptor($aesKey));

        $cbcKey = hash_hmac('sha256', $masterKey, 'dgn:derive:aes-cbc', true);
        $registry->register(new OpenSslAes256CbcEncryptor($cbcKey));

        if (extension_loaded('sodium')) {
            $sodiumKey = hash_hmac('sha256', $masterKey, 'dgn:derive:sodium', true);
            if (strlen($sodiumKey) === SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
                $registry->register(new SodiumXChaCha20Encryptor($sodiumKey));
            }
        }

        if (!$registry->has($default)) {
            throw new \InvalidArgumentException(sprintf(
                'Default encryptor "%s" is not available (register ext-sodium for sodium-xchacha20).',
                $default
            ));
        }

        return new EncryptionManager($registry, $default);
    }
}
