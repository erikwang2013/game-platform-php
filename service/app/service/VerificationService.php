<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

use support\Redis;
use Throwable;

class VerificationService
{
    const CODE_TTL = 600;
    const RESEND_TTL = 60;
    const CODE_LENGTH = 6;

    public static function sendEmail(string $email, int $userId): array
    {
        $key = 'verify:email:' . $userId;
        try {
            if (Redis::exists($key . ':cooldown')) {
                return ['success' => false, 'message' => 'Please wait 60 seconds before resending'];
            }

            $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
            Redis::setex($key, self::CODE_TTL, $code);
            Redis::setex($key . ':cooldown', self::RESEND_TTL, '1');
        } catch (Throwable) {
            // Redis 不可用时 fail-closed：不发放验证码，避免用户收到无法核验的码
            return ['success' => false, 'message' => 'Verification service unavailable, please try again later'];
        }

        // Attempt email delivery via notification service
        \app\service\NotificationService::send(
            $userId, 'verification', 'Email Verification',
            "Your verification code is: {$code}. Valid for 10 minutes."
        );

        return ['success' => true, 'message' => 'Verification code sent'];
    }

    public static function sendSms(string $phone, int $userId): array
    {
        $key = 'verify:sms:' . $userId;
        try {
            if (Redis::exists($key . ':cooldown')) {
                return ['success' => false, 'message' => 'Please wait 60 seconds before resending'];
            }

            $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
            Redis::setex($key, self::CODE_TTL, $code);
            Redis::setex($key . ':cooldown', self::RESEND_TTL, '1');
        } catch (Throwable) {
            return ['success' => false, 'message' => 'Verification service unavailable, please try again later'];
        }

        return ['success' => true, 'message' => 'SMS code sent'];
    }

    public static function verifyEmail(int $userId, string $code): bool
    {
        $key = 'verify:email:' . $userId;
        try {
            $stored = Redis::get($key);
            if (!$stored || $stored !== $code) {
                return false;
            }
            Redis::del($key, $key . ':cooldown');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function verifySms(int $userId, string $code): bool
    {
        $key = 'verify:sms:' . $userId;
        try {
            $stored = Redis::get($key);
            if (!$stored || $stored !== $code) {
                return false;
            }
            Redis::del($key, $key . ':cooldown');
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
