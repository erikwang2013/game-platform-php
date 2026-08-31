<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\Game;
use common\model\GamePlayLog;
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
        $platform   = $request->input('platform');
        $region     = $request->input('region');

        $query = Game::where('status', 1)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'desc');

        if ($keyword) {
            $query->where('name', 'like', '%' . $keyword . '%');
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($platform) {
            $query->where('platform', $platform);
        }

        if ($region) {
            $query->where('region', $region);
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
                'sdk_version' => $game->sdk_version,
                'platform'    => $game->platform,
                'region'      => $game->region,
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
            'sdk_version'  => $game->sdk_version,
            'platform'     => $game->platform,
            'region'       => $game->region,
            'currencies'   => $currencyList,
        ]);
    }

    /**
     * @Apidoc\Title("多游戏聚合余额")
     * @Apidoc\Url("/api/game/balance")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     * @Apidoc\Desc("M5: 聚合用户在各游戏（上架）中的游戏币余额")
     */
    public function balance(Request $request): Response
    {
        $wallets = Db::table('user_game_wallet w')
            ->join('game g', 'g.id', '=', 'w.game_id')
            ->join('game_currency c', 'c.id', '=', 'w.currency_id')
            ->where('w.user_id', $request->userId)
            ->where('g.status', 1)
            ->get([
                'w.game_id', 'g.name', 'g.slug', 'g.type',
                'w.currency_id', 'c.name AS currency_name', 'c.symbol',
                'w.balance', 'w.frozen_balance',
            ]);

        $games = [];
        foreach ($wallets as $w) {
            if (!isset($games[$w->game_id])) {
                $games[$w->game_id] = [
                    'game_id'    => $this->encodeId((int) $w->game_id),
                    'name'       => $w->name,
                    'slug'       => $w->slug,
                    'type'       => $w->type,
                    'currencies' => [],
                ];
            }
            $games[$w->game_id]['currencies'][] = [
                'currency_id'    => $this->encodeId((int) $w->currency_id),
                'name'           => $w->currency_name,
                'symbol'         => $w->symbol,
                'balance'        => $w->balance,
                'frozen_balance' => $w->frozen_balance,
            ];
        }

        return $this->success(['games' => array_values($games)]);
    }

    /**
     * @Apidoc\Title("签发 SDK 会话令牌")
     * @Apidoc\Url("/api/game/session")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="game_id", type="string", require=true, desc="游戏ID(hashid)")
     * @Apidoc\Desc("M5: 自研/内嵌游戏启动前签发 5 分钟 HMAC 会话令牌（SdkSessionAuth 校验）")
     */
    public function session(Request $request): Response
    {
        $gameId = $request->input('game_id', '');
        if (empty($gameId)) {
            return $this->fail('game_id required', 422);
        }
        $gameId = $this->decodeId($gameId);

        $game = Game::find($gameId);
        if (!$game || (int) $game->status !== 1) {
            return $this->fail('Game not found', 404);
        }
        if ($game->type !== 'self' && $game->type !== 'embedded') {
            return $this->fail('SDK session only for self/embedded games', 403);
        }

        $payload = rtrim(strtr(base64_encode(json_encode([
            'game_id' => $gameId,
            'user_id' => $request->userId,
            'exp'     => time() + 300,
        ], JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');

        return $this->success([
            'token'      => $payload . '.' . hash_hmac('sha256', $payload, $game->api_secret),
            'expires_in' => 300,
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
