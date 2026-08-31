<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\risk\evaluators;

use app\service\risk\RiskEvaluator;

/**
 * IP 黑名单检测（旧 switch case 迁移，行为不变）
 *
 * 硬规则：命中即 block（RiskService fail-closed）。
 */
class IpBlacklistEvaluator implements RiskEvaluator
{
    public function type(): string
    {
        return 'ip_blacklist';
    }

    public function evaluate(int $userId, string $checkType, array $context, array $config): array
    {
        $ip = (string) ($context['ip'] ?? '');
        $blacklist = $config['blacklist'] ?? [];

        if ($ip !== '' && in_array($ip, $blacklist, true)) {
            return ['matched' => true, 'message' => "IP {$ip} 命中黑名单", 'severity' => 'high'];
        }

        return ['matched' => false, 'message' => 'IP 未命中黑名单', 'severity' => 'low'];
    }
}
