<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\service\FeatureFlag;
use app\model\PlatformConfig;
use support\Db;

/**
 * FeatureFlag 单元测试
 * 覆盖: isEnabled 默认值、enable/disable、百分比灰度（crc32 分桶）、all()
 */
class FeatureFlagTest extends TestCase
{
    private const TEST_FEATURE = 'test_flag';
    private const TEST_GROUP = 'feature';

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    #[Test]
    public function isEnabledDefaultsToFalse(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            $this->assertFalse(FeatureFlag::isEnabled(self::TEST_FEATURE));
        });
    }

    #[Test]
    public function enableAndDisableToggleFlag(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            $this->seedRow(self::TEST_FEATURE, 'off'); // 预置行，绕过 insert 路径的 id 生成缺陷
            FeatureFlag::enable(self::TEST_FEATURE);
            $this->assertTrue(FeatureFlag::isEnabled(self::TEST_FEATURE));

            FeatureFlag::disable(self::TEST_FEATURE);
            $this->assertFalse(FeatureFlag::isEnabled(self::TEST_FEATURE));
        });
    }

    #[Test]
    public function inRolloutEnabledFlagIsAlwaysTrue(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            $this->seedRow(self::TEST_FEATURE, 'on');
            $this->assertTrue(FeatureFlag::inRollout(self::TEST_FEATURE, 12345));
        });
    }

    #[Test]
    public function enableOnMissingRowThrowsDueToMissingId(): void
    {
        // 已知缺陷：PlatformConfig::set()/FeatureFlag::enable() 在无现存行时
        // 走 insert 路径，因模型未生成 id 且表列无默认值而抛错（见测试报告）。
        Db::connection()->transaction(function () {
            $this->cleanup();
            $this->expectException(\Throwable::class);
            FeatureFlag::enable(self::TEST_FEATURE);
        });
    }

    #[Test]
    public function inRolloutZeroPercentNeverReleases(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            $this->insertPercent(0);
            $this->assertFalse(FeatureFlag::inRollout(self::TEST_FEATURE, 12345));
        });
    }

    #[Test]
    public function inRolloutHundredPercentAlwaysReleases(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            $this->insertPercent(100);
            $this->assertTrue(FeatureFlag::inRollout(self::TEST_FEATURE, 12345));
        });
    }

    #[Test]
    public function inRolloutIsDeterministicPerSubject(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            $this->insertPercent(50);
            // crc32 分桶稳定：同一 subject 两次结果一致
            $first = FeatureFlag::inRollout(self::TEST_FEATURE, 98765);
            $second = FeatureFlag::inRollout(self::TEST_FEATURE, 98765);
            $this->assertSame($first, $second);
        });
    }

    #[Test]
    public function allReturnsEnabledFlagMap(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            $this->seedRow(self::TEST_FEATURE, 'on');
            $this->seedRow('second_flag', 'on');

            $flags = FeatureFlag::all();
            $this->assertTrue($flags[self::TEST_FEATURE]);
            $this->assertTrue($flags['second_flag']);
            $this->assertArrayNotHasKey('third_flag', $flags);
        });
    }

    private function seedRow(string $key, string $value): void
    {
        $config = new PlatformConfig();
        $config->id = (int) (970000000 + crc32(self::TEST_FEATURE . ':' . $key) % 1000000);
        $config->group = self::TEST_GROUP;
        $config->key = $key;
        $config->value = $value;
        $config->type = 'string';
        $config->save();
    }

    private function insertPercent(int $percent): void
    {
        $config = new PlatformConfig();
        $config->id = (int) (960000000 + crc32(self::TEST_FEATURE . ':percent') % 1000000);
        $config->group = self::TEST_GROUP;
        $config->key = self::TEST_FEATURE . '_percent';
        $config->value = (string) $percent;
        $config->type = 'int';
        $config->save();
    }

    private function cleanup(): void
    {
        PlatformConfig::where('group', self::TEST_GROUP)
            ->whereIn('key', [self::TEST_FEATURE, self::TEST_FEATURE . '_percent', 'second_flag', 'third_flag'])
            ->delete();
    }
}
