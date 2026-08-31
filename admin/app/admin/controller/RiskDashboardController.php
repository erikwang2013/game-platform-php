<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\DeviceFingerprint;
use app\model\IpReputation;
use app\model\RiskLog;
use app\model\RiskRule;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("风控总览")
 * @Apidoc\Group("risk")
 */
class RiskDashboardController extends BaseController
{
    /**
     * @Apidoc\Title("总览数据")
     * @Apidoc\Desc("24h 命中分布 + 规则/黑名单/设备簇规模")
     */
    public function index(Request $request): Response
    {
        $since24h = date('Y-m-d H:i:s', time() - 86400);

        $total24h = RiskLog::where('created_at', '>=', $since24h)->count();
        $byAction = RiskLog::where('created_at', '>=', $since24h)
            ->selectRaw('action, count(*) as cnt')->groupBy('action')
            ->get()->pluck('cnt', 'action')->all();

        $recent = RiskLog::where('created_at', '>=', $since24h)
            ->orderBy('id', 'desc')->limit(5)->get();

        return $this->success([
            'total_events_24h' => $total24h,
            'blocked_24h' => (int) ($byAction['block'] ?? 0),
            'warned_24h' => (int) ($byAction['warn'] ?? 0),
            'logged_24h' => (int) ($byAction['log'] ?? 0),
            'block_rate_24h' => $total24h > 0 ? round(((int) ($byAction['block'] ?? 0)) / $total24h * 100, 2) : 0,
            'enabled_rules' => RiskRule::where('status', 1)->count(),
            'total_rules' => RiskRule::count(),
            'blacklist_ips' => IpReputation::where('source', 'internal_blacklist')->count(),
            'device_clusters' => DeviceFingerprint::where('account_count', '>=', 2)->count(),
            'recent_events' => array_map(fn ($row) => [
                'id' => $this->encodeId((int) $row->id),
                'user_id' => $this->encodeId((int) $row->user_id),
                'type' => (string) $row->type,
                'action' => (string) $row->action,
                'detail' => mb_substr((string) $row->detail, 0, 100),
                'created_at' => (string) $row->created_at,
            ], $recent),
        ]);
    }
}
