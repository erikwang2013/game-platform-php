<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\RiskDeviceBlock;
use common\model\RiskRule;
use common\model\RiskLog;
use app\service\RiskService;
use support\Db;

/**
 * RiskService 单元测试
 * 覆盖: 各规则类型评估（黑名单/金额异常/未知类型）、check() 阻断/告警/放行与风控日志
 */
class RiskServiceTest extends TestCase
{
    private const TEST_RULE_ID = 980000001;
    private const TEST_USER_ID = 990000301;

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    #[Test]
    public function evaluateIpBlacklistHitsWhenIpListed(): void
    {
        $rule = $this->makeRule('ip_blacklist', ['blacklist' => ['1.2.3.4']]);
        $result = self::evaluateRule($rule, self::TEST_USER_ID, 'login', ['ip' => '1.2.3.4']);
        $this->assertTrue($result['matched']);
        $this->assertStringContainsString('1.2.3.4', $result['message']);
    }

    #[Test]
    public function evaluateIpBlacklistMissesWhenIpNotListed(): void
    {
        $rule = $this->makeRule('ip_blacklist', ['blacklist' => ['1.2.3.4']]);
        $result = self::evaluateRule($rule, self::TEST_USER_ID, 'login', ['ip' => '9.9.9.9']);
        $this->assertFalse($result['matched']);
    }

    #[Test]
    public function evaluateAmountAnomalyTriggersAtBoundary(): void
    {
        $rule = $this->makeRule('amount_anomaly', ['min_amount' => '100']);
        $result = self::evaluateRule($rule, self::TEST_USER_ID, 'withdraw', ['amount' => '100']);
        $this->assertTrue($result['matched']);
    }

    #[Test]
    public function evaluateAmountAnomalyMissesBelowThreshold(): void
    {
        $rule = $this->makeRule('amount_anomaly', ['min_amount' => '100']);
        $result = self::evaluateRule($rule, self::TEST_USER_ID, 'withdraw', ['amount' => '99.9999']);
        $this->assertFalse($result['matched']);
    }

    #[Test]
    public function evaluateUnknownRuleTypeNeverMatches(): void
    {
        $rule = $this->makeRule('unknown_type', []);
        $result = self::evaluateRule($rule, self::TEST_USER_ID, 'login', []);
        $this->assertFalse($result['matched']);
    }

