<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

use app\model\DepositOrder;

/**
 * 可选能力接口：支持按订单反查网关侧状态的网关实现之。
 * H3 对账模块只依赖本接口即可工作，不感知具体网关。
 */
interface QueryableGatewayInterface
{
    /**
     * 查询网关侧订单状态（用于对账/超时复核）。
     *
     * @return array{status: string, amount: string, raw: array}
     *               status: pending|confirmed|failed
     */
    public function query(DepositOrder $order): array;
}
