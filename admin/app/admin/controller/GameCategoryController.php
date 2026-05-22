<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\GameCategory;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("游戏分类管理")
 * @Apidoc\Group("gamecategory")
 */
class GameCategoryController extends BaseController
{
    /**
     * 游戏分类列表
     * @Apidoc\Title("游戏分类列表")
     * @Apidoc\Url("/admin/game/category/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="page", type="int", required=false, desc="页码")
     * @Apidoc\Param(name="limit", type="int", required=false, desc="每页数量")
     */
    public function list(Request $request): Response
    {
        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $query = GameCategory::query();
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('sort', 'asc')
                      ->orderBy('id', 'desc')
                      ->get()
                      ->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * 创建游戏分类
     * @Apidoc\Title("创建游戏分类")
     * @Apidoc\Url("/admin/game/category/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="name", type="string", required=true, desc="分类名称")
     * @Apidoc\Param(name="slug", type="string", required=true, desc="分类标识")
     * @Apidoc\Param(name="sort", type="int", required=false, desc="排序")
     * @Apidoc\Param(name="status", type="int", required=false, desc="状态:0禁用 1启用")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:50',
            'slug' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if (GameCategory::where('slug', $request->input('slug'))->exists()) {
            return $this->fail('分类标识已存在', 422);
        }

        $category = new GameCategory();
        $category->id     = $this->generateId();
        $category->name   = $request->input('name');
        $category->slug   = $request->input('slug');
        $category->sort   = (int) $request->input('sort', 0);
        $category->status = (int) $request->input('status', 1);
        $category->save();

        return $this->success(['id' => $this->encodeId($category->id)], '创建成功');
    }

    /**
     * 更新游戏分类
     * @Apidoc\Title("更新游戏分类")
     * @Apidoc\Url("/admin/game/category/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="name", type="string", required=false, desc="分类名称")
     * @Apidoc\Param(name="sort", type="int", required=false, desc="排序")
     * @Apidoc\Param(name="status", type="int", required=false, desc="状态")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id       = $this->decodeId($hashid);
        $category = GameCategory::find($id);
        if (!$category) {
            return $this->fail('分类不存在', 404);
        }

        $category->fill($request->only(['name', 'sort', 'status']));
        $category->save();

        return $this->success([], '更新成功');
    }

    /**
     * 删除游戏分类
     * @Apidoc\Title("删除游戏分类")
     * @Apidoc\Url("/admin/game/category/{hashid}")
     * @Apidoc\Method("DELETE")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id       = $this->decodeId($hashid);
        $category = GameCategory::find($id);
        if (!$category) {
            return $this->fail('分类不存在', 404);
        }

        $category->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 分配游戏到分类
     * @Apidoc\Title("分配游戏到分类")
     * @Apidoc\Url("/admin/game/category/assign")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="category_id", type="string", required=true, desc="分类ID")
     * @Apidoc\Param(name="game_ids", type="array", required=true, desc="游戏ID列表")
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
        $category   = GameCategory::find($categoryId);
        if (!$category) {
            return $this->fail('分类不存在', 404);
        }

        $gameIds = [];
        foreach ($request->input('game_ids', []) as $hashid) {
            $gameIds[] = $this->decodeId($hashid);
        }

        $category->games()->sync($gameIds);

        return $this->success([], '分配成功');
    }
}
