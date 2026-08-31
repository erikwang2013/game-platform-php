<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

use common\model\DepositOrder;
use common\model\PaymentMethod;
use support\Request;

/**
 * Paytm Payments Gateway：initiate transaction 换取 txnToken（15 分钟有效），
 * 用户跳转 Paytm 托管支付页 showPaymentPage 完成支付（UPI/Card/NetBanking）。
 * 回调为表单 NVP + CHECKSUMHASH，签名采用官方方案：
 * salt(4 随机字符) + SHA256(参数串|salt) 拼接后再 AES-128-CBC(merchant key) 加密。
 */
class PaytmGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://securegw.paytm.in';

    private const CHECKSUM_IV = '@@@@&&&&####$$$$';

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $mid = getenv('PAYTM_MID') ?: '';
        $key = getenv('PAYTM_KEY') ?: '';
        if (!$mid || !$key) {
            throw new \RuntimeException('PAYTM_MID / PAYTM_KEY not configured');
        }
        $apiUrl  = rtrim(getenv('PAYTM_API_URL') ?: self::API_URL, '/');
        $siteUrl = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');

        $body = [
            'requestType' => 'Payment',
            'mid'         => $mid,
            'websiteName' => getenv('PAYTM_WEBSITE') ?: 'DEFAULT',
            'orderId'     => $order->order_no,
            'txnAmount'   => ['value' => (string) $order->amount, 'currency' => $order->currency],
            'userInfo'    => ['custId' => 'user_' . $order->user_id],
            'callbackUrl' => $siteUrl . '/api/payment/callback?provider=paytm',
        ];
        $json = json_encode($body, JSON_UNESCAPED_SLASHES);

        $resp = (new \GuzzleHttp\Client(['timeout' => 15]))->post(
            $apiUrl . '/v3/transaction/initiate?mid=' . urlencode($mid) . '&orderId=' . urlencode($order->order_no),
            [
                'headers' => ['Content-Type' => 'application/json', 'X-Checksum' => self::signBody($json, $key)],
                'body'    => $json,
            ]
        );
        $data = json_decode((string) $resp->getBody(), true);

        if (($data['resultInfo']['resultStatus'] ?? '') !== 'S' || empty($data['txnToken'])) {
            throw new \RuntimeException('Paytm initiate failed: ' . ($data['resultInfo']['resultMsg'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => $apiUrl . '/theia/api/v1/showPaymentPage?mid=' . urlencode($mid)
                . '&orderId=' . urlencode($order->order_no)
                . '&txnToken=' . urlencode((string) $data['txnToken']),
            'transaction_id' => $order->order_no,
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $key = getenv('PAYTM_KEY') ?: '';
        $params = [];
        parse_str($request->rawBody(), $params);
        // Fail closed: 未配置密钥或报文无校验和一律拒绝
        if (!$key || !is_array($params) || empty($params['CHECKSUMHASH'])) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $checksum = (string) $params['CHECKSUMHASH'];
        unset($params['CHECKSUMHASH']);
        if (!self::verifyNvp($params, $key, $checksum)) {
            return ['valid' => false, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'failed'];
        }

        $status = match ((string) ($params['STATUS'] ?? '')) {
            'TXN_SUCCESS' => 'success',
            'TXN_FAILURE' => 'failed',
            default       => 'ignored',
        };

        return [
            'valid'          => true,
            'order_no'       => (string) ($params['ORDERID'] ?? ''),
            'transaction_id' => (string) ($params['TXNID'] ?? ($params['BANKTXNID'] ?? '')),
            'amount'         => (string) ($params['TXNAMOUNT'] ?? ''),
            'status'         => $status,
        ];
    }

    /** 请求体 JSON 签名（官方 generateSignature(body, key)） */
    public static function signBody(string $body, string $key): string
    {
        return self::encryptChecksum($body, $key);
    }

    /** 回调 NVP 签名（官方 generateSignature(params, key)，ksort + | 连接） */
    public static function signNvp(array $params, string $key): string
    {
        return self::encryptChecksum(self::stringifyNvp($params), $key);
    }

    private static function encryptChecksum(string $string, string $key): string
    {
        $salt = self::randomSalt();
        return openssl_encrypt(
            hash('sha256', $string . '|' . $salt) . $salt,
            'AES-128-CBC',
            html_entity_decode($key),
            0,
            self::CHECKSUM_IV
        );
    }

    private static function verifyNvp(array $params, string $key, string $checksum): bool
    {
        $plain = openssl_decrypt($checksum, 'AES-128-CBC', html_entity_decode($key), 0, self::CHECKSUM_IV);
        if ($plain === false || strlen($plain) < 5) {
            return false;
        }
        $salt = substr($plain, -4);
        $expected = hash('sha256', self::stringifyNvp($params) . '|' . $salt) . $salt;
        return hash_equals($expected, $plain);
    }

    private static function stringifyNvp(array $params): string
    {
        ksort($params);
        return implode('|', array_map(
            fn ($value) => ($value !== null && strtolower((string) $value) !== 'null') ? (string) $value : '',
            $params
        ));
    }

    private static function randomSalt(): string
    {
        $chars = '9876543210ZYXWVUTSRQPONMLKJIHGFEDCBAabcdefghijklmnopqrstuvwxyz!@#$&_';
        $salt = '';
        for ($i = 0; $i < 4; $i++) {
            $salt .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $salt;
    }
}
