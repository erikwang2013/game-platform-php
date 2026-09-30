<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageFactory;

/**
 * 点击验证码的坐标规范化与校验 —— admin / service 两棵树共用同一份实现。
 *
 * 两棵树各自的 support/helpers.php 只保留同名全局函数做 delegate：
 * 这段逻辑带一个反直觉的坑（见 verifyFromIp 注释），复制成两份必然漂移，
 * 而它是登录/注册/敏感操作的唯一防线。
 */
class Captcha
{
    /**
     * 规范化验证码点击坐标为 [x, y] 元组格式（poster-php 包期望的格式）
     */
    public static function clicks(mixed $clicks): array
    {
        if (!is_array($clicks)) {
            return [];
        }
        return array_map(
            fn($c) => [$c['x'] ?? $c[0] ?? 0, $c['y'] ?? $c[1] ?? 0],
            $clicks
        );
    }

    /**
     * 按「真实客户端 IP」归属的验证码校验（vendor 的 captcha_verify() 在本项目下不可用）。
     *
     * poster-php 的 CaptchaManager::resolveIdentity() 依次取
     * session_id() → $_SERVER['REMOTE_ADDR'] → 'cli'，而 webman 跑在 CLI SAPI 下：
     * session_id() 恒为 ''（webman 用自家的 Workerman\Protocols\Http\Session，全 vendor 无 session_start）、
     * $_SERVER['REMOTE_ADDR'] 不存在 —— 身份因此恒为常量 'cli'，captcha.rate_limit 的跨 key
     * 窗口限流退化成「全局桶」：任何匿名者每分钟刷满 30 次校验，所有用户的
     * 登录/注册/验证码校验会一起被判失败（RateLimiter 刻意不区分「被限流」与「填错」）。
     *
     * vendor 的 helpers.php 由 composer autoload.files 在 require vendor/autoload.php 时载入，
     * 早于应用侧任何文件（support/helpers.php 由 config/autoload.php 在 App::run 里后加载），
     * 同名函数无法从应用侧覆盖，故走包内文档化的注入点：显式给 CaptchaManager 传 identityResolver。
     *
     * @param string $ip 客户端 IP，传 $request->getRealIp()。
     *                   不要读 $_SERVER['REMOTE_ADDR']：CLI SAPI 下它不存在，正是本缺陷的成因；
     *                   也不要图省事传常量/固定值，那会原样退回全局桶。
     */
    public static function verifyFromIp(string $ip, string $key, string $type, mixed $data): bool
    {
        // 取不到 IP 时（CLI 直调、测试）给一次性身份：跨 key 限流对该次调用不生效。
        // 宁可少一层限流，也不能退回共享常量——同一 key 的 max_attempts 仍在兜着。
        $identity = $ip !== '' ? $ip : 'anon:' . uniqid('', true);

        $manager = new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            StorageFactory::create(PosterConfig::get('captcha.storage')),
            static fn(): string => $identity
        );

        return $manager->verify($key, ['type' => $type, 'data' => $data]);
    }
}
