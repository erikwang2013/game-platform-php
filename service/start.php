#!/usr/bin/env php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

chdir(__DIR__);
require_once __DIR__ . '/vendor/autoload.php';

// 加载 .env 环境变量（vlucas/phpdotenv）
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__);
    $dotenv->safeLoad();
}

support\App::run();
