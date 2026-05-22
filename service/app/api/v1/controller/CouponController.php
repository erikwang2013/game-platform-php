<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * C端 - 优惠券
 *
 * @Apidoc\Title("优惠券")
 * @Apidoc\Group("coupon")
 */
class CouponController extends BaseController
{
    /**
     * @Apidoc\Title("可用优惠券列表")
     * @Apidoc\Url("/api/coupon/available")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function available(Request $request): Response
    {
        $userId = $request->userId;

        return $this->success(['list' => []]);
    }

    /**
     * @Apidoc\Title("领取优惠券")
     * @Apidoc\Url("/api/coupon/claim")
     * @Apidoc\Method("POST")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
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

        return $this->success(['claimed' => true], 'Coupon claimed successfully');
    }

    /**
     * @Apidoc\Title("我的优惠券")
     * @Apidoc\Url("/api/coupon/my")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function my(Request $request): Response
    {
        $userId = $request->userId;

        return $this->success(['list' => []]);
    }
}
