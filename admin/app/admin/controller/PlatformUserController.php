<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\User;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("平台用户")
 * @Apidoc\Group("platform_user")
 */
class PlatformUserController extends BaseController
{
    /**
     * @Apidoc\Title("平台用户列表")
     * @Apidoc\Desc("分页获取平台(C端)用户列表，支持关键词搜索和状态筛选")
     * @Apidoc\Url("/admin/v1/platform/user/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("page", type="int", require=false, desc="页码")
     * @Apidoc\Param("per_page", type="int", require=false, desc="每页数量")
     * @Apidoc\Param("keyword", type="string", require=false, desc="搜索关键词(用户名/昵称)")
     * @Apidoc\Param("status", type="int", require=false, desc="状态(0禁用,1启用)")
     * @Apidoc\Returned("id", type="string", desc="用户ID(hashid编码)")
     */
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

    /**
     * @Apidoc\Title("用户详情")
     * @Apidoc\Desc("获取指定平台用户的详细信息，包含钱包信息")
     * @Apidoc\Url("/admin/v1/platform/user/{hashid}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Returned("id", type="string", desc="用户ID(hashid编码)")
     */
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

    /**
     * @Apidoc\Title("编辑/封禁用户")
     * @Apidoc\Desc("更新平台用户的状态或昵称")
     * @Apidoc\Url("/admin/v1/platform/user/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("status", type="int", require=false, desc="用户状态(0禁用,1启用)")
     * @Apidoc\Param("nickname", type="string", require=false, desc="用户昵称")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id   = $this->decodeId($hashid);
        $user = User::find($id);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        $user->fill($request->only(['status', 'nickname']));
        $user->save();

        return $this->success([], '更新成功');
    }
}
