<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\DepositOrder;
use app\model\ExchangeRecord;
use app\model\GamePlayLog;
use app\model\User;
use app\model\WithdrawOrder;
use hg\apidoc\annotation as Apidoc;
use support\Redis;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("数据报表")
 * @Apidoc\Group("report")
 */
class ReportController extends BaseController
{
    private const MAX_DAYS = 90;

    /**
     * @Apidoc\Title("报表汇总")
     * @Apidoc\Desc("统计时间段内新增用户、充值、提现、兑换、游戏局数")
     * @Apidoc\Url("/admin/report/summary")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name="Authorization",require=true,desc="Bearer Token")
     * @Apidoc\Query(name="start",type="string",require=false,desc="开始日期 Y-m-d，缺省最近30天")
     * @Apidoc\Query(name="end",type="string",require=false,desc="结束日期 Y-m-d，缺省今天")
     */
    public function summary(Request $request): Response
    {
        $range = $this->normalizeDateRange($request->input('start'), $request->input('end'));
        if ($range === null) {
            return $this->fail('日期格式必须为 Y-m-d 且跨度不超过 90 天', 400);
        }
        [$start, $end] = $range;

        // Redis 缓存 5 分钟，Redis 不可用时降级为直查数据库
        $cacheKey = 'report:summary:' . $start . ':' . $end;
        try {
            $cached = Redis::get($cacheKey);
            if ($cached) {
                return $this->success(json_decode($cached, true));
            }
        } catch (\Throwable) {
        }

        $between = [$start . ' 00:00:00', $end . ' 23:59:59'];

        $data = [
            'start' => $start,
            'end' => $end,
            'new_users' => User::whereBetween('created_at', $between)->count(),
            'deposit_amount' => DepositOrder::whereBetween('created_at', $between)->where('status', 'confirmed')->sum('platform_amount') ?? '0',
            'deposit_count' => DepositOrder::whereBetween('created_at', $between)->where('status', 'confirmed')->count(),
            'withdraw_amount' => WithdrawOrder::whereBetween('created_at', $between)->whereIn('status', ['approved', 'completed'])->sum('platform_amount') ?? '0',
            'withdraw_count' => WithdrawOrder::whereBetween('created_at', $between)->whereIn('status', ['approved', 'completed'])->count(),
            'exchange_amount' => ExchangeRecord::whereBetween('created_at', $between)->sum('platform_amount') ?? '0',
            'play_count' => GamePlayLog::whereBetween('created_at', $between)->count(),
        ];

        try {
            Redis::setex($cacheKey, 300, json_encode($data, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
        }

        return $this->success($data);
    }

    /**
     * @Apidoc\Title("日报表")
     * @Apidoc\Desc("按日聚合返回统计明细，0 填充无数据日期")
     * @Apidoc\Url("/admin/report/daily")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name="Authorization",require=true,desc="Bearer Token")
     * @Apidoc\Query(name="start",type="string",require=false,desc="开始日期 Y-m-d，缺省最近30天")
     * @Apidoc\Query(name="end",type="string",require=false,desc="结束日期 Y-m-d，缺省今天")
     */
    public function daily(Request $request): Response
    {
        $range = $this->normalizeDateRange($request->input('start'), $request->input('end'));
        if ($range === null) {
            return $this->fail('日期格式必须为 Y-m-d 且跨度不超过 90 天', 400);
        }
        [$start, $end] = $range;

        $cacheKey = 'report:daily:' . $start . ':' . $end;
        try {
            $cached = Redis::get($cacheKey);
            if ($cached) {
                return $this->success(json_decode($cached, true));
            }
        } catch (\Throwable) {
        }

        $rows = $this->dailyRows($start, $end);

        try {
            Redis::setex($cacheKey, 300, json_encode($rows, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
        }

        return $this->success(['start' => $start, 'end' => $end, 'rows' => $rows]);
    }

    /**
     * @Apidoc\Title("日报表导出")
     * @Apidoc\Desc("导出日报表 CSV，UTF-8 BOM 保证 Excel 打开不乱码")
     * @Apidoc\Url("/admin/report/export")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name="Authorization",require=true,desc="Bearer Token")
     * @Apidoc\Query(name="start",type="string",require=false,desc="开始日期 Y-m-d，缺省最近30天")
     * @Apidoc\Query(name="end",type="string",require=false,desc="结束日期 Y-m-d，缺省今天")
     * @Apidoc\Query(name="format",type="string",require=false,desc="导出格式，缺省 excel(CSV)")
     */
    public function export(Request $request): Response
    {
        $range = $this->normalizeDateRange($request->input('start'), $request->input('end'));
        if ($range === null) {
            return $this->fail('日期格式必须为 Y-m-d 且跨度不超过 90 天', 400);
        }
        [$start, $end] = $range;

        $rows = $this->dailyRows($start, $end);

        $lines = [['日期', '新增用户', '充值金额', '充值笔数', '提现金额', '提现笔数', '兑换金额', '游戏局数']];
        foreach ($rows as $row) {
            $lines[] = [
                $row['date'], $row['new_users'], $row['deposit_amount'], $row['deposit_count'],
                $row['withdraw_amount'], $row['withdraw_count'], $row['exchange_amount'], $row['play_count'],
            ];
        }

        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM
        foreach ($lines as $line) {
            $csv .= implode(',', $line) . "\r\n";
        }

        $filename = 'report_' . $start . '_' . $end . '.csv';
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * 解析并校验日期范围：缺省最近 30 天；格式非法、start>end 或跨度超限返回 null
     */
    private function normalizeDateRange($start, $end, int $maxDays = self::MAX_DAYS): ?array
    {
        $start = (string) ($start ?: date('Y-m-d', strtotime('-29 days')));
        $end = (string) ($end ?: date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return null;
        }
        $s = strtotime($start);
        $e = strtotime($end);
        // date() 回写校验，拦截 2026-02-31 这类会被 strtotime 自动滚动的非法日期
        if ($s === false || $e === false || date('Y-m-d', $s) !== $start || date('Y-m-d', $e) !== $end || $s > $e) {
            return null;
        }
        if (($e - $s) / 86400 + 1 > $maxDays) {
            return null;
        }
        return [$start, $end];
    }

    /**
     * 生成 [start, end] 闭区间的日期序列
     */
    private function dateSeries(string $start, string $end): array
    {
        $dates = [];
        for ($t = strtotime($start); $t <= strtotime($end); $t += 86400) {
            $dates[] = date('Y-m-d', $t);
        }
        return $dates;
    }

    /**
     * 按日聚合报表数据，0 填充缺失日期
     */
    private function dailyRows(string $start, string $end): array
    {
        $between = [$start . ' 00:00:00', $end . ' 23:59:59'];

        $newUsers = User::whereBetween('created_at', $between)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
            ->groupBy('date')
            ->pluck('cnt', 'date');

        $deposits = DepositOrder::whereBetween('created_at', $between)->where('status', 'confirmed')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt, SUM(platform_amount) as total')
            ->groupBy('date')
            ->get()->keyBy('date');

        $withdraws = WithdrawOrder::whereBetween('created_at', $between)->whereIn('status', ['approved', 'completed'])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt, SUM(platform_amount) as total')
            ->groupBy('date')
            ->get()->keyBy('date');

        $exchanges = ExchangeRecord::whereBetween('created_at', $between)
            ->selectRaw('DATE(created_at) as date, SUM(platform_amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $plays = GamePlayLog::whereBetween('created_at', $between)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')
            ->groupBy('date')
            ->pluck('cnt', 'date');

        $rows = [];
        foreach ($this->dateSeries($start, $end) as $date) {
            $rows[] = [
                'date' => $date,
                'new_users' => (int) ($newUsers[$date] ?? 0),
                'deposit_amount' => $deposits[$date]->total ?? '0',
                'deposit_count' => (int) ($deposits[$date]->cnt ?? 0),
                'withdraw_amount' => $withdraws[$date]->total ?? '0',
                'withdraw_count' => (int) ($withdraws[$date]->cnt ?? 0),
                'exchange_amount' => $exchanges[$date] ?? '0',
                'play_count' => (int) ($plays[$date] ?? 0),
            ];
        }
        return $rows;
    }
}
