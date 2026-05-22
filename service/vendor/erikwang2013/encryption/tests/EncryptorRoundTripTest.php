<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Encryptor\Aes256GcmEncryptor;
use Erikwang2013\Encryption\Encryptor\OpenSslAes256CbcEncryptor;
use Erikwang2013\Encryption\Encryptor\SodiumXChaCha20Encryptor;
use Erikwang2013\Encryption\EncryptionManager;
use Erikwang2013\Encryption\EncryptionManagerFactory;
use Erikwang2013\Encryption\EncryptorRegistry;
use PHPUnit\Framework\TestCase;

final class EncryptorRoundTripTest extends TestCase
{
    public function testAes256GcmRoundTrip(): void
    {
        $key = random_bytes(32);
        $e = new Aes256GcmEncryptor($key);
        $plain = 'hello 世界';
        self::assertSame($plain, $e->decrypt($e->encrypt($plain)));
    }

    public function testAes256CbcRoundTrip(): void
    {
        $key = random_bytes(32);
        $e = new OpenSslAes256CbcEncryptor($key);
        $plain = 'cbc payload';
        self::assertSame($plain, $e->decrypt($e->encrypt($plain)));
    }

    public function testSodiumRoundTripWhenAvailable(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('ext-sodium not loaded');
        }
        $key = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $e = new SodiumXChaCha20Encryptor($key);
        $plain = 'sodium test';
        self::assertSame($plain, $e->decrypt($e->encrypt($plain)));
    }

    public function testManagerUsesRegistry(): void
    {
        $key = random_bytes(32);
        $gcm = new Aes256GcmEncryptor($key);
        $registry = new EncryptorRegistry($gcm);
        $mgr = new EncryptionManager($registry, 'aes-256-gcm');
        $plain = 'mgr';
        self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain)));
    }

    public function testFactoryMasterKey(): void
    {
        $master = random_bytes(32);
        $mgr = EncryptionManagerFactory::fromMasterKey($master, 'aes-256-gcm');
        $plain = 'factory';
        $ct = $mgr->encrypt($plain);
        self::assertSame($plain, $mgr->decrypt($ct));
    }
}
