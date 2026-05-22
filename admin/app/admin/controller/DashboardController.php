<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use app\model\AdminUser;
use app\common\EncryptionService;
use app\model\OperationLog;
use app\model\User;
use app\model\Game;
use app\model\DepositOrder;
use app\model\WithdrawOrder;
use app\model\ExchangeRecord;
use support\Redis;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("仪表盘")
 * @Apidoc\Group("dashboard")
 */
class DashboardController extends BaseController
{
    /**
     * @Apidoc\Title("仪表盘")
     * @Apidoc\Desc("获取管理后台仪表盘数据，包含统计、趋势、分布和最近日志")
     * @Apidoc\Url("/admin/dashboard")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     */
    public function index(Request $request): Response
    {
        // Redis 缓存 5 分钟，避免每次请求跑 5+ 条 SQL
        $cacheKey = 'dashboard:data';
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return $this->success(json_decode($cached, true));
        }

        $today = date('Y-m-d');
        $startOfRange = date('Y-m-d', strtotime('-29 days'));

        $data = [
            'stats' => $this->getStats($today),
            'trends' => $this->getTrends($startOfRange),
            'distribution' => $this->getDistribution(),
            'recent_logs' => $this->getRecentLogs(),
        ];

        Redis::setex($cacheKey, 300, json_encode($data, JSON_UNESCAPED_UNICODE));

        return $this->success($data);
    }

    /**
     * @Apidoc\Title("平台仪表盘")
     * @Apidoc\Desc("获取平台运营总览数据")
     * @Apidoc\Url("/admin/dashboard/platform")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     */
    public function platform(Request $request): Response
    {
        $totalUsers = User::count();
        $activeUsers = User::where('last_login_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))->count();
        $totalGames = Game::where('status', 1)->count();
        $pendingWithdraws = WithdrawOrder::where('status', 'pending')->count();

        $todayDeposits = DepositOrder::whereDate('created_at', date('Y-m-d'))
            ->where('status', 'confirmed')
            ->sum('platform_amount') ?? '0.0000';

        $todayWithdraws = WithdrawOrder::whereDate('created_at', date('Y-m-d'))
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
        $totalUsers = AdminUser::count();
        $todayNew = AdminUser::whereDate('created_at', $today)->count();
        $todayActive = AdminUser::whereDate('last_login_at', $today)->count();
        $todayLogs = OperationLog::whereDate('created_at', $today)->count();

        return [
            [
                'label' => '用户总数',
                'value' => (string) $totalUsers,
                'icon' => 'people',
                'color' => '#1677FF',
                'trend' => $this->calcTrend(AdminUser::class),
            ],
            [
                'label' => '今日新增',
                'value' => (string) $todayNew,
                'icon' => 'person_add',
                'color' => '#52C41A',
            ],
            [
                'label' => '活跃用户',
                'value' => (string) $todayActive,
                'icon' => 'bolt',
                'color' => '#FA8C16',
            ],
            [
                'label' => '操作日志',
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
        $dailyNewUsers = AdminUser::whereDate('created_at', '>=', $startOfRange)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $cumulative = AdminUser::whereDate('created_at', '<', $startOfRange)->count();
        foreach ($dates as $date) {
            $cumulative += $dailyNewUsers[$date] ?? 0;
            $userGrowth[] = $cumulative;
        }

        // 一次查询获取操作日志每日数量
        $dailyLogs = OperationLog::whereDate('created_at', '>=', $startOfRange)
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
                ['name' => '累计用户', 'data' => $userGrowth, 'color' => '#1677FF'],
                ['name' => '操作日志', 'data' => $logCounts, 'color' => '#52C41A'],
            ],
        ];
    }

    private function getDistribution(): array
    {
        return [
            'user_status' => [
                ['name' => '启用', 'value' => AdminUser::where('status', 1)->count()],
                ['name' => '禁用', 'value' => AdminUser::where('status', 0)->count()],
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
                $data['user_name'] = $log->user->username ?? '系统';
                unset($data['user'], $data['user_id']);
                return $data;
            })
            ->toArray();
    }

    private function calcTrend(string $modelClass): ?float
    {
        $today = $modelClass::whereDate('created_at', date('Y-m-d'))->count();
        $yesterday = $modelClass::whereDate('created_at', date('Y-m-d', strtotime('-1 day')))->count();

        if ($yesterday === 0) {
            return $today > 0 ? 100.0 : 0.0;
        }
        return round(($today - $yesterday) / $yesterday * 100, 1);
    }
}
