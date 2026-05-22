<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Asymmetric\Sm2AsymmetricCipher;
use Erikwang2013\Encryption\AsymmetricCipherRegistry;
use Erikwang2013\Encryption\AsymmetricCryptoManager;
use Erikwang2013\Encryption\Guomi\Sm2EncryptionService;
use Erikwang2013\Encryption\Hash\Sha256Hasher;
use Erikwang2013\Encryption\HashingManager;
use Erikwang2013\Encryption\HasherRegistry;
use Erikwang2013\Encryption\Kdf\HkdfSha256;
use Erikwang2013\Encryption\Kdf\Pbkdf2Sha256;
use Erikwang2013\Encryption\KeyDerivationManager;
use Erikwang2013\Encryption\KeyDerivationRegistry;
use Erikwang2013\Encryption\PasswordBasedKdfManager;
use Erikwang2013\Encryption\PasswordBasedKdfRegistry;
use PHPUnit\Framework\TestCase;

final class CryptoPrimitivesTest extends TestCase
{
    public function testSha256Hasher(): void
    {
        $h = new Sha256Hasher();
        self::assertSame(32, strlen($h->digest('x')));
        self::assertSame(64, strlen($h->digestHex('x')));
    }

    public function testHashingManager(): void
    {
        $mgr = new HashingManager(new HasherRegistry(new Sha256Hasher()), 'sha256');
        $d = $mgr->digestHex('abc');
        self::assertSame(64, strlen($d));
    }

    public function testHkdf(): void
    {
        $kdf = new HkdfSha256();
        $ikm = random_bytes(32);
        $salt = random_bytes(16);
        $key = $kdf->derive($ikm, $salt, 32, 'ctx');
        self::assertSame(32, strlen($key));
        $mgr = new KeyDerivationManager(new KeyDerivationRegistry($kdf), 'hkdf-sha256');
        self::assertSame(16, strlen($mgr->derive($ikm, $salt, 16, 'ctx')));
    }

    public function testPbkdf2(): void
    {
        $kdf = new Pbkdf2Sha256(1000);
        $out = $kdf->deriveFromPassword('secret', random_bytes(16), 32);
        self::assertSame(32, strlen($out));
        $mgr = new PasswordBasedKdfManager(new PasswordBasedKdfRegistry($kdf), 'pbkdf2-sha256');
        self::assertSame(32, strlen($mgr->deriveFromPassword('secret', random_bytes(16), 32)));
    }

    public function testSm2AsymmetricRoundTripWhenGmpAvailable(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp not loaded');
        }
        $pair = Sm2EncryptionService::generateKeyPairHex();
        $cipher = new Sm2AsymmetricCipher();
        $plain = 'asymmetric-sm2';
        $ct = $cipher->encrypt($plain, $pair->getPublicKey());
        self::assertSame($plain, $cipher->decrypt($ct, $pair->getPrivateKey()));

        $mgr = new AsymmetricCryptoManager(new AsymmetricCipherRegistry($cipher), 'sm2');
        self::assertSame($plain, $mgr->decrypt($mgr->encrypt($plain, $pair->getPublicKey()), $pair->getPrivateKey()));
    }
}
