<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Webman\Route;
use support\Request;

/**
 * C端 API 路由配置
 *
 * API 版本策略:
 * - 版本号通过请求头 API-Version 携带（如 "v1"）
 * - 缺失时默认使用 v1
 * - 由 ApiVersion 中间件校验
 */

function v(string $controller, string $action): \Closure
{
    return function (Request $request) use ($controller, $action) {
        $version = $request->apiVersion ?? 'v1';
        $class = "\\app\\api\\{$version}\\controller\\{$controller}";
        return (new $class)->{$action}($request);
    };
}

// 健康检查
Route::get('/health', function () {
    return json(['code' => 0, 'message' => 'ok']);
});

// 公开接口（无需认证）
Route::group('/api', function () {
    // Routes will be added in subsequent tasks
})->middleware([
    app\middleware\ApiVersion::class,
]);

Route::disableDefaultRoute();
