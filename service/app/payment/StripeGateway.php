<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

use app\model\DepositOrder;
use app\model\PaymentMethod;
use support\Request;

/**
 * Stripe 支付网关：使用 Checkout Session（托管支付页，无需客户端 Stripe JS），
 * 经 payment_method_types 支持 alipay/wechat_pay/konbini/pix/upi/oxxo 等 125+ 支付方式。
 */
class StripeGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://api.stripe.com/v1';

    private const ZERO_DECIMAL_CURRENCIES = ['JPY', 'KRW'];

    public function createPayment(DepositOrder $order, PaymentMethod $method): array
    {
        $secret = getenv('STRIPE_SECRET_KEY') ?: '';
        if (!$secret) {
            throw new \RuntimeException('STRIPE_SECRET_KEY not configured');
        }

        $apmTypes = is_array($method->config['apm_types'] ?? null) ? $method->config['apm_types'] : ['card'];
        $siteUrl  = rtrim(getenv('SITE_URL') ?: 'https://example.com', '/');

        $params = [
            'mode'    => 'payment',
            'payment_method_types' => $apmTypes,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]'            => strtolower($order->currency),
            'line_items[0][price_data][unit_amount]'         => $this->toMinor($order->amount, $order->currency),
            'line_items[0][price_data][product_data][name]'  => 'Game deposit ' . $order->order_no,
            'metadata[order_no]' => $order->order_no,
            'success_url' => $siteUrl . '/payment/success?order_no=' . $order->order_no,
            'cancel_url'  => $siteUrl . '/payment/cancel?order_no=' . $order->order_no,
        ];

        $resp = (new \GuzzleHttp\Client(['timeout' => 15, 'auth' => [$secret, '']]))
            ->post(self::API_URL . '/checkout/sessions', ['form_params' => $params]);
        $data = json_decode((string) $resp->getBody(), true);

        if (empty($data['id']) || empty($data['url'])) {
            throw new \RuntimeException('Stripe checkout session failed: ' . ($data['error']['message'] ?? 'unknown'));
        }

        return [
            'checkout_url'   => (string) $data['url'],
            'transaction_id' => (string) $data['id'],
        ];
    }

    public function verifyCallback(Request $request): array
    {
        $data = json_decode($request->rawBody(), true);
        if (!is_array($data) || ($data['type'] ?? '') !== 'checkout.session.completed') {
            return ['valid' => true, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'ignored'];
        }

        $object = $data['data']['object'] ?? [];

        // 异步支付方式（alipay/wechat_pay/konbini/pix/upi 等）completed 事件可能尚未到账
        // （payment_status 为 unpaid/processing），此时确认会提前入账且后续 async_payment_succeeded
        // 会被 CAS 幂等丢弃。仅 payment_status=paid 才算成功，其余按 ignored 处理。
        if (($object['payment_status'] ?? '') !== 'paid') {
            return ['valid' => true, 'order_no' => '', 'transaction_id' => '', 'amount' => '', 'status' => 'ignored'];
        }

        $amount = $this->fromMinor((string) ($object['amount_total'] ?? '0'), (string) ($object['currency'] ?? ''));
        return [
            'valid'          => true,
            'order_no'       => (string) ($object['metadata']['order_no'] ?? ''),
            'transaction_id' => (string) ($object['id'] ?? ''),
            'amount'         => $amount,
            'status'         => 'success',
        ];
    }

    /** 金额转最小单位（分），JPY/KRW 等零小数币种不放大 */
    public function toMinor(string $amount, string $currency): string
    {
        if (in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES, true)) {
            return bcmul($amount, '1', 0);
        }
        return bcmul($amount, '100', 0);
    }

    /** 最小单位转回金额字符串 */
    private function fromMinor(string $amount, string $currency): string
    {
        if (in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES, true)) {
            return $amount;
        }
        return bcdiv($amount, '100', 4);
    }
}
