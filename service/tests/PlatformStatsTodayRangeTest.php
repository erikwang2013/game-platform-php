<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\PlatformStatsController;
use common\SnowflakeService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Redis;
use support\Request;

/**
 * `GET /api/v1/platform/stats` 的「今日局数」—— **公开无鉴权**端点，过滤列不能被函数包住。
 *
 * 原写法 `whereDate('created_at', date('Y-m-d'))` 编译成 `date(created_at) = ?`
 * （vendor/illuminate/database/Query/Grammars/Grammar.php 的 date 包装）——列被函数包住，
 * `game_game_play_log.idx_created_at`（install/install.sql:604）直接失效，每次都是全表扫；
 * 本端点匿名可无限触发 ⇒ 是个 DoS 放大杠杆。现改成裸列的半开区间
 * `>= 今天 00:00:00 AND < 明天 00:00:00`，口径同 MetricsController::todayRange。
 *
 * ⚠ 两条用例分工是刻意的，别把前者当钉子：
 *   - today...Boundary() 是**行为面**检查（右端写宽/写窄能红），但对「改回 whereDate」**无感**：
 *     whereDate 与半开区间在 DATETIME(0) 下语义等价，行为读数完全一样。
 *   - playsFilterKeepsTheColumnBare() 直接断**生成的 SQL**，这条才抓得到「改回 whereDate」。
 *
 * 库名必须含 test（沿用 WalletTransactionsOrderingTest / WithdrawDailyLimitBoundaryTest 的口径）；
 * 全部写入包在外层事务里，tearDown 整笔回滚。
 */
final class PlatformStatsTodayRangeTest extends TestCase
{
    private static bool $booted = false;

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
    }

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());
        }

        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (Db::transactionLevel() > 0) {
            Db::rollBack();
        }

        parent::tearDown();
    }

    /**
     * 边界正反证：今天 23:59:59 的一局**算进**今天，次日 00:00:00 的一局**不算**。
     *
     * 用**差值**读（先取基线再逐条播）而不是断绝对值：测试库今天可能已有别的行，
     * 绝对值会被它们带偏，差值只反映本用例播的那两条。
     */
    #[Test]
    public function todayPlaysCountsTodaysLastSecondButNotTomorrowsFirstSecond(): void
    {
        $base = $this->stats()['today_game_plays'];

        $this->seedPlayLog(date('Y-m-d', strtotime('+1 day')) . ' 00:00:00');
        $this->assertSame($base, $this->stats()['today_game_plays'],
            '次日 00:00:00 的一局不得算进今天 ⇒ 右端写宽了（`<= 次日零点` 之类）');

        $this->seedPlayLog(date('Y-m-d') . ' 23:59:59');
        $this->assertSame($base + 1, $this->stats()['today_game_plays'],
            '今天 23:59:59 的一局必须算进今天 ⇒ 右端写窄了（`< 今天 23:59:59` 之类会漏掉最后一秒）');
    }

    /**
     * 真钉子：过滤列必须是**裸列**（形状面）。
     *
     * 只在 game_play_log 的查询上看——`active_users_7d` 走的是 user 表，别把两件事混在一起。
     */
    #[Test]
    public function playsFilterKeepsTheColumnBareSoTheIndexIsUsable(): void
    {
        $log = $this->collect(fn () => $this->stats());

        $plays = array_values(array_filter(
            $log,
            static fn (array $q): bool => stripos($q['query'], 'game_play_log') !== false
        ));
        $this->assertNotEmpty($plays,
            '前提探针：查询日志里应出现 game_play_log 的查询；没有说明日志没生效，下面的断言恒真');

        $offenders = [];
        foreach ($plays as $q) {
            // whereDate 的编译产物：`where date(`col`) = ?`；`select DATE(created_at)` 是分组键，
            // 前面是 select 不是 where/and，天然不命中（同 DashboardDateFilterTest 的口径）。
            if (preg_match('/\b(where|and)\s+date\s*\(/i', $q['query'])) {
                $offenders[] = $q['query'];
            }
        }

        $this->assertSame([], $offenders,
            "以下 SQL 把过滤列包进了函数 ⇒ game_game_play_log.idx_created_at 失效、退化成全表扫，"
            . "而本端点公开无鉴权：\n  " . implode("\n  ", $offenders));
    }

    /**
     * 形状面只证明「没用函数」，证明不了「边界没写错」。这条钉绑定值里出现当天与**次日**的 00:00:00
     * 两个整点，且**不出现** 23:59:59（半开区间是有意选的，不依赖秒精度）。
     */
    #[Test]
    public function dayBoundsAreHalfOpenWholeDayIntervals(): void
    {
        $log = $this->collect(fn () => $this->stats());

        $today    = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $seen     = [];
        foreach ($log as $q) {
            foreach ($q['bindings'] as $b) {
                if (is_string($b) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $b)) {
                    $seen[$b] = true;
                }
            }
        }
        $seen = array_keys($seen);

        $this->assertContains($today . ' 00:00:00', $seen,
            '绑定值里没有当天起点。实际：' . implode(', ', $seen));
        $this->assertContains($tomorrow . ' 00:00:00', $seen,
            '绑定值里没有**次日**零点 —— 右端还停在当天（漏掉今天剩下时间）。实际：' . implode(', ', $seen));
        $this->assertNotContains($today . ' 23:59:59', $seen,
            '绑定了 23:59:59 ⇒ 右端是闭区间，DATETIME(0) 下今天等价，但换个精度就漏行；'
            . '有意取半开 [今天 00:00:00, 次日 00:00:00)');
    }

    /** @return array<string,mixed> 响应信封的 data 段 */
    private function stats(): array
    {
        // 端点自 2026-10-02 起整包缓存 60 秒（键见 PlatformStatsController::CACHE_KEY）。本用例读的是
        // **每次调用**的真实口径（差值断言、SQL 形状），所以每次先清键强制回落现算；不清的话第二次
        // 起拿到的是上一次的快照，差值断言会变红。Redis 不可用时端点自己也是现算，故吞掉异常。
        try {
            Redis::del(PlatformStatsController::CACHE_KEY);
        } catch (\Throwable) {
        }

        $response = (new PlatformStatsController())->stats(
            new Request("GET /api/v1/platform/stats HTTP/1.1\r\nHost: localhost\r\n\r\n")
        );
        $body = json_decode((string) $response->rawBody(), true) ?? [];

        $this->assertSame(0, $body['code'] ?? -1, '端点未成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));

        return $body['data'];
    }

    /** 直插一条对局日志（真表名 game_game_play_log，前缀由 Db 补） */
    private function seedPlayLog(string $createdAt): void
    {
        Db::table('game_play_log')->insert([
            'id'         => (int) SnowflakeService::generate(),
            'user_id'    => 990000101,
            'game_id'    => 990000102,
            'action'     => 'start',
            'created_at' => $createdAt,
        ]);
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
