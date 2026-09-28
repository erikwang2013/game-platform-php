<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use common\SnowflakeService;

class OperationLog implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $method = $request->method();

        if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return $handler($request);
        }

        $response = $handler($request);

        try {
            // 递归过滤敏感字段
            $input = $this->filterSensitive($request->all());

            $log = new \app\model\OperationLog();
            $log->id         = SnowflakeService::generate();
            $log->user_id    = $request->adminId ?? 0;
            $log->action     = $method;
            $log->method     = $method;
            $log->path       = $this->stripVersionSegment($request->path());
            $log->ip         = $request->getRealIp();
            $log->source     = $this->detectSource($request);
            $log->input      = json_encode($input, JSON_UNESCAPED_UNICODE);
            $log->created_at = date('Y-m-d H:i:s');
            $log->save();
        } catch (\Throwable $e) {
            // 日志记录失败不应影响业务请求
        }

        return $response;
    }

    /**
     * 剔除 URL 路径中的版本段（/admin/v1/user → /admin/user），保持审计记录与权限 slug 语义一致
     */
    private function stripVersionSegment(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        if (count($segments) > 1 && preg_match('/^v\d+$/', $segments[1])) {
            unset($segments[1]);
        }
        return '/' . implode('/', $segments);
    }

    /** 命中即整体打码的词（按「词」匹配，不是整串匹配） */
    private const SENSITIVE_WORDS = [
        'password', 'passwd', 'pwd', 'secret', 'token', 'credential',
        'signature', 'salt', 'private', 'key', 'config',
    ];

    /**
     * 递归过滤敏感字段，防止密码/密钥等泄露到日志
     */
    private function filterSensitive(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = $this->filterSensitive($value);
            }
        }
        return $data;
    }

    /**
     * 敏感键判定：按「词」切分后匹配，覆盖 api_key / api_secret / secret_access_key /
     * access_token / newPassword 这类前后缀式命名。
     *
     * 旧实现是 `in_array($key, $keys, true)` 整串精确匹配，只挡得住自成一词的
     * password/token/secret，所以 api_key、api_secret、config 全部原样落进操作日志
     * —— 而这三者恰恰是 Game.api_secret（SDK 签名密钥）与 CDN/支付凭据（库内密文、
     * 日志明文）的载体。
     *
     * 迁移无损性：旧表 8 条词条在新规则下**逐条仍然命中** —— password、old_password、
     * new_password、new_password_confirmation、token、secret、access_token、refresh_token
     * 切词后各自切得出 password / token / secret（如 old_password → [old, password]）。
     * 这次改动只扩大覆盖面，不缩小。
     *
     * 两处刻意的取舍，都不是凭感觉收窄，是照本仓实际字段名定的：
     * 1. `config` 一律整体打码，不递归。CdnProviderController.php:93 与
     *    PaymentController.php:118 都把它当凭据载体，而 CdnProbeService.php:44-45 读的键名
     *    含缩写（ak / sk），缩写过不了任何词表 ⇒ 只递归而不整体打码等于假修。
     *    代价：ActivityController / RiskRuleController 的 config 正文（非凭据）也不再入日志，
     *    中间件里无法区分同名字段的两种语义。路径 + 业务主键仍足以定位改动。
     * 2. 裸 `key`（且它必须是键名里唯一的词）不打码：本仓叫 `key` 的字段是标识符不是凭据 ——
     *    AchievementController.php:50 的成就 key、ConfigController.php:88 的配置键名。
     *    打码会把「改了哪个配置项/哪个成就」从审计里抹掉。`api_key` / `secret_key` 这类
     *    组合词仍然命中（见上）。
     */
    private function isSensitiveKey(string $key): bool
    {
        // 先在分隔符（_ - . 空格）和 camelCase 驼峰处切词；不带 i 修饰符，否则 [A-Z] 会匹配小写、
        // 把每个字符边界都切一刀；大小写统一交给 strtolower。
        $words = preg_split('/[^A-Za-z0-9]+|(?<=[a-z0-9])(?=[A-Z])/', $key, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_map('strtolower', $words);

        foreach ($words as $word) {
            if (!in_array($word, self::SENSITIVE_WORDS, true)) {
                continue;
            }
            if ($word === 'key' && count($words) === 1) {
                continue;
            }
            return true;
        }

        return false;
    }

    /**
     * 从请求头检测客户端来源端
     */
    private function detectSource(Request $request): string
    {
        // 优先使用原生客户端显式声明的平台头
        $platform = $request->header('X-Client-Platform', '');
        if ($platform && in_array(strtolower($platform), [
            'ipados', 'macos', 'windows', 'linux', 'ios', 'android', 'harmonyos', 'web',
        ], true)) {
            return strtolower($platform);
        }

        // 通过 User-Agent 推断
        $ua = $request->header('User-Agent', '');

        if (stripos($ua, 'HarmonyOS') !== false || stripos($ua, 'OpenHarmony') !== false) {
            return 'harmonyos';
        }
        if (stripos($ua, 'iPad') !== false) {
            return 'ipados';
        }
        if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iOS') !== false || stripos($ua, 'CFNetwork') !== false) {
            return 'ios';
        }
        if (stripos($ua, 'Android') !== false) {
            return 'android';
        }
        if (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS') !== false) {
            return 'macos';
        }
        if (stripos($ua, 'Windows') !== false) {
            return 'windows';
        }
        if (stripos($ua, 'Linux') !== false) {
            return 'linux';
        }

        return 'web';
    }
}
