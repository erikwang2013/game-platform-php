<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use app\model\AdminRole;
use support\Request;

/**
 * @Apidoc\Title("角色管理")
 * @Apidoc\Group("role")
 */
class RoleController extends BaseController
{
    /**
     * @Apidoc\Title("角色列表")
     * @Apidoc\Desc("分页获取角色列表，包含关联用户数量")
     * @Apidoc\Url("/admin/v1/role")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("page", type="int", require=false, desc="页码")
     * @Apidoc\Param("per_page", type="int", require=false, desc="每页数量")
     * @Apidoc\Returned("id", type="string", desc="角色ID(hashid编码)")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $query = AdminRole::withCount('users');
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'asc')
                      ->get()
                      ->map(fn($role) => $this->encodeIds($role->toArray()));

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @Apidoc\Title("创建角色")
     * @Apidoc\Desc("创建一个新角色并分配权限")
     * @Apidoc\Url("/admin/v1/role")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("name", type="string", require=true, desc="角色名称")
     * @Apidoc\Param("slug", type="string", require=true, desc="角色标识")
     * @Apidoc\Param("description", type="string", require=false, desc="角色描述")
     * @Apidoc\Param("status", type="int", require=false, desc="状态(0禁用,1启用)")
     * @Apidoc\Param("permission_ids", type="array", require=false, desc="权限ID数组")
     * @Apidoc\Returned("id", type="string", desc="角色ID(hashid编码)")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:50',
            'slug' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $role = new AdminRole();
        $role->id = $this->generateId();
        $role->name = $request->input('name');
        $role->slug = $request->input('slug');
        $role->description = $request->input('description', '');
        $role->status = (int) $request->input('status', 1);
        $role->save();

        // 同步权限
        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->input('permission_ids', []));
        }

        return $this->success($this->encodeIds($role->toArray()), '创建成功');
    }

    /**
     * @Apidoc\Title("更新角色")
     * @Apidoc\Desc("更新角色信息并同步权限")
     * @Apidoc\Url("/admin/v1/role/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("name", type="string", require=false, desc="角色名称")
     * @Apidoc\Param("description", type="string", require=false, desc="角色描述")
     * @Apidoc\Param("status", type="int", require=false, desc="状态(0禁用,1启用)")
     * @Apidoc\Param("permission_ids", type="array", require=false, desc="权限ID数组")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $role = AdminRole::find($id);
        if (!$role) {
            return $this->fail('角色不存在', 404);
        }

        $role->name = $request->input('name', $role->name);
        $role->description = $request->input('description', $role->description);
        $role->status = (int) $request->input('status', $role->status);
        $role->save();

        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->input('permission_ids', []));
        }

        return $this->success($this->encodeIds($role->toArray()), '更新成功');
    }

    /**
     * @Apidoc\Title("删除角色")
     * @Apidoc\Desc("删除指定角色，同时解除权限和用户关联(需密码二次确认)")
     * @Apidoc\Url("/admin/v1/role/{hashid}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $role = AdminRole::find($id);
        if (!$role) {
            return $this->fail('角色不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();

        return $this->success([], '删除成功');
    }
}
