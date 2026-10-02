<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use app\middleware\AdminPermission; // 中间件那个（提供 forget / forgetByRole）；本文件不与同名模型撞车，可用短名
use app\model\AdminRole;
use support\Request;
use support\Response;

#[Apidoc\Title("角色管理")]
#[Apidoc\Group("role")]
class RoleController extends BaseController
{
    #[Apidoc\Title("角色列表")]
    #[Apidoc\Desc("分页获取角色列表，包含关联用户数量")]
    #[Apidoc\Url("/admin/v1/role")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "page", type: "int", require: false, desc: "页码")]
    #[Apidoc\Param(name: "limit", type: "int", require: false, desc: "每页数量")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "角色ID(hashid编码)")]
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        // clamp [1,200]：200 是本仓客户端的最大合法取数（游戏/角色下拉一次拉全）；无上界时 ?limit=10000000 直接拉全表
        $limit = min(200, max(1, (int) $request->input('limit', 15)));

        $query = AdminRole::with('permissions')->withCount('users');
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
                      ->limit($limit)
                      ->orderBy('id', 'asc')
                      ->get()
                      ->map(function ($role) {
                          $data = $this->encodeIds($role->toArray());
                          // 关联数组里是裸 BIGINT id，不对外；改下 hashid 形式的 permission_ids，
                          // 前端才能把「当前已授的权限」回填进表单（否则勾选状态永远显示为空）
                          unset($data['permissions']);
                          $data['permission_ids'] = array_values(array_map(
                              fn($pid) => $this->encodeId((int) $pid),
                              $role->permissions->pluck('id')->all()
                          ));
                          return $data;
                      });

        return $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Apidoc\Title("创建角色")]
    #[Apidoc\Desc("创建一个新角色并分配权限")]
    #[Apidoc\Url("/admin/v1/role")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "name", type: "string", require: true, desc: "角色名称")]
    #[Apidoc\Param(name: "slug", type: "string", require: true, desc: "角色标识")]
    #[Apidoc\Param(name: "description", type: "string", require: false, desc: "角色描述")]
    #[Apidoc\Param(name: "status", type: "int", require: false, desc: "状态(0禁用,1启用)")]
    #[Apidoc\Param(name: "permission_ids", type: "array", require: false, desc: "权限ID数组")]
    #[Apidoc\Returned(name: "id", type: "string", desc: "角色ID(hashid编码)")]
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
            $role->permissions()->sync($this->decodePermissionIds($request->input('permission_ids', [])));
        }

        // 无需 forgetByRole：角色 id 是刚生成的雪花号，admin_user_role 里不可能有指向它的行
        //（「角色→管理员」的分配在 UserController::assignRole，那里已清缓存）。调了就是一次空查。
        return $this->success($this->encodeIds($role->toArray()), trans('Created successfully'));
    }

    #[Apidoc\Title("更新角色")]
    #[Apidoc\Desc("更新角色信息并同步权限")]
    #[Apidoc\Url("/admin/v1/role/{hashid}")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "name", type: "string", require: false, desc: "角色名称")]
    #[Apidoc\Param(name: "description", type: "string", require: false, desc: "角色描述")]
    #[Apidoc\Param(name: "status", type: "int", require: false, desc: "状态(0禁用,1启用)")]
    #[Apidoc\Param(name: "permission_ids", type: "array", require: false, desc: "权限ID数组")]
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $role = AdminRole::find($id);
        if (!$role) {
            return $this->fail(trans('Role not found'), 404);
        }

        // 镜像 store 的 name 规则（sometimes：局部更新），并补齐 store 漏管的 description/status/
        // permission_ids。status 若被写成 0 以外的值，AdminPermission.php:75 的 `$role->status === 0`
        // 判停用会失配 —— 列定义是 TINYINT UNSIGNED 0=禁用 1=启用，收成 0/1 才和那处判断对齐。
        $validator = validator($request->all(), [
            'name'           => 'sometimes|required|string|max:50',
            'description'    => 'sometimes|nullable|string|max:255',
            'status'         => 'sometimes|required|integer|in:0,1',
            'permission_ids' => 'sometimes|array',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $role->name = $request->input('name', $role->name);
        $role->description = $request->input('description', $role->description);
        $role->status = (int) $request->input('status', $role->status);
        $role->save();

        if ($request->has('permission_ids')) {
            $role->permissions()->sync($this->decodePermissionIds($request->input('permission_ids', [])));
        }

        // status / 权限集已落库 ⇒ 该角色名下管理员的 perm:{adminId} 必须立刻失效，否则最长 60 秒内
        // 「已减掉的权限」仍然打得通端点。本方法没有事务包裹，故不存在「提交之后」的更晚时机。
        AdminPermission::forgetByRole($id);

        return $this->success($this->encodeIds($role->toArray()), trans('Updated successfully'));
    }

    /**
     * permission_ids 与全站 API 约定一致，对外只认 hashid。
     *
     * 修之前 `sync($request->input('permission_ids'))` 直接吃裸数组：UI 手里只有 hashid，
     * 塞进 BIGINT 关联表会被 MySQL 静默转成 0 ⇒ **角色的权限一个都挂不上，且不报错**。
     * 非空数组里出现非法 hashid ⇒ decodeId 抛 400（fail-fast，不落半截关联）。
     */
    private function decodePermissionIds(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $hashid) {
            $ids[] = $this->decodeId((string) $hashid);
        }
        return $ids;
    }

    #[Apidoc\Title("删除角色")]
    #[Apidoc\Desc("删除指定角色，同时解除权限和用户关联(需密码二次确认)")]
    #[Apidoc\Url("/admin/v1/role/{hashid}")]
    #[Apidoc\Method("DELETE")]
    #[Apidoc\Author("erik")]
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $role = AdminRole::find($id);
        if (!$role) {
            return $this->fail(trans('Role not found'), 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''));
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        // ⚠ 必须在 users()->detach() **之前**：forgetByRole 是按 admin_user_role 反查名单的，
        // 先 detach 再调就是查空表、静默空转 —— 角色没了，但持有者缓存里的旧权限集最长还能用 60 秒。
        // （本方法没有事务包裹，调早了也不会回滚，只有「谁先谁后能查到名单」这一条约束。）
        AdminPermission::forgetByRole($id);

        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();

        return $this->success([], trans('Deleted successfully'));
    }
}
