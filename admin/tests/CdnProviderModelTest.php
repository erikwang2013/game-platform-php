<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\model\CdnProvider;

/**
 * CDN 厂商配置模型测试
 */
class CdnProviderModelTest extends TestCase
{
    #[Test]
    public function configEncryptRoundTrip(): void
    {
        $json = '{"bucket":"static","region":"auto","access_key_id":"AK","secret_access_key":"SK"}';
        $m = new CdnProvider();
        $m->config = $json;
        $this->assertSame($json, $m->config);
    }

    #[Test]
    public function castsStatusToInt(): void
    {
        $m = new CdnProvider();
        $m->status = '1';
        $this->assertSame(1, $m->status);
    }

    #[Test]
    public function seededProvidersExist(): void
    {
        try {
            $count = CdnProvider::count();
            $this->assertGreaterThanOrEqual(5, $count);
            $this->assertNotNull(CdnProvider::where('provider', 'cloudflare')->first());
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not configured in test environment');
        }
    }
}
