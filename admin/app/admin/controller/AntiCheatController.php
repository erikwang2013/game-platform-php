<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\AntiCheatEvent;
use common\model\UserTrust;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("反作弊事件")
 * @Apidoc\Group("anticheat")
 */
class AntiCheatController extends BaseController
{
    /**
     * @Apidoc\Title("事件列表")
     */
    public function events(Request $request): Response
    {
        $query = AntiCheatEvent::query();
        if ($request->get('user_id')) {
            $query->where('user_id', $this->decodeId((string) $request->get('user_id')));
        }
        if ($request->get('game_id')) {
            $query->where('game_id', $this->decodeId((string) $request->get('game_id')));
        }
        if ($request->get('rule_type')) {
            $query->where('rule_type', (string) $request->get('rule_type'));
        }
        if ($request->get('status')) {
            $query->where('status', (string) $request->get('status'));
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

        return $this->success([
            'total' => $total,
            'items' => array_map(fn ($row) => $this->format($row), $items),
        ]);
    }

    /**
     * @Apidoc\Title("事件详情")
     */
    public function detail(Request $request, string $hashid): Response
    {
        $row = AntiCheatEvent::find($this->decodeId($hashid));
        if (!$row) {
            return $this->fail('事件不存在');
        }
        $data = $this->format($row);
        $data['user_trust'] = $this->trustInfo((int) $row->user_id);

        return $this->success($data);
    }

    /**
     * @Apidoc\Title("人工审核")
     * @Apidoc\Desc("status: open/confirmed/whitelisted/closed；whitelisted 需附 note（当前仅记事件，不联动信任分）")
     */
    public function review(Request $request, string $hashid): Response
    {
        $row = AntiCheatEvent::find($this->decodeId($hashid));
        if (!$row) {
            return $this->fail('事件不存在');
        }
        $status = (string) $request->post('status', '');
        if (!in_array($status, ['open', 'confirmed', 'whitelisted', 'closed'], true)) {
            return $this->fail('status 仅支持 open/confirmed/whitelisted/closed');
        }

        $row->status = $status;
        $row->reviewer_id = (int) $request->adminId;
        $row->review_note = mb_substr(trim((string) $request->post('note', '')), 0, 255);
        $row->save();

        return $this->success([
            'id' => $this->encodeId((int) $row->id),
            'status' => $row->status,
            'message' => '审核已记录',
        ]);
    }

    private function format(AntiCheatEvent $row): array
    {
        return [
            'id' => $this->encodeId((int) $row->id),
            'user_id' => $this->encodeId((int) $row->user_id),
            'game_id' => $this->encodeId((int) $row->game_id),
            'rule_type' => (string) $row->rule_type,
            'rule_name' => (string) $row->rule_name,
            'severity' => (int) $row->severity,
            'score_delta' => (int) $row->score_delta,
            'action' => (string) $row->action,
            'evidence' => json_decode((string) $row->evidence, true) ?: [],
            'round_id' => (string) $row->round_id,
            'stat_date' => (string) $row->stat_date,
            'status' => (string) $row->status,
            'review_note' => (string) $row->review_note,
            'created_at' => (string) $row->created_at,
        ];
    }

    private function trustInfo(int $userId): array
    {
        $trust = UserTrust::where('user_id', $userId)->first();
        if (!$trust) {
            return ['score' => 100, 'band' => 'normal', 'whitelisted' => 0];
        }

        return [
            'score' => (int) $trust->score,
            'band' => (string) $trust->band,
            'hit_count' => (int) $trust->hit_count,
            'whitelisted' => (int) $trust->whitelisted,
        ];
    }
}
