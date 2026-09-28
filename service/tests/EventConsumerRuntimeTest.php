<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\event\EventBus;
use app\model\EventOutbox;
use app\process\EventConsumer;
use app\process\EventSubscriber;
use app\service\AchievementService;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Processors\MySqlProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Workerman\Timer;
use Workerman\Worker;

/**
 * EventConsumer 的运行期契约（不连 MySQL、不连 Redis）：
 *   1. onWorkerStart 必须立即返回并注册两个【重复】定时器 —— 它一旦阻塞，workerman 的事件循环就起不来、
 *      信号永不派发（只能 SIGKILL；SIGKILL 落在批中途会让已派发未标记的行重放）。
 *   2. 失败语义：可靠事件的消费失败必须向上抛（驱动 outbox 重试直至死信）；非可靠事件只记日志 ——
 *      后者若改成抛，Pub/Sub 消费进程会被一条失败事件带停。
 *   3. 死信边界：先 retry_count+1 再派发，累计到 MAX_ATTEMPTS 才置 DEAD；未到则【不改 status】，
 *      保持 PENDING 交给退避谓词重试（谓词侧另见 EventConsumerBackoffContractTest）。
 *   4. 清单成员与两条消费路径：RELIABLE_EVENTS 的每个成员失败都必须抛；Pub/Sub（EventSubscriber）与
 *      outbox（EventConsumer）必须派发到同一组 handler —— 否则把 emit 改成 push 会丢消费者（净回归）。
 *
 * 无库手法：把 Eloquent 的连接解析器换成假连接（先例 ExchangeControllerRateGuardTest）。假连接对业务
 * 查询一律抛错 ⇒ 三个下游 handler 必然失败；异常在各自【第一个 Eloquent 查询】处就抛，链条走不到
 * support\Db ⇒ 本用例不可能碰到真库。写操作只计数、记录绑定参数，用于断言"到底写了什么"。
 *
 * 唯一的外部副作用（实测，不隐瞒）：死信用例会走真 `markDeadLetter()`，其 `Redis::incr` 每次运行
 * 让共享计数 `metrics:event_dead_total` +1（实测 6→7）。该计数按设计只增不减、语义为"确实发生过一次
 * 死信"，故不做"跑完 DECR 回退"——那会与并发写者的自增互相抵消。
 */
final class EventConsumerRuntimeTest extends TestCase
{
    /** @var \Illuminate\Database\ConnectionResolverInterface|null */
    private $previousResolver;

