<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use common\model\User;
use support\Request;
use support\Response;

#[Apidoc\Title("平台用户")]
#[Apidoc\Group("platform_user")]
class PlatformUserController extends BaseController
{
    #[Apidoc\Title("平台用户列表")]
    #[Apidoc\Desc("分页获取平台(C端)用户列表，支持关键词搜索和状态筛选")]
    #[Apidoc\Url("/admin/v1/platform/user/list")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "page", type: "int", require: false, desc: "页码")]
    #[Apidoc\Param(name: "limit", type: "int", require: false, desc: "每页数量")]
    #[Apidoc\Param(name: "keyword", type: "string", require: false, desc: "搜索关键词(用户名/昵称)")]
    #[Apidoc\Param(name: "status", type: "int", require: false, desc: "状态(0禁用,1启用)")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "用户ID(hashid编码)")]
    public function list(Request $request): Response
    {
        $page    = (int) $request->input('page', 1);
        $limit   = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status  = $request->input('status');

        $query = User::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                  ->orWhere('nickname', 'like', "%{$keyword}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'desc')
                      ->get()
                      ->map(function ($user) {
                          $data = $user->toArray();
                          unset($data['password']);
                          return $this->encodeIds($data);
                      });

        return $this->success([
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    #[Apidoc\Title("用户详情")]
    #[Apidoc\Desc("获取指定平台用户的详细信息，包含钱包信息")]
    #[Apidoc\Url("/admin/v1/platform/user/{hashid}")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "用户ID(hashid编码)")]
    public function detail(Request $request, string $hashid): Response
    {
        $id   = $this->decodeId($hashid);
        $user = User::with('wallet')->find($id);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        $data = $user->toArray();
        unset($data['password']);
        $data = $this->encodeIds($data);

        if ($user->wallet) {
            $walletData = $user->wallet->toArray();
            $data['wallet'] = $this->encodeIds($walletData);
        }

        return $this->success($data);
    }

    #[Apidoc\Title("编辑/封禁用户")]
    #[Apidoc\Desc("更新平台用户的状态或昵称；status 严格只收 0/1，其它值一律 422")]
    #[Apidoc\Url("/admin/v1/platform/user/{hashid}")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "status", type: "int", require: false, desc: "用户状态(0禁用,1启用)，只收 0/1")]
    #[Apidoc\Param(name: "nickname", type: "string", require: false, desc: "用户昵称(<=50 字符)")]
    #[Apidoc\Returned(name: "count", type: "int", desc: "实际更新行数；提交与当前相同的值时为 0")]
    public function update(Request $request, string $hashid): Response
    {
        $id   = $this->decodeId($hashid);
        $user = User::find($id);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        $status   = $request->input('status');
        $nickname = $request->input('nickname');
        if ($status === null && $nickname === null) {
            return $this->fail('没有可更新的字段', 422);
        }

        $data = [];
        if ($status !== null) {
            // 严格只收 0/1（收 '0'/'1' 给 form 编码留路）。**先原样比、再转 int**：
            // 反过来的话 (int)'banned' 与 (int)'normal' 都是 0 = 禁用 —— 用户点「解封」
            // 反而被封禁，是真实可达的静默改错（旧实现正是这个形状：fill 进 $casts 的 int 强转）。
            if (!in_array($status, [0, 1, '0', '1'], true)) {
                return $this->fail('状态值无效', 422);
            }
            $data['status'] = (int) $status;
        }
        if ($nickname !== null) {
            // 列宽口径：game_user.nickname = VARCHAR(50) NOT NULL（install.sql:game_user DDL）
            if (!is_string($nickname) || mb_strlen($nickname) > 50) {
                return $this->fail('昵称无效', 422);
            }
            $data['nickname'] = $nickname;
        }

        // 同值不算改动，直接返回 0 行。不能只靠 MySQL 的 changed-rows：Eloquent 的
        // Builder::update() 会顺手写 updated_at（`set status = ?, updated_at = ?`），
        // 于是「提交和当前一样的值」也会被算成 1 行 —— 界面又回到「显示成功、实际没改」。
        $dirty = [];
        foreach ($data as $column => $value) {
            if ($value !== $user->getAttribute($column)) {
                $dirty[$column] = $value;
            }
        }
        if ($dirty === []) {
            return $this->success(['count' => 0], '更新成功');
        }

        $affected = User::where('id', $id)->update($dirty);

        return $this->success(['count' => $affected], '更新成功');
    }
}
