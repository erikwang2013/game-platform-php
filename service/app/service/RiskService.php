<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service;

use app\model\RiskRule;
use app\model\RiskLog;

/**
 * 风控引擎
 *
 * 检查顺序: IP黑名单 → 频率检测 → 金额异常 → 速度检测
 * 返回: passed(通过) / warn(警告+记录) / block(阻断)
 */
class RiskService
{
    /**
     * 执行风控检查
     *
     * @param int    $userId    用户ID (0=未登录)
     * @param string $checkType 检查类型: deposit/withdraw/exchange/login
     * @param array  $context   上下文: ['amount' => '100', 'ip' => '1.2.3.4', 'device_id' => '...']
     * @return array ['result' => 'passed'|'warn'|'block', 'message' => '', 'rule_name' => '']
     */
    public static function check(int $userId, string $checkType, array $context = []): array
    {
        $rules = RiskRule::getEnabled();

        foreach ($rules as $rule) {
            $config = json_decode($rule->config, true) ?? [];
            $result = self::evaluateRule($rule, $userId, $checkType, $context, $config);

            if ($result['matched']) {
                // 记录风控日志
                self::log($userId, $rule, $checkType, $rule->action, $context, $result['message']);

                if ($rule->action === 'block') {
                    return ['result' => 'block', 'message' => $result['message'], 'rule_name' => $rule->name];
                }
                if ($rule->action === 'warn') {
                    return ['result' => 'warn', 'message' => $result['message'], 'rule_name' => $rule->name];
                }
            }
        }

        return ['result' => 'passed', 'message' => '', 'rule_name' => ''];
    }

    /**
     * 评估单条规则是否命中
     *
     * @param RiskRule $rule
     * @param int      $userId
     * @param string   $checkType
     * @param array    $context
     * @param array    $config
     * @return array ['matched' => bool, 'message' => string]
     */
    private static function evaluateRule(RiskRule $rule, int $userId, string $checkType, array $context, array $config): array
    {
        $matched = false;
        $message = '';

        switch ($rule->type) {
            case 'ip_blacklist':
                $ip = $context['ip'] ?? '';
                $blacklist = $config['blacklist'] ?? [];
                if ($ip && in_array($ip, $blacklist)) {
                    $matched = true;
                    $message = "IP {$ip} in blacklist";
                }
                break;

            case 'amount_anomaly':
                $amount = $context['amount'] ?? '0';
                $minAmount = $config['min_amount'] ?? '0';
                if (bccomp($amount, $minAmount, 4) >= 0) {
                    $matched = true;
                    $message = "Large amount {$amount} detected";
                }
                break;

            case 'frequency':
                $windowMinutes = $config['window_minutes'] ?? 60;
                $maxCount = $config['max_count'] ?? 10;
                $since = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));

                $count = RiskLog::where('user_id', $userId)
                    ->where('type', $checkType)
                    ->where('created_at', '>=', $since)
                    ->count();

                if ($count >= $maxCount) {
                    $matched = true;
                    $message = "Frequency limit exceeded: {$count} in {$windowMinutes}min (max {$maxCount})";
                }
                break;

            case 'velocity':
                $windowMinutes = $config['window_minutes'] ?? 10;
                $maxAccounts = $config['max_accounts'] ?? 3;
                $sameIp = $config['same_ip'] ?? true;
                $ip = $context['ip'] ?? '';

                if ($sameIp && $ip) {
                    $since = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));
                    $uniqueUsers = RiskLog::where('created_at', '>=', $since)
                        ->whereNotNull('context')
                        ->distinct('user_id')
                        ->count('user_id');
                    if ($uniqueUsers >= $maxAccounts) {
                        $matched = true;
                        $message = "Velocity check: {$uniqueUsers} accounts in {$windowMinutes}min from IP {$ip}";
                    }
                }
                break;
        }

        return ['matched' => $matched, 'message' => $message];
    }

    /**
     * 记录风控日志
     *
     * @param int      $userId
     * @param RiskRule $rule
     * @param string   $type
     * @param string   $action
     * @param array    $context
     * @param string   $message
     */
    private static function log(int $userId, RiskRule $rule, string $type, string $action, array $context, string $message): void
    {
        try {
            $riskLog = new RiskLog();
            // 使用 timestamp+random 生成唯一的非业务ID，避免依赖跨模块的 SnowflakeService
            $riskLog->id = intval(date('YmdHis') . random_int(10000, 99999));
            $riskLog->user_id = $userId;
            $riskLog->rule_id = $rule->id;
            $riskLog->type = $type;
            $riskLog->action = $action;
            $riskLog->context = json_encode($context, JSON_UNESCAPED_UNICODE);
            $riskLog->result = $message;
            $riskLog->created_at = date('Y-m-d H:i:s');
            $riskLog->save();
        } catch (\Throwable $e) {
            // 风控日志失败不影响主流程
        }
    }
}
