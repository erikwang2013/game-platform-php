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
 * @Apidoc\Title("Identity")
 * @Apidoc\Group("user")
 */
class IdentityController extends BaseController
{
    /**
     * @Apidoc\Title("Identity Status")
     * @Apidoc\Url("/api/user/identity/status")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function status(Request $request): Response
    {
        $userId = $request->userId;

        $identity = UserIdentity::where('user_id', $userId)->first();

        if (!$identity) {
            return $this->success([
                'status'      => 'not_submitted',
                'real_name'   => null,
                'id_type'     => null,
                'verified_at' => null,
            ]);
        }

        // Mask real name: show first character and last character, mask middle
        $maskedName = $this->maskName($identity->real_name ?? '');

        return $this->success([
            'status'          => $identity->status,
            'real_name'       => $maskedName,
            'id_type'         => $identity->id_type,
            'id_front_photo'  => $identity->id_front_photo,
            'id_back_photo'   => $identity->id_back_photo,
            'selfie_photo'    => $identity->selfie_photo,
            'verified_at'     => $identity->verified_at,
            'remark'          => $identity->remark,
        ]);
    }

    /**
     * @Apidoc\Title("Identity Apply")
     * @Apidoc\Url("/api/user/identity/apply")
     * @Apidoc\Method("POST")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     * @Apidoc\Param(name:"real_name",type:"string",require:true,desc:"Full real name")
     * @Apidoc\Param(name:"id_type",type:"string",require:true,desc:"ID type (id_card, passport, driver_license)")
     * @Apidoc\Param(name:"id_number",type:"string",require:true,desc:"ID number")
     * @Apidoc\Param(name:"id_front_photo",type:"string",require:true,desc:"Front side of ID photo URL")
     * @Apidoc\Param(name:"id_back_photo",type:"string",require:false,desc:"Back side of ID photo URL")
     * @Apidoc\Param(name:"selfie_photo",type:"string",require:true,desc:"Selfie photo URL")
     */
    public function apply(Request $request): Response
    {
        $userId = $request->userId;

        $validator = validator($request->all(), [
            'real_name'      => 'required|string|min:1|max:100',
            'id_type'        => 'required|in:id_card,passport,driver_license',
            'id_number'      => 'required|string|min:5|max:50',
            'id_front_photo' => 'required|string|max:500',
            'id_back_photo'  => 'nullable|string|max:500',
            'selfie_photo'   => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $realName     = $request->input('real_name');
        $idType       = $request->input('id_type');
        $idNumber     = $request->input('id_number');
        $idFrontPhoto = $request->input('id_front_photo');
        $idBackPhoto  = $request->input('id_back_photo');
        $selfiePhoto  = $request->input('selfie_photo');

        // Check if there's a pending or verified identity
        $existing = UserIdentity::where('user_id', $userId)->first();
        if ($existing && in_array($existing->status, ['pending', 'verified'])) {
            return $this->fail(
                $existing->status === 'pending'
                    ? 'Your identity verification is under review. Please wait.'
                    : 'Your identity has already been verified.',
                422
            );
        }

        if ($existing) {
            // Update existing rejected record
            $existing->real_name      = $realName;
            $existing->id_type        = $idType;
            $existing->id_number      = $idNumber;
            $existing->id_front_photo = $idFrontPhoto;
            $existing->id_back_photo  = $idBackPhoto;
            $existing->selfie_photo   = $selfiePhoto;
            $existing->status         = 'pending';
            $existing->remark         = null;
            $existing->save();
        } else {
            // Create new identity record
            $identity = new UserIdentity();
            $identity->id             = $this->generateId();
            $identity->user_id        = $userId;
            $identity->real_name      = $realName;
            $identity->id_type        = $idType;
            $identity->id_number      = $idNumber;
            $identity->id_front_photo = $idFrontPhoto;
            $identity->id_back_photo  = $idBackPhoto;
            $identity->selfie_photo   = $selfiePhoto;
            $identity->status         = 'pending';
            $identity->save();
        }

        return $this->success([
            'status' => 'pending',
        ], 'Identity verification submitted successfully. Please wait for review.');
    }

    /**
     * Mask a name: show first and last character, mask the rest.
     * E.g., "Zhang San" → "Z*** S**"
     */
    private function maskName(string $name): string
    {
        if (empty($name)) {
            return '';
        }

        $parts = explode(' ', $name);
        $masked = [];

        foreach ($parts as $part) {
            $len = mb_strlen($part);
            if ($len <= 2) {
                $masked[] = mb_substr($part, 0, 1) . str_repeat('*', max(0, $len - 1));
            } else {
                $masked[] = mb_substr($part, 0, 1) . str_repeat('*', $len - 2) . mb_substr($part, -1);
            }
        }

        return implode(' ', $masked);
    }
}
