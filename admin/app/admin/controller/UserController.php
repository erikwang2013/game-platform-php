<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use app\model\AdminUser;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("管理员用户")
 * @Apidoc\Group("admin_user")
 */
class UserController extends BaseController
{
    /**
     * @Apidoc\Title("管理员列表")
     * @Apidoc\Desc("分页获取管理员用户列表，支持关键词搜索和状态筛选")
     * @Apidoc\Url("/admin/user")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("page", type="int", require=false, desc="页码")
     * @Apidoc\Param("per_page", type="int", require=false, desc="每页数量")
     * @Apidoc\Param("keyword", type="string", require=false, desc="搜索关键词(用户名/真实姓名)")
     * @Apidoc\Param("status", type="int", require=false, desc="状态(0禁用,1启用)")
     * @Apidoc\Returned("id", type="string", desc="用户ID(hashid编码)")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = AdminUser::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                  ->orWhere('real_name', 'like', "%{$keyword}%");
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
                          unset($data['password'], $data['id_card']);
                          // 脱敏处理（Encryptable cast 已自动解密，直接对明文脱敏）
                          if (!empty($data['phone'])) {
                              $data['phone'] = preg_replace('/^(\d{3})\d+(\d{4})$/', '$1****$2', $data['phone']);
                          }
                          if (!empty($data['email'])) {
                              $parts = explode('@', $data['email']);
                              $data['email'] = mb_substr($parts[0], 0, 1) . '***@' . ($parts[1] ?? '');
                          }
                          return $this->encodeIds($data);
                      });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @Apidoc\Title("创建管理员")
     * @Apidoc\Desc("创建一个新的管理员用户")
     * @Apidoc\Url("/admin/user")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("username", type="string", require=true, desc="用户名(3-50位)")
     * @Apidoc\Param("password", type="string", require=true, desc="密码(6-32位)")
     * @Apidoc\Param("real_name", type="string", require=true, desc="真实姓名")
     * @Apidoc\Param("status", type="int", require=false, desc="状态(0禁用,1启用)")
     * @Apidoc\Param("phone", type="string", require=false, desc="手机号")
     * @Apidoc\Param("email", type="string", require=false, desc="邮箱")
     * @Apidoc\Returned("id", type="string", desc="用户ID(hashid编码)")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'username' => 'required|string|min:3|max:50',
            'password' => 'required|string|min:6|max:32',
            'real_name' => 'required|string|max:50',
            'status' => 'in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $exists = AdminUser::where('username', $request->input('username'))->exists();
        if ($exists) {
            return $this->fail('用户名已存在', 422);
        }

        $user = new AdminUser();
        $user->id = $this->generateId();
        $user->username = $request->input('username');
        $user->password = password_hash($request->input('password'), PASSWORD_BCRYPT);
        $user->real_name = $request->input('real_name');
        $user->status = (int) $request->input('status', 1);
        $user->phone = $request->input('phone', '');
        $user->email = $request->input('email', '');
        $user->save();

        $data = $user->toArray();
        unset($data['password'], $data['id_card']);
        return $this->success($this->encodeIds($data), '创建成功');
    }

    /**
     * @Apidoc\Title("管理员详情")
     * @Apidoc\Desc("获取指定管理员的详细信息")
     * @Apidoc\Url("/admin/user/{hashid}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Returned("id", type="string", desc="用户ID(hashid编码)")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $user = AdminUser::find($id);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        $data = $user->toArray();
        unset($data['password'], $data['id_card']);
        // Encryptable cast 已自动解密，phone/email 直接为明文
        return $this->success($this->encodeIds($data));
    }

    /**
     * @Apidoc\Title("编辑管理员")
     * @Apidoc\Desc("更新管理员用户信息")
     * @Apidoc\Url("/admin/user/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("real_name", type="string", require=false, desc="真实姓名")
     * @Apidoc\Param("status", type="int", require=false, desc="状态(0禁用,1启用)")
     * @Apidoc\Param("password", type="string", require=false, desc="新密码(6-32位)")
     * @Apidoc\Param("phone", type="string", require=false, desc="手机号")
     * @Apidoc\Param("email", type="string", require=false, desc="邮箱")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $user = AdminUser::find($id);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        $user->real_name = $request->input('real_name', $user->real_name);
        $user->status = (int) $request->input('status', $user->status);

        if ($request->has('password') && !empty($request->input('password'))) {
            $user->password = password_hash($request->input('password'), PASSWORD_BCRYPT);
        }
        if ($request->has('phone')) {
            $user->phone = $request->input('phone', '');
        }
        if ($request->has('email')) {
            $user->email = $request->input('email', '');
        }

        $user->save();

        $data = $user->toArray();
        unset($data['password'], $data['id_card']);
        return $this->success($this->encodeIds($data), '更新成功');
    }

    /**
     * @Apidoc\Title("删除管理员")
     * @Apidoc\Desc("删除指定管理员用户(软删除，需密码二次确认)")
     * @Apidoc\Url("/admin/user/{hashid}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $user = AdminUser::find($id);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $user->delete();
        return $this->success([], '删除成功');
    }

    /**
     * @Apidoc\Title("批量删除")
     * @Apidoc\Desc("批量删除管理员用户(需密码二次确认)")
     * @Apidoc\Url("/admin/user/batch/destroy")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("ids", type="array", require=true, desc="用户ID数组(hashid编码)")
     * @Apidoc\Param("password", type="string", require=true, desc="当前管理员密码")
     */
    public function batchDestroy(Request $request): Response
    {
        $ids      = $request->input('ids', []);
        $password = $request->input('password', '');

        if (empty($ids) || !is_array($ids)) {
            return $this->fail('请选择要删除的用户', 422);
        }

        $adminId = $request->adminId ?? 0;
        $error   = $this->confirmPassword($adminId, $password, $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $decodedIds = [];
        $invalidIds = [];
        foreach ($ids as $hashid) {
            try {
                $decodedIds[] = $this->decodeId($hashid);
            } catch (\InvalidArgumentException $e) {
                $invalidIds[] = $hashid;
            }
        }
        if (!empty($invalidIds)) {
            return $this->fail('无效的ID: ' . implode(', ', $invalidIds), 422);
        }

        AdminUser::whereIn('id', $decodedIds)->delete();

        return $this->success(['count' => count($decodedIds)], '删除成功');
    }

    /**
     * @Apidoc\Title("批量启禁用")
     * @Apidoc\Desc("批量启用或禁用管理员用户")
     * @Apidoc\Url("/admin/user/batch/status")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("ids", type="array", require=true, desc="用户ID数组(hashid编码)")
     * @Apidoc\Param("status", type="int", require=true, desc="状态(0禁用,1启用)")
     */
    public function batchStatus(Request $request): Response
    {
        $ids    = $request->input('ids', []);
        $status = (int) $request->input('status', 0);

        if (empty($ids) || !is_array($ids)) {
            return $this->fail('请选择用户', 422);
        }

        if (!in_array($status, [0, 1], true)) {
            return $this->fail('状态值无效', 422);
        }

        $decodedIds = [];
        $invalidIds = [];
        foreach ($ids as $hashid) {
            try {
                $decodedIds[] = $this->decodeId($hashid);
            } catch (\InvalidArgumentException $e) {
                $invalidIds[] = $hashid;
            }
        }
        if (!empty($invalidIds)) {
            return $this->fail('无效的ID: ' . implode(', ', $invalidIds), 422);
        }

        AdminUser::whereIn('id', $decodedIds)->update(['status' => $status]);

        $label = $status === 1 ? '启用' : '禁用';
        return $this->success(['count' => count($decodedIds)], "批量{$label}成功");
    }
}
