<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

/**
 * 网关能力表：基于可选接口的 instanceof 判断。
 * 控制器/前端不感知具体网关 —— 不支持退款的网关，前端隐藏退款入口。
 */
class GatewayCapabilities
{
    public static function supportsRefund(PaymentGatewayInterface $gateway): bool
    {
        return $gateway instanceof RefundableGatewayInterface;
    }

    public static function supportsQuery(PaymentGatewayInterface $gateway): bool
    {
        return $gateway instanceof QueryableGatewayInterface;
    }
}
