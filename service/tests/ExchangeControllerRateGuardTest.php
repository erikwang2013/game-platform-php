<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\ExchangeController;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Processors\MySqlProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use support\Request;

/**
 * 汇率守卫：quote() 与 sell/buy（doExchange）都必须以失败信封拒绝非正汇率。
 *
 * 回归背景：exchangeLegs() 的 out 分支做 bcdiv($amount, $effectiveRate, 8)，该调用原本在
 * Db::beginTransaction() 与 try 之外。admin 把 exchange_rate 存成 0（GameController::manageCurrency
 * 原先零校验）时抛未捕获的 DivisionByZeroError —— 500 纯文本而非 JSON 信封；负汇率不抛错，
 * 但会算出【负的平台币净额】，即按负金额入账。两者都必须在动钱之前拦下。
 *
 * 无 DB：本用例不连 MySQL。Eloquent 连接被替换为只读假连接，GameCurrency/Game 的查询由内存行
 * 满足，其余查询（user_vip 等）返回空集（VipService 因此返回折扣 '0'）。任何写操作会在假连接上
 * 被计数，用于断言「失败路径一行未写」。
 */
class ExchangeControllerRateGuardTest extends TestCase
{
    /** @var \Illuminate\Database\ConnectionResolverInterface|null */
    private $previousResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousResolver = Model::getConnectionResolver();
    }

    protected function tearDown(): void
    {
        Model::setConnectionResolver($this->previousResolver);
        parent::tearDown();
    }

    /** 只读假连接：按 SQL 里的表名喂固定行，写操作仅计数（不落库） */
    private function readonlyConnection(string $exchangeRate): Connection
    {
        return new class (['exchange_rate' => $exchangeRate]) extends Connection {
            public int $writes = 0;

            public function __construct(private array $overrides)
            {
                $this->queryGrammar  = new MySqlGrammar();
                $this->postProcessor = new MySqlProcessor();
            }

            public function select($query, $bindings = [], $useReadPdo = true)
            {
                if (str_contains($query, 'game_currency')) {
                    return [$this->overrides + [
                        'id' => '1', 'game_id' => '2', 'name' => 'Gold', 'symbol' => 'G',
                        'spread_pct' => '0', 'min_exchange' => '0', 'max_exchange' => '0',
                    ]];
                }
                if (preg_match('/from\s+`?game`?(\s|$)/i', $query)) {
                    return [['id' => '2', 'status' => '1', 'name' => 'Game', 'slug' => 'game']];
                }
                return [];
            }

            public function insert($query, $bindings = [])
            {
                $this->writes++;
                return true;
            }

            public function update($query, $bindings = [])
            {
                $this->writes++;
                return 1;
            }

            public function delete($query, $bindings = [])
            {
                $this->writes++;
                return 1;
            }

            public function statement($query, $bindings = [])
            {
                $this->writes++;
                return true;
            }
        };
    }

    private function installConnection(Connection $connection): void
    {
        Model::setConnectionResolver(new class ($connection) implements \Illuminate\Database\ConnectionResolverInterface {
            public function __construct(private Connection $connection) {}
            public function connection($name = null) { return $this->connection; }
            public function getDefaultConnection() { return 'default'; }
            public function setDefaultConnection($name) {}
        });
    }

    private function requestWith(string $direction = 'out', string $platformAmount = '100', int $userId = 5): Request
    {
        $body   = json_encode([
            'game_id'         => '2',
            'currency_id'     => '1',
            'direction'       => $direction,
            'platform_amount' => $platformAmount,
        ]);
        $buffer = "POST /api/v1/exchange/quote HTTP/1.1\r\nHost: localhost\r\n"
            . "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n{$body}";
        $request = new Request($buffer);
        $request->userId = $userId;
        return $request;
    }

    /** hashids 走 webman 容器（PHPUnit 下无插件实例），覆写为直取数字 ID */
    private function controller(): ExchangeController
    {
        return new class extends ExchangeController {
            protected function decodeId(string $hashid): int
            {
                return (int) $hashid;
            }
        };
    }

    /**
     * 报价入口：in/out 两个方向都必须返回 422 信封，且一行未写。
     *
     * @param non-empty-string $rate
     */
    #[Test]
    #[DataProvider('nonPositiveRates')]
    public function quoteRejectsNonPositiveRate(string $rate): void
    {
        foreach (['in', 'out'] as $direction) {
            $connection = $this->readonlyConnection($rate);
            $this->installConnection($connection);

            $response = (new ReflectionMethod(ExchangeController::class, 'quote'))
                ->invoke($this->controller(), $this->requestWith($direction));

            $body = json_decode($response->rawBody(), true);
            $this->assertSame(422, $body['code'], "rate={$rate} direction={$direction} 必须返回失败信封");
            $this->assertSame('Exchange rate is invalid', $body['message']);
            $this->assertSame(0, $connection->writes, '报价失败路径不得写库');
        }
    }

    /**
     * 卖出（out）是原崩溃路径（bcdiv 除零），买入（in）原先静默算出负金额 —— 现在都拦在事务之前。
     *
     * @param non-empty-string $rate
     */
    #[Test]
    #[DataProvider('nonPositiveRates')]
    public function doExchangeRejectsNonPositiveRate(string $rate): void
    {
        foreach (['in', 'out'] as $direction) {
            $connection = $this->readonlyConnection($rate);
            $this->installConnection($connection);

            $response = (new ReflectionMethod(ExchangeController::class, 'doExchange'))
                ->invoke($this->controller(), $this->requestWith(), $direction);

            $body = json_decode($response->rawBody(), true);
            $this->assertSame(422, $body['code'], "rate={$rate} direction={$direction} 必须返回失败信封");
            $this->assertSame('Exchange rate is invalid', $body['message']);
            $this->assertSame(0, $connection->writes, '成交失败路径不得动钱');
        }
    }

    /** @return array<string, array{string}> */
    public static function nonPositiveRates(): array
    {
        return [
            '零'         => ['0'],
            '零（定点）' => ['0.00000000'],
            '负整数'     => ['-1'],
            '负小数'     => ['-0.5'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function positiveRates(): array
    {
        return [
            '平价'     => ['1'],
            '小数'     => ['1.5'],
            '最小可分辨' => ['0.00000001'],
            '百倍'     => ['100'],
        ];
    }

    /** @param non-empty-string $rate */
    #[Test]
    #[DataProvider('positiveRates')]
    public function rateErrorAcceptsPositiveRate(string $rate): void
    {
        $this->assertNull(self::rateError($rate));
    }

    /** @param non-empty-string $rate */
    #[Test]
    #[DataProvider('nonPositiveRates')]
    public function rateErrorRejectsNonPositiveRate(string $rate): void
    {
        $this->assertSame('Exchange rate is invalid', self::rateError($rate));
    }

    /**
     * 守卫存在的原因：未被拦下的零汇率会让 out 分支抛 DivisionByZeroError
     * （而非 JSON 信封）。行数改动若删掉守卫，上面两条入口用例会直接报错。
     */
    #[Test]
    public function unguardedLegsWouldDivideByZero(): void
    {
        $this->expectException(\DivisionByZeroError::class);

        (new ReflectionMethod(ExchangeController::class, 'exchangeLegs'))
            ->invoke(null, 'out', '100', '0', '0');
    }

    private static function rateError(string $rate): ?string
    {
        return (new ReflectionMethod(ExchangeController::class, 'rateError'))->invoke(null, $rate);
    }
}
