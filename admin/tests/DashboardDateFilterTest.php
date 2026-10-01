<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\DashboardController;
use common\SnowflakeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Redis;
use support\Request;

/**
 * 仪表盘的日边界过滤**不得把列包进函数**。
 *
 * `whereDate('created_at', $d)` 被编译成 `date(created_at) = ?`
 * （vendor/illuminate/database/Query/Grammars/Grammar.php:526-531：
 * `'date('.$this->wrap($col).').'.$op.' ?'`）—— 列被函数包住，B+ 树索引直接失效，
 * 每次都退化成全表扫。四张表的 created_at 都是 `DATETIME(0)` 且都带 `idx_created_at`
 * （install/install.sql），所以换成裸列的范围比较就能走索引。
 *
 * 判据取**生成的 SQL**（照 service/tests/WalletTransactionsOrderingTest.php 的做法）：
 * 端到端断言查不出「索引用没用」——本机数据量小，全表扫与走索引的**结果完全一样**，
 * 唯一稳定的观察面是 SQL 本身。
 *
 * 覆盖面要点：`index()` 命中 Redis 时一条 SQL 都不发，所以先捅掉缓存键；
 * `platform()` **完全没有缓存**，10 处里它占 2 处，必须单独跑一遍。
 */
class DashboardDateFilterTest extends TestCase
{
    /** 边界用例播种的 operation_log 行 id，tearDown 逐条清掉 */
    private array $seededIds = [];

    /** 命中缓存则零 SQL，前提探针会失去对象 —— 先捅掉本 locale 的键 */
    private function cacheKey(): string
    {
        return 'dashboard:data:' . locale();
    }

    protected function setUp(): void
    {
        try {
            Db::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('数据库不可用：没有查询日志可断言');
        }

        $this->dropCache();
    }

    protected function tearDown(): void
    {
        if ($this->seededIds !== []) {
            try {
                Db::table('operation_log')->whereIn('id', $this->seededIds)->delete();
            } catch (\Throwable) {
            }
            $this->seededIds = [];
        }
        $this->dropCache();

        parent::tearDown();
    }

    private function dropCache(): void
    {
        try {
            Redis::del($this->cacheKey());
        } catch (\Throwable) {
            // Redis 不可用时 index() 本就会降级为直查，缓存键不存在也没关系
        }
    }

    #[Test]
    public function dashboardDateFiltersKeepTheColumnBareSoTheIndexIsUsable(): void
    {
        $controller = new DashboardController();
        $log = array_merge(
            $this->collect(fn() => $controller->index($this->request())),
            $this->collect(fn() => $controller->platform($this->request()))
        );

        $this->assertNotEmpty($log, '前提：两个端点都应发出 SQL，空日志说明本用例什么也没测到');
        $this->assertGreaterThan(0, $this->countDayBounded($log),
            '前提探针：应存在按 [00:00:00, 23:59:59] 闭区间过滤的查询；'
            . '0 说明没走到目标形状（或缓存没捅掉），此时下面的断言是恒真的');

        $offenders = [];
        foreach ($log as $q) {
            // whereDate 的编译产物：`where date(`col`) = ?` / `and date(`col`) >= ?`
            // 只禁**函数形式**。`select DATE(created_at) as date` 是分组键、不过滤，不算违规，
            // 且它前面是 select 不是 where/and，这里天然不会命中。
            if (preg_match('/\b(where|and)\s+date\s*\(/i', $q['query'])) {
                $offenders[] = $q['query'];
            }
        }

        $this->assertSame([], $offenders,
            "以下 SQL 把过滤列包进了函数 ⇒ idx_created_at 失效、退化成全表扫：\n  "
            . implode("\n  ", $offenders));
    }

    /**
     * 语义等价性：换形之后边界必须还是**整天**，且取**半开** `[当天 00:00:00, 次日 00:00:00)`。
     *
     * 上面那条只证明「没用函数」，证明不了「边界没写错」——把区间写成 `>= 今天` 也能让它绿。
     * 这条钉住绑定值里出现当天的 00:00:00 与**次日**的 00:00:00，且**不出现** 23:59:59。
     * （闭区间 `<= 23:59:59` 今天与半开等价 —— 四张表都是 DATETIME(0)；取半开是有意的，
     *  不依赖秒精度，见 DashboardController::platform() 的注释。）
     */
    #[Test]
    public function dayBoundsAreHalfOpenWholeDayIntervals(): void
    {
        $controller = new DashboardController();
        $log = $this->collect(fn() => $controller->platform($this->request()));

        $today    = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $seen = [];
        foreach ($log as $q) {
            foreach ($q['bindings'] as $b) {
                if (is_string($b) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $b)) {
                    $seen[$b] = true;
                }
            }
        }
        $seen = array_keys($seen);
        sort($seen);

