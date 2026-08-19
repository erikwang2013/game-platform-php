<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use app\model\DepositOrder;
use app\model\Game;
use app\model\GamePlayLog;
use support\Db;
use support\Log;
use Throwable;

/**
 * 充值数据服务 — 基于 erik_deposit_order 实时聚合，DB 故障时返回空数据而非报错
 */
class DepositLogService
{
    /**
     * 充值审计：写入应用日志（deposit_log 表未进 install.sql 前的最小可用实现）
     */
    public static function log(int $orderId, int $userId, string $amount, string $currency, string $status): void
    {
        try {
            Log::info('deposit_audit', [
                'order_id' => $orderId,
                'user_id'  => $userId,
                'amount'   => $amount,
                'currency' => $currency,
                'status'   => $status,
            ]);
        } catch (Throwable) {
            // 审计失败不阻断入账主流程
        }
    }

    public static function revenueOverview(int $days): array
    {
        $days = max(1, $days);
        $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $result[date('Y-m-d', strtotime("-{$i} days"))] = '0';
        }
        try {
            $rows = DepositOrder::select(
                Db::raw('DATE(created_at) AS d'),
                Db::raw('COALESCE(SUM(platform_amount), 0) AS total')
            )
                ->where('status', 'confirmed')
                ->where('created_at', '>=', $since)
                ->groupBy('d')
                ->get();

            $sum = 0;
            foreach ($rows as $row) {
                $result[$row->d] = (string) $row->total;
                $sum += (float) $row->total;
            }
            return ['total' => (string) $sum, 'trend' => $result];
        } catch (Throwable) {
            return ['total' => '0', 'trend' => $result];
        }
    }

    public static function conversionByGame(int $days): array
    {
        $days = max(1, $days);
        $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        try {
            // 每款游戏：玩家数（去重）+ 充值人数（去重）+ 转化率
            $players = GamePlayLog::select('game_id', Db::raw('COUNT(DISTINCT user_id) AS c'))
                ->where('created_at', '>=', $since)
                ->groupBy('game_id')
                ->pluck('c', 'game_id');

            $depositors = DepositOrder::select('game_id', Db::raw('COUNT(DISTINCT user_id) AS c'))
                ->where('status', 'confirmed')
                ->where('created_at', '>=', $since)
                ->whereNotNull('game_id')
                ->groupBy('game_id')
                ->pluck('c', 'game_id');

            $gameIds = $players->keys()->merge($depositors->keys())->unique();
            $result = [];
            foreach ($gameIds as $gameId) {
                $p = (int) ($players[$gameId] ?? 0);
                $d = (int) ($depositors[$gameId] ?? 0);
                $game = Game::find($gameId);
                $result[] = [
                    'game_id'         => (int) $gameId,
                    'game_name'       => $game->name ?? $game->title ?? 'game#' . $gameId,
                    'players'         => $p,
                    'depositors'      => $d,
                    'conversion_rate' => $p > 0 ? round($d / $p, 4) : 0.0,
                ];
            }
            return $result;
        } catch (Throwable) {
            return [];
        }
    }
}
