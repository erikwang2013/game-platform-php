<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\GamePlayLog;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("Game Play Log")
 * @Apidoc\Group("game")
 */
class GamePlayLogController extends BaseController
{
    /**
     * @Apidoc\Title("Game Play Logs")
     * @Apidoc\Url("/api/game/play-logs")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     * @Apidoc\Query(name:"page",type:"integer",require:false,desc:"Page number")
     * @Apidoc\Query(name:"per_page",type:"integer",require:false,desc:"Items per page")
     * @Apidoc\Query(name:"game_id",type:"string",require:false,desc:"Game hashid filter")
     * @Apidoc\Query(name:"action",type:"string",require:false,desc:"Action filter (launch, exit, spin, bet, win, etc.)")
     */
    public function list(Request $request): Response
    {
        $userId  = $request->userId;
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);
        $gameId  = $request->input('game_id');
        $action  = $request->input('action');

        $query = GamePlayLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($gameId) {
            $query->where('game_id', $this->decodeId($gameId));
        }

        if ($action) {
            $query->where('action', $action);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($paginator->items() as $log) {
            $items[] = [
                'id'         => $this->encodeId($log->id),
                'game_id'    => $log->game_id ? $this->encodeId($log->game_id) : null,
                'action'     => $log->action,
                'detail'     => $log->detail,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at,
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
     * @Apidoc\Title("Game Play Log Detail")
     * @Apidoc\Url("/api/game/play-log/{hashid}")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     * @Apidoc\Param(name:"hashid",type:"string",require:true,desc:"Play log hashid")
     */
    public function detail(Request $request, string $hashid): Response
    {
        $userId = $request->userId;
        $logId  = $this->decodeId($hashid);

        $log = GamePlayLog::where('id', $logId)
            ->where('user_id', $userId)
            ->first();

        if (!$log) {
            return $this->fail('Play log not found', 404);
        }

        return $this->success([
            'id'         => $this->encodeId($log->id),
            'game_id'    => $log->game_id ? $this->encodeId($log->game_id) : null,
            'user_id'    => $this->encodeId($log->user_id),
            'action'     => $log->action,
            'detail'     => $log->detail,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at,
        ]);
    }
}
