<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\MetricsController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Redis;
use support\Request;

/**
 * `/metrics` 里那两条**曾经裸跑 safeCount** 的指标必须真的走 `cachedGauge`。
 *
 * 判据取**查询日志的条数**，不取 Redis 键是否存在 —— 键存在只证明"写了缓存"，
 * 证明不了"下一次请求读它"。冷/热两次请求各跑一遍，比对同一形状的 SQL 出现次数：
 *   冷：恰好 1 次（前提探针：证明这条指标确实要查库，否则本用例无对象）
 *   热：0 次（这才是修复的行为面）
 *
 * 调用方不止 Prometheus —— `admin/apps/angular` 的 dashboard.ts:100 与 settings.ts:432
 * 也在打 /metrics，所以重复查库的代价按页面加载次数计。
 *
 * ⚠ 共享 Redis 上的并发：本用例会先删这两把键来制造冷启动（TTL 只有 30 秒）。
 * 若另一个套件在同一瞬间调用 /metrics，热启动那半段可能被它重新填热 —— 表现为偶发红。
 * 报读数前先 `pgrep -x phpunit` 确认没有并行套件（本仓既定纪律）。
 */
class MetricsCachingTest extends TestCase
{
    /** 本用例制造的冷启动所涉的缓存键，tearDown 一并清掉 */
    private const CACHE_KEYS = [
        'metrics:admin:active_users_today',
        'metrics:biz:deposit_confirmed_created_today',
    ];

    protected function setUp(): void
    {
        try {
            Db::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('数据库不可用：查询日志里不会有任何语句，判据无对象');
        }
        try {
            Redis::ping();
        } catch (\Throwable) {
            // cachedGauge 在 Redis 不可用时**静默降级**为每次直查 —— 此时热启动也必然查库，
            // 断言会红但红的不是被测代码。跳过并说明，而不是改断言。
            $this->markTestSkipped('Redis 不可用：cachedGauge 降级为每次直查，本用例的冷/热判据不成立');
        }
    }

    protected function tearDown(): void
    {
        try {
            Redis::del(...self::CACHE_KEYS);
        } catch (\Throwable) {
        }

        parent::tearDown();
    }

    #[Test]
    public function uncachedMetricsAreNotRequeriedWithinTheCacheWindow(): void
    {
        // 冷启动：清掉这两把键，保证第一次请求一定落库
        Redis::del(...self::CACHE_KEYS);
        $cold = $this->runAndLogQueries();

        $this->assertSame(1, $this->countQueries($cold, self::activeUsersQuery()),
            '前提探针：冷缓存时 open_admin_active_users 应恰好查一次库（0 次说明这条路径没在跑）');
        $this->assertSame(1, $this->countQueries($cold, self::confirmedCreatedTodayQuery()),
            '前提探针：冷缓存时成功率分子应恰好查一次库');

        $warm = $this->runAndLogQueries();

        $this->assertSame(0, $this->countQueries($warm, self::activeUsersQuery()),
            '30 秒内第二次请求仍在跑 last_login_at 的 whereDate —— open_admin_active_users 没套 cachedGauge');
        $this->assertSame(0, $this->countQueries($warm, self::confirmedCreatedTodayQuery()),
            '30 秒内第二次请求仍在跑成功率分子 —— 它还没从 BcMath::percent 的实参里挪进 cachedGauge');
    }

    private static function activeUsersQuery(): callable
    {
        return static fn(string $sql, array $bindings): bool => str_contains($sql, 'last_login_at');
    }

    /** 成功率分子：`status = 'confirmed'` 且按 created_at 取当天（分母没有 status 条件，可据此区分） */
    private static function confirmedCreatedTodayQuery(): callable
    {
        // ⚠ 判据只认**语义**（created_at 列 + 'confirmed' 绑定），**不认 SQL 拼法**。
        // 原先钉的是 `date(`created_at`)` 字样 —— 那是 `whereDate` 的编译产物；同批另一个代理
        // 把这批 whereDate 统一换成 `whereBetween`（MetricsController::todayRange()）后，
        // 本用例当场假红：「冷缓存时应恰好查一次库，实际 0」（2026-10-02 实测）。
        // 换成 whereBetween 之后列名 created_at 与绑定 'confirmed' 都还在，唯独 `date(` 没了。
        // 分母（deposit_total_today）没有 status 条件、`paid_at` 那条含 paid_at 不含 created_at，
        // 所以这两个条件合起来仍能把它与其余两条资金指标区分开。
        return static fn(string $sql, array $bindings): bool
            => str_contains($sql, 'created_at') && in_array('confirmed', $bindings, true);
    }

    /** @return array<int, array{query: string, bindings: array}> */
    private function runAndLogQueries(): array
    {
        $connection = Db::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            (new MetricsController())->index(new Request("GET /metrics HTTP/1.1\r\nHost: localhost\r\n\r\n"));
            return $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
        }
    }

    /**
     * @param array<int, array{query: string, bindings: array}> $log
     * @param callable(string, array): bool $matches
     */
    private function countQueries(array $log, callable $matches): int
    {
        return count(array_filter(
            $log,
            static fn(array $q): bool => $matches($q['query'], $q['bindings'])
        ));
    }
}
