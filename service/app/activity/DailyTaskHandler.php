<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\activity;

use common\model\Activity;

/**
 * 每日任务活动：消费 EventBus 事件累加进度，全任务达标后一次性发奖。
 * config schema: {tasks: [{event: 'deposit.completed', target: 1, reward: {type, amount}}], reset_hour: 0}
 * 最小区间：period_key 按自然日（reset_hour 仅展示不生效）；target = Σ任务 target，
 * 事件命中任一任务 current+1，全部达标才发奖（reward_ref=任务序号 1 起，uk 防重）。
 * 可用事件：deposit.completed（Outbox 可靠路径）、anticheat.round_finished（Pub/Sub，payload 含 win_amount）。
 */
class DailyTaskHandler implements ActivityHandlerInterface
{
    public function canJoin(int $userId, Activity $activity, array $ctx): bool
    {
        return $activity->status === Activity::STATUS_ENABLED
            && ($activity->start_at === null || $activity->start_at <= $ctx['now'])
            && ($activity->end_at === null || $activity->end_at >= $ctx['now'])
            && ($activity->game_id === 0 || (int) ($ctx['game_id'] ?? 0) === $activity->game_id);
    }

    public function onProgress(int $userId, Activity $activity, array $ctx): ?array
    {
        $config = is_array($activity->config) ? $activity->config : [];
        $tasks  = $config['tasks'] ?? [];
        if (!is_array($tasks) || $tasks === []) {
            return null;
        }

        $event = (string) ($ctx['event'] ?? '');
        $matched = false;
        $target = 0;
        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }
            if ((string) ($task['event'] ?? '') === $event) {
                $matched = true;
            }
            $target += max(0, (int) ($task['target'] ?? 0));
        }
        if (!$matched || $target <= 0) {
            return null;
        }

        return ['period_key' => date('Y-m-d'), 'delta' => 1, 'target' => $target];
    }

    public function defaultConfig(): array
    {
        return [
            'tasks'      => [
                ['event' => 'deposit.completed', 'target' => 1, 'reward' => ['type' => 'platform_coin', 'amount' => '5']],
            ],
            'reset_hour' => 0,
        ];
    }
}
