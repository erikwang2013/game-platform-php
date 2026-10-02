<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\model\Notification;
use common\model\WithdrawOrder;
use common\service\EventPublisher;
use common\service\NotificationService;
use common\service\PayoutService;
use support\Db;

/**
 * PayoutService 单元测试
 * 覆盖: 状态/重试守卫、PayPal 邮箱提取、完成标记幂等性（含 EventBus 事件）
 */
class PayoutServiceTest extends TestCase
{
    private const TEST_USER_ID = 990000601;

    #[Test]
    public function executeRejectsNonApprovedOrder(): void
    {
        $order = new WithdrawOrder();
        $order->status = 'pending';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not in approved status');
        PayoutService::execute($order);
    }

    #[Test]
    public function executeRejectsMaxAttemptsExceeded(): void
    {
        $order = new WithdrawOrder();
        $order->status = 'approved';
        $order->payout_attempts = 5;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Max payout attempts exceeded');
        PayoutService::execute($order);
    }

    #[Test]
    public function syncStatusReturnsCurrentWhenNoBatchId(): void
    {
        $order = new WithdrawOrder();
        $order->payout_status = 'processing';

        $this->assertSame('processing', PayoutService::syncStatus($order));
    }

    #[Test]
    public function extractPaypalEmailFromJsonPaypalEmailField(): void
    {
        $order = new WithdrawOrder();
        $order->account_info = json_encode(['paypal_email' => 'payout@example.com']);
        $this->assertSame('payout@example.com', self::extractPaypalEmail($order));
    }

    #[Test]
    public function extractPaypalEmailFallsBackToEmailField(): void
    {
        $order = new WithdrawOrder();
        $order->account_info = json_encode(['email' => 'fallback@example.com']);
        $this->assertSame('fallback@example.com', self::extractPaypalEmail($order));
    }

    #[Test]
    public function extractPaypalEmailFromPlainString(): void
    {
        $order = new WithdrawOrder();
        $order->account_info = 'plain-email@example.com';
        $this->assertSame('plain-email@example.com', self::extractPaypalEmail($order));
    }

    #[Test]
    public function extractPaypalEmailThrowsOnInvalidInfo(): void
    {
        $order = new WithdrawOrder();
        $order->account_info = 'not-an-email';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot extract PayPal email');
        self::extractPaypalEmail($order);
    }

    /**
     * 已完成订单必须原样返回：不发事件、不发通知、不落库、不覆盖 paid_at。
     *
     * 旧版本只把 status/payout_status 设成 completed/success 再断言它们仍是
     * completed/success——断言的是自己刚写进去的值，删掉 markCompleted 的幂等
     * 守卫也照样通过，docblock 承诺的「不重复发事件/通知」则零断言。
     *
     * EventPublisher 与 NotificationService 都留了注册缝（未注册即 no-op），
     * 这里挂探针把「不重复发」从注释变成可断言的事实。
     */
    #[Test]
    public function markCompletedIsIdempotent(): void
    {
        $events = [];
        $pushed = [];
        EventPublisher::setPublisher(function (string $event, string $eventId, array $payload) use (&$events): void {
            $events[] = [$event, $eventId, $payload];
        });
        NotificationService::setPushHandler(function (...$args) use (&$pushed): void {
            $pushed[] = $args;
        });

        try {
            $order = new WithdrawOrder();
            $order->id              = 980000602;
            $order->user_id         = self::TEST_USER_ID;
            $order->platform_amount = '50.0000';
            $order->status          = 'completed';
            $order->payout_status   = 'success';
            $order->paid_at         = '2026-01-01 00:00:00';
            $paidAtBefore           = (string) $order->paid_at;

            PayoutService::markCompleted($order);

            $this->assertSame([], $events, '已完成订单不得重复发 withdraw.completed');
            $this->assertSame([], $pushed, '已完成订单不得重复发通知');
            $this->assertSame('completed', $order->status);
            $this->assertSame('success', $order->payout_status);
            $this->assertSame($paidAtBefore, (string) $order->paid_at, 'paid_at 不得被第二次调用覆盖');
            $this->assertFalse($order->exists, '已完成订单不得被再次落库');
        } finally {
            // setPublisher 只收 callable，无法复位为 null；用空实现恢复「未注册 = no-op」的等价语义
            EventPublisher::setPublisher(static function (): void {
            });
            NotificationService::setPushHandler(null);
        }
    }

