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

class AdminPermission
{
    private const CACHE_TTL = 60; // 权限缓存 60 秒

    public function process(Request $request, callable $next): Response
    {
        $adminId = $request->adminId ?? 0;
        if (!$adminId) {
            return json(['code' => 401, 'message' => '未登录', 'data' => []]);
        }

        $path = $this->stripVersionSegment($request->path());
        $method = $request->method();

        $permissions = $this->getUserPermissions($adminId);

        if (in_array('*', $permissions)) {
            return $next($request);
        }

        $requiredPermission = strtolower($method) . '.' . trim($path, '/');

        if (!in_array($requiredPermission, $permissions)) {
            return json(['code' => 403, 'message' => '无权限访问', 'data' => []]);
        }

        return $next($request);
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

    private function getUserPermissions(int $adminId): array
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
