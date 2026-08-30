<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\controller\ReportController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 报表控制器纯逻辑测试：日期校验、日期序列、聚合结构
 */
class ReportControllerTest extends TestCase
{
    private function callPrivate(string $method, ...$args): mixed
    {
        $m = new \ReflectionMethod(ReportController::class, $method);
        $m->setAccessible(true);
        return $m->invoke(new ReportController(), ...$args);
    }

    #[Test]
    public function normalizeDateRangeDefaultsToLast30Days(): void
    {
        $range = $this->callPrivate('normalizeDateRange', null, null);
        $this->assertSame(date('Y-m-d'), $range[1]);
        $this->assertSame(date('Y-m-d', strtotime('-29 days')), $range[0]);
    }

    #[Test]
    public function normalizeDateRangeRejectsInvalidFormats(): void
    {
        $this->assertNull($this->callPrivate('normalizeDateRange', '2026-1-1', null));
        $this->assertNull($this->callPrivate('normalizeDateRange', '2026-13-01', null));
        $this->assertNull($this->callPrivate('normalizeDateRange', '2026-02-31', null)); // 非法日期会被 strtotime 滚动
        $this->assertNull($this->callPrivate('normalizeDateRange', '2026-02-01', '2026-01-01')); // start > end
    }

    #[Test]
    public function normalizeDateRangeRejectsSpanOver90Days(): void
    {
        $this->assertNull($this->callPrivate('normalizeDateRange', '2026-01-01', '2026-06-01'));
        $this->assertNotNull($this->callPrivate('normalizeDateRange', '2026-01-01', '2026-03-31')); // 恰好 90 天
    }

    #[Test]
    public function dateSeriesGeneratesInclusiveSequence(): void
    {
        $series = $this->callPrivate('dateSeries', '2026-02-26', '2026-03-02');
        $this->assertSame(['2026-02-26', '2026-02-27', '2026-02-28', '2026-03-01', '2026-03-02'], $series);
    }

    #[Test]
    public function dailyRowsReturnZeroFilledStructure(): void
    {
        try {
            $rows = $this->callPrivate('dailyRows', '2026-01-01', '2026-01-03');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not configured in test environment');
        }

        $this->assertCount(3, $rows);
        $this->assertSame(
            ['date', 'new_users', 'deposit_amount', 'deposit_count', 'withdraw_amount', 'withdraw_count', 'exchange_amount', 'play_count'],
            array_keys($rows[0])
        );
        foreach ($rows as $row) {
            $this->assertIsInt($row['new_users']);
            $this->assertIsString($row['deposit_amount']);
            $this->assertIsInt($row['deposit_count']);
            $this->assertIsString($row['withdraw_amount']);
            $this->assertIsInt($row['withdraw_count']);
            $this->assertIsString($row['exchange_amount']);
            $this->assertIsInt($row['play_count']);
        }
    }
}
