<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\payment;

/**
 * 对账单数据源（可选接口，仅支持自动拉取对账单的网关实现）。
 *
 * 不实现此接口的网关由 {@see StatementSourceResolver::resolve()} 返回 null，
 * 走人工 CSV 上传通道（ReconciliationService::importCsv）。
 *
 * 刻意不并入 PaymentGatewayInterface：该接口语义是"支付创建 + 回调验签"，
 * 把对账拉取塞进去会让 16 个网关被迫实现它们支持不了的能力。
 */
interface StatementSourceInterface
{
    /**
     * 拉取 [startDate, endDate] 区间的对账单明细。
     *
     * 返回原始行数组，字段名由网关原生决定；ReconciliationService 负责归一化。
     * 归一化时识别的可选键：external_id / transaction_id / order_no /
     * amount / currency / status / transaction_time / settled_at。
     *
     * 其余键原样落入 game_reconciliation_statement.raw 供追溯。
     *
     * @param string $gateway   网关标识（stripe/paystack/mercadopago/...）
     * @param string $startDate 窗口起始日 Y-m-d
     * @param string $endDate   窗口结束日 Y-m-d
     *
     * @return array<array<string, mixed>>
     * @throws \RuntimeException 网关 API 调用失败时抛出
     */
    public function fetchStatement(string $gateway, string $startDate, string $endDate): array;
}
