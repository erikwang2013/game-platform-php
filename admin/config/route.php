<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Webman\Route;
use support\Request;

/**
 * API 路由配置
 *
 * 路由分组说明:
 * - /admin/*  管理端接口，需要 JWT 认证 + 权限校验
 * - /api/*    客户端接口（部分白名单，部分需认证）
 * - /health   健康检查（无需认证）
 *
 * API 版本策略:
 * - 版本号通过请求头 API-Version 携带（如 "v1"、"v2"），不在 URL 中体现
 * - 缺失时默认使用 v1
 * - 由 ApiVersion 中间件校验，路由闭包按版本解析对应控制器
 */

/**
 * 创建版本化 API 路由闭包
 */
function v(string $controller, string $action): \Closure
{
    return function (Request $request, ...$params) use ($controller, $action) {
        $version = $request->apiVersion ?? 'v1';
        $class = "\\app\\api\\{$version}\\controller\\{$controller}";
        return (new $class)->{$action}($request, ...$params);
    };
}

// ============================================================
// 健康检查（全局，无需认证）
// ============================================================
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// Prometheus 指标（需 JWT 认证 + 权限）
Route::get('/metrics', [app\admin\controller\MetricsController::class, 'index'])->middleware([
    app\middleware\AdminAuth::class,
    app\middleware\AdminPermission::class,
]);

// security.txt — RFC 9116 安全漏洞报告联系人
Route::get('/.well-known/security.txt', function () {
    return response(<<<'TXT'
Contact: mailto:erik@erik.xyz
Expires: 2027-12-31T23:59:59Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
TXT
    , 200, ['Content-Type' => 'text/plain; charset=utf-8']);
});

// API 文档（需 JWT 认证 + 权限）
Route::get('/api/docs', [app\admin\controller\DocsController::class, 'index'])->middleware([
    app\middleware\AdminAuth::class,
    app\middleware\AdminPermission::class,
]);

