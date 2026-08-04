<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\service;
use app\model\DeviceToken;

class PushService
{
    public static function send(int $userId, string $title, string $body, array $data = []): void
    {
        try {
            $tokens = DeviceToken::where('user_id', $userId)->get();
            foreach ($tokens as $token) {
                match ($token->platform) {
                    'fcm' => self::sendFcm($token->token, $title, $body, $data),
                    'apns' => self::sendApns($token->token, $title, $body, $data),
                    'harmonyos' => self::sendHarmonyOS($token->token, $title, $body, $data),
                    default => null,
                };
            }
        } catch (\Throwable $e) {
            // Push failure must not block main flow
        }
    }

    private static function sendFcm(string $token, string $title, string $body, array $data): void
    {
        $serverKey = getenv('FCM_SERVER_KEY', '');
        if (empty($serverKey)) return;
        (new \GuzzleHttp\Client(['timeout' => 5]))->post('https://fcm.googleapis.com/fcm/send', [
            'headers' => ['Authorization' => 'key=' . $serverKey, 'Content-Type' => 'application/json'],
            'json' => ['to' => $token, 'notification' => ['title' => $title, 'body' => $body], 'data' => $data],
        ]);
    }

    private static function sendApns(string $token, string $title, string $body, array $data): void {}
    private static function sendHarmonyOS(string $token, string $title, string $body, array $data): void {}
}
