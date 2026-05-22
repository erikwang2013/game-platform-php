<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\Game;
use common\model\GameCurrency;
use support\Db;
use support\Request;
use support\Response;

class GameController extends BaseController
{
    /**
     * 游戏列表（分页）
     * GET /admin/game/list
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
                          $data['categories'] = $game->categories()->get()->map(function ($cat) {
                              return [
                                  'name' => $cat->name,
                                  'slug' => $cat->slug,
                              ];
                          });
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
     * POST /admin/game/create
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

        // 同步分类关系
        $this->syncGameCategories($game->id, $request->input('category_ids', []));

        return $this->success(['id' => $this->encodeId($game->id)], '创建成功');
    }

    /**
     * 更新游戏
     * PUT /admin/game/{hashid}
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

        // 同步分类关系
        $this->syncGameCategories($game->id, $request->input('category_ids', []));

        return $this->success([], '更新成功');
    }

    /**
     * 删除游戏
     * DELETE /admin/game/{hashid}
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
     * POST /admin/game/currency/manage
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

    /**
     * 同步游戏分类关联
     */
    private function syncGameCategories(int $gameId, array $categoryHashids): void
    {
        if (empty($categoryHashids)) {
            return;
        }

        $categoryIds = array_map(function ($hashid) {
            return $this->decodeId($hashid);
        }, $categoryHashids);

        // 删除旧关联
        Db::table('erik_game_category_rel')->where('game_id', $gameId)->delete();

        // 插入新关联
        $rows = array_map(function ($categoryId) use ($gameId) {
            return [
                'game_id'     => $gameId,
                'category_id' => $categoryId,
            ];
        }, $categoryIds);

        if (!empty($rows)) {
            Db::table('erik_game_category_rel')->insert($rows);
        }
    }
}
