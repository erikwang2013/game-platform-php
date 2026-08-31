<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\model\Activity;
use app\model\ActivityParticipation;
use app\service\ActivityService;
use app\service\FeatureFlag;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("运营活动")
 * @Apidoc\Group("activity")
 */
class ActivityController extends BaseController
{
    /**
     * @Apidoc\Title("活动列表")
     * @Apidoc\Url("/api/activities/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function list(Request $request): Response
    {
        $now    = date('Y-m-d H:i:s');
        $userId = $request->userId;

        $activities = Activity::where('status', Activity::STATUS_ENABLED)
            ->where(function ($query) use ($now) {
                $query->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->orderBy('id', 'desc')
            ->get()
            ->filter(function ($activity) use ($userId) {
                // 灰度：rollout_percent=0 完全不返回
                return FeatureFlag::inRollout('activity_' . $activity->id, (string) $userId, (int) $activity->rollout_percent);
            })
            ->map(function ($activity) {
                $data = $activity->toArray();
                $data['id'] = $this->encodeId($activity->id);
                if (!empty($activity->game_id) && $activity->game_id > 0) {
                    $data['game_id'] = $this->encodeId((int) $activity->game_id);
                }
                return $data;
            })
            ->values();

        return $this->success(['list' => $activities]);
    }

    /**
     * @Apidoc\Title("活动详情")
     * @Apidoc\Url("/api/activities/{hashid}")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function detail(Request $request, string $hashid): Response
    {
        $activity = Activity::find($this->decodeId($hashid));
        if (!$activity || $activity->status !== Activity::STATUS_ENABLED) {
            return $this->fail('Activity not found', 404);
        }

        $periodKey = date('Y-m-d');
        $participation = ActivityParticipation::where('user_id', $request->userId)
            ->where('activity_id', $activity->id)
            ->where('period_key', $periodKey)
            ->first();

        $data = $activity->toArray();
        $data['id'] = $this->encodeId($activity->id);
        $data['participation'] = $participation
            ? ['current' => (int) $participation->current, 'target' => (int) $participation->target, 'status' => $participation->status]
            : ['current' => 0, 'target' => 0, 'status' => 'progressing'];

        return $this->success($data);
    }

    /**
     * @Apidoc\Title("签到")
     * @Apidoc\Url("/api/activities/{hashid}/checkin")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     */
    public function checkin(Request $request, string $hashid): Response
    {
        try {
            $result = ActivityService::checkin((int) $request->userId, $this->decodeId($hashid));
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 400);
        }

        return $this->success($result);
    }

    /**
     * @Apidoc\Title("我的活动进度")
     * @Apidoc\Url("/api/activities/progress")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function progress(Request $request): Response
    {
        $userId = $request->userId;
        $periodKey = date('Y-m-d');

        $rows = ActivityParticipation::where('user_id', $userId)
            ->where('period_key', $periodKey)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($row) {
                return [
                    'activity_id' => $this->encodeId((int) $row->activity_id),
                    'current'     => (int) $row->current,
                    'target'      => (int) $row->target,
                    'status'      => $row->status,
                ];
            });

        return $this->success(['list' => $rows]);
    }
}
