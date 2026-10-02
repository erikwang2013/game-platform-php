<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use Erikwang2013\Poster\PosterConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Request;

/**
 * 钉子：套件里过验证码闸口的请求必须带客户端身份，不能落在「无连接请求」那个共享限流桶上。
 *
 * 缺陷形状：captcha.rate_limit 按身份分桶（窗口 60 秒 / 上限 30 次），而测试用
 * `new Request(...)` 造的请求没有连接 ⇒ webman 的 getRealIp() 恒返回常量 '0.0.0.0'
 * ⇒ 整套件（以及共享同一套 Redis 的并发套件）共用一个桶，第 30 次之后的校验一律
 * 422「验证码错误」，与用例本身答对答错无关。实测：先烧 31 次同身份校验，紧接着跑
 * UserAuthPending2faTest + ExchangeWalletIntegrationTest ⇒ 3 红，全是同一条 422。
 *
 * 判据分两段：
 *  ① 身份判据（精确，非代理）：限流桶键是身份的纯函数（RateLimiter::KEY_PREFIX . md5(identity)），
 *     所以「测试请求的身份 ≠ 无连接请求的身份」与「两者不同桶」等价。身份一旦相同就地 fail——
 *     先停住再断言，避免在缺陷态里去烧那个正被并发套件使用的共享桶。
 *  ② 行为判据：把本用例自己的桶烧到超限后，同套构造在另一个身份上照常通过
 *     （与 admin/tests/CaptchaTest.php 的 A 耗尽 / B 照常 同法：B 通过 ⇒ 点击构造没错、
 *      限流是活的 ⇒ A 被拒只可能是限流，而不是答错）。
 *
 * @see CaptchaTestHelper::captchaRequest() 修复本体
 */
final class CaptchaRateLimitBucketIsolationTest extends TestCase
{
    use CaptchaTestHelper;

    #[Test]
    public function captchaQuotaBucketIsPerTestCaseNotSharedWithConnectionlessRequests(): void
    {
        $max = (int) PosterConfig::get('captcha.rate_limit.max', 30);
        $this->assertGreaterThan(0, $max, '跨 key 限流被关闭：本用例随之失去意义，请连同本用例一起裁决');

        $raw = "POST /api/v1/auth/login HTTP/1.1\r\nHost: localhost\r\n\r\n";

        // 无连接请求的身份＝修复前全套房共用（且与并发套件互相污染）的那个桶
        $shared = (new Request($raw))->getRealIp();
        // 套件造请求的唯一入口，与四个用例文件用的是同一个方法
        $mine = $this->captchaRequest($raw)->getRealIp();

        if ($shared === $mine) {
            $this->fail(
                "测试请求的身份与无连接请求相同（{$shared}）：整套件共用一个限流桶，"
                . '第 30 次之后的校验会被判 422「验证码错误」（与答对答错无关）。'
                . '请求必须带客户端身份，见 CaptchaTestHelper::captchaRequest()'
            );
        }

        // 不同用例的地址不能被压成同一个常量（那＝退回「整套件共用一个桶」的老路）。
        // 注意这里只证「不是常量」，**不证「两两不撞」**——哈希分槽的碰撞是允许的（见 CaptchaTestHelper::captchaClientIp）
        $probeIps = [];
        foreach (['probeAlpha', 'probeBeta', 'probeGamma'] as $probeMethod) {
            $probeIps[] = (new self($probeMethod))->captchaClientIp();
        }
        $this->assertGreaterThan(
            1,
            count(array_unique($probeIps)),
            '不同用例拿到了同一个客户端地址（' . implode(', ', $probeIps) . '）：桶会重新合并成整套件共用一个'
        );

        $paramsA = $this->captchaParams();
        $paramsB = $this->captchaParams();
        // ⚠ 坐标形状分两条路：走控制器的请求体用 ['x'=>..,'y'=>..]（由 captcha_clicks() 归一成元组），
        //   直接调 captcha_verify_from_ip 则必须自己归一 —— CaptchaManager::checkClick 只认 $point[0]/$point[1]。
        $clicksA = captcha_clicks($paramsA['clicks']);
        $clicksB = captcha_clicks($paramsB['clicks']);

        // 烧本用例自己的桶。$shared 那条路径只用于身份比对、绝不烧：'0.0.0.0' 是跨进程共享的，
        // 烧它会给并发跑的同族套件造出真的 422（本用例不做这种污染）
        for ($i = 0; $i <= $max; $i++) {
            captcha_verify_from_ip($mine, 'no-such-key-' . $i, 'click', []);
        }

        $this->assertFalse(
            captcha_verify_from_ip($mine, $paramsA['captcha_key'], 'click', $clicksA),
            '本用例的桶已耗尽，正确作答也必须被限流（正控：没有它，下面的绿可能是「限流根本没生效」）'
        );
        $this->assertTrue(
            captcha_verify_from_ip('203.0.113.9', $paramsB['captcha_key'], 'click', $clicksB),
            '另一个客户端（TEST-NET-3，两个地址槽位方案都不用它）配额未动，同一套动作照常通过 ⇒ '
            . '上面那条是限流而非答错，且桶确实是按身份分的'
        );
    }
}
