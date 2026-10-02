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

#[Apidoc\Title("数据分析")]
#[Apidoc\Group("analytics")]
class AnalyticsController extends BaseController
{
    /** 回溯天数上界：与 ReportController::MAX_DAYS 同口径（报表端点的日期跨度限制也是 90 天） */
    private const MAX_DAYS = 90;

    #[Apidoc\Title("平台总览")]
    #[Apidoc\Url("/admin/v1/analytics/overview")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer 令牌")]
    public function overview(Request $request): Response
    {
        return $this->success(['today' => GameDashboardService::overview(1), 'week' => GameDashboardService::overview(7)]);
    }

    #[Apidoc\Title("游戏排行")]
    #[Apidoc\Url("/admin/v1/analytics/game-ranking")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer 令牌")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "回溯天数（默认 7）")]
    public function gameRanking(Request $request): Response
    {
        $data = GameDashboardService::gameRanking((int)$request->input('days', 7));
        return $this->success($this->encodeIds($data, ['game_id']));
    }

    #[Apidoc\Title("DAU 趋势")]
    #[Apidoc\Url("/admin/v1/analytics/dau-trend")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer 令牌")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "回溯天数（默认 30）")]
    public function dauTrend(Request $request): Response
    {
        return $this->success(GameDashboardService::dauTrend((int)$request->input('days', 30)));
    }

    #[Apidoc\Title("小时趋势")]
    #[Apidoc\Url("/admin/v1/analytics/hourly-trend")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer 令牌")]
    #[Apidoc\Query(name: "game_id", type: "string", require: false, desc: "游戏 hashid（空=全部）")]
    public function hourlyTrend(Request $request): Response
    {
        $hashid = $request->input('game_id', '');
        $gameId = $hashid ? $this->decodeId($hashid) : 0;
        return $this->success(GameDashboardService::hourlyTrend($gameId));
    }

    #[Apidoc\Title("行为分布")]
    #[Apidoc\Url("/admin/v1/analytics/action-distribution")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer 令牌")]
    #[Apidoc\Query(name: "game_id", type: "string", require: false, desc: "游戏 hashid（空=全部）")]
    #[Apidoc\Query(name: "hours", type: "integer", require: false, desc: "回溯小时数（默认 24）")]
    public function actionDistribution(Request $request): Response
    {
        // 空值即"全部游戏"(gameId=0)，与 hourlyTrend 一致；直接 decode('0') 会抛 400 无效的加密ID
        $hashid = $request->input('game_id', '');
        $gameId = $hashid ? $this->decodeId($hashid) : 0;
        $hours = (int)$request->input('hours', 24);
        return $this->success(GameDashboardService::actionDistribution($gameId, $hours));
    }

    #[Apidoc\Title("营收总览")]
    #[Apidoc\Url("/admin/v1/analytics/revenue")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer 令牌")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "回溯天数（默认 7）")]
    public function revenue(Request $request): Response
    {
        return $this->success(DepositLogService::revenueOverview((int)$request->input('days', 7)));
    }

    #[Apidoc\Title("分游戏转化")]
    #[Apidoc\Url("/admin/v1/analytics/conversion")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer 令牌")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "回溯天数（默认 30）")]
    public function conversion(Request $request): Response
    {
        $data = DepositLogService::conversionByGame((int)$request->input('days', 30));
        return $this->success($this->encodeIds($data, ['game_id']));
    }

    #[Apidoc\Title("联合概率")]
    #[Apidoc\Url("/admin/v1/analytics/probability")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Header(name: "Authorization", require: true, desc: "Bearer 令牌")]
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

    #[Apidoc\Title("留存分析")]
    #[Apidoc\Url("/admin/v1/analytics/retention")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "回溯天数（默认 30，上限 90）")]
    public function retention(Request $request): Response
    {
        // 与 arpu 同口径夹上界：这里不放大查询条数（循环固定 ≤4 轮），但 days=100000 会让窗口变成
        // 274 年 —— 每条查询退化成全量扫描、cohort 结果集可能整表进 PHP，是同一类资源耗尽面。
        $days = min(self::MAX_DAYS, max(1, (int) $request->input('days', 30)));
        $data = [];
        foreach ([1, 3, 7, 30] as $d) {
            if ($d > $days) break;
            $cohortDate = date('Y-m-d', strtotime("-{$days} days"));
            $endDate = date('Y-m-d', strtotime("-" . ($days - $d) . " days"));

            // 整天区间一律写成裸列的 [00:00:00, 23:59:59] 闭区间：whereDate 会被编译成
            // date(created_at) = ?，列被函数包住 ⇒ 索引失效（本文件原有 7 处，逐条改掉）。
            $cohortBetween = [$cohortDate . ' 00:00:00', $cohortDate . ' 23:59:59'];

            $cohort = \common\model\User::whereBetween('created_at', $cohortBetween)->count();
            if ($cohort === 0) { $data["D{$d}"] = '0%'; continue; }

            $active = \common\model\UserSession::where('logged_in_at', '>=', $cohortDate . ' 00:00:00')
                ->where('logged_in_at', '<=', $endDate . ' 23:59:59')
                ->whereIn('user_id', function($q) use ($cohortBetween) {
                    $q->select('id')->from('user')->whereBetween('created_at', $cohortBetween);
                })->distinct('user_id')->count('user_id');

            $data["D{$d}"] = self::pctDisplay((string) $active, (string) $cohort, 1);
        }
        return $this->success($data);
    }

