<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Webman\Route;
use support\Request;

/**
 * C端 API 路由配置
 *
 * 全局中间件链: Cors → SecurityFilter → RateLimit
 * 公开接口: 直接注册
 * 认证接口: UserAuth
 *
 * API 版本策略:
 * - 版本号置于 URL 路径（如 /api/v1/*），不再使用请求头 API-Version
 * - 版本命名空间由 v() 解析；路由注册到哪个前缀组即对外暴露哪个版本
 * - 新增 v2: 创建 app/api/v2/controller 并注册 /api/v2 组 + v('XController','x','v2')
 * - /api/provider/*（第三方 HMAC）与 /api/game/*（SDK）为无版本外部契约，保持原样
 */

function v(string $controller, string $action, string $version = 'v1'): \Closure
{
    return function (Request $request, ...$params) use ($controller, $action, $version) {
        $class = "\\app\\api\\{$version}\\controller\\{$controller}";
        return (new $class)->{$action}($request, ...$params);
    };
}

// 健康检查
Route::get('/health', function () {
    return json(['code' => 0, 'message' => 'ok']);
});

// ============================================================
// 公开接口（无需认证）
// ============================================================
Route::group('/api/v1', function () {
    Route::post('/auth/register', v('AuthController', 'register'));
    Route::post('/auth/login', v('AuthController', 'login'));
    Route::post('/auth/refresh', v('AuthController', 'refresh'));
    Route::get('/auth/oauth/{provider}', v('OAuthController', 'redirect'));
    Route::post('/auth/oauth/{provider}/callback', v('OAuthController', 'callback'));
    Route::post('/captcha/generate', v('CaptchaController', 'generate'));

    Route::get('/language/list', v('LanguageController', 'list'));
    Route::post('/language/switch', v('LanguageController', 'switch'));

    Route::get('/country/list', v('CountryController', 'list'));
    Route::get('/country/{code}', v('CountryController', 'detail'));

    Route::get('/game/list', v('GameController', 'list'));
    Route::get('/game/suggest', v('GameController', 'suggest'));
    Route::get('/game/detail/{hashid}', v('GameController', 'detail'));
    Route::get('/announcement/list', v('AnnouncementController', 'list'));
    Route::get('/announcement/detail/{hashid}', v('AnnouncementController', 'detail'));

    Route::get('/leaderboard/list', v('LeaderboardController', 'list'));
    Route::get('/leaderboard/{hashid}', v('LeaderboardController', 'ranking'));

    Route::get('/search', v('SearchController', 'search'));

    Route::get('/platform/stats', v('PlatformStatsController', 'stats'));

    // 支付
    Route::post('/payment/callback', v('PaymentController', 'callback'));
    Route::get('/payment/methods', v('PaymentController', 'methods'));

    // 2FA 验证（登录流程使用，公开接口）
    Route::post('/2fa/verify', v('TwoFactorController', 'verify'));

    // 分享落地页点击上报（M4，匿名可访问，不泄露分享者信息）
    Route::post('/shares/visit', v('ShareController', 'visit'));
});

// ============================================================
// 认证接口（需登录）
// ============================================================
Route::group('/api/v1', function () {
    // 钱包
    Route::get('/wallet/info', v('WalletController', 'info'));
    Route::get('/wallet/transactions', v('WalletController', 'transactions'));

    // 充值
    Route::post('/deposit/create', v('DepositController', 'create'));
    Route::get('/deposit/orders', v('DepositController', 'orders'));

    // 兑换
    Route::post('/exchange/quote', v('ExchangeController', 'quote'));
    Route::post('/exchange/buy', v('ExchangeController', 'buy'));
    Route::post('/exchange/sell', v('ExchangeController', 'sell'));
    Route::get('/exchange/records', v('ExchangeController', 'records'));

    // 提现
    Route::post('/withdraw/apply', v('WithdrawController', 'apply'));
    Route::get('/withdraw/orders', v('WithdrawController', 'orders'));

    // 游戏
    Route::post('/game/launch', v('GameController', 'launch'));
    Route::get('/game/play-logs', v('GamePlayLogController', 'list'));
    Route::get('/game/play-log/{hashid}', v('GamePlayLogController', 'detail'));

    // 多游戏聚合（M5）：聚合余额 + SDK 会话签发
    Route::get('/game/balance', v('GameController', 'balance'));
    Route::get('/game/session', v('GameController', 'session'));

    // 组队/公会（M4）
    Route::post('/groups', v('GroupController', 'create'));
    Route::get('/groups/{hashid}', v('GroupController', 'detail'));
    Route::get('/groups/{hashid}/members', v('GroupController', 'members'));
    Route::post('/groups/{hashid}/join', v('GroupController', 'join'));
    Route::post('/groups/{hashid}/leave', v('GroupController', 'leave'));
    Route::put('/groups/{hashid}/role', v('GroupController', 'role'));

    // 分享短码（M4）
    Route::post('/shares', v('ShareController', 'create'));

    // 优惠券
    Route::get('/coupon/available', v('CouponController', 'available'));
    Route::post('/coupon/claim', v('CouponController', 'claim'));
    Route::get('/coupon/my', v('CouponController', 'my'));

    // 用户
    Route::get('/user/profile', v('UserController', 'profile'));
    Route::put('/user/profile', v('UserController', 'updateProfile'));
    Route::get('/user/identity/status', v('IdentityController', 'status'));
    Route::post('/user/identity/apply', v('IdentityController', 'apply'));

    // 通知
    Route::get('/notification/list', v('NotificationController', 'list'));
    Route::get('/notification/unread-count', v('NotificationController', 'unreadCount'));
    Route::post('/notification/read', v('NotificationController', 'markRead'));

    // 推荐
    Route::get('/referral/my-code', v('ReferralController', 'myCode'));
    Route::get('/referral/stats', v('ReferralController', 'stats'));
    Route::post('/referral/apply', v('ReferralController', 'apply'));

    // 2FA
    Route::get('/user/2fa/status', v('TwoFactorController', 'status'));
    Route::post('/user/2fa/setup', v('TwoFactorController', 'setup'));
    Route::post('/user/2fa/enable', v('TwoFactorController', 'enable'));
    Route::post('/user/2fa/disable', v('TwoFactorController', 'disable'));

    // 设备令牌
    Route::post('/device/token', v('DeviceTokenController', 'register'));
    Route::delete('/device/token', v('DeviceTokenController', 'unregister'));

    // GDPR
    Route::get('/user/export-data', v('UserController', 'exportData'));
    Route::post('/user/delete-account', v('UserController', 'deleteAccount'));
    Route::put('/user/privacy', v('UserController', 'updatePrivacy'));
})->middleware([
    app\middleware\UserAuth::class,
]);

