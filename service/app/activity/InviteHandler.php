<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\activity;

use app\model\Activity;

/**
 * 邀请注册活动：注册链路按分享短码绑定后驱动（AuthController::register → ShareLink::bindConversion）。
 * config schema: {target: 1, rewards: [{reward: {type, amount}}]}
 * period_key='all'（一次性活动，uk 幂等）；事件 'user.registered' 仅由绑定入口触发，不消费 Pub/Sub。
 */
class InviteHandler implements ActivityHandlerInterface
{
    public function canJoin(int $userId, Activity $activity, array $ctx): bool
    {
        if ($activity->status !== Activity::STATUS_ENABLED
            || ($activity->start_at !== null && $activity->start_at > $ctx['now'])
            || ($activity->end_at !== null && $activity->end_at < $ctx['now'])
            || ($activity->game_id !== 0 && (int) ($ctx['game_id'] ?? 0) !== $activity->game_id)) {
            return false;
        }
        // 只处理分享链接绑定的活动（ctx['activity_id'] 来自 game_share_link.activity_id）
        $activityId = (int) ($ctx['activity_id'] ?? 0);
        return $activityId > 0 && (int) $activity->id === $activityId;
    }

    public function onProgress(int $userId, Activity $activity, array $ctx): ?array
    {
        if ((string) ($ctx['event'] ?? '') !== 'user.registered') {
            return null;
        }
        $config = is_array($activity->config) ? $activity->config : [];
        $target = max(1, (int) ($config['target'] ?? 1));

        return ['period_key' => 'all', 'delta' => 1, 'target' => $target];
    }

    public function defaultConfig(): array
    {
        return [
            'target'  => 1,
            'rewards' => [
                ['reward' => ['type' => 'platform_coin', 'amount' => '10']],
            ],
        ];
    }
}
