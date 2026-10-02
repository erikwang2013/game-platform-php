<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use common\BcMath;
use erikwang2013\apidoc\annotation as Apidoc;
use app\model\AdminUser;
use app\model\OperationLog;
use common\model\User;
use common\model\Game;
use common\model\DepositOrder;
use common\model\WithdrawOrder;
use common\model\ExchangeRecord;
use support\Redis;
use support\Request;
use support\Response;

#[Apidoc\Title("仪表盘")]
#[Apidoc\Group("dashboard")]
class DashboardController extends BaseController
{
    #[Apidoc\Title("仪表盘")]
    #[Apidoc\Desc("获取管理后台仪表盘数据，包含统计、趋势、分布和最近日志")]
    #[Apidoc\Url("/admin/v1/dashboard")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    public function index(Request $request): Response
    {
        // Redis 缓存 5 分钟，避免每次请求跑 5+ 条 SQL；Redis 不可用时降级为直查数据库。
        //
        // ⚠ 键**必须带 locale**：缓存的 payload 里有 9 处 trans()（stats 四条的 label、
        // trends 两条 series.name、distribution 两条 name、recent_logs 的 user_name 兜底）
        // —— 从 process 级静态的 locale() 取。键写成常量时，300 秒内**首个请求者的语言**
        // 会决定所有管理员的仪表盘文案（先来一个英文请求，之后中文管理员也读英文）。
        // 同批的 ReportController:46/:111 缓存的是纯数值行、没有 trans()，不适用，别一起改。
        $cacheKey = 'dashboard:data:' . locale();
        try {
            $cached = Redis::get($cacheKey);
            if ($cached) {
                return $this->success(json_decode($cached, true));
            }
        } catch (\Throwable) {
        }

        $today = date('Y-m-d');
        $startOfRange = date('Y-m-d', strtotime('-29 days'));

        $data = [
            'stats' => $this->getStats($today),
            'trends' => $this->getTrends($startOfRange),
            'distribution' => $this->getDistribution(),
            'recent_logs' => $this->getRecentLogs(),
        ];

        try {
            Redis::setex($cacheKey, 300, json_encode($data, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
        }

        return $this->success($data);
    }

    #[Apidoc\Title("平台仪表盘")]
    #[Apidoc\Desc("获取平台运营总览数据")]
    #[Apidoc\Url("/admin/v1/dashboard/platform")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    public function platform(Request $request): Response
    {
        $totalUsers = User::count();
        $activeUsers = User::where('last_login_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))->count();
        $totalGames = Game::where('status', 1)->count();
        $pendingWithdraws = WithdrawOrder::where('status', 'pending')->count();

        // 日边界一律走**裸列半开区间** `>= 当天 00:00:00 AND < 次日 00:00:00`，**不要用 whereDate**：
        // whereDate 编译成 `date(created_at) = ?`（vendor Grammar.php:526-531），列被函数包住
        // ⇒ `idx_created_at` 直接失效、退化成全表扫。本方法查的两张表
        // （deposit_order / withdraw_order）的 created_at 都有 idx_created_at（install/install.sql 已核）。
        // 右端取**次日零点**而不是 23:59:59：今天两种写法等价（四张表都是 DATETIME(0)），
        // 但哪天有人把某张表改成 DATETIME(3)，`<= 23:59:59` 会**静默漏掉** 23:59:59.5 那一档。
        // ⚠ 本仓有两种日边界写法（此处半开 `< 次日 00:00:00`；ReportController::dailyRows 用闭区间
        //   `23:59:59`）。今天在 DATETIME(0) 列上等价；表若改成 DATETIME(3)，**闭区间那版会静默
        //   漏掉末秒的小数部分**，故新代码一律半开。两边写法有意不统一。
        // ⚠ 本方法 platform() **一条缓存都没有**（只有 index() 有 300s 缓存）⇒ 每次点开这个页签
        //   都实打实跑这几条；下面 $activeUsers 那处 last_login_at 走 idx_last_login_at
        //   （2026_10_02 迁移补的，install/install.sql 已同步）。
        $dayStart     = date('Y-m-d') . ' 00:00:00';
        $nextDayStart = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';

        $todayDeposits = DepositOrder::where('created_at', '>=', $dayStart)
            ->where('created_at', '<', $nextDayStart)
            ->where('status', 'confirmed')
            ->sum('platform_amount') ?? '0.0000';

        $todayWithdraws = WithdrawOrder::where('created_at', '>=', $dayStart)
            ->where('created_at', '<', $nextDayStart)
            ->whereIn('status', ['approved', 'completed'])
            ->sum('platform_amount') ?? '0.0000';

        $totalSpreadFee = ExchangeRecord::sum('spread_fee') ?? '0.0000';

        return $this->success([
            'total_users' => $totalUsers,
            'active_users_7d' => $activeUsers,
            'total_games' => $totalGames,
            'pending_withdraws' => $pendingWithdraws,
            'today_deposits' => $todayDeposits,
            'today_withdraws' => $todayWithdraws,
            'total_spread_fee' => $totalSpreadFee,
        ]);
    }

    private function getStats(string $today): array
    {
        $totalUsers   = AdminUser::count();
        $dayStart     = $today . ' 00:00:00';
        $nextDayStart = date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00';
        $todayNew = AdminUser::where('created_at', '>=', $dayStart)
            ->where('created_at', '<', $nextDayStart)
            ->count();
        // last_login_at 现走 idx_last_login_at（install/migrations/2026_10_02_last_login_at_index.sql
        // 给 game_user / game_admin_user 各补一条）⇒ 本条必须保持**裸列范围**写法，
        // 包成 whereDate 会让这条索引失效。（缺索引时这里曾注释为「换掉 whereDate 收益为 0」，已不成立。）
        $todayActive = AdminUser::where('last_login_at', '>=', $dayStart)
            ->where('last_login_at', '<', $nextDayStart)
            ->count();
        $todayLogs = OperationLog::where('created_at', '>=', $dayStart)
            ->where('created_at', '<', $nextDayStart)
            ->count();

        return [
            [
                'label' => trans('Total users'),
                'value' => (string) $totalUsers,
                'icon' => 'people',
                'color' => '#1677FF',
                'trend' => $this->calcTrend(AdminUser::class),
            ],
            [
                'label' => trans('New today'),
                'value' => (string) $todayNew,
                'icon' => 'person_add',
                'color' => '#52C41A',
            ],
            [
                'label' => trans('Active users'),
                'value' => (string) $todayActive,
                'icon' => 'bolt',
                'color' => '#FA8C16',
            ],
            [
                'label' => trans('Operation logs'),
                'value' => (string) $todayLogs,
                'icon' => 'description',
                'color' => '#722ED1',
            ],
        ];
    }

    private function getTrends(string $startOfRange): array
    {
        $dates = [];
        $userGrowth = [];
        $logCounts = [];

        // 生成日期序列
        for ($i = 29; $i >= 0; $i--) {
            $dates[] = date('Y-m-d', strtotime("+{$i} days", strtotime($startOfRange)));
        }

        // 一次查询获取用户每日新增数，PHP 内累加
        // selectRaw 里的 DATE(created_at) 是**分组键**、不参与过滤，留着不损索引；
        // 过滤条件必须用裸列比较（`>= 'Y-m-d 00:00:00'`），whereDate 会让 idx_created_at 失效。
        $dailyNewUsers = AdminUser::where('created_at', '>=', $startOfRange . ' 00:00:00')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $cumulative = AdminUser::where('created_at', '<', $startOfRange . ' 00:00:00')->count();
        foreach ($dates as $date) {
            $cumulative += $dailyNewUsers[$date] ?? 0;
            $userGrowth[] = $cumulative;
        }

        // 一次查询获取操作日志每日数量
        $dailyLogs = OperationLog::where('created_at', '>=', $startOfRange . ' 00:00:00')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        foreach ($dates as $date) {
            $logCounts[] = $dailyLogs[$date] ?? 0;
        }

        return [
            'dates' => $dates,
            'series' => [
                ['name' => trans('Cumulative users'), 'data' => $userGrowth, 'color' => '#1677FF'],
                ['name' => trans('Operation logs'), 'data' => $logCounts, 'color' => '#52C41A'],
            ],
        ];
    }

    private function getDistribution(): array
    {
        return [
            'user_status' => [
                ['name' => trans('Enabled'), 'value' => AdminUser::where('status', 1)->count()],
                ['name' => trans('Disabled'), 'value' => AdminUser::where('status', 0)->count()],
            ],
        ];
    }

    private function getRecentLogs(): array
    {
        return OperationLog::with('user')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                $data = $log->toArray();
                $data['id'] = $this->encodeId($data['id']);
                $data['user_name'] = $log->user->username ?? trans('System');
                // input 是**原始请求参数**。DDL 注释写着"敏感字段已脱敏"，但脱敏词表
                // （OperationLog::SENSITIVE_WORDS）只覆盖 password/token/secret 那一类密文，
                // **phone / email / real_name 不在其列**；写入侧刚补的 PII 脱敏也只作用于**新行**，
                // 存量行里的明文还在 ⇒ 只要这一列照吐，那条读取路径当下依然成立。
                // 审计页 /admin/v1/log 要留它（react 的 logs.tsx:33 是唯一渲染方），
                // 所以**不能挂模型的 $hidden**（那会把审计页那一列一起打掉）；只掐摘要端点。
                // recent_logs 先于 setex 进 $data ⇒ 缓存 blob 里自然也不含它，无需另做失效。
                unset($data['user'], $data['user_id'], $data['input']);
                return $data;
            })
            ->toArray();
    }

    private function calcTrend(string $modelClass): ?float
    {
        $now  = date('Y-m-d');
        $prev = date('Y-m-d', strtotime('-1 day'));

        // 半开区间：昨天的右端**正好是今天的左端**（$now 00:00:00），相邻两天不重叠也不漏。
        $today = $modelClass::where('created_at', '>=', $now . ' 00:00:00')
            ->where('created_at', '<', date('Y-m-d', strtotime($now . ' +1 day')) . ' 00:00:00')
            ->count();
        $yesterday = $modelClass::where('created_at', '>=', $prev . ' 00:00:00')
            ->where('created_at', '<', $now . ' 00:00:00')
            ->count();

        if ($yesterday === 0) {
            return $today > 0 ? 100.0 : 0.0;
        }
        return (float) BcMath::percent((string) ($today - $yesterday), (string) $yesterday, 1);
    }
}
