<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\anticheat;

/**
 * 反作弊检测器（纯函数，无 IO）— 设计稿 §3.2 下注模式检测
 *
 * 输入: rounds = [['bet' => float, 'won' => bool], ...]（按时间升序）
 * 输出: ['matched' => bool, 'template' => 'fixed|martingale|anti-martingale|arithmetic',
 *        'ratio' => float, 'evidence' => array]
 * 四模板各自给出命中率，最高者 > trigger_ratio 触发。
 *
 * 阈值全部由规则 config 注入（见 game_risk_rule 种子 40000000000000009），
 * 本类不做任何硬编码业务阈值。
 */
class AntiCheatDetector
{
    /**
     * 下注模式检测
     *
     * @param array $rounds 对局序列（按时间升序）
     * @param array $config 规则配置（min_rounds/fixed_cv/trigger_ratio/ratio_tolerance/ar_run/ar_diff_tolerance）
     */
    public static function detectBetPattern(array $rounds, array $config): array
    {
        $n = count($rounds);
        if ($n < (int) ($config['min_rounds'] ?? 30)) {
            return ['matched' => false, 'template' => '', 'ratio' => 0.0, 'evidence' => ['n' => $n, 'reason' => 'insufficient']];
        }

        $bets = array_map(static fn (array $r) => (float) $r['bet'], $rounds);
        $ratioTolerance = (float) ($config['ratio_tolerance'] ?? 0.005);
        $trigger = (float) ($config['trigger_ratio'] ?? 0.6);

        // 1) 固定注: 变异系数 CV = stddev/mean < fixed_cv（n>=min_rounds 已保证）
        // ponytail: 均值/方差/CV 为浮点统计特征（bcmath 无 sqrt/pow 原语），豁免项须保持 float；下同 mean/cv 输出 round()
        $fixed = ['matched' => false, 'ratio' => 0.0];
        $mean = array_sum($bets) / $n;
        if ($mean > 0) {
            $variance = array_sum(array_map(static fn (float $b) => ($b - $mean) ** 2, $bets)) / $n;
            $cv = sqrt($variance) / $mean;
            if ($cv < (float) ($config['fixed_cv'] ?? 0.02)) {
                $fixed = ['matched' => true, 'ratio' => 1.0];
            }
        }

        // 2/3) 马丁格尔/反马丁格尔: 翻倍注仅在上一局输/赢后出现
        $martingale = self::doublingRatio($rounds, false, 2.0, $ratioTolerance);
        $antiMartingale = self::doublingRatio($rounds, true, 2.0, $ratioTolerance);

        // 4) 等差注: 相邻注差额恒定连续 ar_run 次以上
        $arithmetic = ['matched' => false, 'ratio' => 0.0];
        if ($n >= 2) {
            $diffs = [];
            for ($i = 1; $i < $n; $i++) {
                $diffs[] = $bets[$i] - $bets[$i - 1];
            }
            $run = 1;
            $maxRun = 1;
            $arTolerance = (float) ($config['ar_diff_tolerance'] ?? 0.01);
            for ($i = 1; $i < count($diffs); $i++) {
                if (abs($diffs[$i] - $diffs[$i - 1]) <= $arTolerance) {
                    $run++;
                } else {
                    $run = 1;
                }
                $maxRun = max($maxRun, $run);
            }
            if ($maxRun >= (int) ($config['ar_run'] ?? 8)) {
                $arithmetic = ['matched' => true, 'ratio' => 1.0];
            }
        }

        // 四模板取最高命中率
        $candidates = [
            'fixed'            => $fixed,
            'martingale'       => $martingale,
            'anti-martingale'  => $antiMartingale,
            'arithmetic'       => $arithmetic,
        ];
        $best = ['template' => '', 'ratio' => 0.0];
        foreach ($candidates as $name => $cand) {
            if ($cand['ratio'] > $best['ratio']) {
                $best = ['template' => $name, 'ratio' => $cand['ratio']];
            }
        }

        return [
            'matched'  => $best['ratio'] > $trigger,
            'template' => $best['template'],
            'ratio'    => round($best['ratio'], 4),
            'evidence' => [
                'n' => $n,
                'mean' => round($mean, 4),
                'cv' => round(sqrt($variance) / ($mean > 0 ? $mean : 1), 6),
                'martingale_hits' => $martingale['ratio'] > 0 ? $martingale['evidence'] : [],
                'anti_martingale_hits' => $antiMartingale['ratio'] > 0 ? $antiMartingale['evidence'] : [],
                'ar_max_run' => $arithmetic['matched'] ? ($maxRun ?? 0) : 0,
            ],
        ];
    }

