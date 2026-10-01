<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace common\service;

use common\BcMath;
use common\model\DepositOrder;
use common\model\Game;
use common\model\GamePlayLog;
use support\Db;
use support\Log;
use Throwable;

/**
 * 充值数据服务 — 基于 game_deposit_order 实时聚合，DB 故障时返回空数据而非报错
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

            $sum = '0';
            foreach ($rows as $row) {
                $result[$row->d] = (string) $row->total;
                $sum = bcadd($sum, (string) $row->total, 4);
            }
            return ['total' => rtrim(rtrim($sum, '0'), '.'), 'trend' => $result];
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

            // ⚠ 充值订单**没有 game_id 列**（`game_deposit_order` 的列里没有它），所以「某游戏多少人充过值」
            // 只能从对局记录反推：该游戏的玩家里，有多少人同期有过确认充值。
            // 旧写法直接 select DepositOrder.game_id ⇒ SQL 1054，而下面的 catch 把它吞成空数组
            // ⇒ **转化图永远是空的、且不报错**。判据：`SHOW COLUMNS FROM game_deposit_order` 里没有 game_id。
            // ponytail: whereIn 把充值用户全列进 IN —— 管理端看板按天窗口够用；真到十万级充值用户时
            //           应改成 join game_user_wallet / 物化表，别在这一层硬撑。
            $depositorIds = DepositOrder::where('status', 'confirmed')
                ->where('created_at', '>=', $since)
                ->distinct()
                ->pluck('user_id')
                ->all();

            $depositors = $depositorIds === []
                ? collect()
                : GamePlayLog::select('game_id', Db::raw('COUNT(DISTINCT user_id) AS c'))
                    ->where('created_at', '>=', $since)
                    ->whereIn('user_id', $depositorIds)
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
                    'conversion_rate' => $p > 0 ? (float) BcMath::round(bcdiv((string) $d, (string) $p, 6), 4) : 0.0,
                ];
            }
            return $result;
        } catch (Throwable $e) {
            // 只 log 不抛：看板不该因为一个聚合查询失败整页 500（与本类其它方法的取舍一致）。
            // **但不能静默** —— 上面那个「查了不存在的 game_id 列」就是被这里的空 catch 藏了不知多久。
            Log::error('conversionByGame failed: ' . $e->getMessage());

            return [];
        }
    }
}
