<?php
/**
 * tests/api 共享测试基座（无框架，纯 PHP CLI）。
 *
 * 用法:  php tests/api/admin_test.php   (环境变量: BASE_URL / BASE_URL_SERVICE / TOKEN)
 *
 * 约定: webman 业务接口 HTTP 恒为 200, 业务结果在 body.code:
 *   0=成功, 4xx=参数/权限类业务错误, 5xx=服务端错误。
 * 中间件/路由层仍会返回真正的 HTTP 状态(401/404/429/405 等)。
 * 本基座以 body.code 为主、HTTP 状态为兜底进行断言。
 */

/** @return array{0:int,1:?array,2:string,3:?string} [http_code, json_body, raw, transport_error] */
function api(string $method, string $path, ?array $body = null, ?string $token = null, array $extraHeaders = [], string $baseKey = 'BASE_URL'): array
{
    $base = rtrim(getenv($baseKey) ?: '', '/');
    if ($base === '') {
        $base = $baseKey === 'BASE_URL_SERVICE' ? 'http://127.0.0.1:8795' : 'http://127.0.0.1:8789';
    }
    $headers = ['Content-Type: application/json'];
    if ($token !== null && $token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    foreach ($extraHeaders as $h) {
        $headers[] = $h;
    }
    $ctx = stream_context_create(['http' => [
        'method'        => $method,
        'header'        => implode("\r\n", $headers),
        'content'       => $body === null ? null : json_encode($body),
        'timeout'       => 20,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($base . $path, false, $ctx);
    if ($raw === false) {
        return [0, null, '', error_get_last()['message'] ?? 'connection error'];
    }
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})#', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    $json = json_decode($raw, true);
    return [$code, is_array($json) ? $json : null, $raw, null];
}

/** body.code 归一化: HTTP 200 时取 body.code, 否则取 HTTP 状态码 */
function biz_code(array $res): int
{
    [$http, $json] = $res;
    if ($http === 0) return 0;
    if ($http === 200 && isset($json['code']) && is_int($json['code'])) {
        return $json['code'];
    }
    return $http;
}

$GLOBALS['__T'] = ['pass' => 0, 'fail' => 0, 'skip' => 0, 'notes' => [], 'fails' => []];

function t_ok(string $name, bool $cond, string $detail = ''): void
{
    if ($cond) {
        $GLOBALS['__T']['pass']++;
    } else {
        $GLOBALS['__T']['fail']++;
        $GLOBALS['__T']['fails'][] = $name . ' :: ' . substr($detail, 0, 160);
        echo "FAIL $name :: " . substr($detail, 0, 300) . PHP_EOL;
    }
}

/** @param int[] $allow 允许的业务码/HTTP 码 (0 恒为成功) */
function t_check(string $name, array $res, array $allow = [0]): void
{
    [$http, $json, $raw, $err] = $res;
    if ($err) {
        t_ok($name, false, "transport error: $err");
        return;
    }
    $biz = biz_code($res);
    if (in_array($biz, $allow, true) || $biz === 0) {
        $GLOBALS['__T']['pass']++;
        if ($biz === 403) {
            t_note($name, '403 RBAC 权限拦截(角色权限不足)');
        }
        return;
    }
    $msg = $json['message'] ?? $raw;
    $GLOBALS['__T']['fail']++;
    $GLOBALS['__T']['fails'][] = "$name [code=$biz] :: " . substr((string) $msg, 0, 160);
    echo "FAIL $name [code=$biz] :: " . substr((string) $msg, 0, 300) . PHP_EOL;
}

function t_skip(string $name): void
{
    $GLOBALS['__T']['skip']++;
    echo "SKIP $name" . PHP_EOL;
}

function t_note(string $name, string $detail): void
{
    $GLOBALS['__T']['notes'][] = "$name :: $detail";
}

/** 从 config/route.php 解析全部路由, 支持任意嵌套 group (function 语法) */
function collect_routes(string $file, string $prefix = ''): array
{
    $src = file_get_contents($file);
    $src = preg_replace('#/\*.*?\*/#s', '', $src) ?? $src;
    $src = preg_replace('#//[^\n]*#', '', $src) ?? $src;
    $out = [];
    collect_in($src, $prefix, $out);
    return $out;
}

function collect_in(string $src, string $prefix, array &$out): void
{
    $blocks = [];
    $src = preg_replace_callback(
        "#(*NO_JIT)group\(\s*'([^']+)'\s*,\s*function\s*\([^)]*\)\s*(\{(?:[^{}]|(?2))*\})#s",
        function ($m) use (&$blocks) {
            $blocks[] = [$m[1], $m[2]];
            return '';
        },
        $src
    ) ?? $src;
    preg_match_all("#(get|post|put|delete|patch|any)\(\s*'([^']+)'#", $src, $m, PREG_SET_ORDER);
    foreach ($m as $r) {
        $out[] = [strtoupper($r[1]), $prefix . $r[2]];
    }
    foreach ($blocks as [$g, $body]) {
        collect_in($body, $prefix . $g, $out);
    }
}

function t_summary(string $suite): void
{
    $t = $GLOBALS['__T'];
    printf(PHP_EOL . "==== %s 结果: PASS=%d FAIL=%d SKIP=%d ====" . PHP_EOL, $suite, $t['pass'], $t['fail'], $t['skip']);
    if ($t['notes']) {
        echo "-- 备注 --" . PHP_EOL;
        foreach ($t['notes'] as $n) {
            echo "  * $n" . PHP_EOL;
        }
    }
    exit($t['fail'] > 0 ? 1 : 0);
}
