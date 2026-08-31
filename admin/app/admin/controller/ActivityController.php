<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\admin\controller;

use app\activity\ActivityHandlerFactory;
use common\model\Activity;
use support\Request;
use support\Response;

/**
 * 活动管理 CRUD（最小区间：不做 stats/resend）。
 * config JSON 按 type 做轻量 schema 校验，兜底 handler 默认配置。
 */
class ActivityController extends BaseController
{
    public function list(Request $request): Response
    {
        $query = Activity::query();

        if ($request->input('status') !== null && $request->input('status') !== '') {
            $query->where('status', (int) $request->input('status'));
        }
        if ($request->input('type')) {
            $query->where('type', $request->input('type'));
        }

        $total = $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(((int) $request->input('page', 1) - 1) * (int) $request->input('limit', 15))
            ->limit((int) $request->input('limit', 15))
            ->get()
            ->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total]);
    }

    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'type'    => 'required|in:signin,daily_task,invite',
            'name'    => 'required|string|max:100',
            'game_id' => 'nullable|integer',
            'config'  => 'nullable|string',
            'status'  => 'required|integer|in:0,1,2',
            'start_at' => 'nullable|date',
            'end_at'   => 'nullable|date|after_or_equal:start_at',
            'rollout_percent' => 'nullable|integer|between:0,100',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $type = $request->input('type');
        $config = $this->parseConfig($request->input('config'), $type);
        if ($config === null) {
            return $this->fail('config must be valid JSON and match type schema', 422);
        }

        $a = new Activity();
        $a->id = $this->generateId();
        $a->type = $type;
        $a->name = $request->input('name');
        $a->game_id = (int) $request->input('game_id', 0);
        $a->config = $config;
        $a->status = (int) $request->input('status');
        $a->start_at = $request->input('start_at') ?: null;
        $a->end_at = $request->input('end_at') ?: null;
        $a->rollout_percent = (int) $request->input('rollout_percent', 100);
        $a->save();

        return $this->success($this->encodeIds($a->toArray()), '创建成功');
    }

    public function update(Request $request, string $hashid): Response
    {
        $a = Activity::find($this->decodeId($hashid));
        if (!$a) {
            return $this->fail('活动不存在', 404);
        }

        if ($request->has('config')) {
            $config = $this->parseConfig($request->input('config'), $a->type);
            if ($config === null) {
                return $this->fail('config must be valid JSON and match type schema', 422);
            }
            $a->config = $config;
        }

        if ($request->input('name') !== null) {
            $a->name = $request->input('name');
        }
        if ($request->input('game_id') !== null) {
            $a->game_id = (int) $request->input('game_id');
        }
        if ($request->input('status') !== null) {
            $a->status = (int) $request->input('status');
        }
        if ($request->has('start_at')) {
            $a->start_at = $request->input('start_at') ?: null;
        }
        if ($request->has('end_at')) {
            $a->end_at = $request->input('end_at') ?: null;
        }
        if ($request->input('rollout_percent') !== null) {
            $a->rollout_percent = (int) $request->input('rollout_percent');
        }
        $a->save();

        return $this->success($this->encodeIds($a->toArray()), '更新成功');
    }

    public function destroy(Request $request, string $hashid): Response
    {
        $a = Activity::find($this->decodeId($hashid));
        if (!$a) {
            return $this->fail('活动不存在', 404);
        }
        $a->delete();

        return $this->success([], '删除成功');
    }

    /**
     * config JSON 按 type 轻量校验；空配置兜底 handler 默认值。
     * 返回 null 表示 JSON 非法或不符合 type schema。
     */
    private function parseConfig(?string $raw, string $type): ?array
    {
        $handler = ActivityHandlerFactory::create((new Activity())->setAttribute('type', $type));

        if ($raw === null || $raw === '') {
            return $handler->defaultConfig();
        }

        $config = json_decode($raw, true);
        if (!is_array($config)) {
            return null;
        }

        if ($type === Activity::TYPE_SIGNIN) {
            $rewards = $config['rewards'] ?? null;
            if (!is_array($rewards) || $rewards === []) {
                return null;
            }
            foreach ($rewards as $entry) {
                if (!is_array($entry) || !isset($entry['day']) || !is_array($entry['reward'] ?? null)) {
                    return null;
                }
            }
        } elseif ($type === Activity::TYPE_DAILY_TASK) {
            $tasks = $config['tasks'] ?? null;
            if (!is_array($tasks) || $tasks === []) {
                return null;
            }
            foreach ($tasks as $task) {
                if (!is_array($task) || !is_string($task['event'] ?? null) || (int) ($task['target'] ?? 0) <= 0 || !is_array($task['reward'] ?? null)) {
                    return null;
                }
            }
        } elseif ($type === Activity::TYPE_INVITE) {
            if ((int) ($config['target'] ?? 0) <= 0) {
                return null;
            }
            $rewards = $config['rewards'] ?? null;
            if (!is_array($rewards) || $rewards === []) {
                return null;
            }
            foreach ($rewards as $entry) {
                if (!is_array($entry) || !is_array($entry['reward'] ?? null)) {
                    return null;
                }
            }
        }

        return $config;
    }
}
