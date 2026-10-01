<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\admin\v1\controller\WithdrawController;
use common\HashidsService;
use common\SnowflakeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * 一次提现驳回**只能写一条**流水。
 *
 * 缺陷形状（2026-10-01 由 C 端 react 那路读源码发现、我复核并修）：
 * `WithdrawReviewTrait` 的驳回分支原本是「两步写两条」——
 *   ① `UserWallet::addBalance($uid, $amount)` —— **不传第 3 参**，而它的签名是
 *      `addBalance(..., string $type = 'deposit', ...)` ⇒ `WalletService::mutate` 走成功路径时
 *      **无条件** `record()` 一条流水，类型记成 **`deposit`**；
 *   ② 紧接着手工 `new Transaction()` 又写一条 `type='refund'`。
 *
 * ⇒ 同额同用户**两条正额流水**。余额与累计收支都是对的（手工那条不碰余额，`total_earned`
 * 只在 `doMutate` 里动），**错的只有流水账**；而钱包列表把 `deposit` 显示成「充值」，
 * 玩家会看到「充值 +100」紧挨「提现退回 +100」，两笔都像进账。
 *
 * 本用例的判别力全在 **`count() === 1`** 上：只断言「有一条 refund」的话，
 * 修之前也会绿（那两条里本来就有 refund）。另加两条正控（remark/ref 与余额）。
 *
 * 直接 new 控制器调用（不经过 HTTP 层）：鉴权链在 config/route.php 与 AdminAuth/AdminPermission
 * 里，另有 AdminPermissionMatchingTest 覆盖 slug 匹配；这里钉的是**资金语义**。
 *
 * 请求用 HTTP 原文构造：`new Request('POST', '/path')` 两参形式不产生可解析数据源
 * （父类构造器只吃一个 buffer 字符串），输入会读成空 —— 那样 action 永远缺省，断言全成假绿。
 */
final class WithdrawRefundLedgerTest extends TestCase
{
    private const AMOUNT = '100.0000';
    private const START_BALANCE = '500.0000';

    private int $userId = 0;
    private int $orderId = 0;

    protected function setUp(): void
    {
        try {
            $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());

            return;
        }
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        $this->userId = SnowflakeService::generate();
        Db::table('user')->insert([
            'id'       => $this->userId,
            'username' => 'wd_refund_' . $this->userId,
            'password' => password_hash('Aa123456', PASSWORD_BCRYPT),
            'status'   => 1,
        ]);
        Db::table('user_wallet')->insert([
            'id'             => SnowflakeService::generate(),
            'user_id'        => $this->userId,
            'balance'        => self::START_BALANCE,
            'frozen_balance' => '0.0000',
            'total_earned'   => '0.0000',
            'total_spent'    => '0.0000',
        ]);

