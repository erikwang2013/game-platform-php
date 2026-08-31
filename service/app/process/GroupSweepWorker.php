<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use support\Db;
use support\Log;

/**
 * 组队/公会定时校正（M4）：每小时
 * 1. team 到期自动解散（status=0，成员关系保留可查）
 * 2. member_count 与 game_group_member 实际数量对齐（并发加退的兜底）
 * ponytail: 简单全量 SQL 修正，量级小（组数 << 用户数）；量大后再按游标分批。
 */
class GroupSweepWorker
{
    public function onWorkerStart(): void
    {
        Log::info('GroupSweepWorker started');

        while (true) {
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
                $counts = Db::table('group g')
                    ->join('group_member m', 'm.group_id', '=', 'g.id')
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

            sleep(3600);
        }
    }
}
