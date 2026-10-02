<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\PlatformStatsController;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Redis;
use support\Request;

/**
 * `GET /api/v1/platform/stats` 的整包缓存（2026-10-02 加，键 platform:stats:v1，TTL 60s）。
 *
 * 为什么加：本端点公开无鉴权，每次请求 4 条 COUNT，其中 Game::count()/User::count() 是**全表计数**
 * ⇒ 匿名可无限触发的放大杠杆。
 *
 * ⚠ 本用例钉的是「缓存真的被读了」，不是「值对不对」——两个方向都要：
 *   - 命中：往键里塞哨兵值，端点必须原样吐出来，且**一条 COUNT 都不许再跑**（这才是省下的开销）；
 *   - 未命中：键不存在时必须回落现算（哨兵值不得出现），否则就成了「只读缓存不看库」的假实现。
 *
 * 命中那条不连库（缓存命中恰好意味着不碰库），未命中那条需要 MySQL，不可用时跳过。
 */
final class PlatformStatsCacheTest extends TestCase
{
    /** 哨兵：任何真实统计都不该长这样 */
    private const SENTINEL = [
        'total_games'      => 424242,
        'total_users'      => 424243,
        'today_game_plays' => 424244,
        'active_users_7d'  => 424245,
    ];

    private static bool $booted = false;
    private static bool $redisOk = false;

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();

        try {
            Redis::setex('platform:stats:probe', 5, '1');
            Redis::del('platform:stats:probe');
            self::$redisOk = true;
        } catch (\Throwable $e) {
            self::$redisOk = false;
        }
    }

    protected function setUp(): void
    {
        if (!self::$redisOk) {
            $this->markTestSkipped('Redis 不可用（跳过缓存用例）');
        }
        Redis::del(PlatformStatsController::CACHE_KEY);
    }

    protected function tearDown(): void
    {
        if (self::$redisOk) {
            Redis::del(PlatformStatsController::CACHE_KEY);
        }
        parent::tearDown();
    }

    /** 命中：塞哨兵值 ⇒ 端点必须原样返回，且不再执行任何查询 */
    #[Test]
    public function cacheHitIsServedVerbatimAndRunsNoCounts(): void
    {
        Redis::setex(PlatformStatsController::CACHE_KEY, 60, (string) json_encode(self::SENTINEL));

        $response = null;
        $queries = $this->collect(function () use (&$response) {
            $response = $this->stats();
        });

        $this->assertSame(self::SENTINEL, $this->data($response),
            '缓存命中时端点必须直接回缓存值（没读 ⇒ 缓存形同虚设）');
        $this->assertSame([], $queries,
            '缓存命中时不得再跑任何 COUNT —— 4 条全表/区间计数正是这次加缓存要省掉的东西。实际：'
            . json_encode(array_column($queries, 'query'), JSON_UNESCAPED_UNICODE));
    }

    /** 未命中：键不存在必须回落现算，哨兵值不得出现（否则等于「缓存写入后再没人算过」） */
    #[Test]
    public function missingCacheFallsBackToFreshCounts(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过现算回落用例）：' . $e->getMessage());
        }

        $data = $this->data($this->stats());

        $this->assertNotSame(self::SENTINEL, $data, '键不存在时必须现算，不得回哨兵值');
        foreach (array_keys(self::SENTINEL) as $key) {
            $this->assertArrayHasKey($key, $data);
            $this->assertIsInt($data[$key], "{$key} 必须是整数（JSON 往返不得把类型改掉）");
        }
    }

    /** @return array<string,mixed> 响应信封的 data 段 */
    private function data(\support\Response $response): array
    {
        $body = json_decode((string) $response->rawBody(), true) ?? [];
        $this->assertSame(0, $body['code'] ?? -1, '端点未成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));

        return $body['data'];
    }

    private function stats(): \support\Response
    {
        return (new PlatformStatsController())->stats(
            new Request("GET /api/v1/platform/stats HTTP/1.1\r\nHost: localhost\r\n\r\n")
        );
    }

    /** @return array<int, array{query: string, bindings: array}> */
    private function collect(callable $run): array
    {
        $connection = Db::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $run();

            return $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
        }
    }

    /** 与 14 个真库用例同一口径：先烧掉开发库那次 init 的守卫，再由测试库 Capsule 最后 setAsGlobal() 落笔 */
    private static function bootTargetDatabase(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        class_exists(Db::class);

        $conf = config('database');
        $name = $conf['default'];
        $conn = $conf['connections'][$name];

        $conn['database'] = getenv('DB_DATABASE_TEST') ?: 'game-platform-test';
        $user = getenv('GP_DB_USER');
        $pass = getenv('GP_DB_PASS');
        $conn['username'] = $user !== false && $user !== '' ? $user : $conn['username'];
        $conn['password'] = $pass !== false && $pass !== '' ? $pass : (string) $conn['password'];

        $capsule = new Capsule();
        $capsule->addConnection($conn, $name);
        $capsule->getDatabaseManager()->setDefaultConnection($name);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
