<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use app\activity\ActivityHandlerFactory;
use app\service\WalletService;
use common\model\Activity;
use common\model\ActivityRewardLog;
use support\Request;
use support\Response;

/**
 * 活动管理 CRUD（最小区间：不做 stats/resend）。
 * config JSON 按 type 做轻量 schema 校验，兜底 handler 默认配置。
 */
#[Apidoc\Title("运营活动")]
#[Apidoc\Group("activity")]
class ActivityController extends BaseController
{
    /**
     * 单条奖励金额的上界 —— 与 service/app/service/ActivityService.php:47 `MAX_REWARD_PER_ENTRY` 是
     * **同一个数字**，两边都从 config 读同一个字段，改一处必须同时改另一处。
     *
     * ⚠ 这里只是 **UI 写入路径的卫生**（拦下手滑写错的配置），**不是钱的闸**：真正的闸在出钱那一侧
     * `ActivityService::creditWallet()`（:327 同一个 bccomp）。那道闸判不过只跳过该条奖励、
     * 落 `status=failed` + `fail_reason`，钱一分不出，participation 照常进终态。
     */
    private const MAX_REWARD_PER_ENTRY = '10000';

    /**
     * 发得出去的 reward type 只有这两个（ActivityRewardLog::REWARD_*）。其余类型在
     * creditWallet 末尾返回 'unsupported reward_type' ⇒ 该条奖励永远发不出去，
     * 配了也是白配：在这里就挡掉，比等到用户达标那天才发现早得多。
     */
    private const REWARD_TYPES = [ActivityRewardLog::REWARD_PLATFORM_COIN, ActivityRewardLog::REWARD_GAME_COIN];

    #[Apidoc\Title("活动列表")]
    #[Apidoc\Url("/admin/v1/activities/list")]
    #[Apidoc\Method("GET")]
    public function list(Request $request): Response
    {
        $query = Activity::query();

        if ($request->input('status') !== null && $request->input('status') !== '') {
            $query->where('status', (int) $request->input('status'));
        }
        if ($request->input('type')) {
            $query->where('type', $request->input('type'));
        }

        // clamp [1,200]：200 是本仓客户端的最大合法取数（游戏/角色下拉一次拉全）；无上界时 ?limit=10000000 直接拉全表
        $limit = min(200, max(1, (int) $request->input('limit', 15)));
        $total = $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(((int) $request->input('page', 1) - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total]);
    }

    #[Apidoc\Title("新增活动")]
    #[Apidoc\Url("/admin/v1/activities/create")]
    #[Apidoc\Method("POST")]
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
            return $this->fail(trans('config must be valid JSON and match type schema'), 422);
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

        return $this->success($this->encodeIds($a->toArray()), trans('Created successfully'));
    }

    #[Apidoc\Title("更新活动")]
    #[Apidoc\Url("/admin/v1/activities/{hashid}")]
    #[Apidoc\Method("PUT")]
    public function update(Request $request, string $hashid): Response
    {
        $a = Activity::find($this->decodeId($hashid));
        if (!$a) {
            return $this->fail(trans('Activity not found'), 404);
        }

        // 镜像 store 的规则，前缀 sometimes：update 是局部更新，缺省的字段不该被判 required。
        $validator = validator($request->all(), [
            'name'            => 'sometimes|required|string|max:100',
            'game_id'         => 'sometimes|nullable|integer|min:0',
            'config'          => 'sometimes|nullable|string',
            'status'          => 'sometimes|required|integer|in:0,1,2',
            'start_at'        => 'sometimes|nullable|date',
            'end_at'          => 'sometimes|nullable|date|after_or_equal:start_at',
            'rollout_percent' => 'sometimes|nullable|integer|between:0,100',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if ($request->has('config')) {
            $config = $this->parseConfig($request->input('config'), $a->type);
            if ($config === null) {
                return $this->fail(trans('config must be valid JSON and match type schema'), 422);
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

        return $this->success($this->encodeIds($a->toArray()), trans('Updated successfully'));
    }

    #[Apidoc\Title("删除活动")]
    #[Apidoc\Url("/admin/v1/activities/{hashid}")]
    #[Apidoc\Method("DELETE")]
    public function destroy(Request $request, string $hashid): Response
    {
        $a = Activity::find($this->decodeId($hashid));
        if (!$a) {
            return $this->fail(trans('Activity not found'), 404);
        }
        $a->delete();

        return $this->success([], trans('Deleted successfully'));
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
                if (!is_array($entry) || !isset($entry['day']) || $this->invalidReward($entry['reward'] ?? null)) {
                    return null;
                }
            }
        } elseif ($type === Activity::TYPE_DAILY_TASK) {
            $tasks = $config['tasks'] ?? null;
            if (!is_array($tasks) || $tasks === []) {
                return null;
            }
            foreach ($tasks as $task) {
                if (!is_array($task) || !is_string($task['event'] ?? null) || (int) ($task['target'] ?? 0) <= 0 || $this->invalidReward($task['reward'] ?? null)) {
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
                if (!is_array($entry) || $this->invalidReward($entry['reward'] ?? null)) {
                    return null;
                }
            }
        }

        return $config;
    }

    /**
     * reward 必须是 {type: 白名单内, amount: > 0 且 ≤ 上界}；返回 true = 拒。
     * 三条分支（签到/日常任务/邀请）共用，避免只堵住其中两条。
     */
    private function invalidReward(mixed $reward): bool
    {
        if (!is_array($reward) || !in_array($reward['type'] ?? null, self::REWARD_TYPES, true)) {
            return true;
        }

        $amount = $reward['amount'] ?? null;
        // 只认 int 与十进制字符串，不认 float：JSON 里写 1.5 会解成 PHP float，而金额禁走 float（仓库铁律）。
        if (is_int($amount)) {
            $amount = (string) $amount;
        }
        if (!is_string($amount) || !preg_match('/^\d+(\.\d+)?$/', $amount)) {
            return true;
        }

        return bccomp($amount, '0', WalletService::SCALE) <= 0
            || bccomp($amount, self::MAX_REWARD_PER_ENTRY, WalletService::SCALE) > 0;
    }
}
