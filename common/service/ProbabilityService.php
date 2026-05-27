<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;

/**
 * 概率计算服务
 *
 * 基于 ClickHouse 的 countIf 聚合函数，
 * 计算事件之间的联合概率和条件概率。
 */
class ProbabilityService
{
    /**
     * 联合概率 P(A ∩ B)
     *
     * @param array{table: string, alias: string, where?: array, whereRaw?: string} $eventA
     * @param array{table: string, alias: string, where?: array, whereRaw?: string} $eventB
     */
    public static function joint(array $eventA, array $eventB): float
    {
        $sql = self::buildProbabilitySql($eventA, $eventB, 'joint');
        $result = ClickHouseService::query($sql);

        return (float) ($result->first()['probability'] ?? 0);
    }

    /**
     * 条件概率 P(A | B) = P(A ∩ B) / P(B)
     *
     * @param array{table: string, alias: string, where?: array, whereRaw?: string} $eventA
     * @param array{table: string, alias: string, where?: array, whereRaw?: string} $eventB
     */
    public static function conditional(array $eventA, array $eventB): float
    {
        $sql = self::buildProbabilitySql($eventA, $eventB, 'conditional');
        $result = ClickHouseService::query($sql);

        return (float) ($result->first()['probability'] ?? 0);
    }

    /**
     * 构建概率计算 SQL
     */
    private static function buildProbabilitySql(array $eventA, array $eventB, string $type): string
    {
        if ($eventA['table'] === $eventB['table']) {
            return self::buildSameTableSql($eventA, $eventB, $type);
        }

        return self::buildCrossTableSql($eventA, $eventB, $type);
    }

    /**
     * 同表查询：直接使用 countIf
     */
    private static function buildSameTableSql(array $eventA, array $eventB, string $type): string
    {
        $table = self::quoteTable($eventA['table']);
        $whereAClause = self::buildWhereClause($eventA);
        $whereBClause = self::buildWhereClause($eventB);

        if ($type === 'conditional') {
            $bCount = $whereBClause ?: '1=1';
            $intersect = $whereBClause && $whereAClause
                ? "{$whereBClause} AND {$whereAClause}"
                : ($whereBClause ?: $whereAClause);
            return "SELECT countIf({$intersect}) / nullIf(countIf({$bCount}), 0) AS probability FROM {$table}";
        }

        $intersect = $whereAClause && $whereBClause
            ? "{$whereAClause} AND {$whereBClause}"
            : ($whereAClause ?: $whereBClause);
        $intersect = $intersect ?: '1=1';
        return "SELECT countIf({$intersect}) / count(*) AS probability FROM {$table}";
    }

    /**
     * 跨表查询：通过子查询获取事件集，INNER JOIN 求交集
     */
    private static function buildCrossTableSql(array $eventA, array $eventB, string $type): string
    {
        $alias = $eventA['alias'];
        $tableA = self::quoteTable($eventA['table']);
        $whereA = self::buildWhereClause($eventA);
        $bSet = self::buildDistinctSetSql($eventB);
        $quoteAlias = "`{$alias}`";

        if ($type === 'conditional') {
            $intCond = $whereA ?: '1=1';
            return "SELECT countIf({$intCond}) / nullIf(count(*), 0) AS probability "
                . "FROM ({$bSet}) AS b "
                . "INNER JOIN {$tableA} AS a ON b.{$quoteAlias} = a.{$quoteAlias}";
        }

        $bothCond = $whereA ?: '1=1';
        return "SELECT countIf({$bothCond}) / count(*) AS probability "
            . "FROM ({$bSet}) AS b "
            . "INNER JOIN {$tableA} AS a ON b.{$quoteAlias} = a.{$quoteAlias}";
    }

    /**
     * 构建 DISTINCT 事件集子查询
     */
    private static function buildDistinctSetSql(array $event): string
    {
        $table = self::quoteTable($event['table']);
        $alias = "`{$event['alias']}`";
        $where = self::buildWhereClause($event);
        $whereClause = $where ? " WHERE {$where}" : '';

        return "SELECT DISTINCT {$alias} FROM {$table}{$whereClause}";
    }

    /**
     * 构建 WHERE 条件
     */
    private static function buildWhereClause(array $event): string
    {
        $clauses = [];

        if (!empty($event['where']) && is_array($event['where'])) {
            foreach ($event['where'] as $column => $value) {
                $quotedCol = "`{$column}`";
                if (is_array($value)) {
                    $escaped = array_map(fn($v) => self::escapeValue($v), $value);
                    $clauses[] = $quotedCol . ' IN (' . implode(', ', $escaped) . ')';
                } else {
                    $clauses[] = $quotedCol . ' = ' . self::escapeValue($value);
                }
            }
        }

        if (!empty($event['whereRaw'])) {
            $clauses[] = $event['whereRaw'];
        }

        return implode(' AND ', $clauses);
    }

    private static function quoteTable(string $table): string
    {
        return implode('.', array_map(fn($p) => "`{$p}`", explode('.', $table)));
    }

    private static function escapeValue(mixed $value): string
    {
        if ($value === null) return 'NULL';
        if (is_int($value) || is_float($value)) return (string) $value;
        if (is_bool($value)) return $value ? '1' : '0';
        return "'" . addcslashes((string) $value, "\\'") . "'";
    }
}
