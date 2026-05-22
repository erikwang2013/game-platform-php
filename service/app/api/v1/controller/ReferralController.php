<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\model\PlatformConfig;
use app\model\Referral;
use app\model\ReferralReward;
use app\model\Transaction;
use app\model\UserWallet;
use app\service\NotificationService;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("推荐管理")
 * @Apidoc\Group("referral")
 */
class ReferralController extends BaseController
{
    /**
     * @Apidoc\Title("我的推荐码")
     * @Apidoc\Url("/api/referral/my-code")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
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

    /**
     * @Apidoc\Title("推荐统计")
     * @Apidoc\Url("/api/referral/stats")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
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

    /**
     * @Apidoc\Title("使用推荐码")
     * @Apidoc\Url("/api/referral/apply")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="code", type="string", require=true, desc="推荐码")
     */
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
            return $this->fail('Invalid referral code', 404);
        }

        $referrerId = $referrerRecord->referrer_id;

        // Cannot refer yourself
        if ($referrerId === $userId) {
            return $this->fail('Cannot use your own referral code', 422);
        }

        // Check if this user has already been referred
        $alreadyReferred = Referral::where('referred_id', $userId)->exists();
        if ($alreadyReferred) {
            return $this->fail('You have already applied a referral code', 422);
        }

        // Read bonus amounts from platform config (defaults to 0)
        $referrerBonus = PlatformConfig::get('referral', 'referrer_bonus', '0');
        $referredBonus = PlatformConfig::get('referral', 'referred_bonus', '0');

        // Create the referral record for the referred user
        $referral = new Referral();
        $referral->id          = $this->generateId();
        $referral->referrer_id = $referrerId;
        $referral->referred_id = $userId;
        $referral->code        = $code;
        $referral->status      = 1;
        $referral->save();

        // Grant bonus to referrer
        if (bccomp($referrerBonus, '0', 2) > 0) {
            UserWallet::addBalance($referrerId, $referrerBonus);

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

            $wallet = UserWallet::where('user_id', $referrerId)->first();
            $transaction = new Transaction();
            $transaction->id            = $this->generateId();
            $transaction->user_id       = $referrerId;
            $transaction->type          = 'referral_bonus';
            $transaction->amount        = $referrerBonus;
            $transaction->balance_after = $wallet ? $wallet->balance : '0';
            $transaction->ref_type      = 'referral';
            $transaction->ref_id        = $referral->id;
            $transaction->remark        = 'Referral bonus for inviting user #' . $userId;
            $transaction->save();

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
            UserWallet::addBalance($userId, $referredBonus);

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

            $wallet = UserWallet::where('user_id', $userId)->first();
            $transaction = new Transaction();
            $transaction->id            = $this->generateId();
            $transaction->user_id       = $userId;
            $transaction->type          = 'referral_bonus';
            $transaction->amount        = $referredBonus;
            $transaction->balance_after = $wallet ? $wallet->balance : '0';
            $transaction->ref_type      = 'referral';
            $transaction->ref_id        = $referral->id;
            $transaction->remark        = 'Signup bonus from referral code: ' . $code;
            $transaction->save();

            NotificationService::send(
                $userId,
                'referral',
                'Welcome Bonus',
                "You received {$referredBonus} platform tokens as a signup referral bonus.",
                'referral',
                $referral->id
            );
        }

        return $this->success([
            'referrer_bonus' => $referrerBonus,
            'referred_bonus' => $referredBonus,
        ], 'Referral code applied successfully');
    }
}
