<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\risk\evaluators;

use common\model\DepositOrder;
use common\model\ExchangeRecord;
use common\model\RiskLog;
use common\model\WithdrawOrder;
use app\service\risk\RiskEvaluator;

/**
 * 频率检测（旧 switch case 迁移，行为不变）
 *
 * 只读业务事实表（deposit_order / withdraw_order / exchange_record），
 * 不读 risk_log，避免命中写日志放大自身计数（自激反馈已修复）。
 */
class FrequencyEvaluator implements RiskEvaluator
{
    public function type(): string
    {
        return 'frequency';
    }

    public function evaluate(int $userId, string $checkType, array $context, array $config): array
    {
        $windowMinutes = (int) ($config['window_minutes'] ?? 60);
        $maxCount = (int) ($config['max_count'] ?? 10);
        $since = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));

        // 按业务表计数，而非 risk_log
        $count = match ($checkType) {
            'deposit' => DepositOrder::where('user_id', $userId)
                ->where('status', 'confirmed')
                ->where('paid_at', '>=', $since)
                ->count(),
            'withdraw' => WithdrawOrder::where('user_id', $userId)
                ->where('status', '!=', 'cancelled')
                ->where('created_at', '>=', $since)
                ->count(),
            'exchange' => ExchangeRecord::where('user_id', $userId)
                ->where('created_at', '>=', $since)
                ->count(),
            default => RiskLog::where('user_id', $userId)
                ->where('type', $checkType)
                ->where('created_at', '>=', $since)
                ->count(),
        };

        if ($count >= $maxCount) {
            return ['matched' => true, 'message' => "{$windowMinutes}min 内 {$count} 次 ≥ 阈值 {$maxCount}", 'severity' => 'medium'];
        }

        return ['matched' => false, 'message' => "{$windowMinutes}min 内 {$count} 次，未达阈值 {$maxCount}", 'severity' => 'low'];
    }
}
