<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use app\model\AdminUser;
use support\Db;
use support\Redis;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Route\Route as RouteObject;

class AdminPermission
{
    private const CACHE_TTL = 60; // 权限缓存 60 秒

    public function process(Request $request, callable $next): Response
    {
        $adminId = $request->adminId ?? 0;
        if (!$adminId) {
            return json(['code' => 401, 'message' => trans('Not logged in'), 'data' => []]);
        }

        $path = $this->stripVersionSegment($this->permissionPath($request));
        $method = $request->method();

        $permissions = $this->getUserPermissions($adminId);

        if (in_array('*', $permissions)) {
            return $next($request);
        }

        $requiredPermission = strtolower($method) . '.' . trim($path, '/');

        if (!in_array($requiredPermission, $permissions)) {
            return json(['code' => 403, 'message' => trans('Access denied'), 'data' => []]);
        }

        return $next($request);
    }

    /**
     * 取用于比对权限 slug 的路径。
     *
     * 旧实现取 `$request->path()`（真实请求路径），带占位符的路由因此**永远**算不出可授权的 slug：
     * PUT /admin/v1/user/{id} 的请求路径是 /admin/v1/user/AbC123xY，拼出的 slug 是
     * `put.admin/user/AbC123xY` —— 每个实体一个 slug，install.sql 里任何一行都命中不了。
     * 后果就是只有持 `*` 的角色能用这些端点（`put.admin/user` 等 9 条种子 slug 全是死行）。
     *
     * 改用**路由模式**（App.php:913 把 dispatch 命中的 RouteObject clone 到 $request->route），
     * 再丢掉 `{…}` 占位符段：PUT /admin/v1/user/{id} → /admin/v1/user → `put.admin/user`。
     * 静态路由的模式与请求路径逐字节相同（按 frozen_routes 逐条复算：107/107 条静态 admin 路由
     * 的新旧 slug 完全相同，且 107 条全部挂 AdminPermission），故现有授权面零漂移；
     * 顺带把实体 id 从鉴权输入里彻底移除了（旧实现里它是可被请求方影响的一段）。
     *
     * 代价：同一资源同一方法的 list 与 detail 会归一到同一个 slug
     * （GET /user 与 GET /user/{id} 都是 `get.admin/user`；全仓仅此 1 处 + anticheat/events 1 处）。
     * 这与种子「一资源一方法一 slug」的设计一致，但确实是"授予列表即授予详情"，已在报告中列明。
     */
    private function permissionPath(Request $request): string
    {
        $route = $request->route;
        if (!$route instanceof RouteObject || $route->getPath() === '') {
            // 未命中路由时 $request->route 为 null（正常请求到不了这里，兜底保留旧行为）
            return $request->path();
        }

        $segments = array_filter(
            explode('/', trim($route->getPath(), '/')),
            static fn(string $segment): bool => !preg_match('/^\{.+\}$/', $segment)
        );

        return '/' . implode('/', $segments);
    }

    /**
     * 剔除 URL 路径中的版本段（/admin/v1/user → /admin/user），保持与 DB 权限 slug 一致
     */
    private function stripVersionSegment(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        if (count($segments) > 1 && preg_match('/^v\d+$/', $segments[1])) {
            unset($segments[1]);
        }
        return '/' . implode('/', $segments);
    }

