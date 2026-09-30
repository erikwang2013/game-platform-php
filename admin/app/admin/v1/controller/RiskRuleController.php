<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use common\model\RiskRule;
use app\service\RiskSandboxService;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

#[Apidoc\Title("风控规则配置")]
#[Apidoc\Group("risk")]
class RiskRuleController extends BaseController
{
    /** 十进制字面量：bcmath 只吃这种形状，`is_numeric('1e3')=true` 会让 bccomp 抛 ValueError */
    private const DECIMAL_RE = '/^-?\d+(\.\d+)?$/';

    /**
     * 各 type 允许的 config 键。白名单而不是黑名单：评估器只读自己那几个键，
     * 写错名的键（比如给 frequency 规则配 `max_accounts`）会被**静默忽略**，
     * 运营以为设了阈值其实没设 —— 这种静默空转正是本批要收口的一类。
     *
     * 键名取自各评估器的 `$config['...'] ?? 默认值` 实际读取点（service/app/service/risk/evaluators/）。
     * `currency` 是 amount_anomaly 的装饰键（种子 install.sql:1346 带它，评估器不读），保留以免存量规则改不动。
     */
    private const CONFIG_KEYS = [
        'ip_blacklist'         => ['blacklist'],
        'amount_anomaly'       => ['min_amount', 'currency'],
        'frequency'            => ['window_minutes', 'max_count'],
        'velocity'             => ['window_minutes', 'max_accounts', 'same_ip'],
        'device_fingerprint'   => ['max_accounts_per_device', 'new_device_lookback_hours', 'new_device_withdraw_block'],
        'ip_reputation'        => ['block_score_below', 'warn_score_below', 'block_unknown'],
        'device_account_graph' => ['cluster_threshold', 'max_accounts_per_device', 'frozen_sibling_block'],
        'withdraw_pattern'     => [
            'window_minutes', 'max_applies', 'single_hard_cap', 'drain_ratio',
            'sigma_window_days', 'sigma_multiplier', 'fast_interval_seconds', 'fast_interval_min_count',
        ],
    ];

    /**
     * 整数键的闭区间 [下界, 上界]。
     *
     * 下界一律 ≥1 的这批是**熔断全站**的入口，不是洁癖：评估器用的是 `>=`
     * （VelocityEvaluator.php:53 / FrequencyEvaluator.php:53 / DeviceAccountGraphEvaluator.php:80 /
     * WithdrawPatternEvaluator.php:53）或 `>`（DeviceFingerprintEvaluator.php:47），
     * 阈值取 0 就恒命中；命中后 severity=high，RiskService.php:78 的 disposition 保留规则自身的
     * action，种子规则又是 scope='all' + action='block' ⇒ RiskService.php:108 返回 block，
     * 而 deposit/withdraw/exchange/login 四个 checkType 共用这条路径 ⇒ **连充值一起停**。
     *
     * 上界是防手滑多打一个 0（100000 个账号/次的口径在本平台永远不会到达），不是业务常量。
     * window_minutes / sigma_window_days / fast_interval_seconds 的下界是防「窗口为 0 或负」
     * 导致历史查询为空、规则永不命中（静默 fail-open）。
     */
    private const INT_BOUNDS = [
        'window_minutes'            => [1, 10080],   // 7 天
        'max_count'                 => [1, 100000],
        'max_accounts'              => [1, 100000],
        'max_accounts_per_device'   => [1, 100000],
        'cluster_threshold'         => [1, 100000],
        'max_applies'               => [1, 100000],
        'fast_interval_min_count'   => [1, 100000],
        'new_device_lookback_hours' => [1, 8760],    // 1 年
        'sigma_window_days'         => [2, 3650],    // 标准差至少要 2 个样本
        'sigma_multiplier'          => [1, 100],
        'fast_interval_seconds'     => [1, 86400],
        'block_score_below'         => [0, 100],     // 信誉分域 0..100（IpReputationEvaluator.php:54）
        'warn_score_below'          => [0, 100],
    ];

    // 布尔键要求真正的 JSON true/false，不收字符串：评估器读法是 `(bool) ($config['k'] ?? d)`，
    // 而 `(bool) "false"` 恒为 true —— 放行字符串等于把 "关闭开关" 静默变成 "打开开关"。
    private const BOOL_KEYS = ['same_ip', 'new_device_withdraw_block', 'frozen_sibling_block', 'block_unknown'];

    /** 金额键：必须 > 0（0 会让 `amount >= 阈值` 恒真） */
    private const MONEY_KEYS = ['min_amount', 'single_hard_cap'];

