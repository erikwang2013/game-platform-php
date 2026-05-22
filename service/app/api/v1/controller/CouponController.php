<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\Coupon;
use common\model\UserCoupon;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("Coupon")
 * @Apidoc\Group("coupon")
 */
class CouponController extends BaseController
{
    /**
     * @Apidoc\Title("Available Coupons")
     * @Apidoc\Url("/api/coupon/available")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function available(Request $request): Response
    {
        $userId = $request->userId;
        $now    = date('Y-m-d H:i:s');

        // Get all enabled coupons that are within validity period and have remaining quantity
        $coupons = Coupon::where('status', 1)
            ->where('start_at', '<=', $now)
            ->where('end_at', '>=', $now)
            ->whereRaw('used_qty < total_qty')
            ->orderBy('id', 'desc')
            ->get();

        $items = [];
        foreach ($coupons as $coupon) {
            // Check how many of this coupon the user has already claimed
            $userClaimedCount = UserCoupon::where('user_id', $userId)
                ->where('coupon_id', $coupon->id)
                ->count();

            // Skip if user has claimed max (default max=1 per coupon, or defined by max_qty field)
            $maxQtyPerUser = $coupon->max_per_user ?? 1;
            if ($userClaimedCount >= $maxQtyPerUser) {
                continue;
            }

            $items[] = [
                'id'          => $this->encodeId($coupon->id),
                'name'        => $coupon->name,
                'type'        => $coupon->type,
                'amount'      => $coupon->amount,
                'min_amount'  => $coupon->min_amount,
                'description' => $coupon->description,
                'start_at'    => $coupon->start_at,
                'end_at'      => $coupon->end_at,
                'remaining'   => max(0, (int) $coupon->total_qty - (int) $coupon->used_qty),
            ];
        }

        return $this->success(['items' => $items]);
    }

    /**
     * @Apidoc\Title("Claim Coupon")
     * @Apidoc\Url("/api/coupon/claim")
     * @Apidoc\Method("POST")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     * @Apidoc\Param(name:"coupon_id",type:"string",require:true,desc:"Coupon hashid")
     */
    public function claim(Request $request): Response
    {
        $userId = $request->userId;

        $validator = validator($request->all(), [
            'coupon_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $couponId = $this->decodeId($request->input('coupon_id'));
        $now = date('Y-m-d H:i:s');

        // Find coupon
        $coupon = Coupon::find($couponId);
        if (!$coupon || (int) $coupon->status !== 1) {
            return $this->fail('Coupon not found', 404);
        }

        // Check validity period
        if ($coupon->start_at > $now) {
            return $this->fail('This coupon is not yet available', 422);
        }
        if ($coupon->end_at < $now) {
            return $this->fail('This coupon has expired', 422);
        }

        // Check remaining quantity
        if ((int) $coupon->used_qty >= (int) $coupon->total_qty) {
            return $this->fail('This coupon has been fully claimed', 422);
        }

        // Check per-user limit
        $maxQtyPerUser = $coupon->max_per_user ?? 1;
        $userClaimedCount = UserCoupon::where('user_id', $userId)
            ->where('coupon_id', $couponId)
            ->count();
        if ($userClaimedCount >= $maxQtyPerUser) {
            return $this->fail('You have already claimed this coupon the maximum number of times', 422);
        }

        // Atomic increment used_qty
        $affected = Coupon::where('id', $couponId)
            ->where('used_qty', '<', $coupon->total_qty)
            ->increment('used_qty');

        if ($affected === 0) {
            return $this->fail('This coupon has been fully claimed', 422);
        }

        // Create user coupon record
        $userCoupon = new UserCoupon();
        $userCoupon->id         = $this->generateId();
        $userCoupon->user_id    = $userId;
        $userCoupon->coupon_id  = $couponId;
        $userCoupon->status     = 'unused';
        $userCoupon->expires_at = $coupon->end_at;
        $userCoupon->save();

        return $this->success([
            'id'         => $this->encodeId($userCoupon->id),
            'coupon_id'  => $this->encodeId($coupon->id),
            'name'       => $coupon->name,
            'amount'     => $coupon->amount,
            'expires_at' => $coupon->end_at,
        ], 'Coupon claimed successfully');
    }

    /**
     * @Apidoc\Title("My Coupons")
     * @Apidoc\Url("/api/coupon/my")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     * @Apidoc\Query(name:"page",type:"integer",require:false,desc:"Page number")
     * @Apidoc\Query(name:"per_page",type:"integer",require:false,desc:"Items per page")
     * @Apidoc\Query(name:"status",type:"string",require:false,desc:"Status filter (unused, used, expired)")
     */
    public function my(Request $request): Response
    {
        $userId  = $request->userId;
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);
        $status  = $request->input('status');

        $query = UserCoupon::where('user_id', $userId)
            ->with('coupon')
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($paginator->items() as $userCoupon) {
            $couponInfo = null;
            if ($userCoupon->coupon) {
                $couponInfo = [
                    'id'          => $this->encodeId($userCoupon->coupon->id),
                    'name'        => $userCoupon->coupon->name,
                    'type'        => $userCoupon->coupon->type,
                    'amount'      => $userCoupon->coupon->amount,
                    'min_amount'  => $userCoupon->coupon->min_amount,
                    'description' => $userCoupon->coupon->description,
                ];
            }

            $items[] = [
                'id'         => $this->encodeId($userCoupon->id),
                'coupon'     => $couponInfo,
                'status'     => $userCoupon->status,
                'used_at'    => $userCoupon->used_at,
                'expires_at' => $userCoupon->expires_at,
                'created_at' => $userCoupon->created_at,
            ];
        }

        return $this->success([
            'items'     => $items,
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'per_page'  => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