    /**
     * 正向对照：首次完成必须恰好发一次事件，且 event_id 与 Monitor 对账巡检
     * （Health::GAP_SQL 的 CONCAT('withdraw_', wo.id, '_completed')）拼法一致；
     * 重复调用不得再发。只测「不重复发」而不测「第一次发得对」，等于把
     * 事件名/ID 拼歪也放过——真拼歪了对账会恒报 100% gap。
     */
    #[Test]
    public function markCompletedEmitsOnceThenSkipsOnRepeat(): void
    {
        try {
            Db::connection()->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }

        $events = [];
        EventPublisher::setPublisher(function (string $event, string $eventId, array $payload) use (&$events): void {
            $events[] = [$event, $eventId, $payload];
        });

        $orderId = 980000603;
        Db::connection()->beginTransaction();

        try {
            WithdrawOrder::where('id', $orderId)->delete();

            $order = new WithdrawOrder();
            $order->id              = $orderId;
            $order->order_no        = 'TEST-MC-' . random_int(100000, 999999);
            $order->user_id         = self::TEST_USER_ID;
            $order->platform_amount = '50.0000';
            $order->fiat_amount     = '50.0000';
            $order->currency        = 'USD';
            $order->method          = 'paypal';
            $order->account_info    = json_encode(['paypal_email' => 'payout@example.com']);
            $order->status          = 'approved';
            $order->payout_status   = 'processing';
            $order->payout_attempts = 1;
            $order->save();

            PayoutService::markCompleted($order);

            $this->assertSame('completed', $order->status);
            $this->assertSame('success', $order->payout_status);
            $this->assertNotNull($order->paid_at);
            $this->assertSame(
                [['withdraw.completed', "withdraw_{$orderId}_completed"]],
                array_map(static fn (array $e): array => [$e[0], $e[1]], $events),
                '事件名与 event_id 必须与对账巡检的拼法一致'
            );
            $this->assertSame(self::TEST_USER_ID, $events[0][2]['user_id'] ?? null, 'payload 必须带订单归属的 user_id');
            $this->assertSame(
                1,
                Notification::where('ref_type', 'withdraw')->where('ref_id', $orderId)->count(),
                '首次完成应恰好写一条通知'
            );

            $paidAtFirst = (string) $order->paid_at;

            // 第二次调用：status 已是 completed，应直接返回
            PayoutService::markCompleted($order);

            $this->assertCount(1, $events, '第二次调用不得重复发事件');
            $this->assertSame($paidAtFirst, (string) $order->paid_at, '第二次调用不得覆盖 paid_at');
            $this->assertSame(
                1,
                Notification::where('ref_type', 'withdraw')->where('ref_id', $orderId)->count(),
                '第二次调用不得重复写通知'
            );
        } finally {
            Db::connection()->rollBack();
            EventPublisher::setPublisher(static function (): void {
            });
        }
    }

    #[Test]
    public function markCompletedPersistsStateWhenDatabaseAvailable(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }

        Db::connection()->transaction(function () {
            \common\model\WithdrawOrder::where('id', 980000601)->delete();
            $order = new WithdrawOrder();
            $order->id = 980000601;
            $order->order_no = 'TEST-MC-' . random_int(100000, 999999);
            $order->user_id = self::TEST_USER_ID;
            $order->platform_amount = '50.0000';
            $order->fiat_amount = '50.0000';
            $order->currency = 'USD';
            $order->method = 'paypal';
            $order->account_info = json_encode(['paypal_email' => 'payout@example.com']);
            $order->status = 'approved';
            $order->payout_status = 'processing';
            $order->payout_attempts = 1;
            $order->save();

            PayoutService::markCompleted($order);

            $this->assertSame('completed', $order->status);
            $this->assertSame('success', $order->payout_status);
            $this->assertNotNull($order->paid_at);
        });
    }

    private static function extractPaypalEmail(WithdrawOrder $order): string
    {
        $method = new \ReflectionMethod(PayoutService::class, 'extractPaypalEmail');
        return $method->invoke(null, $order);
    }
}