    #[Apidoc\Title("转化漏斗")]
    #[Apidoc\Url("/admin/v1/analytics/funnel")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "回溯天数（默认 30，上限 90）")]
    public function funnel(Request $request): Response
    {
        // 同 retention：查询条数固定 4 条，但超宽窗口会把「扫描宽度」放大到全表
        $days = min(self::MAX_DAYS, max(1, (int) $request->input('days', 30)));
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

    #[Apidoc\Title("ARPU/ARPPU 趋势")]
    #[Apidoc\Url("/admin/v1/analytics/arpu")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Query(name: "days", type: "integer", require: false, desc: "回溯天数（默认 30，上限 90）")]
    public function arpu(Request $request): Response
    {
        // days 必须夹上界：原实现每天 3 条查询，?days=100000 就是 30 万条查询 + 30 万元素数组，
        // 而 RateLimit 只按请求条数限流，挡不住单请求内的放大。
        $days = min(self::MAX_DAYS, max(1, (int) $request->input('days', 30)));
        $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $end = date('Y-m-d');
        $between = [$start . ' 00:00:00', $end . ' 23:59:59'];

        // 一条 GROUP BY 取代原先的逐日查询（默认 30 天 = 90 条 whereDate 全表扫描）。
        // 日期来自 created_at 裸列，whereBetween 走得了索引；DATE() 只出现在投影里，不影响 sargable。
        $daily = \common\model\DepositOrder::whereBetween('created_at', $between)->where('status', 'confirmed')
            ->selectRaw('DATE(created_at) as date, SUM(platform_amount) as revenue, COUNT(DISTINCT user_id) as payers')
            ->groupBy('date')
            ->get()->keyBy('date');

        // 累计注册数 = 区间前的存量 + 区间内逐日新增。原实现是逐日 whereDate 的 `<=` 累积计数，
        // 与窗口起点无关；拆成这两段后逐日累加，结果相同。
        $cumulative = (int) \common\model\User::where('created_at', '<', $between[0])->count();
        $newUsers = \common\model\User::whereBetween('created_at', $between)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
            ->groupBy('date')
            ->pluck('cnt', 'date');

        $dates = [];
        $arpuSeries = [];
        $arppuSeries = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dates[] = $date;

            // 无数据的日期不会出现在 GROUP BY 结果里，必须补 0（原逐日查询缺日天然为 0，不能变成缺行）
            $cumulative += (int) ($newUsers[$date] ?? 0);
            $day = $daily[$date] ?? null;
            $revenue = (string) ($day->revenue ?? '0');
            $payingUsers = (int) ($day->payers ?? 0);

            // 金额运算 bcmath 完成，末尾 (float) 仅为保持 JSON 数值型输出
            $arpuSeries[] = $cumulative > 0 ? (float) BcMath::round(bcdiv($revenue, (string) $cumulative, 5), 4) : 0;
            $arppuSeries[] = $payingUsers > 0 ? (float) BcMath::round(bcdiv($revenue, (string) $payingUsers, 5), 2) : 0;
        }

        return $this->success(['dates' => $dates, 'arpu' => $arpuSeries, 'arppu' => $arppuSeries]);
    }

    #[Apidoc\Title("游戏经济指标")]
    #[Apidoc\Url("/admin/v1/analytics/economy")]
    #[Apidoc\Method("GET")]
    public function economy(Request $request): Response
    {
        $currencies = \common\model\GameCurrency::with('game')->get();

        // 一条 GROUP BY 取代「每币种 2 条 SUM」（原 1+2N 次查询）。无记录的币种不会出现在结果里，
        // 取值处按缺省 '0' 兜底，与原先逐条 sum() 落到 int 0 再转型的结果一致。
        $sums = \common\model\ExchangeRecord::selectRaw('currency_id, direction, SUM(game_amount) as total')
            ->groupBy('currency_id', 'direction')
            ->get()
            ->keyBy(fn ($r) => $r->currency_id . ':' . $r->direction);

        $items = [];
        foreach ($currencies as $c) {
            // SUM 聚合在无匹配行时回 NULL（逐条 sum() 则是 int 0）——两种都要 `?? '0'` 兜住，
            // 否则 bcsub 收 null/int 抛 TypeError。与 arpu()、CouponController 同款强制转型，别省。
            $minted = (string) ($sums[$c->id . ':in']->total ?? '0');
            $burned = (string) ($sums[$c->id . ':out']->total ?? '0');
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
