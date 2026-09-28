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
     * 单条奖励金额上界（活性奖励的铸币闸）。
     *
     * ⚠ 这是**默认值，不是实测推导出来的值**：install.sql 只有 game_activity 的建表语句、零种子活动；
     * 实测 game-platform 与 game-platform-test 两库的 game_activity / game_activity_reward_log 均 0 行
     * ⇒「观察到的最大合法单条奖励」判定不出，取默认 10000。
     * 与 SelfProvider::MAX_PAYOUT_PER_ROUND = '10000'（service/app/provider/SelfProvider.php:30）
     * 同口径（单次动作最多出多少钱）：本仓只有这两处资金上限，改一处务必看另一处。
     * 将来 game_activity 有真实配置后，应按「观察到的最大合法单条奖励」重定此值。
     *
     * 闸长在出钱这一侧而非 admin 的 parseConfig —— 后者只挡 UI 写入路径，直接改库或将来新增的
     * 写入口都能绕过去；出钱的地方自己认数才拦得住。
     */
    private const MAX_REWARD_PER_ENTRY = '10000';

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
     *
     * 锁序不变式：**平台钱包先于游戏钱包**（与 ExchangeController 同一条规范序）。
     * 旧实现按 config 原序交错取锁，与兑换方向互逆 ⇒ 并发下 1213。
     * 规范序按 reward 类型静态可判（不依赖钱包行是否存在），stable partition 保证同组内相对顺序不变
     * ⇒ reward_ref 仍等于 config 里的位置，与历史行、uk 幂等语义完全一致；grantRewards 只由
     * progress()/checkin() 在同一事务内调用一次（末尾即置 REWARDED，无重入路径），故重排不会漏发或撞 uk。
     * （这条论证由 ActivityRewardLockOrderTest 钉住，别只留在注释里。）
     *
     * 坏配置不再抛：抛 ⇒ 整个 progress/checkin 事务回滚 ⇒ participation 永停 COMPLETED，
     * 且每次事件重试都抛 = 该活动对该用户永久卡死。改成「跳过这一条 + 落失败原因 + 进终态」，
     * 钱由运维按 error 日志补发。
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

        // 第一遍只做「这一条该不该发」的判定并记下它在 config 里的位置（= reward_ref）；不碰钱包、不取锁。
        $pending = [];
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
            if ($rewardType === '' || bccomp($amount, '0', WalletService::SCALE) <= 0) {
                $ref++;
                continue;
            }

            $pending[] = ['ref' => $ref, 'type' => $rewardType, 'amount' => $amount];
            $ref++;
        }

        // 第二遍按类型稳定分组：platform_coin 组整体前移到最前，组内保持 config 原序（其余同理）。
        $pending = array_merge(
            array_values(array_filter($pending, static fn (array $r): bool => $r['type'] === ActivityRewardLog::REWARD_PLATFORM_COIN)),
            array_values(array_filter($pending, static fn (array $r): bool => $r['type'] !== ActivityRewardLog::REWARD_PLATFORM_COIN))
        );

        $granted = [];
        foreach ($pending as $item) {
            $rewardType = $item['type'];
            $amount = $item['amount'];

            $log = new ActivityRewardLog();
            $log->id = SnowflakeService::generate();
            $log->user_id = $userId;
            $log->activity_id = $activity->id;
            $log->participation_id = $row->id;
            $log->period_key = $row->period_key;
            $log->reward_type = $rewardType;
            $log->reward_ref = $item['ref'];
            $log->amount = $amount;
            $log->status = 'succeeded';
            try {
                $log->save(); // uk 冲突 = 已发过，跳过
            } catch (\Throwable $e) {
                if (self::isDuplicateOnKey($e, 'uk_idempotent')) {
                    continue;
                }
                throw $e;
            }

            $reason = self::creditWallet($userId, $activity, $rewardType, $amount, $row->id);
            if ($reason !== null) {
                // 这条发不出去（坏配置/超上限），但 participation 必须走到终态，否则就是「卡死」本身。
                // 落 status=failed + fail_reason：运维据此定位并补发（表上有 idx_status）。
                $log->status = 'failed';
                $log->fail_reason = $reason;
                $log->save();
                Log::error('Activity reward skipped: ' . $reason, [
                    'participation_id' => $row->id,
                    'activity_id'      => $activity->id,
                    'user_id'          => $userId,
                    'reward_type'      => $rewardType,
                    'reward_ref'       => $item['ref'],
                    'amount'           => $amount,
                ]);
                continue;
            }

            $granted[] = ['type' => $rewardType, 'amount' => $amount];
        }

        $row->status = ActivityParticipation::STATUS_REWARDED;
        $row->save();

        return $granted;
    }

    /**
     * 走 M1 统一钱包入口（资金入口唯一，本服务不直接改余额）。
     * game_coin 奖励需 reward 配置携带 game_id/currency_id。
     *
     * @return string|null null = 已发放；非 null = **确定性**拒绝原因（坏配置/超上界），调用方跳过并落 fail_reason。
     *                     这里是「出钱的地方自己认数」的落点：单条上界只在这道闸上判。
     */
    private static function creditWallet(int $userId, Activity $activity, string $rewardType, string $amount, int $participationId): ?string
    {
        if (bccomp($amount, self::MAX_REWARD_PER_ENTRY, WalletService::SCALE) > 0) {
            return 'amount ' . $amount . ' exceeds MAX_REWARD_PER_ENTRY=' . self::MAX_REWARD_PER_ENTRY;
        }

        $remark = 'activity_reward:' . $activity->id . ':' . $participationId;

        if ($rewardType === ActivityRewardLog::REWARD_PLATFORM_COIN) {
            $ok = WalletService::mutate($userId, WalletScope::platform(), '+' . $amount, 'activity_reward', 'activity', (int) $activity->id, $remark);

            return $ok ? null : 'wallet mutate rejected: platform';
        }
        if ($rewardType === ActivityRewardLog::REWARD_GAME_COIN) {
            $config = is_array($activity->config) ? $activity->config : [];
            $gameId = (int) ($config['game_id'] ?? $activity->game_id);
            $currencyId = (int) ($config['currency_id'] ?? 0);
            if ($gameId <= 0 || $currencyId <= 0) {
                return 'game_coin reward needs game_id/currency_id';
            }
            $ok = WalletService::mutate($userId, WalletScope::game($gameId, $currencyId), '+' . $amount, 'activity_reward', 'activity', (int) $activity->id, $remark);

            return $ok ? null : 'wallet mutate rejected: game';
        }

        return 'unsupported reward_type: ' . $rewardType; // vip_exp/coupon/achievement 本最小区间不支持
    }

    /**
     * 只有撞在 uk_idempotent(participation_id, reward_type, reward_ref) 上才算「这条奖励已发过」。
     *
     * 原先按消息一刀切（`Duplicate entry`/`duplicate key`）：任何唯一键冲突都吞成「已发过」，包括
     * 主键 id（snowflake）撞号 —— 那种情况这条奖励其实**一条都没发**，却被静默跳过：没出钱、
     * 没 fail_reason、error 日志也没有，participation 照样进终态。用户看到的「已发」是假的。
     * 键名不匹配一律上抛：撞号是要运维介入的系统性故障，不能伪装成正常的幂等跳过。
     */
    private static function isDuplicateOnKey(\Throwable $e, string $key): bool
    {
        if (!$e instanceof \PDOException) {
            return false; // 非 PDO 异常没有 errorInfo，不认
        }

        return in_array($e->errorInfo[1] ?? null, [1062, 23000], true)
            && str_contains($e->getMessage(), $key);
    }
}
