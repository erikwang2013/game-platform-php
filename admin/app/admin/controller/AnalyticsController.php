<?php

declare(strict_types=1);

namespace app\admin\controller;

use common\service\DepositLogService;
use common\service\GameDashboardService;
use common\service\ProbabilityService;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("Analytics")
 * @Apidoc\Group("analytics")
 */
class AnalyticsController extends BaseController
{
    /**
     * @Apidoc\Title("Platform Overview")
     * @Apidoc\Url("/admin/analytics/overview")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name="Authorization",require=true,desc="Bearer Token")
     */
    public function overview(Request $request): Response
    {
        return $this->success(['today' => GameDashboardService::overview(1), 'week' => GameDashboardService::overview(7)]);
    }

    /**
     * @Apidoc\Title("Game Ranking")
     * @Apidoc\Url("/admin/analytics/game-ranking")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name="Authorization",require=true,desc="Bearer Token")
     * @Apidoc\Query(name="days",type="integer",require=false,desc="Days back (default 7)")
     */
    public function gameRanking(Request $request): Response
    {
        $data = GameDashboardService::gameRanking((int)$request->input('days', 7));
        return $this->success($this->encodeIds($data, ['game_id']));
    }

    /**
     * @Apidoc\Title("DAU Trend")
     * @Apidoc\Url("/admin/analytics/dau-trend")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name="Authorization",require=true,desc="Bearer Token")
     * @Apidoc\Query(name="days",type="integer",require=false,desc="Days back (default 30)")
     */
    public function dauTrend(Request $request): Response
    {
        return $this->success(GameDashboardService::dauTrend((int)$request->input('days', 30)));
    }

    /**
     * @Apidoc\Title("Hourly Trend")
     * @Apidoc\Url("/admin/analytics/hourly-trend")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name="Authorization",require=true,desc="Bearer Token")
     * @Apidoc\Query(name="game_id",type="string",require=false,desc="Game hashid (empty=all)")
     */
    public function hourlyTrend(Request $request): Response
    {
        $hashid = $request->input('game_id', '');
        $gameId = $hashid ? $this->decodeId($hashid) : 0;
        return $this->success(GameDashboardService::hourlyTrend($gameId));
    }

    /**
     * @Apidoc\Title("Action Distribution")
     * @Apidoc\Url("/admin/analytics/action-distribution")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name="Authorization",require=true,desc="Bearer Token")
     * @Apidoc\Query(name="game_id",type="string",require=true,desc="Game hashid")
     * @Apidoc\Query(name="hours",type="integer",require=false,desc="Hours back (default 24)")
     */
    public function actionDistribution(Request $request): Response
    {
        $gameId = $this->decodeId($request->input('game_id', '0'));
        $hours = (int)$request->input('hours', 24);
        return $this->success(GameDashboardService::actionDistribution($gameId, $hours));
    }

    /**
     * @Apidoc\Title("Revenue Overview")
     * @Apidoc\Url("/admin/analytics/revenue")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name="Authorization",require=true,desc="Bearer Token")
     * @Apidoc\Query(name="days",type="integer",require=false,desc="Days back (default 7)")
     */
    public function revenue(Request $request): Response
    {
        return $this->success(DepositLogService::revenueOverview((int)$request->input('days', 7)));
    }

    /**
     * @Apidoc\Title("Conversion by Game")
     * @Apidoc\Url("/admin/analytics/conversion")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name="Authorization",require=true,desc="Bearer Token")
     * @Apidoc\Query(name="days",type="integer",require=false,desc="Days back (default 30)")
     */
    public function conversion(Request $request): Response
    {
        $data = DepositLogService::conversionByGame((int)$request->input('days', 30));
        return $this->success($this->encodeIds($data, ['game_id']));
    }

    /**
     * @Apidoc\Title("Joint Probability")
     * @Apidoc\Url("/admin/analytics/probability")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name="Authorization",require=true,desc="Bearer Token")
     * @Apidoc\Query(name="game_a",type="string",require=true,desc="Game A hashid")
     * @Apidoc\Query(name="game_b",type="string",require=true,desc="Game B hashid")
     */
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