Route::disableDefaultRoute();

// ============================================================
// 游戏提供商回调接口（HMAC-SHA256 签名认证）
// ============================================================
Route::group('/api/provider', function () {
    Route::post('/balance', v('ProviderController', 'balance'));
    Route::post('/bet', v('ProviderController', 'bet'));
    Route::post('/settle', v('ProviderController', 'settle'));
    Route::post('/refund', v('ProviderController', 'refund'));
})->middleware([
    app\middleware\ProviderAuth::class,
]);

// ============================================================
// 自研/内嵌游戏 SDK 接口（M5，SDK 会话令牌认证）
// ============================================================
Route::group('/api/game', function () {
    Route::post('/balance', v('GameSdkController', 'balance'));
    Route::post('/bet', v('GameSdkController', 'bet'));
    Route::post('/settle', v('GameSdkController', 'settle'));
    Route::post('/refund', v('GameSdkController', 'refund'));
})->middleware([
    app\middleware\SdkSessionAuth::class,
]);

// ============================================================
// 工单
// ============================================================
Route::group('/api/v1', function () {
    Route::get('/ticket/list', v('TicketController', 'list'));
    Route::get('/ticket/{hashid}', v('TicketController', 'detail'));
    Route::post('/ticket/create', v('TicketController', 'create'));
    Route::post('/ticket/{hashid}/reply', v('TicketController', 'reply'));
})->middleware([
    app\middleware\UserAuth::class,
]);


// 邮箱/手机验证
Route::group('/api/v1/verify', function () {
    Route::post('/send-email', v('VerificationController', 'sendEmail'));
    Route::post('/confirm-email', v('VerificationController', 'confirmEmail'));
    Route::post('/send-sms', v('VerificationController', 'sendSms'));
    Route::post('/confirm-phone', v('VerificationController', 'confirmPhone'));
})->middleware([
    app\middleware\UserAuth::class,
]);

// 好友
Route::group('/api/v1/friend', function () {
    Route::get('/list', v('FriendController', 'list'));
    Route::get('/requests', v('FriendController', 'requests'));
    Route::post('/request', v('FriendController', 'request'));
    Route::post('/accept', v('FriendController', 'accept'));
    Route::post('/reject', v('FriendController', 'reject'));
    Route::post('/remove', v('FriendController', 'remove'));
    Route::get('/search', v('FriendController', 'search'));
})->middleware([
    app\middleware\UserAuth::class,
]);

// 聊天
Route::group('/api/v1/chat', function () {
    Route::get('/conversations', v('ChatController', 'conversations'));
    Route::get('/messages/{peerHashid}', v('ChatController', 'messages'));
    Route::post('/send', v('ChatController', 'send'));
    Route::post('/read', v('ChatController', 'markRead'));
    Route::get('/unread-total', v('ChatController', 'unreadTotal'));
})->middleware([
    app\middleware\UserAuth::class,
]);

// Webhook订阅
Route::group('/api/v1/webhook', function () {
    Route::get('/list', v('WebhookController', 'list'));
    Route::post('/register', v('WebhookController', 'register'));
    Route::post('/delete', v('WebhookController', 'delete'));
    Route::post('/test', v('WebhookController', 'test'));
})->middleware([
    app\middleware\UserAuth::class,
]);

// 运营活动
Route::group('/api/v1/activities', function () {
    Route::get('/list', v('ActivityController', 'list'));
    Route::get('/progress', v('ActivityController', 'progress'));
    Route::get('/{hashid}', v('ActivityController', 'detail'));
    Route::post('/{hashid}/checkin', v('ActivityController', 'checkin'));
})->middleware([
    app\middleware\UserAuth::class,
]);

// 赛事 (FeatureFlag: tournament)
Route::group('/api/v1/tournament', function () {
    Route::get('/list', v('TournamentController', 'list'));
    Route::get('/{hashid}', v('TournamentController', 'detail'));
    Route::post('/{hashid}/join', v('TournamentController', 'join'));
})->middleware([
    app\middleware\UserAuth::class,
]);
