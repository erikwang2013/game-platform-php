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
            // Request 必须带原始报文：Workerman 的构造函数要求恰好 1 个参数，无参构造会抛
            // ArgumentCountError，被宽 catch 改写成「库未配置」而永久 skip（本用例此前从未执行过）。
            $response = (new PlatformStatsController())->stats(new Request("GET /api/v1/platform/stats HTTP/1.1\r\nHost: localhost\r\n\r\n"));
        } catch (\Illuminate\Database\QueryException | \PDOException $e) {
            // 只把「库不可用」降级为 skip：其它异常必须让用例失败，否则宽 catch 会把真缺陷伪装成环境问题
            $this->markTestSkipped('Database connection not configured in test environment: ' . $e->getMessage());
        }

        $body = json_decode($response->rawBody(), true);
        $this->assertSame(0, $body['code']);
        foreach (['total_games', 'total_users', 'today_game_plays', 'active_users_7d'] as $key) {
            $this->assertArrayHasKey($key, $body['data']);
            $this->assertIsInt($body['data'][$key]);
        }
    }
}
