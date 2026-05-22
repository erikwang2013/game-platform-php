<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use app\common\EncryptionService;

class EncryptionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = \Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }
    }

    #[Test]
    public function encrypt_empty_string(): void
    {
        $result = EncryptionService::encrypt('');
        $this->assertEquals('', $result);
    }

    #[Test]
    public function decrypt_empty_string(): void
    {
        $result = EncryptionService::decrypt('');
        $this->assertEquals('', $result);
    }

    #[Test]
    public function encrypt_decrypt_roundtrip(): void
    {
        $tests = ['13812345678', 'test@erik.xyz', 'Hello World'];
        foreach ($tests as $plain) {
            $encrypted = EncryptionService::encrypt($plain);
            $this->assertNotEquals($plain, $encrypted, "加密后应与原文不同: $plain");
            $decrypted = EncryptionService::decrypt($encrypted);
            $this->assertEquals($plain, $decrypted, "解密后应还原: $plain");
        }
    }

    #[Test]
    public function encrypt_same_plaintext_different_ciphertext(): void
    {
        $c1 = EncryptionService::encrypt('same-text');
        $c2 = EncryptionService::encrypt('same-text');
        // AES-CBC 随机 IV 导致不同密文
        $this->assertNotEquals($c1, $c2);
    }

    #[Test]
    public function maskPhone_format(): void
    {
        $result = EncryptionService::maskPhone('13812345678');
        $this->assertEquals('138****5678', $result);
    }

    #[Test]
    public function maskPhone_short_number(): void
    {
        $result = EncryptionService::maskPhone('12345');
        $this->assertEquals('12345', $result);
    }

    #[Test]
    public function maskEmail_format(): void
    {
        $result = EncryptionService::maskEmail('hello@erik.xyz');
        $this->assertEquals('h***@erik.xyz', $result);
    }

    #[Test]
    public function maskEmail_short_name(): void
    {
        $result = EncryptionService::maskEmail('a@erik.xyz');
        $this->assertEquals('a**@erik.xyz', $result);
    }
}