        $this->orderId = $this->seedOrder();
    }

    /** 再造一张待审提现单（同用户），返回订单 ID。 */
    private function seedOrder(): int
    {
        $orderId = SnowflakeService::generate();
        Db::table('withdraw_order')->insert([
            'id'              => $orderId,
            'order_no'        => 'WO' . $orderId,
            'user_id'         => $this->userId,
            'platform_amount' => self::AMOUNT,
            'status'          => 'pending',
        ]);

        return $orderId;
    }

    protected function tearDown(): void
    {
        if ($this->userId === 0) {
            return;
        }

        // 钱包写路径自 2026-09-28 起落 wallet.mutated 事件行（Outbox）：按本用户流水派生的 event_id
        // 精确删除，不按全表计数/全表删（并发跑测试时别人也在写这张表）
        $txIds = Db::table('transaction')->where('user_id', $this->userId)->pluck('id')->all();
        if ($txIds !== []) {
            Db::table('event_outbox')->whereIn('event_id', array_map(
                static fn ($id) => 'wallet.mutated:' . $id,
                $txIds
            ))->delete();
        }

        foreach (['transaction', 'user_wallet', 'withdraw_order'] as $table) {
            Db::table($table)->where('user_id', $this->userId)->delete();
        }
        Db::table('user')->where('id', $this->userId)->delete();
    }

    /** @return array<string,mixed> */
    private function reject(): array
    {
        $body = json_encode([
            'order_id' => HashidsService::encode($this->orderId),
            'action'   => 'reject',
            'note'     => 'probe',
        ], JSON_UNESCAPED_UNICODE);
        $request = new Request(
            "POST /admin/v1/withdraw/review HTTP/1.1\r\nHost: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body
        );
        $request->adminId = 990000001;

        $response = (new WithdrawController())->review($request);

        return json_decode((string) $response->rawBody(), true) ?? [];
    }

    /**
     * **核心**：一次驳回恰好一条流水（判别力在 `count() === 1`）。
     *
     * 修之前这里是 2 条（一条 deposit + 一条 refund），所以这条断言会红；
     * 只断言「存在 refund」则会绿 —— 那是假钉子。
     */
    #[Test]
    public function rejectionWritesExactlyOneLedgerRow(): void
    {
        $body = $this->reject();
        $this->assertSame(0, (int) ($body['code'] ?? -1), '驳回未成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));

        $rows = Db::table('transaction')->where('user_id', $this->userId)->get();
        $this->assertCount(
            1,
            $rows,
            '一次驳回写了 ' . count($rows) . ' 条流水（应为 1）：'
            . json_encode($rows->map(static fn ($r) => ['type' => $r->type, 'amount' => $r->amount])->all(), JSON_UNESCAPED_UNICODE)
        );
    }

    /** 正控：那一条得是退款本身（别修成「一条但对不上」）。 */
    #[Test]
    public function theSingleRowIsTheRefundItself(): void
    {
        $this->reject();

        $row = Db::table('transaction')->where('user_id', $this->userId)->first();
        $this->assertNotNull($row, '没有流水');
        $this->assertSame('refund', $row->type, '流水类型不是 refund');
        $this->assertSame('提现驳回退款', $row->remark, 'remark 丢了 —— 说明改回了 addBalance（它的签名里没有 remark 参数）');
        $this->assertSame('withdraw', $row->ref_type);
        $this->assertSame($this->orderId, (int) $row->ref_id);
    }

    /** 正控：钱要真到账（别为了去掉一条流水把退款本身弄丢了）。 */
    #[Test]
    public function refundActuallyCreditsTheWallet(): void
    {
        $this->reject();

        $balance = (string) Db::table('user_wallet')->where('user_id', $this->userId)->value('balance');
        $this->assertSame(
            bcadd(self::START_BALANCE, self::AMOUNT, 4),
            bcadd($balance, '0', 4),
            '余额没有加上退款额'
        );
    }

    /** 顺序：订单真的翻成 rejected（别只退了款没动状态）。 */
    #[Test]
    public function orderIsMarkedRejected(): void
    {
        $this->reject();

        $this->assertSame('rejected', (string) Db::table('withdraw_order')->where('id', $this->orderId)->value('status'));
    }

    /**
     * 批量驳回是**第二个同样形状的调用点**（`batchReview`），单条那条钉子够不到它 ——
     * 两处各自独立地写了两条流水，也必须各自独立地被钉住。
     */
    #[Test]
    public function batchRejectAlsoWritesExactlyOneRowPerOrder(): void
    {
        $second = $this->seedOrder();
        $body = json_encode([
            'ids'    => [HashidsService::encode($this->orderId), HashidsService::encode($second)],
            'action' => 'reject',
            'note'   => 'probe',
        ], JSON_UNESCAPED_UNICODE);
        $request = new Request(
            "POST /admin/v1/withdraw/batch-review HTTP/1.1\r\nHost: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body
        );
        $request->adminId = 990000001;

        $response = json_decode((string) (new WithdrawController())->batchReview($request)->rawBody(), true) ?? [];
        $this->assertSame(2, (int) ($response['data']['processed'] ?? -1), '批量驳回未处理全 2 单：' . json_encode($response, JSON_UNESCAPED_UNICODE));

        $rows = Db::table('transaction')->where('user_id', $this->userId)->get();
        $this->assertCount(
            2,
            $rows,
            '两单批量驳回写了 ' . count($rows) . ' 条流水（应为 2，每单一条）：'
            . json_encode($rows->map(static fn ($r) => ['type' => $r->type, 'ref' => $r->ref_id])->all(), JSON_UNESCAPED_UNICODE)
        );
        $this->assertSame(
            ['refund'],
            $rows->pluck('type')->unique()->values()->all(),
            '批量驳回写了非 refund 类型的流水'
        );
        $this->assertSame(
            2,
            $rows->pluck('ref_id')->unique()->count(),
            '两条流水没有分别指向两张订单'
        );
    }
}
