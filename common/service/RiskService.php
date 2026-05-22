<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use common\model\User;
use common\model\Transaction;
use common\model\PlatformConfig;

/**
 * 风控服务
 *
 * 对交易进行风险评估，检测异常行为。
 */
class RiskService
{
    /**
     * 检测单笔交易风险
     *
     * @param int    $userId     用户ID
     * @param string $amount     交易金额
     * @param string $type       交易类型
     * @param string $refType    关联类型
     * @param int    $refId      关联ID
     * @return array{passed: bool, level: string, reason: string}
     */
    public static function check(int $userId, string $amount, string $type, string $refType = '', int $refId = 0): array
    {
        // 检查单笔交易上限
        $maxAmount = PlatformConfig::get('risk', 'max_single_amount', '10000.0000');
        if (bccomp($amount, $maxAmount, 4) > 0) {
            return [
                'passed' => false,
                'level'  => 'high',
                'reason' => "Single transaction exceeds max amount: {$maxAmount}",
            ];
        }

        // 检查日累计交易额
        $todayTotal = Transaction::where('user_id', $userId)
            ->where('created_at', '>=', date('Y-m-d 00:00:00'))
            ->sum('amount');

        $dailyLimit = PlatformConfig::get('risk', 'daily_tx_limit', '50000.0000');
        $todayTotalWithNew = bcadd((string) $todayTotal, $amount, 4);
        if (bccomp($todayTotalWithNew, $dailyLimit, 4) > 0) {
            return [
                'passed' => false,
                'level'  => 'high',
                'reason' => "Daily transaction limit exceeded: {$dailyLimit}",
            ];
        }

        // 检查短时间高频交易
        $recentCount = Transaction::where('user_id', $userId)
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - 300))
            ->count();

        $maxFrequency = PlatformConfig::get('risk', 'max_tx_frequency_5min', '10');
        if ($recentCount > (int) $maxFrequency) {
            return [
                'passed' => false,
                'level'  => 'medium',
                'reason' => "Too many transactions in 5 minutes: {$recentCount}",
            ];
        }

        return [
            'passed' => true,
            'level'  => 'low',
            'reason' => '',
        ];
    }
}
