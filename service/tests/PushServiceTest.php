<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use app\service\PushService;

/**
 * PushService 单元测试
 * 覆盖: base64url 编码、无设备 token 时静默返回
 */
class PushServiceTest extends TestCase
{
    #[Test]
    public function base64urlEncodeProducesUrlSafeOutput(): void
    {
        // 输入含 +/ 的原始字节，验证 URL-safe 编码
        $raw = "Hello+\xfa/";
        $encoded = self::base64urlEncode($raw);
        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);

        // 与标准 base64 内容一致（去掉填充并替换符号）
        $expected = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
        $this->assertSame($expected, $encoded);
    }

    #[Test]
    public function sendWithNoDeviceTokensDoesNotThrow(): void
    {
        // 无 token 用户：不应抛异常（查询失败也应静默）
        PushService::send(990000501, 'title', 'body');
        $this->assertTrue(true);
    }

    #[Test]
    public function sendWithDataDoesNotThrow(): void
    {
        PushService::send(990000501, 'title', 'body', ['k' => 'v']);
        $this->assertTrue(true);
    }

    private static function base64urlEncode(string $data): string
    {
        $method = new \ReflectionMethod(PushService::class, 'base64urlEncode');
        $method->setAccessible(true);
        return $method->invoke(null, $data);
    }
}