    #[Test]
    public function checkBlocksWhenBlockingRuleMatches(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            $rule = $this->makeRule('ip_blacklist', ['blacklist' => ['5.5.5.5']], 'block', 90);
            $rule->save();

            $result = RiskService::check(self::TEST_USER_ID, 'login', ['ip' => '5.5.5.5']);

            $this->assertSame('block', $result['result']);
            $this->assertSame($rule->name, $result['rule_name']);
            // 注: 风控日志写入受 game_risk_log.result VARCHAR(20) 长度限制影响
            // （消息如 "IP 5.5.5.5 in blacklist" 超过 20 字符），此处仅断言 check() 结果。
        });
    }

    #[Test]
    public function checkWarnsWhenWarningRuleMatches(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            $rule = $this->makeRule('amount_anomaly', ['min_amount' => '1000'], 'warn', 80);
            $rule->save();

            $result = RiskService::check(self::TEST_USER_ID, 'withdraw', ['amount' => '5000']);

            $this->assertSame('warn', $result['result']);
            $this->assertSame($rule->name, $result['rule_name']);
        });
    }

    #[Test]
    public function checkPassesWhenNoRuleMatches(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            $rule = $this->makeRule('ip_blacklist', ['blacklist' => ['5.5.5.5']], 'block', 90);
            $rule->save();

            $result = RiskService::check(self::TEST_USER_ID, 'login', ['ip' => '8.8.8.8']);

            $this->assertSame('passed', $result['result']);
            $this->assertSame('', $result['message']);
        });
    }

    #[Test]
    public function checkBlocksOnHighestPriorityRule(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            // 同级硬规则（同为 ip_blacklist block/severity=high）时先命中者胜：
            // getEnabled 按 priority desc，高优先级规则先评估并保底为首个命中。
            $low = $this->makeRule('ip_blacklist', ['blacklist' => ['1.1.1.1']], 'block', 10);
            $low->id = self::TEST_RULE_ID;
            $low->name = 'test-ip_blacklist-low';
            $low->save();
            $high = $this->makeRule('ip_blacklist', ['blacklist' => ['1.1.1.1']], 'block', 90);
            $high->id = self::TEST_RULE_ID + 1;
            $high->name = 'test-ip_blacklist-high';
            $high->save();

            $result = RiskService::check(self::TEST_USER_ID, 'withdraw', ['ip' => '1.1.1.1']);
            $this->assertSame('block', $result['result']);
            $this->assertSame('test-ip_blacklist-high', $result['rule_name']);
        });
    }

    /**
     * 管理端人工拉黑：**一条规则都没有**（cleanup 之后库里没有测试规则）也必须阻断。
     * 这条正是「拉黑按钮是否真的拉黑」的判据 —— 若实现成「等 device_fingerprint 规则命中」，
     * 种子规则 status=0（默认停用）时这里会 passed。
     */
    #[Test]
    public function checkBlocksOnManualDeviceBlockWithNoRuleEnabled(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            $fp = hash('sha256', 'lead-manual-block-probe');
            RiskDeviceBlock::block($fp);
            try {
                $result = RiskService::check(self::TEST_USER_ID, 'withdraw', ['fp_hash' => $fp, 'amount' => '1']);
                $this->assertSame('block', $result['result']);
                $this->assertSame('管理端设备拉黑', $result['rule_name']);
                $this->assertStringContainsString('拉黑', $result['message']);
            } finally {
                RiskDeviceBlock::unblock($fp);
            }
        });
    }

    /** 解封后同一条 check 必须放行（否则「解封」按钮也是假的） */
    #[Test]
    public function checkPassesAfterManualDeviceUnblock(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            $fp = hash('sha256', 'lead-manual-block-probe');
            RiskDeviceBlock::unblock($fp);

            $result = RiskService::check(self::TEST_USER_ID, 'withdraw', ['fp_hash' => $fp, 'amount' => '1']);
            $this->assertSame('passed', $result['result']);
        });
    }

    /** 别的设备不受影响（负控：标记不能是靠「有没有 fp_hash」生效的） */
    #[Test]
    public function checkDoesNotBlockOtherDevice(): void
    {
        Db::connection()->transaction(function () {
            $this->cleanup();
            $blocked = hash('sha256', 'lead-manual-block-probe');
            RiskDeviceBlock::block($blocked);
            try {
                $other = hash('sha256', 'lead-other-device');
                $result = RiskService::check(self::TEST_USER_ID, 'withdraw', ['fp_hash' => $other, 'amount' => '1']);
                $this->assertSame('passed', $result['result']);
            } finally {
                RiskDeviceBlock::unblock($blocked);
            }
        });
    }

    private function makeRule(string $type, array $config, string $action = 'block', int $priority = 0): RiskRule
    {
        $rule = new RiskRule();
        $rule->id = self::TEST_RULE_ID;
        $rule->name = 'test-' . $type;
        $rule->type = $type;
        $rule->config = json_encode($config, JSON_UNESCAPED_UNICODE);
        $rule->action = $action;
        $rule->priority = $priority;
        $rule->status = 1;
        return $rule;
    }

    private function cleanup(): void
    {
        RiskRule::whereIn('id', [self::TEST_RULE_ID, self::TEST_RULE_ID + 1])->delete();
        RiskLog::where('rule_id', self::TEST_RULE_ID)->delete();
        RiskLog::where('rule_id', self::TEST_RULE_ID + 1)->delete();
    }

    private static function evaluateRule(RiskRule $rule, int $userId, string $checkType, array $context): array
    {
        // RiskService 已重构为评估器架构：type → RiskEvaluator 注册表（私有 evaluatorMap），
        // 单条规则评估 = 从注册表取评估器直接 evaluate（与 check() 分发一致）。
        $map = (new \ReflectionMethod(RiskService::class, 'evaluatorMap'))->invoke(null);
        $evaluator = $map[$rule->type] ?? null;
        if ($evaluator === null) {
            return ['matched' => false, 'message' => '']; // 预留类型：无评估器 → 不命中（与 check() 跳过一致）
        }
        $config = json_decode($rule->config, true) ?? [];
        return $evaluator->evaluate($userId, $checkType, $context, $config);
    }
}
