<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use app\model\AdminPermission;
use support\Request;

/**
 * @Apidoc\Title("权限管理")
 * @Apidoc\Group("permission")
 */
class PermissionController extends BaseController
{
    /**
     * 权限树
     * @Apidoc\Title("权限树")
     * @Apidoc\Url("/admin/permission")
     * @Apidoc\Method("GET")
     */
    public function index(Request $request): Response
    {
        $permissions = AdminPermission::orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        $tree = $this->buildTree($permissions);
        return $this->success($tree);
    }

    /**
     * 创建权限
     * @Apidoc\Title("创建权限")
     * @Apidoc\Url("/admin/permission")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="name", type="string", required=true, desc="权限名称")
     * @Apidoc\Param(name="slug", type="string", required=true, desc="权限标识")
     * @Apidoc\Param(name="type", type="int", required=true, desc="类型:1目录 2菜单 3按钮")
     * @Apidoc\Param(name="parent_id", type="int", required=false, desc="父级ID")
     * @Apidoc\Param(name="icon", type="string", required=false, desc="图标")
     * @Apidoc\Param(name="path", type="string", required=false, desc="前端路径")
     * @Apidoc\Param(name="sort", type="int", required=false, desc="排序")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:50',
            'slug' => 'required|string|max:100',
            'type' => 'required|in:1,2,3',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $perm = new AdminPermission();
        $perm->id = $this->generateId();
        $perm->parent_id = (int) $request->input('parent_id', 0);
        $perm->name = $request->input('name');
        $perm->slug = $request->input('slug');
        $perm->type = (int) $request->input('type');
        $perm->icon = $request->input('icon', '');
        $perm->path = $request->input('path', '');
        $perm->sort = (int) $request->input('sort', 0);
        $perm->save();

        return $this->success($this->encodeIds($perm->toArray()), '创建成功');
    }

    /**
     * 更新权限
     * @Apidoc\Title("更新权限")
     * @Apidoc\Url("/admin/permission/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="name", type="string", required=false, desc="权限名称")
     * @Apidoc\Param(name="icon", type="string", required=false, desc="图标")
     * @Apidoc\Param(name="path", type="string", required=false, desc="前端路径")
     * @Apidoc\Param(name="sort", type="int", required=false, desc="排序")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $perm = AdminPermission::find($id);
        if (!$perm) {
            return $this->fail('权限不存在', 404);
        }

        $perm->name = $request->input('name', $perm->name);
        $perm->icon = $request->input('icon', $perm->icon);
        $perm->path = $request->input('path', $perm->path);
        $perm->sort = (int) $request->input('sort', $perm->sort);
        $perm->save();

        return $this->success($this->encodeIds($perm->toArray()), '更新成功');
    }

    /**
     * 删除权限（需密码二次确认）
     * @Apidoc\Title("删除权限")
     * @Apidoc\Url("/admin/permission/{hashid}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Param(name="password", type="string", required=true, desc="管理员密码确认")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $perm = AdminPermission::find($id);
        if (!$perm) {
            return $this->fail('权限不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        // 级联删除子权限
        AdminPermission::where('parent_id', $id)->delete();
        $perm->roles()->detach();
        $perm->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 构建权限树
     */
    private function buildTree(array $permissions, int $parentId = 0): array
    {
        $tree = [];
        foreach ($permissions as $perm) {
            if ($perm['parent_id'] == $parentId) {
                $originalId = $perm['id'];
                $perm = $this->encodeIds($perm);
                $children = $this->buildTree($permissions, $originalId);
                if ($children) {
                    $perm['children'] = $children;
                }
                $tree[] = $perm;
            }
        }
        return $tree;
    }
}
