<?php

declare(strict_types=1);

namespace common\service;

use Erikwang2013\ClickHouse\Webman\ClickHouseService;

class ProbabilityService
{
    public static function joint(array $eventA, array $eventB): float
    {
        $sql = self::buildProbabilitySql($eventA, $eventB, 'joint');
        $result = ClickHouseService::query($sql);
        return (float) ($result->first()['probability'] ?? 0);
    }

    public static function conditional(array $eventA, array $eventB): float
    {
        $sql = self::buildProbabilitySql($eventA, $eventB, 'conditional');
        $result = ClickHouseService::query($sql);
        return (float) ($result->first()['probability'] ?? 0);
    }

    private static function buildProbabilitySql(array $eventA, array $eventB, string $type): string
    {
        if ($eventA['table'] === $eventB['table']) {
            return self::buildSameTableSql($eventA, $eventB, $type);
        }
        return self::buildCrossTableSql($eventA, $eventB, $type);
    }

    private static function buildSameTableSql(array $eventA, array $eventB, string $type): string
    {
        $table = self::quoteTable($eventA['table']);
        $wA = self::buildWhereClause($eventA);
        $wB = self::buildWhereClause($eventB);

        if ($type === 'conditional') {
            $b = $wB ?: '1=1';
            $ab = $wB && $wA ? "{$wB} AND {$wA}" : ($wB ?: $wA);
            return "SELECT countIf({$ab}) / nullIf(countIf({$b}), 0) AS probability FROM {$table}";
        }

        $ab = $wA && $wB ? "{$wA} AND {$wB}" : ($wA ?: $wB);
        $ab = $ab ?: '1=1';
        return "SELECT countIf({$ab}) / count(*) AS probability FROM {$table}";
    }

    private static function buildCrossTableSql(array $eventA, array $eventB, string $type): string
    {
        $alias = $eventA['alias'];
        $tableA = self::quoteTable($eventA['table']);
        $wA = self::buildWhereClause($eventA);
        $bSet = self::buildDistinctSetSql($eventB);
        $quote = "`{$alias}`";

        if ($type === 'conditional') {
            $cond = $wA ?: '1=1';
            return "SELECT countIf({$cond}) / nullIf(count(*), 0) AS probability "
                . "FROM ({$bSet}) AS b "
                . "INNER JOIN {$tableA} AS a ON b.{$quote} = a.{$quote}";
        }

        $cond = $wA ?: '1=1';
        return "SELECT countIf({$cond}) / count(*) AS probability "
            . "FROM ({$bSet}) AS b "
            . "INNER JOIN {$tableA} AS a ON b.{$quote} = a.{$quote}";
    }

    private static function buildDistinctSetSql(array $event): string
    {
        $table = self::quoteTable($event['table']);
        $alias = "`{$event['alias']}`";
        $w = self::buildWhereClause($event);
        $wc = $w ? " WHERE {$w}" : '';
        return "SELECT DISTINCT {$alias} FROM {$table}{$wc}";
    }

    private static function buildWhereClause(array $event): string
    {
        $clauses = [];
        if (!empty($event['where']) && is_array($event['where'])) {
            foreach ($event['where'] as $col => $val) {
                $q = "`{$col}`";
                $clauses[] = is_array($val)
                    ? $q . ' IN (' . implode(', ', array_map(fn($v) => self::escapeValue($v), $val)) . ')'
                    : $q . ' = ' . self::escapeValue($val);
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
