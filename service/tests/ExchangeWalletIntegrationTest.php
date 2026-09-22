<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\ExchangeController;
use common\HashidsService;
use common\model\ExchangeRecord;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use support\Response;

/**
 * 兑换的真库集成测试：buy(in) / sell(out) 的扣加余额 + game_exchange_record 落库。
 *
 * 与 ExchangeControllerLegsTest 的分工：那里只钉 exchangeLegs 的纯计算（DB-free）；
 * 这里真开 Db::beginTransaction()、真扣钱包、真写账本，覆盖：
 *   in  = 扣平台币（提交值）→ 加游戏币净额
 *   out = 扣【提交的游戏币原值】→ 加「折算额 − 点差」的平台币净额
 * 失败路径（余额不足）一并覆盖：断言回滚后余额原样、无账本记录。
 *
 * 只打测试库：连接库名必须含 test，否则硬失败，绝不静默写开发库。
 * 金额断言一律走 bcmath（表列 scale 与代码 scale 不同，字符串相等不可靠）。
 */
class ExchangeWalletIntegrationTest extends TestCase
{
    /** 平台币初始余额 */
    private const PLATFORM_START = '1000';
    /** 游戏币初始余额 */
    private const GAME_START = '5000';
    /** 已知币价：1 平台币 = 100 游戏币 */
    private const RATE = '100';
    /** 点差 5% */
    private const SPREAD = '5';

    private static bool $booted = false;

