<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\api\v1\controller\AuthController;
use Erikwang2013\Jwt\JWT;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use support\Request;

/**
 * 刷新令牌契约：admin 的三个客户端（Angular/React/Flutter）都把 refresh_token 放在请求体里，
 * 该端点在 /api/v1 公开组（config/route.php:313）内，不走 AdminAuth 中间件。
 *
 * 本用例钉住的是 jwt-webman v2.1.2 引入的 decode() 硬闸：默认 $allowRefresh=false 时
 * 拒绝 token_type=refresh 的令牌，刷新技术上正是要解这类令牌，必须显式放行。
 * 回归时会表现为 401「刷新令牌无效或已过期」，因此断言 code === 0 + 新的 access_token。
 *
 * 令牌由控制器自身的 getJWT() 铸造（反射取私有工厂），保证与控制器校验时用的是
 * 同一份配置与密钥，避免测试自造配置造成的假阴/假阳。
 */
class AuthControllerRefreshTest extends TestCase
{
    /**
     * 不存在的管理员 ID：sub 非 0 会走 AdminUser::find() 分支但取不到记录，
     * 因而不写 last_login_at，用例无副作用；刷新路径本身不依赖库中已有数据。
     */
    private const SUB = 99999999999999999;

    #[Test]
    public function refreshAcceptsBodyRefreshToken(): void
    {
        $body = $this->refresh($this->mintRefreshToken());

        $this->assertSame(0, $body['code'], 'body 携带 refresh_token 的请求必须刷新成功（不得再被 decode 硬闸挡成 401）');
        $this->assertNotEmpty($body['data']['access_token'] ?? '', '应下发新的 access_token');
        $this->assertNotEmpty($body['data']['refresh_token'] ?? '', '应轮换出新的 refresh_token');
    }

    #[Test]
    public function refreshRejectsExpiredRefreshToken(): void
    {
        // 过期 1 小时，远超 config 的 leeway=60s
        $body = $this->refresh($this->mintRefreshToken(-3600));

        $this->assertSame(401, $body['code'], '过期 refresh token 必须被拒');
        $this->assertEmpty($body['data']['access_token'] ?? '', '被拒时不得下发 access_token');
    }

    #[Test]
    public function refreshRejectsForgedRefreshToken(): void
    {
        $this->assertSame(401, $this->refresh($this->forge($this->mintRefreshToken()))['code'], '签名被篡改的 refresh token 必须被拒');
    }

    #[Test]
    public function refreshRejectsMissingToken(): void
    {
        $body = $this->refresh('');

        $this->assertSame(422, $body['code'], '未提供 refresh_token 时必须拒绝，不得放行');
        $this->assertEmpty($body['data']['access_token'] ?? '', '被拒时不得下发 access_token');
    }

    /** 与控制器同一份配置/密钥铸造 refresh token */
    private function mintRefreshToken(int $expire = 0): string
    {
        $jwt = (new ReflectionMethod(AuthController::class, 'getJWT'))->invoke(null);
        assert($jwt instanceof JWT);

        return $jwt->encode(['sub' => self::SUB, 'token_type' => 'refresh'], $expire);
    }

    /** 篡改签名段正中一个字符：该位置 6 bit 全部有效，必然改变签名字节 */
    private function forge(string $token): string
    {
        $parts = explode('.', $token);
        $sig = $parts[2];
        $mid = intdiv(strlen($sig), 2);
        $parts[2] = substr($sig, 0, $mid) . ($sig[$mid] === 'A' ? 'B' : 'A') . substr($sig, $mid + 1);

        return implode('.', $parts);
    }

    /** @return array<string, mixed> */
    private function refresh(string $bodyToken): array
    {
        $request = new Request("POST /api/v1/auth/refresh HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost(['refresh_token' => $bodyToken]);

        return json_decode((new AuthController())->refresh($request)->rawBody(), true);
    }
}
