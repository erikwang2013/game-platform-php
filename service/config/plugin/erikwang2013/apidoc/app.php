<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * erikwang2013/apidoc 配置 — C端业务 API 文档
 * 访问: http://localhost:8792/apidoc/
 * 说明: 按 erikwang2013/apidoc-php 的配置结构书写，文档前缀与真实路由组 /api/v1 对齐
 */

// 访问密码与加密盐一律从环境变量读取（.env 不纳入版本控制），杜绝明文口令入库。
// 本配置 auth.enable 为 false（C端文档公开），取值仍不落明文：一旦有人改为 true，
// 未配置 APIDOC_PASSWORD 时为进程级随机口令 ⇒ 无人能登录（fail-closed），绝不会退化成空密码可进。
// 不用 throw 的原因：apidoc 是可选文档插件，配置加载期抛异常会拖垮整个应用启动。
$apidocPassword = (string) getenv('APIDOC_PASSWORD');
if ($apidocPassword === '') {
    $apidocPassword = bin2hex(random_bytes(32));
}
// secret_key 必须跨 worker 确定性：若随机则 token 互不认账、授权后立即失效（webman 各 worker 独立加载配置）。
$apidocSecretKey = (string) getenv('APIDOC_SECRET_KEY');
if ($apidocSecretKey === '') {
    $apidocSecretKey = hash('sha256', 'apidoc-secret-key|' . $apidocPassword);
}

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
                // （选配）多级分组树：title=显示名，name=匹配键（须与控制器 #[Apidoc\Group("...")] 一致），children 递归
                // 注意：控制器 #[Apidoc\Group] 的值必须命中 **叶子** name。若撞上容器 name，objtctGroupByTree
                // 走 children 分支、同名桶永不被消费 → 该控制器从菜单静默消失（比落进「未分组」更隐蔽）。
                'groups' => [
                    ['title' => '账号与认证', 'name' => 'account', 'children' => [
                        ['title' => '用户认证', 'name' => 'auth'],
                        ['title' => '验证码', 'name' => 'captcha'],
                        ['title' => '邮箱/手机验证', 'name' => 'verify'],
                        ['title' => '设备令牌', 'name' => 'device'],
                        ['title' => '用户管理', 'name' => 'user'],
                    ]],
                    ['title' => '游戏中心', 'name' => 'game_center', 'children' => [
                        ['title' => '游戏管理', 'name' => 'game'],
                        ['title' => '游戏 SDK', 'name' => 'game-sdk'],
                        ['title' => '游戏提供商', 'name' => 'provider'],
                        ['title' => '排行榜', 'name' => 'leaderboard'],
                        ['title' => '赛事管理', 'name' => 'tournament'],
                        ['title' => '组队/公会', 'name' => 'group'],
                    ]],
                    ['title' => '社交互动', 'name' => 'social', 'children' => [
                        ['title' => '好友', 'name' => 'friend'],
                        ['title' => '聊天消息', 'name' => 'chat'],
                        ['title' => '分享', 'name' => 'share'],
                        ['title' => '通知管理', 'name' => 'notification'],
                        ['title' => '运营活动', 'name' => 'activity'],
                    ]],
                    ['title' => '资金钱包', 'name' => 'finance', 'children' => [
                        ['title' => '钱包管理', 'name' => 'wallet'],
                        ['title' => '提现管理', 'name' => 'withdraw'],
                        ['title' => '支付管理', 'name' => 'payment'],
                        ['title' => '兑换管理', 'name' => 'exchange'],
                        ['title' => '优惠券', 'name' => 'coupon'],
                        ['title' => '推荐管理', 'name' => 'referral'],
                    ]],
                    ['title' => '运营支撑', 'name' => 'support', 'children' => [
                        ['title' => '工单', 'name' => 'ticket'],
                        ['title' => '公告管理', 'name' => 'announcement'],
                        ['title' => '语言管理', 'name' => 'language'],
                        ['title' => '国家配置', 'name' => 'country'],
                        ['title' => '全局搜索', 'name' => 'search'],
                        ['title' => '平台统计', 'name' => 'platform'],
                        ['title' => 'Webhook', 'name' => 'webhook'],
                    ]],
                ],
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
            // 全局访问密码，取自环境变量 APIDOC_PASSWORD（enable=false 时不校验）
            'password' => $apidocPassword,
            // 密码加密盐，取自环境变量 APIDOC_SECRET_KEY
            'secret_key' => $apidocSecretKey,
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
