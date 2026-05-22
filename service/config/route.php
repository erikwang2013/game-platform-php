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
 * 公开接口: + ApiVersion
 * 认证接口: + ApiVersion → UserAuth
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

// ============================================================
// 公开接口（无需认证）
// ============================================================
Route::group('/api', function () {
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
    Route::get('/game/{hashid}', v('GameController', 'detail'));
    Route::get('/announcement/list', v('AnnouncementController', 'list'));
    Route::get('/announcement/detail/{hashid}', v('AnnouncementController', 'detail'));

    Route::get('/leaderboard/list', v('LeaderboardController', 'list'));
    Route::get('/leaderboard/{hashid}', v('LeaderboardController', 'ranking'));

    Route::get('/search', v('SearchController', 'search'));
    Route::get('/game/suggest', v('GameController', 'suggest'));

    // 支付
    Route::post('/payment/callback', v('PaymentController', 'callback'));
    Route::get('/payment/methods', v('PaymentController', 'methods'));

    // 2FA 验证（登录流程使用，公开接口）
    Route::post('/2fa/verify', v('TwoFactorController', 'verify'));
})->middleware([
    app\middleware\ApiVersion::class,
]);

// ============================================================
// 认证接口（需登录）
// ============================================================
Route::group('/api', function () {
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

    // GDPR
    Route::get('/user/export-data', v('UserController', 'exportData'));
    Route::post('/user/delete-account', v('UserController', 'deleteAccount'));
    Route::put('/user/privacy', v('UserController', 'updatePrivacy'));
})->middleware([
    app\middleware\ApiVersion::class,
    app\middleware\UserAuth::class,
]);

Route::disableDefaultRoute();
