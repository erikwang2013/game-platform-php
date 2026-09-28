<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use common\model\Game;
use common\model\GameCurrency;
use support\Db;
use support\Request;
use support\Response;

#[Apidoc\Title("游戏管理")]
#[Apidoc\Group("game")]
class GameController extends BaseController
{
    #[Apidoc\Title("游戏列表")]
    #[Apidoc\Desc("分页获取游戏列表，支持关键词搜索")]
    #[Apidoc\Url("/admin/v1/game/list")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "page", type: "int", require: false, desc: "页码")]
    #[Apidoc\Param(name: "limit", type: "int", require: false, desc: "每页数量")]
    #[Apidoc\Param(name: "keyword", type: "string", require: false, desc: "搜索关键词")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "ID(hashid编码)")]
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

    #[Apidoc\Title("游戏详情")]
    #[Apidoc\Desc("按 hashid 获取单个游戏详情，供管理端客户端游戏详情页使用")]
    #[Apidoc\Url("/admin/v1/game/{hashid}")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "hashid", type: "string", require: true, desc: "游戏ID(hashid编码)", in: "path")]
    public function detail(Request $request, string $hashid): Response
    {
        $game = Game::with('currencies')->find($this->decodeId($hashid));
        if (!$game) {
            return $this->fail('游戏不存在', 404);
        }

        $currencies = [];
        foreach ($game->currencies as $currency) {
            $currencies[] = [
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
            'currencies'   => $currencies,
        ]);
    }

