<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace common\service;
use common\model\WithdrawOrder;
use common\CircuitBreaker;
use support\Log;
use support\Redis;

final class PayoutService
{
    private const MAX_ATTEMPTS = 5;
    private const TOKEN_TTL = 3300;

    public static function execute(WithdrawOrder $order): array
    {
        if ($order->status !== 'approved') {
            throw new \RuntimeException('Order is not in approved status');
        }
        if ($order->payout_attempts >= self::MAX_ATTEMPTS) {
            throw new \RuntimeException('Max payout attempts exceeded');
        }

        if (FeatureFlag::isEnabled('provider_mock')) {
            Log::warning('PayoutService mock mode: skip PayPal payout, order ' . $order->order_no);
            self::markCompleted($order);
            return [
                'payout_batch_id' => 'mock-' . $order->order_no,
                'payout_item_id' => 'mock-' . $order->order_no,
                'payout_status' => 'success',
                'payout_attempts' => $order->payout_attempts + 1,
            ];
        }

        $attempt = $order->payout_attempts + 1;
        $batchId = $order->order_no . '-' . $attempt;
        $email = self::extractPaypalEmail($order);
        $amount = bccomp($order->fiat_amount, '0', 4) > 0 ? $order->fiat_amount : $order->platform_amount;
        $currency = $order->currency ?: 'USD';

        $accessToken = self::getAccessToken();
        $client = new \GuzzleHttp\Client(['timeout' => 15]);

        $response = CircuitBreaker::call('paypal', fn () => $client->post(self::baseUrl() . '/v1/payments/payouts', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'sender_batch_header' => [
                    'sender_batch_id' => $batchId,
                    'email_subject' => 'You have a payout',
                    'email_message' => 'You have received a payout from Game Platform.',
                ],
                'items' => [[
                    'recipient_type' => 'EMAIL',
                    'amount' => ['value' => $amount, 'currency' => $currency],
                    'receiver' => $email,
                    'note' => 'Withdrawal ' . $order->order_no,
                    'sender_item_id' => $order->order_no,
                ]],
            ],
        ]));

        $body = json_decode((string) $response->getBody(), true);
        $batchHeader = $body['batch_header'] ?? [];
        $item = $body['items'][0] ?? [];

        $order->payout_batch_id = $batchHeader['payout_batch_id'] ?? '';
        $order->payout_item_id = $item['payout_item_id'] ?? '';
        $order->payout_attempts = $attempt;

        $itemStatus = $item['transaction_status'] ?? ($item['payout_item']['transaction_status'] ?? '');

        if ($itemStatus === 'SUCCESS') {
            self::markCompleted($order);
        } elseif ($itemStatus === 'FAILED') {
            $order->payout_status = 'failed';
            $order->save();
        } else {
            $order->payout_status = 'processing';
            $order->save();
        }

        return [
            'payout_batch_id' => $order->payout_batch_id,
            'payout_item_id' => $order->payout_item_id,
            'payout_status' => $order->payout_status,
            'payout_attempts' => $order->payout_attempts,
        ];
    }

    public static function syncStatus(WithdrawOrder $order): string
    {
        if (empty($order->payout_batch_id)) {
            return $order->payout_status;
        }

        if (FeatureFlag::isEnabled('provider_mock')) {
            Log::warning('PayoutService mock mode: skip PayPal status check, order ' . $order->order_no);
            return 'success';
        }

        $accessToken = self::getAccessToken();
        $client = new \GuzzleHttp\Client(['timeout' => 10]);

        $response = CircuitBreaker::call('paypal', fn () => $client->get(self::baseUrl() . '/v1/payments/payouts/' . $order->payout_batch_id, [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
        ]));

        $body = json_decode((string) $response->getBody(), true);
        $batchStatus = $body['batch_header']['batch_status'] ?? '';

        if ($batchStatus === 'SUCCESS') {
            self::markCompleted($order);
            return 'success';
        }

        if ($batchStatus === 'DENIED' || $batchStatus === 'CANCELED') {
            $order->payout_status = 'failed';
            $order->save();
            return 'failed';
        }

        if (in_array($batchStatus, ['PENDING', 'PROCESSING'], true)) {
            $order->payout_status = 'processing';
            $order->save();
            return 'processing';
        }

        return $order->payout_status;
    }

    public static function getAccessToken(): string
    {
        try {
            $cached = Redis::get('paypal:token');
            if ($cached) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // 缓存不可用可降级直连 PayPal，但必须告警
            Log::warning('PayPal token Redis get failed, fetching fresh: ' . $e->getMessage());
        }

        $clientId = getenv('PAYPAL_CLIENT_ID');
        $clientSecret = getenv('PAYPAL_CLIENT_SECRET');

        if (empty($clientId) || empty($clientSecret)) {
            throw new \RuntimeException('PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET must be configured');
        }

        $client = new \GuzzleHttp\Client(['timeout' => 10]);
        $response = CircuitBreaker::call('paypal', fn () => $client->post(self::baseUrl() . '/v1/oauth2/token', [
            'auth' => [$clientId, $clientSecret],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]));

        $body = json_decode((string) $response->getBody(), true);
        $token = $body['access_token'] ?? '';
        if (empty($token)) {
            throw new \RuntimeException('Failed to obtain PayPal access token');
        }

        try {
            Redis::setex('paypal:token', self::TOKEN_TTL, $token);
        } catch (\Throwable $e) {
            Log::warning('PayPal token Redis setex failed (token still returned): ' . $e->getMessage());
        }
        return $token;
    }

    public static function markCompleted(WithdrawOrder $order): void
    {
        // 幂等：已完成的订单不重复标记，避免 syncStatus 轮询重复发通知
        if ($order->status === 'completed') {
            return;
        }

        $order->status = 'completed';
        $order->payout_status = 'success';
        $order->paid_at = date('Y-m-d H:i:s');
        $order->save();

        // 真正打款完成才发 completed 事件（申请时发的是 withdraw.applied）。
        // eventId 与 Monitor 对账巡检 SQL 的 CONCAT('withdraw_', wo.id, '_completed') 对应。
        EventPublisher::push('withdraw.completed', "withdraw_{$order->id}_completed", [
            'user_id' => $order->user_id,
            'platform_amount' => $order->platform_amount,
            'status' => 'completed',
        ]);

        NotificationService::send(
            $order->user_id,
            'withdraw',
            'Withdrawal Completed',
            "Your withdrawal of {$order->platform_amount} platform tokens has been sent to your account.",
            'withdraw',
            $order->id
        );
    }

    private static function baseUrl(): string
    {
        $mode = getenv('PAYPAL_MODE', 'sandbox');
        return $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private static function extractPaypalEmail(WithdrawOrder $order): string
    {
        $info = json_decode($order->account_info, true);
        if (is_array($info)) {
            return $info['paypal_email'] ?? $info['email'] ?? '';
        }
        if (str_contains($order->account_info, '@') && str_contains($order->account_info, '.')) {
            return $order->account_info;
        }
        throw new \RuntimeException('Cannot extract PayPal email from account_info');
    }
}
