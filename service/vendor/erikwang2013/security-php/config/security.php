<?php

/**
 * Security Plugin Configuration
 *
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This file controls all detection behavior.
 * Publish to your project config directory and customize as needed.
 */

return [
    /*
     * 总开关
     * 设为 false 可以临时关闭所有安全检测功能
     * 建议在调试或特定内部环境时设为 false
     */
    'enabled' => true,

    /*
     * 检测器配置
     * 每个检测器可以独立控制启用状态和处理模式
     *
     * enabled: true=启用检测, false=跳过
     * mode:
     *   'block'  — 检测到攻击时拦截请求，返回 403
     *   'log'    — 仅记录日志，不拦截，适合监控模式
     */
    'detectors' => [
        // XSS 跨站脚本攻击检测
        // 检测 <script>、onerror=、javascript: 等注入模式
        'xss' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // SQL 注入检测
        // 检测 union select、sleep(、-- 注释、or 1=1 等注入模式
        'sql_injection' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // 命令注入检测
        // 检测反引号、$()、管道符、/dev/tcp 等命令执行模式
        'command_injection' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // 路径遍历检测
        // 检测 ../、..\\、/etc/passwd、php://filter 等文件包含模式
        'path_traversal' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // 恶意文件上传检测
        // 检测文件扩展名是否在允许的白名单内，以及 PHP 标签头
        'upload' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // SSRF 服务端请求伪造检测
        // 检测内网 IP（127.x、10.x、172.16-31.x、192.168.x）、cloud metadata、危险协议
        'ssrf' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // XXE XML 外部实体注入检测
        // 检测 <!ENTITY、SYSTEM/PUBLIC 标识、DOCTYPE 声明等
        'xxe' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // HTTP 响应头注入检测
        // 检测 CRLF 换行符注入（%0d%0a、\r\n）、Set-Cookie、Location 等响应头注入
        // 注意：默认 log 模式，因为 \r\n\r\n 会匹配多段落文本（如表单 textarea）
        'header_injection' => [
            'enabled' => true,
            'mode'    => 'log',
        ],

        // 反序列化攻击检测
        // 检测 PHP 序列化对象格式（O:数字:、C:数字:）、魔术方法等
        'deserialization' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // LDAP 注入检测
        // 检测 LDAP 过滤语法（&、|、!、*）、属性枚举等
        'ldap_injection' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // 邮件头注入检测
        // 检测 Bcc、Cc、From、To 等邮件头注入，防止邮件被劫持转发
        'mail_header' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // SSTI 服务端模板注入检测
        // 检测 Jinja2/Twig（{{}}、{%%}）、FreeMarker（${}）、ERB（<%%>）等模板语法
        // 注意：默认 log 模式，因为 {{ }} 会匹配 Vue/Angular/Handlebars 前端模板
        'ssti' => [
            'enabled' => true,
            'mode'    => 'log',
        ],

        // NoSQL 注入检测
        // 检测 MongoDB $ne/$gt/$regex/$where 等操作符注入、认证绕过
        // 注意：默认 log 模式，因为 $ne/$gt 会匹配 Shell 变量、LaTeX、价格字符串
        'nosql_injection' => [
            'enabled' => true,
            'mode'    => 'log',
        ],

        // Open Redirect 开放重定向检测
        // 检测 //evil.com 协议相对URL、javascript: 伪协议、外部域名重定向
        'open_redirect' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // JWT 攻击检测
        // 检测 alg:none 签名绕过、kid 参数注入、空签名等 JWT 安全问题
        'jwt_attack' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // Host 头攻击检测
        // 检测 Host 头注入、X-Forwarded-Host 投毒、X-Original-URL 等
        'host_header' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // HTTP Request Smuggling 检测
        // 检测 Transfer-Encoding/Content-Length 不一致、TE.CL/CL.TE 攻击
        'request_smuggling' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // GraphQL 注入检测
        // 检测 __schema/__type 内省查询、深度嵌套、批量查询攻击
        'graphql_injection' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // XPATH 注入检测
        // 检测 or 1=1 布尔绕过、| 联合操作符、count/string/substring 函数注入
        'xpath_injection' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // JNDI / Log4Shell 注入检测
        // 检测 ${jndi:ldap://、${lower:j、${env:、${::-j} 等 Log4j 漏洞利用
        'jndi_injection' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // SSI 服务端包含注入检测
        // 检测 <!--#exec cmd=、<!--#include file=、<!--#echo var= 等 SSI 指令
        'ssi_injection' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // CSV 公式注入检测
        // 检测 =cmd|、=powershell、HYPERLINK() 等 Excel 公式攻击
        'csv_injection' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // 敏感数据泄露检测
        // 检测信用卡号、AWS Key、私钥头、数据库连接串、API Token、JWT Secret
        'data_leak' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // Prototype Pollution 检测
        // 检测 __proto__、constructor.prototype、__defineSetter__ 等 JS 原型污染
        'prototype_pollution' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // WebSocket 劫持检测
        // 检测 Upgrade:websocket 头注入、null Origin 绕过、WS URL 注入
        'websocket' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // CORS 绕过检测
        // 检测 Origin 头注入、Access-Control-* 头注入、preflight 请求投毒
        'cors' => [
            'enabled' => true,
            'mode'    => 'block',
        ],

        // DNS Rebinding 检测
        // 检测 Host 头内网 IP（127/10/172/192/0.0.0.0）、localhost、无 TLD 短主机名
        'dns_rebinding' => [
            'enabled' => true,
            'mode'    => 'block',
        ],
    ],

    /*
     * 拦截响应配置
     * 当检测器的 mode 为 'block' 时生效
     */
    // HTTP 状态码，通常使用 403（禁止访问）或 406（不可接受）
    'block_status_code' => 403,

    // 返回给客户端的内容，{type} 会被替换为攻击类型标识
    'block_message' => 'Request blocked by security policy',

    /*
     * 日志配置
     *
     * enabled: 是否记录攻击日志
     * channel: 日志通道
     *   'file' — 写入文件（推荐）
     * path: 日志文件路径，留空则使用 sys_get_temp_dir() . '/security.log'
     * max_size: 单个日志文件最大体积，单位 MB，超过后自动轮转。设为 0 禁用轮转
     * dedup_seconds: 去重窗口（秒）。同一请求内，相同 IP+类型+字段在此时间内不重复记录。设为 0 禁用
     *   注意：在 PHP-FPM 等短生命周期模式下，去重仅对单次请求有效，非跨请求去重
     */
    'log' => [
        'enabled'       => true,
        'channel'       => 'file',
        'path'          => '',
        'max_size'      => 10,
        'dedup_seconds' => 5,
    ],

    /*
     * IP 白名单
     * 白名单内的 IP 地址不进行安全检测
     * 格式：支持单个 IP 和 CIDR 网段
     * 示例：
     *   '127.0.0.1',         — 单个 IP
     *   '10.0.0.0/8',        — CIDR 网段
     *   '192.168.1.0/24',    — /24 子网
     */
    'whitelist_ips' => [],

    /*
     * 字段白名单
     * 这些字段名的值将跳过检测，不报告威胁
     * 框架自带的 token 字段、表单辅助字段等应当加入
     *
     * 例如 Laravel 的 _token（CSRF token）可能包含随机字符串，
     * 加入白名单可以避免误报
     */
    'whitelist_fields' => ['_token', '_method', 'csrf_token'],
];