// ============================================================
// 管理端路由
// ============================================================
Route::group('/admin', function () {
    // 仪表盘
    Route::get('/dashboard', [app\admin\controller\DashboardController::class, 'index']);

    // 用户管理
    Route::resource('/user', app\admin\controller\UserController::class);
    Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
    Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

    // 角色管理
    Route::resource('/role', app\admin\controller\RoleController::class);

    // 权限管理
    Route::resource('/permission', app\admin\controller\PermissionController::class);

    // 系统配置
    Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
    Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
    Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
    Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

    // 操作日志
    Route::get('/log', [app\admin\controller\LogController::class, 'index']);

    // 个人中心
    Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
    Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
    Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

    // 导出
    Route::post('/export/excel', [app\admin\controller\ExportController::class, 'excel']);
    Route::post('/export/pdf', [app\admin\controller\ExportController::class, 'pdf']);

    // 导入
    Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

    // 文件上传
    Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

    // 平台仪表盘
    Route::get('/dashboard/platform', [app\admin\controller\DashboardController::class, 'platform']);

    // 身份认证审核
    Route::get('/identity/list', [app\admin\controller\IdentityController::class, 'list']);
    Route::put('/identity/review', [app\admin\controller\IdentityController::class, 'review']);

    // 游戏管理
    Route::get('/game/list', [app\admin\controller\GameController::class, 'list']);

    // 游戏区服管理
    Route::get('/game/server/list', [app\admin\controller\GameServerController::class, 'list']);
    Route::post('/game/server/create', [app\admin\controller\GameServerController::class, 'create']);
    Route::put('/game/server/{hashid}', [app\admin\controller\GameServerController::class, 'update']);
    Route::delete('/game/server/{hashid}', [app\admin\controller\GameServerController::class, 'destroy']);
    Route::post('/game/create', [app\admin\controller\GameController::class, 'create']);
    Route::put('/game/{hashid}', [app\admin\controller\GameController::class, 'update']);
    Route::delete('/game/{hashid}', [app\admin\controller\GameController::class, 'destroy']);
    Route::post('/game/currency/manage', [app\admin\controller\GameController::class, 'manageCurrency']);

    // 游戏分类管理
    Route::get('/game/category/list', [app\admin\controller\GameCategoryController::class, 'list']);
    Route::post('/game/category/create', [app\admin\controller\GameCategoryController::class, 'create']);
    Route::put('/game/category/{hashid}', [app\admin\controller\GameCategoryController::class, 'update']);
    Route::delete('/game/category/{hashid}', [app\admin\controller\GameCategoryController::class, 'destroy']);
    Route::post('/game/category/assign', [app\admin\controller\GameCategoryController::class, 'assignGames']);

    // 国家配置管理
    Route::get('/country/config/list', [app\admin\controller\CountryConfigController::class, 'list']);
    Route::post('/country/config/create', [app\admin\controller\CountryConfigController::class, 'create']);
    Route::put('/country/config/{hashid}', [app\admin\controller\CountryConfigController::class, 'update']);

    // 提现管理
    Route::get('/withdraw/orders', [app\admin\controller\WithdrawController::class, 'orders']);
    Route::put('/withdraw/review', [app\admin\controller\WithdrawController::class, 'review']);
    Route::put('/withdraw/switch', [app\admin\controller\WithdrawController::class, 'toggleSwitch']);
    Route::post('/withdraw/limits/set', [app\admin\controller\WithdrawController::class, 'setLimits']);
    Route::get('/withdraw/limits/list', [app\admin\controller\WithdrawController::class, 'listLimits']);
    Route::put('/withdraw/limits/{hashid}', [app\admin\controller\WithdrawController::class, 'updateLimit']);
    Route::post('/withdraw/batch-review', [app\admin\controller\WithdrawController::class, 'batchReview']);
    Route::post('/withdraw/execute-payout', [app\admin\controller\WithdrawController::class, 'executePayout']);
    Route::post('/withdraw/sync-payout', [app\admin\controller\WithdrawController::class, 'syncPayout']);

    // 支付方式管理
    Route::get('/payment/method/list', [app\admin\controller\PaymentController::class, 'list']);
    Route::post('/payment/method/toggle', [app\admin\controller\PaymentController::class, 'toggle']);
    Route::post('/payment/method/create', [app\admin\controller\PaymentController::class, 'create']);
    Route::put('/payment/method/{hashid}', [app\admin\controller\PaymentController::class, 'update']);
    Route::delete('/payment/method/{hashid}', [app\admin\controller\PaymentController::class, 'delete']);

    // CDN 厂商配置管理
    Route::get('/cdn/provider/list', [app\admin\controller\CdnProviderController::class, 'list']);
    Route::post('/cdn/provider/toggle', [app\admin\controller\CdnProviderController::class, 'toggle']);
    Route::post('/cdn/provider/create', [app\admin\controller\CdnProviderController::class, 'create']);
    Route::put('/cdn/provider/{hashid}', [app\admin\controller\CdnProviderController::class, 'update']);
    Route::delete('/cdn/provider/{hashid}', [app\admin\controller\CdnProviderController::class, 'delete']);
    Route::post('/cdn/provider/test', [app\admin\controller\CdnProviderController::class, 'test']);

    // C端用户管理
    Route::get('/platform/user/list', [app\admin\controller\PlatformUserController::class, 'list']);
    Route::get('/platform/user/{hashid}', [app\admin\controller\PlatformUserController::class, 'detail']);
    Route::put('/platform/user/{hashid}', [app\admin\controller\PlatformUserController::class, 'update']);

    // 公告管理
    Route::get('/announcement/list', [app\admin\controller\AnnouncementController::class, 'list']);
    Route::post('/announcement/create', [app\admin\controller\AnnouncementController::class, 'create']);

    // 排行榜管理
    Route::get('/leaderboard/list', [app\admin\controller\LeaderboardController::class, 'list']);
    Route::post('/leaderboard/create', [app\admin\controller\LeaderboardController::class, 'create']);
    Route::put('/leaderboard/{hashid}', [app\admin\controller\LeaderboardController::class, 'update']);
    Route::delete('/leaderboard/{hashid}', [app\admin\controller\LeaderboardController::class, 'destroy']);
    Route::post('/leaderboard/{hashid}/refresh', [app\admin\controller\LeaderboardController::class, 'refresh']);

    // 优惠券管理
    Route::get('/coupon/list', [app\admin\controller\CouponController::class, 'list']);
    Route::post('/coupon/create', [app\admin\controller\CouponController::class, 'create']);
    Route::put('/coupon/{hashid}', [app\admin\controller\CouponController::class, 'update']);
    Route::delete('/coupon/{hashid}', [app\admin\controller\CouponController::class, 'destroy']);
    Route::get('/coupon/{hashid}/stats', [app\admin\controller\CouponController::class, 'stats']);

    // VIP等级管理
    Route::get('/vip/level/list', [app\admin\controller\VipLevelController::class, 'list']);
    Route::post('/vip/level/create', [app\admin\controller\VipLevelController::class, 'create']);
    Route::put('/vip/level/{hashid}', [app\admin\controller\VipLevelController::class, 'update']);
    Route::delete('/vip/level/{hashid}', [app\admin\controller\VipLevelController::class, 'destroy']);

    // 成就管理
    Route::get('/achievement/list', [app\admin\controller\AchievementController::class, 'list']);
    Route::post('/achievement/create', [app\admin\controller\AchievementController::class, 'create']);
    Route::put('/achievement/{hashid}', [app\admin\controller\AchievementController::class, 'update']);
    Route::delete('/achievement/{hashid}', [app\admin\controller\AchievementController::class, 'destroy']);

    // 导出扩展
    Route::post('/export/users', [app\admin\controller\ExportController::class, 'exportUsers']);
    Route::post('/export/transactions', [app\admin\controller\ExportController::class, 'exportTransactions']);
    Route::post('/export/receipt', [app\admin\controller\ExportController::class, 'receipt']);

    // 全局搜索
    Route::get('/search', [app\admin\controller\SearchController::class, 'search']);

    // 数据分析（MySQL 实时聚合）
    Route::get('/analytics/overview', [app\admin\controller\AnalyticsController::class, 'overview']);
    Route::get('/analytics/game-ranking', [app\admin\controller\AnalyticsController::class, 'gameRanking']);
    Route::get('/analytics/dau-trend', [app\admin\controller\AnalyticsController::class, 'dauTrend']);
    Route::get('/analytics/hourly-trend', [app\admin\controller\AnalyticsController::class, 'hourlyTrend']);
    Route::get('/analytics/action-distribution', [app\admin\controller\AnalyticsController::class, 'actionDistribution']);
    Route::get('/analytics/revenue', [app\admin\controller\AnalyticsController::class, 'revenue']);
    Route::get('/analytics/conversion', [app\admin\controller\AnalyticsController::class, 'conversion']);
    Route::get('/analytics/probability', [app\admin\controller\AnalyticsController::class, 'probability']);
    Route::get('/analytics/retention', [app\admin\controller\AnalyticsController::class, 'retention']);
    Route::get('/analytics/funnel', [app\admin\controller\AnalyticsController::class, 'funnel']);
    Route::get('/analytics/arpu', [app\admin\controller\AnalyticsController::class, 'arpu']);
    Route::get('/analytics/economy', [app\admin\controller\AnalyticsController::class, 'economy']);

    // 工单管理
    Route::get('/ticket/list', [app\admin\controller\TicketController::class, 'list']);
    Route::get('/ticket/{hashid}', [app\admin\controller\TicketController::class, 'detail']);
    Route::post('/ticket/{hashid}/reply', [app\admin\controller\TicketController::class, 'reply']);
    Route::post('/ticket/{hashid}/close', [app\admin\controller\TicketController::class, 'close']);
    Route::post('/ticket/{hashid}/assign', [app\admin\controller\TicketController::class, 'assign']);
})->middleware([
    app\middleware\AdminAuth::class,
    app\middleware\AdminPermission::class,
    app\middleware\OperationLog::class,
]);

// ============================================================
// 公开接口（通过 API-Version 头路由到版本化控制器）
// ============================================================
Route::group('/api', function () {
    // 点击验证码
    Route::post('/captcha/generate', v('CaptchaController', 'generate'));
    Route::post('/captcha/verify', v('CaptchaController', 'verify'));

    // 认证
    Route::post('/auth/login', v('AuthController', 'login'));
    Route::post('/auth/register', v('AuthController', 'register'));
    Route::post('/auth/refresh', v('AuthController', 'refresh'));
})->middleware([
    app\middleware\ApiVersion::class,
]);

// 关闭默认路由
Route::disableDefaultRoute();
