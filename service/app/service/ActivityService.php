<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

use app\activity\ActivityHandlerFactory;
use app\activity\ActivityHandlerInterface;
use common\SnowflakeService;
use app\event\EventBus;
use common\model\Activity;
use common\model\ActivityParticipation;
use common\model\ActivityRewardLog;
use support\Db;
use support\Log;

/**
 * 运营活动引擎（M3 最小区间）。
 *
 * 数据流: EventBus 事件 → EventConsumer::dispatch() 尾部 handle()（或 checkin 端点）
 *   → handler canJoin（时间窗/game_id/灰度，过期直接返回不落库）
 *   → progress（单事务原子累加，失败整体回滚——防 Outbox 重放双算）
 *   → 达标 → reward（WalletService::mutate + reward_log 同事务，uk 幂等防重发）
 *
 * 幂等设计：participation uk(user+activity+period) 保证同周期一条进度；
 * reward_log uk(participation+reward_type+reward_ref) 保证同一进度同一类奖只发一次。
 * 发奖统一走 M1 WalletService::mutate，本服务不直接改余额。
 */
class ActivityService
{
    /**
     * EventBus 事件入口（EventConsumer::dispatch() 尾部调用）。
     * 业务性跳过（活动结束/事件不匹配/未命中灰度）在 handler 内返回，不抛异常；
     * 系统异常按活动隔离记录，最后一个异常上抛——由 dispatch() 进 $failures 驱动可靠事件重试。
     */
    public static function handle(string $event, array $payload): void
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $ctx = ['event' => $event, 'game_id' => (int) ($payload['game_id'] ?? 0), 'now' => date('Y-m-d H:i:s')];

        $activities = Activity::where('status', Activity::STATUS_ENABLED)
            ->whereIn('type', [Activity::TYPE_SIGNIN, Activity::TYPE_DAILY_TASK, Activity::TYPE_INVITE])
            ->get();

        $firstError = null;
        foreach ($activities as $activity) {
            try {
                self::progress($userId, $activity, $ctx);
            } catch (\Throwable $e) {
                Log::warning('ActivityService progress failed: ' . $e->getMessage(), [
                    'activity_id' => $activity->id,
                    'user_id'     => $userId,
                    'event'       => $event,
                ]);
                $firstError ??= $e;
            }
        }

