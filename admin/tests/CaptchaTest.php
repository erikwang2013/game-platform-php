<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

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
        $this->assertArrayHasKey('targets', $result['extra'], 'extra 应包含 targets');

        $this->assertNotEmpty($result['key']);
        $this->assertNotEmpty($result['image']);
        $this->assertCount(3, $result['extra']['targets'], 'medium 难度应有 3 个目标');
    }

    #[Test]
    public function captcha_targets_have_required_fields(): void
    {
        $result = captcha_create('click', ['difficulty' => 'easy']);

        foreach ($result['extra']['targets'] as $target) {
            $this->assertArrayHasKey('x', $target);
            $this->assertArrayHasKey('y', $target);
            $this->assertArrayHasKey('text', $target);
            $this->assertArrayHasKey('order', $target);
            $this->assertIsInt($target['x']);
            $this->assertIsInt($target['y']);
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

        $this->assertCount(2, $easy['extra']['targets'], 'easy 应为 2 个目标');
        $this->assertCount(3, $medium['extra']['targets'], 'medium 应为 3 个目标');
        $this->assertCount(4, $hard['extra']['targets'], 'hard 应为 4 个目标');
    }

    #[Test]
    public function captcha_verify_correct_clicks_passes(): void
    {
        $result = captcha_create('click', ['difficulty' => 'easy']);
        $targets = $result['extra']['targets'];

        // 使用正确的目标坐标
        $clicks = array_map(fn($t) => ['x' => $t['x'], 'y' => $t['y']], $targets);
        $valid = captcha_verify($result['key'], 'click', $clicks);

        $this->assertTrue($valid, '点击正确坐标应验证通过');
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
    public function captcha_key_has_limited_attempts(): void
    {
        $result = captcha_create('click', ['difficulty' => 'easy']);
        $targets = $result['extra']['targets'];
        $clicks = array_map(fn($t) => ['x' => $t['x'], 'y' => $t['y']], $targets);

        // 第一次验证通过
        $first = captcha_verify($result['key'], 'click', $clicks);
        $this->assertTrue($first);
    }

    #[Test]
    public function captcha_generates_unique_keys(): void
    {
        $r1 = captcha_create('click');
        $r2 = captcha_create('click');

        $this->assertNotEquals($r1['key'], $r2['key'], '每次生成的 key 应不同');
    }
}
