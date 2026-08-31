<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\User;
use app\service\VerificationService;
use support\Request;
use support\Response;

class VerificationController extends BaseController
{
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
