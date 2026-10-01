<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\AnalyticsController;
use common\BcMath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use support\Response;

/**
 * AnalyticsController 的三条钉子，对应两处修复：
 *
 * ① `arpu` 的 days 无上界 + 逐日循环查库。改前 `?days=30` 就是 90 条 whereDate 全表扫描
 *    （打 game_deposit_order ×2 + game_user ×1，其中 totalUsers 还是 `<=` 的累积计数、越往后扫得越多），
 *    `?days=100000` 是 30 万条查询 + 30 万元素数组。RateLimit 按请求条数限流，挡不住单请求内的放大。
 *    修法：days 夹到 [1, 90]（与本仓 ReportController::MAX_DAYS 同口径）+ 逐日循环并成一条 GROUP BY。
 *
 * ② 本文件原有的 7 处 whereDate 被 Eloquent 编译成 `date(created_at) = ?`，列被函数包住 ⇒ 索引失效。
 *    改成裸列的 [00:00:00, 23:59:59] 闭区间；arpu 那 3 处随 ① 一起消失，retention 剩 4 处逐条改。
 *
 * ⚠ 改 GROUP BY 最容易错的是「缺日」语义：原实现循环 N 天、每天查一次，没有数据的那天**天然是 0**；
 *   改成 GROUP BY 后缺日**不会出行**，必须补 0 而不是缺行。seriesMatchesLegacy 那条就是钉这个的
 *   —— 它拿**旧实现的逐日循环原文**当参照物逐值比对，改坏零填充 / 累计注册数的累加次序都会红。
 */
final class AnalyticsControllerTest extends TestCase
{
    private const MAX_DAYS = 90;

    /** 按 pid 派生 id 段：同机并行跑测试时不会与另一次运行抢同一批主键 */
    private int $uidBase;
    private int $oidBase;
    private array $orderNos = [];

    protected function setUp(): void
    {
        $slot = (getmypid() % 100000) * 1000;
        $this->uidBase = 990000000000000000 + $slot;
        $this->oidBase = 991000000000000000 + $slot;
    }

    protected function tearDown(): void
    {
        try {
            Db::table('user')->whereBetween('id', [$this->uidBase, $this->uidBase + 999])->delete();
            Db::table('user_session')->where('token_id', self::tokenId())->delete();
            if ($this->orderNos !== []) {
                Db::table('deposit_order')->whereIn('order_no', $this->orderNos)->delete();
            }
        } catch (\Throwable) {
            // 连不上库时没什么可清的
        }
    }

    // ---------------------------------------------------------------- 夹具

    private function get(string $url): Request
    {
        return new Request("GET {$url} HTTP/1.1\r\nHost: localhost\r\n\r\n");
    }

    /** 跑一次端点，返回 [响应体数组, 本次查询条数, 本次 SQL 列表] */
    private function call(string $method, string $query = ''): array
    {
        $controller = new AnalyticsController();
        $request = $this->get("/admin/v1/analytics/{$method}{$query}");

        Db::connection()->flushQueryLog();
        Db::connection()->enableQueryLog();
        try {
            $response = $controller->{$method}($request);
        } finally {
            $log = Db::connection()->getQueryLog();
            Db::connection()->disableQueryLog();
        }

        $body = json_decode((string) $response->rawBody(), true);

        return [$body['data'] ?? [], count($log), array_column($log, 'query')];
    }

    private function seedUser(int $seq, string $createdAt): int
    {
        $id = $this->uidBase + $seq;
        Db::table('user')->insertOrIgnore([
            'id' => $id, 'username' => 'arpu_nail_' . $id, 'password' => '$2y$10$nail',
            'nickname' => 'arpu_nail', 'avatar' => '', 'email' => '', 'phone' => '',
            'country' => 'CN', 'language' => 'zh-CN', 'status' => 1,
            'last_login_at' => null, 'last_login_ip' => '',
            'created_at' => $createdAt, 'updated_at' => $createdAt, 'deleted_at' => null,
        ]);

        return $id;
    }

