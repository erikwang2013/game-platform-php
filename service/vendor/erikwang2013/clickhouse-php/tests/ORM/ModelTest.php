<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Tests\ORM;

use Erikwang2013\ClickHouse\ClickHouse;
use Erikwang2013\ClickHouse\Client\Manager;
use Erikwang2013\ClickHouse\ORM\Collection;
use PHPUnit\Framework\TestCase;
use Mockery;

class TestModel extends \Erikwang2013\ClickHouse\ORM\Model
{
    protected string $table = 'test_table';
}

class ModelTest extends TestCase
{
    public function testModelGetTable(): void
    {
        $model = new TestModel();
        $this->assertSame('test_table', $model->getTable());
    }

    public function testModelAttributes(): void
    {
        $model = new TestModel(['name' => 'foo', 'value' => 42]);
        $this->assertSame('foo', $model->name);
        $this->assertSame(42, $model->value);
    }

    public function testCollectionFirst(): void
    {
        $collection = new Collection([['a' => 1], ['a' => 2]]);
        $this->assertSame(['a' => 1], $collection->first());
        $this->assertSame(['a' => 2], $collection->last());
        $this->assertCount(2, $collection);
    }

    public function testCollectionPluck(): void
    {
        $collection = new Collection([['name' => 'a'], ['name' => 'b']]);
        $this->assertSame(['a', 'b'], $collection->pluck('name'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}