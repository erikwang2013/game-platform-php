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
 * @Apidoc\Title("C端用户管理")
 * @Apidoc\Group("platform_user")
 */
class PlatformUserController extends BaseController
{
    /**
     * C端用户列表（分页）
     * @Apidoc\Title("C端用户列表")
     * @Apidoc\Url("/admin/platform/user/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="page", type="int", required=false, desc="页码")
     * @Apidoc\Param(name="limit", type="int", required=false, desc="每页数量")
     * @Apidoc\Param(name="keyword", type="string", required=false, desc="搜索关键词")
     * @Apidoc\Param(name="status", type="int", required=false, desc="状态:0禁用 1启用")
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
     * C端用户详情（含钱包）
     * @Apidoc\Title("C端用户详情")
     * @Apidoc\Url("/admin/platform/user/{hashid}")
     * @Apidoc\Method("GET")
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
     * 更新C端用户
     * @Apidoc\Title("更新C端用户")
     * @Apidoc\Url("/admin/platform/user/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="status", type="int", required=false, desc="状态")
     * @Apidoc\Param(name="nickname", type="string", required=false, desc="昵称")
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