    /**
     * protected 而非 private：仅供测试覆写以注入固定权限集。
     * 测试若走真实 DB/Redis，会和并行跑套件的其它写者抢同一份 MySQL/Redis 读数。
     */
    protected function getUserPermissions(int $adminId): array
    {
        // 账号状态必须先查库、且必须在读缓存之前：缓存里可能还留着降权前的 '*'，
        // 把停用/删除判断放到缓存之后，等于给被停用的管理员留最长 60 秒的全权窗口。
        // 代价是每请求一次主键查询（走 PK 单行），换来的是封禁/删除即时生效（fail-closed）。
        $user = AdminUser::find($adminId);
        if (!$user || $user->status !== 1) return [];

        // Redis 缓存，避免每请求 N+1 查询
        $cacheKey = "perm:{$adminId}";
        try {
            $cached = Redis::get($cacheKey);
            if ($cached) {
                return json_decode($cached, true);
            }
        } catch (\Throwable) {}

        $permissions = [];
        foreach ($user->roles as $role) {
            if ($role->status === 0) continue;
            foreach ($role->permissions as $perm) {
                $permissions[] = $perm->slug;
            }
        }
        $permissions = array_unique($permissions);

        try {
            Redis::setex($cacheKey, self::CACHE_TTL, json_encode($permissions));
        } catch (\Throwable) {}

        return $permissions;
    }

    /**
     * 清掉单个管理员的权限缓存（`perm:{adminId}`）。
     *
     * ⚠ **本仓有两个同名 `AdminPermission`**：本文件是 middleware（`perm:` 缓存真身），另一个是
     *   `app\model\AdminPermission`（权限树模型，children/parent/roles）。**调用点别写短名** ——
     *   需要同时用两者的文件请用别名：
     *       use app\middleware\AdminPermission as AdminPermGuard;
     *   别名名是**文件局部**的，本仓已落地的形态（逐文件核过）：UserController / PermissionController 用
     *   `AdminPermGuard`；RoleController 只引中间件、不与同名模型撞车，故用短名，随既有风格即可。
     *   只引其中一个、且不与另一个撞车的文件，普通 `use` 即可。
     *   （`PermissionController` 已经 `use app\model\AdminPermission`，那边写短名会
     *   `Call to undefined method` 致命错误。）
     *
     * `perm:` 这个键的**唯一归属地就是本文件**：清缓存一律走本方法，别在外部手写
     * `Redis::del("perm:...")` —— 键长什么样只有一个真值源，改前缀才不会漏掉某个调用点。
     *
     * ⚠ 不要改成删 `perm:*`：`perm:` 无索引前缀，Redis 的 KEYS/SCAN 全表在生产是禁用的。
     *
     * @param int $adminId 管理员 id（BIGINT，非 hashid）
     */
    public static function forget(int $adminId): void
    {
        if ($adminId <= 0) {
            return;
        }

        try {
            Redis::del("perm:{$adminId}");
        } catch (\Throwable) {
            // Redis 不可用时中间件本来就回落查库（getUserPermissions 内 try/catch），不阻断主流程
        }
    }

