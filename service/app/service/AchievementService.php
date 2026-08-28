<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

use app\common\SnowflakeService;
use app\model\Achievement;
use app\model\UserAchievement;
use support\Db;
use support\Log;

/**
 * Event-driven achievement progress tracker.
 *
 * condition_json schema (ecosystem migration seeds):
 * - event: deposit.completed | exchange.completed | game.played | user.login | referral.applied
 * - metric: count | sum | distinct_count | consecutive_days
 * - table / column / sum_column / distinct_column / threshold
 */
class AchievementService
{
    public static function handle(string $event, array $payload): void
    {
        $userId = self::resolveUserId($event, $payload);
        if ($userId <= 0) {
            return;
        }

        // condition_json 为 MySQL JSON 列，SQL 过滤事件，避免每次事件全表加载
        $achievements = Achievement::query()->where('condition_json->event', $event)->get();
        foreach ($achievements as $achievement) {
            $condition = json_decode((string) $achievement->condition_json, true);
            if (!is_array($condition)) {
                continue;
            }

            try {
                self::evaluate($userId, $achievement, $condition);
            } catch (\Throwable $e) {
                Log::warning('AchievementService evaluate failed: ' . $e->getMessage(), [
                    'achievement_key' => $achievement->key ?? null,
                    'user_id' => $userId,
                    'event' => $event,
                ]);
            }
        }
    }

    public static function check(string $event, array $payload): void
    {
        self::handle($event, $payload);
    }

    private static function resolveUserId(string $event, array $payload): int
    {
        if ($event === 'referral.applied') {
            return (int) ($payload['referrer_id'] ?? 0);
        }

        return (int) ($payload['user_id'] ?? 0);
    }

    private static function evaluate(int $userId, Achievement $achievement, array $condition): void
    {
        $ua = UserAchievement::where('user_id', $userId)
            ->where('achievement_id', $achievement->id)
            ->first();

        if ($ua && (int) $ua->completed === 1) {
            return;
        }

        $threshold = max(1, (int) ($condition['threshold'] ?? 1));
        $progress = max(0, self::computeProgress($userId, $condition));
        $completed = $progress >= $threshold ? 1 : 0;
        $storedProgress = min($progress, $threshold);

        if (!$ua) {
            $ua = new UserAchievement();
            $ua->id = SnowflakeService::generate();
            $ua->user_id = $userId;
            $ua->achievement_id = $achievement->id;
            $ua->progress = 0;
            $ua->completed = 0;
        }

        $ua->progress = $storedProgress;
        $ua->completed = $completed;
        $ua->save();

        if ($completed === 1) {
            VipService::addExp(
                $userId,
                (int) $achievement->points,
                'achievement',
                (int) $achievement->id,
                'achievement'
            );
        }
    }

    private static function computeProgress(int $userId, array $condition): int
    {
        $metric = (string) ($condition['metric'] ?? 'count');

        return match ($metric) {
            'count' => self::metricCount($userId, $condition),
            'sum' => self::metricSum($userId, $condition),
            'distinct_count' => self::metricDistinct($userId, $condition),
            'consecutive_days' => self::metricConsecutiveDays($userId),
            default => 0,
        };
    }

    private static function normalizeTable(string $table): string
    {
        $table = trim($table);
        if (str_starts_with($table, 'game_')) {
            return substr($table, 5);
        }

        return $table;
    }

    private static function safeIdent(string $name): ?string
    {
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) === 1 ? $name : null;
    }

    private static function metricCount(int $userId, array $condition): int
    {
        $table = self::normalizeTable((string) ($condition['table'] ?? ''));
        $column = self::safeIdent((string) ($condition['column'] ?? 'user_id'));
        if ($table === '' || $column === null || self::safeIdent($table) === null) {
            return 0;
        }

        $query = Db::table($table)->where($column, $userId);
        if ($table === 'deposit_order') {
            $query->where('status', 'confirmed');
        }

        return (int) $query->count();
    }

    private static function metricSum(int $userId, array $condition): int
    {
        $table = self::normalizeTable((string) ($condition['table'] ?? ''));
        $sumColumn = self::safeIdent((string) ($condition['sum_column'] ?? 'platform_amount'));
        if ($table === '' || $sumColumn === null || self::safeIdent($table) === null) {
            return 0;
        }

        $query = Db::table($table)->where('user_id', $userId);
        if ($table === 'deposit_order') {
            $query->where('status', 'confirmed');
        }

        return (int) floor((float) $query->sum($sumColumn));
    }

    private static function metricDistinct(int $userId, array $condition): int
    {
        $table = self::normalizeTable((string) ($condition['table'] ?? ''));
        $distinctColumn = self::safeIdent((string) ($condition['distinct_column'] ?? 'game_id'));
        if ($table === '' || $distinctColumn === null || self::safeIdent($table) === null) {
            return 0;
        }

        $row = Db::table($table)
            ->where('user_id', $userId)
            ->selectRaw("COUNT(DISTINCT `{$distinctColumn}`) AS cnt")
            ->first();

        return (int) ($row->cnt ?? 0);
    }

    private static function metricConsecutiveDays(int $userId): int
    {
        $rows = Db::table('user_session')
            ->where('user_id', $userId)
            ->orderByDesc('logged_in_at')
            ->limit(120)
            ->pluck('logged_in_at');

        $dates = [];
        foreach ($rows as $loggedInAt) {
            $day = substr((string) $loggedInAt, 0, 10);
            if ($day !== '' && !isset($dates[$day])) {
                $dates[$day] = true;
            }
        }

        if ($dates === []) {
            return 0;
        }

        $ordered = array_keys($dates);
        rsort($ordered);

        $streak = 1;
        for ($i = 1, $n = count($ordered); $i < $n; $i++) {
            $expected = date('Y-m-d', strtotime($ordered[$i - 1] . ' -1 day'));
            if ($ordered[$i] !== $expected) {
                break;
            }
            $streak++;
        }

        return $streak;
    }
}
