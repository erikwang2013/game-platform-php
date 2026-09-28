<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use Erikwang2013\Jwt\JWTException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * JWT_STORAGE_TYPE 旋钮（admin 树）：默认 file 行为不变，翻成 redis 时不得在构造期抛。
 *
 * admin 侧的构造点是 jwt_instance()，AdminAuth / AuthController / ProfileController 三个
 * getJWT() 都走它；不传 JWTFactory::createFromConfig() 的第三个参数（$connections）时，
 * JWT_STORAGE_TYPE=redis 会让 AdminAuth 构造即抛 ⇒ 管理端整条鉴权链 500。
 *
 * 注意 admin 的 jwt() 是死代码：vendor 的 Laravel/helpers.php 先声明了同名函数，
 * 所以这里测 jwt_instance() 而不是 jwt()。
 *
 * 独立进程跑：JWT 实例与 AdminAuth::$jwt 都是进程内 static 缓存，同进程内先跑过的用例
 * 会让本次拿到旧 storage，用例恒绿（假绿）。
 */
class JwtRedisStorageKnobTest extends TestCase
{
    private const CFG = 'plugin.erikwang2013.jwt.jwt';

    /**
     * 按运维的真实动作翻旋钮：改环境变量，再让 jwt 配置目录重新执行一次
     * （jwt.php 在加载时读 getenv('JWT_STORAGE_TYPE')）。只重载这一棵子树——
     * 全量 Config::reload(config_path()) 会连带重跑 apidoc 的 route.php，在测试进程里炸路由。
     */
    private function forceStorageType(string $type): string
    {
        putenv('JWT_STORAGE_TYPE=' . $type);
        $_ENV['JWT_STORAGE_TYPE'] = $_SERVER['JWT_STORAGE_TYPE'] = $type;
        \Webman\Config::load(config_path() . '/plugin/erikwang2013/jwt', [], 'plugin.erikwang2013.jwt');

        $effective = (string) config(self::CFG . '.storage.type');
        $this->assertSame($type, $effective, '探针没把 storage.type 翻过来，用例无意义');

        return (string) config(self::CFG . '.storage.prefix');
    }

    /** AdminAuth::getJWT() 是管理端 500 的那条路径，必须能构造出来。 */
    private function adminAuthJwt(): \Erikwang2013\Jwt\JWT
    {
        try {
            $m = new \ReflectionMethod(\app\middleware\AdminAuth::class, 'getJWT');
            $m->setAccessible(true);
            return $m->invoke(null);
        } catch (JWTException $e) {
            $this->fail('JWT_STORAGE_TYPE=redis 下 AdminAuth::getJWT() 构造失败（就是本缺陷）：' . $e->getMessage());
        }
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function redis_mode_constructs_and_blacklists_into_redis(): void
    {
        $prefix = $this->forceStorageType('redis');
        $jwt    = $this->adminAuthJwt();

        $token   = $jwt->encode(['sub' => 1, 'username' => 'knob-test'], 60);
        $payload = $jwt->decode($token);
        $jti     = (string) $payload['jti'];
        $key     = $prefix . $jti;

        try {
            $this->assertTrue($jwt->blacklist($token), 'blacklist() 应返回 true');
            $this->assertGreaterThan(
                0,
                (int) \support\Redis::exists($key),
                "黑名单必须真的落在 Redis（键 {$key}）"
            );

            try {
                $jwt->decode($token);
                $this->fail('拉黑后 decode() 必须抛 TOKEN_BLACKLISTED');
            } catch (JWTException $e) {
                $this->assertSame(JWTException::TOKEN_BLACKLISTED, $e->getCode());
            }
        } finally {
            \support\Redis::del($key);
        }
    }

    /**
     * 默认（file）不得被这次改动带偏：黑名单仍走本地文件，一个 Redis 键都不该出现。
     * 若把 resolver 写成"无条件走 Redis"，本用例转红。
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function file_mode_does_not_touch_redis(): void
    {
        $prefix = $this->forceStorageType('file');
        $jwt    = $this->adminAuthJwt();

        $token = $jwt->encode(['sub' => 1, 'username' => 'knob-test'], 60);
        $jti   = (string) $jwt->decode($token)['jti'];

        $this->assertTrue($jwt->blacklist($token));
        $this->assertSame(
            0,
            (int) \support\Redis::exists($prefix . $jti),
            'storage.type=file 时黑名单不得写 Redis'
        );

        try {
            $jwt->decode($token);
            $this->fail('file 模式下拉黑后 decode() 同样必须抛 TOKEN_BLACKLISTED');
        } catch (JWTException $e) {
            $this->assertSame(JWTException::TOKEN_BLACKLISTED, $e->getCode());
        }
    }
}
