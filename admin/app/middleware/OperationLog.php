<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use app\common\SnowflakeService;

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
            $log->path       = $request->path();
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
     * 递归过滤敏感字段，防止密码等泄露到日志
     */
    private function filterSensitive(array $data): array
    {
        $keys = ['password', 'old_password', 'new_password', 'new_password_confirmation', 'token', 'secret', 'access_token', 'refresh_token'];
        foreach ($data as $key => $value) {
            if (in_array($key, $keys, true)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = $this->filterSensitive($value);
            }
        }
        return $data;
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
