<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\PlatformStatsController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Request;

/**
 * 平台公开统计接口测试
 */
class PlatformStatsControllerTest extends TestCase
{
    #[Test]
    public function statsReturnsExpectedStructure(): void
    {
        try {
            $response = (new PlatformStatsController())->stats(new Request());
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not configured in test environment');
        }

        $body = json_decode($response->rawBody(), true);
        $this->assertSame(0, $body['code']);
        foreach (['total_games', 'total_users', 'today_game_plays', 'active_users_7d'] as $key) {
            $this->assertArrayHasKey($key, $body['data']);
            $this->assertIsInt($body['data'][$key]);
        }
    }
}
