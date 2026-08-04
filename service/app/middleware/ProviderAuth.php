<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use app\model\Game;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class ProviderAuth implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $gameId = $request->header('X-Game-Id', '');
        $timestamp = $request->header('X-Timestamp', '');
        $signature = $request->header('X-Signature', '');

        if (empty($gameId) || empty($timestamp) || empty($signature)) {
            return json(['code' => 401, 'message' => 'Missing auth headers', 'data' => []]);
        }

        $game = Game::find((int) $gameId);
        if (!$game || (int) $game->status !== 1) {
            return json(['code' => 401, 'message' => 'Unknown or disabled game', 'data' => []]);
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return json(['code' => 401, 'message' => 'Request expired', 'data' => []]);
        }

        $expected = $this->computeSignature($game, $timestamp, $request);
        if (!hash_equals($expected, $signature)) {
            return json(['code' => 401, 'message' => 'Invalid signature', 'data' => []]);
        }

        $request->gameId = (int) $gameId;
        $request->game = $game;

        return $next($request);
    }

    private function computeSignature(Game $game, string $timestamp, Request $request): string
    {
        $body = (string) $request->rawBody();
        $signStr = $game->id . ':' . $timestamp . ':' . $request->method() . ':' . $request->path() . ':' . $body;
        return hash_hmac('sha256', $signStr, $game->api_secret);
    }
}
