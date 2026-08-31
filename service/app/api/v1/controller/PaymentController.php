<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\CountryConfig;
use common\model\DepositOrder;
use common\model\PaymentMethod;
use app\payment\GatewayFactory;
use common\service\DepositLogService;
use common\model\UserWallet;
use app\event\EventBus;
use app\service\RiskService;
use app\service\WalletScope;
use hg\apidoc\annotation as Apidoc;
use support\Db;
use support\Log;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("支付管理")
 * @Apidoc\Group("payment")
 */
class PaymentController extends BaseController
{
    /**
     * @Apidoc\Title("支付回调")
     * @Apidoc\Url("/api/payment/callback")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="order_no", type="string", require=true, desc="订单号")
     * @Apidoc\Param(name="transaction_id", type="string", require=true, desc="交易ID")
     * @Apidoc\Param(name="status", type="string", require=true, desc="支付状态(success/failed)")
     */
    private const ALLOWED_PROVIDERS = [
        'stripe', 'paypal', 'nowpayments', 'coinbase',
        'skrill', 'neteller', 'paysafecard', 'paytm',
        'mercadopago', 'astropay', 'paypay', 'kakaopay', 'gcash',
        'mpesa', 'paystack', 'toss', 'adyen', 'grabpay',
    ];

    public function callback(Request $request): Response
    {
        $provider = strtolower((string) $request->input('provider', ''));
        if (!in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            return $this->fail('Invalid provider', 403);
        }
        if ($provider === 'stripe' && !$this->verifyStripeSignature($request)) {
            return $this->fail('Invalid signature', 403);
        }
        if ($provider === 'paypal' && !$this->verifyPayPalSignature($request)) {
            return $this->fail('Invalid signature', 403);
        }
        if ($provider === 'nowpayments' && !$this->verifyNowPaymentsSignature($request)) {
            return $this->fail('Invalid signature', 403);
        }
        if ($provider === 'coinbase' && !$this->verifyCoinbaseSignature($request)) {
            return $this->fail('Invalid signature', 403);
        }

        // 配置了可信回调来源 IP 列表时校验来源，未配置则不强制（仅限服务端到服务端的 Webhook）
        $trustedIps = getenv('CALLBACK_TRUSTED_IPS') ?: '';
        if ($trustedIps !== '') {
            $ips = array_map('trim', explode(',', $trustedIps));
            if (!in_array((string) $request->getRealIp(), $ips, true)) {
                return $this->fail('Source IP not allowed', 403);
            }
        }

        // M-Pesa 回调无签名，CheckoutRequestID 客户端可知（即订单 transaction_id），
        // 伪造回调即可免费入账 —— 必须配置可信来源 IP 才允许该渠道，否则一律拒绝（fail-closed）
        if ($provider === 'mpesa' && $trustedIps === '') {
            return $this->fail('M-Pesa requires CALLBACK_TRUSTED_IPS', 403);
        }

        // 解析回调数据：order_no/金额一律取自已验签的报文（或权威回查 API），
        // 不信任查询参数 —— 扁平协议可被重放篡改（截获 1 条真实 webhook 即可给任意订单入账）
        $verified = GatewayFactory::resolve($provider)->verifyCallback($request);

        if (empty($verified['valid'])) {
            return $this->fail('Invalid callback payload', 403);
        }
        if (($verified['status'] ?? '') === 'ignored') {
            // 网关事件无需处理（如 charge:created），成功应答防止网关重试
            return $this->success([], 'Ignored');
        }

        $orderNo        = (string) $verified['order_no'];
        $transactionId  = (string) $verified['transaction_id'];
        $callbackStatus = (string) $verified['status'];
        $callbackAmount = (string) $verified['amount'];

        $order = DepositOrder::where('order_no', $orderNo)->first();

        if (!$order) {
            return $this->fail('Order not found', 404);
        }

        // 回调 provider 必须与订单支付方式一致，防止跨渠道冒用；支付方式不存在同样拒绝
        $method = PaymentMethod::find($order->payment_method_id);
        if (!$method || strtolower((string) $method->provider) !== $provider) {
            return $this->fail('Provider mismatch', 403);
        }

        // 惰性过期：支付链接已过期且订单仍 pending 则取消，成功应答防止网关重试。
        // 回调状态为 success 时跳过 —— 用户已真实付款，过期不阻止入账（webhook 可能延迟）
        if ($callbackStatus !== 'success' && $order->status === 'pending' && $order->expires_at && strtotime((string) $order->expires_at) < time()) {
            DepositOrder::where('id', $order->id)->where('status', 'pending')->update(['status' => 'cancelled']);
            return $this->success(['order_no' => $order->order_no, 'status' => 'cancelled'], 'Order expired');
        }

        // 入账金额与订单核对：回调携带金额时不一致（或非数字）直接拒绝
        if ($callbackAmount !== '' && (!is_numeric($callbackAmount) || bccomp($callbackAmount, $order->amount, 4) !== 0)) {
            return $this->fail('Amount mismatch', 422);
        }

        // Idempotency: skip if this transaction_id was already processed
        if ($order->transaction_id === $transactionId && $order->status !== 'pending') {
            return $this->success(['order_no' => $order->order_no, 'status' => $order->status], 'Already processed');
        }

        // 风控检查（H4）：入账前执行；阻断 → 订单转人工审核，不授信。
        // IP 优先取订单留存的下单用户 IP（回调来源是网关回源 IP，velocity 会按网关汇聚误伤），缺省回落网关 IP
        $risk = RiskService::check($order->user_id, 'deposit', [
            'amount'          => (string) $order->platform_amount,
            'ip'              => (string) ($order->client_ip ?: $request->getRealIp()),
            'user_agent'      => (string) $request->header('user-agent', ''),
            'accept_lang'     => (string) $request->header('accept-language', ''),
            'accept_encoding' => (string) $request->header('accept-encoding', ''),
        ]);
        if ($risk['result'] === 'block') {
            DepositOrder::where('id', $order->id)->where('status', 'pending')->update(['status' => 'manual_review']);
            return $this->success(['order_no' => $order->order_no, 'status' => 'manual_review'], 'Deposit under manual review');
        }

        // 状态更新 + 余额入账 + 流水写入必须同事务，任一步失败整体回滚，防止半入账
        Db::beginTransaction();

        try {
            // Atomic status update prevents double-credit race condition
            $updated = DepositOrder::where('id', $order->id)
                ->where('status', 'pending')
                ->update([
                    'status'         => $callbackStatus === 'success' ? 'confirmed' : 'cancelled',
                    'transaction_id' => $transactionId,
                    'paid_at'        => $callbackStatus === 'success' ? date('Y-m-d H:i:s') : null,
                ]);

            if (!$updated) {
                Db::rollBack();
                return $this->success([], 'Already processed');
            }

            if ($callbackStatus === 'success') {
                $order->refresh();

                // Credit the user's platform wallet (M1: WalletService 统一记账+流水，ref 关联充值单)
                if (!UserWallet::addBalance($order->user_id, (string) $order->platform_amount, 'deposit', 'deposit', (int) $order->id)) {
                    throw new \RuntimeException('Wallet credit failed');
                }

                DepositLogService::log($order->id, $order->user_id, $order->amount, $order->currency, 'confirmed');

                // 到账事件与业务行同事务写入 Outbox（可靠投递），commit 后由消费进程投递 Webhook/成就引擎
                EventBus::push('deposit.completed', 'deposit_' . $order->id, [
                    'order_id'       => $order->id,
                    'order_no'       => $order->order_no,
                    'user_id'        => $order->user_id,
                    'amount'         => $order->platform_amount,
                    'currency'       => $order->currency,
                    'transaction_id' => $transactionId,
                ]);
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            Log::error('Payment callback failed', ['order_no' => $orderNo, 'trace_id' => ($request->traceId ?? ''), 'error' => $e->getMessage()]);
            return $this->fail('Callback processing failed', 500);
        }

        if ($callbackStatus === 'success') {
            return $this->success([
                'order_no' => $order->order_no,
                'status'   => 'confirmed',
                'paid_at'  => $order->paid_at,
            ], 'Deposit confirmed');
        }

        // Callback status is 'failed': order already cancelled atomically above
        return $this->success([
            'order_no' => $order->order_no,
            'status'   => 'cancelled',
        ], 'Deposit cancelled');
    }

    /**
     * @Apidoc\Title("支付方式列表")
     * @Apidoc\Url("/api/payment/methods")
     * @Apidoc\Method("GET")
     */
    public function methods(Request $request): Response
    {
        $country = $this->resolveCountry($request);

        $methods = PaymentMethod::where('status', 1)->get()
            ->filter(fn (PaymentMethod $method) => $method->isAvailableIn($country));

        // 本国优先：按国家配置 payment_methods 顺序排序（'crypto' 匹配 type='crypto' 方法），其余按 sort
        $pref = [];
        if ($country !== '') {
            $cfg = CountryConfig::getByCode($country);
            $pref = $cfg ? CountryConfig::methodNames((string) $cfg->payment_methods) : [];
        }
        $prefCount = count($pref);
        $methods = $methods->sortBy(function (PaymentMethod $method) use ($pref, $prefCount): int {
            $apm = (string) ($method->config['apm_types'][0] ?? '');
            $key = $method->type === 'crypto' ? 'crypto' : match ($apm) {
                'wechat_pay' => 'wechat',
                'alipay'     => 'alipay',
                default      => (string) $method->provider,
            };
            $idx = array_search($key, $pref, true);
            return ($idx === false ? $prefCount : $idx) * 1000 + (int) $method->sort;
        })->values();

        $list = $methods->map(function (PaymentMethod $method) {
            return [
                'id'         => $this->encodeId($method->id),
                'name'       => $method->name,
                'type'       => $method->type,
                'provider'   => $method->provider,
                'min_amount' => $method->min_amount,
                'max_amount' => $method->max_amount,
            ];
        });

        return $this->success(['list' => $list->toArray()]);
    }

    /**
     * Verify NOWPayments IPN signature (HMAC-SHA512 over raw body).
     */
    private function verifyNowPaymentsSignature(Request $request): bool
    {
        $secret    = getenv('NOWPAYMENTS_IPN_SECRET') ?: '';
        $signature = $request->header('X-NowPayments-Sig', '');
        if (!$secret || !$signature) {
            // Fail closed: 未配置密钥时拒绝一切回调，与 JWT 一致
            return false;
        }
        $expected = hash_hmac('sha512', $request->rawBody(), $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Verify Coinbase Commerce webhook signature (HMAC-SHA256 hex digest of raw body,
     * shared secret used raw as key — 官方 SDK coinbase-commerce-php 同款算法).
     */
    private function verifyCoinbaseSignature(Request $request): bool
    {
        $secret    = getenv('COINBASE_COMMERCE_WEBHOOK_SECRET') ?: '';
        $signature = $request->header('X-CC-Webhook-Signature', '');
        if (!$secret || !$signature) {
            // Fail closed: 未配置密钥时拒绝一切回调，与 JWT 一致
            return false;
        }
        $expected = hash_hmac('sha256', $request->rawBody(), $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Verify Stripe webhook signature.
     */
    private function verifyStripeSignature(Request $request): bool
    {
        $signature = $request->header('Stripe-Signature', '');
        if (!$signature) {
            return false;
        }

        $secret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
        if (!$secret) {
            // Fail closed: 未配置密钥时拒绝一切回调，与 JWT 一致
            return false;
        }

        $payload = $request->rawBody();
        $sigParts = explode(',', $signature);
        $timestamp = '';
        $receivedSig = '';

        foreach ($sigParts as $part) {
            if (str_starts_with($part, 't=')) {
                $timestamp = substr($part, 2);
            }
            if (str_starts_with($part, 'v1=')) {
                $receivedSig = substr($part, 3);
            }
        }

        if (!$timestamp || !$receivedSig) {
            return false;
        }

        // 时间戳新鲜度：签名 header 的 t= 即 payload 时间戳，缺失时上面已拒绝；
        // 超过 ±5 分钟视为重放
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSig = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedSig, $receivedSig);
    }

    private function verifyPayPalSignature(Request $request): bool
    {
        // PayPal uses a different verification: POST back to PayPal to verify
        // Fail closed: 未配置 webhook_id 时拒绝一切回调
        if (empty(getenv('PAYPAL_WEBHOOK_ID'))) {
            return false;
        }

        $verifyUrl = getenv('PAYPAL_VERIFY_URL') ?: 'https://api-m.paypal.com/v1/notifications/verify-webhook-signature';

        try {
            $http = new \GuzzleHttp\Client(['timeout' => 10]);
            $authSig = $request->header('PAYPAL-AUTH-ALGO', '');
            $certUrl = $request->header('PAYPAL-CERT-URL', '');
            $transmissionId = $request->header('PAYPAL-TRANSMISSION-ID', '');
            $transmissionSig = $request->header('PAYPAL-TRANSMISSION-SIG', '');
            $transmissionTime = $request->header('PAYPAL-TRANSMISSION-TIME', '');

            $resp = $http->post($verifyUrl, [
                'json' => [
                    'auth_algo' => $authSig,
                    'cert_url' => $certUrl,
                    'transmission_id' => $transmissionId,
                    'transmission_sig' => $transmissionSig,
                    'transmission_time' => $transmissionTime,
                    'webhook_id' => getenv('PAYPAL_WEBHOOK_ID') ?: '',
                    'webhook_event' => json_decode($request->rawBody(), true),
                ],
            ]);
            $result = json_decode((string)$resp->getBody(), true);
            return ($result['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Throwable $e) {
            // Fail closed: 验证异常视为验签失败
            return false;
        }
    }
}
