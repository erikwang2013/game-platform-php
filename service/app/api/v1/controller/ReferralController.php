<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\PlatformConfig;
use common\model\Referral;
use app\model\ReferralReward;
use common\model\UserWallet;
use common\service\NotificationService;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Db;
use support\Request;
use support\Response;
use app\event\EventBus;
use common\service\VipService;

#[Apidoc\Title("推荐管理")]
#[Apidoc\Group("referral")]
class ReferralController extends BaseController
{
    #[Apidoc\Title("我的推荐码")]
    #[Apidoc\Url("/api/v1/referral/my-code")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Auth(true)]
    public function myCode(Request $request): Response
    {
        $userId   = $request->userId;
        $referral = Referral::where('referrer_id', $userId)->first();

        $code = $referral ? $referral->code : null;

        // Count how many users this user has referred (records where they are the referrer)
        $referralCount = Referral::where('referrer_id', $userId)
            ->where('status', 1)
            ->count();

        // Sum rewards earned from referrals
        $totalRewards = ReferralReward::where('user_id', $userId)
            ->where('status', 1)
            ->sum('amount');

        return $this->success([
            'code'           => $code,
            'referral_count' => $referralCount,
            'total_rewards'  => (string) ($totalRewards ?? '0'),
        ]);
    }

    #[Apidoc\Title("推荐统计")]
    #[Apidoc\Url("/api/v1/referral/stats")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Auth(true)]
    public function stats(Request $request): Response
    {
        $userId = $request->userId;

        $referralCount = Referral::where('referrer_id', $userId)
            ->where('status', 1)
            ->count();

        $totalRewards = ReferralReward::where('user_id', $userId)
            ->where('status', 1)
            ->sum('amount');

        $referral = Referral::where('referrer_id', $userId)->first();
        $code = $referral ? $referral->code : '';

        return $this->success([
            'referral_count' => $referralCount,
            'total_rewards'  => (string) ($totalRewards ?? '0'),
            'code'           => $code,
        ]);
    }

