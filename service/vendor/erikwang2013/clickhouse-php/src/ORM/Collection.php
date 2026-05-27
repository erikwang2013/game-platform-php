<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\ORM;

class Collection implements \IteratorAggregate, \Countable, \ArrayAccess
{
    public function __construct(
        private readonly array $items,
    ) {
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function last(): mixed
    {
        return $this->items[count($this->items) - 1] ?? null;
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    public function filter(callable $callback): static
    {
        return new static(array_values(array_filter($this->items, $callback)));
    }

    public function pluck(string $column): array
    {
        return array_column($this->items, $column);
    }

    public function offsetExists(mixed $offset): bool { return isset($this->items[$offset]); }
    public function offsetGet(mixed $offset): mixed { return $this->items[$offset]; }
    public function offsetSet(mixed $offset, mixed $value): void { throw new \LogicException('Collection is read-only'); }
    public function offsetUnset(mixed $offset): void { throw new \LogicException('Collection is read-only'); }
}