<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\DepositOrder;
use common\model\PaymentMethod;
use common\model\PlatformConfig;
use app\payment\GatewayFactory;
use app\service\ComplianceCheckService;
use common\service\NotificationService;
use common\service\DepositLogService;
use hg\apidoc\annotation as Apidoc;
use support\Log;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("充值管理")
 * @Apidoc\Group("wallet")
 */
class DepositController extends BaseController
{
    /**
     * @Apidoc\Title("创建充值订单")
     * @Apidoc\Url("/api/v1/deposit/create")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="amount", type="float", require=true, desc="充值金额")
     * @Apidoc\Param(name="currency", type="string", require=true, desc="货币(USD/CNY/EUR)")
     * @Apidoc\Param(name="payment_method_id", type="string", require=true, desc="支付方式ID")
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'amount'            => 'required|numeric|min:0.01',
            'currency'          => 'required|in:USD,CNY,EUR,JPY,KRW,GBP,BRL,INR',
            'payment_method_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $userId          = $request->userId;
        $amount          = $request->input('amount');
        $currency        = $request->input('currency');

        // 精度对齐支付商最小单位转换：JPY/KRW 零小数币，其余最多 2 位小数，否则 Stripe 分转换回环不一致
        $maxDecimals = in_array(strtoupper((string) $currency), ['JPY', 'KRW'], true) ? 0 : 2;
        if (!preg_match('/^\d+(\.\d{1,' . $maxDecimals . '})?$/', (string) $amount)) {
            return $this->fail('Amount precision not supported', 422);
        }
        $paymentMethodId = $this->decodeId($request->input('payment_method_id'));

        // 支付方式校验：存在、启用、国家可见（按语言头映射）、金额区间
        $method = PaymentMethod::find($paymentMethodId);
        if (!$method || (int) $method->status !== 1) {
            return $this->fail('Payment method not available', 422);
        }
        if (!$method->isAvailableIn($this->resolveCountry($request))) {
            return $this->fail('Payment method not available in your country', 422);
        }
        if ($method->currency !== '' && strtoupper($method->currency) !== strtoupper($currency)) {
            return $this->fail('Currency not supported by this method', 422);
        }
        if (bccomp((string) $amount, (string) $method->min_amount, 4) < 0) {
            return $this->fail('Amount below minimum', 422);
        }
        if (bccomp((string) $method->max_amount, '0', 4) > 0 && bccomp((string) $amount, (string) $method->max_amount, 4) > 0) {
            return $this->fail('Amount above maximum', 422);
        }

        // Calculate platform amount using exchange rate
        $exchangeRate   = PlatformConfig::get('payment', 'default_exchange_rate', '1.0000');
        $platformAmount = bcmul((string) $amount, $exchangeRate, 4);

        // Generate order number: DEP + YmdHis + unique suffix (uniqid 微秒+进程，避免同秒撞 uk_order_no)
        $orderNo = 'DEP' . date('YmdHis') . strtoupper(substr(uniqid('', true), -6));

        // 合规钩子（默认 no-op，config/compliance.php enabled=false 时与改造前行为完全一致）
        ComplianceCheckService::beforeDeposit($userId, (string) $amount, (string) $currency, $this->resolveCountry($request));

        $order = new DepositOrder();
        $order->id                 = $this->generateId();
        $order->order_no           = $orderNo;
        $order->user_id            = $userId;
        $order->amount             = (string) $amount;
        $order->currency           = $currency;
        $order->platform_amount    = $platformAmount;
        $order->payment_method_id  = $paymentMethodId;
        $order->status             = 'pending';
        // 留存下单用户 IP：回调请求来自网关回源 IP，风控需按用户真实 IP 聚合
        $order->client_ip          = (string) $request->getRealIp();
        $order->save();

        // 调网关创建支付：成功回填支付链接与过期时间，失败取消订单（客户端可重试）
        try {
            $result = GatewayFactory::resolve((string) $method->provider)->createPayment($order, $method);
            $order->checkout_url   = (string) ($result['checkout_url'] ?? '');
            $order->transaction_id = (string) ($result['transaction_id'] ?? '');
            $order->expires_at     = date('Y-m-d H:i:s', time() + 3600);
            $order->save();
        } catch (\Throwable $e) {
            Log::error('Gateway create payment failed', ['order_no' => $orderNo, 'error' => $e->getMessage()]);
            DepositOrder::where('id', $order->id)->where('status', 'pending')->update(['status' => 'cancelled']);
            return $this->fail('Payment gateway unavailable, please retry', 502);
        }

        NotificationService::send(
            $userId,
            'deposit',
            'Deposit Received',
            "{$platformAmount} platform tokens credited",
            'deposit',
            $order->id
        );

        DepositLogService::log($order->id, $userId, (string)$amount, $currency, 'pending');

        return $this->success([
            'order_id'        => $this->encodeId($order->id),
            'order_no'        => $order->order_no,
            'amount'          => $amount,
            'platform_amount' => $platformAmount,
            'checkout_url'    => $order->checkout_url,
            'expires_at'      => $order->expires_at,
        ], 'Deposit order created');
    }

    /**
     * @Apidoc\Title("充值记录")
     * @Apidoc\Url("/api/v1/deposit/orders")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function orders(Request $request): Response
    {
        $userId  = $request->userId;
        $page    = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);

        $paginator = DepositOrder::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($paginator->items() as $order) {
            $items[] = [
                'id'              => $this->encodeId($order->id),
                'order_no'        => $order->order_no,
                'amount'          => $order->amount,
                'currency'        => $order->currency,
                'platform_amount' => $order->platform_amount,
                'status'          => $order->status,
                'paid_at'         => $order->paid_at,
                'created_at'      => $order->created_at,
            ];
        }

        return $this->success([
            'items'     => $items,
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'per_page'  => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
