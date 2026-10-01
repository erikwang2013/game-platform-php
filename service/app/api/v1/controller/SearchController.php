<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\Game;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;

#[Apidoc\Title("全局搜索")]
#[Apidoc\Group("search")]
class SearchController extends BaseController
{
    #[Apidoc\Title("全局搜索")]
    #[Apidoc\Url("/api/v1/search")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Param(name: "q", type: "string", require: true, desc: "搜索关键词")]
    #[Apidoc\Param(name: "page", type: "int", require: false, desc: "页码")]
    public function search(Request $request): \support\Response
    {
        $q = $request->input('q', '');
        $type = $request->input('type', 'game');
        $page = (int)$request->input('page', 1);
        $perPage = (int)$request->input('per_page', 20);

        // ⚠ 公开端点只认 game，**不看请求里的 type**。
        // 本端点在**公开组**（config/route.php:37-73，无 UserAuth），而 user 分支会把整行原样吐出去
        // —— 只 `unset($data['password'])`，`email`/`phone` 照样在（Encryptable 读回是**明文**）
        // ⇒ 不带令牌 `?type=user&q=a` 即可**按关键字批量拉取用户联系方式**。
        // 控制器自己的注释写着 user 搜索 "only for admin usage"（管理端另有自己的 SearchController）
        // ⇒ 这里一律按 game 处理。**别把它放宽回 user**：那是未鉴权的 PII 批量导出。
        $type = 'game';

        if (empty(trim($q))) {
            return $this->success(['list' => [], 'total' => 0]);
        }

        try {
            $results = Game::search($q)->where('status', 1)
                ->paginate($perPage, 'page', $page);

            $list = [];
            foreach ($results->items() as $item) {
                $data = $item->toArray();
                $data['id'] = $this->encodeId($data['id']);
                unset($data['password']);
                $list[] = $data;
            }

            return $this->success([
                'list' => $list,
                'total' => $results->total(),
                'page' => $page,
                'per_page' => $perPage,
            ]);
        } catch (\Throwable $e) {
            // Fallback to LIKE search if ES is not available
            $like = '%' . $q . '%';
            // ⚠ 两个 LIKE 必须**包进同一层闭包**。平铺的 orWhere 生成的是
            // `status=1 AND name LIKE ? OR description LIKE ?`，而 SQL 里 AND 优先于 OR
            // ⇒ 实际等价于 `(status=1 AND name LIKE ?) OR (description LIKE ?)`，
            // 描述命中的那一支**绕过 status=1** ⇒ 已下架的游戏（`game_game.status` 注释：0=下架）
            // 只要简介命中关键词就会出现在 C 端搜索结果里。
            $query = Game::where('status', 1)->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)->orWhere('description', 'like', $like);
            });

            $total = $query->count();
            $items = $query->forPage($page, $perPage)->get()->map(function ($item) {
                $data = $item->toArray();
                $data['id'] = $this->encodeId($data['id']);
                unset($data['password']);
                return $data;
            });

            return $this->success([
                'list' => $items,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ]);
        }
    }
}
