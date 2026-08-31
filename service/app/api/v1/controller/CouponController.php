<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\Coupon;
use common\model\UserCoupon;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("优惠券")
 * @Apidoc\Group("coupon")
 */
class CouponController extends BaseController
{
    /**
     * @Apidoc\Title("可领优惠券")
     * @Apidoc\Url("/api/coupon/available")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function available(Request $request): Response
    {
        $now    = date('Y-m-d H:i:s');
        $userId = $request->userId;

        $coupons = Coupon::where('status', 1)
            ->where(function ($query) use ($now) {
                $query->whereNull('start_at')
                    ->orWhere('start_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', $now);
            })
            ->where(function ($query) {
                $query->where('total_qty', 0)
                    ->orWhereRaw('used_qty < total_qty');
            })
            ->orderBy('id', 'desc')
            ->get()
            ->filter(function ($coupon) use ($userId) {
                // Check user_limit: user hasn't already claimed max
                $userLimit = (int) $coupon->user_limit;
                if ($userLimit > 0) {
                    $claimed = UserCoupon::where('user_id', $userId)
                        ->where('coupon_id', $coupon->id)
                        ->count();
                    if ($claimed >= $userLimit) {
                        return false;
                    }
                }
                return true;
            })
            ->values()
            ->map(function ($coupon) {
                $data = $coupon->toArray();
                $data['id'] = $this->encodeId($coupon->id);
                if (!empty($coupon->game_id) && $coupon->game_id > 0) {
                    $data['game_id'] = $this->encodeId((int) $coupon->game_id);
                }
                return $data;
            });

        return $this->success($coupons);
    }

    /**
     * @Apidoc\Title("领取优惠券")
     * @Apidoc\Url("/api/coupon/claim")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="coupon_id", type="string", require=true, desc="优惠券ID")
     */
    public function claim(Request $request): Response
    {
        $validator = validator($request->all(), [
            'coupon_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $couponId = $this->decodeId($request->input('coupon_id'));
        $userId   = $request->userId;
        $now      = date('Y-m-d H:i:s');

        $coupon = Coupon::find($couponId);
        if (!$coupon) {
            return $this->fail('优惠券不存在', 404);
        }

        // Check status
        if ((int) $coupon->status !== 1) {
            return $this->fail('优惠券已禁用', 400);
        }

        // Check time range
        if ($coupon->start_at && $coupon->start_at > $now) {
            return $this->fail('优惠券尚未开始', 400);
        }
        if ($coupon->end_at && $coupon->end_at < $now) {
            return $this->fail('优惠券已过期', 400);
        }

        // Check user limit
        $userLimit = (int) $coupon->user_limit;
        if ($userLimit > 0) {
            $claimed = UserCoupon::where('user_id', $userId)
                ->where('coupon_id', $couponId)
                ->count();
            if ($claimed >= $userLimit) {
                return $this->fail('您已达到该优惠券的领取上限', 400);
            }
        }

        // Check conditions
        $conditions = json_decode($coupon->conditions ?? '{}', true) ?: [];
        if (!empty($conditions['min_deposit'])) {
            $totalDeposit = \common\model\DepositOrder::where('user_id', $userId)->where('status', 'confirmed')->sum('platform_amount') ?? '0';
            if (bccomp($totalDeposit, $conditions['min_deposit'], 4) < 0) {
                return $this->fail('Minimum deposit of ' . $conditions['min_deposit'] . ' not met', 400);
            }
        }
        if (!empty($conditions['first_user_only']) && $conditions['first_user_only']) {
            $hasDeposit = \common\model\DepositOrder::where('user_id', $userId)->where('status', 'confirmed')->exists();
            if ($hasDeposit) return $this->fail('This coupon is for new users only', 400);
        }
        if (!empty($conditions['game_id']) && $conditions['game_id'] > 0) {
            $gamePlayed = \common\model\GamePlayLog::where('user_id', $userId)->where('game_id', (int) $conditions['game_id'])->exists();
            if (!$gamePlayed) return $this->fail('Must play the required game first', 400);
        }

        // Atomic increment used_qty
        $affected = Coupon::where('id', $couponId)
            ->where(function ($query) {
                $query->where('total_qty', 0)
                    ->orWhereRaw('used_qty < total_qty');
            })
            ->increment('used_qty');

        if ($affected === 0) {
            return $this->fail('优惠券已被领完', 400);
        }

        // Refresh coupon data
        $coupon->refresh();

        // Create UserCoupon
        $userCoupon = new UserCoupon();
        $userCoupon->id        = $this->generateId();
        $userCoupon->user_id   = $userId;
        $userCoupon->coupon_id = $couponId;
        $userCoupon->status    = 'unused';
        $userCoupon->save();

        $data = $coupon->toArray();
        $data['id'] = $this->encodeId($coupon->id);
        if (!empty($coupon->game_id) && $coupon->game_id > 0) {
            $data['game_id'] = $this->encodeId((int) $coupon->game_id);
        }
        $data['user_coupon_id'] = $this->encodeId($userCoupon->id);

        return $this->success(['coupon' => $data], '领取成功');
    }

    /**
     * @Apidoc\Title("我的优惠券")
     * @Apidoc\Url("/api/coupon/my")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function my(Request $request): Response
    {
        $userId = $request->userId;
        $status = $request->input('status');

        $query = UserCoupon::where('user_id', $userId)
            ->with('coupon');

        if ($status && in_array($status, ['unused', 'used', 'expired'])) {
            $query->where('status', $status);
        }

        $userCoupons = $query->orderBy('id', 'desc')->get();

        $list = $userCoupons->map(function ($uc) {
            $data = $uc->toArray();
            $data['id'] = $this->encodeId($uc->id);
            if ($uc->coupon) {
                $couponData = $uc->coupon->toArray();
                $couponData['id'] = $this->encodeId($uc->coupon->id);
                if (!empty($uc->coupon->game_id) && $uc->coupon->game_id > 0) {
                    $couponData['game_id'] = $this->encodeId((int) $uc->coupon->game_id);
                }
                $data['coupon'] = $couponData;
            }
            return $data;
        });

        return $this->success($list);
    }
}