    /**
     * 角色变更后清掉该角色下所有管理员的权限缓存。
     * （同名类 / 短名陷阱见 {@see forget()} 的 docblock，调用点同样适用。）
     *
     * 为什么需要：`perm:{adminId}` 的另一处失效点只覆盖「给管理员分配角色」（UserController）。
     * **角色/权限本身的变更原先没有任何失效点** ⇒ 改角色权限、删角色、删权限之后，持有者继续
     * 拿着旧权限集最长 CACHE_TTL(=60) 秒。加权限是**延迟生效**（运营看得见，会以为没保存成功）；
     * 减权限/删角色是**延迟失效**（看不见）—— 危险的是后者：已吊销的权限在一分钟窗口内仍然打得通端点。
     *
     * 按角色反查 `admin_user_role` 拿精确名单，**刻意不走 `AdminRole::find()?->users()`**：
     * 后者要求角色行还在，而「角色已经被删掉」恰恰是最需要失效的时刻（无论调用点把删除放在
     * 本调用之前还是之后），走关系会在那一刻静默空转。
     *
     * 接入点（本方法只提供实现，调用在控制器侧；传**裸 BIGINT id**，不是 hashid）：
     * - RoleController::update —— status / permission_ids 变化 ⇒ 直接 forgetByRole($id)
     * - RoleController::destroy —— **必须在 `$role->users()->detach()` 之前调**：本方法是按
     *   `admin_user_role` 反查名单的，detach 完再调等于查空表、静默空转（该方法目前把 detach
     *   放在 return 前几行）。
     * - PermissionController::destroy —— 同样**必须在 `$perm->roles()->detach()` 与
     *   `where('parent_id', $id)->delete()` **之前**先把受影响的 role_id 收下来
     *   （`admin_role_permission` where permission_id ∈ {自身 ∪ 子权限}），再逐个 forgetByRole；
     *   删权限这条最危险（缓存里留着的 slug 已无对应权限行，却仍能过 RBAC 最长 60 秒）。
     * 未接入时本方法无副作用，只是白写。
     *
     * @param int $roleId 角色 id（BIGINT，非 hashid）
     */
    public static function forgetByRole(int $roleId): void
    {
        if ($roleId <= 0) {
            return;
        }

        try {
            // 表名不带 game_ 前缀：连接层 grammar 会补（同 WalletService 的 TABLE_* 常量口径）
            $adminIds = Db::table('admin_user_role')
                ->where('role_id', $roleId)
                ->pluck('user_id')
                ->all();
        } catch (\Throwable) {
            // DB 不可用时中间件本来就回落查库（getUserPermissions 内 try/catch），不阻断主流程
            return;
        }

        foreach ($adminIds as $adminId) {
            self::forget((int) $adminId);
        }
    }

    /**
     * 权限（含其直接子权限）变更后，清掉所有挂过这些权限的角色名下管理员的缓存。
     * （同名类 / 短名陷阱见 {@see forget()} 的 docblock，调用点同样适用。）
     *
     * 为什么需要：删一个父权限会级联带走子权限（见 PermissionController::destroy），而
     * `perm:{adminId}` 里存的是 slug 集合 —— 持权路径是「管理员 → 角色 → 权限」，受影响的管理员
     * 完全可能挂在「只授了那个子权限」的角色上。让**调用方**自己去收「受影响的 role_id 集合」
     * ＝把「缓存键怎么构成」的知识泄到控制器里，而收漏一个来源、或收晚一步，都是**静默空转**、不报错。
     *
     * ⚠ 必须早于以下两件事，否则本方法自己就查不到名单（同样静默空转）：
     *   - `admin_role_permission` 的 detach（`$perm->roles()->detach()`）
     *   - 子权限行的 delete（本方法按 parent_id 反查子权限）
     * 本方法自身不做任何删除，只读以上两张表；它读的是「此刻还在不在」，故顺序由调用点保证。
     *
     * 子权限口径与 PermissionController::destroy 的级联删除一致：只收**直接**子权限
     * （destroy 也只删一层，孙节点不会被删 ⇒ 不在受影响集合里）。哪天级联改成递归，这里要一起改。
     *
     * @param int $permissionId 权限 id（BIGINT，非 hashid）
     */
    public static function forgetByPermission(int $permissionId): void
    {
        if ($permissionId <= 0) {
            return;
        }

        try {
            // 表名不带 game_ 前缀：连接层 grammar 会补（同 forgetByRole 与 WalletService 的口径）
            $childIds = Db::table('admin_permission')
                ->where('parent_id', $permissionId)
                ->pluck('id')
                ->all();

            $roleIds = Db::table('admin_role_permission')
                ->whereIn('permission_id', array_merge([$permissionId], $childIds))
                ->distinct()
                ->pluck('role_id')
                ->all();
        } catch (\Throwable) {
            // DB 不可用时中间件本来就回落查库（getUserPermissions 内 try/catch），不阻断主流程
            return;
        }

        // 逐个走 forgetByRole：它自己会按 admin_user_role 反查成员，
        // 「谁能看到这个角色」这件事同样只有一个真值源，别在这里再写一遍反查。
        foreach ($roleIds as $roleId) {
            self::forgetByRole((int) $roleId);
        }
    }
}
