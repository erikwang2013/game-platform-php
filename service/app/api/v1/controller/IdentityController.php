<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\UserIdentity;
use support\Request;
use support\Response;

/**
 * C端 - 实名认证
 *
 * @Apidoc\Title("实名认证")
 * @Apidoc\Group("user")
 */
class IdentityController extends BaseController
{
    /**
     * @Apidoc\Title("认证状态查询")
     * @Apidoc\Url("/api/user/identity/status")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function status(Request $request): Response
    {
        $identity = UserIdentity::where('user_id', $request->userId)->first();

        if (!$identity) {
            return $this->success([
                'status' => 'not_submitted',
            ]);
        }

        return $this->success([
            'status'       => $identity->status,
            'real_name'    => $this->maskName($identity->real_name),
            'id_type'      => $identity->id_type,
            'review_note'  => $identity->review_note,
            'submitted_at' => $identity->created_at ? $identity->created_at->format('Y-m-d H:i:s') : null,
            'reviewed_at'  => $identity->reviewed_at ? $identity->reviewed_at->format('Y-m-d H:i:s') : null,
        ]);
    }

    /**
     * @Apidoc\Title("提交/重新提交认证")
     * @Apidoc\Url("/api/user/identity/apply")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name:"real_name",type:"string",require:true,desc:"真实姓名")
     * @Apidoc\Param(name:"id_type",type:"string",require:true,desc:"证件类型(id_card/passport/driver_license)")
     * @Apidoc\Param(name:"id_number",type:"string",require:true,desc:"证件号码")
     * @Apidoc\Param(name:"id_front_photo",type:"string",require:true,desc:"证件正面照片")
     * @Apidoc\Param(name:"id_back_photo",type:"string",require:false,desc:"证件背面照片")
     * @Apidoc\Param(name:"selfie_photo",type:"string",require:true,desc:"自拍照片")
     * @Apidoc\Param(name:"country",type:"string",require:false,desc:"国家代码")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function apply(Request $request): Response
    {
        $validator = validator($request->all(), [
            'real_name'       => 'required|string|max:100',
            'id_type'         => 'required|string|in:id_card,passport,driver_license',
            'id_number'       => 'required|string',
            'id_front_photo'  => 'required|string',
            'id_back_photo'   => 'nullable|string',
            'selfie_photo'    => 'required|string',
            'country'         => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // Check if already submitted and still pending or approved
        $existing = UserIdentity::where('user_id', $request->userId)->first();

        if ($existing && in_array($existing->status, ['pending', 'approved'], true)) {
            return $this->fail('You already have a pending or approved KYC submission', 422);
        }

        $now = date('Y-m-d H:i:s');

        if ($existing && $existing->status === 'rejected') {
            // Re-submission: update the existing record
            $existing->real_name       = $request->input('real_name');
            $existing->id_type         = $request->input('id_type');
            $existing->id_number       = $request->input('id_number');
            $existing->id_front_photo  = $request->input('id_front_photo');
            $existing->id_back_photo   = $request->input('id_back_photo', '');
            $existing->selfie_photo    = $request->input('selfie_photo');
            $existing->country         = $request->input('country', '');
            $existing->status          = 'pending';
            $existing->reviewer_id     = null;
            $existing->review_note     = '';
            $existing->reviewed_at     = null;
            $existing->updated_at      = $now;
            $existing->save();
        } else {
            // New submission
            $identity = new UserIdentity();
            $identity->id              = $this->generateId();
            $identity->user_id         = $request->userId;
            $identity->real_name       = $request->input('real_name');
            $identity->id_type         = $request->input('id_type');
            $identity->id_number       = $request->input('id_number');
            $identity->id_front_photo  = $request->input('id_front_photo');
            $identity->id_back_photo   = $request->input('id_back_photo', '');
            $identity->selfie_photo    = $request->input('selfie_photo');
            $identity->country         = $request->input('country', '');
            $identity->status          = 'pending';
            $identity->created_at      = $now;
            $identity->updated_at      = $now;
            $identity->save();
        }

        return $this->success([], 'KYC submitted successfully');
    }

    /**
     * Mask a real name for privacy.
     * Examples:
     *   "Zhang San"   -> "Z*** S**"
     *   "张三"         -> "张*"
     *   "A"           -> "*"
     */
    private function maskName(?string $name): string
    {
        if (empty($name)) {
            return '';
        }

        $name  = trim($name);
        $len   = mb_strlen($name);
        $parts = explode(' ', $name);

        if (count($parts) > 1) {
            // Multi-word name (e.g., "Zhang San")
            $masked = array_map(function (string $part): string {
                return $this->maskSinglePart($part);
            }, $parts);
            return implode(' ', $masked);
        }

        return $this->maskSinglePart($name);
    }

    /**
     * Mask a single part of a name: first character + asterisks.
     */
    private function maskSinglePart(string $part): string
    {
        $len = mb_strlen($part);

        if ($len <= 1) {
            return '*';
        }

        $first = mb_substr($part, 0, 1);
        return $first . str_repeat('*', $len - 1);
    }
}
