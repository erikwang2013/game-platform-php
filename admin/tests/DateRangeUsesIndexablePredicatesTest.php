<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\LogController;
use app\admin\v1\controller\MetricsController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Redis;
use support\Request;

/**
 * 「按天筛选」一律用**裸列的范围比较**，不许把列包进 `date()`。
 *
 * 缺陷形状：`whereDate('created_at', date('Y-m-d'))` 被 illuminate 编译成
 * `date(created_at) = ?`（vendor/illuminate/database/Query/Grammars/Grammar.php:526-531
 * 就是 `'date('.$this->wrap($col).').'.$op.' ?'`）—— 列被函数包住 ⇒ **B+ 树索引整条失效**，
 * 每次调用都退化成全表扫。`/metrics` 被 angular 的 dashboard/settings 每次页面加载都打，
 * `/admin/v1/log` 的日期筛选是运营最常用的入口，两条都在热路径上。
 *
 * 口径照抄同批的 `ReportController::dailyRows`（`whereBetween('created_at', [$start.' 00:00:00', $end.' 23:59:59'])`），
 * 不另立一套。
 *
 * ⚠ 为什么钉 SQL + bindings 而不是钉结果行数：行数是**结果**，插几条落在今天/昨天的数据
 * 只能证明"筛选逻辑大致对"，**证明不了索引用没用上** —— 全表扫和走索引返回的行一模一样。
 * 索引可用性的可观察代理就是「谓词里有没有函数包列」，而那是 SQL 层的事实。
 * bindings 一并钉住，防止有人把 `date()` 去掉了却把边界值写错（比如漏掉 23:59:59，
 * 变成"只到当天 00:00:00"，当天数据全丢 —— 那是比慢更严重的错）。
 *
 * 变异读数：把任一处改回 `whereDate(...)` ⇒ 对应用例红（SQL 里出现 `date(`）。
 * 把 `' 23:59:59'` 去掉 ⇒ bindings 档红。
 */