    /** @var array<array-key, Worker> 本进程原有的 worker 列表（本类会临时造一个，收尾要还原） */
    private array $previousWorkers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousWorkers  = self::workers();
    }

    protected function tearDown(): void
    {
        // delAll 同时解除 pcntl_alarm：假运行时的 Timer::add 会 alarm(1)，不清掉本进程 1 秒后会被 SIGALRM 打死
        Timer::delAll();
        self::setWorkers($this->previousWorkers);
        Model::setConnectionResolver($this->previousResolver);
        parent::tearDown();
    }

    public function testWorkerStartReturnsPromptlyAndRegistersBothRepeatTimers(): void
    {
        // Timer::add 需要 workerman 运行时：无 Worker 时它直接抛
        // "Timer can only be used in workerman running environment"。造一个不监听端口的 Worker 即可。
        new Worker();

        $timer  = new ReflectionClass(Timer::class);
        $idProp = $timer->getProperty('timerId');
        $idProp->setAccessible(true);
        $before = (int) $idProp->getValue();

        $started = microtime(true);
        (new EventConsumer())->onWorkerStart();
        $elapsed = microtime(true) - $started;

        self::assertLessThan(1.0, $elapsed, 'onWorkerStart 阻塞了：事件循环起不来 ⇒ 信号永不派发、只能 SIGKILL');
        self::assertSame($before + 2, (int) $idProp->getValue(), '必须恰好注册两个定时器：少一个 = 轮询或存量 gauge 不再运行');

        $intervals  = [];
        $persistent = [];
        $tasks      = $timer->getProperty('tasks');
        $tasks->setAccessible(true);
        foreach ($tasks->getValue() as $batch) {
            foreach ($batch as [$func, $args, $isPersistent, $interval]) {
                // 计时/间隔不涉金额；(float) 仅用于类型对齐（GAUGE_INTERVAL 是 int 常量，Timer::add 收 float）
                $intervals[]  = (float) $interval;
                $persistent[] = $isPersistent;
            }
        }
        $expected = [(float) self::const('POLL_INTERVAL'), (float) self::const('GAUGE_INTERVAL')];
        sort($intervals);
        sort($expected);

        self::assertSame($expected, $intervals, '定时器间隔必须取自常量：调用处写死字面量会让轮询周期静默漂移');
        self::assertSame([true, true], $persistent, 'persistent=false 是一次性定时器：消费进程一生只扫一批就静默停工');
    }

    /** 前提控制：假连接确实让下游失败。没有它，"非可靠事件不抛"可能只是"谁都没干活"的空跑 */
    public function testControlDownstreamHandlerReallyFails(): void
    {
        $this->installConnection(self::brokenConnection());

        $this->expectException(\Throwable::class);
        AchievementService::handle('exchange.completed', ['user_id' => 5]);
    }

    /**
     * 可靠清单的失败语义：名单内【每一个】事件的下游失败都必须向上抛（驱动 outbox 重试直至死信）。
     * provider 读常量而不抄清单 ⇒ 新增成员自动纳入；成员被移出时本用例会自动"改判"成非可靠侧而全绿，
     * 故必须配 testWalletMutatedIsReliable 那条 pin（两条一正一反，缺一个就会静默退化）。
     */
    #[DataProvider('reliableEvents')]
    public function testEveryListedReliableEventFailsLoudly(string $event): void
    {
        $this->installConnection(self::brokenConnection());

        $this->expectException(\Throwable::class);
        EventConsumer::dispatch($event, ['user_id' => 5], $event . '_1');
    }

    /** @return array<string, array{string}> */
    public static function reliableEvents(): array
    {
        $cases = [];
        foreach (EventBus::RELIABLE_EVENTS as $event) {
            $cases[$event] = [$event];
        }

        return $cases;
    }

    /** 用户显式要求 wallet.mutated 升可靠投递：移出名单 = 退回 Pub/Sub，消费失败只记日志、事件永久丢失 */
    public function testWalletMutatedIsReliable(): void
    {
        self::assertContains('wallet.mutated', EventBus::RELIABLE_EVENTS);
    }

    /**
     * 消费者一致性（emit→push 的净回归防线）：Pub/Sub 路径（EventSubscriber）与 outbox 路径
     * （EventConsumer）必须到同一组 handler。不读源码文本：用假连接数「下游 handler 的查询次数」，
     * 两条路各跑一遍比对。差异只有一处且方向是变强——outbox 路径带真 eventId，WebhookController
     * 因此多做一次幂等去重读（假连接对 exists 查询返回"无行"，故不影响计数）。
     */
    public function testPubSubAndOutboxPathsReachTheSameHandlers(): void
    {
        $pubSub = self::brokenConnection();
        $this->installConnection($pubSub);
        (new ReflectionMethod(EventSubscriber::class, 'dispatchMessage'))
            ->invoke(new EventSubscriber(), json_encode(['event' => 'wallet.mutated', 'payload' => ['user_id' => 5]]));

        $outbox = self::brokenConnection();
        $this->installConnection($outbox);
        try {
            EventConsumer::dispatch('wallet.mutated', ['user_id' => 5], 'wallet_mutated_1');
        } catch (\Throwable) {
            // 可靠事件在此必然上抛（上一条用例已钉死）；本用例只问它走到了哪些 handler
        }

        self::assertSame(3, $pubSub->handlerSelects, 'Pub/Sub 路径必须依次走到 成就 / Webhook / 活动 三个 handler');
        self::assertSame(
            $pubSub->handlerSelects,
            $outbox->handlerSelects,
            '两条路的 handler 集合不同 ⇒ 切到 outbox 就送不到原消费者，是净回归'
        );
    }

    public function testNonReliableEventFailureIsSwallowedWithoutWrites(): void
    {
        $connection = self::brokenConnection();
        $this->installConnection($connection);

        EventConsumer::dispatch('game.played', ['user_id' => 5]);

        self::assertSame(0, $connection->writes, '非可靠事件的下游失败只记日志：不抛出、不写库');
    }

    /**
     * 死信边界。可达域是 retry_count < MAX_ATTEMPTS（消费端查询条件），三种取值都测。
     *
     * @param int  $retryCount 行上的既有重试次数
     * @param bool $expectDead 本次失败后是否应转死信
     */
    #[DataProvider('retryCounts')]
    public function testDeadLetterOnlyAfterMaxAttempts(int $retryCount, bool $expectDead): void
    {
        $connection = self::outboxConnection($retryCount);
        $this->installConnection($connection);

        (new ReflectionMethod(EventConsumer::class, 'drainBatch'))->invoke(null);

        $updates = $connection->updates;
        self::assertCount(2, $updates, '每次尝试两次写：retry_count+1，然后写 last_error（转死信时连同 status）');
        self::assertSame($retryCount + 1, $updates[0][0], '必须先递增再派发：顺序反了首次尝试也会白等一个退避窗口（下一条查询就取不出它）');
        self::assertContains($connection->error, $updates[1], '失败原因必须落库：死信 last_error 是人工重放时唯一线索');
        self::assertSame(
            $expectDead,
            in_array(EventOutbox::STATUS_DEAD, $updates[1], true),
            $expectDead ? '累计到上限必须置 DEAD' : '未到上限不得改 status：改了就绕开退避谓词、也绕开死信计数'
        );
    }

    /** @return array<string, array{int, bool}> */
    public static function retryCounts(): array
    {
        return [
            '首次失败'     => [0, false],
            '第二次失败'   => [1, false],
            '末次失败转死信' => [2, true],
        ];
    }

    /** 全抛假连接：任何查询都失败，写操作只计数 */
    private static function brokenConnection(): Connection
    {
        return new class extends Connection {
            public int $writes = 0;
            /** 下游 handler 的查询次数（WebhookController 的 exists 幂等去重读不计：只有 outbox 路径带 eventId 才发它） */
            public int $handlerSelects = 0;
            public string $error = 'probe: handler db down';

            public function __construct()
            {
                $this->queryGrammar  = new MySqlGrammar();
                $this->postProcessor = new MySqlProcessor();
            }

            public function select($query, $bindings = [], $useReadPdo = true)
            {
                if (str_contains($query, 'exists')) {
                    return [['exists' => 0]];
                }
                $this->handlerSelects++;

                throw new \RuntimeException($this->error);
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

    /** outbox 行喂给 drainBatch，其余查询（即下游 handler 的）一律抛；UPDATE 的绑定参数逐条留存 */
    private static function outboxConnection(int $retryCount): Connection
    {
        return new class ($retryCount) extends Connection {
            /** @var array<int, array<int, mixed>> 每次 UPDATE 的绑定参数 */
            public array $updates = [];
            public string $error = 'probe: handler db down';

            public function __construct(private int $retryCount)
            {
                $this->queryGrammar  = new MySqlGrammar();
                $this->postProcessor = new MySqlProcessor();
            }

            public function select($query, $bindings = [], $useReadPdo = true)
            {
                // exists() 查询（WebhookController 的幂等去重）按真库形状返回，缺键会触发 PHP warning
                if (str_contains($query, 'exists')) {
                    return [['exists' => 0]];
                }
                if (str_contains($query, 'event_outbox')) {
                    return [[
                        'id' => '123', 'event_id' => 'exchange_1', 'event' => 'exchange.completed',
                        'payload' => '{"user_id":5}', 'status' => 0, 'retry_count' => $this->retryCount,
                        'last_error' => null, 'occurred_at' => '2026-09-28 00:00:00',
                        'processed_at' => null, 'created_at' => '2026-09-28 00:00:00',
                        'updated_at' => '2026-09-28 00:00:00',
                    ]];
                }
                throw new \RuntimeException($this->error);
            }

            public function insert($query, $bindings = [])
            {
                $this->updates[] = $bindings;
                return true;
            }

            public function update($query, $bindings = [])
            {
                $this->updates[] = array_values($bindings);
                return 1;
            }

            public function delete($query, $bindings = [])
            {
                return 1;
            }

            public function statement($query, $bindings = [])
            {
                return true;
            }
        };
    }

    private function installConnection(Connection $connection): void
    {
        Model::setConnectionResolver(new class ($connection) implements \Illuminate\Database\ConnectionResolverInterface {
            public function __construct(private Connection $connection) {}

            public function connection($name = null)
            {
                return $this->connection;
            }

            public function getDefaultConnection()
            {
                return 'default';
            }

            public function setDefaultConnection($name) {}
        });
    }

    private static function const(string $name): int|float
    {
        return (new ReflectionClass(EventConsumer::class))->getReflectionConstant($name)->getValue();
    }

    /** @return array<array-key, Worker> */
    private static function workers(): array
    {
        $property = new \ReflectionProperty(Worker::class, 'workers');
        $property->setAccessible(true);

        return (array) $property->getValue();
    }

    /** @param array<array-key, Worker> $workers */
    private static function setWorkers(array $workers): void
    {
        $property = new \ReflectionProperty(Worker::class, 'workers');
        $property->setAccessible(true);
        $property->setValue(null, $workers);
    }
}