    private int $userId = 0;
    private int $gameId = 0;
    private int $currencyId = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
        // hashids 容器绑定由 webman 插件 bootstrap 注册，PHPUnit 下缺这层引导，
        // 而 ExchangeController::decodeId() 依赖它解码 game_id/currency_id
        HashidsBootstrap::start(null);
    }

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());
        }

        // 服务端真实库名，而不是配置里的名字：写操作前的最后一道闸
        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        if ($this->userId === 0) {
            return;
        }

        // 只删本用例造的行（snowflake ID 唯一），不 TRUNCATE、不碰他人数据
        foreach (['exchange_record', 'transaction', 'notification', 'user_game_wallet', 'user_wallet'] as $table) {
            Db::table($table)->where('user_id', $this->userId)->delete();
        }
        Db::table('game_currency')->where('id', $this->currencyId)->delete();
        Db::table('game')->where('id', $this->gameId)->delete();
        Db::table('user')->where('id', $this->userId)->delete();
    }

    /** in：支出平台币 10 → 到账游戏币 1000 − 5% = 950 */
    #[Test]
    public function buyDeductsPlatformAndCreditsNetGameBalance(): void
    {
        $body = $this->exchange('buy', '10');

        $this->assertSame(0, $body['code'], 'buy 应成功：' . json_encode($body));
        $this->assertSame(
            0,
            bccomp($this->platformBalance(), '990', 8),
            '平台币只扣提交的 10：' . $this->platformBalance()
        );
        $this->assertSame(
            0,
            bccomp($this->gameBalance(), '5950', 8),
            '游戏币按扣点差后的净额到账（+950）：' . $this->gameBalance()
        );

        $record = $this->recordOf('in');
        $this->assertSame(0, bccomp($record->platform_amount, '10', 4), 'in: platform_amount = 支出的平台币');
        $this->assertSame(0, bccomp($record->game_amount, '950', 4), 'in: game_amount = 到账游戏币净额');
        $this->assertSame(0, bccomp($record->spread_fee, '50', 4), 'in: 点差按游戏币计（1000 × 5%）');
        $this->assertSame(0, bccomp($record->rate, self::RATE, 8), 'in: rate 为成交汇率');
    }

    /**
     * out：卖出游戏币 950 → 扣 950 游戏币，入账 950 ÷ 100 − 5% = 9.025 平台币。
     *
     * 修复前的实现把「提交值 ÷ 汇率」（9.5）当成要扣的游戏币数，本用例的余额断言即失败。
     */
    #[Test]
    public function sellDeductsSubmittedGameCoinsAndCreditsNetPlatformBalance(): void
    {
        $body = $this->exchange('sell', '950');

        $this->assertSame(0, $body['code'], 'sell 应成功：' . json_encode($body));
        $this->assertSame(
            0,
            bccomp($this->gameBalance(), '4050', 8),
            '卖 950 必须扣 950 游戏币（旧实现只扣 9.5）：' . $this->gameBalance()
        );
        $this->assertSame(
            0,
            bccomp($this->platformBalance(), '1009.025', 8),
            '平台币入账折算额 9.5 扣掉 5% 点差 0.475 的净额：' . $this->platformBalance()
        );

        $record = $this->recordOf('out');
        $this->assertSame(0, bccomp($record->game_amount, '950', 4), 'out: game_amount = 提交的游戏币数');
        $this->assertSame(0, bccomp($record->platform_amount, '9.025', 4), 'out: platform_amount = 折算额 − 点差');
        $this->assertSame(0, bccomp($record->spread_fee, '0.475', 4), 'out: 点差按平台币计（9.5 × 5%）');
        $this->assertSame(0, bccomp($record->rate, self::RATE, 8), 'out: rate 为成交汇率');
    }

    /** 游戏币不足：400 + 回滚，余额与账本都不留痕 */
    #[Test]
    public function sellBeyondGameBalanceRollsBackWithoutRecord(): void
    {
        $body = $this->exchange('sell', '5001');

        $this->assertSame(400, $body['code'], '余额不足应 400：' . json_encode($body));
        $this->assertSame(0, bccomp($this->gameBalance(), self::GAME_START, 8), '回滚后游戏币余额原样');
        $this->assertSame(0, bccomp($this->platformBalance(), self::PLATFORM_START, 8), '回滚后平台币余额原样');
        $this->assertSame(0, $this->recordCount(), '失败路径不得落账本');
    }

    /** 平台币不足：同样 400 + 回滚（走 WalletService 的余额校验，不是 deductGameBalance） */
    #[Test]
    public function buyBeyondPlatformBalanceRollsBackWithoutRecord(): void
    {
        $body = $this->exchange('buy', '1000.0001');

        $this->assertSame(400, $body['code'], '余额不足应 400：' . json_encode($body));
        $this->assertSame(0, bccomp($this->platformBalance(), self::PLATFORM_START, 8), '回滚后平台币余额原样');
        $this->assertSame(0, bccomp($this->gameBalance(), self::GAME_START, 8), '回滚后游戏币余额原样');
        $this->assertSame(0, $this->recordCount(), '失败路径不得落账本');
    }

    /**
     * 让 support\Db 指向测试库。
     *
     * tests/bootstrap.php 只把测试库配置写进了一个局部数组；而 support\Db 首次被 autoload 时
     * 其文件尾部的 Webman\Database\Initializer::init(config('database')) 会再建一个 capsule
     * 并 setAsGlobal —— 用的是【开发库】的 config('database')。所以必须先把这次一次性初始化
     * 烧掉，再自己 setAsGlobal，否则查询打的是开发库。
     */
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
        // 凭据只从环境变量读，不进仓库、不落盘（本机 root 非免密，bootstrap 的 password='' 连不上）
        $user = getenv('GP_DB_USER');
        $pass = getenv('GP_DB_PASS');
        $conn['username'] = $user !== false && $user !== '' ? $user : $conn['username'];
        $conn['password'] = $pass !== false && $pass !== '' ? $pass : '';

        $capsule = new Capsule();
        $capsule->addConnection($conn, $name);
        $capsule->getDatabaseManager()->setDefaultConnection($name);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    /** @return array<string, mixed> 解码后的响应体 */
    private function exchange(string $action, string $amount): array
    {
        $request = new Request("POST /api/v1/exchange/{$action} HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost([
            'game_id'         => HashidsService::encode($this->gameId),
            'currency_id'     => HashidsService::encode($this->currencyId),
            'platform_amount' => $amount,
        ]);
        // 生产环境由 UserAuth 中间件注入，PHPUnit 下手工放上
        $request->userId = $this->userId;

        $response = (new ExchangeController())->{$action}($request);
        $this->assertInstanceOf(Response::class, $response);

        return json_decode($response->rawBody(), true);
    }

    private function platformBalance(): string
    {
        return (string) Db::table('user_wallet')->where('user_id', $this->userId)->value('balance');
    }

    private function gameBalance(): string
    {
        return (string) Db::table('user_game_wallet')
            ->where('user_id', $this->userId)
            ->where('game_id', $this->gameId)
            ->where('currency_id', $this->currencyId)
            ->value('balance');
    }

    private function recordCount(): int
    {
        return ExchangeRecord::where('user_id', $this->userId)->count();
    }

    private function recordOf(string $direction): ExchangeRecord
    {
        $record = ExchangeRecord::where('user_id', $this->userId)
            ->where('direction', $direction)
            ->first();

        $this->assertNotNull($record, "game_exchange_record 应有 direction={$direction} 的记录");
        $this->assertSame(1, $this->recordCount(), '一次兑换只应落一条账本');

        return $record;
    }

    private function seedFixtures(): void
    {
        $this->userId     = SnowflakeService::generate();
        $this->gameId     = SnowflakeService::generate();
        $this->currencyId = SnowflakeService::generate();

        Db::table('user')->insert([
            'id'       => $this->userId,
            'username' => 'exch_it_' . $this->userId,
            'password' => 'not-a-real-hash', // 本用例不校验口令，仅满足 NOT NULL
        ]);
        Db::table('game')->insert([
            'id'     => $this->gameId,
            'name'   => 'Exchange IT Game',
            'slug'   => 'exch-it-' . $this->gameId,
            'status' => 1, // doExchange 要求游戏上架
        ]);
        Db::table('game_currency')->insert([
            'id'            => $this->currencyId,
            'game_id'       => $this->gameId,
            'name'          => 'IT Coin',
            'symbol'        => 'ITC',
            'exchange_rate' => self::RATE,
            'spread_pct'    => self::SPREAD,
        ]);
        Db::table('user_wallet')->insert([
            'id'      => SnowflakeService::generate(),
            'user_id' => $this->userId,
            'balance' => self::PLATFORM_START,
        ]);
        Db::table('user_game_wallet')->insert([
            'id'          => SnowflakeService::generate(),
            'user_id'     => $this->userId,
            'game_id'     => $this->gameId,
            'currency_id' => $this->currencyId,
            'balance'     => self::GAME_START,
        ]);
    }
}
