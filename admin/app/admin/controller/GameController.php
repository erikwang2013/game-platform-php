<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\Game;
use app\model\GameCurrency;
use support\Db;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("游戏管理")
 * @Apidoc\Group("game")
 */
class GameController extends BaseController
{
    /**
     * @Apidoc\Title("游戏列表")
     * @Apidoc\Desc("分页获取游戏列表，支持关键词搜索")
     * @Apidoc\Url("/admin/game/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("page", type="int", require=false, desc="页码")
     * @Apidoc\Param("per_page", type="int", require=false, desc="每页数量")
     * @Apidoc\Param("keyword", type="string", require=false, desc="搜索关键词")
     * @Apidoc\Returned("id", type="string", desc="ID(hashid编码)")
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
     * @Apidoc\Title("创建游戏")
     * @Apidoc\Desc("创建一个新游戏")
     * @Apidoc\Url("/admin/game/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("name", type="string", require=true, desc="游戏名称")
     * @Apidoc\Param("slug", type="string", require=true, desc="游戏标识")
     * @Apidoc\Param("type", type="string", require=true, desc="游戏类型(self,third_party)")
     * @Apidoc\Param("description", type="string", require=false, desc="游戏描述")
     * @Apidoc\Param("cover_image", type="string", require=false, desc="封面图片")
     * @Apidoc\Param("api_endpoint", type="string", require=false, desc="API端点")
     * @Apidoc\Param("status", type="int", require=false, desc="状态(0禁用,1启用)")
     * @Apidoc\Param("sort", type="int", require=false, desc="排序")
     * @Apidoc\Returned("id", type="string", desc="ID(hashid编码)")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:50|regex:/^[a-z0-9_-]+$/',
            'type' => 'required|string|in:self,embedded,third_party',
            'platform' => 'string|in:h5,unity,web,native',
            'region' => 'string|max:10',
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
        $game->sdk_version = $request->input('sdk_version', '');
        $game->platform    = $request->input('platform', 'h5');
        $game->region      = $request->input('region', 'global');
        $game->save();

        // 同步分类关系
        $this->syncGameCategories($game->id, $request->input('category_ids', []));

        return $this->success(['id' => $this->encodeId($game->id)], '创建成功');
    }

    /**
     * @Apidoc\Title("编辑游戏")
     * @Apidoc\Desc("更新游戏信息")
     * @Apidoc\Url("/admin/game/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("name", type="string", require=false, desc="游戏名称")
     * @Apidoc\Param("type", type="string", require=false, desc="游戏类型")
     * @Apidoc\Param("description", type="string", require=false, desc="游戏描述")
     * @Apidoc\Param("cover_image", type="string", require=false, desc="封面图片")
     * @Apidoc\Param("api_endpoint", type="string", require=false, desc="API端点")
     * @Apidoc\Param("status", type="int", require=false, desc="状态")
     * @Apidoc\Param("sort", type="int", require=false, desc="排序")
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
            'sdk_version', 'platform', 'region',
        ]));
        $game->save();

        // 同步分类关系
        $this->syncGameCategories($game->id, $request->input('category_ids', []));

        return $this->success([], '更新成功');
    }

    /**
     * @Apidoc\Title("删除游戏")
     * @Apidoc\Desc("删除指定游戏")
     * @Apidoc\Url("/admin/game/{hashid}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
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
     * @Apidoc\Title("管理游戏币种")
     * @Apidoc\Desc("批量管理游戏的币种设置")
     * @Apidoc\Url("/admin/game/currency/manage")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("game_id", type="string", require=true, desc="游戏ID(hashid编码)")
     * @Apidoc\Param("currencies", type="array", require=true, desc="币种数组")
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
        Db::table('game_category_rel')->where('game_id', $gameId)->delete();

        // 插入新关联
        $rows = array_map(function ($categoryId) use ($gameId) {
            return [
                'game_id'     => $gameId,
                'category_id' => $categoryId,
            ];
        }, $categoryIds);

        if (!empty($rows)) {
            Db::table('game_category_rel')->insert($rows);
        }
    }
}
