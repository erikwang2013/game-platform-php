<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\cdn\CdnFactory;
use app\cdn\CdnProviderInterface;
use PHPUnit\Framework\TestCase;

class CdnFactoryTest extends TestCase
{
    // 与 game_cdn_provider 种子数据同构的 fixture（config/cdn.php 已删除）
    private const CONFIG = [
        'default' => 'cloudflare',
        'providers' => [
            'cloudflare' => ['bucket' => 'static', 'domain' => 'cdn.example.com', 'account_id' => '', 'api_token' => '', 'zone_id' => '', 's3' => ['region' => 'auto', 'access_key_id' => '', 'secret_access_key' => '']],
            'cloudfront' => ['bucket' => 'static', 'domain' => 'd111111abcdef8.cloudfront.net', 'distribution_id' => '', 's3' => ['region' => 'us-east-1', 'access_key_id' => '', 'secret_access_key' => '']],
            'aliyun'     => ['bucket' => 'static', 'domain' => 'cdn.aliyun.example.com', 'access_key_id' => '', 'access_key_secret' => '', 'region' => 'oss-cn-hangzhou'],
            'tencent'    => ['bucket' => 'static', 'domain' => 'cdn.tencent.example.com', 'secret_id' => '', 'secret_key' => '', 'region' => 'ap-guangzhou'],
            'huawei'     => ['bucket' => 'static', 'domain' => 'cdn.huawei.example.com', 'ak' => '', 'sk' => '', 'region' => 'cn-north-4'],
        ],
    ];

    public function testResolveAllProviders(): void
    {
        foreach (array_keys(self::CONFIG['providers']) as $provider) {
            $resolved = CdnFactory::resolve($provider, self::CONFIG);
            $this->assertInstanceOf(CdnProviderInterface::class, $resolved, "provider {$provider}");
        }
    }

    public function testUnknownProviderThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CdnFactory::resolve('akamai', self::CONFIG);
    }
}
