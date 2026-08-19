<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use app\model\DepositOrder;
use app\model\Game;
use app\model\GamePlayLog;
use app\model\User;
use support\Db;
use Throwable;

/**
 * 游戏运营仪表盘数据服务 — 全部基于 MySQL 实时聚合，DB 故障时返回空数据而非报错
 */
class GameDashboardService
{
    public static function overview(int $days): array
    {
        $days = max(1, $days);
        $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        try {
            return [
                'dau'       => (int) User::where('last_login_at', '>=', $since)->count(),
                'revenue'   => (string) (DepositOrder::where('status', 'confirmed')
                    ->where('created_at', '>=', $since)->sum('platform_amount') ?? '0'),
                'new_users' => (int) User::where('created_at', '>=', $since)->count(),
            ];
        } catch (Throwable) {
            return ['dau' => 0, 'revenue' => '0', 'new_users' => 0];
        }
    }

    public static function gameRanking(int $days): array
    {
        $days = max(1, $days);
        $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        try {
            $rows = GamePlayLog::select('game_id', Db::raw('COUNT(*) AS plays'), Db::raw('COUNT(DISTINCT user_id) AS players'))
                ->where('created_at', '>=', $since)
                ->groupBy('game_id')
                ->orderByDesc('plays')
                ->limit(10)
                ->get();

            return $rows->map(function ($row) {
                $game = Game::find($row->game_id);
                return [
                    'game_id' => (int) $row->game_id,
                    'name'    => $game->name ?? $game->title ?? 'game#' . $row->game_id,
                    'plays'   => (int) $row->plays,
                    'players' => (int) $row->players,
                ];
            })->all();
        } catch (Throwable) {
            return [];
        }
    }

    public static function dauTrend(int $days): array
    {
        $days = max(1, $days);
        $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $result[date('Y-m-d', strtotime("-{$i} days"))] = 0;
        }
        try {
            $rows = User::select(Db::raw('DATE(last_login_at) AS d'), Db::raw('COUNT(*) AS c'))
                ->where('last_login_at', '>=', $since)
                ->groupBy('d')
                ->get();
            foreach ($rows as $row) {
                $result[$row->d] = (int) $row->c;
            }
        } catch (Throwable) {
        }
        return $result;
    }

    public static function hourlyTrend(int $gameId = 0): array
    {
        $result = [];
        for ($h = 0; $h < 24; $h++) {
            $result[$h] = 0;
        }
        try {
            $query = GamePlayLog::select(Db::raw('HOUR(started_at) AS h'), Db::raw('COUNT(*) AS c'))
                ->where('created_at', '>=', date('Y-m-d 00:00:00'))
                ->groupBy('h');
            if ($gameId > 0) {
                $query->where('game_id', $gameId);
            }
            foreach ($query->get() as $row) {
                $result[(int) $row->h] = (int) $row->c;
            }
        } catch (Throwable) {
        }
        return $result;
    }

    public static function actionDistribution(int $gameId, int $hours): array
    {
        $hours = max(1, min($hours, 168));
        $result = ['start' => 0, 'end' => 0, 'earn' => 0, 'spend' => 0];
        try {
            $query = GamePlayLog::select('action', Db::raw('COUNT(*) AS c'))
                ->where('created_at', '>=', date('Y-m-d H:i:s', time() - $hours * 3600))
                ->groupBy('action');
            if ($gameId > 0) {
                $query->where('game_id', $gameId);
            }
            foreach ($query->get() as $row) {
                $result[$row->action] = (int) $row->c;
            }
        } catch (Throwable) {
        }
        return $result;
    }
}
