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
            'paypal'      => new PayPalGateway(),
            'skrill'      => new SkrillGateway(),
            'neteller'    => new NetellerGateway(),
            'paysafecard' => new PaysafecardGateway(),
            'paytm'       => new PaytmGateway(),
            'mercadopago' => new MercadoPagoGateway(),
            'astropay'    => new AstroPayGateway(),
            'paypay'      => new PayPayGateway(),
            'kakaopay'    => new KakaoPayGateway(),
            'gcash'       => new GcashGateway(),
            'mpesa'       => new MpesaGateway(),
            'paystack'    => new PaystackGateway(),
            'toss'        => new TossGateway(),
            default       => throw new \InvalidArgumentException("Unsupported payment gateway: {$provider}"),
        };
    }
}
