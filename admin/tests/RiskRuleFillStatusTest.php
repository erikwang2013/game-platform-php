<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\RiskRuleController;
use common\model\RiskRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * 规则 status 的缺省值：**缺键即启用**。
 *
 * 旧写法 `in_array((int) ($data['status'] ?? 1), [0,1], true) ? (int) $data['status'] : 1`
 * 自己推翻自己：缺键时判据恒真（1 ∈ [0,1]），于是取右边的 `(int) $data['status']`
 * ⇒ undefined key 警告 + `(int) null` = 0，**规则被静默停用**。前端填表编辑是全量提交
 * （RiskRuleController::update 复用 fill()），少带一个 status 就会把规则关掉。
 */
class RiskRuleFillStatusTest extends TestCase
{
    /** 只反射这个纯函数：fill() 不 save()，摸不到 DB */
    private function filled(array $data): RiskRule
    {
        $method = new ReflectionMethod(RiskRuleController::class, 'fill');

        $rule = new RiskRule();
        $method->invoke(new RiskRuleController(), $rule, $data);

        return $rule;
    }

    private function base(array $extra = []): array
    {
        return array_merge([
            'name'   => 'lead-test-status',
            'type'   => 'frequency',
            'action' => 'warn',
            'config' => '{"window_minutes":60,"max_count":10}',
        ], $extra);
    }

    #[Test]
    public function missingStatusDefaultsToEnabled(): void
    {
        $this->assertSame(1, $this->filled($this->base())->status, '缺 status 必须缺省启用（旧实现给 0 = 静默停用）');
    }

    #[Test]
    public function explicitZeroIsKept(): void
    {
        $this->assertSame(0, $this->filled($this->base(['status' => 0]))->status, '显式停用不能被改写');
    }

    #[Test]
    public function explicitOneIsKept(): void
    {
        $this->assertSame(1, $this->filled($this->base(['status' => 1]))->status);
    }

    #[Test]
    public function outOfRangeFallsBackToEnabled(): void
    {
        $this->assertSame(1, $this->filled($this->base(['status' => 9]))->status, '越界值回落启用，不是 0');
    }
}
