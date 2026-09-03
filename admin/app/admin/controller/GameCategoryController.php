<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\GameCategory;
use support\Db;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("游戏分类")
 * @Apidoc\Group("gamecategory")
 */
class GameCategoryController extends BaseController
{
    /**
     * @Apidoc\Title("分类列表")
     * @Apidoc\Desc("获取所有游戏分类列表")
     * @Apidoc\Url("/admin/v1/game/category/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Returned("id", type="string", desc="分类ID(hashid编码)")
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
     * @Apidoc\Title("创建分类")
     * @Apidoc\Desc("创建一个新的游戏分类")
     * @Apidoc\Url("/admin/v1/game/category/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("name", type="string", require=true, desc="分类名称")
     * @Apidoc\Param("slug", type="string", require=true, desc="分类标识")
     * @Apidoc\Param("icon", type="string", require=false, desc="分类图标")
     * @Apidoc\Param("sort", type="int", require=false, desc="排序")
     * @Apidoc\Returned("id", type="string", desc="分类ID(hashid编码)")
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
     * @Apidoc\Title("编辑分类")
     * @Apidoc\Desc("更新游戏分类信息")
     * @Apidoc\Url("/admin/v1/game/category/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
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
     * @Apidoc\Title("删除分类")
     * @Apidoc\Desc("删除指定游戏分类并清理关联关系")
     * @Apidoc\Url("/admin/v1/game/category/{hashid}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id       = $this->decodeId($hashid);
        $category = GameCategory::find($id);
        if (!$category) {
            return $this->fail('分类不存在', 404);
        }

        // 移除关联关系
        Db::table('game_category_rel')->where('category_id', $id)->delete();
        $category->delete();

        return $this->success([], '删除成功');
    }

    /**
     * @Apidoc\Title("分配游戏到分类")
     * @Apidoc\Desc("将游戏批量分配到指定分类")
     * @Apidoc\Url("/admin/v1/game/category/assign")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("category_id", type="string", require=true, desc="分类ID(hashid编码)")
     * @Apidoc\Param("game_ids", type="array", require=true, desc="游戏ID数组(hashid编码)")
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
        Db::table('game_category_rel')->where('category_id', $categoryId)->delete();

        // 插入新关联
        $rows = array_map(function ($gameId) use ($categoryId) {
            return [
                'category_id' => $categoryId,
                'game_id'     => $gameId,
            ];
        }, $gameIds);

        if (!empty($rows)) {
            Db::table('game_category_rel')->insert($rows);
        }

        return $this->success([], '分配成功');
    }
}
