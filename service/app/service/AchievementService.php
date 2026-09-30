<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;
use common\service\VipService;

use common\SnowflakeService;
use common\model\Achievement;
use common\model\UserAchievement;
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

        // condition_json 为 MySQL JSON 列，SQL 过滤事件，避免每次事件全表加载。
        // status=1 才算：管理端「停用」一条成就后事件不再触发授予（既有进度与已授予记录不受影响）。
        // 依赖 install/migrations/2026_09_30_achievement_status.sql 已执行（列缺失时本查询会抛错，
        // 而异常会被 EventConsumer 收进 $failures 驱动 Outbox 重试直至死信 ⇒ 顺序不能颠倒）。
        $achievements = Achievement::query()
            ->where('condition_json->event', $event)
            ->where('status', 1)
            ->get();
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

    /**
     * 算进度 → 落库 → 达标发经验。
     *
     * 发经验的**唯一凭据**是下面那次条件 UPDATE 的 affected rows，不是本函数开头那次读。
     * 旧写法是 check-then-act（读 completed=0 → 判 → 存 → addExp）：两个并发事件各自读到
     * completed=0、各自 addExp ⇒ 经验双发。这不只是积分问题——`VipService::addExp` 会顺带
     * 升 VIP 等级，而等级直接决定 `getExchangeDiscount()` 的兑换折扣（钱）。
     * CAS 在行锁上串行，并发的两个只有一个拿到 affected >= 1。
     *
     * 这条修法**不依赖 uk(user_id, achievement_id)** —— 既有生产库不会因 install.sql 而变化：
     * 无 uk 时并发插入会留下重复行，但两行 completed=0 ⇒ 第一次 UPDATE 命中 2 行（认领成功），
     * 第二个进程再 UPDATE 命中 0 行（不发经验）。DDL 只负责消掉重复行这个数据质量问题。
     */
    private static function evaluate(int $userId, Achievement $achievement, array $condition): void
    {
        // 快路径：已完成直接返回，连 progress 都不回写 —— 否则连续签到断签会把
        // 已完成成就的进度条改小（storedProgress 是按当前指标重算的）。
        $ua = UserAchievement::where('user_id', $userId)
            ->where('achievement_id', $achievement->id)
            ->first();

        if ($ua && (int) $ua->completed === 1) {
            return;
        }

        $threshold = max(1, (int) ($condition['threshold'] ?? 1));
        $progress = max(0, self::computeProgress($userId, $condition));
        $storedProgress = min($progress, $threshold);

        if (!$ua) {
            // 先落占位行（completed=0）。插入失败只在「撞唯一键且该行确实已存在」时吞掉；
            // 主键 id 撞号（snowflake 撞号）同样报 1062，但那一行并不存在 —— 必须冒出去，
            // 否则会被当成"别人插过了"，接着对一行不存在的记录做 CAS，静默不发经验。
            $ua = new UserAchievement();
            $ua->id = SnowflakeService::generate();
            $ua->user_id = $userId;
            $ua->achievement_id = $achievement->id;
            $ua->progress = 0;
            $ua->completed = 0;
            try {
                $ua->save();
            } catch (\PDOException $e) {
                $isDupKey = in_array($e->errorInfo[1] ?? null, [1062, 23000], true);
                $rowExists = UserAchievement::where('user_id', $userId)
                    ->where('achievement_id', $achievement->id)
                    ->exists();
                if (!$isDupKey || !$rowExists) {
                    throw $e;
                }
            }
        }

        if ($progress < $threshold) {
            // 未达标：只写进度。completed 恒 0、无副作用 ⇒ 并发重复写无害，不必 CAS。
            UserAchievement::where('user_id', $userId)
                ->where('achievement_id', $achievement->id)
                ->update(['progress' => $storedProgress]);

            return;
        }

        $claimed = UserAchievement::where('user_id', $userId)
            ->where('achievement_id', $achievement->id)
            ->where('completed', 0)
            ->update(['completed' => 1, 'progress' => $storedProgress]);

        if ($claimed < 1) {
            return; // 已有人认领过：一条经验都不发
        }

        VipService::addExp(
            $userId,
            (int) $achievement->points,
            'achievement',
            (int) $achievement->id,
            'achievement'
        );
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
