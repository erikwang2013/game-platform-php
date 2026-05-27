<?php

declare(strict_types=1);

namespace app\admin\controller;

use common\service\DepositLogService;
use common\service\GameDashboardService;
use common\service\ProbabilityService;
use support\Request;
use support\Response;

class AnalyticsController extends BaseController
{
    public function overview(Request $request): Response
    {
        return $this->success(['today' => GameDashboardService::overview(1), 'week' => GameDashboardService::overview(7)]);
    }

    public function gameRanking(Request $request): Response
    {
        $data = GameDashboardService::gameRanking((int)$request->input('days', 7));
        return $this->success($this->encodeIds($data, ['game_id']));
    }

    public function dauTrend(Request $request): Response
    {
        return $this->success(GameDashboardService::dauTrend((int)$request->input('days', 30)));
    }

    public function hourlyTrend(Request $request): Response
    {
        $hashid = $request->input('game_id', '');
        $gameId = $hashid ? $this->decodeId($hashid) : 0;
        return $this->success(GameDashboardService::hourlyTrend($gameId));
    }

    public function actionDistribution(Request $request): Response
    {
        $gameId = $this->decodeId($request->input('game_id', '0'));
        $hours = (int)$request->input('hours', 24);
        return $this->success(GameDashboardService::actionDistribution($gameId, $hours));
    }

    public function revenue(Request $request): Response
    {
        return $this->success(DepositLogService::revenueOverview((int)$request->input('days', 7)));
    }

    public function conversion(Request $request): Response
    {
        $data = DepositLogService::conversionByGame((int)$request->input('days', 30));
        return $this->success($this->encodeIds($data, ['game_id']));
    }

    public function probability(Request $request): Response
    {
        $a = $this->decodeId($request->input('game_a', '0'));
        $b = $this->decodeId($request->input('game_b', '0'));
        if ($a <= 0 || $b <= 0) return $this->fail('game_a and game_b required', 422);
        return $this->success(['joint' => ProbabilityService::joint(
            ['table' => 'erik_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => $a]],
            ['table' => 'erik_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => $b]],
        )]);
    }
}
