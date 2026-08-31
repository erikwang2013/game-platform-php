<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\RiskLog;
use common\model\RiskRule;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("风控事件")
 * @Apidoc\Group("risk")
 */
class RiskEventController extends BaseController
{
    /**
     * @Apidoc\Title("事件列表")
     */
    public function list(Request $request): Response
    {
        $query = RiskLog::query();
        if ($request->get('user_id')) {
            $query->where('user_id', $this->decodeId((string) $request->get('user_id')));
        }
        if ($request->get('rule_id')) {
            $query->where('rule_id', $this->decodeId((string) $request->get('rule_id')));
        }
        if ($request->get('action')) {
            $query->where('action', (string) $request->get('action'));
        }
        if ($request->get('type')) {
            $query->where('type', (string) $request->get('type'));
        }
        if ($request->get('start_time')) {
            $query->where('created_at', '>=', (string) $request->get('start_time'));
        }
        if ($request->get('end_time')) {
            $query->where('created_at', '<=', (string) $request->get('end_time'));
        }

        $page = max(1, (int) $request->get('page', 1));
        $size = min(100, max(1, (int) $request->get('size', 20)));
        $total = (clone $query)->count();
        $items = $query->orderBy('id', 'desc')->forPage($page, $size)->get()->all();

        $ruleMap = $this->ruleMap($items);

        return $this->success([
            'total' => $total,
            'items' => array_map(fn ($row) => $this->format($row, $ruleMap), $items),
        ]);
    }

    /**
     * @Apidoc\Title("事件详情")
     */
    public function detail(Request $request, string $hashid): Response
    {
        $row = RiskLog::find($this->decodeId($hashid));
        if (!$row) {
            return $this->fail('事件不存在');
        }

        return $this->success($this->format($row, $this->ruleMap([$row])));
    }

    /**
     * @Apidoc\Title("人工处置")
     * @Apidoc\Desc("risk_log 无独立审核状态列，处置动作由 OperationLog 中间件自动写入操作审计")
     */
    public function handle(Request $request, string $hashid): Response
    {
        $row = RiskLog::find($this->decodeId($hashid));
        if (!$row) {
            return $this->fail('事件不存在');
        }
        $decision = (string) $request->post('decision', '');
        if (!in_array($decision, ['approve', 'reject'], true)) {
            return $this->fail('decision 仅支持 approve/reject');
        }
        $note = mb_substr(trim((string) $request->post('note', '')), 0, 500);

        return $this->success([
            'id' => $this->encodeId((int) $row->id),
            'decision' => $decision,
            'note' => $note,
            'message' => '已记录人工处置（操作审计可查）',
        ]);
    }

    /** @var array<int,string> rule_id → name */
    private function ruleMap(array $rows): array
    {
        $ids = array_values(array_unique(array_map(static fn ($row) => (int) $row->rule_id, $rows)));

        return RiskRule::whereIn('id', $ids)->get()->pluck('name', 'id')->all();
    }

    private function format(RiskLog $row, array $ruleMap): array
    {
        return [
            'id' => $this->encodeId((int) $row->id),
            'user_id' => $this->encodeId((int) $row->user_id),
            'rule_id' => $this->encodeId((int) $row->rule_id),
            'rule_name' => (string) ($ruleMap[(int) $row->rule_id] ?? ''),
            'type' => (string) $row->type,
            'action' => (string) $row->action,
            'result' => (string) $row->result,
            'detail' => (string) $row->detail,
            'context' => json_decode((string) $row->context, true) ?: [],
            'ip_masked' => $this->maskHash((string) $row->ip_hash),
            'fp_masked' => $this->maskHash((string) $row->fp_hash),
            'created_at' => (string) $row->created_at,
        ];
    }

    private function maskHash(string $hash): string
    {
        return $hash === '' ? '' : substr($hash, 0, 8) . '****';
    }
}
