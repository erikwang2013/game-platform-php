<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

use app\model\DepositOrder;

/**
 * 可选能力接口：支持退款的网关实现之。
 * 不加入 PaymentGatewayInterface —— 本地支付网关（M-Pesa STK Push、
 * Paysafecard 预付码等）根本不支持退款，能力查询用 GatewayCapabilities。
 */
interface RefundableGatewayInterface
{
    /**
     * 退款（全额或部分）。实现方必须保证幂等：同一订单重复调用不产生重复退款。
     *
     * @return array{success: bool, refund_id: string}
     */
    public function refund(DepositOrder $order, string $amount, string $reason = ''): array;
}
