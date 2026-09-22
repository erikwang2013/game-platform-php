<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\AuthController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Request;

/**
 * 刷新令牌契约：C 端（Angular/React/Flutter/HarmonyOS）只把 refresh_token 放在请求体，
 * 不带 Authorization 头；refresh() 必须优先读 body，缺省才回退 header。
 * 客户端不变的前提下，一旦回退成只读 header，第一个用例即刻失败。
 *
 * 刷新路径不触库（JWT 校验+轮换+decode），因此本测试无需数据库。
 */
class AuthControllerRefreshTest extends TestCase
{
    private const SUB = 4242;

    #[Test]
    public function refreshAcceptsBodyTokenWithoutAuthorizationHeader(): void
    {
        $body = $this->refresh(null, jwt_wrapper()->create(['sub' => self::SUB, 'token_type' => 'refresh']));

        $this->assertSame(0, $body['code'], '仅带 body refresh_token 的请求必须刷新成功');
        $this->assertNotEmpty($body['data']['access_token'] ?? '', '应下发新的 access_token');
        $this->assertNotEmpty($body['data']['refresh_token'] ?? '', '应轮换出新的 refresh_token');
    }

    #[Test]
    public function refreshAcceptsHeaderTokenAsFallback(): void
    {
        // support\Request 由原始报文构造，不落 $_SERVER；JwtWrapper::currentToken() 读的是
        // $_SERVER['HTTP_AUTHORIZATION']，故按真实网关行为注入
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . jwt_wrapper()->create(['sub' => self::SUB, 'token_type' => 'refresh']);
        try {
            $body = $this->refresh(null, '');
        } finally {
            unset($_SERVER['HTTP_AUTHORIZATION']);
        }

        $this->assertSame(0, $body['code'], '仅带 Authorization 头的请求仍须刷新成功（回退路径）');
    }

    #[Test]
    public function refreshRejectsAccessTokenInBody(): void
    {
        // token_type 缺省即 access token，不能当 refresh token 用
        $body = $this->refresh(null, jwt_wrapper()->create(['sub' => self::SUB]));

        $this->assertSame(401, $body['code'], 'access token 不得通过刷新校验');
    }

    #[Test]
    public function refreshRejectsMissingToken(): void
    {
        $body = $this->refresh(null, '');

        $this->assertSame(401, $body['code'], '无任何 token 时必须 401');
    }

    /** @return array<string, mixed> */
    private function refresh(?string $header, string $bodyToken): array
    {
        $request = new Request(
            "POST /api/v1/auth/refresh HTTP/1.1\r\nHost: localhost\r\n"
            . ($header === null ? '' : "Authorization: Bearer {$header}\r\n")
            . "\r\n"
        );
        $request->setPost(['refresh_token' => $bodyToken]);

        return json_decode((new AuthController())->refresh($request)->rawBody(), true);
    }
}
