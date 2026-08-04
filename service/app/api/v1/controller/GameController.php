<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\model\Game;
use app\model\GamePlayLog;
use app\model\UserWallet;
use common\service\GamePlayLogService;
use hg\apidoc\annotation as Apidoc;
use support\Db;
use support\Request;
use support\Response;
use app\event\EventBus;

/**
 * @Apidoc\Title("游戏管理")
 * @Apidoc\Group("game")
 */
class GameController extends BaseController
{
    /**
     * @Apidoc\Title("游戏列表")
     * @Apidoc\Url("/api/game/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="page", type="int", require=false, desc="页码")
     * @Apidoc\Param(name="per_page", type="int", require=false, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", require=false, desc="搜索关键词")
     * @Apidoc\Param(name="type", type="string", require=false, desc="游戏类型")
     * @Apidoc\Param(name="category_id", type="string", require=false, desc="分类ID")
     */
    public function list(Request $request): Response
    {
        $page       = (int) $request->input('page', 1);
        $perPage    = (int) $request->input('per_page', 20);
        $keyword    = $request->input('keyword');
        $type       = $request->input('type');
        $categoryId = $request->input('category_id');

        $query = Game::where('status', 1)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'desc');

        if ($keyword) {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($categoryId) {
            $decodedCategoryId = $this->decodeId($categoryId);
            $gameIds = Db::table('game_category_rel')
                ->where('category_id', $decodedCategoryId)
                ->pluck('game_id')
                ->toArray();
            $query->whereIn('id', $gameIds);
        }

        $paginator = $query->with(['currencies', 'categories'])->paginate($perPage, ['*'], 'page', $page);

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

            $categoryList = [];
            foreach ($game->categories as $category) {
                $categoryList[] = [
                    'name' => $category->name,
                    'slug' => $category->slug,
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
                'categories'  => $categoryList,
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
     * @Apidoc\Title("游戏详情")
     * @Apidoc\Url("/api/game/{hashid}")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="游戏hashid", in="path")
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
     * @Apidoc\Title("启动游戏")
     * @Apidoc\Url("/api/game/launch")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="game_id", type="string", require=true, desc="游戏ID")
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

        // 生成会话 ID
        $sessionId = 'GAME_SESSION_' . date('YmdHis') . random_int(1000, 9999);

        // 获取用户游戏钱包余额
        $wallet = UserWallet::where('user_id', $request->userId)->first();
        $gameAmountBefore = $wallet ? $wallet->balance : '0.00';

        // 创建游戏记录
        $playLog = new GamePlayLog();
        $playLog->id                = $this->generateId();
        $playLog->user_id           = $request->userId;
        $playLog->game_id           = $gameId;
        $playLog->session_id        = $sessionId;
        $playLog->action            = 'start';
        $playLog->game_amount_before = $gameAmountBefore;
        $playLog->created_at        = date('Y-m-d H:i:s');
        $playLog->save();

        GamePlayLogService::write($request->userId, $gameId, 'launch', ['session_id' => $sessionId], $request->getRealIp() ?: '', $request->header('User-Agent', ''));

        EventBus::emit('game.played', ['user_id' => $request->userId, 'game_id' => $gameId, 'session_id' => $sessionId]);

        return $this->success([
            'id'            => $this->encodeId($game->id),
            'name'          => $game->name,
            'slug'          => $game->slug,
            'type'          => $game->type,
            'api_endpoint'  => $game->api_endpoint,
            'session_id'    => $sessionId,
        ]);
    }

    /**
     * @Apidoc\Title("搜索建议")
     * @Apidoc\Url("/api/game/suggest")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="q", type="string", require=true, desc="搜索关键词")
     */
    public function suggest(Request $request): \support\Response
    {
        $q = $request->input('q', '');
        if (empty(trim($q))) {
            return $this->success(['suggestions' => []]);
        }

        try {
            $games = Game::search($q)->where('status', 1)->take(5)->get();
        } catch (\Throwable $e) {
            $games = Game::where('status', 1)
                ->where('name', 'like', "%{$q}%")
                ->limit(5)->get();
        }

        $suggestions = $games->map(fn($g) => [
            'id' => $this->encodeId($g->id),
            'name' => $g->name,
            'slug' => $g->slug,
        ]);

        return $this->success(['suggestions' => $suggestions]);
    }
}
