<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use support\Db;
use support\Log;
use Workerman\Timer;

/**
 * 组队/公会定时校正（M4）：每小时
 * 1. team 到期自动解散（status=0，成员关系保留可查）
 * 2. member_count 与 game_group_member 实际数量对齐（并发加退的兜底）
 * ponytail: 简单全量 SQL 修正，量级小（组数 << 用户数）；量大后再按游标分批。
 */
class GroupSweepWorker
{
    /** 校正间隔（秒） */
    private const INTERVAL = 3600;

    public function onWorkerStart(): void
    {
        Log::info('GroupSweepWorker started');

        // 定时器而非 while(true)+sleep(3600)：onWorkerStart 必须尽快返回。workerman 的
        // Worker::run() 先 reinstallSignal()（把子进程的信号处理挂到事件循环上），
        // 再在 onWorkerStart 返回【之后】才 $globalEvent->run() ⇒ 阻塞在这里信号永不派发，
        // graceful stop（SIGINT/SIGQUIT）失效、只能 SIGKILL。
        // 首轮保留「启动即校正一次」语义（原 while 首轮不等待），用一次性 1s 延迟换不阻塞启动。
        Timer::add(1, [$this, 'sweep'], [], false);
        Timer::add(self::INTERVAL, [$this, 'sweep']);
    }

    public function sweep(): void
    {
        try {
            $now = date('Y-m-d H:i:s');

            // team 到期自动解散（left_at 置当前时间，成员关系保留）
            $expired = Db::table('group')
                ->where('type', 'team')
                ->where('status', 1)
                ->whereNotNull('expire_at')
                ->where('expire_at', '<', $now)
                ->pluck('id');
            foreach ($expired as $groupId) {
                Db::transaction(function () use ($groupId, $now) {
                    Db::table('group')->where('id', $groupId)->update(['status' => 0]);
                    Db::table('group_member')->where('group_id', $groupId)->whereNull('left_at')->update(['left_at' => $now]);
                });
            }

            // member_count 校正：以成员表实际有效行数为准。
            // ⚠ 这段原先有两处致命伤（2026-10-02 核实：runtime/logs 里 2026-09-28 起 133 条
            //   `Unknown column 'g.id'`，计数校正从落地起就是空转，只有前半段到期解散在跑）：
            //   a) `Db::table('group as g')` 的别名会被表前缀改写成 `game_g`，而 selectRaw 是**裸串**、
            //      不参与 wrap ⇒ `g.id` 在库里不存在，整条查询抛 1054 被下面的 catch 吞掉。
            //      故改成**单表聚合**：group_member 的 group_id 不带别名，裸串不受前缀影响。
            //   b) 取数必须覆盖「没有活跃成员的组」（成员全退光 / 压根没成员）。用别名 JOIN 时
            //      INNER JOIN 会把这类组整行吃掉，而它们恰是这个兜底存在的理由 —— 正常路径
            //      GroupController::leave()/解散 会自己把 member_count 置 0，兜底只见异常路径。
            //      这里用 `?? 0` 补零：比 LEFT JOIN + `whereNull('m.left_at')` 更难写错
            //      （WHERE 在连接**之后**生效，LEFT JOIN 补出的 NULL 行会被它一起滤掉，等于 INNER JOIN）。
            // 同族判据见 Health::GAP_SQL 的注释（缺行被吃掉 ⇒ 巡检退化为假绿灯）。
            $active = [];
            foreach (Db::table('group_member')->whereNull('left_at')
                ->selectRaw('group_id, COUNT(*) AS cnt')
                ->groupBy('group_id')
                ->get() as $row) {
                $active[(int) $row->group_id] = (int) $row->cnt;
            }

            $fixed = 0;
            foreach (Db::table('group')->select('id', 'member_count')->get() as $groupRow) {
                $groupId = (int) $groupRow->id;
                $cnt = $active[$groupId] ?? 0;
                if ((int) $groupRow->member_count !== $cnt) {
                    Db::table('group')->where('id', $groupId)->update(['member_count' => $cnt]);
                    $fixed++;
                }
            }

            Log::info('group sweep done', ['expired' => count($expired), 'count_fixed' => $fixed]);
        } catch (\Throwable $e) {
            Log::error('group sweep failed', ['error' => $e->getMessage()]);
        }
    }
}
