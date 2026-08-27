<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\service\ProbabilityService;

/**
 * platform-common 共享包测试
 * 覆盖: ProbabilityService SQL 构建与概率计算（无需数据库）
 */
class PlatformCommonTest extends TestCase
{
    #[Test]
    public function escapeValueHandlesAllTypes(): void
    {
        $this->assertSame('NULL', ProbabilityService::escapeValue(null));
        $this->assertSame('1', ProbabilityService::escapeValue(true));
        $this->assertSame('0', ProbabilityService::escapeValue(false));
        $this->assertSame('42', ProbabilityService::escapeValue(42));
        $this->assertSame('3.14', ProbabilityService::escapeValue(3.14));
        $this->assertSame('10.5', ProbabilityService::escapeValue('10.5'));
        $this->assertSame("'O''Brien'", ProbabilityService::escapeValue("O'Brien"));
        $this->assertSame("''", ProbabilityService::escapeValue(''));
    }

    #[Test]
    public function quoteTableHandlesSingleAndQualifiedNames(): void
    {
        $this->assertSame('`erik_game_play_log`', ProbabilityService::quoteTable('erik_game_play_log'));
        $this->assertSame('`db`.`table`', ProbabilityService::quoteTable('db.table'));
    }

    #[Test]
    public function buildWhereClauseHandlesScalarArrayAndRaw(): void
    {
        $this->assertSame(
            "`game_id` = 5 AND `action` IN ('start', 'earn') AND created_at > now()",
            ProbabilityService::buildWhereClause([
                'where' => ['game_id' => 5, 'action' => ['start', 'earn']],
                'whereRaw' => 'created_at > now()',
            ])
        );
        $this->assertSame('', ProbabilityService::buildWhereClause([]));
    }

    #[Test]
    public function buildDistinctSetSqlProducesExactQuery(): void
    {
        $this->assertSame(
            "SELECT DISTINCT `user_id` FROM `erik_game_play_log` WHERE `game_id` = 5",
            ProbabilityService::buildDistinctSetSql([
                'table' => 'erik_game_play_log',
                'alias' => 'user_id',
                'where' => ['game_id' => 5],
            ])
        );
    }

    #[Test]
    public function jointReturnsZeroWithoutDatabase(): void
    {
        // 数据库不可用时 joint() 应返回 0 概率而非抛异常
        $result = ProbabilityService::joint(['table' => 'nonexistent_table'], ['table' => 'nonexistent_table']);
        $this->assertSame(0.0, $result['joint_probability']);
        $this->assertSame(0.0, $result['confidence']);
    }

    #[Test]
    public function conditionalReturnsZeroWithoutDatabase(): void
    {
        $result = ProbabilityService::conditional(['table' => 'nonexistent_table'], ['table' => 'nonexistent_table']);
        $this->assertSame(0.0, $result['conditional_probability']);
        $this->assertSame(0.0, $result['confidence']);
    }
}
