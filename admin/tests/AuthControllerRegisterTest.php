<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\api\v1\controller\AuthController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use support\Request;
use support\Response;

/**
 * 注册口令强度策略守卫。
 *
 * 策略常量被回退（如 min:8+regex → min:6）时本测试必须失败，否则形同虚设。
 * 三条独立防线：
 *   1. 控制器级：弱口令必须被 422 拒绝，且拒绝理由必须来自口令规则本身；
 *   2. 规则级正例：直接对 PASSWORD_RULE 常量求值，强口令必须通过（防规则整体失效）；
 *   3. 规则级负例：同一常量对 7 个弱口令必须全部失败（防规则被回退成 min:6）。
 *
 * 弱点说明：admin register() 在校验与首次落库之间还夹着一道 captcha 闸门
 * （AuthController.php:170，captcha_verify 只返回 bool、不抛异常），
 * 规则被回退后弱口令会穿透校验撞上该闸门并同样得到 422 + "验证码错误"。
 * 因此断言必须比对 message —— 只断言 code === 422 的负向对照是静默无效的。
 */
class AuthControllerRegisterTest extends TestCase
{
    private const PROBE_USERNAME = 'policy_probe';

    /** captcha 闸门自身的 422 文案：命中它说明拒绝不是口令规则给的 */
    private const CAPTCHA_MESSAGE = '验证码错误，请重试';

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
        $this->assertNotSame(
            self::CAPTCHA_MESSAGE,
            $body['message'] ?? '',
            "弱口令 `{$password}` 的 422 必须来自口令规则，而非兜底的验证码闸门"
        );
    }

    #[Test]
    public function registerPasswordRuleAcceptsStrongPassword(): void
    {
        $rule = (new ReflectionClass(AuthController::class))
            ->getReflectionConstant('PASSWORD_RULE')->getValue();

        $this->assertFalse(
            validator(['password' => 'Abcdef12'], ['password' => $rule])->fails(),
            '强口令 Abcdef12 应通过注册策略'
        );
    }

    /**
     * 与 registerPasswordRuleAcceptsStrongPassword 同源（同一反射取的 PASSWORD_RULE），
     * 但为负例：规则回退成 min:6 后 7 个弱口令会通过校验，断言直接 Failures。
     * 不经控制器，故不看 captcha 闸门与 MySQL 的脸色。
     */
    #[Test]
    #[DataProvider('weakPasswords')]
    public function registerPasswordRuleRejectsWeakPassword(string $password): void
    {
        $rule = (new ReflectionClass(AuthController::class))
            ->getReflectionConstant('PASSWORD_RULE')->getValue();

        $this->assertTrue(
            validator(['password' => $password], ['password' => $rule])->fails(),
            "弱口令 `{$password}` 应被注册策略拒绝"
        );
    }

    /** 口令校验失败必须先于任何入库/验证码副作用，因此本用例不依赖 MySQL */
    private function register(string $password): Response
    {
        $request = new Request("POST /api/v1/auth/register HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost([
            'username'    => self::PROBE_USERNAME,
            'password'    => $password,
            'real_name'   => 'policy_probe',
            'captcha_key' => 'bogus-captcha-key',
            'clicks'      => [['x' => 1, 'y' => 1], ['x' => 2, 'y' => 2]],
        ]);

        return (new AuthController())->register($request);
    }
}
