<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\Game;
use common\model\User;
use support\Request;

/**
 * @Apidoc\Title("搜索")
 * @Apidoc\Group("search")
 */
class SearchController extends BaseController
{
    /**
     * @Apidoc\Title("全局搜索")
     * @Apidoc\Desc("全局搜索游戏或用户，支持ES全文检索和数据库LIKE回退")
     * @Apidoc\Url("/admin/v1/search")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("q", type="string", require=true, desc="搜索关键词")
     * @Apidoc\Param("type", type="string", require=false, desc="搜索类型(game,user)")
     */
    public function search(Request $request): \support\Response
    {
        $q = $request->input('q', '');
        $type = $request->input('type', 'game');
        $page = (int)$request->input('page', 1);
        $perPage = (int)$request->input('per_page', 20);

        if (empty(trim($q))) {
            return $this->success(['list' => [], 'total' => 0]);
        }

        try {
            if ($type === 'game') {
                $results = Game::search($q)->paginate($perPage, 'page', $page);
            } else {
                $results = User::search($q)->paginate($perPage, 'page', $page);
            }
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
            $query = $type === 'game' ? Game::query() : User::query();
            $query->where('name', 'like', "%{$q}%");
            if ($type !== 'game') {
                $query->orWhere('username', 'like', "%{$q}%");
            }
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
