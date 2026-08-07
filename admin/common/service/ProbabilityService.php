<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use support\Db;
use Throwable;

/**
 * 概率分析服务 — 基于游戏行为日志（erik_game_play_log）计算事件概率
 *
 * 事件定义结构:
 *   ['table' => 'erik_game_play_log', 'alias' => 'user_id',
 *    'where' => ['game_id' => 5, 'action' => ['start', 'earn']],
 *    'whereRaw' => 'created_at > now()']
 */
class ProbabilityService
{
    /**
     * SQL 值转义：NULL→NULL, 布尔→1/0, 数值原样, 字符串加单引号并转义内部引号
     */
    public static function escapeValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    /**
     * 表名反引号引用，支持 "db.table" 双段形式
     */
    public static function quoteTable(string $table): string
    {
        $parts = explode('.', $table, 2);
        if (count($parts) === 2) {
            return "`{$parts[0]}`.`{$parts[1]}`";
        }
        return "`{$table}`";
    }

    /**
     * 从事件定义构建 WHERE 子句；数组值生成 IN 列表；whereRaw 原样追加
     */
    public static function buildWhereClause(array $event): string
    {
        $clauses = [];
        foreach ($event['where'] ?? [] as $field => $value) {
            if (is_array($value)) {
                $escaped = array_map([self::class, 'escapeValue'], $value);
                $clauses[] = "`{$field}` IN (" . implode(', ', $escaped) . ')';
            } else {
                $clauses[] = "`{$field}` = " . self::escapeValue($value);
            }
        }
        if (!empty($event['whereRaw'])) {
            $clauses[] = (string) $event['whereRaw'];
        }
        return implode(' AND ', $clauses);
    }

    /**
     * 构建去重集合查询：SELECT DISTINCT `alias` FROM `table` [WHERE ...]
     */
    public static function buildDistinctSetSql(array $event): string
    {
        $alias = (string) ($event['alias'] ?? 'id');
        $sql = 'SELECT DISTINCT `' . $alias . '` FROM ' . self::quoteTable((string) ($event['table'] ?? ''));
        $where = self::buildWhereClause($event);
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        return $sql;
    }

    /**
     * 联合概率：|A∩B| / |A∪B|（Jaccard 系数），confidence 为 |A∩B| / |A|
     */
    public static function joint(array $eventA, array $eventB): array
    {
        try {
            $nA = self::countDistinct($eventA);
            $nB = self::countDistinct($eventB);
            $nAB = self::countIntersect($eventA, $eventB);
            $union = $nA + $nB - $nAB;
            return [
                'joint_probability' => $union > 0 ? round($nAB / $union, 6) : 0.0,
                'confidence'        => $nA > 0 ? round($nAB / $nA, 6) : 0.0,
            ];
        } catch (Throwable) {
            // 数据库不可用时返回空概率，避免接口 500
            return ['joint_probability' => 0.0, 'confidence' => 0.0];
        }
    }

    /**
     * 条件概率 P(B|A) = |A∩B| / |A|
     */
    public static function conditional(array $eventA, array $eventB): array
    {
        try {
            $nA = self::countDistinct($eventA);
            $nAB = self::countIntersect($eventA, $eventB);
            $p = $nA > 0 ? round($nAB / $nA, 6) : 0.0;
            return [
                'conditional_probability' => $p,
                'confidence'               => $p,
            ];
        } catch (Throwable) {
            return ['conditional_probability' => 0.0, 'confidence' => 0.0];
        }
    }

    private static function countDistinct(array $event): int
    {
        $row = Db::selectOne('SELECT COUNT(*) AS c FROM (' . self::buildDistinctSetSql($event) . ') t');
        return (int) ($row->c ?? 0);
    }

    private static function countIntersect(array $eventA, array $eventB): int
    {
        $alias = (string) ($eventA['alias'] ?? 'id');
        $sql = 'SELECT COUNT(*) AS c FROM ('
            . self::buildDistinctSetSql($eventA) . ') a '
            . 'INNER JOIN (' . self::buildDistinctSetSql($eventB) . ') b '
            . 'ON a.`' . $alias . '` = b.`' . $alias . '`';
        $row = Db::selectOne($sql);
        return (int) ($row->c ?? 0);
    }
}
