<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\GamePlayLog;
use support\Request;
use support\Response;

class GamePlayLogController extends BaseController
{
    /**
     * 游戏记录列表（分页）
     * GET /api/game/play-logs
     */
    public function list(Request $request): Response
    {
        $userId  = $request->userId;
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);
        $gameIdHashid = $request->input('game_id');
        $action  = $request->input('action');

        $query = GamePlayLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($gameIdHashid) {
            $gameId = $this->decodeId($gameIdHashid);
            $query->where('game_id', $gameId);
        }

        if ($action) {
            $query->where('action', $action);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $items = [];

        foreach ($paginator->items() as $log) {
            $items[] = [
                'id'                 => $this->encodeId($log->id),
                'game_id'            => $this->encodeId($log->game_id),
                'server_id'          => $log->server_id ? $this->encodeId($log->server_id) : null,
                'session_id'         => $log->session_id,
                'action'             => $log->action,
                'game_amount_before' => $log->game_amount_before,
                'game_amount_change' => $log->game_amount_change,
                'game_amount_after'  => $log->game_amount_after,
                'created_at'         => $log->created_at,
            ];
        }

        return $this->success([
            'items'     => $items,
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'per_page'  => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    /**
     * 游戏记录详情
     * GET /api/game/play-log/{hashid}
     */
    public function detail(Request $request, string $hashid): Response
    {
        $userId = $request->userId;
        $id = $this->decodeId($hashid);

        $log = GamePlayLog::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$log) {
            return $this->fail('Play log not found', 404);
        }

        return $this->success([
            'id'                    => $this->encodeId($log->id),
            'user_id'               => $this->encodeId($log->user_id),
            'game_id'               => $this->encodeId($log->game_id),
            'server_id'             => $log->server_id ? $this->encodeId($log->server_id) : null,
            'session_id'            => $log->session_id,
            'action'                => $log->action,
            'game_amount_before'    => $log->game_amount_before,
            'game_amount_change'    => $log->game_amount_change,
            'game_amount_after'     => $log->game_amount_after,
            'platform_amount_change' => $log->platform_amount_change,
            'metadata'              => $log->metadata,
            'started_at'            => $log->started_at,
            'ended_at'              => $log->ended_at,
            'created_at'            => $log->created_at,
        ]);
    }
}
