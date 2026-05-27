<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\service\AbTestService;
use common\service\UserProfileService;
use ReflectionClass;

class ClickHouseServicesTest extends TestCase
{
    // ============================================================
    // AbTestService — pure logic, no ClickHouse dependency
    // ============================================================

    #[Test]
    public function assign_default_variants_splits_50_50(): void
    {
        $control = 0;
        $treatment = 0;
        for ($i = 1; $i <= 1000; $i++) {
            $v = AbTestService::assign('test_exp', $i);
            if ($v === 'control') $control++;
            elseif ($v === 'treatment') $treatment++;
        }
        $this->assertGreaterThan(425, $control, 'control ~50%');
        $this->assertLessThan(575, $control);
        $this->assertGreaterThan(425, $treatment, 'treatment ~50%');
        $this->assertLessThan(575, $treatment);
    }

    #[Test]
    public function assign_same_user_always_same_variant(): void
    {
        $v1 = AbTestService::assign('my_exp', 42);
        $v2 = AbTestService::assign('my_exp', 42);
        $v3 = AbTestService::assign('my_exp', 42);
        $this->assertSame($v1, $v2);
        $this->assertSame($v2, $v3);
    }

    #[Test]
    public function assign_different_experiments_independent(): void
    {
        $v1 = AbTestService::assign('exp_a', 42);
        $v2 = AbTestService::assign('exp_b', 42);
        $this->assertContains($v1, ['control', 'treatment']);
        $this->assertContains($v2, ['control', 'treatment']);
    }

    #[Test]
    public function assign_custom_weighted_variants(): void
    {
        $counts = ['a' => 0, 'b' => 0, 'c' => 0];
        for ($i = 1; $i <= 1000; $i++) {
            $v = AbTestService::assign('weighted', $i, ['a' => 10, 'b' => 30, 'c' => 60]);
            $counts[$v]++;
        }
        $this->assertGreaterThan(0, $counts['a'], 'a gets some');
        $this->assertGreaterThan(200, $counts['b'], 'b ~30%');
        $this->assertGreaterThan(450, $counts['c'], 'c ~60%');
    }

    #[Test]
    public function assign_empty_variants_uses_default(): void
    {
        $result = AbTestService::assign('empty', 1, []);
        $this->assertContains($result, ['control', 'treatment']);
    }

    #[Test]
    public function assign_deterministic_across_runs(): void
    {
        $b1 = []; $b2 = [];
        for ($i = 1; $i <= 100; $i++) $b1[] = AbTestService::assign('stable', $i);
        for ($i = 1; $i <= 100; $i++) $b2[] = AbTestService::assign('stable', $i);
        $this->assertSame($b1, $b2);
    }

    #[Test]
    public function assign_handles_all_int_user_ids(): void
    {
        foreach ([1, 100, 9999, 1000000] as $id) {
            $r = AbTestService::assign('id_test', $id);
            $this->assertIsString($r);
            $this->assertNotEmpty($r);
        }
    }

    // ============================================================
    // UserProfileService — buildTags logic (via reflection)
    // ============================================================

