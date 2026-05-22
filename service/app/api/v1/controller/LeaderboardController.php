<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\Leaderboard;
use common\service\LeaderboardService;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("Leaderboard")
 * @Apidoc\Group("leaderboard")
 */
class LeaderboardController extends BaseController
{
    /**
     * @Apidoc\Title("Leaderboard List")
     * @Apidoc\Url("/api/leaderboard/list")
     * @Apidoc\Method("GET")
     */
    public function list(Request $request): Response
    {
        $boards = Leaderboard::where('status', 1)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'desc')
            ->get();

        $items = [];
        foreach ($boards as $board) {
            $items[] = [
                'id'          => $this->encodeId($board->id),
                'name'        => $board->name,
                'type'        => $board->type,
                'description' => $board->description,
                'config'      => $board->config,
            ];
        }

        return $this->success(['items' => $items]);
    }

    /**
     * @Apidoc\Title("Leaderboard Ranking")
     * @Apidoc\Url("/api/leaderboard/{hashid}")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name:"hashid",type:"string",require:true,desc:"Leaderboard hashid")
     * @Apidoc\Query(name:"period",type:"string",require:false,desc:"Period (all, daily, weekly, monthly)")
     * @Apidoc\Query(name:"page",type:"integer",require:false,desc:"Page number")
     * @Apidoc\Query(name:"per_page",type:"integer",require:false,desc:"Items per page")
     */
    public function ranking(Request $request, string $hashid): Response
    {
        $boardId = $this->decodeId($hashid);

        $board = Leaderboard::find($boardId);
        if (!$board || (int) $board->status !== 1) {
            return $this->fail('Leaderboard not found', 404);
        }

        $period  = $request->input('period', 'all');
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        // Validate period
        if (!in_array($period, ['all', 'daily', 'weekly', 'monthly'], true)) {
            return $this->fail('Invalid period. Supported: all, daily, weekly, monthly', 422);
        }

        $rankingData = LeaderboardService::getRanking($boardId, $period, $page, $perPage);

        return $this->success([
            'board'    => [
                'id'          => $this->encodeId($board->id),
                'name'        => $board->name,
                'type'        => $board->type,
                'description' => $board->description,
            ],
            'period'   => $period,
            'items'    => $rankingData['items'],
            'total'    => $rankingData['total'],
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }
}
