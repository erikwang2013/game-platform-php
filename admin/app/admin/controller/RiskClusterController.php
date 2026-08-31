<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\DeviceAccountMap;
use common\model\DeviceFingerprint;
use app\model\RiskCluster;
use common\model\RiskLog;
use common\model\User;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("关联团伙")
 * @Apidoc\Group("risk")
 *
 * 已确认团伙（game_risk_cluster，人工写入）；detect 只出候选不落库。
 * 同 IP / 同设备候选来自 risk_log / device_account_map，均为哈希值。
 */
class RiskClusterController extends BaseController
{
    private const DETECT_WINDOW_DAYS = 7;
    private const IP_MIN_ACCOUNTS = 5;
    private const DEVICE_MIN_ACCOUNTS = 3;
    private const DETECT_TOP = 10;

    /**
     * @Apidoc\Title("团伙列表")
     */
    public function list(Request $request): Response
    {
        $query = RiskCluster::query();
        if ($request->get('type')) {
            $query->where('type', (string) $request->get('type'));
        }
        if ($request->get('status') !== null && $request->get('status') !== '') {
            $query->where('status', (int) $request->get('status'));
        }

        $page = max(1, (int) $request->get('page', 1));
        $size = min(100, max(1, (int) $request->get('size', 20)));
        $total = (clone $query)->count();
        $items = $query->orderBy('updated_at', 'desc')->forPage($page, $size)->get()->all();

        return $this->success([
            'total' => $total,
            'items' => array_map(fn ($row) => $this->format($row), $items),
        ]);
    }

    /**
     * @Apidoc\Title("团伙成员")
     * @Apidoc\Desc("same_device 查 device_account_map；same_ip 查 risk_log 去重；其余读 member_ids")
     */
    public function members(Request $request, string $hashid): Response
    {
        $cluster = RiskCluster::find($this->decodeId($hashid));
        if (!$cluster) {
            return $this->fail('团伙不存在');
        }

        $memberIds = $this->resolveMemberIds($cluster);
        $users = User::whereIn('id', $memberIds)->get()->keyBy('id');
        $members = array_map(fn ($id) => [
            'id' => $this->encodeId($id),
            'username' => (string) ($users[$id]->username ?? '未知'),
        ], $memberIds);

        return $this->success(['cluster' => $this->format($cluster), 'members' => $members]);
    }

    /**
     * @Apidoc\Title("聚类检测")
     * @Apidoc\Desc("只返回候选不落库：同 IP >= 5 账户、同设备指纹 >= 3 账户（近 7 天）")
     */
    public function detect(Request $request): Response
    {
        $since = date('Y-m-d H:i:s', time() - self::DETECT_WINDOW_DAYS * 86400);

        $ipCandidates = RiskLog::where('created_at', '>=', $since)
            ->where('ip_hash', '!=', '')
            ->selectRaw('ip_hash, count(distinct user_id) as user_count')
            ->groupBy('ip_hash')->havingRaw('count(distinct user_id) >= ?', [self::IP_MIN_ACCOUNTS])
            ->orderByRaw('user_count desc')->limit(self::DETECT_TOP)->get()->all();

        $deviceCandidates = DeviceFingerprint::where('account_count', '>=', self::DEVICE_MIN_ACCOUNTS)
            ->orderBy('account_count', 'desc')->limit(self::DETECT_TOP)->get()->all();

        $candidates = [];
        foreach ($ipCandidates as $row) {
            $candidates[] = [
                'type' => 'same_ip',
                'fingerprint' => (string) $row->ip_hash,
                'fingerprint_masked' => substr((string) $row->ip_hash, 0, 8) . '****',
                'user_count' => (int) $row->user_count,
            ];
        }
        foreach ($deviceCandidates as $row) {
            $candidates[] = [
                'type' => 'same_device',
                'fingerprint' => (string) $row->fp_hash,
                'fingerprint_masked' => substr((string) $row->fp_hash, 0, 8) . '****',
                'user_count' => (int) $row->account_count,
            ];
        }

        return $this->success(['window_days' => self::DETECT_WINDOW_DAYS, 'candidates' => $candidates]);
    }

    /**
     * @Apidoc\Title("人工确认团伙")
     * @Apidoc\Desc("POST {type, fingerprint, name, member_ids?} 写入 game_risk_cluster")
     */
    public function confirm(Request $request): Response
    {
        $type = (string) $request->post('type', '');
        $fingerprint = (string) $request->post('fingerprint', '');
        $name = mb_substr((string) $request->post('name', ''), 0, 100);

        if (!in_array($type, ['same_ip', 'same_device', 'same_pay_account', 'manual'], true)) {
            return $this->fail('type 非法');
        }
        if ($name === '') {
            return $this->fail('name 必填');
        }
        if (in_array($type, ['same_ip', 'same_device'], true) && $fingerprint === '') {
            return $this->fail('fingerprint 必填');
        }

        $members = [];
        foreach ((array) $request->post('member_ids', []) as $raw) {
            $id = (int) $raw;
            if ($id > 0 && !in_array($id, $members, true)) {
                $members[] = $id;
            }
        }
        $userCount = max(count($members), (int) $request->post('user_count', 0));

        $cluster = RiskCluster::create([
            'id' => $this->generateId(),
            'name' => $name,
            'type' => $type,
            'fingerprint' => $fingerprint,
            'member_ids' => $members === [] ? '' : json_encode($members),
            'user_count' => $userCount,
            'status' => 1,
        ]);

        return $this->success(['cluster' => $this->format($cluster)]);
    }

    /**
     * @Apidoc\Title("团伙状态更新")
     * @Apidoc\Desc("status: 1=观察中 2=已处置 0=误判")
     */
    public function status(Request $request, string $hashid): Response
    {
        $cluster = RiskCluster::find($this->decodeId($hashid));
        if (!$cluster) {
            return $this->fail('团伙不存在');
        }
        $status = (int) $request->post('status', -1);
        if (!in_array($status, [0, 1, 2], true)) {
            return $this->fail('status 非法（0/1/2）');
        }
        $cluster->status = $status;
        $cluster->save();

        return $this->success(['cluster' => $this->format($cluster)]);
    }

    private function resolveMemberIds(RiskCluster $cluster): array
    {
        if ($cluster->member_ids !== '') {
            $ids = json_decode((string) $cluster->member_ids, true);
            return array_map('intval', is_array($ids) ? $ids : []);
        }
        if ($cluster->type === 'same_device' && $cluster->fingerprint !== '') {
            return DeviceAccountMap::where('fp_hash', (string) $cluster->fingerprint)
                ->limit(50)->pluck('user_id')->all();
        }
        if ($cluster->type === 'same_ip' && $cluster->fingerprint !== '') {
            return RiskLog::where('ip_hash', (string) $cluster->fingerprint)
                ->distinct()->limit(50)->pluck('user_id')->all();
        }
        return [];
    }

    private function format(RiskCluster $cluster): array
    {
        return [
            'id' => $this->encodeId((int) $cluster->id),
            'name' => (string) $cluster->name,
            'type' => (string) $cluster->type,
            'fingerprint' => (string) $cluster->fingerprint,
            'fingerprint_masked' => $cluster->fingerprint === '' ? '' : substr((string) $cluster->fingerprint, 0, 8) . '****',
            'user_count' => (int) $cluster->user_count,
            'status' => (int) $cluster->status,
            'created_at' => (string) $cluster->created_at,
            'updated_at' => (string) $cluster->updated_at,
        ];
    }
}
