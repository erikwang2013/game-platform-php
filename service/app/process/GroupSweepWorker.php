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

            // member_count 校正：以成员表实际有效行数为准
            $counts = Db::table('group as g')
                ->join('group_member as m', 'm.group_id', '=', 'g.id')
                ->whereNull('m.left_at')
                ->selectRaw('g.id, COUNT(*) AS cnt')
                ->groupBy('g.id')
                ->get();

            $fixed = 0;
            foreach ($counts as $row) {
                $memberCount = Db::table('group')->where('id', $row->id)->value('member_count');
                if ((int) $memberCount !== (int) $row->cnt) {
                    Db::table('group')->where('id', $row->id)->update(['member_count' => (int) $row->cnt]);
                    $fixed++;
                }
            }

            Log::info('group sweep done', ['expired' => count($expired), 'count_fixed' => $fixed]);
        } catch (\Throwable $e) {
            Log::error('group sweep failed', ['error' => $e->getMessage()]);
        }
    }
}
