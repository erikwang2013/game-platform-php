<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use app\middleware\AdminPermission as AdminPermGuard; // 中间件那个（提供 forget / forgetByRole / forgetByPermission，本文件用最后那个）；别名只因下行是**同名模型**
use app\model\AdminPermission;
use support\Request;
use support\Response;

#[Apidoc\Title("权限管理")]
#[Apidoc\Group("permission")]
class PermissionController extends BaseController
{
    #[Apidoc\Title("权限树")]
    #[Apidoc\Desc("获取完整的权限树结构")]
    #[Apidoc\Url("/admin/v1/permission")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    public function index(Request $request): Response
    {
        $permissions = AdminPermission::orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        $tree = $this->buildTree($permissions);
        return $this->success($tree);
    }

    #[Apidoc\Title("创建权限")]
    #[Apidoc\Desc("创建一个新的权限节点")]
    #[Apidoc\Url("/admin/v1/permission")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "name", type: "string", require: true, desc: "权限名称")]
    #[Apidoc\Param(name: "slug", type: "string", require: true, desc: "权限标识")]
    #[Apidoc\Param(name: "type", type: "int", require: true, desc: "权限类型(1菜单,2按钮,3接口)")]
    #[Apidoc\Param(name: "parent_id", type: "int", require: false, desc: "父权限ID")]
    #[Apidoc\Param(name: "icon", type: "string", require: false, desc: "图标")]
    #[Apidoc\Param(name: "path", type: "string", require: false, desc: "前端路由路径")]
    #[Apidoc\Param(name: "sort", type: "int", require: false, desc: "排序")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "权限ID(hashid编码)")]
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
        $perm->parent_id = $this->decodeParentId($request->input('parent_id'));
        $perm->name = $request->input('name');
        $perm->slug = $request->input('slug');
        $perm->type = (int) $request->input('type');
        $perm->icon = $request->input('icon', '');
        $perm->path = $request->input('path', '');
        $perm->sort = (int) $request->input('sort', 0);
        $perm->save();

        // 无需 forgetByPermission：新权限还没挂到任何角色上（挂载走 RoleController::update 的 permission_ids），
        // perm:{adminId} 缓存里不可能出现这个 slug。调了就是一次空查。
        return $this->success($this->encodeIds($perm->toArray()), trans('Created successfully'));
    }

    #[Apidoc\Title("更新权限")]
    #[Apidoc\Desc("更新指定权限节点的信息")]
    #[Apidoc\Url("/admin/v1/permission/{hashid}")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "name", type: "string", require: false, desc: "权限名称")]
    #[Apidoc\Param(name: "icon", type: "string", require: false, desc: "图标")]
    #[Apidoc\Param(name: "path", type: "string", require: false, desc: "前端路由路径")]
    #[Apidoc\Param(name: "sort", type: "int", require: false, desc: "排序")]
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $perm = AdminPermission::find($id);
        if (!$perm) {
            return $this->fail(trans('Permission not found'), 404);
        }

        // 镜像 store 的 name 规则（sometimes：局部更新）。update 不写 slug/type，故不校验它们；
        // icon/path/sort 是 store 漏管、update 会写的字段，按列宽收口（icon VARCHAR(50)、path VARCHAR(255)）。
        $validator = validator($request->all(), [
            'name' => 'sometimes|required|string|max:50',
            'icon' => 'sometimes|nullable|string|max:50',
            'path' => 'sometimes|nullable|string|max:255',
            'sort' => 'sometimes|nullable|integer|min:0',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 无需 forgetByPermission：本方法只写 name/icon/path/sort，**不写 slug/type/parent_id**，
        // 而 perm:{adminId} 缓存的正是一串 slug ⇒ 这里改不动任何已缓存的值（加了是纯空转）。
        $perm->name = $request->input('name', $perm->name);
        $perm->icon = $request->input('icon', $perm->icon);
        $perm->path = $request->input('path', $perm->path);
        $perm->sort = (int) $request->input('sort', $perm->sort);
        $perm->save();

        return $this->success($this->encodeIds($perm->toArray()), trans('Updated successfully'));
    }

    #[Apidoc\Title("删除权限")]
    #[Apidoc\Desc("删除指定权限节点及其子权限(需密码二次确认)")]
    #[Apidoc\Url("/admin/v1/permission/{hashid}")]
    #[Apidoc\Method("DELETE")]
    #[Apidoc\Author("erik")]
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $perm = AdminPermission::find($id);
        if (!$perm) {
            return $this->fail(trans('Permission not found'), 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''));
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        // 缓存里存的是 slug 集合 ⇒ 删权限（含级联的子权限）后，持有者最长还会认这 60 秒，
        // 而缓存里的 slug 已经没有对应权限行了。
        // ⚠ 必须在下面 detach/delete **之前**：forgetByPermission 是按 admin_permission（子权限）
        // 与 admin_role_permission 两张表反查的，行没了再查就是空转（同 RoleController::destroy）。
        // 用别名调：本文件的短名 `AdminPermission` 是那个同名**模型**，写短名会打到模型上（致命错误）。
        AdminPermGuard::forgetByPermission($id);

        // 级联删除子权限
        AdminPermission::where('parent_id', $id)->delete();
        $perm->roles()->detach();
        $perm->delete();

        return $this->success([], trans('Deleted successfully'));
    }

    /**
     * parent_id 与全站 API 约定一致，对外只认 hashid。
     *
     * 空 / 0 / '0' = 根节点（hashid 编不出 0，故 0 只能来自「不填」）。
     * 传裸数字 id 会被 decodeId 拒（400），**不会静默落成 0**——这正是修之前的行为：
     * `(int) $request->input('parent_id')` 把 UI 手里的 hashid 剁成 0，新建的权限一律挂到根，
     * 而且没有任何报错。
     */
    private function decodeParentId(mixed $raw): int
    {
        $s = is_string($raw) ? trim($raw) : (string) $raw;
        if ($s === '' || $s === '0') {
            return 0;
        }
        return $this->decodeId($s);
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
                // parent_id 也编成 hashid：前端拿到的每个 id 都应是 hashid，才能原样回填
                // （分组比较用的是上面的原始值，故这步必须在 encodeIds 之后、且只动输出）
                $perm['parent_id'] = ((int) $perm['parent_id']) > 0
                    ? $this->encodeId((int) $perm['parent_id'])
                    : 0;
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
