<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\api\v1\controller\CaptchaController;
use Erikwang2013\Poster\Captcha\RateLimiter;
use Erikwang2013\Poster\PosterConfig;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use support\Request;

class CaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = \Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }
    }

    #[Test]
    public function captcha_generate_returns_valid_structure(): void
    {
        $result = captcha_create('click', ['difficulty' => 'medium']);

        $this->assertArrayHasKey('key', $result, '应包含 key');
        $this->assertArrayHasKey('image', $result, '应包含 image');
        $this->assertArrayHasKey('extra', $result, '应包含 extra');
        $this->assertArrayHasKey('texts', $result['extra'], 'extra 应包含 texts');

        $this->assertNotEmpty($result['key']);
        $this->assertNotEmpty($result['image']);
        $this->assertCount(3, $result['extra']['texts'], 'medium 难度应有 3 个目标');
    }

    #[Test]
    public function captcha_targets_have_required_fields(): void
    {
        $result = captcha_create('click', ['difficulty' => 'easy']);

        foreach ($result['extra']['texts'] as $target) {
            $this->assertArrayHasKey('text', $target);
            $this->assertArrayHasKey('order', $target);
            $this->assertIsString($target['text']);
            $this->assertIsInt($target['order']);
        }
    }

    #[Test]
    public function captcha_difficulty_controls_target_count(): void
    {
        $easy = captcha_create('click', ['difficulty' => 'easy']);
        $medium = captcha_create('click', ['difficulty' => 'medium']);
        $hard = captcha_create('click', ['difficulty' => 'hard']);

        $this->assertCount(2, $easy['extra']['texts'], 'easy 应为 2 个目标');
        $this->assertCount(3, $medium['extra']['texts'], 'medium 应为 3 个目标');
        $this->assertCount(4, $hard['extra']['texts'], 'hard 应为 4 个目标');
    }

    #[Test]
    public function captcha_verify_wrong_clicks_fails(): void
    {
        $result = captcha_create('click', ['difficulty' => 'easy']);

        // 使用完全错误的坐标
        $clicks = [['x' => 0, 'y' => 0], ['x' => 999, 'y' => 999]];
        $valid = captcha_verify($result['key'], 'click', $clicks);

        $this->assertFalse($valid, '错误坐标应验证失败');
    }

    #[Test]
    public function captcha_generates_unique_keys(): void
    {
        $r1 = captcha_create('click');
        $r2 = captcha_create('click');

        $this->assertNotEquals($r1['key'], $r2['key'], '每次生成的 key 应不同');
    }

    /**
     * 跨 key 限流必须按「客户端 IP」分桶，不能退化成全站共用的常量桶。
     *
     * vendor 默认身份解析是 session_id() → $_SERVER['REMOTE_ADDR'] → 'cli'，webman 跑在
     * CLI SAPI 下两个来源都不成立，身份恒为 'cli' ⇒ 键恒为 md5('cli')，整个 admin 共用一个
     * 60 秒窗口，任何匿名者刷满 captcha.rate_limit.max 次就让全体管理员的校验一起判失败。
     *
     * 判据是「A 的配额耗尽后只有 A 被拒、B 照常通过」：两端用同一套构造正确答案的写法，
     * 互相校验（B 通过 ⇒ 点击构造没错 ⇒ A 被拒只可能是限流）。两端 IP 取本次运行独占的值
     * （198.51.100.x，TEST-NET-2），避免与并发套件互相污染共享 Redis 上的计数。
     */
    #[Test]
    public function captcha_rate_limit_bucket_is_keyed_by_client_ip(): void
    {
        $max = (int) PosterConfig::get('captcha.rate_limit.max', 30);
        $this->assertGreaterThan(0, $max, '跨 key 限流被配置关闭：身份归属随之失去意义，请连同本用例一起裁决');

        $storage = StorageFactory::create(PosterConfig::get('captcha.storage'));
        $slot = random_int(1, 126);              // 一次取两个相邻且必定不同的地址
        $ipA = "198.51.100.{$slot}";
        $ipB = '198.51.100.' . ($slot + 1);

        [$keyA, $clicksA] = $this->answerFor($storage);
        [$keyB, $clicksB] = $this->answerFor($storage);

        // 先用不存在的 key 把 A 在本窗口的配额刷满（走的就是被测的那条限流路径）
        for ($i = 0; $i < $max; $i++) {
            captcha_verify_from_ip($ipA, 'no-such-key-' . $i, 'click', []);
        }

        $this->assertFalse(
            captcha_verify_from_ip($ipA, $keyA, 'click', $clicksA),
            '配额已耗尽的 IP 必须被限流（此处为「被限流」而非「答错」，由下面的 B 反证）'
        );
        $this->assertTrue(
            captcha_verify_from_ip($ipB, $keyB, 'click', $clicksB),
            '另一个 IP 不受影响：桶必须是按 IP 分的，不能是全局常量桶'
        );
        $this->assertNotNull(
            $storage->get(RateLimiter::KEY_PREFIX . md5($ipB)),
            '限流计数应按客户端 IP 归属（不是常量身份）'
        );
    }

    /** @return array{0: string, 1: array} 一次验证码的 key 与按存储答案构造的正确点击 */
    private function answerFor(StorageInterface $storage): array
    {
        $result  = captcha_create('click', ['difficulty' => 'easy']);
        $targets = $storage->get($result['key'])['targets'] ?? [];
        $this->assertNotEmpty($targets, '应能读到本次验证码的答案（生成与校验同一份存储）');

        return [
            $result['key'],
            array_map(static fn(array $t): array => [$t['x'], $t['y']], $targets),
        ];
    }

    /**
     * 正向回归：同一 IP 连续多次「答对」的校验必须全部通过。
     *
     * 限流是按身份计数的，身份一旦退化成全站共用的常量，正常用户在窗口内会被误拒；
     * 这条覆盖三个入口（login / register / captcha/verify）共用的那条校验路径。
     */
    #[Test]
    public function captcha_verify_from_client_ip_accepts_correct_answers(): void
    {
        $storage = StorageFactory::create(PosterConfig::get('captcha.storage'));
        $ip = '203.0.113.' . random_int(1, 126);   // 本次运行独占，避免并发套件共用桶

        for ($i = 1; $i <= 3; $i++) {
            $result  = captcha_create('click', ['difficulty' => 'easy']);
            $targets = $storage->get($result['key'])['targets'] ?? [];
            $this->assertNotEmpty($targets, '应能读到本次验证码的答案（生成与校验同一份存储）');

            $clicks = array_map(static fn(array $t): array => [$t['x'], $t['y']], $targets);

            $this->assertTrue(
                captcha_verify_from_ip($ip, $result['key'], 'click', $clicks),
                "同一 IP 第 {$i} 次答对却未通过：限流不得误伤正常校验"
            );
        }
    }

    /**
     * 回归护栏（纯文件判据）：三个验证码校验入口都必须走按 IP 归属的实现。
     * 改回 vendor 的 captcha_verify() 不会报错、单 key 校验照常工作，缺陷只会以
     * 「全站共用一个限流桶」的形式静默复发，故用调用点计数钉住。
     */
    #[Test]
    public function captcha_verify_call_sites_key_by_client_ip(): void
    {
        $expect = [
            '/app/api/v1/controller/AuthController.php'    => 2, // login + register
            '/app/api/v1/controller/CaptchaController.php' => 1,
        ];

        foreach ($expect as $rel => $count) {
            $src = (string) file_get_contents(dirname(__DIR__) . $rel);

            $this->assertSame(
                $count,
                substr_count($src, 'captcha_verify_from_ip('),
                "$rel 应恰有 {$count} 处按客户端 IP 归属的验证码校验"
            );
            $this->assertSame(
                0,
                preg_match_all('/\bcaptcha_verify\(/', $src),
                "$rel 不应再直接调用 vendor 的 captcha_verify()（身份恒为 'cli'）"
            );
        }
    }

    /**
     * 公开端点 `/api/v1/captcha/verify` 收到 `key[]=xxx`（数组）时必须回 422 信封，
     * 不能把 TypeError 漏给框架 —— 漏出去在 debug 形态下是 HTTP 500 + 完整堆栈（含绝对路径）。
     *
     * `empty()` 挡不住非空数组（`empty(['xxx']) === false`），而 `captcha_verify_from_ip`
     * 的 `string $key` 形参在 strict_types 下会抛 TypeError；verify() 又没有 try/catch。
     */
    #[Test]
    public function captcha_verify_rejects_array_key_without_leaking_type_error(): void
    {
        $body = 'key[]=xxx&clicks[]=1';
        $raw  = "POST /api/v1/captcha/verify HTTP/1.1\r\nHost: localhost\r\n"
              . "Content-Type: application/x-www-form-urlencoded\r\n"
              . 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body;
        $request = new Request($raw);

        // 前提探针：若 `key[]=xxx` 没被解析成数组，本用例就压根没走到目标分支 —— 恒真式假绿
        $this->assertIsArray($request->input('key'), 'key[]=xxx 应被解析成数组，否则本用例没在测目标分支');

        $payload = json_decode((new CaptchaController())->verify($request)->rawBody(), true);

        $this->assertSame(422, $payload['code'] ?? null,
            '数组 key 应回 422 信封；这里拿到非 422（或直接抛 TypeError）说明形状校验缺了');
    }
}
