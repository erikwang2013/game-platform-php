<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use common\BcMath;
use common\service\DepositLogService;
use common\service\GameDashboardService;
use common\service\ProbabilityService;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

#[Apidoc\Title("Analytics")]
#[Apidoc\Group("analytics")]
class AnalyticsController extends BaseController
{
    #[Apidoc\Title("Platform Overview")]
    #[Apidoc\Url("/admin/v1/analytics/overview")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer Token")]
    public function overview(Request $request): Response
    {
        return $this->success(['today' => GameDashboardService::overview(1), 'week' => GameDashboardService::overview(7)]);
    }

    #[Apidoc\Title("Game Ranking")]
    #[Apidoc\Url("/admin/v1/analytics/game-ranking")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer Token")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "Days back (default 7)")]
    public function gameRanking(Request $request): Response
    {
        $data = GameDashboardService::gameRanking((int)$request->input('days', 7));
        return $this->success($this->encodeIds($data, ['game_id']));
    }

    #[Apidoc\Title("DAU Trend")]
    #[Apidoc\Url("/admin/v1/analytics/dau-trend")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer Token")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "Days back (default 30)")]
    public function dauTrend(Request $request): Response
    {
        return $this->success(GameDashboardService::dauTrend((int)$request->input('days', 30)));
    }

    #[Apidoc\Title("Hourly Trend")]
    #[Apidoc\Url("/admin/v1/analytics/hourly-trend")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer Token")]
    #[Apidoc\Query(name: "game_id", type: "string", require: false, desc: "Game hashid (empty=all)")]
    public function hourlyTrend(Request $request): Response
    {
        $hashid = $request->input('game_id', '');
        $gameId = $hashid ? $this->decodeId($hashid) : 0;
        return $this->success(GameDashboardService::hourlyTrend($gameId));
    }

    #[Apidoc\Title("Action Distribution")]
    #[Apidoc\Url("/admin/v1/analytics/action-distribution")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer Token")]
    #[Apidoc\Query(name: "game_id", type: "string", require: false, desc: "Game hashid (empty=all)")]
    #[Apidoc\Query(name: "hours", type: "integer", require: false, desc: "Hours back (default 24)")]
    public function actionDistribution(Request $request): Response
    {
        // 空值即"全部游戏"(gameId=0)，与 hourlyTrend 一致；直接 decode('0') 会抛 400 无效的加密ID
        $hashid = $request->input('game_id', '');
        $gameId = $hashid ? $this->decodeId($hashid) : 0;
        $hours = (int)$request->input('hours', 24);
        return $this->success(GameDashboardService::actionDistribution($gameId, $hours));
    }

    #[Apidoc\Title("Revenue Overview")]
    #[Apidoc\Url("/admin/v1/analytics/revenue")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer Token")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "Days back (default 7)")]
    public function revenue(Request $request): Response
    {
        return $this->success(DepositLogService::revenueOverview((int)$request->input('days', 7)));
    }

    #[Apidoc\Title("Conversion by Game")]
    #[Apidoc\Url("/admin/v1/analytics/conversion")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer Token")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "Days back (default 30)")]
    public function conversion(Request $request): Response
    {
        $data = DepositLogService::conversionByGame((int)$request->input('days', 30));
        return $this->success($this->encodeIds($data, ['game_id']));
    }

