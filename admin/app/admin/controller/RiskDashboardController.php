<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\DeviceFingerprint;
use common\model\IpReputation;
use common\model\RiskLog;
use common\model\RiskRule;
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

    /**
     * @Apidoc\Title("趋势大盘")
     * @Apidoc\Desc("时间段内命中数按天/小时分组，含动作分布；只读 risk_log（idx_created_at）")
     */
    public function overview(Request $request): Response
    {
        $from = (string) $request->get('from', date('Y-m-d H:i:s', strtotime('-7 days')));
        $to = (string) $request->get('to', date('Y-m-d H:i:s'));
        $fmt = $request->get('group_by', 'day') === 'hour' ? '%Y-%m-%d %H:00' : '%Y-%m-%d';

        $rows = RiskLog::where('created_at', '>=', $from)->where('created_at', '<=', $to)
            ->selectRaw("DATE_FORMAT(created_at, '{$fmt}') as bucket, count(*) as hits, sum(action = 'block') as blocked, sum(action = 'warn') as warned, sum(action = 'log') as logged")
            ->groupBy('bucket')->orderBy('bucket')->get()->all();

        $series = [];
        $total = ['hits' => 0, 'blocked' => 0, 'warned' => 0, 'logged' => 0];
        foreach ($rows as $row) {
            $item = [
                'bucket' => (string) $row->bucket,
                'hits' => (int) $row->hits,
                'blocked' => (int) $row->blocked,
                'warned' => (int) $row->warned,
                'logged' => (int) $row->logged,
            ];
            $series[] = $item;
            foreach ($total as $k => $v) {
                $total[$k] += $item[$k];
            }
        }

        return $this->success([
            'from' => $from,
            'to' => $to,
            'group_by' => $request->get('group_by', 'day') === 'hour' ? 'hour' : 'day',
            'series' => $series,
            'total' => $total,
            'block_rate' => $total['hits'] > 0 ? round($total['blocked'] / $total['hits'] * 100, 2) : 0,
        ]);
    }

    /**
     * @Apidoc\Title("命中趋势（按规则类型分色）")
     * @Apidoc\Desc("type => [{bucket, hits}]；rule_type 过滤可选")
     */
    public function hitTrend(Request $request): Response
    {
        $from = (string) $request->get('from', date('Y-m-d H:i:s', strtotime('-7 days')));
        $to = (string) $request->get('to', date('Y-m-d H:i:s'));
        $ruleType = (string) $request->get('rule_type', '');

        $query = RiskLog::where('created_at', '>=', $from)->where('created_at', '<=', $to);
        if ($ruleType !== '') {
            $query->where('type', $ruleType);
        }
        $rows = $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as bucket, type, count(*) as hits")
            ->groupBy('bucket', 'type')->orderBy('bucket')->get()->all();

        $series = [];
        foreach ($rows as $row) {
            $series[(string) $row->type][] = ['bucket' => (string) $row->bucket, 'hits' => (int) $row->hits];
        }

        return $this->success(['from' => $from, 'to' => $to, 'rule_type' => $ruleType, 'series' => $series]);
    }

    /**
     * @Apidoc\Title("动作分布")
     * @Apidoc\Desc("action => 计数/占比（log/warn/block）")
     */
    public function actionDistribution(Request $request): Response
    {
        $from = (string) $request->get('from', date('Y-m-d H:i:s', strtotime('-7 days')));
        $to = (string) $request->get('to', date('Y-m-d H:i:s'));

        $rows = RiskLog::where('created_at', '>=', $from)->where('created_at', '<=', $to)
            ->selectRaw('action, count(*) as cnt')->groupBy('action')->get()->all();

        $total = array_sum(array_map(static fn ($r) => (int) $r->cnt, $rows));
        $items = array_map(static fn ($r) => [
            'action' => (string) $r->action,
            'count' => (int) $r->cnt,
            'ratio' => $total > 0 ? round((int) $r->cnt / $total * 100, 2) : 0,
        ], $rows);

        return $this->success(['from' => $from, 'to' => $to, 'total' => $total, 'items' => $items]);
    }

    /**
     * @Apidoc\Title("规则效果")
     * @Apidoc\Desc("每规则命中/阻断/误判（manual_review）及比率；误判率口径：result=manual_review 占命中数")
     */
    public function rulePerformance(Request $request): Response
    {
        $from = (string) $request->get('from', date('Y-m-d H:i:s', strtotime('-7 days')));
        $to = (string) $request->get('to', date('Y-m-d H:i:s'));

        $stats = RiskLog::where('created_at', '>=', $from)->where('created_at', '<=', $to)
            ->selectRaw('rule_id, count(*) as hits, sum(action = "block") as blocked, sum(result = "manual_review") as manual_review')
            ->groupBy('rule_id')->get()->keyBy('rule_id');

        $rules = RiskRule::orderBy('priority', 'desc')->get()->all();
        $items = [];
        foreach ($rules as $rule) {
            $s = $stats[(int) $rule->id] ?? null;
            $hits = (int) ($s->hits ?? 0);
            $blocked = (int) ($s->blocked ?? 0);
            $manual = (int) ($s->manual_review ?? 0);
            $items[] = [
                'id' => $this->encodeId((int) $rule->id),
                'name' => (string) $rule->name,
                'type' => (string) $rule->type,
                'action' => (string) $rule->action,
                'priority' => (int) $rule->priority,
                'status' => (int) $rule->status,
                'hits' => $hits,
                'blocked' => $blocked,
                'block_rate' => $hits > 0 ? round($blocked / $hits * 100, 2) : 0,
                'manual_review' => $manual,
                'manual_review_rate' => $hits > 0 ? round($manual / $hits * 100, 2) : 0,
            ];
        }

        return $this->success(['from' => $from, 'to' => $to, 'items' => $items]);
    }
}
