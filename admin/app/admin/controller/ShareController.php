<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\ShareLink;
use support\Db;
use support\Request;
use support\Response;

/**
 * 分享统计（M4）：裂变漏斗 分享→点击→转化
 *
 * @Apidoc\Title("分享统计")
 * @Apidoc\Group("share")
 */
class ShareController extends BaseController
{
    /**
     * @Apidoc\Title("分享裂变漏斗统计")
     * @Apidoc\Url("/admin/share/stats")
     * @Apidoc\Method("GET")
     * @Apidoc\Param("activity_id", type="string", require=false, desc="活动ID(hashid)")
     * @Apidoc\Param("from", type="string", require=false, desc="起始日期 Y-m-d")
     * @Apidoc\Param("to", type="string", require=false, desc="结束日期 Y-m-d")
     */
    public function stats(Request $request): Response
    {
        $query = ShareLink::query();
        if ($request->input('activity_id')) {
            $query->where('activity_id', $this->decodeId($request->input('activity_id')));
        }
        if ($request->input('from')) {
            $query->where('created_at', '>=', $request->input('from') . ' 00:00:00');
        }
        if ($request->input('to')) {
            $query->where('created_at', '<=', $request->input('to') . ' 23:59:59');
        }

        $total = (clone $query)->count();
        $clicks = (clone $query)->sum('clicks');
        $conversions = (clone $query)->sum('conversions');

        $daily = $query
            ->select(Db::raw('DATE(created_at) AS day'), Db::raw('COUNT(*) AS shares'), Db::raw('SUM(clicks) AS clicks'), Db::raw('SUM(conversions) AS conversions'))
            ->groupBy(Db::raw('DATE(created_at)'))
            ->orderBy('day', 'desc')
            ->limit(30)
            ->get();

        return $this->success([
            'funnel' => [
                'shares'      => (int) $total,
                'clicks'      => (int) $clicks,
                'conversions' => (int) $conversions,
            ],
            'daily' => $daily,
        ]);
    }
}
