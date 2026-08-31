<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\AccountAccountLink;
use app\model\DeviceAccountMap;
use app\model\DeviceFingerprint;
use app\model\User;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("关联图谱")
 * @Apidoc\Group("risk")
 *
 * 两跳闭包（与 service DeviceAccountGraphEvaluator 同思路，只读）：
 * 根用户 → 同设备账号（一跳）→ 其设备上的账号（二跳）。
 */
class RiskGraphController extends BaseController
{
    private const CLUSTER_THRESHOLD = 6;
    private const HOP_CAP = 50;

    /**
     * @Apidoc\Title("用户关联图谱")
     */
    public function graph(Request $request, string $userId): Response
    {
        $rootId = $this->decodeId($userId);
        $root = User::find($rootId);
        if (!$root) {
            return $this->fail('用户不存在');
        }

        // 一跳：与根用户共用设备的账号
        $fps = DeviceAccountMap::where('user_id', $rootId)->limit(self::HOP_CAP)->pluck('fp_hash')->all();
        $hop1 = $fps === [] ? [] : DeviceAccountMap::where('fp_hash', 'in', $fps)
            ->where('user_id', '!=', $rootId)->pluck('user_id')->all();

        // 二跳：一跳账号各自的设备上的账号
        $hop2 = [];
        if ($hop1 !== []) {
            $hop1Fps = DeviceAccountMap::where('user_id', 'in', $hop1)->distinct()->limit(self::HOP_CAP)->pluck('fp_hash')->all();
            if ($hop1Fps !== []) {
                $hop2 = DeviceAccountMap::where('fp_hash', 'in', $hop1Fps)->pluck('user_id')->all();
            }
        }

        $memberIds = array_values(array_unique(array_merge([$rootId], array_map('intval', $hop1), array_map('intval', $hop2))));

        // 节点
        $users = User::whereIn('id', $memberIds)->get()->keyBy('id');
        $nodes = [];
        foreach ($memberIds as $id) {
            $user = $users[$id] ?? null;
            $nodes[] = [
                'id' => $this->encodeId($id),
                'username' => (string) ($user->username ?? '未知'),
                'status' => $user ? (int) $user->status : -1,
                'is_root' => $id === $rootId,
            ];
        }

        // 边：账号关联边 + 设备共享边（同设备账号两两成边，weight 0.6）
        $edges = [];
        $linkEdges = AccountAccountLink::whereIn('user_id_a', $memberIds)->whereIn('user_id_b', $memberIds)->limit(200)->get();
        foreach ($linkEdges as $e) {
            $edges[] = [
                'from' => $this->encodeId((int) $e->user_id_a),
                'to' => $this->encodeId((int) $e->user_id_b),
                'type' => (string) $e->link_type,
                'weight' => 1.0,
                'occurrences' => 1,
            ];
        }

        $fpUsers = [];
        if ($fps !== []) {
            $fpUsers = DeviceAccountMap::where('fp_hash', 'in', $fps)->get()
                ->groupBy('fp_hash')->map(static fn ($rows) => $rows->pluck('user_id')->all())->all();
        }
        foreach ($fpUsers as $fp => $uids) {
            $seen = [];
            foreach ($uids as $a) {
                foreach ($uids as $b) {
                    $ai = (int) $a;
                    $bi = (int) $b;
                    if ($ai === $bi) {
                        continue;
                    }
                    $pair = $ai < $bi ? $ai . '-' . $bi : $bi . '-' . $ai;
                    if (isset($seen[$pair])) {
                        continue;
                    }
                    $seen[$pair] = true;
                    $edges[] = [
                        'from' => $this->encodeId($ai),
                        'to' => $this->encodeId($bi),
                        'type' => 'same_device',
                        'weight' => 0.6,
                        'occurrences' => 1,
                    ];
                }
            }
        }

        return $this->success([
            'root' => $this->encodeId($rootId),
            'nodes' => $nodes,
            'edges' => $edges,
            'cluster_size' => count($memberIds),
            'hops' => $hop2 !== [] ? 2 : ($hop1 !== [] ? 1 : 0),
            'risk_verdict' => count($memberIds) >= self::CLUSTER_THRESHOLD ? 'suspicious' : 'normal',
        ]);
    }

    /**
     * @Apidoc\Title("关联簇概览")
     * @Apidoc\Desc("高账号数设备 TOP10 + 关联类型分布")
     */
    public function clusters(Request $request): Response
    {
        $devices = DeviceFingerprint::where('account_count', '>=', 2)
            ->orderBy('account_count', 'desc')->limit(10)->get();

        $clusters = [];
        foreach ($devices as $device) {
            $memberIds = DeviceAccountMap::where('fp_hash', (string) $device->fp_hash)->limit(20)->pluck('user_id')->all();
            $users = User::whereIn('id', $memberIds)->get()->keyBy('id');
            $members = [];
            foreach ($memberIds as $id) {
                $members[] = ['id' => $this->encodeId((int) $id), 'username' => (string) ($users[(int) $id]->username ?? '未知')];
            }
            $clusters[] = [
                'fp_masked' => substr((string) $device->fp_hash, 0, 8) . '****',
                'account_count' => (int) $device->account_count,
                'members' => $members,
                'last_seen_at' => (string) $device->last_seen_at,
            ];
        }

        $linkStats = AccountAccountLink::selectRaw('link_type, count(*) as cnt')
            ->groupBy('link_type')->get()->pluck('cnt', 'link_type')->all();

        return $this->success(['device_clusters' => $clusters, 'link_type_stats' => $linkStats]);
    }
}
