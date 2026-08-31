<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\risk\evaluators;

use app\service\risk\RiskEvaluator;

/**
 * 单笔大额预警（旧 switch case 迁移，行为不变）
 */
class AmountAnomalyEvaluator implements RiskEvaluator
{
    public function type(): string
    {
        return 'amount_anomaly';
    }

    public function evaluate(int $userId, string $checkType, array $context, array $config): array
    {
        $amount = (string) ($context['amount'] ?? '0');
        $minAmount = (string) ($config['min_amount'] ?? '0');

        if (bccomp($amount, $minAmount, 4) >= 0) {
            return ['matched' => true, 'message' => "单笔金额 {$amount} ≥ 阈值 {$minAmount}", 'severity' => 'medium'];
        }

        return ['matched' => false, 'message' => '金额正常', 'severity' => 'low'];
    }
}
