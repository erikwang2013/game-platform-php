<?php
/*
 * Copyright (c) 2026 erik <erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

/**
 * 对账单数据源解析器（对齐 GatewayFactory 的 match 风格）。
 *
 * 返回 null = 该网关不支持自动拉取，调用方应走人工 CSV 上传。
 *
 * 目前所有网关均走人工 CSV：结算对账单 API 普遍只对企业商户开放，
 * 小商户只能从门户下载。首批自动拉取候选（需先核实官方文档端点）：
 * stripe / paystack / mercadopago。实现 StatementSourceInterface 后，
 * 在此加一行即可接入，不改其它代码。
 */
class StatementSourceResolver
{
    /**
     * 解析网关对应的对账单数据源。
     *
     * @param string $gateway 网关标识
     *
     * @return StatementSourceInterface|null null=该网关需人工上传 CSV
     */
    public static function resolve(string $gateway): ?StatementSourceInterface
    {
        return match ($gateway) {
            // 'stripe'      => new StripeStatementSource(),
            // 'paystack'    => new PaystackStatementSource(),
            // 'mercadopago' => new MercadoPagoStatementSource(),
            default => null,
        };
    }

    /**
     * 网关是否支持自动拉取对账单（供管理端展示能力清单）。
     */
    public static function supports(string $gateway): bool
    {
        return self::resolve($gateway) !== null;
    }
}