        $this->assertContains($today . ' 00:00:00', $seen,
            '绑定值里没有当天起点。实际：' . implode(', ', $seen));
        $this->assertContains($tomorrow . ' 00:00:00', $seen,
            '绑定值里没有**次日**零点 —— 右端还停在当天（写成 `<= 当天 00:00:00` 或闭区间都会漏）。
             实际：' . implode(', ', $seen));
        $this->assertNotContains($today . ' 23:59:59', $seen,
            '右端是 23:59:59（闭区间）：DATETIME 一旦带小数秒就会静默漏掉 23:59:59.5 那一档');
    }

    /**
     * 边界互斥性 —— **行为面**读数，不是 SQL 形状。
     *
     * 播种两行 operation_log：一行落在**当天 23:59:59**（必须在内），一行落在**次日 00:00:00**
     * （必须在外），读仪表盘「Operation logs」那条统计的**差值**。
     *
     * 为什么非要行为面：形状断言只能证明「列没被函数包住」，把右端写成 `<= 次日 00:00:00`
     * 或 `< 次日 23:59:59`（区间整整宽了一天）它照样绿 —— 而那正是这条改写最容易犯的错。
     * 差值法：+2 = 区间写宽（次日那行被算进来了），0 = 写窄/写偏（当天 23:59:59 那行掉了），
     * `+1` 才是对的。用差值同时消掉了「库里本来有多少行」这个变量。
     *
     * ⚠ 前提探针：先按**故意放宽**的窗口 `[当天 00:00:00, 次日 00:00:00]` 数一遍，必须是
     * `before + 2`。插库失败时它是 `before + 0`，此时 `+1` 那条断言会因为「差的不是 2」而先红，
     * 而不是让 +1 蒙混过去。
     */
    #[Test]
    public function dayBoundaryIsMutuallyExclusiveAtMidnight(): void
    {
        $logsCount = static function (string $body): int {
            $payload = json_decode($body, true);
            foreach ($payload['data']['stats'] ?? [] as $stat) {
                if (($stat['label'] ?? null) === trans('Operation logs')) {
                    return (int) $stat['value'];
                }
            }
            self::fail('响应里找不到 Operation logs 这条统计，本用例失去对象');
        };

        $today    = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $this->dropCache();
        $before = $logsCount((new DashboardController())->index($this->request())->rawBody());

        // 两行同批插入：一个在当天最后一秒，一个在次日第零秒
        foreach ([$today . ' 23:59:59', $tomorrow . ' 00:00:00'] as $at) {
            $id = (int) SnowflakeService::generate();
            Db::table('operation_log')->insert([
                'id'         => $id,
                'user_id'    => 0,
                'action'     => 'probe.day.boundary',
                'method'     => 'POST',
                'path'       => '/probe/day-boundary-' . bin2hex(random_bytes(6)),
                'ip'         => '127.0.0.1',
                'source'     => 'web',
                'input'      => '{}',
                'created_at' => $at,
            ]);
            $this->seededIds[] = $id;
        }

        // 前提探针：放宽到「含次日零点」的闭区间时，两行都在
        $widened = Db::table('operation_log')
            ->where('created_at', '>=', $today . ' 00:00:00')
            ->where('created_at', '<=', $tomorrow . ' 00:00:00')
            ->count();
        $this->assertSame($before + 2, $widened,
            '前提：播种的两行应都落在放宽窗口 [当天 00:00:00, 次日 00:00:00] 内（证明它们真的插进去了）');

        $this->dropCache();
        $after = $logsCount((new DashboardController())->index($this->request())->rawBody());

        $this->assertSame($before + 1, $after,
            "当天计数应**恰好 +1**：当天 23:59:59 在内、次日 00:00:00 在外。"
            . "实际 before={$before}, after={$after} —— "
            . ($after === $before + 2 ? '区间写宽了：次日零点那行被算进了当天'
                : ($after === $before ? '区间写窄/写偏：当天 23:59:59 那行没被算进当天' : '差值与预期不符')));
    }

    private function request(): Request
    {
        return new Request("GET /admin/v1/dashboard HTTP/1.1\r\nHost: localhost\r\n\r\n");
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

    /**
     * @param array<int, array{query: string, bindings: array}> $log
     */
    private function countDayBounded(array $log): int
    {
        return count(array_filter($log, static function (array $q): bool {
            foreach ($q['bindings'] as $b) {
                if (is_string($b) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $b)) {
                    return true;
                }
            }
            return false;
        }));
    }
}
