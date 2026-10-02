<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * erikwang2013/aetherupload-webman 配置 — 管理端
 *
 * 本文件与同目录 route.php 是**手工分发**的（没跑插件的 `Install::install()`）：只取真正需要的
 * 两份配置，不往宿主写命令壳 / 示例页 / vendor js / 翻译（翻译另按需拷）。
 *
 * ⚠ **两者必须成对存在**：route.php 顶层要求值 `ConfigMapper::get('route_preprocess')`，缺本文件
 *   会拿到 null，而 `Route::post(string $path, ...)` 是 string 形参 ⇒ TypeError，worker 起不来。
 *
 * 本树承载的是「后台维护、C 端用户看」的**展示件**（游戏封面 / 分类图标 / 成就图标）：
 * - display **不挂鉴权**：`<img src>` 带不了 Authorization 头，而 savedPath 里是文件内容的 md5，
 *   不可猜；KYC / 头像这类**个人件不在本树**（走 service 的鉴权端点）。
 * - 上传（preprocess/uploading）与下载仍要求后台登录。
 * 落盘：`storage/app/aetherupload/`（**不在 public 下**，只经 display/download 路由对外）。
 * 前端落库的值形如 `{对外基址}/admin/v1/aetherupload/display/{savedPath}`。
 */

return [
    'enable' => true,

    // 秒传需要 Redis 存 hash→路径：图片都很小，秒传省下的那点时间不抵多一处外部依赖，关掉。
    'instant_completion' => false,

    // 宽松模式：客户端跳过 md5 计算 ⇒ 单块直传（图片 ≤5MB，默认 chunk_size 1MB 也只需几次分块）。
    // 代价：秒传与完整性校验都不可用 —— 对本场景无意义。
    'lax_mode' => true,

    'root_dir' => 'storage/app/aetherupload',
    'chunk_size' => 1000000,
    'resource_subdir_rule' => 'month',

    // 危险后缀黑名单沿用插件默认（php / html / js / sh / htaccess … 一律拒收）
    'forbidden_extensions' => ['php', 'part', 'html', 'shtml', 'htm', 'shtm', 'xhtml', 'xml', 'js', 'jsp', 'asp', 'java', 'py', 'sh', 'bat', 'exe', 'dll', 'cgi', 'htaccess', 'reg', 'aspx', 'vbs'],
    'extra_mime_types' => [],
    'x_accel_redirect' => false,

    // 路由刻意放在 /admin/v1 下：骑既有反向代理前缀，前端不用新增 proxy 规则
    'route_preprocess' => '/admin/v1/aetherupload/preprocess',
    'route_uploading'  => '/admin/v1/aetherupload/uploading',
    'route_display'    => '/admin/v1/aetherupload/display',
    'route_download'   => '/admin/v1/aetherupload/download',

    // 上传/下载要后台登录 **且过 RBAC**：只挂 AdminAuth 的话，任何已登录而零权限的后台账号都能
    // 往 storage/app/aetherupload/ 写文件 —— 该能力在 install.sql 里既授不出也吊销不掉。
    // 补种子的三条 slug 见 install/install.sql「aetherupload 展示件」段。
    // 两条 POST（preprocess/uploading）再补挂 OperationLog：本树两条写路由刻意放在 /admin/v1 下
    // **但不在该路由组内**，组上挂的 OperationLog 覆盖不到 ⇒ 文件写入曾是全仓唯一「副作用不进任何
    // 日志表」的写路径。OperationLog 要求的能力这里都具备：只对 POST/PUT/DELETE 放行、读
    // $request->path()/$request->all()/$request->adminId，不依赖 /admin/v1 组上下文；
    // `all()` 只取表单字段，multipart 的文件段在 $request->file() 里，不会把文件字节灌进日志。
    // display 仍然公开（见文件头说明：`<img src>` 带不了 Authorization，savedPath 是内容 md5）——
    // 它是**有意**不挂任何中间件的，别"补齐"它。
    'middleware_preprocess' => [\app\middleware\AdminAuth::class, \app\middleware\AdminPermission::class, \app\middleware\OperationLog::class],
    'middleware_uploading'  => [\app\middleware\AdminAuth::class, \app\middleware\AdminPermission::class, \app\middleware\OperationLog::class],
    'middleware_display'    => [],
    'middleware_download'   => [\app\middleware\AdminAuth::class, \app\middleware\AdminPermission::class],

    'groups' => [
        'image' => [
            'group_dir' => 'image',
            'resource_maxsize' => 5242880, // 5MB
            // 刻意不含 svg：SVG 可内嵌脚本，作为展示件直出到浏览器＝存储型 XSS 的入口
            'resource_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'event_before_upload_complete' => false,
            'event_upload_complete' => false,
        ],
    ],
];
