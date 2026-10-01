<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\WithdrawController;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use support\Db;
use support\Request;

/**
 * 提现限额的日/月窗口边界 —— **行为面**钉子。
 *
 * 类名保留 Daily（lead 是按这个名核的日窗口那批钉子），本文件现在同时钉两个窗口：日窗口 3 条、
 * 月窗口 2 条。月窗口那两条在事务内改 `withdraw_limit` 的 default 档（日闸抬宽、月闸收窄），
 * 否则 default 档 daily=10000 < monthly=50000，日闸总是先拦，月边界永远走不到。
 *
 * 被钉的代码：`WithdrawController::applyLocked()` 里日限额那笔 sum。
 * 原写法 `whereDate('created_at', date('Y-m-d'))` 编译成 `date(created_at) = ?`
 * （vendor/illuminate/database/Query/Grammars/Grammar.php:526-531），
 * `game_withdraw_order.created_at` 上的 `idx_created_at` 直接失效；
 * 现在改成半开区间 `>= 当天 00:00:00 AND < 次日 00:00:00`。
 *
 * 为什么**两条**钉子、而不是只留一条：
 *  - 形状（dailyLimitFilterKeepsTheColumnBare）能抓「改回 whereDate」——
 *    那次回退**行为完全等价**，任何行为面读数都看不见它；
 *  - 行为（dailyLimitExcludesOrdersFromTomorrow）能抓「右端写宽一天」——
 *    那种写法列没被函数包住，形状面照样绿。两条各堵一个盲区，缺一不可。
 *
 * 为什么非得走控制器真身：把 sum 抄进测试里自己跑一遍，变异控制器时那条**不会红**
 * —— 抄来的查询是第二真值源，不是被钉的对象。这里用反射直接调 private 的 applyLocked，
 * 绕开验证码与 Redis 串行锁（那是 apply() 的事），但**走的仍是真实资金路径**。
 *
 * 全部写入包在一层外层事务里，tearDown 整笔回滚：控制器自己的 `Db::commit()`
 * 在嵌套事务下只释放 SAVEPOINT（Laravel 语义），作业本行、流水行、事件行都随外层回滚，
 * 不往库里留一行。这也是不写 tearDown 清理清单的原因 —— 回滚比逐表删更不容易漏。
 *
 * 只打测试库：库名不含 test 直接 fail（沿用 WalletServiceLockTest / WalletFreezeLedgerTest 的口径）。
 */
class WithdrawDailyLimitBoundaryTest extends TestCase
{
    private static bool $booted = false;

    /** 本用例独占的用户 id：播种、断言都只碰这一批行 */
    private int $userId = 0;

