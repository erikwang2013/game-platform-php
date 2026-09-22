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
 * - /admin/v1/*  管理端接口，需要 JWT 认证 + 权限校验
 * - /api/v1/*    客户端公开接口（验证码/认证）
 * - /health、/metrics、/api/docs、/.well-known/security.txt  基础设施端点（不版本化）
 *
 * API 版本策略:
 * - 版本号置于 URL 路径（如 /api/v1/*、/admin/v1/*），不再使用请求头 API-Version
 * - 版本命名空间由 v() 解析；路由注册到哪个前缀组即对外暴露哪个版本
 * - 新增 v2: 创建 app/api/v2/controller 并注册 /api/v2 组 + v('XController','x','v2')
 */

/**
 * 创建版本化 API 路由闭包
 *
 * ponytail: 闭包只能声明具名参数。Webman 反射(App.php getMethodParameterMetadata)
 * 不识别 variadic：getType() 为 null 且 isDefaultValueAvailable() 为 false，会被记成
 * “必填无默认值”输入，导致整组路由无条件抛 MissingInputException(HTTP 400)。
 * 参数绑定分两条路径：注入路径按【名字】($request->all() 与占位符 $args 均为名字键)；
 * 非注入路径由 App.php:398 `array_values($args)` 压平后按【位置】展开。分流条件是
 * `array_keys($args) !== $keys`(App.php:575)：名字或顺序对不上，参数会被静默传 null 而不报错。
 * 结论：v() 仅适用于【无占位符】路由；带 {id} 的路由请用数组可调用 [Controller::class, 'method']。
 */
function v(string $controller, string $action, string $version = 'v1'): \Closure
{
    return function (Request $request) use ($controller, $action, $version) {
        $class = "\\app\\api\\{$version}\\controller\\{$controller}";
        return (new $class)->{$action}($request);
    };
}

// ============================================================
// 健康检查（全局，无需认证）
// ============================================================
Route::get('/health', [app\admin\v1\controller\HealthController::class, 'index']);

