<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use app\service\VerificationService;
use support\Redis;

/**
 * VerificationService 单元测试
 * 覆盖: 邮件/SMS 验证码发送、冷却期拦截、验证通过后清除
 */
class VerificationServiceTest extends TestCase
{
    private const TEST_USER_ID = 990000401;

    protected function setUp(): void
    {
        try {
            Redis::ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        try {
            Redis::del('verify:email:' . self::TEST_USER_ID);
            Redis::del('verify:email:' . self::TEST_USER_ID . ':cooldown');
            Redis::del('verify:sms:' . self::TEST_USER_ID);
            Redis::del('verify:sms:' . self::TEST_USER_ID . ':cooldown');
        } catch (\Throwable $e) {
            // ignore
        }
    }

    #[Test]
    public function sendEmailStoresSixDigitCode(): void
    {
        $result = VerificationService::sendEmail('test@example.com', self::TEST_USER_ID);
        $this->assertTrue($result['success']);

        $code = Redis::get('verify:email:' . self::TEST_USER_ID);
        $this->assertIsString($code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotNull(Redis::get('verify:email:' . self::TEST_USER_ID . ':cooldown'));
    }

    #[Test]
    public function sendEmailRespectsCooldown(): void
    {
        Redis::setex('verify:email:' . self::TEST_USER_ID . ':cooldown', 60, '1');

        $result = VerificationService::sendEmail('test@example.com', self::TEST_USER_ID);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('60 seconds', $result['message']);
    }

    #[Test]
    public function verifyEmailAcceptsStoredCodeAndCleansUp(): void
    {
        Redis::setex('verify:email:' . self::TEST_USER_ID, 600, '123456');

        $this->assertTrue(VerificationService::verifyEmail(self::TEST_USER_ID, '123456'));
        $value = Redis::get('verify:email:' . self::TEST_USER_ID);
        $this->assertTrue($value === false || $value === null); // 兼容 predis(null)/phpredis(false)
        $cooldown = Redis::get('verify:email:' . self::TEST_USER_ID . ':cooldown');
        $this->assertTrue($cooldown === false || $cooldown === null);
    }

    #[Test]
    public function verifyEmailRejectsWrongCode(): void
    {
        Redis::setex('verify:email:' . self::TEST_USER_ID, 600, '123456');
        $this->assertFalse(VerificationService::verifyEmail(self::TEST_USER_ID, '999999'));
    }

    #[Test]
    public function sendSmsStoresCodeAndVerifies(): void
    {
        $result = VerificationService::sendSms('+85212345678', self::TEST_USER_ID);
        $this->assertTrue($result['success']);

        $code = Redis::get('verify:sms:' . self::TEST_USER_ID);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);
        $this->assertTrue(VerificationService::verifySms(self::TEST_USER_ID, (string) $code));
    }
}
