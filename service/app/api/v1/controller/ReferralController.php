<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\Referral;
use common\model\ReferralReward;
use common\model\Transaction;
use common\model\User;
use common\model\UserWallet;
use common\model\PlatformConfig;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("Referral")
 * @Apidoc\Group("referral")
 */
class ReferralController extends BaseController
{
    /**
     * @Apidoc\Title("My Referral Code")
     * @Apidoc\Url("/api/referral/my-code")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function myCode(Request $request): Response
    {
        $userId = $request->userId;

        // Find or generate referral code
        $code = $this->findOrGenerateCode($userId);

        // Get referral count (users who signed up using this code)
        $count = Referral::where('referrer_id', $userId)->count();

        // Get total rewards earned from referrals
        $totalRewards = ReferralReward::where('user_id', $userId)
            ->where('status', 1)
            ->sum('amount');

        return $this->success([
            'code'          => $code,
            'count'         => $count,
            'total_rewards' => (string) ($totalRewards ?: '0.0000'),
        ]);
    }

    /**
     * @Apidoc\Title("Referral Stats")
     * @Apidoc\Url("/api/referral/stats")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function stats(Request $request): Response
    {
        $userId = $request->userId;

        $count = Referral::where('referrer_id', $userId)->count();

        $totalRewards = ReferralReward::where('user_id', $userId)
            ->where('status', 1)
            ->sum('amount');

        // Get recent referrals
        $recentReferrals = Referral::where('referrer_id', $userId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $items = [];
        foreach ($recentReferrals as $ref) {
            $items[] = [
                'id'         => $this->encodeId($ref->id),
                'username'   => $ref->user->username ?? 'Unknown',
                'status'     => $ref->status,
                'created_at' => $ref->created_at,
            ];
        }

        return $this->success([
            'count'          => $count,
            'total_rewards'  => (string) ($totalRewards ?: '0.0000'),
            'recent'         => $items,
        ]);
    }

    /**
     * @Apidoc\Title("Apply Referral Code")
     * @Apidoc\Url("/api/referral/apply")
     * @Apidoc\Method("POST")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     * @Apidoc\Param(name:"code",type:"string",require:true,desc:"Referral code to apply")
     */
    public function apply(Request $request): Response
    {
        $userId = $request->userId;

        $validator = validator($request->all(), [
            'code' => 'required|string|min:4|max:16',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $code = strtoupper(trim($request->input('code')));

        // Check if user already has a referrer
        $existingReferral = Referral::where('user_id', $userId)->first();
        if ($existingReferral) {
            return $this->fail('You have already applied a referral code', 422);
        }

        // Find the code owner
        $codeOwner = Referral::where('code', $code)->first();
        if (!$codeOwner) {
            // Check if the code belongs to a user directly (generated code stored in User model or Referral)
            return $this->fail('Invalid referral code', 404);
        }

        $referrerId = $codeOwner->user_id;

        // Prevent self-referral
        if ((int) $referrerId === (int) $userId) {
            return $this->fail('You cannot use your own referral code', 422);
        }

        // Create referral record
        $referral = new Referral();
        $referral->id          = $this->generateId();
        $referral->user_id     = $userId;
        $referral->referrer_id = $referrerId;
        $referral->code        = $code;
        $referral->status      = 1;
        $referral->save();

        // Grant signup bonus to both parties
        $referrerBonus = PlatformConfig::get('referral', 'referrer_bonus', '1.0000');
        $refereeBonus  = PlatformConfig::get('referral', 'referee_bonus', '2.0000');

        // Credit referrer
        if (bccomp($referrerBonus, '0', 4) > 0) {
            $this->grantBonus($referrerId, $referrerBonus, 'referrer_bonus', $referral->id, $userId);
        }

        // Credit referee (the user who applied the code)
        if (bccomp($refereeBonus, '0', 4) > 0) {
            $this->grantBonus($userId, $refereeBonus, 'referee_bonus', $referral->id, $referrerId);
        }

        return $this->success([
            'referral_id' => $this->encodeId($referral->id),
            'code'        => $code,
        ], 'Referral code applied successfully');
    }

    /**
     * Find existing code or generate a new one for the user.
     */
    private function findOrGenerateCode(int $userId): string
    {
        $existing = Referral::where('user_id', $userId)->first();
        if ($existing && !empty($existing->code)) {
            return $existing->code;
        }

        // Generate a unique 8-char alphanumeric code
        $maxAttempts = 10;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = $this->generateCode();

            if (!Referral::where('code', $code)->exists()) {
                // Save the generated code for this user
                if ($existing) {
                    $existing->code = $code;
                    $existing->save();
                } else {
                    $referral = new Referral();
                    $referral->id      = $this->generateId();
                    $referral->user_id = $userId;
                    $referral->code    = $code;
                    $referral->status  = 0; // Not yet used as a referrer
                    $referral->save();
                }

                return $code;
            }
        }

        // Fallback: use a timestamp-based code
        $code = strtoupper(substr(md5((string) $userId . microtime()), 0, 8));
        return $code;
    }

    /**
     * Generate a random 8-char alphanumeric referral code.
     */
    private function generateCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }

    /**
     * Grant a referral bonus to a user.
     */
    private function grantBonus(int $userId, string $amount, string $type, int $referralId, int $relatedUserId): void
    {
        // Add balance
        UserWallet::addBalance($userId, $amount);

        // Create transaction record
        $transaction = new Transaction();
        $transaction->id            = $this->generateId();
        $transaction->user_id       = $userId;
        $transaction->type          = $type;
        $transaction->amount        = $amount;
        $transaction->balance_after = UserWallet::where('user_id', $userId)->value('balance') ?? '0.0000';
        $transaction->ref_type      = 'referral';
        $transaction->ref_id        = $referralId;
        $transaction->remark        = "Referral bonus from user #{$relatedUserId}";
        $transaction->save();

        // Create reward record
        $reward = new ReferralReward();
        $reward->id          = $this->generateId();
        $reward->referral_id = $referralId;
        $reward->user_id     = $userId;
        $reward->type        = $type;
        $reward->amount      = $amount;
        $reward->status      = 1;
        $reward->save();
    }
}
