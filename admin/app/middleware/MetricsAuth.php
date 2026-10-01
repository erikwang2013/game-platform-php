<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;

/**
 * `/metrics` 专用认证：**管理员 JWT（原路径，RBAC 照旧）** 或 **配置里的静态抓取令牌**。
 *
 * 为什么不能直接用 AdminAuth：JWT 会过期，抓取器需要长期凭据；而且 AdminAuth / AdminPermission
 * 的拒绝是「HTTP 200 + JSON 信封」（全仓约定），抓取器读不懂 —— 只看状态码的会把 401 当成功、
 * 再在解析正文时报格式错，看 Content-Type 的会把 target 判成 down。
 * 本中间件是**唯一**一处把拒绝改写成 `401/403 text/plain` 的地方，且只挂在 /metrics 一条路由上。
 *
 * 静态令牌**等价于已授权**（该端点只读）⇒ 命中即直接放行，不再走 AdminPermission；
 * 管理员 JWT 那条路仍原样经过 AdminAuth → AdminPermission —— 两个中间件由本文件**直接委托**，
 * 不复制它们的校验逻辑（复制会造出第二真值源，两边迟早分叉）。
 *
 * ⚠ **本类只服务 `/metrics` 一条路由，且必须委托 `AdminPermission`。** 这两条约束各有一条钉子，
 * 改本文件或改 config/route.php 之前先读它们 —— 它们是「MetricsAuth 仍然在 /metrics 上执行 RBAC」
 * 的**机器证明**（本仓没有「跳过单个中间件」的机制，RBAC 是委托进来的、在 `getMiddleware()` 里看不见，
 * 所以只能靠行为钉子而非路由表来钉）：
 *  - 把本类挂到**别的路由**上 ⇒
 *    `MetricsEndpointAuthTest::onlyTheMetricsRouteCarriesMetricsAuth` 红
 *    （断言全路由表里挂 MetricsAuth 的集合恰好是 `['/metrics']`，且 `/metrics` 上只挂 MetricsAuth）；
 *  - 让本类**不再委托 RBAC**（删掉下面那句 `new AdminPermission()->process(...)`）⇒
 *    `MetricsEndpointAuthTest::adminJwtWithoutMetricsPermissionIsStillRejectedByRbac` 红
 *    （真实管理员 + 有效 JWT + 权限缓存无 `get.metrics` ⇒ 必须仍被 403）。
 *  另：`PermissionSeedParityTest` 的取集判据为「挂了 AdminPermission **或** MetricsAuth」，
 *  那条豁免就靠上面两条钉子兜底（豁免点有具名注释）。
 */
class MetricsAuth
{
    public function process(Request $request, callable $next): Response
    {
        if ($this->scrapeTokenMatches($request)) {
            return $next($request);
        }

        // $adminId 只在 AdminAuth 认下这枚 JWT 后才被赋值 ⇒ 它同时充当"认证过了吗"与"以谁的身份"。
        // 用「有没有身份」而不是「AdminAuth 有没有返回」来分流 401/403：签得出但无 sub 的令牌
        // 会让 AdminAuth 通过而 adminId=0，那仍属于"没登录"，报 403 就指错了原因。
        $adminId    = 0;
        $authorized = false;

        $response = (new AdminAuth())->process($request, function (Request $req) use (&$adminId, &$authorized, $next) {
            $adminId = (int) ($req->adminId ?? 0);
            return (new AdminPermission())->process($req, function (Request $req2) use (&$authorized, $next) {
                $authorized = true;
                return $next($req2);
            });
        });

        if ($authorized) {
            return $response;
        }

        return $adminId === 0
            ? $this->reject(401, 'Unauthorized: provide an admin bearer token or the metrics scrape token')
            : $this->reject(403, 'Forbidden: this admin account lacks the get.metrics permission');
    }

    /**
     * 静态令牌比对。fail-closed 的两个必要条件，缺任何一个都是开洞：
     *  - 配置为空/未设置 ⇒ 永不匹配（`hash_equals('', '')` 恒真，所以空配置必须**先**挡掉）
     *  - 请求未带凭据 ⇒ 永不匹配（否则"空配置 + 空 Bearer"恰好相等）
     * 比对走 hash_equals（时序安全），不用 == / ===。
     */
    private function scrapeTokenMatches(Request $request): bool
    {
        $configured = $this->configuredToken();
        if ($configured === '') {
            return false;
        }

        $presented = $this->bearerToken($request);
        if ($presented === '') {
            return false;
        }

        return hash_equals($configured, $presented);
    }

    /**
     * 真值来自 config/app.php 的 metrics_scrape_token（← 环境变量 METRICS_SCRAPE_TOKEN），绝不硬编码。
     *
     * protected 而非 private：仅供测试覆写以注入固定令牌 —— 与 AdminPermission::getUserPermissions
     * 同一做法。不然用例只能靠改 .env 来切换令牌，等于把钉子钉在环境上。
     */
    protected function configuredToken(): string
    {
        return (string) config('app.metrics_scrape_token', '');
    }

    /** 与 AdminAuth 同一套取法（Bearer 前缀可有可无） */
    private function bearerToken(Request $request): string
    {
        return trim(str_replace('Bearer ', '', $request->header('Authorization', '')));
    }

    /**
     * 单行纯文本：只认状态码、只认 Content-Type 的抓取器都能直接读懂原因。
     *
     * ⚠ **有意不走 `trans()`** —— 这是本仓「进 HTTP 响应的文案要 trans」约定的一处**例外**，
     * 别"修正"它：这里是给抓取器与运维读的**机器可读运维行**，不是面向用户的响应文案。
     * 三条理由：① 同端点的**成功**正文（`# HELP` / `# TYPE` / 指标名）本来就全英文，拒绝行跟着英文才一致；
     * ② 走 trans 会按**请求 locale** 渲染成中文，对 Prometheus 是负收益（它只看状态码与 Content-Type）；
     * ③ 拒绝原因要能直接被 `curl`/日志引用，不该随调用方的 `Accept-Language` 变。
     * 真要做多语言，也是在抓取侧或告警侧做映射，不是让端点按请求头变脸。
     */
    private function reject(int $status, string $reason): Response
    {
        return response("{$status} {$reason}\n", $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
