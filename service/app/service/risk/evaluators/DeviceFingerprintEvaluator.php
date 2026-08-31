<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\risk\evaluators;

use common\SnowflakeService;
use app\model\DeviceAccountMap;
use app\model\DeviceFingerprint;
use app\service\risk\RiskEvaluator;
use support\Db;
use support\Log;
use Throwable;

/**
 * 设备指纹检测
 *
 * 只读 hash（fp_hash / user_agent_hash / accept_lang_hash），不读明文 UA / IP。
 * 硬规则：命中异常时由 RiskService fail-closed。
 */
class DeviceFingerprintEvaluator implements RiskEvaluator
{
    public function type(): string
    {
        return 'device_fingerprint';
    }

    public function evaluate(int $userId, string $checkType, array $context, array $config): array
    {
        $fpHash = $context['fp_hash'] ?? '';
        if ($fpHash === '' || $userId <= 0) {
            return $this->miss('设备指纹缺失（未登录或上下文未派生 fp_hash）');
        }

        $maxAccounts   = (int) ($config['max_accounts_per_device'] ?? 5);
        $lookbackHours = (int) ($config['new_device_lookback_hours'] ?? 24);
        $blockNewDev   = (bool) ($config['new_device_withdraw_block'] ?? true);
        $sandbox       = (bool) ($context['_sandbox'] ?? false);

        $this->touch($fpHash, $userId, $context, $sandbox);

        $fingerprint = DeviceFingerprint::where('fp_hash', $fpHash)->first();
        $accountCount = (int) ($fingerprint?->account_count ?? 0);

        if ($accountCount > $maxAccounts) {
            return ['matched' => true, 'message' => "同设备关联 {$accountCount} 个账号（阈值 {$maxAccounts}）", 'severity' => 'high'];
        }

        $newDevice = $fingerprint !== null
            && strtotime((string) $fingerprint->first_seen_at) >= strtotime("-{$lookbackHours} hours");
        if (!$newDevice) {
            return $this->miss("设备已观测（关联 {$accountCount} 个账号）");
        }

        if ($checkType === 'withdraw') {
            return [
                'matched' => true,
                'message' => "新设备（{$lookbackHours}h 内首次出现）发起提现",
                'severity' => $blockNewDev ? 'high' : 'medium',
            ];
        }

        if ($checkType === 'login' || $checkType === 'deposit') {
            return ['matched' => true, 'message' => "新设备{$checkType}（{$lookbackHours}h 内首次出现）", 'severity' => 'low'];
        }

        return $this->miss("新设备但当前环节（{$checkType}）不评估");
    }

    /**
     * 幂等 upsert 设备指纹与设备-账号边，供登录 / 充值 / 提现入口复用。
     * 沙箱试算不写库。
     */
    private function touch(string $fpHash, int $userId, array $context, bool $sandbox): void
    {
        if ($sandbox) {
            return;
        }

        try {
            $now = date('Y-m-d H:i:s');

            // 边表先写，account_count 依赖边表计数
            if (Db::table('device_account_map')->where('fp_hash', $fpHash)->where('user_id', $userId)->update(['last_seen_at' => $now]) === 0) {
                Db::table('device_account_map')->insert([
                    'id'            => SnowflakeService::generate(),
                    'fp_hash'       => $fpHash,
                    'user_id'       => $userId,
                    'first_seen_at' => $now,
                    'last_seen_at'  => $now,
                ]);
            }

            $count = (int) Db::table('device_account_map')->where('fp_hash', $fpHash)->count();

            if (Db::table('device_fingerprint')->where('fp_hash', $fpHash)->update([
                'last_seen_at'  => $now,
                'account_count' => $count,
            ]) === 0) {
                Db::table('device_fingerprint')->insert([
                    'id'               => SnowflakeService::generate(),
                    'fp_hash'          => $fpHash,
                    'ip_c_segment'     => $context['ip_c_segment'] ?? '',
                    'user_agent_hash'  => $context['user_agent_hash'] ?? '',
                    'accept_lang_hash' => $context['accept_lang_hash'] ?? '',
                    'first_seen_at'    => $now,
                    'last_seen_at'     => $now,
                    'account_count'    => $count,
                ]);
            }
        } catch (Throwable $e) {
            // 写库失败不影响检测结论；调用方（RiskService）按硬规则 fail-closed 处理
            Log::error('DeviceFingerprint upsert failed: ' . $e->getMessage(), ['fp_hash' => $fpHash, 'user_id' => $userId]);
        }
    }

    private function miss(string $message): array
    {
        return ['matched' => false, 'message' => $message, 'severity' => 'low'];
    }
}
