<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * aetherupload 插件路由 — C 端（手工分发，与同目录 app.php 成对；缺一会 TypeError：见 app.php 头注）
 *
 * 与插件自带 `vendor/.../config/route.php` 的两处差别，都是刻意为之：
 *
 * 1. **示例页那三条路由没有搬**（`GET|POST /aetherupload`、`/aetherupload/example_source`）：
 *    它们在 `config('app.debug')` 为真时注册且**不带中间件**，本仓 app.debug 是 true
 *    ⇒ 照抄等于对外开一个无鉴权的上传演示页。
 * 2. **display / download 两条也没有搬**：本树上传的是 KYC 证件照这类个人件，而插件那两个端点
 *    只挂「有没有登录」，**不校验这个 savedPath 属于谁** —— 任何登录用户拿别人的 savedPath
 *    就能读到证件照。读取统一走 `GET /api/v1/user/file/{savedPath}`（按归属校验）。
 */

// 绑定宿主适配器：本文件是 webman 里唯一保证「每个 HTTP worker 启动时执行一次」的入口
// （被 support\bootstrap.php 的 Route::load() require），下面的 ConfigMapper::get() 依赖它。
\AetherUpload\Runtime::bind(new \AetherUpload\Adapter\Webman\WebmanAdapter());

use Webman\Route;

Route::post(\AetherUpload\ConfigMapper::get('route_preprocess'), [\AetherUpload\UploadController::class, 'preprocess'])->middleware(\AetherUpload\ConfigMapper::get('middleware_preprocess'));

Route::post(\AetherUpload\ConfigMapper::get('route_uploading'), [\AetherUpload\UploadController::class, 'saveChunk'])->middleware(\AetherUpload\ConfigMapper::get('middleware_uploading'));
