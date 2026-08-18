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

    /**
     * @Apidoc\Title("Retention Analysis")
     * @Apidoc\Url("/admin/analytics/retention")
     * @Apidoc\Method("GET")
     * @Apidoc\Query(name="days",type="integer",require=false,desc="Days back (default 30)")
     */
    public function retention(Request $request): Response
    {
        $days = (int) $request->input('days', 30);
        $data = [];
        foreach ([1, 3, 7, 30] as $d) {
            if ($d > $days) break;
            $cohortDate = date('Y-m-d', strtotime("-{$days} days"));
            $endDate = date('Y-m-d', strtotime("-" . ($days - $d) . " days"));

            $cohort = \app\model\User::whereDate('created_at', $cohortDate)->count();
            if ($cohort === 0) { $data["D{$d}"] = '0%'; continue; }

            $active = \app\model\UserSession::whereDate('created_at', '>=', $cohortDate)
                ->whereDate('created_at', '<=', $endDate)
                ->whereIn('user_id', function($q) use ($cohortDate) {
                    $q->select('id')->from('user')->whereDate('created_at', $cohortDate);
                })->distinct('user_id')->count('user_id');

            $data["D{$d}"] = round($active / $cohort * 100, 1) . '%';
        }
        return $this->success($data);
    }

    /**
     * @Apidoc\Title("Conversion Funnel")
     * @Apidoc\Url("/admin/analytics/funnel")
     * @Apidoc\Method("GET")
     * @Apidoc\Query(name="days",type="integer",require=false,desc="Days back (default 30)")
     */
    public function funnel(Request $request): Response
    {
        $days = (int) $request->input('days', 30);
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $registered = \app\model\User::where('created_at', '>=', $since)->count();
        $deposited = \app\model\DepositOrder::where('created_at', '>=', $since)->where('status', 'confirmed')->distinct('user_id')->count('user_id');
        $exchanged = \app\model\ExchangeRecord::where('created_at', '>=', $since)->distinct('user_id')->count('user_id');
        $played = \app\model\GamePlayLog::where('created_at', '>=', $since)->distinct('user_id')->count('user_id');

        $base = $registered > 0 ? $registered : 1;
        return $this->success([
            ['step' => 'register', 'count' => $registered, 'rate' => '100%'],
            ['step' => 'first_deposit', 'count' => $deposited, 'rate' => round($deposited / $base * 100, 1) . '%'],
            ['step' => 'first_exchange', 'count' => $exchanged, 'rate' => round($exchanged / $base * 100, 1) . '%'],
            ['step' => 'first_game', 'count' => $played, 'rate' => round($played / $base * 100, 1) . '%'],
        ]);
    }

    /**
     * @Apidoc\Title("ARPU/ARPPU Trend")
     * @Apidoc\Url("/admin/analytics/arpu")
     * @Apidoc\Method("GET")
     * @Apidoc\Query(name="days",type="integer",require=false,desc="Days back (default 30)")
     */
    public function arpu(Request $request): Response
    {
        $days = (int) $request->input('days', 30);
        $dates = [];
        $arpuSeries = [];
        $arppuSeries = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dates[] = $date;

            $revenue = (float) (\app\model\DepositOrder::whereDate('created_at', $date)->where('status', 'confirmed')->sum('platform_amount') ?? '0');
            $totalUsers = \app\model\User::whereDate('created_at', '<=', $date)->count();
            $payingUsers = \app\model\DepositOrder::whereDate('created_at', $date)->where('status', 'confirmed')->distinct('user_id')->count('user_id');

            $arpuSeries[] = $totalUsers > 0 ? round($revenue / $totalUsers, 4) : 0;
            $arppuSeries[] = $payingUsers > 0 ? round($revenue / $payingUsers, 2) : 0;
        }

        return $this->success(['dates' => $dates, 'arpu' => $arpuSeries, 'arppu' => $arppuSeries]);
    }

    /**
     * @Apidoc\Title("Game Economy Indicators")
     * @Apidoc\Url("/admin/analytics/economy")
     * @Apidoc\Method("GET")
     */
    public function economy(Request $request): Response
    {
        $currencies = \app\model\GameCurrency::with('game')->get();
        $items = [];
        foreach ($currencies as $c) {
            $minted = \app\model\ExchangeRecord::where('currency_id', $c->id)->where('direction', 'in')->sum('game_amount') ?? '0';
            $burned = \app\model\ExchangeRecord::where('currency_id', $c->id)->where('direction', 'out')->sum('game_amount') ?? '0';
            $circulation = bcsub($minted, $burned, 8);
            $inflation = bccomp($minted, '0', 4) > 0 ? bcmul(bcdiv(bcsub($minted, $burned, 8), $minted, 8), '100', 2) : '0';

            $items[] = [
                'game_name' => $c->game->name ?? 'Unknown',
                'currency' => $c->name, 'symbol' => $c->symbol,
                'total_minted' => $minted, 'total_burned' => $burned,
                'circulation' => $circulation, 'inflation_rate' => $inflation . '%',
            ];
        }
        return $this->success(['currencies' => $items]);
    }

}
