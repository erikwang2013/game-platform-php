<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * aetherupload 插件路由 — 管理端（手工分发，与同目录 app.php 成对；缺一会 TypeError：见 app.php 头注）
 *
 * 与插件自带 `vendor/.../config/route.php` 的唯一差别：**示例页那三条路由没有搬过来**。
 * 它们在 `config('app.debug')` 为真时注册，且**不带任何中间件** —— 本仓两树 app.debug 都是 true，
 * 照抄等于对外开一个无鉴权的上传演示页（`GET|POST /aetherupload`、`/aetherupload/example_source`）。
 */

// 绑定宿主适配器：本文件是 webman 里唯一保证「每个 HTTP worker 启动时执行一次」的入口
// （被 support\bootstrap.php 的 Route::load() require），下面的 ConfigMapper::get() 依赖它。
\AetherUpload\Runtime::bind(new \AetherUpload\Adapter\Webman\WebmanAdapter());

use Webman\Route;

Route::post(\AetherUpload\ConfigMapper::get('route_preprocess'), [\AetherUpload\UploadController::class, 'preprocess'])->middleware(\AetherUpload\ConfigMapper::get('middleware_preprocess'));

Route::post(\AetherUpload\ConfigMapper::get('route_uploading'), [\AetherUpload\UploadController::class, 'saveChunk'])->middleware(\AetherUpload\ConfigMapper::get('middleware_uploading'));

Route::get(\AetherUpload\ConfigMapper::get('route_display') . '/{uri}', [\AetherUpload\ResourceController::class, 'display'])->middleware(\AetherUpload\ConfigMapper::get('middleware_display'));

Route::get(\AetherUpload\ConfigMapper::get('route_download') . '/{uri}/{newName}', [\AetherUpload\ResourceController::class, 'download'])->middleware(\AetherUpload\ConfigMapper::get('middleware_download'));
