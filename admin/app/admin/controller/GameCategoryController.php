<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\GameCategory;
use support\Db;
use support\Request;
use support\Response;

class GameCategoryController extends BaseController
{
    /**
     * 游戏分类列表
     * GET /admin/game/category/list
     */
    public function list(Request $request): Response
    {
        $list = GameCategory::orderBy('sort', 'asc')
            ->get()
            ->map(function ($category) {
                $data = $category->toArray();
                return $this->encodeIds($data);
            });

        return $this->success(['list' => $list]);
    }

    /**
     * 创建游戏分类
     * POST /admin/game/category/create
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:50',
            'slug' => 'required|string|max:50|regex:/^[a-z0-9_-]+$/',
            'icon' => 'nullable|string',
            'sort' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $category = new GameCategory();
        $category->id     = $this->generateId();
        $category->name   = $request->input('name');
        $category->slug   = $request->input('slug');
        $category->icon   = $request->input('icon', '');
        $category->sort   = (int) $request->input('sort', 0);
        $category->status = 1;
        $category->save();

        return $this->success(['id' => $this->encodeId($category->id)], '创建成功');
    }

    /**
     * 更新游戏分类
     * PUT /admin/game/category/{hashid}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id       = $this->decodeId($hashid);
        $category = GameCategory::find($id);
        if (!$category) {
            return $this->fail('分类不存在', 404);
        }

        $category->fill($request->only([
            'name', 'icon', 'sort', 'status',
        ]));
        $category->save();

        return $this->success([], '更新成功');
    }

    /**
     * 删除游戏分类
     * DELETE /admin/game/category/{hashid}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id       = $this->decodeId($hashid);
        $category = GameCategory::find($id);
        if (!$category) {
            return $this->fail('分类不存在', 404);
        }

        // 移除关联关系
        Db::table('erik_game_category_rel')->where('category_id', $id)->delete();
        $category->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 为分类分配游戏
     * POST /admin/game/category/assign
     */
    public function assignGames(Request $request): Response
    {
        $validator = validator($request->all(), [
            'category_id' => 'required|string',
            'game_ids'    => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $categoryId = $this->decodeId($request->input('category_id'));
        $gameIds    = array_map(function ($hashid) {
            return $this->decodeId($hashid);
        }, $request->input('game_ids', []));

        // 删除旧关联
        Db::table('erik_game_category_rel')->where('category_id', $categoryId)->delete();

        // 插入新关联
        $rows = array_map(function ($gameId) use ($categoryId) {
            return [
                'category_id' => $categoryId,
                'game_id'     => $gameId,
            ];
        }, $gameIds);

        if (!empty($rows)) {
            Db::table('erik_game_category_rel')->insert($rows);
        }

        return $this->success([], '分配成功');
    }
}
