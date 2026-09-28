<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use common\model\User;
use Webman\Http\Request;
use Webman\MiddlewareInterface;
use Webman\Http\Response;

/**
 * C端用户JWT认证中间件
 *
 * 从 Authorization Bearer Token 中解析用户ID，
 * 校验Token有效性，注入 $request->userId
 */
class UserAuth implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return json(['code' => 401, 'message' => '未登录', 'data' => []]);
        }

        try {
            $payload = jwt_wrapper()->verify($token);
        } catch (\Throwable $e) {
            return json(['code' => 401, 'message' => 'Token已过期或无效', 'data' => []]);
        }

        // 2FA 待验证票据（login 遇已开 2FA 的账号时签发，scope=pending_2fa）只是登录半成品，
        // 唯一用途是去 /api/v1/2fa/verify 换发正式令牌；放行它等于让拿不出 TOTP 的人
        // 直达提现/兑换等受保护端点。access token 从不带 scope，故按「无 scope 才放行」
        // 的白名单判定：将来再出现别的半成品票据也默认拒收（fail-closed）。
        if (($payload->scope ?? null) !== null) {
            return json(['code' => 401, 'message' => 'Token已过期或无效', 'data' => []]);
        }

        $user = User::find($payload->sub);
        if (!$user || $user->status !== 1) {
            return json(['code' => 401, 'message' => '用户不存在或已禁用', 'data' => []]);
        }

        $request->userId = (int) $payload->sub;

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
