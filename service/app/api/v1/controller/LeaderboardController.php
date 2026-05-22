<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\Leaderboard;
use common\service\LeaderboardService;
use support\Request;
use support\Response;

class LeaderboardController extends BaseController
{
    /**
     * 排行榜列表
     * GET /api/leaderboard/list
     */
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

    /**
     * 排行榜排名
     * GET /api/leaderboard/{hashid}
     */
    public function ranking(Request $request, string $hashid): Response
    {
        $boardId = $this->decodeId($hashid);

        $board = Leaderboard::find($boardId);
        if (!$board || $board->status !== 1) {
            return $this->fail('Leaderboard not found', 404);
        }

        $ranking = LeaderboardService::getRanking($boardId);

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