// Prometheus 指标（需 JWT 认证 + 权限）
Route::get('/metrics', [app\admin\v1\controller\MetricsController::class, 'index'])->middleware([
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
Route::get('/api/docs', [app\admin\v1\controller\DocsController::class, 'index'])->middleware([
    app\middleware\AdminAuth::class,
    app\middleware\AdminPermission::class,
]);

// ============================================================
// 管理端路由
// ============================================================
Route::group('/admin/v1', function () {
    // 仪表盘
    Route::get('/dashboard', [app\admin\v1\controller\DashboardController::class, 'index']);

    // 用户管理
    Route::resource('/user', app\admin\v1\controller\UserController::class);
    Route::post('/user/batch/destroy', [app\admin\v1\controller\UserController::class, 'batchDestroy']);
    Route::post('/user/batch/status', [app\admin\v1\controller\UserController::class, 'batchStatus']);

    // 角色管理
    Route::resource('/role', app\admin\v1\controller\RoleController::class);

    // 权限管理
    Route::resource('/permission', app\admin\v1\controller\PermissionController::class);

    // 系统配置
    Route::get('/config', [app\admin\v1\controller\ConfigController::class, 'index']);
    Route::post('/config', [app\admin\v1\controller\ConfigController::class, 'store']);
    Route::put('/config/{id}', [app\admin\v1\controller\ConfigController::class, 'update']);
    Route::delete('/config/{id}', [app\admin\v1\controller\ConfigController::class, 'destroy']);

    // 操作日志
    Route::get('/log', [app\admin\v1\controller\LogController::class, 'index']);

    // 个人中心
    Route::put('/profile', [app\admin\v1\controller\ProfileController::class, 'updateProfile']);
    Route::put('/profile/password', [app\admin\v1\controller\ProfileController::class, 'updatePassword']);
    Route::post('/profile/logout', [app\admin\v1\controller\ProfileController::class, 'logout']);

    // 导出
    Route::post('/export/excel', [app\admin\v1\controller\ExportController::class, 'excel']);
    Route::post('/export/pdf', [app\admin\v1\controller\ExportController::class, 'pdf']);

    // 导入
    Route::post('/import/users', [app\admin\v1\controller\ImportController::class, 'users']);

    // 文件上传
    Route::post('/upload', [app\admin\v1\controller\UploadController::class, 'upload']);

    // 平台仪表盘
    Route::get('/dashboard/platform', [app\admin\v1\controller\DashboardController::class, 'platform']);

    // 身份认证审核
    Route::get('/identity/list', [app\admin\v1\controller\IdentityController::class, 'list']);
    Route::put('/identity/review', [app\admin\v1\controller\IdentityController::class, 'review']);

    // 游戏管理
    Route::get('/game/list', [app\admin\v1\controller\GameController::class, 'list']);
    // 试玩入口：纯预览，不写游玩日志、不动钱包（管理端身份没有 C 端 userId）
    Route::post('/game/launch', [app\admin\v1\controller\GameController::class, 'launch']);
    // 放在 /game/list 之后：FastRoute 静态段优先，不会遮蔽列表路由
    Route::get('/game/{hashid}', [app\admin\v1\controller\GameController::class, 'detail']);

    // 游戏区服管理
    Route::get('/game/server/list', [app\admin\v1\controller\GameServerController::class, 'list']);
    Route::post('/game/server/create', [app\admin\v1\controller\GameServerController::class, 'create']);
    Route::put('/game/server/{hashid}', [app\admin\v1\controller\GameServerController::class, 'update']);
    Route::delete('/game/server/{hashid}', [app\admin\v1\controller\GameServerController::class, 'destroy']);
    Route::post('/game/create', [app\admin\v1\controller\GameController::class, 'create']);
    Route::put('/game/{hashid}', [app\admin\v1\controller\GameController::class, 'update']);
    Route::delete('/game/{hashid}', [app\admin\v1\controller\GameController::class, 'destroy']);
    Route::post('/game/currency/manage', [app\admin\v1\controller\GameController::class, 'manageCurrency']);

    // 游戏分类管理
    Route::get('/game/category/list', [app\admin\v1\controller\GameCategoryController::class, 'list']);
    Route::post('/game/category/create', [app\admin\v1\controller\GameCategoryController::class, 'create']);
    Route::put('/game/category/{hashid}', [app\admin\v1\controller\GameCategoryController::class, 'update']);
    Route::delete('/game/category/{hashid}', [app\admin\v1\controller\GameCategoryController::class, 'destroy']);
    Route::post('/game/category/assign', [app\admin\v1\controller\GameCategoryController::class, 'assignGames']);

    // 国家配置管理
    Route::get('/country/config/list', [app\admin\v1\controller\CountryConfigController::class, 'list']);
    Route::post('/country/config/create', [app\admin\v1\controller\CountryConfigController::class, 'create']);
    Route::put('/country/config/{hashid}', [app\admin\v1\controller\CountryConfigController::class, 'update']);

    // 提现管理
    Route::get('/withdraw/orders', [app\admin\v1\controller\WithdrawController::class, 'orders']);
    Route::put('/withdraw/review', [app\admin\v1\controller\WithdrawController::class, 'review']);
    Route::put('/withdraw/switch', [app\admin\v1\controller\WithdrawController::class, 'toggleSwitch']);
    Route::post('/withdraw/limits/set', [app\admin\v1\controller\WithdrawController::class, 'setLimits']);
    Route::get('/withdraw/limits/list', [app\admin\v1\controller\WithdrawController::class, 'listLimits']);
    Route::put('/withdraw/limits/{hashid}', [app\admin\v1\controller\WithdrawController::class, 'updateLimit']);
    Route::post('/withdraw/batch-review', [app\admin\v1\controller\WithdrawController::class, 'batchReview']);
    Route::post('/withdraw/execute-payout', [app\admin\v1\controller\WithdrawController::class, 'executePayout']);
    Route::post('/withdraw/sync-payout', [app\admin\v1\controller\WithdrawController::class, 'syncPayout']);

    // 支付方式管理
    Route::get('/payment/method/list', [app\admin\v1\controller\PaymentController::class, 'list']);
    Route::post('/payment/method/toggle', [app\admin\v1\controller\PaymentController::class, 'toggle']);
    Route::post('/payment/method/create', [app\admin\v1\controller\PaymentController::class, 'create']);
    Route::put('/payment/method/{hashid}', [app\admin\v1\controller\PaymentController::class, 'update']);
    Route::delete('/payment/method/{hashid}', [app\admin\v1\controller\PaymentController::class, 'delete']);

    // CDN 厂商配置管理
    Route::get('/cdn/provider/list', [app\admin\v1\controller\CdnProviderController::class, 'list']);
    Route::post('/cdn/provider/toggle', [app\admin\v1\controller\CdnProviderController::class, 'toggle']);
    Route::post('/cdn/provider/create', [app\admin\v1\controller\CdnProviderController::class, 'create']);
    Route::put('/cdn/provider/{hashid}', [app\admin\v1\controller\CdnProviderController::class, 'update']);
    Route::delete('/cdn/provider/{hashid}', [app\admin\v1\controller\CdnProviderController::class, 'delete']);
    Route::post('/cdn/provider/test', [app\admin\v1\controller\CdnProviderController::class, 'test']);

    // C端用户管理
    Route::get('/platform/user/list', [app\admin\v1\controller\PlatformUserController::class, 'list']);
    Route::get('/platform/user/{hashid}', [app\admin\v1\controller\PlatformUserController::class, 'detail']);
    Route::put('/platform/user/{hashid}', [app\admin\v1\controller\PlatformUserController::class, 'update']);

    // 公告管理
    Route::get('/announcement/list', [app\admin\v1\controller\AnnouncementController::class, 'list']);
    Route::post('/announcement/create', [app\admin\v1\controller\AnnouncementController::class, 'create']);

    // 排行榜管理
    Route::get('/leaderboard/list', [app\admin\v1\controller\LeaderboardController::class, 'list']);
    Route::post('/leaderboard/create', [app\admin\v1\controller\LeaderboardController::class, 'create']);
    Route::put('/leaderboard/{hashid}', [app\admin\v1\controller\LeaderboardController::class, 'update']);
    Route::delete('/leaderboard/{hashid}', [app\admin\v1\controller\LeaderboardController::class, 'destroy']);
    Route::post('/leaderboard/{hashid}/refresh', [app\admin\v1\controller\LeaderboardController::class, 'refresh']);

    // 优惠券管理
    Route::get('/coupon/list', [app\admin\v1\controller\CouponController::class, 'list']);
    Route::post('/coupon/create', [app\admin\v1\controller\CouponController::class, 'create']);
    Route::put('/coupon/{hashid}', [app\admin\v1\controller\CouponController::class, 'update']);
    Route::delete('/coupon/{hashid}', [app\admin\v1\controller\CouponController::class, 'destroy']);
    Route::get('/coupon/{hashid}/stats', [app\admin\v1\controller\CouponController::class, 'stats']);

    // VIP等级管理
    Route::get('/vip/level/list', [app\admin\v1\controller\VipLevelController::class, 'list']);
    Route::post('/vip/level/create', [app\admin\v1\controller\VipLevelController::class, 'create']);
    Route::put('/vip/level/{hashid}', [app\admin\v1\controller\VipLevelController::class, 'update']);
    Route::delete('/vip/level/{hashid}', [app\admin\v1\controller\VipLevelController::class, 'destroy']);

    // 成就管理
    Route::get('/achievement/list', [app\admin\v1\controller\AchievementController::class, 'list']);
    Route::post('/achievement/create', [app\admin\v1\controller\AchievementController::class, 'create']);
    Route::put('/achievement/{hashid}', [app\admin\v1\controller\AchievementController::class, 'update']);
    Route::delete('/achievement/{hashid}', [app\admin\v1\controller\AchievementController::class, 'destroy']);

    // 活动管理
    Route::get('/activities/list', [app\admin\v1\controller\ActivityController::class, 'list']);
    Route::post('/activities/create', [app\admin\v1\controller\ActivityController::class, 'create']);
    Route::put('/activities/{hashid}', [app\admin\v1\controller\ActivityController::class, 'update']);
    Route::delete('/activities/{hashid}', [app\admin\v1\controller\ActivityController::class, 'destroy']);

    // 导出扩展
    Route::post('/export/users', [app\admin\v1\controller\ExportController::class, 'exportUsers']);
    Route::post('/export/transactions', [app\admin\v1\controller\ExportController::class, 'exportTransactions']);
    Route::post('/export/receipt', [app\admin\v1\controller\ExportController::class, 'receipt']);

    // 全局搜索
    Route::get('/search', [app\admin\v1\controller\SearchController::class, 'search']);

    // 数据报表
    Route::get('/report/summary', [app\admin\v1\controller\ReportController::class, 'summary']);
    Route::get('/report/daily', [app\admin\v1\controller\ReportController::class, 'daily']);
    Route::get('/report/export', [app\admin\v1\controller\ReportController::class, 'export']);

    // 数据分析（MySQL 实时聚合）
    Route::get('/analytics/overview', [app\admin\v1\controller\AnalyticsController::class, 'overview']);
    Route::get('/analytics/game-ranking', [app\admin\v1\controller\AnalyticsController::class, 'gameRanking']);
    Route::get('/analytics/dau-trend', [app\admin\v1\controller\AnalyticsController::class, 'dauTrend']);
    Route::get('/analytics/hourly-trend', [app\admin\v1\controller\AnalyticsController::class, 'hourlyTrend']);
    Route::get('/analytics/action-distribution', [app\admin\v1\controller\AnalyticsController::class, 'actionDistribution']);
    Route::get('/analytics/revenue', [app\admin\v1\controller\AnalyticsController::class, 'revenue']);
    Route::get('/analytics/conversion', [app\admin\v1\controller\AnalyticsController::class, 'conversion']);
    Route::get('/analytics/probability', [app\admin\v1\controller\AnalyticsController::class, 'probability']);
    Route::get('/analytics/retention', [app\admin\v1\controller\AnalyticsController::class, 'retention']);
    Route::get('/analytics/funnel', [app\admin\v1\controller\AnalyticsController::class, 'funnel']);
    Route::get('/analytics/arpu', [app\admin\v1\controller\AnalyticsController::class, 'arpu']);
    Route::get('/analytics/economy', [app\admin\v1\controller\AnalyticsController::class, 'economy']);

    // 工单管理
    Route::get('/ticket/list', [app\admin\v1\controller\TicketController::class, 'list']);
    Route::get('/ticket/{hashid}', [app\admin\v1\controller\TicketController::class, 'detail']);
    Route::post('/ticket/{hashid}/reply', [app\admin\v1\controller\TicketController::class, 'reply']);
    Route::post('/ticket/{hashid}/close', [app\admin\v1\controller\TicketController::class, 'close']);
    Route::post('/ticket/{hashid}/assign', [app\admin\v1\controller\TicketController::class, 'assign']);

    // ---- 风控管理 ----
    Route::get('/risk/dashboard', [app\admin\v1\controller\RiskDashboardController::class, 'index']);
    Route::get('/risk/rule/list', [app\admin\v1\controller\RiskRuleController::class, 'list']);
    Route::post('/risk/rule/create', [app\admin\v1\controller\RiskRuleController::class, 'create']);
    Route::put('/risk/rule/{hashid}', [app\admin\v1\controller\RiskRuleController::class, 'update']);
    Route::post('/risk/rule/{hashid}/toggle', [app\admin\v1\controller\RiskRuleController::class, 'toggle']);
    Route::post('/risk/rule/test', [app\admin\v1\controller\RiskRuleController::class, 'test']);
    Route::get('/risk/event/list', [app\admin\v1\controller\RiskEventController::class, 'list']);
    Route::get('/risk/event/{hashid}', [app\admin\v1\controller\RiskEventController::class, 'detail']);
    Route::post('/risk/event/{hashid}/handle', [app\admin\v1\controller\RiskEventController::class, 'handle']);
    Route::get('/risk/device/list', [app\admin\v1\controller\RiskDeviceController::class, 'list']);
    Route::post('/risk/device/block', [app\admin\v1\controller\RiskDeviceController::class, 'block']);
    Route::post('/risk/device/unblock', [app\admin\v1\controller\RiskDeviceController::class, 'unblock']);
    Route::get('/risk/ip/list', [app\admin\v1\controller\RiskIpController::class, 'list']);
    Route::post('/risk/ip/block', [app\admin\v1\controller\RiskIpController::class, 'block']);
    Route::post('/risk/ip/whitelist', [app\admin\v1\controller\RiskIpController::class, 'whitelist']);
    Route::post('/risk/ip/appeal', [app\admin\v1\controller\RiskIpController::class, 'appeal']);
    Route::post('/risk/ip/recheck', [app\admin\v1\controller\RiskIpController::class, 'recheck']);
    // clusters 必须先于 /risk/graph/{userId} 注册（webman 顺序匹配）
    Route::get('/risk/graph/clusters', [app\admin\v1\controller\RiskGraphController::class, 'clusters']);
    Route::get('/risk/graph/{userId}', [app\admin\v1\controller\RiskGraphController::class, 'graph']);

    // ---- M6 风控可视化（静态路由先于动态注册）----
    Route::get('/risk/overview', [app\admin\v1\controller\RiskDashboardController::class, 'overview']);
    Route::get('/risk/hit-trend', [app\admin\v1\controller\RiskDashboardController::class, 'hitTrend']);
    Route::get('/risk/action-distribution', [app\admin\v1\controller\RiskDashboardController::class, 'actionDistribution']);
    Route::get('/risk/rule-performance', [app\admin\v1\controller\RiskDashboardController::class, 'rulePerformance']);
    Route::get('/risk/clusters', [app\admin\v1\controller\RiskClusterController::class, 'list']);
    Route::post('/risk/clusters/detect', [app\admin\v1\controller\RiskClusterController::class, 'detect']);
    Route::post('/risk/clusters/confirm', [app\admin\v1\controller\RiskClusterController::class, 'confirm']);
    Route::get('/risk/clusters/{hashid}/members', [app\admin\v1\controller\RiskClusterController::class, 'members']);
    Route::put('/risk/clusters/{hashid}/status', [app\admin\v1\controller\RiskClusterController::class, 'status']);
    Route::get('/risk/users', [app\admin\v1\controller\RiskUserController::class, 'users']);
    Route::get('/risk/users/{hashid}/timeline', [app\admin\v1\controller\RiskUserController::class, 'timeline']);
    Route::post('/risk/users/{hashid}/hold', [app\admin\v1\controller\RiskUserController::class, 'hold']);

    // ---- 反作弊管理 ----
    Route::get('/anticheat/events', [app\admin\v1\controller\AntiCheatController::class, 'events']);
    Route::get('/anticheat/events/{hashid}', [app\admin\v1\controller\AntiCheatController::class, 'detail']);
    Route::post('/anticheat/events/{hashid}/review', [app\admin\v1\controller\AntiCheatController::class, 'review']);

    // ---- 组队/公会管理（M4）----
    Route::get('/groups', [app\admin\v1\controller\GroupController::class, 'list']);
    Route::get('/groups/{hashid}/audit', [app\admin\v1\controller\GroupController::class, 'audit']);

    // ---- 分享统计（M4）----
    Route::get('/share/stats', [app\admin\v1\controller\ShareController::class, 'stats']);
})->middleware([
    app\middleware\AdminAuth::class,
    app\middleware\AdminPermission::class,
    app\middleware\OperationLog::class,
]);

// ============================================================
// 公开接口（版本号置于 URL 路径）
// ============================================================
Route::group('/api/v1', function () {
    // 点击验证码
    Route::post('/captcha/generate', v('CaptchaController', 'generate'));
    Route::post('/captcha/verify', v('CaptchaController', 'verify'));

    // 认证
    Route::post('/auth/login', v('AuthController', 'login'));
    Route::post('/auth/register', v('AuthController', 'register'));
    Route::post('/auth/refresh', v('AuthController', 'refresh'));
});

// 关闭默认路由
Route::disableDefaultRoute();
