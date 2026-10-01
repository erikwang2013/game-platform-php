<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use common\model\User;
use common\model\UserOauth;
use common\model\UserSession;
use common\model\UserWallet;
use support\Db;
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
            return $this->fail(trans('User not found'), 404);
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
            return $this->fail(trans('User not found'), 404);
        }

        $status   = $request->input('status');
        $nickname = $request->input('nickname');
        if ($status === null && $nickname === null) {
            return $this->fail(trans('No fields to update'), 422);
        }

        $data = [];
        if ($status !== null) {
            // 严格只收 0/1（收 '0'/'1' 给 form 编码留路）。**先原样比、再转 int**：
            // 反过来的话 (int)'banned' 与 (int)'normal' 都是 0 = 禁用 —— 用户点「解封」
            // 反而被封禁，是真实可达的静默改错（旧实现正是这个形状：fill 进 $casts 的 int 强转）。
            if (!in_array($status, [0, 1, '0', '1'], true)) {
                return $this->fail(trans('Invalid status value'), 422);
            }
            $data['status'] = (int) $status;
        }
        if ($nickname !== null) {
            // 列宽口径：game_user.nickname = VARCHAR(50) NOT NULL（install.sql:game_user DDL）
            if (!is_string($nickname) || mb_strlen($nickname) > 50) {
                return $this->fail(trans('Invalid nickname'), 422);
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
            return $this->success(['count' => 0], trans('Updated successfully'));
        }

        $affected = User::where('id', $id)->update($dirty);

        return $this->success(['count' => $affected], trans('Updated successfully'));
    }

    #[Apidoc\Title("注销平台用户")]
    #[Apidoc\Desc("注销平台用户：资金未结清（余额或冻结额非 0）一律 422。注销语义与 C 端自助注销一致 = 匿名化资料 + 软删除 + 清会话/OAuth；软删除后 UserAuth 的 User::find 查不到人，该用户全部端点立即 401。")]
    #[Apidoc\Url("/admin/v1/platform/user/{hashid}")]
    #[Apidoc\Method("DELETE")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Returned(name: "count", type: "int", desc: "1=本次注销成功；0=此前已注销（幂等重复调用）")]
    #[Apidoc\Returned(name: "already_deleted", type: "bool", desc: "true 表示该用户此前已注销，本次未改动任何数据")]
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);

        return Db::transaction(function () use ($id): Response {
            // withTrashed + lockForUpdate：
            //  - withTrashed 让「重复注销」可分辨（不加就被 SoftDeletes 全局作用域挡成 404，
            //    分不清「已注销」与「不存在」，幂等就无从谈起）；
            //  - lockForUpdate 把「查余额 → 软删除」之间的窗口关掉，两次并发注销也只有一次真的落库。
            $user = User::withTrashed()->lockForUpdate()->find($id);
            if (!$user) {
                return $this->fail(trans('User not found'), 404);
            }
            if ($user->trashed()) {
                return $this->success(['count' => 0, 'already_deleted' => true], trans('User account is already closed'));
            }

            // 资金闸：与 C 端自助注销同口径（service/app/api/v1/controller/UserController.php:192-196），
            // 外加 frozen_balance —— 冻结额是提现中的在途资金，只看 balance 会把在途提现变成无主订单。
            // bccomp 而非浮点比较：两列都是 DECIMAL(20,8)。
            $wallet = UserWallet::where('user_id', $id)->lockForUpdate()->first();
            if ($wallet) {
                foreach (['balance', 'frozen_balance'] as $column) {
                    if (bccomp((string) $wallet->getAttribute($column), '0', 8) > 0) {
                        return $this->fail(trans('This user still has balance or frozen funds; please settle before closing the account'), 422);
                    }
                }
            }

            // 匿名化必须在 delete() **之前**：软删除后 SoftDeletes 全局作用域会让这次 update
            // 变成空操作，PII 原样留在库里（C 端 UserController.php:198-206 就是为躲这个坑写的）。
            // 列宽：username VARCHAR(50) —— 'deleted_' + 最多 19 位雪花 = 27 字符。
            $user->update([
                'username' => 'deleted_' . $id,
                'nickname' => '',
                'avatar'   => '',
                'email'    => '',
                'phone'    => '',
            ]);

            // ⚠ 上面 email/phone 写进去的是 **admin 侧 cipher 的密文**：这两列走 Encryptable，
            // 而 admin 树解析 AES-256-CBC、service 树回退 aes-256-gcm（两树 cipher 分歧是已知项，
            // 见记忆 encryptable-cipher-divergence-admin-service）。这里之所以安全，**只是因为**
            // 注销后本行对两树的模型层都不可见（SoftDeletes 一律过滤，全仓 `withTrashed` 零引用）；
            // 若将来有人用 withTrashed 读这一行，service 侧会拿到密文而不是空串 ——
            // 解它要走 cipher 统一，不是改本端点。反向亦然：别把这里的写法当"跨树写 PII 是安全的"先例。
            $user->delete();

            // 会话与 OAuth 绑定一并清掉（与 C 端同一处置）。真正的失效来自软删除本身：
            // service 的 UserAuth.php:45 每请求 User::find($sub)，软删除后恒 null ⇒ 401。
            UserOauth::where('user_id', $id)->delete();
            UserSession::where('user_id', $id)->delete();

            return $this->success(['count' => 1, 'already_deleted' => false], trans('Account closed'));
        });
    }
}