    #[Apidoc\Title("使用推荐码")]
    #[Apidoc\Url("/api/v1/referral/apply")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Auth(true)]
    #[Apidoc\Param(name: "code", type: "string", require: true, desc: "推荐码")]
    public function apply(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|size:8',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $userId = $request->userId;
        $code   = strtoupper(trim($request->input('code')));

        // Find the referrer by their code
        $referrerRecord = Referral::where('code', $code)->first();
        if (!$referrerRecord) {
            return $this->fail(trans('Invalid referral code'), 404);
        }

        $referrerId = $referrerRecord->referrer_id;

        // Cannot refer yourself
        if ($referrerId === $userId) {
            return $this->fail(trans('Cannot use your own referral code'), 422);
        }

        // Check if this user has already been referred
        $alreadyReferred = Referral::where('referred_id', $userId)->exists();
        if ($alreadyReferred) {
            return $this->fail(trans('You have already applied a referral code'), 422);
        }

        // Read bonus amounts from platform config (defaults to 0)
        $referrerBonus = PlatformConfig::get('referral', 'referrer_bonus', '0');
        $referredBonus = PlatformConfig::get('referral', 'referred_bonus', '0');

        // 推荐奖励跨 5 表（referral / referral_reward / referral_commission / wallet / transaction）
        // 共 9 处写，原先零事务：referral 行一旦落库，后面任何一处失败都会让被推荐人永久拿不到奖励
        // —— 重试被上面的 alreadyReferred 预检挡死。整段包一个事务，全成或全无。
        try {
            Db::transaction(function () use ($referrerId, $userId, $code, $referrerBonus, $referredBonus) {
                // Create the referral record for the referred user
                $referral = new Referral();
                $referral->id          = $this->generateId();
                $referral->referrer_id = $referrerId;
                $referral->referred_id = $userId;
                $referral->code        = $code;
                $referral->status      = 1;
                // 并发场景下唯一索引兜底：预检查通过后仍可能撞 uk(referred_id)，由外层 catch 转 422
                $referral->save();

                // Grant bonus to referrer
                if (bccomp($referrerBonus, '0', 2) > 0) {
                    self::creditReferralBonus($referrerId, $referrerBonus);

                    $rewardId = $this->generateId();
                    $reward = new ReferralReward();
                    $reward->id            = $rewardId;
                    $reward->referral_id   = $referral->id;
                    $reward->user_id       = $referrerId;
                    $reward->type          = 'referrer_bonus';
                    $reward->amount        = $referrerBonus;
                    $reward->source_amount = '0';
                    $reward->status        = 1;
                    $reward->save();

                    // 流水已由 WalletService 统一写入（type=referral_bonus，ref 关联后续奖励单）
                    NotificationService::send(
                        $referrerId,
                        'referral',
                        'Referral Bonus',
                        "You received {$referrerBonus} platform tokens for referring a new user.",
                        'referral',
                        $referral->id
                    );
                }

                // Grant bonus to referred user
                if (bccomp($referredBonus, '0', 2) > 0) {
                    self::creditReferralBonus($userId, $referredBonus);

                    $rewardId2 = $this->generateId();
                    $reward2 = new ReferralReward();
                    $reward2->id            = $rewardId2;
                    $reward2->referral_id   = $referral->id;
                    $reward2->user_id       = $userId;
                    $reward2->type          = 'referred_bonus';
                    $reward2->amount        = $referredBonus;
                    $reward2->source_amount = '0';
                    $reward2->status        = 1;
                    $reward2->save();

                    // 流水已由 WalletService 统一写入（type=referral_bonus，ref 关联后续奖励单）
                    NotificationService::send(
                        $userId,
                        'referral',
                        'Welcome Bonus',
                        "You received {$referredBonus} platform tokens as a signup referral bonus.",
                        'referral',
                        $referral->id
                    );
                }

                VipService::addExp($referrerId, VipService::EXP_REFERRAL, 'referral', $referral->id, 'referral');

                // Multi-level commission (level 2: referrer of referrer)
                $parentReferral = Referral::where('referred_id', $referrerId)->first();
                if ($parentReferral && $parentReferral->referrer_id !== $userId) {
                    $level2Bonus = PlatformConfig::get('referral', 'level2_bonus', '0');
                    $level2Rate = PlatformConfig::get('referral', 'level2_rate', '0.05');
                    $commission = bcmul($referrerBonus, $level2Rate, 4);

                    if (bccomp($commission, '0', 2) > 0) {
                        self::creditReferralBonus($parentReferral->referrer_id, $commission);
                        $c = new \app\model\ReferralCommission();
                        $c->id = $this->generateId();
                        $c->referral_id = $parentReferral->id;
                        $c->user_id = $parentReferral->referrer_id;
                        $c->level = 2;
                        $c->source_user_id = $referrerId;
                        $c->source_amount = $referrerBonus;
                        $c->commission_rate = $level2Rate;
                        $c->commission_amount = $commission;
                        $c->source_type = 'referral';
                        $c->source_id = $referral->id;
                        $c->created_at = date('Y-m-d H:i:s');
                        $c->save();
                    }
                }
            });
        } catch (\PDOException $e) {
            if (self::isDuplicateOnKey($e, 'uk_referred_id')) {
                return $this->fail(trans('You have already applied a referral code'), 422);
            }
            throw $e;
        }

        // emit 必须在 commit 之后：消费者（成就引擎等）按已提交数据行事，
        // 事务内发事件会在回滚后留下按未落库奖励算出的进度。
        EventBus::emit('referral.applied', ['referrer_id' => $referrerId, 'referred_id' => $userId]);

        return $this->success([
            'referrer_bonus' => $referrerBonus,
            'referred_bonus' => $referredBonus,
        ], 'Referral code applied successfully');
    }

    /**
     * 这次重复键冲突是不是「撞在指定唯一键上」。
     *
     * 成对判据，两半都要有：
     *  - 是 uk_referred_id ⇒ 并发双提交，与 alreadyReferred 预检同义 ⇒ 转 422 用户错误；
     *  - 不是（典型是流水表 snowflake 撞号，键名落在主键/其它键上）⇒ **原样上抛**。
     * 只按错误码 1062 一刀切会把系统性撞号伪装成「你已经用过推荐码」——用一句用户错误掩盖
     * 一个需要运维介入的故障，比不捕获更糟。
     */
    private static function isDuplicateOnKey(\Throwable $e, string $key): bool
    {
        if (!$e instanceof \PDOException) {
            return false; // 非 PDO 异常没有 errorInfo：不认，交给上层原样上抛
        }

        return in_array($e->errorInfo[1] ?? null, [1062, 23000], true)
            && str_contains($e->getMessage(), $key);
    }

    /**
     * 发推荐奖励：钱包写入不得静默失败。
     *
     * UserWallet::addBalance 失败时只返回 false、不抛异常，而此刻推荐关系已在同一事务里落库，
     * 重试又被 apply() 的 alreadyReferred 预检挡死 ⇒ 静默失败＝永久少发。
     * 转成异常让外层事务整体回滚（推荐关系也不留），用户可重试。
     * 与 ExchangeController / ActivityService::creditWallet 的既有口径一致：钱的每一步都看返回值。
     */
    private static function creditReferralBonus(int $userId, string $amount): void
    {
        if (!UserWallet::addBalance($userId, $amount, 'referral_bonus')) {
            throw new \RuntimeException("Referral bonus credit failed: user={$userId} amount={$amount}");
        }
    }
}
