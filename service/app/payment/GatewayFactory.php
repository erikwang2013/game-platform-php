<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

class GatewayFactory
{
    public static function resolve(string $provider): PaymentGatewayInterface
    {
        return match ($provider) {
            'stripe'      => new StripeGateway(),
            'nowpayments' => new NowPaymentsGateway(),
            'coinbase'    => new CoinbaseCommerceGateway(),
            default       => throw new \InvalidArgumentException("Unsupported payment gateway: {$provider}"),
        };
    }
}
