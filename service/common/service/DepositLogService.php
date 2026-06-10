<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace common\service;

class DepositLogService
{
    public static function log(int $orderId, int $userId, string $amount, string $currency, string $status): void {}

    public static function revenueOverview(int $days): array
    {
        return ['total' => 0, 'trend' => []];
    }

    public static function conversionByGame(int $days): array { return []; }
}
