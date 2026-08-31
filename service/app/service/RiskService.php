<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service;

use common\SnowflakeService;
use app\event\EventBus;
use app\model\RiskLog;
use app\model\RiskRule;
use app\service\risk\FingerprintContext;
use app\service\risk\RiskEvaluator;
use app\service\risk\evaluators\AmountAnomalyEvaluator;
use app\service\risk\evaluators\DeviceAccountGraphEvaluator;
use app\service\risk\evaluators\DeviceFingerprintEvaluator;
use app\service\risk\evaluators\FrequencyEvaluator;
use app\service\risk\evaluators\IpBlacklistEvaluator;
use app\service\risk\evaluators\IpReputationEvaluator;
use app\service\risk\evaluators\VelocityEvaluator;
use app\service\risk\evaluators\WithdrawPatternEvaluator;

/**
 * 风控引擎（H4）
 *
 * 策略评估器（RiskEvaluator）按 type 注册到 evaluatorMap，规则配置驱动；
 * 一次 check 收集全部命中，处置取最严（block > warn > log），所有命中写日志。
 *
 * 处置判定：severity 封顶（low → log，medium → 至多 warn，high → 规则 action 原样），
 * 保证硬规则（ip_blacklist / device_fingerprint 等 action=block）fail-closed，
 * 软规则（log/warn）不会被降级失效，也不因低危命中误伤。
 *
 * 返回: passed(通过) / warn(警告+记录) / block(阻断)
 */
class RiskService
{
    /** @var array<string,RiskEvaluator>|null type → 评估器实例 */
    private static ?array $evaluators = null;

    /**
     * 执行风控检查
     *
     * @param int    $userId    用户ID (0=未登录)
     * @param string $checkType 检查类型: deposit/withdraw/exchange/login
     * @param array  $context   上下文: ['amount' => '100', 'ip' => '1.2.3.4', 'user_agent' => '...']
     * @return array ['result' => 'passed'|'warn'|'block', 'message' => '', 'rule_name' => '']
     */
    public static function check(int $userId, string $checkType, array $context = []): array
    {
        // 派生指纹上下文（PII：只存 hash），评估器复用同一份派生结果
        $context['_sandbox'] = (bool) config('risk.sandbox', false);
        $fp = FingerprintContext::build($userId, $context);
        if ($fp !== []) {
            $context = array_merge($context, $fp);
        }

        $map = self::evaluatorMap();
        $hits = [];

        foreach (RiskRule::getEnabled() as $rule) {
            if (!self::scopeApplies($rule, $checkType)) {
                continue;
            }

            $evaluator = $map[$rule->type] ?? null;
            if ($evaluator === null) {
                continue; // 无对应评估器的规则跳过（预留类型不阻断）
            }

            $config = json_decode((string) $rule->config, true) ?? [];
            $result = $evaluator->evaluate($userId, $checkType, $context, $config);

            if (empty($result['matched'])) {
                continue;
            }

            $action = self::disposition((string) $rule->action, (string) ($result['severity'] ?? 'low'));
            $message = (string) ($result['message'] ?? '');

            // 命中即留痕：日志 + 关键事件（Outbox 可靠投递）
            self::log($userId, $rule, $checkType, $action, $context, $message);
            EventBus::push('risk.alert', 'risk_' . SnowflakeService::generate(), [
                'user_id'    => $userId,
                'check_type' => $checkType,
                'rule_id'    => $rule->id,
                'rule_name'  => $rule->name,
                'action'     => $action,
                'message'    => $message,
            ]);

            $hits[] = ['rule' => $rule, 'message' => $message, 'action' => $action];
        }

        // 收集全部命中后取最严处置（block > warn > log）；同级取先命中（优先级高）的规则
        $strictness = ['block' => 3, 'warn' => 2, 'log' => 1];
        $best = null;
        foreach ($hits as $hit) {
            if ($best === null || $strictness[$hit['action']] > $strictness[$best['action']]) {
                $best = $hit;
            }
        }

        if ($best === null) {
            return ['result' => 'passed', 'message' => '', 'rule_name' => ''];
        }

        if ($best['action'] === 'block') {
            return ['result' => 'block', 'message' => $best['message'], 'rule_name' => $best['rule']->name];
        }
        if ($best['action'] === 'warn') {
            return ['result' => 'warn', 'message' => $best['message'], 'rule_name' => $best['rule']->name];
        }

        // 仅 log 命中不阻断主流程（与旧行为一致）
        return ['result' => 'passed', 'message' => '', 'rule_name' => ''];
    }

    /**
     * type → 评估器实例注册表。延迟构建，避免每次 check 重复 new。
     */
    private static function evaluatorMap(): array
    {
        if (self::$evaluators === null) {
            $evaluators = [
                new IpBlacklistEvaluator(),
                new AmountAnomalyEvaluator(),
                new FrequencyEvaluator(),
                new VelocityEvaluator(),
                new DeviceFingerprintEvaluator(),
                new IpReputationEvaluator(),
                new DeviceAccountGraphEvaluator(),
                new WithdrawPatternEvaluator(),
            ];

            $map = [];
            foreach ($evaluators as $evaluator) {
                $map[$evaluator->type()] = $evaluator;
            }
            self::$evaluators = $map;
        }

        return self::$evaluators;
    }

    /**
     * 规则 scope 过滤：all 或与当前检查环节一致。
     */
    private static function scopeApplies(RiskRule $rule, string $checkType): bool
    {
        $scope = (string) ($rule->scope ?? 'all');

        return $scope === 'all' || $scope === $checkType;
    }

    /**
     * 处置 = 规则 action 受 severity 封顶：
     * low → log；medium → 至多 warn；high → 规则 action 原样（硬规则 fail-closed）。
     */
    private static function disposition(string $ruleAction, string $severity): string
    {
        if ($severity === 'high') {
            return $ruleAction;
        }
        if ($severity === 'medium') {
            return $ruleAction === 'block' ? 'warn' : $ruleAction;
        }

        return 'log';
    }

    /**
     * 记录风控日志。result 存规范化处置，完整命中消息写 detail（H4 §4.2 不再截断）。
     */
    private static function log(int $userId, RiskRule $rule, string $type, string $action, array $context, string $message): void
    {
        try {
            $riskLog = new RiskLog();
            // Use SnowflakeService for collision-free unique IDs (same module, same app)
            $riskLog->id = SnowflakeService::generate();
            $riskLog->user_id = $userId;
            $riskLog->rule_id = $rule->id;
            $riskLog->type = $type;
            $riskLog->action = $action;
            $riskLog->context = json_encode($context, JSON_UNESCAPED_UNICODE);
            $riskLog->result = $action;
            $riskLog->detail = mb_substr($message, 0, 2000);
            $riskLog->ip_hash = (string) ($context['ip_hash'] ?? '');
            $riskLog->fp_hash = (string) ($context['fp_hash'] ?? '');
            $riskLog->user_agent_hash = (string) ($context['user_agent_hash'] ?? '');
            $riskLog->created_at = date('Y-m-d H:i:s');
            $riskLog->save();
        } catch (\Throwable $e) {
            // 风控日志失败不影响主流程
            \support\Log::error('RiskLog save failed', ['error' => $e->getMessage(), 'user_id' => $userId, 'rule_id' => $rule->id]);
        }
    }
}
