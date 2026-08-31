<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\risk\evaluators;

use app\model\RiskLog;
use app\service\risk\RiskEvaluator;

/**
 * 短时多账号检测（旧 switch case 迁移，行为不变）
 *
 * 跨用户聚合，需要 risk_log 历史 —— 这是 velocity 的本职（检测同 IP 多账号），
 * 非 frequency 式自激问题。
 *
 * 优先用 FingerprintContext 派生的 ip_hash 精确匹配；上下文缺 ip_hash 时
 * 回退 JSON_EXTRACT(context, '$.ip')（兼容旧日志行）。
 */
class VelocityEvaluator implements RiskEvaluator
{
    public function type(): string
    {
        return 'velocity';
    }

    public function evaluate(int $userId, string $checkType, array $context, array $config): array
    {
        $windowMinutes = (int) ($config['window_minutes'] ?? 10);
        $maxAccounts = (int) ($config['max_accounts'] ?? 3);
        $sameIp = (bool) ($config['same_ip'] ?? true);
        $ip = (string) ($context['ip'] ?? '');
        $ipHash = (string) ($context['ip_hash'] ?? '');

        if (!$sameIp || ($ip === '' && $ipHash === '')) {
            return ['matched' => false, 'message' => '无 IP 上下文，跳过同 IP 聚合', 'severity' => 'low'];
        }

        $since = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));
        $query = RiskLog::where('created_at', '>=', $since);

        if ($ipHash !== '') {
            $query->where('ip_hash', $ipHash);
        } else {
            $query->whereRaw("JSON_EXTRACT(context, '$.ip') = ?", [$ip]);
        }

        $uniqueUsers = $query->distinct('user_id')->count('user_id');

        if ($uniqueUsers >= $maxAccounts) {
            return ['matched' => true, 'message' => "{$windowMinutes}min 内同 IP {$uniqueUsers} 个账号 ≥ 阈值 {$maxAccounts}", 'severity' => 'high'];
        }

        return ['matched' => false, 'message' => "{$windowMinutes}min 内同 IP {$uniqueUsers} 个账号，未达阈值 {$maxAccounts}", 'severity' => 'low'];
    }
}
