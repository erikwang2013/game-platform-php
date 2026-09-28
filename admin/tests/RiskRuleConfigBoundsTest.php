<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\RiskRuleController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * 风控规则 config 的值域校验
 *
 * 旧实现只有 `json_decode($config, true) === null` 一道闸：它只挡非法 JSON，
 * 于是 `max_accounts: 0` 这类**合法但会熔断全站**的值一路放行 ——
 * VelocityEvaluator.php:53 是 `$uniqueUsers >= $maxAccounts`，取 0 恒命中；
 * 命中后 severity=high，RiskService.php:78 保留规则自身 action，种子规则的
 * scope='all' + action='block' ⇒ RiskService.php:108 返回 block，
 * 而 deposit/withdraw/exchange/login 共用这条路径 ⇒ 连充值一起停。
 */
class RiskRuleConfigBoundsTest extends TestCase
{
    /** validateConfig 是私有的，且 fill() 要碰到模型——只反射这一个纯函数，不碰 DB。 */
    private function validate(string $type, string $config): string
    {
        $method = new ReflectionMethod(RiskRuleController::class, 'validateConfig');
        $method->setAccessible(true);

        return $method->invoke(new RiskRuleController(), $type, $config);
    }

    private function rejects(string $type, string $config): string
    {
        try {
            $this->validate($type, $config);
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }
        $this->fail("config {$config} 应当被 {$type} 拒绝，实际通过了");
    }

    /**
     * 熔断入口：阈值取 0 会让 `>=`/`>` 恒真 ⇒ 规则恒命中 ⇒ 全站 block。
     * 逐条对应一个评估器的读取点。
     */
    public static function meltdownConfigs(): array
    {
        return [
            'velocity.max_accounts=0（短时多账号）'          => ['velocity', '{"window_minutes":10,"max_accounts":0,"same_ip":true}'],
            'frequency.max_count=0（高频检测）'              => ['frequency', '{"window_minutes":60,"max_count":0}'],
            'device_account_graph.cluster_threshold=0（团伙）' => ['device_account_graph', '{"cluster_threshold":0}'],
            'device_fingerprint.max_accounts_per_device=0'   => ['device_fingerprint', '{"max_accounts_per_device":0}'],
            'withdraw_pattern.max_applies=0（清仓式提现）'    => ['withdraw_pattern', '{"max_applies":0}'],
            'withdraw_pattern.single_hard_cap=0'             => ['withdraw_pattern', '{"single_hard_cap":0}'],
            'withdraw_pattern.drain_ratio=0'                 => ['withdraw_pattern', '{"drain_ratio":0}'],
            'amount_anomaly.min_amount=0'                    => ['amount_anomaly', '{"min_amount":0}'],
            'velocity.max_accounts 负数'                     => ['velocity', '{"max_accounts":-1}'],
            'withdraw_pattern.drain_ratio>1'                 => ['withdraw_pattern', '{"drain_ratio":1.5}'],
            // 反向熔断：阈值**过高**与过低同样能全站熔断。IpReputationEvaluator.php:60 `$score < $blockBelow`
            // 而 reputation_score 是 TINYINT、语义域 0..100（install.sql:743 列注释 0=bad/50=neutral/100=good）
            // ⇒ block_score_below=1000 时任何 IP 都命中阻断，凡是查 ip_reputation 的检查类型全停。
            'ip_reputation.block_score_below=1000（全站熔断）' => ['ip_reputation', '{"block_score_below":1000}'],
            'ip_reputation.warn_score_below=101（全员预警）'   => ['ip_reputation', '{"warn_score_below":101}'],
            'ip_reputation 阈值负数'                          => ['ip_reputation', '{"block_score_below":-1}'],
        ];
    }

    #[Test]
    #[DataProvider('meltdownConfigs')]
    public function meltdownConfigsAreRejected(string $type, string $config): void
    {
        $this->assertNotSame('', $this->rejects($type, $config));
    }

    /**
     * JSON 标量/数组也能过旧闸（`json_decode('"abc"', true)` 得 'abc'，非 null），
     * 落库后 RiskService.php:71 拿到字符串再喂给 `evaluate(..., array $config)` ⇒ TypeError ⇒
     * C 端充值/提现 500。这里把形状闸钉住。
     */
    public static function malformedConfigs(): array
    {
        return [
            'JSON 字符串' => ['"abc"'],
            'JSON 数字'   => ['42'],
            'JSON 布尔'   => ['true'],
            'JSON null'   => ['null'],
            'JSON 数组'   => ['[1,2]'],
            '非 JSON'     => ['not json at all'],
            '空串'        => [''],
        ];
    }

