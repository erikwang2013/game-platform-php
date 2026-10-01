<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\MetricsController;
use common\SnowflakeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Redis;
use support\Request;

/**
 * `open_admin_reconciliation_diff_pending`（H3 资金风险）必须真的读到表。
 *
 * 旧实现的闭包是 `Db::table('game_reconciliation_diff')->where('status', 'pending')->count()`，
 * 里面叠了**两个**各自都足以让它恒为 0 的错，而外层 `safeCount()` 会把 SQLSTATE 42S22
 * 静默吞掉、返回 0 —— 指标照常出现在 /metrics 上，只是永远是 0：
 *  ① 表名多写了 `game_`：`Db::table` 自己会补前缀（config/database.php:36 `'prefix' => 'game_'`），
 *     于是实际查 `game_game_reconciliation_diff`（**不存在**，实测 information_schema 为 0 行）。
 *  ② 列名 `status` 不存在：真列是 `resolution`，值 pending/resolved/ignored
 *     （DDL: install.sql:1152；同口径见 ReconciliationService::listDiffs 的 where('resolution', …)）。
 *
 * 后果不是"少一个监控项"：这是待处理资金对账差异**唯一**的告警源，恒 0 等于差异永不告警。
 *
 * 本文件钉三件事（都是行为级，不看源码文本）：
 *  1. 指标读数 == 库里 resolution='pending' 的真实行数，且该行数 ≥ 2（不是"恰好 0 所以全绿"）；
 *  2. resolution='resolved' 的行**不计入** —— 证明 where 过滤是活的，不是"把整表 count 了"；
 *  3. 指标行照常出现在 /metrics 正文里（别为了修数据把指标本身弄没了）。
 *
 * 变异读数：
 *  - 去掉手工前缀改回 `'game_reconciliation_diff'` ⇒ 第 1 条红（读数 0 ≠ 期望 ≥2）；
 *  - 列名改回 `'status'` ⇒ 同样红。两条各自独立可红。
 */
final class MetricsReconciliationGaugeTest extends TestCase
{
    private const GAUGE_KEY = 'metrics:biz:reconciliation_diff_pending';
    private const METRIC    = 'open_admin_reconciliation_diff_pending';

    /** @var int[] tearDown 要删的 reconciliation_diff.id */
    private array $diffIds = [];

    protected function setUp(): void
    {
        try {
            $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());

            return;
        }
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }
        try {
            Redis::connection()->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis 不可用（指标有 30s 缓存，拿不到干净读数）：' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->diffIds as $id) {
            Db::table('reconciliation_diff')->where('id', $id)->delete();
        }
        $this->diffIds = [];

        // 别把本次的读数留在缓存里给别的用例当"真值"
        try {
            Redis::del(self::GAUGE_KEY);
        } catch (\Throwable) {
        }
    }

    // ============================================================
    // 播种
    // ============================================================

    /** 直插一条对账差异（真表名 game_reconciliation_diff，前缀由 Db 补）。 */
    private function seedDiff(string $resolution, string $severity = 'high'): void
    {
        $id = SnowflakeService::generate();
        Db::table('reconciliation_diff')->insert([
            'id'          => $id,
            'batch_id'    => SnowflakeService::generate(),
            'diff_type'   => 'amount_mismatch',
            'severity'    => $severity,
            'description' => 'probe',
            'resolution'  => $resolution,
        ]);
        $this->diffIds[] = $id;
    }

    /** 库里真值：这条才是"应该报几"的判据，不是源码里写的那个数。 */
    private function dbPendingCount(): int
    {
        return (int) Db::table('reconciliation_diff')->where('resolution', 'pending')->count();
    }

    /** 取 /metrics 正文里该指标那一行的读数。 */
    private function gaugeFromEndpoint(): int
    {
        try {
            Redis::del(self::GAUGE_KEY); // 30s 缓存：不删就读到别的用例留下的旧值
        } catch (\Throwable) {
        }

        $request  = new Request("GET /metrics HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $body     = (new MetricsController())->index($request)->rawBody();

        $this->assertMatchesRegularExpression(
            '/^# HELP ' . self::METRIC . ' /m',
            $body,
            '指标本身必须还在正文里（修数据不能把指标弄没）'
        );

        $this->assertSame(
            1,
            preg_match('/^' . self::METRIC . ' (\d+)$/m', $body, $m),
            '没在 /metrics 正文里找到 ' . self::METRIC . ' 的读数行'
        );

        return (int) $m[1];
    }

    // ============================================================
    // 一、真的读到了表（不是 safeCount 吞异常后的 0）
    // ============================================================

    #[Test]
    public function gaugeReadsTheRealPendingCountInsteadOfAlwaysZero(): void
    {
        $this->seedDiff('pending');
        $this->seedDiff('pending');
        // 干扰行：留在 pending 之外，用来看过滤有没有生效
        $this->seedDiff('resolved');
        $this->seedDiff('ignored');

        $expected = $this->dbPendingCount();
        $this->assertGreaterThanOrEqual(
            2,
            $expected,
            '库里至少该有刚播的两条 pending —— 否则下面的断言会在"真值恰好是 0"上假绿'
        );

        $this->assertSame(
            $expected,
            $this->gaugeFromEndpoint(),
            '指标读数与库里 resolution=\'pending\' 的真实行数对不上：表名多写 game_ 前缀、'
            . '或列名写成不存在的 status，都会让 safeCount() 吞掉 42S22 后恒返回 0'
        );
    }

    // ============================================================
    // 二、resolved / ignored 不得计入
    // ============================================================

    #[Test]
    public function onlyPendingRowsAreCounted(): void
    {
        $base = $this->dbPendingCount();

        // 只播非 pending 行 ⇒ pending 真值不变；把整表 count 了的话读数会比 $base 大 3
        $this->seedDiff('resolved');
        $this->seedDiff('resolved');
        $this->seedDiff('ignored');

        $this->assertSame($base, $this->dbPendingCount(), '前置条件：非 pending 行不该改变 pending 真值');
        $this->assertSame(
            $base,
            $this->gaugeFromEndpoint(),
            'resolved/ignored 被算进"待处理"了 —— 差异已处理完还在告警，运维会开始忽略这个指标'
        );
    }
}
