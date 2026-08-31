<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

use app\model\DepositOrder;
use app\model\DeviceAccountMap;
use app\model\DeviceFingerprint;
use app\model\ExchangeRecord;
use app\model\IpReputation;
use app\model\RiskLog;
use common\model\User;
use app\model\WithdrawOrder;

/**
 * 管理端风控沙箱试算：按服务端评估器同款判定条件只读评估，不写库、不落日志、不触发处置。
 *
 * ponytail: 管理端与服务端是两个独立应用（无共享 autoload），无法调用 service 的 RiskService；
 *           此处镜像各评估器核心条件。若判定逻辑漂移，以 service/app/service/risk/evaluators 为准。
 */
class RiskSandboxService
{
    /** 支持的规则类型（与 service 评估器注册表一致） */
    public const TYPES = [
        'ip_blacklist', 'amount_anomaly', 'frequency', 'velocity',
        'device_fingerprint', 'ip_reputation', 'device_account_graph', 'withdraw_pattern',
    ];

    /**
     * 试算单条规则。
     *
     * @param int    $userId    用户ID（0=未登录）
     * @param string $checkType deposit/withdraw/exchange/login
     * @param array  $rule      RiskRule 行（type/action/config）
     * @param array  $context   ['ip'=>, 'user_agent'=>, 'amount'=>, 'fp_hash'=>]
     * @return array ['matched'=>bool, 'message'=>string, 'severity'=>string, 'action'=>string]
     */
    public static function test(int $userId, string $checkType, array $rule, array $context): array
    {
        $type = (string) $rule['type'];
        $config = json_decode((string) ($rule['config'] ?? '{}'), true) ?? [];
        $context['ip_hash'] = hash('sha256', (string) ($context['ip'] ?? ''));
        $context['fp_hash'] = (string) ($context['fp_hash'] ?? hash('sha256', (string) ($context['user_agent'] ?? '') . '|' . (string) ($context['ip'] ?? '')));

        $hit = match ($type) {
            'ip_blacklist' => self::ipBlacklist($context, $config),
            'amount_anomaly' => self::amountAnomaly($context, $config),
            'frequency' => self::frequency($userId, $checkType, $config),
            'velocity' => self::velocity($userId, $context, $config),
            'device_fingerprint' => self::deviceFingerprint($userId, $checkType, $context, $config),
            'ip_reputation' => self::ipReputation($context, $config),
            'device_account_graph' => self::deviceAccountGraph($userId, $context, $config),
            'withdraw_pattern' => self::withdrawPattern($userId, $config),
            default => ['matched' => false, 'message' => '未知规则类型', 'severity' => 'low'],
        };

        $hit['action'] = self::disposition((string) $rule['action'], (string) $hit['severity']);

        return $hit;
    }

    private static function ipBlacklist(array $context, array $config): array
    {
        $ip = (string) ($context['ip'] ?? '');
        if ($ip !== '' && in_array($ip, $config['blacklist'] ?? [], true)) {
            return ['matched' => true, 'message' => 'IP 命中黑名单', 'severity' => 'high'];
        }

        return self::miss();
    }

    private static function amountAnomaly(array $context, array $config): array
    {
        $amount = (string) ($context['amount'] ?? '0');
        $min = (string) ($config['min_amount'] ?? '0');
        if (bccomp($amount, $min, 4) >= 0) {
            return ['matched' => true, 'message' => "金额 {$amount} ≥ 阈值 {$min}", 'severity' => 'medium'];
        }

        return self::miss();
    }

    private static function frequency(int $userId, string $checkType, array $config): array
    {
        $since = date('Y-m-d H:i:s', time() - ((int) ($config['window_minutes'] ?? 720)) * 60);
        $count = match ($checkType) {
            'deposit' => DepositOrder::where('user_id', $userId)->where('status', 'confirmed')->where('paid_at', '>=', $since)->count(),
            'withdraw' => WithdrawOrder::where('user_id', $userId)->where('status', '!=', 'cancelled')->where('created_at', '>=', $since)->count(),
            'exchange' => ExchangeRecord::where('user_id', $userId)->where('created_at', '>=', $since)->count(),
            default => RiskLog::where('user_id', $userId)->where('type', $checkType)->where('created_at', '>=', $since)->count(),
        };
        $max = (int) ($config['max_count'] ?? 10);
        if ($count >= $max) {
            return ['matched' => true, 'message' => "窗口内 {$checkType} 次数 {$count} ≥ {$max}", 'severity' => 'high'];
        }

        return self::miss();
    }

    private static function velocity(int $userId, array $context, array $config): array
    {
        $ipHash = (string) $context['ip_hash'];
        if ($ipHash === '') {
            return self::miss();
        }
        $since = date('Y-m-d H:i:s', time() - ((int) ($config['window_minutes'] ?? 1440)) * 60);
        $accounts = RiskLog::where('ip_hash', $ipHash)->where('created_at', '>=', $since)
            ->where('user_id', '!=', $userId)->distinct()->count('user_id');
        $max = (int) ($config['max_accounts'] ?? 3);
        if ($accounts >= $max) {
            return ['matched' => true, 'message' => "同 IP 关联账号 {$accounts} ≥ {$max}", 'severity' => 'high'];
        }

        return self::miss();
    }

