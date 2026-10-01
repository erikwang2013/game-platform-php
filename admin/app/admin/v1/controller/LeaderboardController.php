<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use common\model\Leaderboard;
use common\service\LeaderboardService;
use support\Request;
use support\Response;

#[Apidoc\Title("排行榜")]
#[Apidoc\Group("leaderboard")]
class LeaderboardController extends BaseController
{
    #[Apidoc\Title("排行榜列表")]
    #[Apidoc\Desc("分页获取排行榜列表，支持按游戏筛选")]
    #[Apidoc\Url("/admin/v1/leaderboard/list")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "排行榜ID(hashid编码)")]
    public function list(Request $request): Response
    {
        $page    = (int) $request->input('page', 1);
        // clamp [1,200]：200 是本仓客户端的最大合法取数（游戏/角色下拉一次拉全）；无上界时 ?limit=10000000 直接拉全表
        $limit   = min(200, max(1, (int) $request->input('limit', 15)));
        $gameIdHashid = $request->input('game_id');

        $query = Leaderboard::query();

        if ($gameIdHashid) {
            $gameId = $this->decodeId($gameIdHashid);
            $query->where('game_id', $gameId);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('sort', 'asc')
                      ->orderBy('id', 'desc')
                      ->get()
                      ->map(function ($board) {
                          $data = $board->toArray();
                          return $this->encodeIds($data);
                      });

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    #[Apidoc\Title("创建排行榜")]
    #[Apidoc\Desc("创建一个新的排行榜")]
    #[Apidoc\Url("/admin/v1/leaderboard/create")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "name", type: "string", require: true, desc: "排行榜名称")]
    #[Apidoc\Param(name: "type", type: "string", require: true, desc: "排行榜类型(daily,weekly,monthly,alltime)")]
    #[Apidoc\Param(name: "metric", type: "string", require: true, desc: "排序指标(earned,spent,play_count)")]
    #[Apidoc\Param(name: "game_id", type: "string", require: false, desc: "关联游戏ID(hashid编码)")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "排行榜ID(hashid编码)")]
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name'   => 'required|string|max:100',
            'type'   => 'required|string|in:daily,weekly,monthly,alltime',
            'metric' => 'required|string|in:earned,spent,play_count',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $gameIdHashid = $request->input('game_id');
        $gameId = 0;
        if ($gameIdHashid) {
            $gameId = $this->decodeId($gameIdHashid);
        }

        $board = new Leaderboard();
        $board->id      = $this->generateId();
        $board->name    = $request->input('name');
        $board->type    = $request->input('type');
        $board->metric  = $request->input('metric');
        $board->game_id = $gameId;
        $board->rule    = $request->input('rule', '');
        $board->status  = (int) $request->input('status', 0);
        $board->sort    = (int) $request->input('sort', 0);
        $board->save();

        return $this->success(['id' => $this->encodeId($board->id)], trans('Created successfully'));
    }

    #[Apidoc\Title("编辑排行榜")]
    #[Apidoc\Desc("更新排行榜信息")]
    #[Apidoc\Url("/admin/v1/leaderboard/{hashid}")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Author("erik")]
    public function update(Request $request, string $hashid): Response
    {
        $id    = $this->decodeId($hashid);
        $board = Leaderboard::find($id);

        if (!$board) {
            return $this->fail(trans('Leaderboard not found'), 404);
        }

        // 镜像 create 的规则（sometimes：局部更新），并补齐 create 漏掉的 status/rule/sort。
        // type 的取值沿用 create 的枚举（daily/weekly/monthly/alltime）；列注释（install.sql:818）
        // 已同步改成同一组取值，`total` 只作为建表默认值存在，走 LeaderboardService 的全时段分支。
        $validator = validator($request->all(), [
            'name'   => 'sometimes|required|string|max:100',
            'type'   => 'sometimes|required|string|in:daily,weekly,monthly,alltime',
            'metric' => 'sometimes|required|string|in:earned,spent,play_count',
            'rule'   => 'sometimes|nullable|string',
            'status' => 'sometimes|required|integer|in:0,1',
            'sort'   => 'sometimes|nullable|integer|min:0',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $board->fill($request->only([
            'name', 'type', 'metric', 'rule', 'status', 'sort',
        ]));
        $board->save();

        return $this->success([], trans('Updated successfully'));
    }

    #[Apidoc\Title("删除排行榜")]
    #[Apidoc\Desc("删除指定排行榜并清除缓存")]
    #[Apidoc\Url("/admin/v1/leaderboard/{hashid}")]
    #[Apidoc\Method("DELETE")]
    #[Apidoc\Author("erik")]
    public function destroy(Request $request, string $hashid): Response
    {
        $id    = $this->decodeId($hashid);
        $board = Leaderboard::find($id);

        if (!$board) {
            return $this->fail(trans('Leaderboard not found'), 404);
        }

        $board->delete();
        LeaderboardService::clearCache($id);

        return $this->success([], trans('Deleted successfully'));
    }

    #[Apidoc\Title("刷新排行榜缓存")]
    #[Apidoc\Desc("清除并重新计算排行榜缓存数据")]
    #[Apidoc\Url("/admin/v1/leaderboard/{hashid}/refresh")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    public function refresh(Request $request, string $hashid): Response
    {
        $id    = $this->decodeId($hashid);
        $board = Leaderboard::find($id);

        if (!$board) {
            return $this->fail(trans('Leaderboard not found'), 404);
        }

        LeaderboardService::clearCache($id);
        LeaderboardService::computeRanking($id);

        return $this->success([], trans('Refreshed successfully'));
    }
}
