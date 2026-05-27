<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Schema;

class Column
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $modifiers = [],
    ) {
    }

    public function toSql(): string
    {
        $sql = "`{$this->name}` {$this->type}";
        foreach ($this->modifiers as $modifier) {
            $sql .= ' ' . $modifier;
        }
        return $sql;
    }
}