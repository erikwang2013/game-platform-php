<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\AuthController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use support\Request;
use support\Response;

/**
 * 注册密码强度策略：弱口令必须在触库前被 422 拒绝。
 * 校验失败早于任何 DB 访问（register() 的 fail 分支在第一处 User::where 之前），
 * 因此本测试不需要数据库。
 *
 * 弱点说明：service 侧 register() 没有 captcha 闸门，规则一旦被回退
 * （如 min:8+regex → min:6），8 位弱口令会穿透校验直撞 AuthController.php:55 的
 * User::where：MySQL 可达且 probe 用户名已存在时同样返回 422（"Username already
 * exists"），只断言 code === 422 是静默无效的。故必须比对 message。
 */
class AuthControllerRegisterTest extends TestCase
{
    /** 用户名固定且合法，保证 password 是唯一会校验失败的字段 */
    private const PROBE_USERNAME = 'policy_probe';

    /** @return array<string, array{string}> */
    public static function weakPasswords(): array
    {
        return [
            '过短'   => ['Ab1'],
            '纯小写' => ['abcdefgh'],
            '纯大写' => ['ABCDEFGH'],
            '纯数字' => ['12345678'],
            '缺大写' => ['abcdefg1'],
            '缺小写' => ['ABCDEFG1'],
            '缺数字' => ['Abcdefgh'],
        ];
    }

    #[Test]
    #[DataProvider('weakPasswords')]
    public function registerRejectsWeakPassword(string $password): void
    {
        $body = json_decode($this->register($password)->rawBody(), true);

        $this->assertSame(422, $body['code'], "弱口令 `{$password}` 应被拒绝");
        $this->assertMatchesRegularExpression(
            '/^validation\.(min\.string|regex)$/',
            (string) ($body['message'] ?? ''),
            "弱口令 `{$password}` 的 422 必须来自口令规则本身，而非用户名已存在等其它 422 来源"
        );
    }

    /**
     * 强口令走的是真实的 PASSWORD_RULE 常量（非测试内复制的正则），
     * 因此规则一旦回退到 min:6 无强度校验，本断言即失败。
     */
    #[Test]
    public function registerPasswordRuleAcceptsStrongPassword(): void
    {
        $rule = (new ReflectionClass(AuthController::class))
            ->getReflectionConstant('PASSWORD_RULE')
            ->getValue();

        $this->assertFalse(
            validator(['password' => 'Abcdef12'], ['password' => $rule])->fails(),
            '强口令 Abcdef12 应通过注册策略'
        );
    }

    /**
     * 与 registerPasswordRuleAcceptsStrongPassword 同源（同一反射取的 PASSWORD_RULE），
     * 但为负例：规则回退成 min:6 后 6 个 8 位弱口令会通过校验，断言直接 Failures。
     * 与 AuthController.php:55 的 QueryException 无关，检出不再看环境能否连库的脸色。
     */
    #[Test]
    #[DataProvider('weakPasswords')]
    public function registerPasswordRuleRejectsWeakPassword(string $password): void
    {
        $rule = (new ReflectionClass(AuthController::class))
            ->getReflectionConstant('PASSWORD_RULE')
            ->getValue();

        $this->assertTrue(
            validator(['password' => $password], ['password' => $rule])->fails(),
            "弱口令 `{$password}` 应被注册策略拒绝"
        );
    }

    private function register(string $password): Response
    {
        $request = new Request("POST /api/v1/auth/register HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost(['username' => self::PROBE_USERNAME, 'password' => $password]);

        return (new AuthController())->register($request);
    }
}