    /**
     * 金额刻意贴合 `game_withdraw_limit` 的 default 档（不新增/不改限额行）：
     * single_max=1000.0000 / daily_limit=10000.0000。
     * 播种 10 单 × 1000 = 当天已用满 10000，再请求 999 ⇒ 10999 > 10000 ⇒ 日限额拦截。
     * 请求额取 999 而不是 1000，是为了不落在 single_max 的等号上。
     */
    private const ORDER_AMOUNT   = '1000';
    private const REQUEST_AMOUNT = '999';
    private const SEED_COUNT     = 10;

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
        // 日限额放行后会走到 success 响应的 encodeId()，该路径依赖 hashids 容器绑定
        // （webman 插件 bootstrap 注册的，PHPUnit 下要手动起；同 WalletTransactionsOrderingTest）
        HashidsBootstrap::start(null);
    }

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());
        }

        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        $this->userId = (int) SnowflakeService::generate();
        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        // 控制器若走到 commit，只释放了它的 SAVEPOINT；这里把外层（以及任何残留层）整笔回滚
        while (Db::transactionLevel() > 0) {
            Db::rollBack();
        }

        parent::tearDown();
    }

    /**
     * 当天 23:59:59 那单**必须算进**当天。
     *
     * 这一条同时是下面那条的前提探针：它证明「日限额这道闸真的会拦」「播种的行真的进了 sum」
     * —— 否则 next-day 那条的「没被拦」可能只是因为整条路径根本没走到限额检查（比如风控 block）。
     */
    #[Test]
    public function dailyLimitCountsAnOrderPlacedAtTodaysLastSecond(): void
    {
        $this->seedOrders(date('Y-m-d') . ' 23:59:59', self::SEED_COUNT);
        $this->giveWallet('100000');

        $payload = $this->applyWithdraw();

        $this->assertSame(
            trans('Daily withdrawal limit exceeded'),
            $payload['message'] ?? null,
            '当天 23:59:59 的 10 单应已用满日限额 ⇒ 必须拦在日限额上。'
            . '实际响应：' . json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * 次日 00:00:00 那单**不得算进**当天 —— 右端写宽一天（`<= 次日 00:00:00`）时，恰好这条红。
     *
     * 判据用「没被日限额拦住」而不是「响应码是 0」：日限额放行之后还有月限额、
     * 订单创建、钱包扣款一串下游，任一环出问题都会改响应码，那是别的问题、不该算在边界账上。
     * 用 co-证：同一个用户、同一笔金额、同一套限额，唯一变量是播种行的**时间戳**，
     * 23:59:59 被拦而 00:00:00 不被拦 ⇒ 差异只能来自日边界。
     */
    #[Test]
    public function dailyLimitExcludesAnOrderPlacedAtTomorrowsFirstSecond(): void
    {
        $this->seedOrders(date('Y-m-d', strtotime('+1 day')) . ' 00:00:00', self::SEED_COUNT);
        $this->giveWallet('100000');

        $payload = $this->applyWithdraw();

        $this->assertNotSame(
            trans('Daily withdrawal limit exceeded'),
            $payload['message'] ?? null,
            '次日 00:00:00 的 10 单**不得**算进当天：日限额应放行'
            . '（10999 > 10000 只在把次日那批算进来时才成立）⇒ 被日限额拦住说明右端写宽了一天。'
            . '实际响应：' . json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        // 正证：只断言「不是日限额」会假绿 —— 闸门之前就返回（风控 block / 余额不足 / 异常）
        // 也同样不是日限额。订单真落库，才说明确实**走过了**日限额与月限额两道闸。
        $this->assertSame(1, (int) Db::table('withdraw_order')
            ->where('user_id', $this->userId)
            ->where('platform_amount', self::REQUEST_AMOUNT . '.0000')
            ->count(),
            '日限额放行后应恰好落库 1 单（金额 ' . self::REQUEST_AMOUNT . '）；0 单说明流程根本没走过那道闸。'
            . '实际响应：' . json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 形状面：过滤列不得被函数包住。
     *
     * 唯一能抓「改回 whereDate」的判据 —— 那次回退在 DATETIME(0) 列上行为完全等价，
     * 上面两条行为面钉子对它**全绿**。
     */
    #[Test]
    public function dailyLimitFilterKeepsTheColumnBare(): void
    {
        $this->seedOrders(date('Y-m-d') . ' 23:59:59', self::SEED_COUNT);
        $this->giveWallet('100000');

        $log = $this->collect(fn () => $this->applyWithdraw());

        $dailySum = array_values(array_filter($log, static fn (array $q): bool =>
            stripos($q['query'], 'withdraw_order') !== false && stripos($q['query'], 'sum(') !== false));
        $this->assertNotEmpty($dailySum,
            '前提探针：日志里应出现对 withdraw_order 的 sum 查询；没有说明没走到日限额那道闸，下面的断言是恒真的');

        $offenders = [];
        foreach ($log as $q) {
            // whereDate 的编译产物：`where date(`col`) = ?` / `and date(`col`) >= ?`；
            // `select DATE(created_at) as date` 这类分组键前面是 select 不是 where/and，天然不命中。
            if (preg_match('/\b(where|and)\s+date\s*\(/i', $q['query'])) {
                $offenders[] = $q['query'];
            }
        }

        $this->assertSame([], $offenders,
            "以下 SQL 把过滤列包进了函数 ⇒ idx_created_at 失效、退化成全表扫：\n  " . implode("\n  ", $offenders));
    }

    /**
     * 当月最后一秒那单**必须算进**当月 —— 月窗口的正证，同时是下一条的前提探针：
     * 它证明月闸真的会拦、播种的行真的进了月 sum（否则「次月首秒没被拦」可能只是整条路径没走到月闸）。
     *
     * 为什么要在事务里临时改限额行（tearDown 整笔回滚，不留痕）：default 档 daily=10000 < monthly=50000，
     * 拿默认值播种时**日闸总是先拦**，月窗口的边界根本走不到；而且「本月最后一秒」在月末那天恰好
     * 落在今天的日窗口里，两者读数重合、不可区分。改成 daily=1000000 / monthly=10000 之后，
     * 唯一可能拦下这笔请求的就是月窗口 ⇒ 读数只反映月边界。
     *
     * 兼做形状面：本场景里日闸会**放行**（故日 sum 也执行），月闸会拦下 ⇒ 两条 sum 都在查询日志里，
     * 月窗口那条按**下界绑定**认出来（原写法下界是 date('Y-m-01')、不带时分秒，认不出即前提探针红）。
     */
    #[Test]
    public function monthlyLimitCountsAnOrderPlacedAtTheMonthsLastSecond(): void
    {
        $this->capMonthBoundaryLimits();
        $this->seedOrders(date('Y-m-t') . ' 23:59:59', self::SEED_COUNT);
        $this->giveWallet('100000');

        $payload = null;
        $log = $this->collect(function () use (&$payload): void {
            $payload = $this->applyWithdraw();
        });

        $this->assertSame(
            trans('Monthly withdrawal limit exceeded'),
            $payload['message'] ?? null,
            '当月 23:59:59（月末最后一秒）的 10 单应已用满月限额 ⇒ 必须拦在月限额上。'
            . '实际响应：' . json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        // 顺序有讲究：形状扫描放在前提探针**之前**。反过来的话，whereDate 那类回退会先撞上探针
        // （它的下界绑定变成不带时分秒的 `date('Y-m-01')`），报出来的原因就成了"探针没找到"而不是
        // "列被包住了" —— 红得对，但诊断指错地方。
        $sums = array_values(array_filter($log, static fn (array $q): bool =>
            stripos($q['query'], 'withdraw_order') !== false && stripos($q['query'], 'sum(') !== false));

        $offenders = [];
        foreach ($sums as $q) {
            if (preg_match('/\b(where|and)\s+date\s*\(/i', $q['query'])) {
                $offenders[] = $q['query'];
            }
        }
        $this->assertSame([], $offenders,
            "提现限额的过滤列被包进了函数 ⇒ idx_created_at 失效、退化成全表扫：\n  " . implode("\n  ", $offenders));

        // 前提探针（兼钉「左端必须写全 00:00:00」）：月窗口那条 sum 必须以**显式到秒**的当月起点为下界。
        // 漏掉 ' 00:00:00' 时靠 MySQL 隐式补零才是对的，这里认不出 ⇒ 红。
        $monthStart = date('Y-m-01') . ' 00:00:00';
        $monthSums = array_values(array_filter($sums, static fn (array $q): bool =>
            in_array($monthStart, $q['bindings'], true)));
        $this->assertNotEmpty($monthSums,
            "日志里应有以当月起点 `{$monthStart}` 为下界的 sum 查询；没有说明月窗口那道闸没按「显式到秒的月起点」执行"
            . '（上面那条形状断言在此情形下覆盖不到月窗口）');
    }

    /**
     * 次月第一秒那单**不得算进**当月 —— 右端写宽（`<= $nextMonthStart` 或再加一天）时，恰好这条红。
     *
     * 与日窗口那条同一套 co-证：同用户、同金额、同限额，唯一变量是播种行的时间戳；
     * 当月最后一秒被拦、次月第一秒不被拦 ⇒ 差异只能来自月边界。
     */
    #[Test]
    public function monthlyLimitExcludesAnOrderPlacedAtNextMonthsFirstSecond(): void
    {
        $this->capMonthBoundaryLimits();
        $this->seedOrders(date('Y-m-01 00:00:00', strtotime('first day of next month')), self::SEED_COUNT);
        $this->giveWallet('100000');

        $payload = $this->applyWithdraw();

        $this->assertNotSame(
            trans('Monthly withdrawal limit exceeded'),
            $payload['message'] ?? null,
            '次月 00:00:00 的 10 单**不得**算进当月：月限额应放行'
            . '（10999 > 10000 只在把次月那批算进来时才成立）⇒ 被月限额拦住说明右端写宽了。'
            . '实际响应：' . json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        // 正证：只断言「不是月限额」会假绿 —— 闸门之前返回（风控 block / 余额不足 / 异常）同样不是月限额。
        $this->assertSame(1, (int) Db::table('withdraw_order')
            ->where('user_id', $this->userId)
            ->where('platform_amount', self::REQUEST_AMOUNT . '.0000')
            ->count(),
            '月限额放行后应恰好落库 1 单（金额 ' . self::REQUEST_AMOUNT . '）；0 单说明流程根本没走过月闸。'
            . '实际响应：' . json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /** 走控制器真身：反射调 private applyLocked，返回解出的响应信封 */
    private function applyWithdraw(): array
    {
        $response = (new ReflectionMethod(WithdrawController::class, 'applyLocked'))->invoke(
            new WithdrawController(),
            $this->request(),
            $this->userId,
            self::REQUEST_AMOUNT,
            'paypal',
            'probe@example.com'
        );

        return json_decode((string) $response->rawBody(), true) ?? [];
    }

    private function request(): Request
    {
        return new Request("POST /api/v1/withdraw/apply HTTP/1.1\r\nHost: localhost\r\n\r\n");
    }

    /** 播种 $count 行「已计入」状态的提现单，全部落在同一时刻 */
    private function seedOrders(string $createdAt, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Db::table('withdraw_order')->insert([
                'id'              => (int) SnowflakeService::generate(),
                'order_no'        => 'WTHPROBE' . bin2hex(random_bytes(6)),
                'user_id'         => $this->userId,
                'platform_amount' => self::ORDER_AMOUNT,
                'fiat_amount'     => '0.0000',
                'currency'        => 'USD',
                'method'          => 'paypal',
                'account_info'    => 'probe',
                'status'          => 'completed',
                'created_at'      => $createdAt,
                'updated_at'      => $createdAt,
            ]);
        }
    }

    /** 钱包余额必须够这笔请求，否则控制器在日限额**之前**就以「余额不足」返回，这道闸根本走不到 */
    private function giveWallet(string $balance): void
    {
        Db::table('user_wallet')->insert([
            'id'             => (int) SnowflakeService::generate(),
            'user_id'        => $this->userId,
            'balance'        => $balance,
            'frozen_balance' => '0.00000000',
            'total_earned'   => '0.00000000',
            'total_spent'    => '0.00000000',
            'version'        => 0,
        ]);
    }

    /**
     * 事务内把 default 档改成「日闸足够宽、月闸足够窄」——让月窗口成为唯一可能拦下请求的那道。
     * 改的是共享限额行，故先在改之前把基线值钉住：若读到的不是套件基线，说明上一轮把行写脏了
     * （本类只在自身事务内改，tearDown 整笔回滚），必须响亮地红，不能静默按脏值跑。
     */
    private function capMonthBoundaryLimits(): void
    {
        $row = Db::table('withdraw_limit')->where('user_level', 'default')->first();
        $this->assertNotNull($row, '前提：测试库应有 withdraw_limit 的 default 档');
        $this->assertSame('10000.0000', (string) $row->daily_limit,
            '前提：default 档日限额应是套件基线 10000.0000（读到别的值＝限额行被写脏了）');
        $this->assertSame('50000.0000', (string) $row->monthly_limit,
            '前提：default 档月限额应是套件基线 50000.0000（读到别的值＝限额行被写脏了）');
        $this->assertSame('1000.0000', (string) $row->single_max,
            '前提：default 档单笔上限应是套件基线 1000.0000（请求额 ' . self::REQUEST_AMOUNT . ' 要低于它）');

        Db::table('withdraw_limit')->where('user_level', 'default')->update([
            'daily_limit'   => '1000000.0000',
            'monthly_limit' => '10000.0000',
        ]);
    }

    /** @return array<int, array{query: string, bindings: array}> */
    private function collect(callable $run): array
    {
        $connection = Db::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $run();
            return $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
        }
    }

    /** 与 14 个真库用例同一口径：先烧掉开发库那次 init 的守卫，再由测试库 Capsule 最后 setAsGlobal() 落笔 */
    private static function bootTargetDatabase(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        class_exists(Db::class);

        $conf = config('database');
        $name = $conf['default'];
        $conn = $conf['connections'][$name];

        $conn['database'] = getenv('DB_DATABASE_TEST') ?: 'game-platform-test';
        // 凭据优先取环境变量（GP_DB_USER/GP_DB_PASS，本机可用其覆盖），缺省回落到 config('database')
        // 即 .env 的口令 —— 不要硬编码空串：那会让本类及其之后的所有用例连不上库并静默 skip。
        $user = getenv('GP_DB_USER');
        $pass = getenv('GP_DB_PASS');
        $conn['username'] = $user !== false && $user !== '' ? $user : $conn['username'];
        $conn['password'] = $pass !== false && $pass !== '' ? $pass : (string) $conn['password'];

        $capsule = new Capsule();
        $capsule->addConnection($conn, $name);
        $capsule->getDatabaseManager()->setDefaultConnection($name);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
