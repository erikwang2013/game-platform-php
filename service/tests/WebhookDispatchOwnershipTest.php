<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\WebhookController;
use app\event\EventBus;
use common\SnowflakeService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * Webhook 投递替身：`dispatch()` 用 `new static()` 实例化，故经本类调用即走这里。
 * 记录投递目标并可令投递恒失败 —— 两条缺陷的观察面都在「谁被投了 / 失败后抛不抛」上。
 */
class RecordingWebhook extends WebhookController
{
    /** @var list<array{url: string, event: string}> */
    public static array $delivered = [];

    public static bool $fail = false;

    protected function deliver(string $url, array $data): bool
    {
        self::$delivered[] = ['url' => $url, 'event' => (string) ($data['event'] ?? '')];
        return !self::$fail;
    }
}

/**
 * H1：Webhook 订阅原先是**全站广播** —— dispatch 只按事件名过滤，从不看归属，
 * 任何人 `POST /api/v1/webhook/register` 一个自己控制的 https 回调即可持续收到
 * 全站充值/兑换/风控载荷（含 order_no/user_id/amount/transaction_id）。
 * 订阅键 `"{userId}_{hookId}"` 里本来就有归属，修正为「只投给载荷属主」。
 *
 * M3：单个订阅者投递失败原会抛异常，EventConsumer 借此把**整个事件**重投，
 * 于是已成功的其它订阅者重复收到同一事件；一个恒返 5xx 的订阅者即可让全站可靠事件
 * 持续重投直到死信（商户按 webhook 记账＝重复入账）。修正为订阅者级失败与事件级失败解耦。
 *
 * 只打测试库：连接库名必须含 test，否则硬失败，绝不静默写开发库。
 */
final class WebhookDispatchOwnershipTest extends TestCase
{
    private static bool $booted = false;

    private int $ownerId = 0;
    private int $otherId = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
    }

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过 webhook 归属过滤用例）：' . $e->getMessage());
        }

        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        RecordingWebhook::$delivered = [];
        RecordingWebhook::$fail = false;

        // 每次运行都是新的属主 ID：断言用全等比较，别的用例残留的订阅行匹配不上
        $this->ownerId = SnowflakeService::generate();
        $this->otherId = SnowflakeService::generate();

        $this->seedHook($this->ownerId, 'https://owner.example/hook');
        $this->seedHook($this->otherId, 'https://other.example/hook');
    }

    protected function tearDown(): void
    {
        foreach ([$this->ownerId, $this->otherId] as $id) {
            if ($id > 0) {
                Db::table('platform_config')->where('group', 'webhook')->where('key', $id . '_hook')->delete();
            }
        }
    }

    /** H1 正例：充值事件只投给该用户的订阅 */
    #[Test]
    public function eventIsDeliveredOnlyToThePayloadOwner(): void
    {
        RecordingWebhook::dispatch('deposit.completed', [
            'user_id'  => $this->ownerId,
            'order_no' => 'DEP_OWNERSHIP_1',
            'amount'   => '100.0000',
        ], 'test_wh_' . bin2hex(random_bytes(8)));

        $this->assertSame(
            ['https://owner.example/hook'],
            array_column(RecordingWebhook::$delivered, 'url'),
            '充值事件只能投给载荷属主的订阅（改前会同时投给 other.example = 跨用户泄漏）：'
                . json_encode(RecordingWebhook::$delivered)
        );
    }

    /** H1 负例：载荷没有 user_id（无法判定归属）时不得投给任何订阅者（fail-closed） */
    #[Test]
    public function payloadWithoutOwnerIsDeliveredToNobody(): void
    {
        RecordingWebhook::dispatch(
            'deposit.completed',
            ['order_no' => 'DEP_OWNERSHIP_2'],
            'test_wh_' . bin2hex(random_bytes(8))
        );

        $this->assertSame(
            [],
            RecordingWebhook::$delivered,
            '缺 user_id 的载荷不得投给任何订阅者：' . json_encode(RecordingWebhook::$delivered)
        );
    }

    /** M3：可靠事件遇到恒失败的订阅者，dispatch 必须返回而不是抛（抛 ⇒ Outbox 重投整个事件） */
    #[Test]
    public function failingSubscriberDoesNotThrowForReliableEvent(): void
    {
        // 钉住被测前提：deposit.completed 确实在可靠事件名单里，否则本用例是空跑
        $this->assertContains('deposit.completed', EventBus::RELIABLE_EVENTS);

        RecordingWebhook::$fail = true;

        RecordingWebhook::dispatch('deposit.completed', [
            'user_id' => $this->ownerId,
        ], 'test_wh_' . bin2hex(random_bytes(8)));

        $this->assertSame(
            ['https://owner.example/hook'],
            array_column(RecordingWebhook::$delivered, 'url'),
            '失败订阅者仍应被尝试投递 —— 证明下面「没抛」不是因为压根没投'
        );
    }

    private function seedHook(int $userId, string $url): void
    {
        Db::table('platform_config')->insert([
            'id'          => SnowflakeService::generate(),
            'group'       => 'webhook',
            'key'         => $userId . '_hook',
            'value'       => json_encode([
                'id'         => 'hook_' . $userId,
                'url'        => $url,
                'events'     => ['deposit.completed'],
                'created_at' => date('Y-m-d H:i:s'),
            ]),
            'type'        => 'string',
            'description' => '',
        ]);
    }

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
