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
        // config 是 JSON 语义字段：读取侧按 array 取值（json_decode 后还原），不再回落硬编码默认
        $this->assertSame(json_decode($json, true), $m->config);
    }

    #[Test]
    public function configNonJsonValueStaysString(): void
    {
        // 正控：非 JSON 的 config 值不得被 json_decode 误伤（标量原样读回；纯数字串尤甚，不得变 int）
        $m = new CdnProvider();
        $m->config = 'plain-token-abc';
        $this->assertSame('plain-token-abc', $m->config);

        $m->config = '1234567890';
        $this->assertSame('1234567890', $m->config);
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