    #[Test]
    #[DataProvider('malformedConfigs')]
    public function nonObjectConfigsAreRejected(string $config): void
    {
        $this->assertNotSame('', $this->rejects('velocity', $config));
    }

    /**
     * 键必须属于该 type：评估器只读自己那几个键，写错名的键会被**静默忽略**，
     * 运营以为设了阈值其实没设。`max_accounts` 是 velocity 的键，配到 frequency 上是无操作。
     */
    #[Test]
    public function keyNotBelongingToTypeIsRejected(): void
    {
        $message = $this->rejects('frequency', '{"max_accounts":3}');

        $this->assertStringContainsString('max_accounts', $message);
        $this->assertStringContainsString('window_minutes', $message); // 报错里要给出可用键
    }

    /**
     * 布尔键不受字符串：评估器读法是 `(bool) ($config['k'] ?? d)`，`(bool) "false"` 恒为 true，
     * 放行字符串等于把"关闭开关"静默变成"打开开关"。
     */
    #[Test]
    public function stringBoolIsRejectedButRealBoolPasses(): void
    {
        $this->assertStringContainsString('布尔', $this->rejects('velocity', '{"same_ip":"false"}'));

        $this->assertSame(
            '{"window_minutes":10,"max_accounts":3,"same_ip":false}',
            $this->validate('velocity', '{"window_minutes":10,"max_accounts":3,"same_ip":false}')
        );
    }

    /**
     * 科学计数法是 is_numeric 认可、bccomp 会抛 ValueError 的形状，必须挡在门口。
     */
    #[Test]
    public function scientificNotationIsRejected(): void
    {
        $this->assertNotSame('', $this->rejects('velocity', '{"max_accounts":"1e3"}'));
    }

    /**
     * sigma_multiplier 是统计乘数，评估器按 (float) 读（WithdrawPatternEvaluator.php:43），
     * 传 JSON 小数必须能过。
     */
    #[Test]
    public function floatStatisticMultiplierIsAccepted(): void
    {
        $this->assertSame(
            '{"sigma_multiplier":2.5}',
            $this->validate('withdraw_pattern', '{"sigma_multiplier":2.5}')
        );
    }

    #[Test]
    public function blacklistAcceptsStringArrayAndEmptyArray(): void
    {
        $this->assertSame('{"blacklist":[]}', $this->validate('ip_blacklist', '{"blacklist":[]}'));
        $this->assertSame('{"blacklist":["1.2.3.4"]}', $this->validate('ip_blacklist', '{"blacklist":["1.2.3.4"]}'));

        $this->assertNotSame('', $this->rejects('ip_blacklist', '{"blacklist":[123]}'));
        $this->assertNotSame('', $this->rejects('ip_blacklist', '{"blacklist":"1.2.3.4"}'));
    }

    #[Test]
    public function emptyConfigObjectIsAccepted(): void
    {
        $this->assertSame('{}', $this->validate('velocity', '{}'));
    }

    /**
     * 回归闸：install.sql 里**播种的每一条规则**都必须过得了新的值域校验，
     * 否则上线后运营连存量规则都改不动（改一次 422 一次）。
     * 直接从 install.sql 读，种子新增规则/新键时这条会红。
     */
    #[Test]
    public function seededRulesAllPassValidation(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../install/install.sql');
        $this->assertNotFalse($sql, 'install.sql 不可读');

        $block = strstr($sql, '-- 默认风控规则');
        $this->assertNotFalse($block, 'install.sql 里找不到风控规则种子段');
        $block = substr($block, 0, (int) strpos($block, ';'));

        // 每行形如 (id, 'name', 'type', 'scope', 'config', 'action', priority, status)
        preg_match_all("/'(\w+)',\s*'(\w+)',\s*'(\{[^']*\})'/", $block, $rows, PREG_SET_ORDER);
        $this->assertNotEmpty($rows, '风控规则种子一行都没解析到');

        $checked = 0;
        foreach ($rows as [, $type, $scope, $config]) {
            // 只有 RiskSandboxService::TYPES 里的类型可经本接口增删改；anticheat_bet_pattern
            // 不在其中，fill() 在 type 那道闸就拒了，与 config 校验无关。
            if (!in_array($type, \app\service\RiskSandboxService::TYPES, true)) {
                continue;
            }
            $this->assertSame($config, $this->validate($type, $config), "种子规则 {$type} 被拒");
            $checked++;
        }

        $this->assertSame(8, $checked, '可经接口变更的种子规则应有 8 条');
    }
}
