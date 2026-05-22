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
 * C端 - 推荐
 *
 * @Apidoc\Title("推荐")
 * @Apidoc\Group("referral")
 */
class ReferralController extends BaseController
{
    /**
     * @Apidoc\Title("我的推荐码")
     * @Apidoc\Url("/api/referral/my-code")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function myCode(Request $request): Response
    {
        $userId = $request->userId;

        return $this->success([
            'code'       => 'REF' . strtoupper(substr(hash('sha256', (string) $userId), 0, 8)),
            'link'       => 'https://platform.example.com/ref?code=REF' . strtoupper(substr(hash('sha256', (string) $userId), 0, 8)),
            'total_refs' => 0,
        ]);
    }

    /**
     * @Apidoc\Title("推荐数据")
     * @Apidoc\Url("/api/referral/stats")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function stats(Request $request): Response
    {
        $userId = $request->userId;

        return $this->success([
            'total_referrals'     => 0,
            'active_referrals'    => 0,
            'total_commission'    => '0.0000',
            'pending_commission'  => '0.0000',
        ]);
    }

    /**
     * @Apidoc\Title("应用推荐码")
     * @Apidoc\Url("/api/referral/apply")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name:"referral_code",type:"string",require:true,desc:"推荐码")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function apply(Request $request): Response
    {
        $userId = $request->userId;

        $validator = validator($request->all(), [
            'referral_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $referralCode = $request->input('referral_code');

        return $this->success([], 'Referral code applied');
    }
}
