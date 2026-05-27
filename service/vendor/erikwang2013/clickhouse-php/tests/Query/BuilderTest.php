<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Tests\Query;

use Erikwang2013\ClickHouse\Client\ClientInterface;
use Erikwang2013\ClickHouse\Query\Builder;
use Erikwang2013\ClickHouse\Query\Expression;
use Erikwang2013\ClickHouse\Query\Result;
use PHPUnit\Framework\TestCase;
use Mockery;

class BuilderTest extends TestCase
{
    private function createBuilder(): Builder
    {
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('query')->andReturn(new Result([]));
        return new Builder($client);
    }

    public function testBasicSelectSql(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs')->where('level', 'error')->limit(10);
        $sql = $builder->toSql();
        $this->assertStringContainsString('SELECT * FROM logs', $sql);
        $this->assertStringContainsString("WHERE level = 'error'", $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testWhereInSql(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs')->whereIn('level', ['error', 'warn']);
        $sql = $builder->toSql();
        $this->assertStringContainsString("level IN ('error', 'warn')", $sql);
    }

    public function testWhereBetweenSql(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs')->whereBetween('date', ['2024-01-01', '2024-01-31']);
        $sql = $builder->toSql();
        $this->assertStringContainsString("WHERE date BETWEEN '2024-01-01' AND '2024-01-31'", $sql);
    }

    public function testWhereNullSql(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs')->whereNull('deleted_at');
        $sql = $builder->toSql();
        $this->assertStringContainsString('WHERE deleted_at IS NULL', $sql);
    }

    public function testOrderByAndGroupBy(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs')->groupBy('level')->orderBy('count', 'DESC');
        $sql = $builder->toSql();
        $this->assertStringContainsString('GROUP BY level', $sql);
        $this->assertStringContainsString('ORDER BY count DESC', $sql);
    }

    public function testInsertSql(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs');
        $sql = (new \Erikwang2013\ClickHouse\Query\Grammar())->compileInsert($builder, [
            ['name' => 'test', 'value' => 42],
        ]);
        $this->assertStringContainsString('INSERT INTO logs', $sql);
        $this->assertStringContainsString("'test'", $sql);
        $this->assertStringContainsString('42', $sql);
    }

    public function testWhereRawWithAndCombination(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs')->where('status', 'active')->whereRaw('some_column > 0');
        $sql = $builder->toSql();
        $this->assertStringContainsString("WHERE status = 'active' AND some_column > 0", $sql);
    }

    public function testExpressionNotQuoted(): void
    {
        $builder = $this->createBuilder();
        $builder->table('logs')->where('date', '>=', new Expression('today()'));
        $sql = $builder->toSql();
        $this->assertStringContainsString('WHERE date >= today()', $sql);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}