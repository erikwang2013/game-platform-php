<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

use common\model\DepositOrder;
use common\model\PaymentMethod;
use support\Request;

interface PaymentGatewayInterface
{
    /**
     * 创建支付：返回网关支付链接与网关交易ID。
     *
     * @return array{checkout_url: string, transaction_id: string}
     */
    public function createPayment(DepositOrder $order, PaymentMethod $method): array;

    /**
     * 校验并解析回调。签名校验由控制器完成，这里只解析已验签的原始报文。
     *
     * @return array{valid: bool, order_no: string, transaction_id: string, amount: string, status: string}
     *               status: success=已支付 failed=失败 ignored=网关事件无需处理（回调应回 200）
     */
    public function verifyCallback(Request $request): array;
}
