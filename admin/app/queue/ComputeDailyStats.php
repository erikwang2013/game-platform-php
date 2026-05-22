<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\queue;

use common\model\StatDaily;
use common\model\User;
use common\model\DepositOrder;
use common\model\WithdrawOrder;
use common\model\ExchangeRecord;
use common\model\GamePlayLog;

/**
 * 日统计快照计算（建议 crontab 每天凌晨 1:00 执行）
 */
class ComputeDailyStats
{
    public static function run(string $date = null): void
    {
        $date = $date ?? date('Y-m-d', strtotime('-1 day'));

        // 1. 用户统计
        $newUsers = User::whereDate('created_at', $date)->count();
        $activeUsers = User::whereDate('last_login_at', $date)->count();
        self::saveStat($date, 'users', 0, [
            'new_users' => $newUsers,
            'active_users' => $activeUsers,
            'total_users' => User::count(),
        ]);

        // 2. 充值统计
        $deposits = DepositOrder::whereDate('created_at', $date)
            ->where('status', 'confirmed')
            ->selectRaw('COUNT(*) as count, SUM(platform_amount) as total_amount')
            ->first();
        self::saveStat($date, 'deposit', 0, [
            'count' => (int)($deposits->count ?? 0),
            'total_amount' => $deposits->total_amount ?? '0.0000',
        ]);

        // 3. 提现统计
        $withdraws = WithdrawOrder::whereDate('created_at', $date)
            ->whereIn('status', ['approved', 'completed'])
            ->selectRaw('COUNT(*) as count, SUM(platform_amount) as total_amount')
            ->first();
        self::saveStat($date, 'withdraw', 0, [
            'count' => (int)($withdraws->count ?? 0),
            'total_amount' => $withdraws->total_amount ?? '0.0000',
        ]);

        // 4. 兑换统计
        $exchanges = ExchangeRecord::whereDate('created_at', $date)
            ->selectRaw('COUNT(*) as count, SUM(spread_fee) as total_fee')
            ->first();
        self::saveStat($date, 'exchange', 0, [
            'count' => (int)($exchanges->count ?? 0),
            'total_fee' => $exchanges->total_fee ?? '0.0000',
        ]);

        // 5. 游戏统计
        $gamePlays = GamePlayLog::whereDate('created_at', $date)
            ->selectRaw('COUNT(DISTINCT user_id) as players, COUNT(*) as total_sessions')
            ->first();
        self::saveStat($date, 'game', 0, [
            'players' => (int)($gamePlays->players ?? 0),
            'total_sessions' => (int)($gamePlays->total_sessions ?? 0),
        ]);
    }

    private static function saveStat(string $date, string $type, int $gameId, array $metrics): void
    {
        StatDaily::updateOrCreate(
            ['date' => $date, 'stat_type' => $type, 'game_id' => $gameId],
            [
                'id' => (int)(date('Ymd') . '000' . random_int(100, 999)),
                'metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
            ]
        );
    }
}
