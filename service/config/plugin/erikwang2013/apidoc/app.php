<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * erikwang2013/apidoc 配置 — C端业务 API 文档
 * 访问: http://localhost:8788/apidoc/
 * 说明: 业务取值与 config/plugin/hg/apidoc/app.php 一致，按 erikwang2013/apidoc-php 的配置结构书写
 */
return [
    // 是否启用本插件
    'enable' => true,
    'apidoc' => [
        // （选配）文档标题，显示在左上角与首页
        'title' => '全球游戏聚合平台 — C端业务 API',
        // （选配）文档描述，显示在首页
        'desc' => '用户认证、钱包、充值、兑换、提现、游戏、公告、排行榜、优惠券、通知、推荐',
        // （必须）设置文档的应用/版本，可配置多个；每个应用对应一个控制器目录
        'apps' => [
            [
                // （必须）应用标题，显示在文档左侧导航
                'title' => 'C端业务 v1',
                // （必须）控制器目录地址，相对应用根目录，反斜杠分隔
                'path' => 'app\api\v1\controller',
                // （必须）应用唯一 key，注解中通过它引用该应用
                'key' => 'service',
            ],
        ],
        // （必须）通用注释定义类，Returned 注解使用 ref 简写时以此类所在命名空间解析
        'definitions' => "app\common\controller\Definitions",
        // （必须）自动生成 url 规则：接口未写 @Apidoc\Url("xxx") 注解时使用以下规则
        'auto_url' => [
            // 字母规则：lcfirst=首字母小写；ucfirst=首字母大写
            'letter_rule' => "lcfirst",
            // url 前缀，版本号置于 URL 路径，与真实路由 /api/v1 对齐
            'prefix' => "/api/v1",
        ],
        // （选配）是否自动注册路由；本项目路由在 config/route.php 显式注册，故关闭
        'auto_register_routes' => false,
        // （必须）缓存配置
        'cache' => [
            // 是否开启文档缓存
            'enable' => false,
        ],
        // （必须）权限认证配置
        'auth' => [
            // 是否启用访问密码验证；C端文档不设访问密码
            'enable' => false,
            // 全局访问密码
            'password' => "admin123",
            // 密码加密盐
            'secret_key' => "apidoc#erik.xyz",
            // 授权访问后的有效期（秒）
            'expire' => 86400,
        ],
        // 全局参数：所有接口共用，仅用于文档展示
        'params' => [
            // （选配）全局的请求 Header
            'header' => [
                // name=字段名，type=字段类型，require=是否必须，default=默认值，desc=字段描述
                ['name' => 'Authorization', 'type' => 'string', 'require' => false, 'desc' => 'Bearer Token (JWT) 认证接口必传'],
                ['name' => 'X-Language', 'type' => 'string', 'require' => false, 'default' => 'en-US', 'desc' => '语言: en-US/zh-CN/ja-JP/ko-KR'],
            ],
            // （选配）全局的请求 Query
            'query' => [
                // 同上 header
            ],
            // （选配）全局的请求 Body
            'body' => [
                // 同上 header
            ],
        ],
        // 全局响应体
        'responses' => [
            // 成功响应体
            'success' => [
                ['name' => 'code', 'desc' => '业务代码', 'type' => 'int', 'require' => 1],
                ['name' => 'message', 'desc' => '业务信息', 'type' => 'string', 'require' => 1],
                // main=true 指定接口 Returned 参数的挂载节点
                ['name' => 'data', 'desc' => '业务数据', 'main' => true, 'type' => 'object', 'require' => 1],
            ],
            // 异常响应体
            'error' => [
                ['name' => 'code', 'desc' => '错误码', 'type' => 'int', 'require' => 1],
                ['name' => 'message', 'desc' => '错误信息', 'type' => 'string', 'require' => 1],
            ],
        ],
        // （选配）全局响应状态码
        'responses_status' => [
            ['name' => '200', 'desc' => '请求成功'],
            ['name' => '400', 'desc' => '参数错误'],
            ['name' => '401', 'desc' => '未认证'],
            ['name' => '403', 'desc' => '无权限'],
            ['name' => '404', 'desc' => '不存在'],
            ['name' => '422', 'desc' => '验证失败'],
            ['name' => '500', 'desc' => '服务端错误'],
        ],
        // （选配）默认作者
        'default_author' => 'erik',
        // （选配）默认请求类型
        'default_method' => 'GET',
        // （选配）Apidoc 允许跨域访问；跨域由全局 Cors 中间件统一处理，故关闭
        'allowCrossDomain' => false,
        // （选配）解析时忽略的方法名
        'ignored_methods' => [],
        // （选配）数据库配置，用于按表结构生成字段
        'database' => [],
        // （选配）Markdown 文档
        'docs' => [],
        // （选配）接口生成器配置，注意是一个二维数组
        'generator' => [],
    ],
];
