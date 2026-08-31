<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\activity;

use common\model\Activity;

/**
 * 签到活动：端点驱动（POST /checkin），不消费 EventBus 事件。
 * config schema: {cycle: 7, rewards: [{day: 1, reward: {type, amount}}], max_streak_bonus: 0}
 * 最小区间：每日签到发 day=1 奖；连续天数/周期满奖（day>1）不做，reward 仅支持 platform_coin/game_coin。
 */
class SignInHandler implements ActivityHandlerInterface
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
        return null; // 签到走 checkin 端点，无事件步进
    }

    public function defaultConfig(): array
    {
        return [
            'cycle'            => 7,
            'rewards'          => [
                ['day' => 1, 'reward' => ['type' => 'platform_coin', 'amount' => '10']],
            ],
            'max_streak_bonus' => 0,
        ];
    }
}