final class DateRangeUsesIndexablePredicatesTest extends TestCase
{
    /** @var string[] 本用例动过的 Redis 键，跑完删掉免得污染别的用例 */
    private array $touchedCacheKeys = [];

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1 AS ok');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->touchedCacheKeys as $key) {
            try {
                Redis::del($key);
            } catch (\Throwable) {
            }
        }
        $this->touchedCacheKeys = [];
    }

    /**
     * 跑一次调用，回收**本次**生成的全部查询（SQL + bindings）。
     *
     * `flushQueryLog()` 是必须的：`disableQueryLog()` 只是关开关、**不清缓冲**，
     * `enableQueryLog()` 也不清 —— 少了它会把上一次调用（甚至别的用例）的查询一起捞回来，
     * 于是「查到了 date( 」可能来自别的端点，断言变成假红或假绿。
     *
     * @return array<int, array{sql:string, bindings:array<int,mixed>}>
     */
    private function captureQueries(callable $call): array
    {
        $connection = Db::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        try {
            $call();
            $log = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }

        return array_map(
            static fn (array $q): array => ['sql' => $q['query'], 'bindings' => $q['bindings']],
            $log
        );
    }

    /** SQL 里出现 `date(` 就说明有列被函数包住了（`DATE(created_at)` / `date(x) between` 都算）。 */
    private function assertNoFunctionWrappedColumn(array $queries, string $table, string $what): void
    {
        $onTable = array_values(array_filter(
            $queries,
            static fn (array $q): bool => stripos($q['sql'], $table) !== false
        ));

        $this->assertNotEmpty(
            $onTable,
            "没抓到任何打到 {$table} 的查询 ⇒ 断言会落空（缓存命中？提前 return？）。" . $what
        );

        foreach ($onTable as $q) {
            $this->assertDoesNotMatchRegularExpression(
                '/\bdate\s*\(/i',
                $q['sql'],
                "{$what}：谓词里出现了 date() ⇒ 列被函数包住，索引失效、全表扫。实际 SQL：" . $q['sql']
            );
        }
    }

    // ============================================================
    // 一、/metrics 的四个「今天」gauge
    // ============================================================

    #[Test]
    public function metricsTodayGaugesDoNotWrapTheColumnInDate(): void
    {
        // 这四个 gauge 都走 Redis 缓存（cachedGauge，30s TTL）—— 不删键的话闭包根本不执行，
        // 一条 SQL 都不会生成，下面 assertNotEmpty 会直接报"没抓到"而不是假绿
        $keys = [
            'metrics:admin:active_users_today',
            'metrics:biz:deposit_today',
            'metrics:biz:deposit_total_today',
            'metrics:biz:deposit_confirmed_created_today',
        ];
        foreach ($keys as $key) {
            $this->touchedCacheKeys[] = $key;
            try {
                Redis::del($key);
            } catch (\Throwable) {
            }
        }

        $queries = $this->captureQueries(function () {
            (new MetricsController())->index(
                new Request("GET /metrics HTTP/1.1\r\nHost: localhost\r\n\r\n")
            );
        });

        $this->assertNoFunctionWrappedColumn($queries, 'game_deposit_order', '/metrics 的今日充值 gauge ');
        $this->assertNoFunctionWrappedColumn($queries, 'game_admin_user', '/metrics 的今日活跃 gauge ');

        // 边界值也要对：漏掉 23:59:59 会变成"只统计到当天 00:00:00"，当天数据全丢
        $today = date('Y-m-d');
        $bounds = [];
        foreach ($queries as $q) {
            if (stripos($q['sql'], 'between') !== false
                && (stripos($q['sql'], 'game_deposit_order') !== false
                    || stripos($q['sql'], 'game_admin_user') !== false)
            ) {
                $bounds[] = $q['bindings'];
            }
        }
        $this->assertNotEmpty($bounds, '没抓到带 between 的今日区间查询');
        foreach ($bounds as $b) {
            // 取**末两位**：有的查询前面还挂着 where('status','confirmed')，
            // 它的 binding 排在区间之前（实测 bindings = ['confirmed', start, end]）。
            // whereBetween 永远是最后追加的，所以末两位恒为区间边界。
            $this->assertSame(
                [$today . ' 00:00:00', $today . ' 23:59:59'],
                array_slice(array_values($b), -2),
                '今日区间的边界值不对（必须是本地日的 [00:00:00, 23:59:59]）'
            );
        }
    }

    // ============================================================
    // 二、/admin/v1/log 的日期区间筛选
    // ============================================================

    #[Test]
    public function logDateFilterDoesNotWrapTheColumnInDate(): void
    {
        $start = '2026-01-02';
        $end   = '2026-01-03';

        $queries = $this->captureQueries(function () use ($start, $end) {
            (new LogController())->index(new Request(
                "GET /admin/v1/log?start_date={$start}&end_date={$end}&page=1&limit=15 HTTP/1.1\r\nHost: localhost\r\n\r\n"
            ));
        });

        $this->assertNoFunctionWrappedColumn($queries, 'game_operation_log', '操作日志的日期筛选 ');

        // 起止都要在，且边界是"当天整天"而不是当天零点
        $bounded = array_values(array_filter(
            $queries,
            static fn (array $q): bool => stripos($q['sql'], 'created_at') !== false
                && stripos($q['sql'], 'game_operation_log') !== false
        ));
        $this->assertNotEmpty($bounded, '没抓到操作日志的日期条件查询');

        $allBindings = array_merge(...array_map(static fn (array $q) => $q['bindings'], $bounded));
        $this->assertContains($start . ' 00:00:00', $allBindings, '起始边界丢了或没补 00:00:00');
        $this->assertContains($end . ' 23:59:59', $allBindings, '结束边界没补 23:59:59 ⇒ 结束当天一条都查不到');
    }

    /**
     * 端点契约是「纯日期」（DocsController:141 `'start_date' => 'date?'`，前端是
     * `<input type="date">`）。带时间的串必须先归一到日期再拼时分秒，否则会拼出
     * `'2026-01-02 12:00:00 23:59:59'` 这种非法值 —— 该值送进 MySQL 会被当成
     * 无法解析的日期，筛选静默失效（不报错、返回全量）。
     */
    #[Test]
    public function logDateFilterNormalisesDatetimeInputInsteadOfBuildingGarbage(): void
    {
        $queries = $this->captureQueries(function () {
            (new LogController())->index(new Request(
                "GET /admin/v1/log?start_date=2026-01-02%2012:00:00&end_date=2026-01-03%2012:00:00&page=1&limit=15 HTTP/1.1\r\nHost: localhost\r\n\r\n"
            ));
        });

        $bounded = array_values(array_filter(
            $queries,
            static fn (array $q): bool => stripos($q['sql'], 'game_operation_log') !== false
                && stripos($q['sql'], 'created_at') !== false
        ));
        $this->assertNotEmpty($bounded, '没抓到操作日志的日期条件查询');

        $allBindings = array_merge(...array_map(static fn (array $q) => $q['bindings'], $bounded));
        $this->assertContains('2026-01-02 00:00:00', $allBindings, '带时间的起始值没被归一到当天 00:00:00');
        $this->assertContains('2026-01-03 23:59:59', $allBindings, '带时间的结束值没被归一到当天 23:59:59');
        foreach ($allBindings as $b) {
            $this->assertDoesNotMatchRegularExpression(
                '/\d{2}:\d{2}:\d{2}\s+\d{2}:\d{2}:\d{2}/',
                (string) $b,
                '拼接出了两个时间段的非法日期值：' . $b
            );
        }
    }
}