    /** 比率键：必须 (0, 1]（0 会让 `ratio >= 阈值` 恒真；1 = 提现抽干余额） */
    private const RATIO_KEYS = ['drain_ratio'];

    #[Apidoc\Title("规则列表")]
    public function list(Request $request): Response
    {
        $query = RiskRule::query();
        if ($request->get('status') !== null && $request->get('status') !== '') {
            $query->where('status', (int) $request->get('status'));
        }
        if ($request->get('type')) {
            $query->where('type', (string) $request->get('type'));
        }
        if ($request->get('scope')) {
            $query->where('scope', (string) $request->get('scope'));
        }

        $page = max(1, (int) $request->get('page', 1));
        $size = min(100, max(1, (int) $request->get('size', 20)));
        $total = (clone $query)->count();
        $items = $query->orderBy('priority', 'desc')->forPage($page, $size)->get()->all();

        return $this->success([
            'total' => $total,
            'items' => $this->encodeIds(array_map(static fn ($row) => $row->toArray(), $items)),
        ]);
    }

    #[Apidoc\Title("新建规则")]
    public function create(Request $request): Response
    {
        try {
            $rule = new RiskRule();
            $rule->id = $this->generateId();
            $this->fill($rule, $request->post());
            $rule->save();
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success(['id' => $this->encodeId((int) $rule->id)]);
    }

    #[Apidoc\Title("更新规则")]
    public function update(Request $request, string $hashid): Response
    {
        $rule = RiskRule::find($this->decodeId($hashid));
        if (!$rule) {
            return $this->fail('规则不存在');
        }
        try {
            $this->fill($rule, $request->post());
            $rule->save();
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success();
    }

    #[Apidoc\Title("启停规则")]
    public function toggle(Request $request, string $hashid): Response
    {
        $rule = RiskRule::find($this->decodeId($hashid));
        if (!$rule) {
            return $this->fail('规则不存在');
        }
        $rule->status = $rule->status ? 0 : 1;
        $rule->save();

        return $this->success(['status' => (int) $rule->status]);
    }

    #[Apidoc\Title("沙箱试算")]
    #[Apidoc\Desc("按单条规则只读评估，不写库、不落日志、不触发处置")]
    public function test(Request $request): Response
    {
        $rule = RiskRule::find($this->decodeId((string) $request->post('rule_id', '')));
        if (!$rule) {
            return $this->fail('规则不存在');
        }

        $context = $request->post('context');
        if (!is_array($context)) {
            $context = [];
        }
        $result = RiskSandboxService::test(
            $this->decodeId((string) $request->post('user_id', '')),
            (string) $request->post('check_type', 'login'),
            $rule->toArray(),
            $context
        );

        return $this->success($result);
    }

    /**
     * config 校验：必须是 JSON 对象、键必须在 type 白名单内、值必须在值域内。
     * 通过后原样返回（不重新编码，保留运营输入的格式）。
     *
     * 旧实现只有 `json_decode($config, true) === null` 一道闸，两个洞：
     * ① 只挡非法 JSON，挡不住 JSON 标量/数组 —— `json_decode('"abc"', true)` 得 `'abc'`，
     *    非 null ⇒ 通过。落库后 RiskService.php:71 `json_decode(...) ?? []` 拿到字符串，
     *    再传给 `evaluate(..., array $config)` 直接 TypeError ⇒ 500 打在 C 端充值/提现路径上。
     * ② config 是**阈值载体**，`max_accounts:0` 这类"合法但会熔断全站"的值一路放行（见 INT_BOUNDS 注释）。
     */
    private function validateConfig(string $type, string $raw): string
    {
        $config = json_decode($raw, true);

        // 空对象 {} 与空数组 [] 在 assoc 模式下都是 []，无法区分；两者在这里都无害，放行。
        if (!is_array($config) || ($config !== [] && array_is_list($config))) {
            throw new \InvalidArgumentException('config 必须是 JSON 对象（形如 {"key":value}）');
        }

        $allowed = self::CONFIG_KEYS[$type] ?? [];
        foreach ($config as $key => $value) {
            if (!in_array((string) $key, $allowed, true)) {
                throw new \InvalidArgumentException(
                    "config.{$key} 不是 {$type} 支持的键（可用: " . implode('/', $allowed) . '）'
                );
            }
            $this->assertConfigValue((string) $key, $value);
        }

        return $raw;
    }

    /**
     * 单键值域校验。金额/比率走 bccomp（禁令：金额与十进制指标计算不得经 float）。
     */
    private function assertConfigValue(string $key, mixed $value): void
    {
        if (in_array($key, self::BOOL_KEYS, true)) {
            if (!is_bool($value)) {
                throw new \InvalidArgumentException("config.{$key} 必须是布尔值 true/false");
            }
            return;
        }

        if ($key === 'blacklist') {
            if (!is_array($value)) {
                throw new \InvalidArgumentException('config.blacklist 必须是字符串数组');
            }
            foreach ($value as $ip) {
                if (!is_string($ip) || trim($ip) === '') {
                    throw new \InvalidArgumentException('config.blacklist 只能是非空字符串');
                }
            }
            return;
        }

        if ($key === 'currency') {
            if (!is_string($value) || strlen($value) > 10) {
                throw new \InvalidArgumentException('config.currency 必须是 10 字符以内的字符串');
            }
            return;
        }

        // 数值键统一要求十进制字面量。
        // 不直接用 is_numeric 放行：它认可 "1e3" / "0x10"，而这两者喂给 bccomp 会抛 ValueError。
        // JSON 数字（int/float）经 json_encode 取最短往返十进制表示 —— ponytail: 这里对 float
        // 只做序列化、不做任何算术，属 CLAUDE.md 允许的 JSON 边界转型，故不绕 bcmath。
        $str = match (true) {
            is_int($value), is_float($value) => (string) json_encode($value),
            is_string($value)                => $value,
            default                          => '',
        };
        if ($str === '' || !preg_match(self::DECIMAL_RE, $str)) {
            throw new \InvalidArgumentException("config.{$key} 必须是十进制数字");
        }

        if (in_array($key, self::MONEY_KEYS, true)) {
            if (bccomp($str, '0', 8) <= 0) {
                throw new \InvalidArgumentException("config.{$key} 必须大于 0（当前 {$str}）");
            }
            return;
        }

        if (in_array($key, self::RATIO_KEYS, true)) {
            if (bccomp($str, '0', 8) <= 0 || bccomp($str, '1', 8) > 0) {
                throw new \InvalidArgumentException("config.{$key} 必须落在 (0, 1] 区间（当前 {$str}）");
            }
            return;
        }

        [$min, $max] = self::INT_BOUNDS[$key] ?? [null, null];
        if ($min === null) {
            throw new \InvalidArgumentException("config.{$key} 没有登记值域");
        }
        if (bccomp($str, (string) $min, 8) < 0 || bccomp($str, (string) $max, 8) > 0) {
            throw new \InvalidArgumentException("config.{$key} 必须在 {$min}..{$max} 之间（当前 {$str}）");
        }
    }

    /**
     * 字段校验 + 回填（仅 fillable 字段，越界输入被忽略）
     */
    private function fill(RiskRule $rule, array $data): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $type = (string) ($data['type'] ?? '');
        $action = (string) ($data['action'] ?? '');
        if ($name === '' || $type === '' || $action === '') {
            throw new \InvalidArgumentException('name/type/action 必填');
        }
        if (!in_array($type, RiskSandboxService::TYPES, true)) {
            throw new \InvalidArgumentException('type 不支持: ' . $type);
        }
        if (!in_array($action, ['log', 'warn', 'block'], true)) {
            throw new \InvalidArgumentException('action 仅支持 log/warn/block');
        }
        $scope = (string) ($data['scope'] ?? 'all');
        if (!in_array($scope, ['all', 'deposit', 'withdraw', 'exchange', 'login'], true)) {
            throw new \InvalidArgumentException('scope 仅支持 all/deposit/withdraw/exchange/login');
        }
        $config = $this->validateConfig($type, (string) ($data['config'] ?? '{}'));

        $rule->name = $name;
        $rule->type = $type;
        $rule->scope = $scope;
        $rule->config = $config;
        $rule->action = $action;
        $rule->priority = max(0, min(1000, (int) ($data['priority'] ?? 100)));
        // 缺 status 时**缺省启用**。原写法 `in_array((int) ($data['status'] ?? 1), [0,1], true) ? (int) $data['status'] : 1`
        // 自己推翻了自己：缺键时判据恒真（1 ∈ [0,1]），于是取右边的 `(int) $data['status']`
        // ⇒ undefined key 警告 + `(int) null` = 0，**规则被静默停用**。填表编辑是全量提交，
        // 少带一个 status 就会把规则关掉（潜在；四个前端表单目前都带 status 才没踩到）。
        $status = (int) ($data['status'] ?? 1);
        $rule->status = in_array($status, [0, 1], true) ? $status : 1;
    }
}