    #[Apidoc\Title("Joint Probability")]
    #[Apidoc\Url("/admin/v1/analytics/probability")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer Token")]
    #[Apidoc\Query(name: "game_a", type: "string", require: false, desc: "Game A hashid（空=近 7 天玩得最多的一款）")]
    #[Apidoc\Query(name: "game_b", type: "string", require: false, desc: "Game B hashid（空=近 7 天玩得第二多的一款）")]
    public function probability(Request $request): Response
    {
        $ha = (string) $request->input('game_a', '');
        $hb = (string) $request->input('game_b', '');
        $a = $ha !== '' ? $this->decodeId($ha) : 0;
        $b = $hb !== '' ? $this->decodeId($hb) : 0;

        // 缺参不再 422：与其余 analytics 端点（参数可选、有默认）保持一致。
        // 两棵 Web 管理端的「概率」标签是无参通用渲染器，必填参数会让这个端点永远打不开。
        if ($a <= 0 || $b <= 0) {
            $top = GameDashboardService::gameRanking(7);
            if ($a <= 0) $a = (int) ($top[0]['game_id'] ?? 0);
            if ($b <= 0) $b = (int) ($top[1]['game_id'] ?? 0);
        }

        // 不足两款（或显式指定了同一个游戏）时联合概率无意义，返回零值而非报错
        if ($a <= 0 || $b <= 0 || $a === $b) {
            return $this->success([
                'game_a_name' => '', 'game_b_name' => '',
                'joint_probability' => 0.0, 'confidence' => 0.0,
            ]);
        }

        $joint = ProbabilityService::joint(
            ['table' => 'game_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => $a]],
            ['table' => 'game_game_play_log', 'alias' => 'user_id', 'where' => ['game_id' => $b]],
        );
        $ga = \common\model\Game::find($a);
        $gb = \common\model\Game::find($b);

        // 拍平：通用渲染器只认顶层标量（Angular 的 scalarsOf / React 的 AutoView），嵌套对象会渲染成空
        return $this->success([
            'game_a_name'       => $ga->name ?? $ga->title ?? ('game#' . $a),
            'game_b_name'       => $gb->name ?? $gb->title ?? ('game#' . $b),
            'joint_probability' => $joint['joint_probability'],
            'confidence'        => $joint['confidence'],
        ]);
    }

    #[Apidoc\Title("Retention Analysis")]
    #[Apidoc\Url("/admin/v1/analytics/retention")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "Days back (default 30)")]
    public function retention(Request $request): Response
    {
        $days = (int) $request->input('days', 30);
        $data = [];
        foreach ([1, 3, 7, 30] as $d) {
            if ($d > $days) break;
            $cohortDate = date('Y-m-d', strtotime("-{$days} days"));
            $endDate = date('Y-m-d', strtotime("-" . ($days - $d) . " days"));

            $cohort = \common\model\User::whereDate('created_at', $cohortDate)->count();
            if ($cohort === 0) { $data["D{$d}"] = '0%'; continue; }

            $active = \common\model\UserSession::whereDate('logged_in_at', '>=', $cohortDate)
                ->whereDate('logged_in_at', '<=', $endDate)
                ->whereIn('user_id', function($q) use ($cohortDate) {
                    $q->select('id')->from('user')->whereDate('created_at', $cohortDate);
                })->distinct('user_id')->count('user_id');

            $data["D{$d}"] = self::pctDisplay((string) $active, (string) $cohort, 1);
        }
        return $this->success($data);
    }

    #[Apidoc\Title("Conversion Funnel")]
    #[Apidoc\Url("/admin/v1/analytics/funnel")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "Days back (default 30)")]
    public function funnel(Request $request): Response
    {
        $days = (int) $request->input('days', 30);
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $registered = \common\model\User::where('created_at', '>=', $since)->count();
        $deposited = \common\model\DepositOrder::where('created_at', '>=', $since)->where('status', 'confirmed')->distinct('user_id')->count('user_id');
        $exchanged = \common\model\ExchangeRecord::where('created_at', '>=', $since)->distinct('user_id')->count('user_id');
        $played = \common\model\GamePlayLog::where('created_at', '>=', $since)->distinct('user_id')->count('user_id');

        $base = $registered > 0 ? $registered : 1;
        return $this->success([
            ['step' => 'register', 'count' => $registered, 'rate' => '100%'],
            ['step' => 'first_deposit', 'count' => $deposited, 'rate' => self::pctDisplay((string) $deposited, (string) $base, 1)],
            ['step' => 'first_exchange', 'count' => $exchanged, 'rate' => self::pctDisplay((string) $exchanged, (string) $base, 1)],
            ['step' => 'first_game', 'count' => $played, 'rate' => self::pctDisplay((string) $played, (string) $base, 1)],
        ]);
    }

    #[Apidoc\Title("ARPU/ARPPU Trend")]
    #[Apidoc\Url("/admin/v1/analytics/arpu")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "Days back (default 30)")]
    public function arpu(Request $request): Response
    {
        $days = (int) $request->input('days', 30);
        $dates = [];
        $arpuSeries = [];
        $arppuSeries = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dates[] = $date;

            $revenue = (string) (\common\model\DepositOrder::whereDate('created_at', $date)->where('status', 'confirmed')->sum('platform_amount') ?? '0');
            $totalUsers = \common\model\User::whereDate('created_at', '<=', $date)->count();
            $payingUsers = \common\model\DepositOrder::whereDate('created_at', $date)->where('status', 'confirmed')->distinct('user_id')->count('user_id');

            // 金额运算 bcmath 完成，末尾 (float) 仅为保持 JSON 数值型输出
            $arpuSeries[] = $totalUsers > 0 ? (float) BcMath::round(bcdiv($revenue, (string) $totalUsers, 5), 4) : 0;
            $arppuSeries[] = $payingUsers > 0 ? (float) BcMath::round(bcdiv($revenue, (string) $payingUsers, 5), 2) : 0;
        }

        return $this->success(['dates' => $dates, 'arpu' => $arpuSeries, 'arppu' => $arppuSeries]);
    }

    #[Apidoc\Title("Game Economy Indicators")]
    #[Apidoc\Url("/admin/v1/analytics/economy")]
    #[Apidoc\Method("GET")]
    public function economy(Request $request): Response
    {
        $currencies = \common\model\GameCurrency::with('game')->get();
        $items = [];
        foreach ($currencies as $c) {
            // sum() 在聚合值为假时返回 int 0（不是 null），`?? '0'` 兜不住 ⇒ bcsub 收 int 抛 TypeError。
            // 实测同一库：无匹配行时 in=int 0 / out=int 0，有行时才回 string（'40.0000'）。
            // 与 arpu()、CouponController 同款强制转型，别省。
            $minted = (string) (\common\model\ExchangeRecord::where('currency_id', $c->id)->where('direction', 'in')->sum('game_amount') ?? '0');
            $burned = (string) (\common\model\ExchangeRecord::where('currency_id', $c->id)->where('direction', 'out')->sum('game_amount') ?? '0');
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

    /** 百分比展示：bcmath 计算后去 .0 尾缀，保持旧 round() 字符串格式（'87%' 而非 '87.0%'） */
    private static function pctDisplay(string $numerator, string $denominator, int $scale): string
    {
        return rtrim(rtrim(BcMath::percent($numerator, $denominator, $scale), '0'), '.') . '%';
    }
}
