<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

$vendorBootstrap = dirname(__DIR__) . '/vendor/workerman/webman-framework/src/support/bootstrap.php';
if (is_file($vendorBootstrap)) {
    require $vendorBootstrap;
}

\Erikwang2013\Security\SecurityGuard::init(config('security'));
