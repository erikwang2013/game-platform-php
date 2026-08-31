<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use common\model\RiskRule;
use app\service\RiskSandboxService;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("风控规则配置")
 * @Apidoc\Group("risk")
 */
class RiskRuleController extends BaseController
{
    /**
     * @Apidoc\Title("规则列表")
     */
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

    /**
     * @Apidoc\Title("新建规则")
     */
    public function create(Request $request): Response
    {
        try {
            $rule = new RiskRule();
            $rule->id = $this->generateId();
            $this->fill($rule, $request->post());
            $rule->save();
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage());
        }

        return $this->success(['id' => $this->encodeId((int) $rule->id)]);
    }

    /**
     * @Apidoc\Title("更新规则")
     */
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
            return $this->fail($e->getMessage());
        }

        return $this->success();
    }

    /**
     * @Apidoc\Title("启停规则")
     */
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

    /**
     * @Apidoc\Title("沙箱试算")
     * @Apidoc\Desc("按单条规则只读评估，不写库、不落日志、不触发处置")
     */
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
        $config = (string) ($data['config'] ?? '{}');
        if (json_decode($config, true) === null) {
            throw new \InvalidArgumentException('config 必须是合法 JSON');
        }

        $rule->name = $name;
        $rule->type = $type;
        $rule->scope = $scope;
        $rule->config = $config;
        $rule->action = $action;
        $rule->priority = max(0, min(1000, (int) ($data['priority'] ?? 100)));
        $rule->status = in_array((int) ($data['status'] ?? 1), [0, 1], true) ? (int) $data['status'] : 1;
    }
}