    #[Apidoc\Title("游戏试玩预览")]
    #[Apidoc\Desc("管理端试玩入口：校验游戏可用性并回传启动信息，不产生任何用户侧副作用")]
    #[Apidoc\Url("/admin/v1/game/launch")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "game_id", type: "string", require: true, desc: "游戏ID(hashid编码)")]
    public function launch(Request $request): Response
    {
        $validator = validator($request->all(), [
            'game_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $game = Game::find($this->decodeId($request->input('game_id')));
        if (!$game) {
            return $this->fail('游戏不存在', 404);
        }

        if ((int) $game->status !== 1) {
            return $this->fail('游戏未上架', 403);
        }

        // 纯预览：管理端身份只注入 adminId（AdminAuth.php），没有 C 端 userId。
        // 照搬 C 端 launch 会拿 adminId 当 user_id 写 game_play_log 并误查 UserWallet，
        // 产生归属错误的游玩记录，故此处不做任何用户侧写入。
        return $this->success([
            'id'           => $this->encodeId($game->id),
            'name'         => $game->name,
            'slug'         => $game->slug,
            'type'         => $game->type,
            'api_endpoint' => $game->api_endpoint,
            'preview'      => true,
        ]);
    }

    #[Apidoc\Title("创建游戏")]
    #[Apidoc\Desc("创建一个新游戏")]
    #[Apidoc\Url("/admin/v1/game/create")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "name", type: "string", require: true, desc: "游戏名称")]
    #[Apidoc\Param(name: "slug", type: "string", require: true, desc: "游戏标识")]
    #[Apidoc\Param(name: "type", type: "string", require: true, desc: "游戏类型(self,embedded,third_party)")]
    #[Apidoc\Param(name: "description", type: "string", require: false, desc: "游戏描述")]
    #[Apidoc\Param(name: "cover_image", type: "string", require: false, desc: "封面图片")]
    #[Apidoc\Param(name: "api_endpoint", type: "string", require: false, desc: "API端点")]
    #[Apidoc\Param(name: "status", type: "int", require: false, desc: "状态(0禁用,1启用)")]
    #[Apidoc\Param(name: "sort", type: "int", require: false, desc: "排序")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "ID(hashid编码)")]
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

        $game->api_secret = $this->generateApiSecret((string) $game->type, (string) $request->input('api_secret', ''));

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

    #[Apidoc\Title("编辑游戏")]
    #[Apidoc\Desc("更新游戏信息")]
    #[Apidoc\Url("/admin/v1/game/{hashid}")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "name", type: "string", require: false, desc: "游戏名称")]
    #[Apidoc\Param(name: "type", type: "string", require: false, desc: "游戏类型")]
    #[Apidoc\Param(name: "description", type: "string", require: false, desc: "游戏描述")]
    #[Apidoc\Param(name: "cover_image", type: "string", require: false, desc: "封面图片")]
    #[Apidoc\Param(name: "api_endpoint", type: "string", require: false, desc: "API端点")]
    #[Apidoc\Param(name: "status", type: "int", require: false, desc: "状态")]
    #[Apidoc\Param(name: "sort", type: "int", require: false, desc: "排序")]
    public function update(Request $request, string $hashid): Response
    {
        $id   = $this->decodeId($hashid);
        $game = Game::find($id);
        if (!$game) {
            return $this->fail('游戏不存在', 404);
        }

        // 镜像 create 的规则（sometimes：局部更新），并补齐 create 漏掉、而 update 会写的字段。
        // status 收 0/1（列注释 0=下架 1=上架）；几个字符串字段按 game_game 的列宽封顶 ——
        // 超长会被 MySQL 静默截断（非严格模式）或报 1406（严格模式），两种都不是好失败模式。
        $validator = validator($request->all(), [
            'name'         => 'sometimes|required|string|max:100',
            'type'         => 'sometimes|required|string|in:self,embedded,third_party',
            'description'  => 'sometimes|nullable|string',
            'cover_image'  => 'sometimes|nullable|string|max:255',
            'api_endpoint' => 'sometimes|nullable|string|max:255',
            'api_key'      => 'sometimes|nullable|string|max:500',
            'api_secret'   => 'sometimes|nullable|string|max:500',
            'status'       => 'sometimes|required|integer|in:0,1',
            'sort'         => 'sometimes|nullable|integer|min:0',
            'sdk_version'  => 'sometimes|nullable|string|max:20',
            'platform'     => 'sometimes|string|in:h5,unity,web,native',
            'region'       => 'sometimes|string|max:10',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $data = $request->only([
            'name', 'type', 'description', 'cover_image',
            'api_endpoint', 'api_key', 'api_secret', 'status', 'sort',
            'sdk_version', 'platform', 'region',
        ]);

        // 空密钥不覆盖已有非空密钥：管理端编辑表单未填该字段时会提交空串（字段本身是 hidden，
        // 回显不出来），直接落库会静默清空正在运行的密钥，打断第三方对接与自研游戏 SDK 鉴权。
        // 注：Request::only() 用 array_key_exists，字段完全不传时本就不在 $data 中、不会清空。
        if (array_key_exists('api_secret', $data)
            && (string) $data['api_secret'] === ''
            && (string) $game->api_secret !== '') {
            unset($data['api_secret']);
        }

        $game->fill($data);

        // 改类型时补生成：third_party 的游戏密钥本来就允许为空，一旦 PUT 成 self/embedded，
        // 空密钥就成了一条「永远用不了」的自研游戏（service 侧对空密钥 fail-closed 401，
        // 令牌与回调签名都发不出来）。判据必须取**落库后**的 type 与 secret，
        // 否则就像上面那条 guard 一样只看当前库里的值而漏掉本次改动。
        // 只在真生成了才回写：非空密钥不重新加密一遍，免得白白换一次随机 IV。
        $secret    = (string) $game->api_secret;
        $generated = $this->generateApiSecret((string) $game->type, $secret);
        if ($generated !== $secret) {
            $game->api_secret = $generated;
        }

        $game->save();

        // 同步分类关系
        $this->syncGameCategories($game->id, $request->input('category_ids', []));

        return $this->success([], '更新成功');
    }

    #[Apidoc\Title("删除游戏")]
    #[Apidoc\Desc("删除指定游戏")]
    #[Apidoc\Url("/admin/v1/game/{hashid}")]
    #[Apidoc\Method("DELETE")]
    #[Apidoc\Author("erik")]
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

    #[Apidoc\Title("管理游戏币种")]
    #[Apidoc\Desc("批量管理游戏的币种设置")]
    #[Apidoc\Url("/admin/v1/game/currency/manage")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "game_id", type: "string", require: true, desc: "游戏ID(hashid编码)")]
    #[Apidoc\Param(name: "currencies", type: "array", require: true, desc: "币种数组")]
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

        // 全量校验后再落库：避免前面几条已保存、后面才拒绝的部分写入
        foreach ($currencies as $item) {
            // 汇率必须为正：0 会让 C 端卖出（out）的 bcdiv 抛除零错误，负值会算出负金额
            if (isset($item['exchange_rate'])) {
                $rate = (string) $item['exchange_rate'];
                // bcmath 遇到非规范数字串会抛 ValueError，先用正则卡住形式再用 bccomp 比较
                if (!preg_match('/^\d+(\.\d+)?$/', $rate) || bccomp($rate, '0', 8) <= 0) {
                    return $this->fail('汇率必须为大于 0 的数字', 422);
                }
            }
            // 点差百分比区间 [0, 100)
            if (isset($item['spread_pct'])) {
                $spread = (string) $item['spread_pct'];
                if (!preg_match('/^\d+(\.\d+)?$/', $spread) || bccomp($spread, '100', 8) >= 0) {
                    return $this->fail('点差百分比必须在 0（含）到 100（不含）之间', 422);
                }
            }
        }

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
     * 自研/内嵌游戏的密钥由平台生成：空密钥会让回调与 SDK 令牌的 HMAC 校验收化成人人可算的
     * hash_hmac('sha256', $str, '')，service 侧中间件已对空密钥 fail-closed，这类游戏会直接不可用。
     * 第三方游戏的密钥由对方提供，不能代生成（代生成会让平台侧签名与对方对不上）。
     *
     * create 与 update 共用这一处判据：两条路径各写一份正是本类出过缺口的地方。
     * 返回 $secret 原值表示不需要改动（调用方据此避免无谓的回写）。
     */
    private function generateApiSecret(string $type, string $secret): string
    {
        if ($secret === '' && in_array($type, ['self', 'embedded'], true)) {
            return bin2hex(random_bytes(32));
        }

        return $secret;
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
