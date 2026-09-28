<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTFactory;
use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageFactory;
use common\JwtRedisClient;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

/**
 * 创建验证器实例
 */
function validator(array $data, array $rules, array $messages = [], array $attributes = []): \Illuminate\Validation\Validator
{
    static $factory = null;
    if ($factory === null) {
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'));
    }
    return $factory->make($data, $rules, $messages, $attributes);
}

/**
 * JWT 便捷包装
 *
 * 注意：本函数在 admin 树里是**死代码** —— vendor 的 Laravel/helpers.php 已用同名
 * `jwt()`（返回 app('erik.jwt')）占据该函数名，function_exists 守卫恒为真，本函数永不注册。
 * admin 侧真正用的入口是 jwt_instance()。
 */
if (!function_exists('jwt')) {
    function jwt(): \Erikwang2013\Jwt\JwtWrapper
    {
        static $wrapper = null;
        if ($wrapper === null) {
            $jwt = jwt_instance();
            $wrapper = new \Erikwang2013\Jwt\JwtWrapper($jwt);
        }
        return $wrapper;
    }
}

/**
 * 裸 JWT 实例（AdminAuth / AuthController / ProfileController 的 getJWT() 用）。
 *
 * 第三个参数 $connections 只在 storage.type=redis 时被读取（file 模式下 resolver 永不被调用），
 * 故无条件传入不改变今日默认行为；不传则把 JWT_STORAGE_TYPE 设成 redis 会在构造期就抛
 * "Redis resolver callable required" ⇒ 管理端整条鉴权链 500。resolver 的形状见 common\JwtRedisClient。
 */
function jwt_instance(): JWT
{
    return JWTFactory::createFromConfig(
        config('plugin.erikwang2013.jwt.jwt', []),
        null,
        ['redis' => static fn () => new JwtRedisClient()]
    );
}

/**
 * 规范化验证码点击坐标为 [x, y] 元组格式（poster-php 包期望的格式）
 */
function captcha_clicks(mixed $clicks): array
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
 * 窗口限流退化成「全局桶」：任何匿名者每分钟刷满 30 次校验，所有管理员的
 * 登录/注册/验证码校验会一起被判失败（RateLimiter 刻意不区分「被限流」与「填错」）。
 *
 * vendor 的 helpers.php 由 composer autoload.files 在 require vendor/autoload.php 时载入，
 * 早于应用侧任何文件（本文件由 config/autoload.php 在 App::run 里后加载），
 * 同名函数无法从应用侧覆盖，故走包内文档化的注入点：显式给 CaptchaManager 传 identityResolver。
 *
 * @param string $ip 客户端 IP，传 $request->getRealIp()。
 *                   不要读 $_SERVER['REMOTE_ADDR']：CLI SAPI 下它不存在，正是本缺陷的成因；
 *                   也不要图省事传常量/固定值，那会原样退回全局桶。
 */
function captcha_verify_from_ip(string $ip, string $key, string $type, mixed $data): bool
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
