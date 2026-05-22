<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * hg/apidoc 配置 — 管理后台 API 文档
 * 访问: http://localhost:8787/apidoc/
 */
return [
    'enable' => true,
    'apidoc' => [
        'title' => '全球游戏聚合平台 — 管理后台 API',
        'desc' => '游戏管理、提现审核、用户管理、支付管理、KYC审核、公告管理、统计分析',
        'apps' => [[
            'title' => '管理后台 v1',
            'path' => 'app\admin\controller',
            'key' => 'admin',
        ]],
        'definitions' => "app\common\controller\Definitions",
        'auto_url' => [
            'letter_rule' => "lcfirst",
            'prefix' => "/admin",
        ],
        'auto_register_routes' => false,
        'cache' => ['enable' => false],
        'auth' => [
            'enable' => true,
            'password' => "admin123",
            'secret_key' => "apidoc#hg_code",
            'expire' => 86400,
        ],
        'params' => [
            'header' => [
                ['name' => 'Authorization', 'type' => 'string', 'require' => true, 'desc' => 'Bearer Token (JWT)'],
                ['name' => 'API-Version', 'type' => 'string', 'require' => true, 'default' => 'v1', 'desc' => 'API版本号'],
            ],
        ],
        'responses' => [
            'success' => [
                ['name' => 'code', 'desc' => '业务代码', 'type' => 'int', 'require' => 1],
                ['name' => 'message', 'desc' => '业务信息', 'type' => 'string', 'require' => 1],
                ['name' => 'data', 'desc' => '业务数据', 'main' => true, 'type' => 'object', 'require' => 1],
            ],
            'error' => [
                ['name' => 'code', 'desc' => '错误码', 'type' => 'int', 'require' => 1],
                ['name' => 'message', 'desc' => '错误信息', 'type' => 'string', 'require' => 1],
            ],
        ],
        'responses_status' => [
            ['name' => '200', 'desc' => '请求成功'],
            ['name' => '400', 'desc' => '参数错误'],
            ['name' => '401', 'desc' => '未认证'],
            ['name' => '403', 'desc' => '无权限'],
            ['name' => '404', 'desc' => '不存在'],
            ['name' => '422', 'desc' => '验证失败'],
            ['name' => '500', 'desc' => '服务端错误'],
        ],
        'default_author' => 'erik',
        'default_method' => 'GET',
    ],
];
