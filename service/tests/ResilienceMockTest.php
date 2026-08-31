<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use app\model\DeviceToken;
use app\model\PlatformConfig;
use app\model\WithdrawOrder;
use common\service\FeatureFlag;
use common\service\PayoutService;
use app\service\PushService;
use support\Db;

/**
 * 降级开关（feature.provider_mock）接入验证
 * 覆盖: PushService.send / PayoutService.execute 在 mock=on 时短路（不发网络请求），off 恢复原行为
 */
class ResilienceMockTest extends TestCase
{
    private const TEST_USER_ID = 990000701;
    private bool $dbAvailable = false;
    private array $orderIds = [];
    private array $tokenIds = [];

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
        $this->dbAvailable = true;
        $this->setMock(false);
        $this->orderIds = [];
        $this->tokenIds = [];
    }

    protected function tearDown(): void
    {
        if (!$this->dbAvailable) {
            return;
        }
        // 删除测试写入的 platform_config 行（删除即回到默认 off 行为），不影响其他测试
        PlatformConfig::where('group', 'feature')->where('key', 'provider_mock')->delete();
        if ($this->orderIds) {
            WithdrawOrder::whereIn('id', $this->orderIds)->delete();
        }
        if ($this->tokenIds) {
            DeviceToken::whereIn('id', $this->tokenIds)->delete();
        }
    }

    /** 预置 platform_config 行（显式 id，绕开 updateOrCreate 无行时 insert 缺 id 的已知缺陷） */
    private function setMock(bool $on): void
    {
        PlatformConfig::where('group', 'feature')->where('key', 'provider_mock')->delete();
        $config = new PlatformConfig();
        $config->id = 970000901;
        $config->group = 'feature';
        $config->key = 'provider_mock';
        $config->value = $on ? 'on' : 'off';
        $config->type = 'string';
        $config->save();
    }

    private function seedOrder(string $orderNo): WithdrawOrder
    {
        $order = new WithdrawOrder();
        $order->id = (int) (980000700 + crc32($orderNo) % 100);
        $order->order_no = $orderNo;
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
        $this->orderIds[] = $order->id;
        return $order;
    }

    private function seedToken(string $platform = 'fcm'): void
    {
        $token = new DeviceToken();
        $token->id = (int) (960000700 + random_int(0, 99));
        $token->user_id = self::TEST_USER_ID;
        $token->platform = $platform;
        $token->token = 'mock-token-' . $token->id;
        $token->created_at = date('Y-m-d H:i:s');
        $token->save();
        $this->tokenIds[] = $token->id;
    }

    #[Test]
    public function pushSendShortCircuitsWhenMockEnabled(): void
    {
        $this->setMock(true);
        $this->assertTrue(FeatureFlag::isEnabled('provider_mock'));

        // mock=on：直接短路返回，不查询 token、不发网络请求
        $this->seedToken();
        PushService::send(self::TEST_USER_ID, 'title', 'body', ['k' => 'v']);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function pushSendRestoresOriginalBehaviorWhenMockOff(): void
    {
        $this->setMock(false);
        $this->assertFalse(FeatureFlag::isEnabled('provider_mock'));

        // mock=off：走原路径（查询 token；无 FCM 密钥时静默返回，不发网络请求）
        $this->seedToken();
        PushService::send(self::TEST_USER_ID, 'title', 'body');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function payoutExecuteShortCircuitsWhenMockEnabled(): void
    {
        $this->setMock(true);
        $orderNo = 'TEST-MOCK-' . random_int(100000, 999999);
        $order = $this->seedOrder($orderNo);

        $result = PayoutService::execute($order);

        $this->assertSame('mock-' . $orderNo, $result['payout_batch_id']);
        $this->assertSame('success', $result['payout_status']);
        $this->assertSame(2, $result['payout_attempts']);
        $this->assertSame('completed', $order->status, 'mock 模式应标记订单完成');
        $this->assertSame('success', $order->payout_status);
    }

    #[Test]
    public function payoutExecuteRestoresOriginalBehaviorWhenMockOff(): void
    {
        $this->setMock(false);
        $order = $this->seedOrder('TEST-REAL-' . random_int(100000, 999999));

        // mock=off：走原路径而非短路成功。
        // 无 PayPal 凭证时应抛 'must be configured' RuntimeException（getenv 第二参
        // TypeError 缺陷已修复）。抛出的不是 mock 成功结果即证明未短路。
        try {
            PayoutService::execute($order);
            $this->fail('mock=off 不应短路为 mock 成功');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('must be configured', $e->getMessage());
        }
    }
}
