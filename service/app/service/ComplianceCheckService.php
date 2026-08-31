<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service;

/**
 * 合规检查服务（L3）：KYC/AML 策略钩子挂载点。
 * 默认 no-op（config/compliance.php enabled=false），不改变现有充值与提现流程。
 * 规则判定逻辑（KYC 等级校验 / AML 限额 / 制裁名单）为法务定义项，后续在此实现。
 */
class ComplianceCheckService
{
    /**
     * 充值创建前钩子（DepositController::create 调用）
     */
    public static function beforeDeposit(int $userId, string $amount, string $currency, string $countryCode): void
    {
        if (!config('compliance.enabled', false)) {
            return;
        }
        // future: KYC 等级校验 / AML 单笔·单日限额 / 制裁名单（法务定义后实现）
    }

    /**
     * 提现申请前钩子（WithdrawController::applyLocked 调用）
     */
    public static function beforeWithdraw(int $userId, string $amount, string $method, string $countryCode): void
    {
        if (!config('compliance.enabled', false)) {
            return;
        }
        // future: KYC 等级校验 / AML 频率·限额 / 制裁名单（法务定义后实现）
    }
}
