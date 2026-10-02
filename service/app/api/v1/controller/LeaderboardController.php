<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\Leaderboard;
use common\service\LeaderboardService;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

#[Apidoc\Title("排行榜")]
#[Apidoc\Group("leaderboard")]
class LeaderboardController extends BaseController
{
    #[Apidoc\Title("排行榜列表")]
    #[Apidoc\Url("/api/v1/leaderboard/list")]
    #[Apidoc\Method("GET")]
    public function list(Request $request): Response
    {
        $boards = Leaderboard::where('status', 1)
            ->orderBy('sort', 'asc')
            ->get();

        $items = [];
        foreach ($boards as $board) {
            $items[] = [
                'id'     => $this->encodeId($board->id),
                'game_id' => $board->game_id > 0 ? $this->encodeId($board->game_id) : null,
                'name'    => $board->name,
                'type'    => $board->type,
                'metric'  => $board->metric,
            ];
        }

        return $this->success(['list' => $items]);
    }

    #[Apidoc\Title("排行榜详情")]
    #[Apidoc\Url("/api/v1/leaderboard/{hashid}")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Param(name: "hashid", type: "string", require: true, desc: "排行榜hashid", in: "path")]
    public function ranking(Request $request, string $hashid): Response
    {
        $boardId = $this->decodeId($hashid);

        $board = Leaderboard::find($boardId);
        if (!$board || $board->status !== 1) {
            return $this->fail(trans('Leaderboard not found'), 404);
        }

        $ranking = LeaderboardService::getRanking($boardId);

        // user_id 是**裸 BIGINT**出网（LeaderboardService:89/110 的 selectRaw 直出），同一数组里
        // id / game_id 都过了 encodeId，只有它没有 —— 逐行补编（与本文件其余处同一口径）。
        // ⚠ 出网处改、不动 Service：同一份数组还喂 WS，且 Redis 缓存（LeaderboardService:119 的
        // setex 3600）里存的也是裸 id，在服务层改要连缓存一起处理。本端点是公开端点（无 UserAuth）
        // ⇒ 裸自增 id 直接可枚举/可统计，且与全仓 ID 出网约定不一致。
        foreach ($ranking as &$row) {
            if (isset($row['user_id'])) {
                $row['user_id'] = $this->encodeId((int) $row['user_id']);
            }
        }
        unset($row);

        return $this->success([
            'leaderboard' => [
                'id'     => $this->encodeId($board->id),
                'game_id' => $board->game_id > 0 ? $this->encodeId($board->game_id) : null,
                'name'    => $board->name,
                'type'    => $board->type,
                'metric'  => $board->metric,
            ],
            'ranking' => $ranking,
        ]);
    }
}
