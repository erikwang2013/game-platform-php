<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\risk\evaluators;

use app\model\DeviceAccountMap;
use common\model\User;
use app\service\risk\riskEvaluator;
use support\Db;
use support\Log;
use Throwable;

/**
 * 设备-账号关联图谱检测（边表 + 两跳闭包，不引入图数据库）
 *
 * ponytail: 两跳闭包是近似，不是连通分量精确算法；单跳结果按 max_accounts_per_device 截断，
 *           n < 50 时 O(n²) 可忽略。账号规模 > 1000 或需跨设备精确团伙时，
 *           改用定时进程构建连通分量落表、管理端直接读结果。
 */
class DeviceAccountGraphEvaluator implements RiskEvaluator
{
    public function type(): string
    {
        return 'device_account_graph';
    }

    public function evaluate(int $userId, string $checkType, array $context, array $config): array
    {
        $fpHash = $context['fp_hash'] ?? '';
        if ($fpHash === '' || $userId <= 0) {
            return $this->miss('设备指纹缺失，无法评估');
        }

        $threshold     = (int) ($config['cluster_threshold'] ?? 6);
        $blockFrozen   = (bool) ($config['frozen_sibling_block'] ?? true);
        $cap           = (int) ($config['max_accounts_per_device'] ?? 50);
        $sandbox       = (bool) ($context['_sandbox'] ?? false);

        // 一跳：同设备账号
        $hop1 = DeviceAccountMap::where('fp_hash', $fpHash)
            ->where('user_id', '!=', $userId)
            ->limit($cap)
            ->pluck('user_id')
            ->all();

        if ($blockFrozen) {
            $frozen = $this->disabledPeers($hop1);
            if ($frozen !== []) {
                return [
                    'matched'  => true,
                    'message'  => '同设备账号 ' . implode(',', $frozen) . ' 处于禁用状态',
                    'severity' => 'high',
                ];
            }
        }

        // 二跳：一跳账号各自的设备账号集合
        $hop2 = [];
        if ($hop1 !== []) {
            $fpHashes = DeviceAccountMap::where('user_id', 'in', $hop1)
                ->distinct()
                ->pluck('fp_hash')
                ->all();
            if ($fpHashes !== []) {
                $hop2 = DeviceAccountMap::where('fp_hash', 'in', $fpHashes)
                    ->limit($cap * count($fpHashes))
                    ->pluck('user_id')
                    ->all();
            }
        }

        $cluster = array_unique(array_merge($hop1, $hop2));
        $cluster = array_values(array_diff(array_map('intval', $cluster), [$userId]));

        if (count($cluster) >= $threshold) {
            return ['matched' => true, 'message' => "两跳关联账号数 " . count($cluster) . " ≥ 团伙阈值 {$threshold}", 'severity' => 'high'];
        }

        // 同设备 ≥2 账号：记录账号-账号边（幂等），供管理端图谱展示
        if (count($hop1) >= 2) {
            $this->linkSameDevice($userId, $hop1, $sandbox);
            return ['matched' => true, 'message' => "同设备账号数 " . count($hop1) . '（未达团伙阈值 ' . $threshold . '）', 'severity' => 'low'];
        }

        return $this->miss('无同设备账号');
    }

    /**
     * 处于禁用状态的同设备账号（status != 1）
     */
    private function disabledPeers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        try {
            return User::whereIn('id', $userIds)->where('status', '!=', 1)->pluck('id')->all();
        } catch (Throwable $e) {
            Log::error('DeviceAccountGraph disabledPeers failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 批量写 same_device 边（单条 INSERT IGNORE，幂等）
     */
    private function linkSameDevice(int $userId, array $peers, bool $sandbox): void
    {
        if ($sandbox || $peers === []) {
            return;
        }

        try {
            $rows = [];
            foreach ($peers as $peer) {
                $peer = (int) $peer;
                if ($peer <= 0) {
                    continue;
                }
                $rows[] = [
                    'user_id_a' => min($userId, $peer),
                    'user_id_b' => max($userId, $peer),
                    'link_type' => 'same_device',
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }
            if ($rows !== []) {
                Db::table('account_account_link')->insertOrIgnore($rows);
            }
        } catch (Throwable $e) {
            // 边表写失败不影响检测结论
            Log::error('account_account_link insert failed: ' . $e->getMessage());
        }
    }

    private function miss(string $message): array
    {
        return ['matched' => false, 'message' => $message, 'severity' => 'low'];
    }
}
