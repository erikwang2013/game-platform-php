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
 * C端 - 两步验证
 *
 * @Apidoc\Title("两步验证")
 * @Apidoc\Group("auth")
 */
class TwoFactorController extends BaseController
{
    /**
     * @Apidoc\Title("设置两步验证")
     * @Apidoc\Url("/api/user/2fa/setup")
     * @Apidoc\Method("POST")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function setup(Request $request): Response
    {
        $userId = $request->userId;

        // MVP: generate a new 2FA secret
        $secret = bin2hex(random_bytes(16));

        return $this->success([
            'secret'     => $secret,
            'qr_code_url' => 'otpauth://totp/GamePlatform:' . $userId . '?secret=' . $secret . '&issuer=GamePlatform',
        ]);
    }

    /**
     * @Apidoc\Title("启用两步验证")
     * @Apidoc\Url("/api/user/2fa/enable")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name:"code",type:"string",require:true,desc:"验证码")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function enable(Request $request): Response
    {
        $userId = $request->userId;

        $validator = validator($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $code = $request->input('code');

        // MVP: verify code (stub)
        return $this->success([], 'Two-factor authentication enabled');
    }

    /**
     * @Apidoc\Title("验证两步验证码")
     * @Apidoc\Url("/api/2fa/verify")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name:"user_id",type:"string",require:true,desc:"用户HashID")
     * @Apidoc\Param(name:"code",type:"string",require:true,desc:"验证码")
     */
    public function verify(Request $request): Response
    {
        $validator = validator($request->all(), [
            'user_id' => 'required|string',
            'code'    => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $userId = $this->decodeId($request->input('user_id'));
        $code   = $request->input('code');

        // MVP: verify code (stub)
        return $this->success(['verified' => true]);
    }

    /**
     * @Apidoc\Title("禁用两步验证")
     * @Apidoc\Url("/api/user/2fa/disable")
     * @Apidoc\Method("POST")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function disable(Request $request): Response
    {
        $userId = $request->userId;

        return $this->success([], 'Two-factor authentication disabled');
    }

    /**
     * @Apidoc\Title("两步验证状态")
     * @Apidoc\Url("/api/user/2fa/status")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function status(Request $request): Response
    {
        $userId = $request->userId;

        return $this->success([
            'enabled' => false,
            'method'  => 'totp',
        ]);
    }
}
