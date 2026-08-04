<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\service;
use app\model\Achievement;
use app\model\UserAchievement;
use app\service\VipService;
use support\Db;

class AchievementService
{
    public static function check(int $userId, string $event, array $context = []): void
    {
        try {
            $achievements = Achievement::all();
            foreach ($achievements as $ach) {
                if (self::isCompleted($userId, $ach->id)) continue;
                $condition = json_decode($ach->condition_json, true) ?? [];
                if (($condition['event'] ?? '') !== $event) continue;

                $progress = self::getProgress($userId, $ach, $condition);
                $threshold = $condition['threshold'] ?? 0;
                $completed = $progress >= $threshold ? 1 : 0;

                $ua = UserAchievement::where('user_id', $userId)
                    ->where('achievement_id', $ach->id)->first();
                if (!$ua) {
                    $ua = new UserAchievement();
                    $ua->id = (int)(date('YmdHis') . random_int(10000, 99999));
                    $ua->user_id = $userId;
                    $ua->achievement_id = $ach->id;
                }
                $ua->progress = $progress;
                $ua->completed = $completed;
                $ua->save();

                if ($completed && ($ach->points ?? 0) > 0) {
                    VipService::addExp($userId, $ach->points, 'achievement', $ach->id, 'achievement');
                }
            }
        } catch (\Throwable $e) {
            // Achievement check failure must not block main flow
        }
    }

    private static function isCompleted(int $userId, int $achId): bool
    {
        $ua = UserAchievement::where('user_id', $userId)
            ->where('achievement_id', $achId)->first();
        return $ua && $ua->completed === 1;
    }

    private static function getProgress(int $userId, Achievement $ach, array $condition): int
    {
        $metric = $condition['metric'] ?? '';
        $table = $condition['table'] ?? '';
        $column = $condition['column'] ?? 'user_id';

        return match ($metric) {
            'count' => Db::table($table)->where($column, $userId)->count(),
            'sum' => (int) (Db::table($table)->where($column, $userId)->sum($condition['sum_column'] ?? 'amount') ?? 0),
            'distinct_count' => Db::table($table)->where($column, $userId)->distinct()->count($condition['distinct_column'] ?? 'id'),
            'consecutive_days' => self::consecutiveLoginDays($userId),
            default => 0,
        };
    }

    private static function consecutiveLoginDays(int $userId): int
    {
        $dates = Db::table('user_session')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->pluck('created_at')
            ->map(fn($d) => substr((string)$d, 0, 10))
            ->unique()
            ->values()
            ->toArray();

        if (empty($dates)) return 0;
        $today = date('Y-m-d');
        if ($dates[0] !== $today && $dates[0] !== date('Y-m-d', strtotime('-1 day'))) return 0;

        $count = 1;
        for ($i = 1; $i < count($dates); $i++) {
            $expected = date('Y-m-d', strtotime($dates[$i - 1] . ' -1 day'));
            if ($dates[$i] === $expected) $count++; else break;
        }
        return $count;
    }
}
