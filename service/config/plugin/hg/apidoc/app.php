<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * hg/apidoc — C端业务 API 文档
 * 访问: http://localhost:8788/apidoc/
 */
return [
    'enable' => true,
    'apidoc' => [
        'title' => '全球游戏聚合平台 — C端业务 API',
        'desc' => '用户认证、钱包、充值、兑换、提现、游戏、公告、排行榜、优惠券、通知',
        'apps' => [[
            'title' => 'C端业务 v1',
            'path' => 'app\api\v1\controller',
            'key' => 'service',
        ]],
        'definitions' => "app\common\controller\Definitions",
        'auto_url' => ['letter_rule' => "lcfirst", 'prefix' => "/api"],
        'auto_register_routes' => false,
        'cache' => ['enable' => false],
        'auth' => ['enable' => true, 'password' => "admin123", 'secret_key' => "apidoc#hg_code", 'expire' => 86400],
        'params' => ['header' => [
            ['name' => 'Authorization', 'type' => 'string', 'require' => false, 'desc' => 'Bearer Token (JWT) 认证接口必传'],
            ['name' => 'API-Version', 'type' => 'string', 'require' => true, 'default' => 'v1', 'desc' => 'API版本号'],
            ['name' => 'X-Language', 'type' => 'string', 'require' => false, 'default' => 'en-US', 'desc' => '语言: en-US/zh-CN/ja-JP/ko-KR'],
        ]],
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
