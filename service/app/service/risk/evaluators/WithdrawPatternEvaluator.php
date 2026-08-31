<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\risk\evaluators;

use common\model\UserWallet;
use common\model\WithdrawOrder;
use app\service\risk\riskEvaluator;

/**
 * 提现频率异常检测
 *
 * 只读业务事实表 withdraw_order + user_wallet，不读 risk_log
 * （修复旧 frequency 规则的自激反馈：命中写日志会放大自身计数）。
 */
class WithdrawPatternEvaluator implements RiskEvaluator
{
    public function type(): string
    {
        return 'withdraw_pattern';
    }

    public function evaluate(int $userId, string $checkType, array $context, array $config): array
    {
        if ($checkType !== 'withdraw' || $userId <= 0) {
            return $this->miss('仅对提现环节生效');
        }

        $amount = (string) ($context['amount'] ?? '0');
        if (!is_numeric($amount) || bccomp($amount, '0', 4) <= 0) {
            return $this->miss('提现金额缺失');
        }

        $windowMinutes = (int) ($config['window_minutes'] ?? 60);
        $maxApplies    = (int) ($config['max_applies'] ?? 5);
        $hardCap       = (string) ($config['single_hard_cap'] ?? '50000');
        $drainRatio    = (string) ($config['drain_ratio'] ?? '0.99');
        $sigmaDays     = (int) ($config['sigma_window_days'] ?? 90);
        $sigmaK        = (float) ($config['sigma_multiplier'] ?? 3);
        $fastSeconds   = (int) ($config['fast_interval_seconds'] ?? 20);
        $fastMinCount  = (int) ($config['fast_interval_min_count'] ?? 5);

        $since = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));
        $count = WithdrawOrder::where('user_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $since)
            ->count();

        if ($count >= $maxApplies) {
            return ['matched' => true, 'message' => "窗口内 {$count} 笔提现 ≥ 阈值 {$maxApplies}（{$windowMinutes}min）", 'severity' => 'high'];
        }

        if (bccomp($amount, $hardCap, 4) > 0) {
            return ['matched' => true, 'message' => "单笔提现 {$amount} 超过硬上限 {$hardCap}", 'severity' => 'high'];
        }

        // 清仓式提现：接近抽干余额
        $wallet = UserWallet::where('user_id', $userId)->first();
        if ($wallet !== null) {
            $balance = (string) ($wallet->balance ?? '0');
            if (bccomp($balance, '0', 4) > 0) {
                $ratio = bcdiv($amount, $balance, 6);
                if (bccomp($ratio, $drainRatio, 6) >= 0) {
                    return ['matched' => true, 'message' => "提现占余额 " . bcmul($ratio, '100', 2) . "% ≥ 阈值 " . bcmul($drainRatio, '100', 2) . '%', 'severity' => 'medium'];
                }
            }
        }

        // σ 偏离：样本 < 3 笔不计算，避免新用户被误杀
        $sinceWindow = date('Y-m-d H:i:s', strtotime("-{$sigmaDays} days"));
        $history = WithdrawOrder::where('user_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $sinceWindow)
            ->pluck('platform_amount')
            ->all();

        if (count($history) >= 3) {
            $mean = '0';
            $sum  = '0';
            foreach ($history as $value) {
                $sum = bcadd($sum, (string) $value, 4);
            }
            $mean = bcdiv($sum, (string) count($history), 6);

            $variance = '0';
            foreach ($history as $value) {
                $variance = bcadd($variance, bcpow(bcsub((string) $value, $mean, 6), 2), 6);
            }
            $stddev = (float) sqrt((float) bcdiv($variance, (string) count($history), 6));
            $upper = bcadd($mean, (string) ($stddev * $sigmaK), 4);

            if (bccomp($amount, $upper, 4) > 0) {
                return ['matched' => true, 'message' => "单笔提现 {$amount} > 近 {$sigmaDays} 天均值 {$mean} + {$sigmaK}σ（{$upper}）", 'severity' => 'medium'];
            }
        }

        // 机械间隔：自动化脚本特征
        $recent = WithdrawOrder::where('user_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'desc')
            ->limit($fastMinCount)
            ->pluck('created_at')
            ->all();

        if (count($recent) >= $fastMinCount) {
            $gaps = [];
            for ($i = 0, $n = count($recent); $i < $n - 1; $i++) {
                $gaps[] = abs(strtotime((string) $recent[$i]) - strtotime((string) $recent[$i + 1]));
            }
            $fast = count(array_filter($gaps, static fn (int $gap): bool => $gap < $fastSeconds));
            if ($fast >= $fastMinCount - 1) {
                return ['matched' => true, 'message' => "相邻提现间隔均 < {$fastSeconds}s（{$fastMinCount} 笔），疑似脚本", 'severity' => 'medium'];
            }
        }

        return $this->miss("提现模式正常（{$count} 笔 / {$windowMinutes}min）");
    }

    private function miss(string $message): array
    {
        return ['matched' => false, 'message' => $message, 'severity' => 'low'];
    }
}
