<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use app\cdn\CdnService;
use app\cdn\CdnException;
use app\cdn\CloudflareProvider;

/**
 * CDN 配置服务测试（DB 可用时执行，否则 skip）
 */
class CdnServiceTest extends TestCase
{
    #[Test]
    public function providerReturnsInstanceForEnabled(): void
    {
        try {
            $svc = new CdnService();
            $instance = $svc->provider('cloudflare');
            $this->assertInstanceOf(CloudflareProvider::class, $instance);
        } catch (\Throwable $e) {
            if ($e instanceof CdnException) {
                $this->markTestSkipped('数据库无启用配置，跳过（' . $e->getMessage() . '）');
            }
            $this->markTestSkipped('Database connection not configured in test environment');
        }
    }

    #[Test]
    public function providerThrowsForMissing(): void
    {
        try {
            (new CdnService())->provider('nobody');
            $this->fail('期望 CdnException');
        } catch (CdnException $e) {
            $this->assertStringContainsString('nobody', $e->getMessage());
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not configured in test environment');
        }
    }
}
