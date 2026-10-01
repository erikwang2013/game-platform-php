<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use common\model\Game;
use common\model\User;
use support\Request;

#[Apidoc\Title("搜索")]
#[Apidoc\Group("search")]
class SearchController extends BaseController
{
    #[Apidoc\Title("全局搜索")]
    #[Apidoc\Desc("全局搜索游戏或用户（数据库 LIKE 检索；本仓未接 scout/ES）")]
    #[Apidoc\Url("/admin/v1/search")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "q", type: "string", require: true, desc: "搜索关键词")]
    #[Apidoc\Param(name: "type", type: "string", require: false, desc: "搜索类型(game,user)")]
    public function search(Request $request): \support\Response
    {
        $q = $request->input('q', '');
        $type = $request->input('type', 'game');
        $page = (int)$request->input('page', 1);
        $perPage = (int)$request->input('per_page', 20);

        if (empty(trim($q))) {
            return $this->success(['list' => [], 'total' => 0]);
        }

        // ⚠ 本仓**未接 scout/ES**，下面是两条**唯一在跑**的检索路径，不是"ES 挂了时的降级"。
        // 原先这里是 `try { Game::search($q) / User::search($q) } catch (\Throwable) { … }`，
        // 但那两个方法全仓不存在（无任何模型 `use Searchable`）⇒ 每请求必抛
        // `BadMethodCallException: Call to undefined method common\model\{Game,User}::search()`，
        // 被宽 catch 吞掉后固定走下面这段。分支已删。**要真接全文检索是独立一批**
        // （挂 trait + scout 配置 + 索引同步 + 用例），别把这里重新包回 try。
        $query = $type === 'game' ? Game::query() : User::query();
        if ($type === 'game') {
            $query->where('name', 'like', "%{$q}%");
        } else {
            // ⚠ `game_user` **没有 `name` 列**（只有 username/nickname，见 install.sql 的 DDL）。
            // 原先这里不分分支地对两边都跑 `where('name', …)` ⇒ `?type=user` 必抛
            // SQLSTATE 42S22 Unknown column ⇒ 500，react 管理端搜索页的 user 页签点了就报错。
            // 修法：user 分支搜 nickname + username。
            // ⚠ **别把 email/phone 加进来**：那两列是 Encryptable，库里存的是**密文**，
            // LIKE 明文永远匹配不到，加了只会造出"搜得到却搜不着"的假象。
            // 闭包是**防御性**的，不是这里当前必须的：User 有 SoftDeletes 全局作用域，Eloquent 的
            // callScope/addNewWheresWithinGroup 会把已有 wheres 自动归组 —— 实测平铺与包闭包生成
            // **完全相同**的 SQL：`where (nickname like ? or username like ?) and deleted_at is null`。
            // 真正会栽的是**没有全局作用域**的模型：Game 平铺出来就是
            // `status = ? and name like ? or description like ?`（SQL 里 AND 优先于 OR）——
            // C 端 /search 正是栽在这上面。这里包着是为了形状统一，以及将来去掉 SoftDeletes 时不出事。
            $query->where(function ($w) use ($q) {
                $w->where('nickname', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%");
            });
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
