<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\WebhookController;
use PHPUnit\Framework\TestCase;

final class WebhookUrlSafetyTest extends TestCase
{
    public function testRejectsHttpAndPrivateHosts(): void
    {
        $this->assertFalse(WebhookController::isSafeWebhookUrl('http://example.com/hook'));
        $this->assertFalse(WebhookController::isSafeWebhookUrl('https://127.0.0.1/hook'));
        $this->assertFalse(WebhookController::isSafeWebhookUrl('https://10.0.0.1/hook'));
        $this->assertFalse(WebhookController::isSafeWebhookUrl('https://192.168.1.1/hook'));
        $this->assertFalse(WebhookController::isSafeWebhookUrl('not-a-url'));
    }

    public function testAcceptsPublicHttpsIpLiteral(): void
    {
        $this->assertTrue(WebhookController::isSafeWebhookUrl('https://1.1.1.1/webhook'));
    }
}
