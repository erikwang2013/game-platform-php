<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Tests;

use Erikwang2013\Encryption\Guomi\Sm2EncryptionService;
use Erikwang2013\Encryption\Guomi\Sm3Hasher;
use Erikwang2013\Encryption\Guomi\Sm4CbcEncryptor;
use Erikwang2013\Encryption\Guomi\UnavailableNationalAlgorithms;
use Erikwang2013\Encryption\Guomi\ZucEncryptor;
use Erikwang2013\Encryption\Exception\UnsupportedNationalAlgorithmException;
use PHPUnit\Framework\TestCase;

final class GuomiAlgorithmsTest extends TestCase
{
    public function testSm3DigestLength(): void
    {
        $h = new Sm3Hasher();
        self::assertSame(32, strlen($h->digest('abc')));
        self::assertSame(64, strlen($h->digestHex('abc')));
    }

    public function testSm4CbcRoundTrip(): void
    {
        $key = random_bytes(16);
        $e = new Sm4CbcEncryptor($key);
        $plain = '国密 SM4';
        self::assertSame($plain, $e->decrypt($e->encrypt($plain)));
    }

    public function testZucRoundTrip(): void
    {
        $key = random_bytes(16);
        $z = new ZucEncryptor($key);
        $plain = 'ZUC stream';
        self::assertSame($plain, $z->decrypt($z->encrypt($plain)));
    }

    public function testSm2RoundTripWhenGmpAvailable(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('ext-gmp not loaded');
        }
        $kp = Sm2EncryptionService::generateKeyPairHex();
        $plain = 'sm2';
        $ct = Sm2EncryptionService::encrypt($plain, $kp->getPublicKey());
        self::assertSame($plain, Sm2EncryptionService::decrypt($ct, $kp->getPrivateKey()));
    }

    public function testUnavailableAlgorithmsThrow(): void
    {
        $this->expectException(UnsupportedNationalAlgorithmException::class);
        UnavailableNationalAlgorithms::sm1();
    }
}
