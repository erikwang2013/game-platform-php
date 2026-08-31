<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\risk;

/**
 * 风控规则评估器
 *
 * 每条规则类型对应一个实现。新增维度 = 新增一个实现类 + 在 RiskService::$evaluators 注册，
 * 不再改动 RiskService 主体。
 */
interface RiskEvaluator
{
    /**
     * 规则类型标识（与 risk_rule.type 对应）
     */
    public function type(): string;

    /**
     * 评估规则是否命中
     *
     * @param int    $userId    用户ID (0=未登录)
     * @param string $checkType 检查类型: deposit/withdraw/exchange/login
     * @param array  $context   上下文: ip/amount/fp_hash/user_agent 等派生字段
     * @param array  $config    规则 JSON 配置（阈值）
     * @return array ['matched' => bool, 'message' => string, 'severity' => 'low'|'medium'|'high']
     */
    public function evaluate(int $userId, string $checkType, array $context, array $config): array;
}
