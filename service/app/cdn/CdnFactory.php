<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

class CdnFactory
{
    /**
     * @param array{default: string, providers: array<string, array>} $config
     */
    public static function resolve(string $provider, array $config): CdnProviderInterface
    {
        $providers = $config['providers'] ?? [];
        if (!isset($providers[$provider])) {
            throw new \InvalidArgumentException("Unsupported CDN provider: {$provider}");
        }
        return match ($provider) {
            'cloudflare' => new CloudflareProvider($providers['cloudflare']),
            'cloudfront' => new CloudFrontProvider($providers['cloudfront']),
            'aliyun'     => new AliyunProvider($providers['aliyun']),
            'tencent'    => new TencentProvider($providers['tencent']),
            'huawei'     => new HuaweiProvider($providers['huawei']),
        };
    }
}
