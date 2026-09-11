<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\User;
use app\service\VerificationService;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

#[Apidoc\Title("邮箱/手机验证")]
#[Apidoc\Group("verify")]
class VerificationController extends BaseController
{
    #[Apidoc\Title("发送邮箱验证码")]
    #[Apidoc\Url("/api/v1/verify/send-email")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "email", type: "string", require: true, desc: "邮箱地址")]
    public function sendEmail(Request $request): Response
    {
        $email = $request->input('email', '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Invalid email', 422);
        }

        $result = VerificationService::sendEmail($email, $request->userId);
        if (!$result['success']) {
            return $this->fail($result['message'], 429);
        }

        return $this->success([], $result['message']);
    }

    #[Apidoc\Title("确认邮箱验证码")]
    #[Apidoc\Url("/api/v1/verify/confirm-email")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "code", type: "string", require: true, desc: "6 位邮箱验证码")]
    public function confirmEmail(Request $request): Response
    {
        $code = $request->input('code', '');
        if (strlen($code) !== 6) {
            return $this->fail('Invalid code', 422);
        }

        if (!VerificationService::verifyEmail($request->userId, $code)) {
            return $this->fail('Invalid or expired code', 422);
        }

        User::where('id', $request->userId)->update(['email_verified_at' => date('Y-m-d H:i:s')]);

        return $this->success([], 'Email verified');
    }

    #[Apidoc\Title("发送短信验证码")]
    #[Apidoc\Url("/api/v1/verify/send-sms")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "phone", type: "string", require: true, desc: "手机号")]
    public function sendSms(Request $request): Response
    {
        $phone = $request->input('phone', '');
        if (empty($phone)) {
            return $this->fail('Phone required', 422);
        }

        $result = VerificationService::sendSms($phone, $request->userId);
        if (!$result['success']) {
            return $this->fail($result['message'], 429);
        }

        return $this->success([], $result['message']);
    }

    #[Apidoc\Title("确认手机验证码")]
    #[Apidoc\Url("/api/v1/verify/confirm-phone")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "code", type: "string", require: true, desc: "6 位短信验证码")]
    public function confirmPhone(Request $request): Response
    {
        $code = $request->input('code', '');
        if (strlen($code) !== 6) {
            return $this->fail('Invalid code', 422);
        }

        if (!VerificationService::verifySms($request->userId, $code)) {
            return $this->fail('Invalid or expired code', 422);
        }

        User::where('id', $request->userId)->update(['phone_verified_at' => date('Y-m-d H:i:s')]);

        return $this->success([], 'Phone verified');
    }
}
