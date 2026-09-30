<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * erikwang2013/aetherupload-webman 配置 — C 端
 *
 * 与 admin 那份同一套手工分发方式（没跑 `Install::install()`；两份文件**必须成对**，
 * 否则 route.php 顶层拿 null 传进 `Route::post(string)` ⇒ TypeError，worker 起不来）。
 *
 * **本树只收不发通用文件**：这里上传的是**个人件**（头像、KYC 三照），所以
 * - 只注册 preprocess / uploading 两条路由（都在 /api/v1 下，骑既有代理前缀）；
 * - **不注册插件的 display / download 路由**（见 route.php 头注）：插件那两个端点只验「有没有登录」，
 *   不验「这个 savedPath 是不是你的」—— 拿别人的 savedPath 就能读到证件照。个人件的读取统一走
 *   `GET /api/v1/user/file/{savedPath}`（UserFileController，按游戏归属校验，见其注释）。
 * 落盘：`storage/app/aetherupload/`（不在 public 下）。
 */

return [
    'enable' => true,

    'instant_completion' => false, // 个人件都很小，秒传收益不抵多一处 Redis 依赖
    'lax_mode' => true,            // 客户端跳过 md5 ⇒ 单块直传（代价：秒传/完整性校验不可用）

    'root_dir' => 'storage/app/aetherupload',
    'chunk_size' => 1000000,
    'resource_subdir_rule' => 'month',

    'forbidden_extensions' => ['php', 'part', 'html', 'shtml', 'htm', 'shtm', 'xhtml', 'xml', 'js', 'jsp', 'asp', 'java', 'py', 'sh', 'bat', 'exe', 'dll', 'cgi', 'htaccess', 'reg', 'aspx', 'vbs'],
    'extra_mime_types' => [],
    'x_accel_redirect' => false,

    'route_preprocess' => '/api/v1/aetherupload/preprocess',
    'route_uploading'  => '/api/v1/aetherupload/uploading',
    // 下面两条**不注册**（保留键只为 ConfigMapper 取值不报缺）：读取走 UserFileController
    'route_display'    => '/api/v1/aetherupload/display',
    'route_download'   => '/api/v1/aetherupload/download',

    'middleware_preprocess' => [\app\middleware\UserAuth::class],
    'middleware_uploading'  => [\app\middleware\UserAuth::class],
    'middleware_display'    => [\app\middleware\UserAuth::class],
    'middleware_download'   => [\app\middleware\UserAuth::class],

    'groups' => [
        'image' => [
            'group_dir' => 'image',
            'resource_maxsize' => 5242880, // 5MB
            // 不含 svg：可内嵌脚本，个人件直出到浏览器＝存储型 XSS 入口
            'resource_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'event_before_upload_complete' => false,
            'event_upload_complete' => false,
        ],
    ],
];
