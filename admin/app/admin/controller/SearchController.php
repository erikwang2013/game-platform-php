<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("全局搜索")
 * @Apidoc\Group("search")
 */
class SearchController extends BaseController
{
    /**
     * 全局搜索
     * @Apidoc\Title("全局搜索")
     * @Apidoc\Url("/admin/search")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="keyword", type="string", required=true, desc="搜索关键词")
     * @Apidoc\Param(name="type", type="string", required=false, desc="搜索类型:user|game|order")
     */
    public function search(Request $request): Response
    {
        $keyword = $request->input('keyword', '');
        $type    = $request->input('type', '');

        if (empty($keyword)) {
            return $this->fail('请输入搜索关键词', 422);
        }

        $results = [];

        // 搜索用户
        if (empty($type) || $type === 'user') {
            $users = \app\model\AdminUser::where('username', 'like', "%{$keyword}%")
                ->orWhere('real_name', 'like', "%{$keyword}%")
                ->limit(10)
                ->get()
                ->map(function ($user) {
                    $data = $user->toArray();
                    unset($data['password'], $data['id_card']);
                    return $this->encodeIds($data);
                });
            $results['users'] = $users;
        }

        // 搜索游戏
        if (empty($type) || $type === 'game') {
            $games = \common\model\Game::where('name', 'like', "%{$keyword}%")
                ->limit(10)
                ->get()
                ->map(fn($game) => $this->encodeIds($game->toArray()));
            $results['games'] = $games;
        }

        return $this->success($results);
    }
}
