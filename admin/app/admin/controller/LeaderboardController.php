<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\Leaderboard;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("排行榜管理")
 * @Apidoc\Group("leaderboard")
 */
class LeaderboardController extends BaseController
{
    /**
     * 排行榜列表
     * @Apidoc\Title("排行榜列表")
     * @Apidoc\Url("/admin/leaderboard/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="page", type="int", required=false, desc="页码")
     * @Apidoc\Param(name="limit", type="int", required=false, desc="每页数量")
     */
    public function list(Request $request): Response
    {
        $page  = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $query = Leaderboard::query();
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
     * 创建排行榜
     * @Apidoc\Title("创建排行榜")
     * @Apidoc\Url("/admin/leaderboard/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="name", type="string", required=true, desc="排行榜名称")
     * @Apidoc\Param(name="type", type="string", required=true, desc="排行类型")
     * @Apidoc\Param(name="game_id", type="string", required=false, desc="关联游戏ID")
     * @Apidoc\Param(name="sort", type="int", required=false, desc="排序")
     * @Apidoc\Param(name="status", type="int", required=false, desc="状态")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:100',
            'type' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $leaderboard = new Leaderboard();
        $leaderboard->id      = $this->generateId();
        $leaderboard->name    = $request->input('name');
        $leaderboard->type    = $request->input('type');
        $leaderboard->game_id = $request->has('game_id') ? $this->decodeId($request->input('game_id')) : 0;
        $leaderboard->sort    = (int) $request->input('sort', 0);
        $leaderboard->status  = (int) $request->input('status', 1);
        $leaderboard->save();

        return $this->success(['id' => $this->encodeId($leaderboard->id)], '创建成功');
    }

    /**
     * 更新排行榜
     * @Apidoc\Title("更新排行榜")
     * @Apidoc\Url("/admin/leaderboard/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="name", type="string", required=false, desc="名称")
     * @Apidoc\Param(name="sort", type="int", required=false, desc="排序")
     * @Apidoc\Param(name="status", type="int", required=false, desc="状态")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id          = $this->decodeId($hashid);
        $leaderboard = Leaderboard::find($id);
        if (!$leaderboard) {
            return $this->fail('排行榜不存在', 404);
        }

        $leaderboard->fill($request->only(['name', 'sort', 'status']));
        $leaderboard->save();

        return $this->success([], '更新成功');
    }

    /**
     * 删除排行榜
     * @Apidoc\Title("删除排行榜")
     * @Apidoc\Url("/admin/leaderboard/{hashid}")
     * @Apidoc\Method("DELETE")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id          = $this->decodeId($hashid);
        $leaderboard = Leaderboard::find($id);
        if (!$leaderboard) {
            return $this->fail('排行榜不存在', 404);
        }

        $leaderboard->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 刷新排行榜
     * @Apidoc\Title("刷新排行榜")
     * @Apidoc\Url("/admin/leaderboard/{hashid}/refresh")
     * @Apidoc\Method("POST")
     */
    public function refresh(Request $request, string $hashid): Response
    {
        $id          = $this->decodeId($hashid);
        $leaderboard = Leaderboard::find($id);
        if (!$leaderboard) {
            return $this->fail('排行榜不存在', 404);
        }

        // 标记需要刷新，实际刷新由队列任务处理
        $leaderboard->refreshed_at = date('Y-m-d H:i:s');
        $leaderboard->save();

        return $this->success([], '刷新任务已提交');
    }
}
