<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\activity;

use common\model\Activity;

/**
 * 活动类型策略接口。与 ProviderFactory 同构：工厂按 activity.type 匹配实现。
 *
 * 业务性跳过（活动已结束 / 事件不匹配 / 未命中灰度）一律返回 false/null，
 * 不抛异常——由调用方在 handler 内吞掉，只有系统异常上抛驱动 Outbox 重试。
 */
interface ActivityHandlerInterface
{
    /**
     * 用户能否参与：活动时间窗 + game_id 匹配 + 灰度命中。
     */
    public function canJoin(int $userId, Activity $activity, array $ctx): bool;

    /**
     * 事件/操作驱动的进度步进描述。返回 null 表示该事件不作用于本活动。
     *
     * @return array{period_key: string, delta: int, target: int}|null
     */
    public function onProgress(int $userId, Activity $activity, array $ctx): ?array;

    /**
     * 该类型的默认 config（管理端建活动时兜底）。
     */
    public function defaultConfig(): array;
}
