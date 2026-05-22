<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\Game;
use common\model\GameCurrency;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("游戏管理")
 * @Apidoc\Group("game")
 */
class GameController extends BaseController
{
    /**
     * 游戏列表（分页）
     * @Apidoc\Title("游戏列表")
     * @Apidoc\Url("/admin/game/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="page", type="int", require=false, desc="页码")
     * @Apidoc\Param(name="per_page", type="int", require=false, desc="每页数量")
     * @Apidoc\Param(name="keyword", type="string", require=false, desc="关键词搜索")
     */
    public function list(Request $request): Response
    {
        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');

        $query = Game::query();
        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('sort', 'asc')
                      ->orderBy('id', 'desc')
                      ->get()
                      ->map(function ($game) {
                          $data = $game->toArray();
                          $data = $this->encodeIds($data);
                          $data['currency_count'] = $game->currencies()->count();
                          return $data;
                      });

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 创建游戏
     * @Apidoc\Title("创建游戏")
     * @Apidoc\Url("/admin/game/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="name", type="string", require=true, desc="游戏名称")
     * @Apidoc\Param(name="slug", type="string", require=true, desc="游戏标识")
     * @Apidoc\Param(name="type", type="string", require=true, desc="游戏类型: self|third_party")
     * @Apidoc\Param(name="description", type="string", require=false, desc="描述")
     * @Apidoc\Param(name="cover_image", type="string", require=false, desc="封面图")
     * @Apidoc\Param(name="api_endpoint", type="string", require=false, desc="API端点")
     * @Apidoc\Param(name="status", type="int", require=false, desc="状态: 0=禁用, 1=启用")
     * @Apidoc\Param(name="sort", type="int", require=false, desc="排序")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:50|regex:/^[a-z0-9_-]+$/',
            'type' => 'required|string|in:self,third_party',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $slug = $request->input('slug');
        if (Game::where('slug', $slug)->exists()) {
            return $this->fail('游戏标识已存在', 422);
        }

        $game = new Game();
        $game->id          = $this->generateId();
        $game->name        = $request->input('name');
        $game->slug        = $slug;
        $game->type        = $request->input('type');
        $game->description = $request->input('description', '');
        $game->cover_image = $request->input('cover_image', '');
        $game->api_endpoint = $request->input('api_endpoint', '');
        $game->api_key     = $request->input('api_key', '');
        $game->api_secret  = $request->input('api_secret', '');
        $game->status      = (int) $request->input('status', 0);
        $game->sort        = (int) $request->input('sort', 0);
        $game->save();

        return $this->success(['id' => $this->encodeId($game->id)], '创建成功');
    }

    /**
     * 更新游戏
     * @Apidoc\Title("更新游戏")
     * @Apidoc\Url("/admin/game/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="游戏哈希ID")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id   = $this->decodeId($hashid);
        $game = Game::find($id);
        if (!$game) {
            return $this->fail('游戏不存在', 404);
        }

        $game->fill($request->only([
            'name', 'type', 'description', 'cover_image',
            'api_endpoint', 'api_key', 'api_secret', 'status', 'sort',
        ]));
        $game->save();

        return $this->success([], '更新成功');
    }

    /**
     * 删除游戏
     * @Apidoc\Title("删除游戏")
     * @Apidoc\Url("/admin/game/{hashid}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="游戏哈希ID")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id   = $this->decodeId($hashid);
        $game = Game::find($id);
        if (!$game) {
            return $this->fail('游戏不存在', 404);
        }

        $game->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 管理游戏币种
     * @Apidoc\Title("管理游戏币种")
     * @Apidoc\Url("/admin/game/currency/manage")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="game_id", type="string", require=true, desc="游戏哈希ID")
     * @Apidoc\Param(name="currencies", type="array", require=true, desc="币种配置数组")
     */
    public function manageCurrency(Request $request): Response
    {
        $validator = validator($request->all(), [
            'game_id'    => 'required|string',
            'currencies' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $gameId = $this->decodeId($request->input('game_id'));
        $game   = Game::find($gameId);
        if (!$game) {
            return $this->fail('游戏不存在', 404);
        }

        $currencies = $request->input('currencies', []);

        foreach ($currencies as $item) {
            if (!empty($item['id'])) {
                $currencyId = $this->decodeId($item['id']);
                $currency   = GameCurrency::where('game_id', $gameId)->find($currencyId);
                if ($currency) {
                    $currency->fill([
                        'name'          => $item['name'] ?? $currency->name,
                        'symbol'        => $item['symbol'] ?? $currency->symbol,
                        'exchange_rate' => $item['exchange_rate'] ?? $currency->exchange_rate,
                        'spread_pct'    => $item['spread_pct'] ?? $currency->spread_pct,
                        'min_exchange'  => $item['min_exchange'] ?? $currency->min_exchange,
                        'max_exchange'  => $item['max_exchange'] ?? $currency->max_exchange,
                    ]);
                    $currency->save();
                }
            } else {
                $currency = new GameCurrency();
                $currency->id            = $this->generateId();
                $currency->game_id       = $gameId;
                $currency->name          = $item['name'] ?? '';
                $currency->symbol        = $item['symbol'] ?? '';
                $currency->exchange_rate = $item['exchange_rate'] ?? '1.00000000';
                $currency->spread_pct    = $item['spread_pct'] ?? '0.00000000';
                $currency->min_exchange  = $item['min_exchange'] ?? '0.00000000';
                $currency->max_exchange  = $item['max_exchange'] ?? '0.00000000';
                $currency->save();
            }
        }

        return $this->success([], '操作成功');
    }
}
