<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\model\WithdrawOrder;
use common\service\PayoutService;

/**
 * PayoutService 单元测试
 * 覆盖: 状态/重试守卫、PayPal 邮箱提取、完成标记幂等性
 */
class PayoutServiceTest extends TestCase
{
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

    #[Test]
    public function markCompletedIsIdempotent(): void
    {
        $order = new WithdrawOrder();
        $order->status = 'completed';
        $order->payout_status = 'success';

        // 应直接返回，不重复发事件/通知
        PayoutService::markCompleted($order);
        $this->assertSame('completed', $order->status);
        $this->assertSame('success', $order->payout_status);
    }

    private static function extractPaypalEmail(WithdrawOrder $order): string
    {
        $method = new \ReflectionMethod(PayoutService::class, 'extractPaypalEmail');
        $method->setAccessible(true);
        return $method->invoke(null, $order);
    }
}
