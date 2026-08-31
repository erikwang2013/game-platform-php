<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\service;
use common\model\DeviceToken;
use common\CircuitBreaker;
use common\service\FeatureFlag;
use common\Retry;
use support\Log;
use support\Redis;

class PushService
{
    public static function send(int $userId, string $title, string $body, array $data = []): void
    {
        try {
            if (FeatureFlag::isEnabled('provider_mock')) {
                Log::warning("PushService mock mode: skip push to user {$userId}");
                return;
            }
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
        $serviceAccount = getenv('FCM_SERVICE_ACCOUNT_JSON');
        if (!empty($serviceAccount)) {
            self::sendFcmV1($token, $title, $body, $data, $serviceAccount);
            return;
        }

        $serverKey = getenv('FCM_SERVER_KEY');
        if (empty($serverKey)) {
            return;
        }

        Retry::run(function () use ($serverKey, $token, $title, $body, $data) {
            CircuitBreaker::call('push:fcm', function () use ($serverKey, $token, $title, $body, $data) {
                (new \GuzzleHttp\Client(['timeout' => 5]))->post('https://fcm.googleapis.com/fcm/send', [
                    'headers' => ['Authorization' => 'key=' . $serverKey, 'Content-Type' => 'application/json'],
                    'json' => ['to' => $token, 'notification' => ['title' => $title, 'body' => $body], 'data' => $data],
                ]);
            });
        });
    }

    private static function sendFcmV1(string $token, string $title, string $body, array $data, string $serviceAccount): void
    {
        $sa = json_decode($serviceAccount, true);
        if (!$sa || empty($sa['client_email']) || empty($sa['private_key']) || empty($sa['project_id'])) {
            return;
        }

        $accessToken = self::getFcmOAuthToken($sa);
        if (empty($accessToken)) {
            return;
        }

        $url = 'https://fcm.googleapis.com/v1/projects/' . $sa['project_id'] . '/messages:send';
        Retry::run(function () use ($url, $accessToken, $token, $title, $body, $data) {
            CircuitBreaker::call('push:fcm', function () use ($url, $accessToken, $token, $title, $body, $data) {
                (new \GuzzleHttp\Client(['timeout' => 5]))->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                ],
            ],
        ]);
            });
        });
    }

    private static function getFcmOAuthToken(array $sa): string
    {
        $cached = Redis::get('push:fcm:token');
        if ($cached) {
            return $cached;
        }

        $header = self::base64urlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now = time();
        $claims = self::base64urlEncode(json_encode([
            'iss' => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';
        openssl_sign($header . '.' . $claims, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256);
        $assertion = $header . '.' . $claims . '.' . self::base64urlEncode($signature);

        $client = new \GuzzleHttp\Client(['timeout' => 10]);
        $response = $client->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);
        $token = $body['access_token'] ?? '';
        if (!empty($token)) {
            Redis::setex('push:fcm:token', 3000, $token);
        }
        return $token;
    }

    private static function sendApns(string $token, string $title, string $body, array $data): void
    {
        $keyId = getenv('APNS_KEY_ID');
        $teamId = getenv('APNS_TEAM_ID');
        $keyFile = getenv('APNS_KEY_FILE');
        $topic = getenv('APNS_TOPIC');

        if (empty($keyId) || empty($teamId) || empty($keyFile) || empty($topic)) {
            return;
        }
        if (!file_exists($keyFile)) {
            return;
        }

        $mode = getenv('APNS_MODE', 'sandbox');
        $host = $mode === 'production' ? 'api.push.apple.com' : 'api.sandbox.push.apple.com';

        $header = self::base64urlEncode(json_encode(['alg' => 'ES256', 'kid' => $keyId]));
        $claims = self::base64urlEncode(json_encode(['iss' => $teamId, 'iat' => time()]));
        $p8key = file_get_contents($keyFile);
        $signature = '';
        openssl_sign($header . '.' . $claims, $signature, $p8key, OPENSSL_ALGO_SHA256);
        $jwt = $header . '.' . $claims . '.' . self::base64urlEncode($signature);

        Retry::run(function () use ($host, $token, $jwt, $topic, $title, $body, $data) {
            CircuitBreaker::call('push:apns', function () use ($host, $token, $jwt, $topic, $title, $body, $data) {
                (new \GuzzleHttp\Client(['timeout' => 5, 'curl' => [CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0]]))
                    ->post("https://{$host}/3/device/{$token}", [
                        'headers' => [
                            'authorization' => 'bearer ' . $jwt,
                            'apns-topic' => $topic,
                            'apns-push-type' => 'alert',
                            'content-type' => 'application/json',
                        ],
                        'json' => [
                            'aps' => [
                                'alert' => ['title' => $title, 'body' => $body],
                                'sound' => 'default',
                            ],
                            'data' => $data,
                        ],
                    ]);
            });
        });
    }

    private static function sendHarmonyOS(string $token, string $title, string $body, array $data): void
    {
        $appId = getenv('HUAWEI_APP_ID');
        $appSecret = getenv('HUAWEI_APP_SECRET');
        if (empty($appId) || empty($appSecret)) {
            return;
        }

        $accessToken = self::getHuaweiToken($appId, $appSecret);
        if (empty($accessToken)) {
            return;
        }

        Retry::run(function () use ($appId, $token, $title, $body, $data, $accessToken) {
            CircuitBreaker::call('push:harmonyos', function () use ($appId, $token, $title, $body, $data, $accessToken) {
                (new \GuzzleHttp\Client(['timeout' => 5]))->post(
                    "https://push-api.cloud.huawei.com/v1/{$appId}/messages:send",
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'message' => [
                                'token' => [$token],
                                'notification' => ['title' => $title, 'body' => $body],
                                'data' => json_encode($data),
                            ],
                        ],
                    ]
                );
            });
        });
    }

    private static function getHuaweiToken(string $appId, string $appSecret): string
    {
        $cached = Redis::get('push:huawei:token');
        if ($cached) {
            return $cached;
        }

        $client = new \GuzzleHttp\Client(['timeout' => 10]);
        $response = $client->post('https://oauth-login.cloud.huawei.com/oauth2/v3/token', [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => $appId,
                'client_secret' => $appSecret,
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);
        $token = $body['access_token'] ?? '';
        if (!empty($token)) {
            Redis::setex('push:huawei:token', 21600, $token);
        }
        return $token;
    }

    private static function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
