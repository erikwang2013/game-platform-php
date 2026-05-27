<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Query;

class Result implements \IteratorAggregate, \Countable, \ArrayAccess
{
    private int $rowCount;

    public function __construct(
        private readonly array $data,
        int $rowCount = 0,
        private readonly ?array $meta = null,
    ) {
        $this->rowCount = $rowCount ?: count($data);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->data);
    }

    public function count(): int
    {
        return $this->rowCount;
    }

    public function first(): mixed
    {
        return $this->data[0] ?? null;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function column(string $name): array
    {
        return array_column($this->data, $name);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Result is read-only');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Result is read-only');
    }
}