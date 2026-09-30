<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageFactory;

/**
 * 测试里造一张「已知答案」的点击验证码。
 *
 * 登录/注册/提现申请/兑换卖出/领券这五个端点已加服务端强制的点击验证码
 * （见 common\Captcha::verifyFromIp），缺 captcha_key/clicks 一律 422，
 * 所以这些端点的用例必须在请求体里带上本 trait 给出的两个键。
 *
 * 生成与校验共用同一份存储，故生成后能读回目标坐标——这不是绕过，
 * 与 admin 侧 AdminSessionRevocationTest::solveCaptcha() 同法。
 */
trait CaptchaTestHelper
{
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
