<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use app\model\Leaderboard;
use app\model\ExchangeRecord;
use app\service\LeaderboardService;
use support\Db;
use support\Redis;

/**
 * LeaderboardService 单元测试
 * 覆盖: Redis 缓存命中/清除、earned/play_count 排行榜计算（DB fixtures）
 */
class LeaderboardServiceTest extends TestCase
{
    private const TEST_LEADERBOARD_ID = 990000001;
    private const TEST_USER_A = 990000101;
    private const TEST_USER_B = 990000102;

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        try {
            Redis::del(LeaderboardService::CACHE_KEY_PREFIX . self::TEST_LEADERBOARD_ID);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    #[Test]
    public function computeRankingEarnedMetricGroupsByUser(): void
    {
        Db::connection()->transaction(function () {
            // 清空测试区数据（在事务内删除，保证确定性）
            ExchangeRecord::whereIn('user_id', [self::TEST_USER_A, self::TEST_USER_B])->delete();
            Leaderboard::where('id', self::TEST_LEADERBOARD_ID)->delete();

            $board = new Leaderboard();
            $board->id = self::TEST_LEADERBOARD_ID;
            $board->game_id = 0;
            $board->name = 'test-board';
            $board->type = 'all';
            $board->metric = 'earned';
            $board->status = 1;
            $board->save();

            // 用户 A 赚 2 笔共 30，用户 B 赚 1 笔 10
            foreach ([['u' => self::TEST_USER_A, 'amt' => 10], ['u' => self::TEST_USER_A, 'amt' => 20], ['u' => self::TEST_USER_B, 'amt' => 10]] as $i => $row) {
                $record = new ExchangeRecord();
                $record->id = self::TEST_LEADERBOARD_ID * 10 + $i;
                $record->user_id = $row['u'];
                $record->game_id = 0;
                $record->currency_id = 1;
                $record->direction = 'in';
                $record->platform_amount = $row['amt'];
                $record->game_amount = $row['amt'];
                $record->rate = '1.00000000';
                $record->save();
            }

            $rankings = LeaderboardService::computeRanking(self::TEST_LEADERBOARD_ID, 10);

            $this->assertCount(2, $rankings);
            $this->assertSame(self::TEST_USER_A, $rankings[0]['user_id']);
            $this->assertSame(1, $rankings[0]['rank']);
            $this->assertSame('30.0000', (string) $rankings[0]['score']);
            $this->assertSame(self::TEST_USER_B, $rankings[1]['user_id']);
            $this->assertSame(2, $rankings[1]['rank']);
        });
    }

    #[Test]
    public function computeRankingReturnsEmptyForDisabledBoard(): void
    {
        Db::connection()->transaction(function () {
            Leaderboard::where('id', self::TEST_LEADERBOARD_ID)->delete();
            $board = new Leaderboard();
            $board->id = self::TEST_LEADERBOARD_ID;
            $board->game_id = 0;
            $board->name = 'disabled-board';
            $board->type = 'all';
            $board->metric = 'earned';
            $board->status = 0;
            $board->save();

            $this->assertSame([], LeaderboardService::computeRanking(self::TEST_LEADERBOARD_ID, 10));
        });
    }

    #[Test]
    public function getRankingReturnsCachedDataWhenRedisAvailable(): void
    {
        try {
            Redis::ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }

        $cached = [['rank' => 1, 'user_id' => 42, 'score' => '99']];
        Redis::setex(
            LeaderboardService::CACHE_KEY_PREFIX . self::TEST_LEADERBOARD_ID,
            60,
            json_encode($cached, JSON_UNESCAPED_UNICODE)
        );

        $result = LeaderboardService::getRanking(self::TEST_LEADERBOARD_ID, 10);
        $this->assertSame($cached, $result);
    }

    #[Test]
    public function clearCacheRemovesKey(): void
    {
        try {
            Redis::ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }

        $key = LeaderboardService::CACHE_KEY_PREFIX . self::TEST_LEADERBOARD_ID;
        Redis::setex($key, 60, '[]');
        $this->assertNotFalse(Redis::get($key));

        LeaderboardService::clearCache(self::TEST_LEADERBOARD_ID);
        $value = Redis::get($key);
        $this->assertTrue($value === false || $value === null); // 兼容 predis(null)/phpredis(false)
    }
}
