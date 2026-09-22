<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

$vendorBootstrap = dirname(__DIR__) . '/vendor/workerman/webman-framework/src/support/bootstrap.php';
if (is_file($vendorBootstrap)) {
    require $vendorBootstrap;
}

// compose 以 PUBLIC_SITE_URL 注入对外地址（键名不在 .env 中，避免被上面 vendor bootstrap 的
// Dotenv mutable 重载覆盖）；还原为应用使用的 SITE_URL（18 个支付网关请求时 getenv）
$publicSiteUrl = getenv('PUBLIC_SITE_URL');
if ($publicSiteUrl !== false && $publicSiteUrl !== '') {
    putenv('SITE_URL=' . $publicSiteUrl);
    $_ENV['SITE_URL'] = $_SERVER['SITE_URL'] = $publicSiteUrl;
}

\Erikwang2013\Security\SecurityGuard::init(config('plugin.erikwang2013.security-php.app', config('security', [])));
