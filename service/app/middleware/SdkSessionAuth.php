<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use common\model\Game;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 自研/内嵌游戏 SDK 会话认证（M5）
 *
 * Authorization: Bearer {base64url(JSON{game_id,user_id,exp})}.{hex HMAC-SHA256(payload, api_secret)}
 * 令牌由 GET /api/game/session 签发，TTL 5 分钟；user_id 只取自令牌（防越权），请求体不可覆盖。
 */
class SdkSessionAuth implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $authorization = $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $m)) {
            return json(['code' => 401, 'message' => 'Missing bearer token', 'data' => []]);
        }

        $token = trim($m[1]);
        if (substr_count($token, '.') !== 1) {
            return json(['code' => 401, 'message' => 'Invalid token', 'data' => []]);
        }
        [$payload, $signature] = explode('.', $token, 2);

        $claims = json_decode($this->base64UrlDecode($payload), true);
        if (!is_array($claims) || empty($claims['game_id']) || empty($claims['user_id']) || empty($claims['exp'])) {
            return json(['code' => 401, 'message' => 'Invalid token claims', 'data' => []]);
        }

        if ((int) $claims['exp'] < time()) {
            return json(['code' => 401, 'message' => 'Token expired', 'data' => []]);
        }

        $game = Game::find((int) $claims['game_id']);
        if (!$game || (int) $game->status !== 1) {
            return json(['code' => 401, 'message' => 'Unknown or disabled game', 'data' => []]);
        }

        $expected = hash_hmac('sha256', $payload, $game->api_secret);
        if (!hash_equals($expected, $signature)) {
            return json(['code' => 401, 'message' => 'Invalid signature', 'data' => []]);
        }

        $request->gameId = (int) $claims['game_id'];
        $request->game = $game;
        $request->userId = (int) $claims['user_id'];

        return $next($request);
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
