/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\activity;

use common\model\Activity;

/**
 * 活动类型策略工厂。与 ProviderFactory 同构：新增活动类型只需加实现类 + 一个分支。
 */
class ActivityHandlerFactory
{
    public static function create(Activity $activity): ActivityHandlerInterface
    {
        return match ($activity->type) {
            Activity::TYPE_SIGNIN     => new SignInHandler(),
            Activity::TYPE_DAILY_TASK => new DailyTaskHandler(),
            Activity::TYPE_INVITE     => new InviteHandler(),
            default => throw new \InvalidArgumentException("Unknown activity type: {$activity->type}"),
        };
    }
}
