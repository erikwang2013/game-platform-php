<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Schema;

class Grammar
{
    public function compileCreate(string $table, Blueprint $blueprint): string
    {
        $columns = array_map(fn(Column $c) => $c->toSql(), $blueprint->columns);
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $table . ' (' . implode(', ', $columns) . ')';
        $sql .= ' ENGINE = ' . $blueprint->getEngine();

        if ($partitionBy = $blueprint->getPartitionBy()) {
            $sql .= ' PARTITION BY ' . $partitionBy;
        }
        if ($orderBy = $blueprint->getOrderBy()) {
            $sql .= ' ORDER BY (' . implode(', ', $orderBy) . ')';
        }
        if ($primaryKey = $blueprint->getPrimaryKey()) {
            $sql .= ' PRIMARY KEY ' . $primaryKey;
        }
        if ($sampleBy = $blueprint->getSampleBy()) {
            $sql .= ' SAMPLE BY ' . $sampleBy;
        }
        if ($ttl = $blueprint->getTtl()) {
            $sql .= ' TTL ' . $ttl;
        }
        if ($settings = $blueprint->getSettings()) {
            $pairs = [];
            foreach ($settings as $k => $v) {
                $pairs[] = "$k = $v";
            }
            $sql .= ' SETTINGS ' . implode(', ', $pairs);
        }
        return $sql;
    }

    public function compileDrop(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $table;
    }

    public function compileAlter(string $table, Blueprint $blueprint): string
    {
        $columns = array_map(fn(Column $c) => 'ADD COLUMN ' . $c->toSql(), $blueprint->columns);
        return 'ALTER TABLE ' . $table . ' ' . implode(', ', $columns);
    }

    public function compileTableExists(string $table): string
    {
        return "EXISTS TABLE $table";
    }

    public function compileTableList(string $database = 'default'): string
    {
        return "SHOW TABLES FROM $database";
    }

    public function compileTableInfo(string $table): string
    {
        return "DESCRIBE TABLE $table";
    }
}