    private static function deviceFingerprint(int $userId, string $checkType, array $context, array $config): array
    {
        $fp = DeviceFingerprint::where('fp_hash', (string) $context['fp_hash'])->first();
        if (!$fp) {
            return self::miss();
        }
        $max = (int) ($config['max_accounts_per_device'] ?? 5);
        if ((int) $fp->account_count > $max) {
            return ['matched' => true, 'message' => "设备关联账号 {$fp->account_count} > {$max}", 'severity' => 'high'];
        }
        if ($checkType === 'withdraw' && ($config['new_device_withdraw_block'] ?? true)) {
            $lookback = ((int) ($config['new_device_lookback_hours'] ?? 24)) * 3600;
            if (strtotime((string) $fp->first_seen_at) >= time() - $lookback) {
                return ['matched' => true, 'message' => '新设备首次提现', 'severity' => 'high'];
            }
        }

        return self::miss();
    }

    private static function ipReputation(array $context, array $config): array
    {
        $row = IpReputation::where('ip_hash', (string) $context['ip_hash'])->first();
        if (!$row) {
            if ($config['block_unknown'] ?? false) {
                return ['matched' => true, 'message' => '未知 IP（block_unknown）', 'severity' => 'high'];
            }

            return self::miss();
        }
        if ($row->source === 'internal_whitelist') {
            return self::miss();
        }
        if ((int) $row->reputation_score < (int) ($config['block_score_below'] ?? 30)) {
            return ['matched' => true, 'message' => "IP 信誉分 {$row->reputation_score} < 阻断阈值", 'severity' => 'high'];
        }
        if ((int) $row->reputation_score < (int) ($config['warn_score_below'] ?? 60)) {
            return ['matched' => true, 'message' => "IP 信誉分 {$row->reputation_score} < 警告阈值", 'severity' => 'medium'];
        }

        return self::miss();
    }

    private static function deviceAccountGraph(int $userId, array $context, array $config): array
    {
        $fpHash = (string) $context['fp_hash'];
        if ($fpHash === '' || $userId <= 0) {
            return self::miss();
        }
        $threshold = (int) ($config['cluster_threshold'] ?? 6);
        $cap = (int) ($config['max_accounts_per_device'] ?? 50);

        $hop1 = DeviceAccountMap::where('fp_hash', $fpHash)->where('user_id', '!=', $userId)->limit($cap)->pluck('user_id')->all();
        $frozen = [];
        if ($hop1 !== [] && ($config['frozen_sibling_block'] ?? true)) {
            $frozen = User::whereIn('id', $hop1)->where('status', '!=', 1)->pluck('id')->all();
        }
        if ($frozen !== []) {
            return ['matched' => true, 'message' => '同设备账号 ' . implode(',', $frozen) . ' 处于禁用状态', 'severity' => 'high'];
        }

        $hop2 = [];
        if ($hop1 !== []) {
            $fps = DeviceAccountMap::where('user_id', 'in', $hop1)->distinct()->pluck('fp_hash')->all();
            if ($fps !== []) {
                $hop2 = DeviceAccountMap::where('fp_hash', 'in', $fps)->limit($cap * count($fps))->pluck('user_id')->all();
            }
        }
        $cluster = array_values(array_diff(array_unique(array_merge(array_map('intval', $hop1), array_map('intval', $hop2))), [$userId]));

        if (count($cluster) >= $threshold) {
            return ['matched' => true, 'message' => '两跳关联账号数 ' . count($cluster) . " ≥ 团伙阈值 {$threshold}", 'severity' => 'high'];
        }
        if (count($hop1) >= 2) {
            return ['matched' => true, 'message' => '同设备账号数 ' . count($hop1) . '（未达团伙阈值）', 'severity' => 'low'];
        }

        return self::miss();
    }

    private static function withdrawPattern(int $userId, array $config): array
    {
        $orders = WithdrawOrder::where('user_id', $userId)->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - ((int) ($config['sigma_window_days'] ?? 7)) * 86400))->get();

        $maxApplies = (int) ($config['max_applies'] ?? 3);
        if (count($orders) >= $maxApplies) {
            return ['matched' => true, 'message' => '窗口内提现申请 ' . count($orders) . " ≥ {$maxApplies}", 'severity' => 'high'];
        }

        $cap = (string) ($config['single_hard_cap'] ?? '0');
        foreach ($orders as $order) {
            if (bccomp((string) $order->amount, $cap, 4) >= 0) {
                return ['matched' => true, 'message' => "单笔提现超硬上限 {$cap}", 'severity' => 'high'];
            }
        }

        return self::miss();
    }

    private static function disposition(string $ruleAction, string $severity): string
    {
        if ($severity === 'high') {
            return $ruleAction;
        }
        if ($severity === 'medium') {
            return $ruleAction === 'block' ? 'warn' : $ruleAction;
        }

        return 'log';
    }

    private static function miss(): array
    {
        return ['matched' => false, 'message' => '', 'severity' => 'low'];
    }
}
