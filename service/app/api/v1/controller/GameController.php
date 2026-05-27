<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\Game;
use common\service\GamePlayLogService;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("Game")
 * @Apidoc\Group("game")
 */
class GameController extends BaseController
{
    /**
     * @Apidoc\Title("Game List")
     * @Apidoc\Url("/api/game/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Query(name:"page",type:"integer",require:false,desc:"Page number")
     * @Apidoc\Query(name:"per_page",type:"integer",require:false,desc:"Items per page")
     * @Apidoc\Query(name:"keyword",type:"string",require:false,desc:"Search keyword")
     * @Apidoc\Query(name:"type",type:"string",require:false,desc:"Game type filter")
     */
    public function list(Request $request): Response
    {
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);
        $keyword = $request->input('keyword');
        $type    = $request->input('type');

        $query = Game::where('status', 1)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'desc');

        if ($keyword) {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        if ($type) {
            $query->where('type', $type);
        }

        $paginator = $query->with('currencies')->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($paginator->items() as $game) {
            $currencyList = [];
            foreach ($game->currencies as $currency) {
                $currencyList[] = [
                    'id'            => $this->encodeId($currency->id),
                    'name'          => $currency->name,
                    'symbol'        => $currency->symbol,
                    'exchange_rate' => $currency->exchange_rate,
                    'min_exchange'  => $currency->min_exchange,
                    'max_exchange'  => $currency->max_exchange,
                ];
            }

            $items[] = [
                'id'          => $this->encodeId($game->id),
                'name'        => $game->name,
                'slug'        => $game->slug,
                'type'        => $game->type,
                'description' => $game->description,
                'cover_image' => $game->cover_image,
                'currencies'  => $currencyList,
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
     * @Apidoc\Title("Game Detail")
     * @Apidoc\Url("/api/game/{hashid}")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name:"hashid",type:"string",require:true,desc:"Game hashid")
     */
    public function detail(Request $request, string $hashid): Response
    {
        $gameId = $this->decodeId($hashid);

        $game = Game::with('currencies')->find($gameId);
        if (!$game) {
            return $this->fail('Game not found', 404);
        }

        $currencyList = [];
        foreach ($game->currencies as $currency) {
            $currencyList[] = [
                'id'            => $this->encodeId($currency->id),
                'name'          => $currency->name,
                'symbol'        => $currency->symbol,
                'exchange_rate' => $currency->exchange_rate,
                'spread_pct'    => $currency->spread_pct,
                'min_exchange'  => $currency->min_exchange,
                'max_exchange'  => $currency->max_exchange,
            ];
        }

        return $this->success([
            'id'           => $this->encodeId($game->id),
            'name'         => $game->name,
            'slug'         => $game->slug,
            'type'         => $game->type,
            'description'  => $game->description,
            'cover_image'  => $game->cover_image,
            'api_endpoint' => $game->api_endpoint,
            'currencies'   => $currencyList,
        ]);
    }

    /**
     * @Apidoc\Title("Launch Game")
     * @Apidoc\Url("/api/game/launch")
     * @Apidoc\Method("POST")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     * @Apidoc\Param(name:"game_id",type:"string",require:true,desc:"Game hashid")
     */
    public function launch(Request $request): Response
    {
        $validator = validator($request->all(), [
            'game_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $gameId = $this->decodeId($request->input('game_id'));

        $game = Game::find($gameId);
        if (!$game) {
            return $this->fail('Game not found', 404);
        }

        if ((int) $game->status !== 1) {
            return $this->fail('Game is not available', 403);
        }

        $userId = $request->userId ?? 0;
        GamePlayLogService::write(
            userId: $userId,
            gameId: $gameId,
            action: 'launch',
            ipAddress: $request->getRealIp() ?: '',
            userAgent: $request->header('User-Agent', ''),
        );

        return $this->success([
            'id'           => $this->encodeId($game->id),
            'name'         => $game->name,
            'slug'         => $game->slug,
            'type'         => $game->type,
            'api_endpoint' => $game->api_endpoint,
        ]);
    }
}