    /**
     * 翻倍注命中率: 上一局 won=$prevWon 时，本注与上注比值落在 [factor±tolerance] 的比例。
     * 无上注（i=0）或上注为 0 的窗口不参与分母。
     */
    private static function doublingRatio(array $rounds, bool $prevWon, float $factor, float $tolerance): array
    {
        $hits = 0;
        $windows = 0;
        for ($i = 1; $i < count($rounds); $i++) {
            $prevBet = (float) $rounds[$i - 1]['bet'];
            if ($prevBet <= 0 || (bool) $rounds[$i - 1]['won'] !== $prevWon) {
                continue;
            }
            $windows++;
            if (abs(((float) $rounds[$i]['bet']) / $prevBet - $factor) <= $tolerance) {
                $hits++;
            }
        }

        return [
            'matched'  => $windows > 0 && $hits / $windows >= 0.5, // 单模板内部过半即倾向成立
            'ratio'    => $windows > 0 ? $hits / $windows : 0.0,
            'evidence' => ['hits' => $hits, 'windows' => $windows],
        ];
    }

    /**
     * 出招频率检测 — 后置（需要 move_count/active_seconds 数据积累）
     */
    public static function detectRate(array $rounds, array $config): array
    {
        return ['matched' => false, 'reason' => 'not-enabled'];
    }

    /**
     * 对局时长检测 — 后置
     */
    public static function detectDuration(array $rounds, array $config): array
    {
        return ['matched' => false, 'reason' => 'not-enabled'];
    }

    /**
     * 自检（php -r 可运行）: 固定注/马丁格尔/等差 应命中，随机注不应命中
     */
    public static function demo(): void
    {
        $cfg = ['min_rounds' => 30, 'fixed_cv' => 0.02, 'trigger_ratio' => 0.6,
            'ratio_tolerance' => 0.005, 'ar_run' => 8, 'ar_diff_tolerance' => 0.01];

        $fixed = array_map(static fn () => ['bet' => 10.0, 'won' => true], range(1, 40));
        assert(self::detectBetPattern($fixed, $cfg)['matched'] === true, 'fixed should match');

        $martingale = [['bet' => 1.0, 'won' => false]];
        for ($i = 1; $i < 40; $i++) {
            $prev = $martingale[$i - 1];
            $martingale[] = ['bet' => $prev['bet'] * 2, 'won' => !$prev['won']];
        }
        assert(self::detectBetPattern($martingale, $cfg)['template'] === 'martingale', 'martingale should match');

        $ar = [];
        for ($i = 0; $i < 40; $i++) {
            $ar[] = ['bet' => 1.0 + $i * 2.0, 'won' => true]; // 等差 2.0 连续
        }
        assert(self::detectBetPattern($ar, $cfg)['template'] === 'arithmetic', 'arithmetic should match');

        $rand = [];
        $seed = 7;
        for ($i = 0; $i < 40; $i++) {
            $seed = (1103515245 * $seed + 12345) % 2147483648;
            $bet = 1 + $seed % 20;
            $rand[] = ['bet' => (float) $bet, 'won' => $seed % 2 === 0];
        }
        assert(self::detectBetPattern($rand, $cfg)['matched'] === false, 'random should not match');

        echo "AntiCheatDetector demo: OK\n";
    }
}
