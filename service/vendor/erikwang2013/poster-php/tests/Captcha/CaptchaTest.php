<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Captcha;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

class CaptchaTest extends TestCase
{
    private CaptchaManager $manager;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/poster-test-captcha-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $driver = new GdDriver();
        $storage = new FileStorage($this->tempDir);
        $this->manager = new CaptchaManager($driver, $storage);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*.json'));
        rmdir($this->tempDir);
    }

    public function testClickCaptchaGenerateReturnsValidStructure(): void
    {
        $result = $this->manager->create('click')->setDifficulty('easy')->generate();
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('image', $result);
        $this->assertArrayHasKey('extra', $result);
        $this->assertArrayHasKey('targets', $result['extra']);
        $this->assertStringStartsWith('data:image/', $result['image']);
        $this->assertNotEmpty($result['key']);
    }

    public function testClickCaptchaVerifyPassesWithCorrectData(): void
    {
        $result = $this->manager->create('click')->setDifficulty('easy')->generate();
        $targets = $result['extra']['targets'];
        $clickData = [];
        foreach ($targets as $t) {
            $clickData[] = [$t['x'], $t['y']];
        }
        $verified = $this->manager->verify($result['key'], [
            'type' => 'click',
            'data' => $clickData,
        ]);
        $this->assertTrue($verified);
    }

    public function testClickCaptchaIsOneTimeUse(): void
    {
        $result = $this->manager->create('click')->setDifficulty('easy')->generate();
        $targets = $result['extra']['targets'];
        $clickData = [];
        foreach ($targets as $t) {
            $clickData[] = [$t['x'], $t['y']];
        }

        $this->manager->verify($result['key'], ['type' => 'click', 'data' => $clickData]);
        $secondVerify = $this->manager->verify($result['key'], ['type' => 'click', 'data' => $clickData]);
        $this->assertFalse($secondVerify);
    }

    public function testClickCaptchaInvalidDataFails(): void
    {
        $result = $this->manager->create('click')->setDifficulty('easy')->generate();
        $verified = $this->manager->verify($result['key'], [
            'type' => 'click',
            'data' => [[0, 0], [0, 0]],
        ]);
        $this->assertFalse($verified);
    }

    public function testClickCaptchaAllowsRetryOnFailure(): void
    {
        $result = $this->manager->create('click')->setDifficulty('easy')->generate();
        $targets = $result['extra']['targets'];
        $clickData = array_map(fn($t) => [$t['x'], $t['y']], $targets);

        // First: wrong data, should fail but key persists for retry
        $firstTry = $this->manager->verify($result['key'], ['type' => 'click', 'data' => [[0, 0]]]);
        $this->assertFalse($firstTry);

        // Second: correct data, should pass
        $secondTry = $this->manager->verify($result['key'], ['type' => 'click', 'data' => $clickData]);
        $this->assertTrue($secondTry);
    }

    public function testRotateCaptchaGenerateReturnsValidStructure(): void
    {
        $result = $this->manager->create('rotate')->generate();
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('image', $result);
        $this->assertArrayHasKey('extra', $result);
        $this->assertStringStartsWith('data:image/', $result['image']);
    }

    public function testRandomCaptchaReturnsValidType(): void
    {
        $result = $this->manager->create('random')->generate();
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertContains($result['type'], ['click', 'rotate', 'slider']);
        $this->assertArrayHasKey('image', $result);
        $this->assertArrayHasKey('extra', $result);
        $this->assertStringStartsWith('data:image/', $result['image']);
    }

    public function testRandomCaptchaVerificationWorks(): void
    {
        $result = $this->manager->create('random')->generate();
        $type = $result['type'];

        if ($type === 'click') {
            $targets = $result['extra']['targets'];
            $data = array_map(fn($t) => [$t['x'], $t['y']], $targets);
        } elseif ($type === 'slider') {
            $data = $result['extra']['x'];
        } else {
            // rotate: hard to test since we don't know the stored angle
            $this->assertNotNull($result['key']);
            return;
        }

        $pass = $this->manager->verify($result['key'], ['type' => $type, 'data' => $data]);
        $this->assertTrue($pass);
    }

    public function testSliderCaptchaGenerateReturnsValidStructure(): void
    {
        $result = $this->manager->create('slider')->generate();
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('image', $result);
        $this->assertArrayHasKey('extra', $result);
        $this->assertArrayHasKey('puzzle', $result['extra']);
        $this->assertArrayHasKey('x', $result['extra']);
        $this->assertStringStartsWith('data:image/', $result['image']);
    }
}
