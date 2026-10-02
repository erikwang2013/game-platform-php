<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageFactory;
use support\Request;
use Workerman\Connection\TcpConnection;

/**
 * 测试里造一张「已知答案」的点击验证码。
 *
 * 登录/注册/提现申请/兑换卖出/领券这五个端点已加服务端强制的点击验证码
 * （见 common\Captcha::verifyFromIp），缺 captcha_key/clicks 一律 422，
 * 所以这些端点的用例必须在请求体里带上本 trait 给出的两个键。
 *
 * 生成与校验共用同一份存储，故生成后能读回目标坐标——这不是绕过，
 * 与 admin 侧 AdminSessionRevocationTest::solveCaptcha() 同法。
 *
 * 光带 captcha_key/clicks 还不够：**请求本身必须带客户端身份**，
 * 否则整套件共用一个限流桶（见 captchaRequest 的注释）。
 */
trait CaptchaTestHelper
{
    /** 本用例独占的客户端地址，惰性取一次，同一用例内所有请求同源 */
    private ?string $captchaClientIp = null;

    /**
     * 造一个带客户端身份的请求 —— 套件里凡是要过验证码闸口的请求都从这里造。
     *
     * 无连接的 Request 其 getRealIp() 恒为常量 '0.0.0.0'
     * （webman: `$this->connection ? $this->connection->getRemoteIp() : '0.0.0.0'`），
     * 而 captcha.rate_limit 是按这个身份分桶的 —— 整套件于是共用一个 30 次/60 秒的桶，
     * 第 30 次之后的校验一律 422「验证码错误」，与用例本身答对答错无关。
     * 共享同一套 Redis 的**并发**套件也落进同一个桶（实测：无人本地跑套件时该桶仍在涨），
     * 所以这不是「跑两次套件才复现」的时序运气，而是同一进程内就必然发生的耦合
     * （实测：先烧 31 次同身份校验，紧接着跑 UserAuthPending2faTest +
     * ExchangeWalletIntegrationTest ⇒ 3 红，全是同一条 422）。
     *
     * 给 Request 装一个携带客户端地址的连接即可让身份回到按用例分桶。
     * 手法与 admin/tests/ExportTempFileCleanupTest.php 同（匿名 TcpConnection 子类 + 空构造）；
     * 地址取 TEST-NET-2（198.51.100.0/24）内按用例名分槽的固定值（见 captchaClientIp），
     * 与 admin/tests/CaptchaTest.php 同一约定：**按用例名哈希分槽（有碰撞，见 captchaClientIp）**，
     * 且不与并发跑的同族套件互相污染。
     *
     * @param string $raw 原始请求报文，与 `new Request(...)` 的入参同形
     */
    protected function captchaRequest(string $raw): Request
    {
        $request = new Request($raw);
        $request->connection = new class($this->captchaClientIp() . ':12345') extends TcpConnection {
            public function __construct(string $remoteAddress)
            {
                $this->remoteAddress = $remoteAddress;
            }
        };

        return $request;
    }

    /**
     * 本用例的客户端地址：用例名 → TEST-NET-2 内的固定槽位。
     *
     * 固定（不是随机）是为了同一用例每次运行都落进同一个桶 ⇒ 读数是可复现的；
     * 用例之间按名字分槽 ⇒ 只关自己的次数，基本吃不到别人的 30 次配额。
     *
     * ⚠ 别把这里读成「每用例一桶」：分槽是哈希（`crc32 % 128`），**碰撞已实测存在**——
     *   16 个消耗桶的用例方法里有一组三撞（198.51.100.167）。当前无害，因为那三个各自
     *   只消耗个位数校验，离 30 次/60 秒配额很远；真实声称是「哈希分槽 + 碰撞存在 + 余量够」。
     *   升级路径（本批未做）：给必须独占的用例钉一个不参与哈希的保留地址
     *   （例如给 CaptchaRateLimitBucketIsolationTest 留 198.51.100.254）——
     *   **它治不了已知的三撞组**，那三个要么改名要么改用显式地址表；要把「不撞」变成保证，
     *   得换成显式地址表/注册排重，那就不是一行的事了。
     *
     * 取 127-254 段是为了与 admin/tests/CaptchaTest.php 那侧的 1-126 段不重叠，
     * 两棵树并发跑也不会撞进同一个桶。
     */
    private function captchaClientIp(): string
    {
        return $this->captchaClientIp ??= '198.51.100.' . (127 + crc32(static::class . '::' . $this->name()) % 128);
    }

    /**
     * @return array{captcha_key: string, clicks: list<array{x: int, y: int}>}
     */
    protected function captchaParams(): array
    {
        $storage = StorageFactory::create(PosterConfig::get('captcha.storage'));
        $result  = captcha_create('click', ['difficulty' => 'easy']);
        $targets = $storage->get($result['key'])['targets'] ?? [];

        if ($targets === []) {
            $this->fail('读不到本次验证码答案：生成与校验必须共用同一份存储');
        }

        return [
            'captcha_key' => (string) $result['key'],
            'clicks'      => array_map(
                static fn(array $t): array => ['x' => (int) $t['x'], 'y' => (int) $t['y']],
                $targets
            ),
        ];
    }
}
