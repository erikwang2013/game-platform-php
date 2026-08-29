<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\cdn;

use app\model\CdnProvider;

/**
 * CDN 配置服务：从 game_cdn_provider 表读取启用厂商配置
 */
class CdnService
{
    public function provider(string $provider): CdnProviderInterface
    {
        $row = CdnProvider::where('provider', $provider)->where('status', 1)->first();
        if (!$row) {
            throw new CdnException($provider, 'resolve', "厂商未启用或未配置: {$provider}");
        }

        $config = is_string($row->config)
            ? (json_decode($row->config, true) ?: [])
            : (array) $row->config;

        // DB 存单厂商配置，工厂需要完整 providers 结构，包一层适配
        return CdnFactory::resolve($provider, ['providers' => [$provider => $config]]);
    }
}
