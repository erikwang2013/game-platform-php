<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\process\RiskIpCron;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use support\Db;
use Throwable;

/**
 * RiskIpCron 的信誉衰减必须**一批不漏地**扫完整个命中集。
 *
 * 为什么值得一条真库钉子：该循环把命中的行改成 `reputation_score = 50`，行的 where 条件
 * （`reputation_score < 50`）随更新而失效 —— 用 offset 分页（`chunk()` / `forPage()`）时，
 * 第 2 批的 offset 已经越过被前一批改走的行，结果集收缩 ⇒ **静默跳行**；而日志只会说
 * 「衰减了 N 条」，跳掉的那批无人知晓、要等下一个 90 天窗口。
 * `chunkById()` 按 `id > lastId` 翻页，与被改走的行无关，这正是本用例要钉的性质。
 *
 * 种子数必须 > 分块大小（500）：只放几十行时两种实现都一次全取，用例恒绿。
 * 这里放 520 行 ⇒ 至少翻 2 页，把它换回 `chunk()` 必然漏掉后 20 行（已实测：20 行未被衰减）。
 */
class RiskIpCronDecayChunkTest extends TestCase
{
    /** 分块大小 500 之上留余量：520 行 ⇒ 第 2 页 20 行 */
    private const ROWS = 520;
    /** 自持 ID 段：不撞雪花 ID，也便于精确清理与断言 */
    private const ID_BASE = 990007000;

    protected function setUp(): void
    {
        try {
            $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        } catch (Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());

            return;
        }
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        $this->cleanup();
        $this->seed();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    #[Test]
    public function decayScansEveryStaleRowWithoutSkipping(): void
    {
        (new ReflectionMethod(RiskIpCron::class, 'runDaily'))->invoke(null);

        $still = (int) Db::selectOne(
            'SELECT COUNT(*) c FROM game_ip_reputation WHERE id BETWEEN ? AND ? AND reputation_score < 50',
            [self::ID_BASE, self::ID_BASE + self::ROWS - 1]
        )->c;

        $this->assertSame(0, $still, sprintf(
            '有 %d/%d 行未被衰减：分页漏行（offset 会跳过被前一批改走的行，必须 chunkById）',
            $still,
            self::ROWS
        ));
    }

    private function seed(): void
    {
        $stale = date('Y-m-d H:i:s', time() - 100 * 86400);
        $rows = [];
        for ($i = 0; $i < self::ROWS; $i++) {
            $rows[] = [
                'id' => self::ID_BASE + $i,
                'ip_hash' => hash('sha256', 'risk-ip-cron-' . $i),
                'reputation_score' => 10,
                'source' => 'external_proxy',
                'first_seen_at' => $stale,
                'last_seen_at' => $stale,
                'hit_count' => 1,
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            Db::table('ip_reputation')->insert($chunk);
        }
    }

    private function cleanup(): void
    {
        Db::table('ip_reputation')
            ->whereBetween('id', [self::ID_BASE, self::ID_BASE + self::ROWS - 1])
            ->delete();
    }
}