        if ($firstError !== null) {
            throw $firstError;
        }
    }

    /**
     * 进度步进（单事务原子累加）：handler 判定不参与/事件不匹配则直接返回，不落库。
     * 进度 + 达标发奖同一事务提交，任一失败整体回滚——Outbox 重放不会双算。
     */
    public static function progress(int $userId, Activity $activity, array $ctx): void
    {
        $handler = ActivityHandlerFactory::create($activity);
        if (!$handler->canJoin($userId, $activity, $ctx)) {
            return;
        }

        $step = $handler->onProgress($userId, $activity, $ctx);
        if ($step === null) {
            return;
        }

        $periodKey = (string) $step['period_key'];
        $delta     = max(0, (int) $step['delta']);
        $target    = max(0, (int) $step['target']);

        Db::transaction(function () use ($userId, $activity, $handler, $periodKey, $delta, $target) {
            $row = ActivityParticipation::where('user_id', $userId)
                ->where('activity_id', $activity->id)
                ->where('period_key', $periodKey)
                ->first();

            if ($row === null) {
                $row = new ActivityParticipation();
                $row->id = SnowflakeService::generate();
                $row->user_id = $userId;
                $row->activity_id = $activity->id;
                $row->period_key = $periodKey;
                $row->current = 0;
                $row->target = $target; // 目标快照：活动改配置不影响历史周期
                $row->status = ActivityParticipation::STATUS_PROGRESSING;
                $row->save();
            }

            if ($row->status === ActivityParticipation::STATUS_REWARDED) {
                return; // 本周期已发奖，不再累加
            }

            $row->current += $delta;
            if ($row->current >= $row->target) {
                $row->status = ActivityParticipation::STATUS_COMPLETED;
                $row->completed_at = date('Y-m-d H:i:s');
                $row->save();
                self::grantRewards($userId, $row, $activity, $handler);
            } else {
                $row->save();
            }
        });
    }

    /**
     * 签到端点（POST /checkin）。重复签到同周期命中 uk 幂等：已发奖返回 already，不重复发。
     *
     * @return array{status: string, reward?: array}
     * @throws \RuntimeException 活动不存在/未启用/不在时间窗（控制器转业务错误响应）
     */
    public static function checkin(int $userId, int $activityId): array
    {
        $activity = Activity::find($activityId);
        if (!$activity) {
            throw new \RuntimeException('Activity not found');
        }

        $handler = ActivityHandlerFactory::create($activity);
        $ctx = ['event' => '', 'game_id' => 0, 'now' => date('Y-m-d H:i:s')];
        if (!$handler->canJoin($userId, $activity, $ctx)) {
            throw new \RuntimeException('Activity not available');
        }

        $periodKey = date('Y-m-d');
        $result = ['status' => 'already'];

        Db::transaction(function () use ($userId, $activity, $handler, $periodKey, &$result) {
            $row = ActivityParticipation::where('user_id', $userId)
                ->where('activity_id', $activity->id)
                ->where('period_key', $periodKey)
                ->first();

            if ($row === null) {
                $row = new ActivityParticipation();
                $row->id = SnowflakeService::generate();
                $row->user_id = $userId;
                $row->activity_id = $activity->id;
                $row->period_key = $periodKey;
                $row->current = 0;
                $row->target = 1;
                $row->status = ActivityParticipation::STATUS_PROGRESSING;
                $row->save();
            }

            if ($row->status === ActivityParticipation::STATUS_REWARDED) {
                return; // 本日已签到已发奖
            }

            $row->current += 1;
            if ($row->current >= $row->target) {
                $row->status = ActivityParticipation::STATUS_COMPLETED;
                $row->completed_at = date('Y-m-d H:i:s');
                $row->save();
                $granted = self::grantRewards($userId, $row, $activity, $handler);
                $result = ['status' => 'rewarded', 'reward' => $granted];
            } else {
                $row->save();
                $result = ['status' => 'progressing'];
            }
        });

        return $result;
    }

    /**
     * 达标发奖：reward_log 落库 + WalletService::mutate 同事务。
     * reward_log uk(participation_id, reward_type, reward_ref) 防重——重复插入失败即代表已发，跳过。
     * 奖励条目无 day 键（每日任务）全发；有 day 键（签到）仅发 day <= current 的档位。
     */
    private static function grantRewards(
        int $userId,
        ActivityParticipation $row,
        Activity $activity,
        ActivityHandlerInterface $handler
    ): array {
        $config = is_array($activity->config) ? $activity->config : $handler->defaultConfig();
        $rewards = $config['rewards'] ?? [];
        if (!is_array($rewards)) {
            $rewards = [];
        }
        if ($rewards === [] && isset($config['tasks'])) { // daily_task 的奖励挂在 tasks 上
            $rewards = array_column(array_filter($config['tasks'], 'is_array'), 'reward');
        }

        $granted = [];
        $ref = 1;
        foreach ($rewards as $entry) {
            if (!is_array($entry)) {
                $ref++;
                continue;
            }
            $reward = $entry['reward'] ?? $entry;
            if (!is_array($reward)) {
                $ref++;
                continue;
            }
            $day = (int) ($entry['day'] ?? $row->current);
            if ($day > $row->current) { // 连续签到高档位未达，不发
                $ref++;
                continue;
            }

            $rewardType = (string) ($reward['type'] ?? '');
            $amount = (string) ($reward['amount'] ?? '0');
            if ($rewardType === '' || bccomp($amount, '0', 8) <= 0) {
                $ref++;
                continue;
            }

            $log = new ActivityRewardLog();
            $log->id = SnowflakeService::generate();
            $log->user_id = $userId;
            $log->activity_id = $activity->id;
            $log->participation_id = $row->id;
            $log->period_key = $row->period_key;
            $log->reward_type = $rewardType;
            $log->reward_ref = $ref;
            $log->amount = $amount;
            $log->status = 'succeeded';
            try {
                $log->save(); // uk 冲突 = 已发过，跳过
            } catch (\Throwable $e) {
                if (self::isDuplicateKey($e)) {
                    $ref++;
                    continue;
                }
                throw $e;
            }

            $ok = self::creditWallet($userId, $activity, $rewardType, $amount, $row->id);
            if (!$ok) {
                throw new \RuntimeException("Activity reward wallet mutate failed: {$rewardType} {$amount}");
            }

            $granted[] = ['type' => $rewardType, 'amount' => $amount];
            $ref++;
        }

        $row->status = ActivityParticipation::STATUS_REWARDED;
        $row->save();

        return $granted;
    }

    /**
     * 走 M1 统一钱包入口（资金入口唯一，本服务不直接改余额）。
     * game_coin 奖励需 reward 配置携带 game_id/currency_id。
     */
    private static function creditWallet(int $userId, Activity $activity, string $rewardType, string $amount, int $participationId): bool
    {
        $remark = 'activity_reward:' . $activity->id . ':' . $participationId;

        if ($rewardType === ActivityRewardLog::REWARD_PLATFORM_COIN) {
            return WalletService::mutate($userId, WalletScope::platform(), '+' . $amount, 'activity_reward', 'activity', (int) $activity->id, $remark);
        }
        if ($rewardType === ActivityRewardLog::REWARD_GAME_COIN) {
            $config = is_array($activity->config) ? $activity->config : [];
            $gameId = (int) ($config['game_id'] ?? $activity->game_id);
            $currencyId = (int) ($config['currency_id'] ?? 0);
            if ($gameId <= 0 || $currencyId <= 0) {
                return false;
            }
            return WalletService::mutate($userId, WalletScope::game($gameId, $currencyId), '+' . $amount, 'activity_reward', 'activity', (int) $activity->id, $remark);
        }

        return false; // vip_exp/coupon/achievement 奖励类型本最小区间不支持
    }

    private static function isDuplicateKey(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, 'Duplicate entry') || str_contains($msg, 'duplicate key');
    }
}
