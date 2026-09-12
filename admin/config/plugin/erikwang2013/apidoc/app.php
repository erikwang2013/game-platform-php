<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * erikwang2013/apidoc 配置 — 管理后台 API 文档
 * 访问: http://localhost:8789/apidoc/
 * 说明: 按 erikwang2013/apidoc-php 的配置结构书写，文档前缀与真实路由组 /admin/v1 对齐
 */

// 访问密码与加密盐一律从环境变量读取（.env 不纳入版本控制），杜绝明文口令入库。
// fail-closed：APIDOC_PASSWORD 未配置时生成进程级随机口令，各 worker 互不相同 ⇒ 无人能通过校验，
// 绝不退化成"空密码可进"。此处不用 throw：apidoc 是可选文档插件，配置加载期抛异常会拖垮整个应用启动，
// 把故障面限制在文档页内部更稳妥（对比 config/hashids.php 的核心配置 fail-closed 语义）。
$apidocPassword = (string) getenv('APIDOC_PASSWORD');
if ($apidocPassword === '') {
    $apidocPassword = bin2hex(random_bytes(32));
}
// secret_key 必须跨 worker 确定性：若随机则 token 互不认账、授权后立即失效（webman 各 worker 独立加载配置）。
// 故由密码派生，配置一致结果即一致；密码随机时派生的盐同样不可预测。
$apidocSecretKey = (string) getenv('APIDOC_SECRET_KEY');
if ($apidocSecretKey === '') {
    $apidocSecretKey = hash('sha256', 'apidoc-secret-key|' . $apidocPassword);
}

return [
    // 是否启用本插件
    'enable' => true,
    'apidoc' => [
        // （选配）文档标题，显示在左上角与首页
        'title' => '全球游戏聚合平台 — 管理后台 API',
        // （选配）文档描述，显示在首页
        'desc' => '游戏管理、提现审核、用户管理、支付管理、KYC审核、公告管理、统计分析',
        // （必须）设置文档的应用/版本，可配置多个；每个应用对应一个控制器目录
        'apps' => [
            [
                // （必须）应用标题，显示在文档左侧导航
                'title' => '管理后台 v1',
                // （必须）控制器目录地址，相对应用根目录，反斜杠分隔
                'path' => 'app\admin\v1\controller',
                // （必须）应用唯一 key，注解中通过它引用该应用
                'key' => 'admin',
                // （选配）多级分组树：title=显示名，name=控制器 #[Apidoc\Group] 字面量，children=子级
                // 注意：控制器 #[Apidoc\Group] 的值必须命中 **叶子** name。若撞上容器 name，objtctGroupByTree
                // 走 children 分支、同名桶永不被消费 → 该控制器从菜单静默消失（比落进「未分组」更隐蔽）。
                'groups' => [
                    ['title' => '数据概览', 'name' => 'overview', 'children' => [
                        ['title' => '仪表盘',   'name' => 'dashboard'],
                        ['title' => '数据分析', 'name' => 'analytics'],
                        ['title' => '数据报表', 'name' => 'report'],
                    ]],
                    ['title' => '游戏运营', 'name' => 'game_ops', 'children' => [
                        ['title' => '游戏管理', 'name' => 'game'],
                        ['title' => '游戏分类', 'name' => 'gamecategory'],
                        ['title' => '游戏区服', 'name' => 'gameserver'],
                        ['title' => '排行榜',   'name' => 'leaderboard'],
                        ['title' => '公告管理', 'name' => 'announcement'],
                        ['title' => '分享统计', 'name' => 'share'],
                        ['title' => '搜索',     'name' => 'search'],
                        ['title' => '成就管理', 'name' => 'achievement'],
                        ['title' => '运营活动', 'name' => 'activity'],
                    ]],
                    ['title' => '用户运营', 'name' => 'user_ops', 'children' => [
                        ['title' => '平台用户',  'name' => 'platform_user'],
                        ['title' => 'VIP 等级',  'name' => 'vip'],
                        ['title' => '实名认证',  'name' => 'identity'],
                        ['title' => '组队/公会', 'name' => 'group'],
                        ['title' => '工单管理',  'name' => 'ticket'],
                    ]],
                    ['title' => '资金财务', 'name' => 'finance', 'children' => [
                        ['title' => '提现管理', 'name' => 'withdraw'],
                        ['title' => '支付管理', 'name' => 'payment'],
                        ['title' => '优惠券',   'name' => 'coupon'],
                    ]],
                    ['title' => '风控安全', 'name' => 'risk_center', 'children' => [
                        ['title' => '风控中心',   'name' => 'risk'],
                        ['title' => '反作弊事件', 'name' => 'anticheat'],
                    ]],
                    ['title' => '系统权限', 'name' => 'system', 'children' => [
                        ['title' => '管理员用户', 'name' => 'admin_user'],
                        ['title' => '角色管理',   'name' => 'role'],
                        ['title' => '权限管理',   'name' => 'permission'],
                        ['title' => '系统配置',   'name' => 'config'],
                        ['title' => '个人中心',   'name' => 'profile'],
                        ['title' => '操作日志',   'name' => 'log'],
                        ['title' => 'CDN 管理',   'name' => 'cdn'],
                        ['title' => '国家配置',   'name' => 'country_config'],
                    ]],
                    ['title' => '运维工具', 'name' => 'ops', 'children' => [
                        ['title' => '健康检查', 'name' => 'health'],
                        ['title' => '监控指标', 'name' => 'metrics'],
                        ['title' => 'API 文档', 'name' => 'docs'],
                        ['title' => '数据导出', 'name' => 'export'],
                        ['title' => '数据导入', 'name' => 'import'],
                        ['title' => '文件上传', 'name' => 'upload'],
                    ]],
                ],
            ],
            [
                // （必须）应用标题，显示在文档左侧导航
                'title' => '公开 API v1',
                // （必须）控制器目录地址，相对应用根目录，反斜杠分隔
                'path' => 'app\api\v1\controller',
                // （必须）应用唯一 key，注解中通过它引用该应用
                'key' => 'admin_api',
                // （选配）多级分组树：title=显示名，name=控制器 #[Apidoc\Group] 字面量，children=子级
                // 注意：控制器 #[Apidoc\Group] 的值必须命中 **叶子** name。若撞上容器 name，objtctGroupByTree
                // 走 children 分支、同名桶永不被消费 → 该控制器从菜单静默消失（比落进「未分组」更隐蔽）。
                'groups' => [
                    ['title' => '认证鉴权', 'name' => 'access', 'children' => [
                        ['title' => '管理员认证', 'name' => 'auth'],
                        ['title' => '点击验证码', 'name' => 'captcha'],
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
            // url 前缀，版本号置于 URL 路径，与真实路由 /admin/v1 对齐（第一个应用为主应用）
            'prefix' => "/admin/v1",
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
            // 是否启用访问密码验证；管理端文档需密码访问
            'enable' => true,
            // 全局访问密码，取自环境变量 APIDOC_PASSWORD（未配置则无人能登录，见文件头说明）
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
                ['name' => 'Authorization', 'type' => 'string', 'require' => true, 'desc' => 'Bearer Token (JWT)'],
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
