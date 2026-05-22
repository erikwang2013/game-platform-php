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
     * @Apidoc\Title("权限树")
     * @Apidoc\Desc("获取完整的权限树结构")
     * @Apidoc\Url("/admin/permission")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
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
     * @Apidoc\Title("创建权限")
     * @Apidoc\Desc("创建一个新的权限节点")
     * @Apidoc\Url("/admin/permission")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("name", type="string", require=true, desc="权限名称")
     * @Apidoc\Param("slug", type="string", require=true, desc="权限标识")
     * @Apidoc\Param("type", type="int", require=true, desc="权限类型(1菜单,2按钮,3接口)")
     * @Apidoc\Param("parent_id", type="int", require=false, desc="父权限ID")
     * @Apidoc\Param("icon", type="string", require=false, desc="图标")
     * @Apidoc\Param("path", type="string", require=false, desc="前端路由路径")
     * @Apidoc\Param("sort", type="int", require=false, desc="排序")
     * @Apidoc\Returned("id", type="string", desc="权限ID(hashid编码)")
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
     * @Apidoc\Title("更新权限")
     * @Apidoc\Desc("更新指定权限节点的信息")
     * @Apidoc\Url("/admin/permission/{hashid}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Param("name", type="string", require=false, desc="权限名称")
     * @Apidoc\Param("icon", type="string", require=false, desc="图标")
     * @Apidoc\Param("path", type="string", require=false, desc="前端路由路径")
     * @Apidoc\Param("sort", type="int", require=false, desc="排序")
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
     * @Apidoc\Title("删除权限")
     * @Apidoc\Desc("删除指定权限节点及其子权限(需密码二次确认)")
     * @Apidoc\Url("/admin/permission/{hashid}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
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
