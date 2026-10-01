<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use app\model\AdminUser;
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
}