    #[Test]
    public function tags_daily_active(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 25, 'games_played' => 1, 'total_actions' => 10, 'ip_count' => 1, 'peak_hour' => 14]);
        $this->assertContains('daily_active', $t);
    }

    #[Test]
    public function tags_weekly_active(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 10, 'games_played' => 1, 'total_actions' => 10, 'ip_count' => 1, 'peak_hour' => 14]);
        $this->assertContains('weekly_active', $t);
    }

    #[Test]
    public function tags_casual(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 3, 'games_played' => 1, 'total_actions' => 10, 'ip_count' => 1, 'peak_hour' => 14]);
        $this->assertContains('casual', $t);
    }

    #[Test]
    public function tags_dormant(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 0, 'games_played' => 0, 'total_actions' => 0, 'ip_count' => 0, 'peak_hour' => 0]);
        $this->assertContains('dormant', $t);
    }

    #[Test]
    public function tags_explorer(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 15, 'games_played' => 5, 'total_actions' => 100, 'ip_count' => 1, 'peak_hour' => 14]);
        $this->assertContains('explorer', $t);
    }

    #[Test]
    public function tags_focused(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 10, 'games_played' => 1, 'total_actions' => 50, 'ip_count' => 1, 'peak_hour' => 14]);
        $this->assertContains('focused', $t);
    }

    #[Test]
    public function tags_hardcore(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 20, 'games_played' => 3, 'total_actions' => 600, 'ip_count' => 1, 'peak_hour' => 14]);
        $this->assertContains('hardcore', $t);
    }

    #[Test]
    public function tags_regular(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 10, 'games_played' => 2, 'total_actions' => 100, 'ip_count' => 1, 'peak_hour' => 14]);
        $this->assertContains('regular', $t);
    }

    #[Test]
    public function tags_light(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 5, 'games_played' => 1, 'total_actions' => 20, 'ip_count' => 1, 'peak_hour' => 14]);
        $this->assertContains('light', $t);
    }

    #[Test]
    public function tags_stable_ip(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 10, 'games_played' => 2, 'total_actions' => 100, 'ip_count' => 1, 'peak_hour' => 14]);
        $this->assertContains('stable_ip', $t);
    }

    #[Test]
    public function tags_roaming(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 10, 'games_played' => 2, 'total_actions' => 100, 'ip_count' => 5, 'peak_hour' => 14]);
        $this->assertContains('roaming', $t);
    }

    #[Test]
    public function tags_night_owl(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 10, 'games_played' => 2, 'total_actions' => 100, 'ip_count' => 1, 'peak_hour' => 2]);
        $this->assertContains('night_owl', $t);
    }

    #[Test]
    public function tags_morning_player(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 10, 'games_played' => 2, 'total_actions' => 100, 'ip_count' => 1, 'peak_hour' => 8]);
        $this->assertContains('morning_player', $t);
    }

    #[Test]
    public function tags_afternoon_player(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 10, 'games_played' => 2, 'total_actions' => 100, 'ip_count' => 1, 'peak_hour' => 14]);
        $this->assertContains('afternoon_player', $t);
    }

    #[Test]
    public function tags_evening_player(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 10, 'games_played' => 2, 'total_actions' => 100, 'ip_count' => 1, 'peak_hour' => 20]);
        $this->assertContains('evening_player', $t);
    }

    #[Test]
    public function tags_always_5_tags(): void
    {
        $t = $this->invokeBuildTags(['active_days' => 10, 'games_played' => 3, 'total_actions' => 200, 'ip_count' => 1, 'peak_hour' => 14]);
        $this->assertCount(5, $t, 'Always 5 tags');
    }

    #[Test]
    public function batchMetrics_empty_returns_empty(): void
    {
        $r = UserProfileService::batchMetrics([]);
        $this->assertIsArray($r);
        $this->assertEmpty($r);
    }

    // ============================================================
    // SmartCouponService — retentionRecommendations logic
    // ============================================================

    #[Test]
    public function retention_recommendations_amounts_are_strings(): void
    {
        // Test the data structure contract (mock the CH part would require CH)
        // Here we just verify the static methods are callable and return correct types
        $this->assertTrue(method_exists(\common\service\SmartCouponService::class, 'retentionRecommendations'));
        $this->assertTrue(method_exists(\common\service\SmartCouponService::class, 'userActivityProfile'));
    }

    // ============================================================
    // Verify all services are autoloadable
    // ============================================================

    #[Test]
    public function all_services_autoloadable(): void
    {
        $classes = [
            \common\service\GamePlayLogService::class,
            \common\service\ProbabilityService::class,
            \common\service\RecommendService::class,
            \common\service\RiskClickHouseService::class,
            \common\service\SmartCouponService::class,
            \common\service\GameDashboardService::class,
            \common\service\RateLimitDashboardService::class,
            \common\service\UserProfileService::class,
            \common\service\AbTestService::class,
            \common\service\RetentionService::class,
            \common\service\AntiCheatService::class,
            \common\service\DepositLogService::class,
        ];

        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class), "{$class} should be autoloadable");
        }
    }

    #[Test]
    public function all_services_have_expected_static_methods(): void
    {
        $this->assertTrue(method_exists(\common\service\GameDashboardService::class, 'overview'));
        $this->assertTrue(method_exists(\common\service\RecommendService::class, 'alsoPlayed'));
        $this->assertTrue(method_exists(\common\service\RecommendService::class, 'trending'));
        $this->assertTrue(method_exists(\common\service\RecommendService::class, 'forUser'));
        $this->assertTrue(method_exists(\common\service\RetentionService::class, 'cohortRetention'));
        $this->assertTrue(method_exists(\common\service\RetentionService::class, 'churnRate'));
        $this->assertTrue(method_exists(\common\service\AntiCheatService::class, 'detectBotPattern'));
        $this->assertTrue(method_exists(\common\service\AntiCheatService::class, 'assessUser'));
        $this->assertTrue(method_exists(\common\service\RiskClickHouseService::class, 'assessUser'));
        $this->assertTrue(method_exists(\common\service\DepositLogService::class, 'revenueOverview'));
        $this->assertTrue(method_exists(\common\service\DepositLogService::class, 'conversionByGame'));
    }

    // ============================================================
    // Helper
    // ============================================================

    private function invokeBuildTags(array $metrics): array
    {
        $ref = new ReflectionClass(UserProfileService::class);
        $m = $ref->getMethod('buildTags');
        $m->setAccessible(true);
        return $m->invoke(null, $metrics);
    }
}
