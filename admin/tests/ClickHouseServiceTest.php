<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use common\service\ProbabilityService;

class ClickHouseServiceTest extends TestCase
{
    // ============================================================
    // ProbabilityService — pure SQL builder logic
    // ============================================================

    #[Test]
    public function escapeValue_null(): void
    {
        $this->assertSame('NULL', $this->invoke(ProbabilityService::class, 'escapeValue', [null]));
    }

    #[Test]
    public function escapeValue_int(): void
    {
        $this->assertSame('42', $this->invoke(ProbabilityService::class, 'escapeValue', [42]));
    }

    #[Test]
    public function escapeValue_float(): void
    {
        $this->assertSame('3.14', $this->invoke(ProbabilityService::class, 'escapeValue', [3.14]));
    }

    #[Test]
    public function escapeValue_bool(): void
    {
        $this->assertSame('1', $this->invoke(ProbabilityService::class, 'escapeValue', [true]));
        $this->assertSame('0', $this->invoke(ProbabilityService::class, 'escapeValue', [false]));
    }

    #[Test]
    public function escapeValue_string(): void
    {
        $this->assertSame("'hello'", $this->invoke(ProbabilityService::class, 'escapeValue', ['hello']));
    }

    #[Test]
    public function escapeValue_escapes_quote(): void
    {
        $this->assertSame("'it''s'", $this->invoke(ProbabilityService::class, 'escapeValue', ["it's"]));
    }

    #[Test]
    public function quoteTable_single(): void
    {
        $this->assertSame('`game_game_play_log`', $this->invoke(ProbabilityService::class, 'quoteTable', ['game_game_play_log']));
    }

    #[Test]
    public function quoteTable_with_db(): void
    {
        $this->assertSame('`default`.`game_game_play_log`', $this->invoke(ProbabilityService::class, 'quoteTable', ['default.game_game_play_log']));
    }

    #[Test]
    public function buildWhereClause_single(): void
    {
        $r = $this->invoke(ProbabilityService::class, 'buildWhereClause', [['table' => 't', 'alias' => 'id', 'where' => ['game_id' => 5]]]);
        $this->assertSame('`game_id` = 5', $r);
    }

    #[Test]
    public function buildWhereClause_multi(): void
    {
        $r = $this->invoke(ProbabilityService::class, 'buildWhereClause', [['table' => 't', 'alias' => 'id', 'where' => ['game_id' => 5, 'status' => 'confirmed']]]);
        $this->assertStringContainsString(' AND ', $r);
    }

    #[Test]
    public function buildWhereClause_in(): void
    {
        $r = $this->invoke(ProbabilityService::class, 'buildWhereClause', [['table' => 't', 'alias' => 'id', 'where' => ['game_id' => [1, 2, 3]]]]);
        $this->assertStringContainsString('IN (1, 2, 3)', $r);
    }

    #[Test]
    public function buildWhereClause_raw(): void
    {
        $r = $this->invoke(ProbabilityService::class, 'buildWhereClause', [['table' => 't', 'alias' => 'id', 'whereRaw' => 'created_at > now()']]);
        $this->assertStringContainsString('created_at > now()', $r);
    }

    #[Test]
    public function buildWhereClause_empty(): void
    {
        $r = $this->invoke(ProbabilityService::class, 'buildWhereClause', [['table' => 't', 'alias' => 'id']]);
        $this->assertSame('', $r);
    }

    #[Test]
    public function buildDistinctSetSql_full(): void
    {
        $r = $this->invoke(ProbabilityService::class, 'buildDistinctSetSql', [['table' => 't', 'alias' => 'user_id', 'where' => ['game_id' => 5]]]);
        $this->assertStringContainsString('SELECT DISTINCT `user_id`', $r);
        $this->assertStringContainsString('WHERE', $r);
    }

    // ============================================================
    // Autoload + API contract
    // ============================================================

    #[Test]
    public function all_services_autoload(): void
    {
        foreach ([
            ProbabilityService::class,
            \common\service\GameDashboardService::class,
            \common\service\DepositLogService::class,
        ] as $c) {
            $this->assertTrue(class_exists($c));
        }
    }

    #[Test]
    public function all_methods_exist(): void
    {
        $this->assertTrue(method_exists(ProbabilityService::class, 'joint'));
        $this->assertTrue(method_exists(ProbabilityService::class, 'conditional'));
        $this->assertTrue(method_exists(\common\service\GameDashboardService::class, 'overview'));
        $this->assertTrue(method_exists(\common\service\DepositLogService::class, 'log'));
        $this->assertTrue(method_exists(\common\service\DepositLogService::class, 'revenueOverview'));
    }

    // ============================================================
    // Helper
    // ============================================================

    private function invoke(string $class, string $method, array $args): mixed
    {
        $ref = new ReflectionClass($class);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke(null, ...$args);
    }
}
