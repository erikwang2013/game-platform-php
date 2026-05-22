<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\DepositOrder;
use common\model\PlatformConfig;
use support\Request;
use support\Response;

class DepositController extends BaseController
{
    /**
     * POST /api/deposit/create
     */
    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'amount'            => 'required|numeric|min:0.01',
            'currency'          => 'required|in:USD,CNY,EUR',
            'payment_method_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $userId          = $request->userId;
        $amount          = $request->input('amount');
        $currency        = $request->input('currency');
        $paymentMethodId = $this->decodeId($request->input('payment_method_id'));

        // Calculate platform amount using exchange rate
        $exchangeRate   = PlatformConfig::get('payment', 'default_exchange_rate', '1.0000');
        $platformAmount = bcmul((string) $amount, $exchangeRate, 4);

        // Generate order number: DEP + YmdHis + random 4 digits
        $orderNo = 'DEP' . date('YmdHis') . str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

        $order = new DepositOrder();
        $order->id                 = $this->generateId();
        $order->order_no           = $orderNo;
        $order->user_id            = $userId;
        $order->amount             = (string) $amount;
        $order->currency           = $currency;
        $order->platform_amount    = $platformAmount;
        $order->payment_method_id  = $paymentMethodId;
        $order->status             = 'pending';
        $order->save();

        return $this->success([
            'order_id'        => $this->encodeId($order->id),
            'order_no'        => $order->order_no,
            'amount'          => $amount,
            'platform_amount' => $platformAmount,
        ], 'Deposit order created');
    }

    /**
     * GET /api/deposit/orders
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