    private function seedOrder(int $seq, int $userId, string $amount, string $status, string $createdAt): void
    {
        $no = 'arpu_nail_' . getmypid() . '_' . $seq;
        $this->orderNos[] = $no;
        Db::table('deposit_order')->insertOrIgnore([
            'id' => $this->oidBase + $seq, 'order_no' => $no, 'user_id' => $userId,
            'amount' => $amount, 'currency' => 'USD', 'platform_amount' => $amount,
            'payment_method_id' => 0, 'status' => $status, 'transaction_id' => '',
            'checkout_url' => '', 'expires_at' => null, 'paid_at' => null, 'client_ip' => '127.0.0.1',
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }

    private static function day(int $ago): string
    {
        return date('Y-m-d', strtotime("-{$ago} days"));
    }

    /** 会话行按 pid 打标：并行跑测试时不与另一次运行抢 uk_token_id */
    private static function tokenId(): string
    {
        return 'arpu_nail_' . getmypid();
    }

    private static function between(string $date): array
    {
        return [$date . ' 00:00:00', $date . ' 23:59:59'];
    }

    /**
     * 在窗口里挑一个「既无注册、也无充值单」的日子当空洞。
     *
     * 测试库是共享的（同机有并行跑测试的代理，且库里本就有一批散布在近 30 天的历史数据），
     * 钉死某一天不是踩到别人的种子、就是落在别人已有的数据上 ⇒ 运行时从远到近挑第一个空白的。
     * 挑不到就 skip，不赌某一个具体日期。
     */
    private function pickEmptyDay(int $from = 2, int $to = 80): string
    {
        for ($ago = $to; $ago >= $from; $ago--) {
            $date = self::day($ago);
            $hasOrder = Db::table('deposit_order')->whereBetween('created_at', self::between($date))->exists();
            $hasUser = Db::table('user')->whereBetween('created_at', self::between($date))->exists();
            if (!$hasOrder && !$hasUser) {
                return $date;
            }
        }

        return '';
    }

    /**
     * 旧实现（git HEAD 的 arpu 方法体，逐字照抄，只把 Response 拆成裸数组）——等价性比对的参照物。
     * 保留 whereDate 原样：它就是被替换掉的那份代码，用改写后的写法当参照物等于自证。
     */
    private function legacyArpuSeries(int $days): array
    {
        $dates = [];
        $arpuSeries = [];
        $arppuSeries = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dates[] = $date;

            $revenue = (string) (\common\model\DepositOrder::whereDate('created_at', $date)->where('status', 'confirmed')->sum('platform_amount') ?? '0');
            $totalUsers = \common\model\User::whereDate('created_at', '<=', $date)->count();
            $payingUsers = \common\model\DepositOrder::whereDate('created_at', $date)->where('status', 'confirmed')->distinct('user_id')->count('user_id');

            $arpuSeries[] = $totalUsers > 0 ? (float) BcMath::round(bcdiv($revenue, (string) $totalUsers, 5), 4) : 0;
            $arppuSeries[] = $payingUsers > 0 ? (float) BcMath::round(bcdiv($revenue, (string) $payingUsers, 5), 2) : 0;
        }

        return ['dates' => $dates, 'arpu' => $arpuSeries, 'arppu' => $arppuSeries];
    }

    // ---------------------------------------------------------------- ① days 上界 + 查询条数

    #[Test]
    public function daysIsClampedAndQueryCountDoesNotScaleWithIt(): void
    {
        [$data, $queries] = $this->call('arpu', '?days=100000');

        // 旧实现这里会吐 100000 天 / 30 万条查询
        $this->assertCount(self::MAX_DAYS, $data['dates'], '?days=100000 必须被夹到 90 天');
        $this->assertSame(self::day(self::MAX_DAYS - 1), $data['dates'][0], '窗口起点应是 -89 天');
        $this->assertSame(self::day(0), $data['dates'][self::MAX_DAYS - 1], '窗口终点应是今天');
        $this->assertCount(self::MAX_DAYS, $data['arpu']);
        $this->assertCount(self::MAX_DAYS, $data['arppu']);

        // 逐日循环已并成一条 GROUP BY：改前 days=30 是 90 条、days=1000 是 3000 条，现在恒为 3 条
        $this->assertSame(3, $queries, 'arpu 必须只发 3 条查询（日营收/付费人数聚合 + 区间前存量 + 区间内新增），实际 ' . $queries . ' 条');

        [$byDefault] = $this->call('arpu', '?days=90');
        $this->assertSame($byDefault['dates'], $data['dates'], 'clamp 后 100000 应与 90 等价');

        // 低于下界同样夹住：旧实现 days<=0 时循环一次都不跑，返回三个空数组
        [$low] = $this->call('arpu', '?days=0');
        $this->assertCount(1, $low['dates'], 'days=0 必须夹到 1 天，而不是空序列');
    }

    // ---------------------------------------------------------------- ① 缺日补 0 / 与旧逐日实现逐值一致

    #[Test]
    public function seriesIsZeroFilledAndMatchesLegacyPerDayComputation(): void
    {
        // 整段包在一个 REPEATABLE READ 事务里：同机有并行跑测试的代理在写同一个测试库，
        // 而这条要拿旧实现当参照物逐值比对 —— 两次读之间被写入就会假红（实测踩到过：
        // 同一个「今天」的 arpu 前一次 275/43、后一次 275/42，差的全是别人的并发数据）。
        // 事务给这条连接一个固定快照，两次实现读到的是同一份数据；种子也一并回滚。
        $gap = '';
        $series = [];
        $wide = [];

        Db::connection()->beginTransaction();
        try {
            $gap = $this->pickEmptyDay();

            // 空洞两侧各铺数据：中间那天没有任何充值单、也没有注册
            $oldUser = $this->seedUser(1, date('Y-m-d', strtotime($gap . ' -400 days')) . ' 08:00:00');
            $userA = $this->seedUser(2, date('Y-m-d', strtotime($gap . ' -1 days')) . ' 09:00:00');
            $userB = $this->seedUser(3, date('Y-m-d', strtotime($gap . ' +1 days')) . ' 10:00:00');

            // 边界钉子：恰好落在 90 天窗口起点整点（00:00:00.000000）的注册用户。
            // 正确实现里存量用 `created_at < 窗口起点`、再叠加区间内逐日新增 ⇒ 这个用户只被算一次；
            // 若存量误写成 `<=`（含起点），它会与 GROUP BY 的新增量重复计数 ⇒ 分母 +1、整条曲线偏移。
            // 没有这个整点用户，`<` 和 `<=` 在任何种子下都同值 —— 变异实测咬不住（M3 曾整条变绿）。
            // 取 day(89) 而非 day(29)：pickEmptyDay 的候选区间是 [2,80]，不会撞上空洞那天。
            $this->seedUser(4, self::day(self::MAX_DAYS - 1) . ' 00:00:00');

            $this->seedOrder(1, $userA, '100.0000', 'confirmed', date('Y-m-d', strtotime($gap . ' -1 days')) . ' 23:59:59');
            $this->seedOrder(2, $userB, '7.2500', 'confirmed', date('Y-m-d', strtotime($gap . ' +1 days')) . ' 00:00:00');
            $this->seedOrder(3, $oldUser, '999.0000', 'confirmed', date('Y-m-d', strtotime($gap . ' +1 days')) . ' 13:00:00');
            $this->seedOrder(4, $userA, '5.0000', 'pending', date('Y-m-d', strtotime($gap . ' -1 days')) . ' 14:00:00'); // pending 不计

            // 两次实现在同一个快照里各跑一遍；断言全部放到事务外，免得被 catch 吞成 skip
            foreach ([1, 7, 30, 90] as $days) {
                $series[$days] = [$this->call('arpu', '?days=' . $days)[0], $this->legacyArpuSeries($days)];
            }
            $wide = $this->call('arpu', '?days=' . self::MAX_DAYS)[0];
        } finally {
            Db::connection()->rollBack();
        }

        if ($gap === '') {
            $this->markTestSkipped('窗口内找不到空白天，无法构造「缺日」种子');
        }

        foreach ($series as $days => [$data, $legacy]) {
            // 旧实现返回的是 PHP 原生值，端点返回的是过了一遍信封 json_encode 再解回来的值；
            // 整数值的 float（250.0）在 webman 的 json() 下会落成 `250`（没开 JSON_PRESERVE_ZERO_FRACTION），
            // 解回来是 int。比对的是**同一条线上格式**，所以参照物也过一遍同样的往返。
            $legacyWire = json_decode((string) json_encode($legacy), true);

            $this->assertSame(
                $legacyWire,
                $data,
                "days={$days}：GROUP BY 版必须与旧逐日循环逐值一致（dates 顺序、缺日补 0、累计注册数的累加都要对得上）"
            );
        }

        // 空洞那天：序列里必须在、且是 0（不是缺行、也不是 null）
        $idx = array_search($gap, $wide['dates'], true);
        $this->assertNotFalse($idx, "空白天 {$gap} 必须仍在 dates 里（缺日要补 0 而不是缺行）");
        $this->assertSame(0.0, (float) $wide['arpu'][$idx], "空白天 {$gap} 的 arpu 必须是 0");
        $this->assertSame(0, $wide['arppu'][$idx], "空白天 {$gap} 的 arppu 必须是 0");
    }

    // ---------------------------------------------------------------- ① days 上界（retention / funnel 同口径）

    #[Test]
    public function retentionAndFunnelShareTheSameDaysCeiling(): void
    {
        // 同 arpu：整段包进 REPEATABLE READ 事务，前后两次调用读同一份快照，排除并行写入的假红
        Db::connection()->beginTransaction();
        try {
            // -90 天铺一个 cohort：不铺的话夹不夹上界都是全 '0%'，assertSame 会**恒真**
            $this->seedUser(1, self::day(90) . ' 10:00:00');
            $this->seedUser(2, self::day(90) . ' 11:00:00');
            Db::table('user_session')->insertOrIgnore([
                'id' => $this->uidBase + 900, 'user_id' => $this->uidBase + 1,
                'token_id' => self::tokenId(), 'device' => 'web', 'ip' => '127.0.0.1',
                'location' => '', 'user_agent' => 'phpunit',
                'logged_in_at' => self::day(90) . ' 12:00:00',
                'expired_at' => self::day(89) . ' 12:00:00',
            ]);
            // 400 天前的用户：只可能落进「没夹上界」的 274 年窗口 —— 它是这条断言不恒真的依据
            $this->seedUser(3, self::day(400) . ' 12:00:00');

            $retention90 = $this->call('retention', '?days=90')[0];
            $retentionWide = $this->call('retention', '?days=100000')[0];
            $funnel90 = $this->call('funnel', '?days=90')[0];
            $funnelWide = $this->call('funnel', '?days=100000')[0];
            // 下界：旧实现 days=0 时 `if (1 > 0) break` 一次都不跑，返回空数组
            $retentionZero = $this->call('retention', '?days=0')[0];

            // 独立复算 90 天窗口的注册数（不夹上界时它会变成整表）
            $registered90 = \common\model\User::where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-90 days')))->count();
        } finally {
            Db::connection()->rollBack();
        }

        $this->assertSame($retention90, $retentionWide, 'retention ?days=100000 必须被夹到 90 天（窗口 274 年 ⇒ 全量扫描）');
        $this->assertSame($funnel90, $funnelWide, 'funnel ?days=100000 必须被夹到 90 天');

        // 非恒真：窗口必须正好是 90 天，既不含那个 400 天前的用户、也不是空窗口
        $this->assertSame($registered90, $funnel90[0]['count'], 'funnel 的注册数必须正好是 90 天窗口的量');
        $this->assertNotSame('0%', $retention90['D1'], 'cohort 种子没生效（或夹到下界外），这条断言会退化成恒真');
        $this->assertArrayHasKey('D1', $retentionZero, 'retention ?days=0 必须夹到 1 天并出 D1，而不是空数组');
    }

    // ---------------------------------------------------------------- ② whereDate → 裸列区间

    #[Test]
    public function generatedSqlComparesDateColumnsWithoutWrappingThemInFunctions(): void
    {
        // retention 的 cohort 取 `-days` 那天，铺一个用户让四条 D 分支都真正发查询
        $this->seedUser(1, self::day(30) . ' 10:00:00');
        $this->seedUser(2, self::day(30) . ' 11:00:00');
        Db::table('user_session')->insertOrIgnore([
            'id' => $this->uidBase + 900, 'user_id' => $this->uidBase + 1,
            'token_id' => self::tokenId(), 'device' => 'web', 'ip' => '127.0.0.1',
            'location' => '', 'user_agent' => 'phpunit',
            'logged_in_at' => self::day(29) . ' 12:00:00',
            'expired_at' => self::day(-1) . ' 12:00:00',
        ]);

        [, , $arpuSql] = $this->call('arpu', '?days=30');
        [, , $retentionSql] = $this->call('retention', '?days=30');

        $all = array_merge($arpuSql, $retentionSql);
        $this->assertNotSame([], $all, '没抓到 SQL，断言无从谈起（查询日志没生效？）');

        // whereDate 的编译产物是 date(`created_at`) = ? / date(`logged_in_at`) >= ?，
        // 列被函数包住 ⇒ 索引失效。改后 WHERE 里不允许再出现这种形状。
        // （投影里的 `DATE(created_at) as date` 后面跟的是 as，正则不匹配，属合法用法。）
        foreach ($all as $sql) {
            $this->assertDoesNotMatchRegularExpression(
                '/date\(\s*`?(created_at|logged_in_at)`?\s*\)\s*(=|>=|<=|>|<)/i',
                $sql,
                'WHERE 里的日期列被函数包住了，索引用不上：' . $sql
            );
        }

        // 反向断言：过滤条件必须还在（只删条件也能让上面那条变绿）
        $retention = implode("\n", $retentionSql);
        $this->assertMatchesRegularExpression(
            '/`?logged_in_at`?\s*>=\s*\?/',
            $retention,
            'retention 下界过滤不见了：' . $retention
        );
        $this->assertMatchesRegularExpression(
            '/`?logged_in_at`?\s*<=\s*\?/',
            $retention,
            'retention 上界过滤不见了：' . $retention
        );
        $this->assertMatchesRegularExpression(
            '/`?created_at`?\s+between\s+\?\s+and\s+\?/i',
            $retention,
            'retention 的 cohort 子查询区间过滤不见了：' . $retention
        );
        $this->assertMatchesRegularExpression(
            '/`?created_at`?\s+between\s+\?\s+and\s+\?/i',
            implode("\n", $arpuSql),
            'arpu 的日聚合区间过滤不见了'
        );
    }
}
