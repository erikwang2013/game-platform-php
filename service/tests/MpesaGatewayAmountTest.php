<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\payment\MpesaGateway;
use common\model\DepositOrder;
use common\model\PaymentMethod;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * M-Pesa 推送金额必须为整数 KES（Safaricom 官方要求，小数无法精确推送）。
 *
 * 该分支原为 (float)/floor() 判断，违反本仓「金额运算必须 bcmath」规则；现改为
 * bcadd($x,'0',0) 按 scale 0 截断后 bccomp 比较（精确，不依赖 float 舍入）。两侧都要钉住：
 * 既不能放过小数（推送给 M-Pesa 会被拒/金额错），也不能误拒整数（存款直接挂）。
 * 被测列 game_deposit_order.amount 为 DECIMAL(18,4)，8 位比较精度高于其 4 位小数，无容差漏网。
 *
 * 无 DB 无外网：订单与 user 关系为内存对象；MPESA_API_URL 指向必然拒绝的本地端口，
 * 走到网络层即证明整数校验通过。
 */
class MpesaGatewayAmountTest extends TestCase
{
    private const ENV = [
        'MPESA_CONSUMER_KEY'    => 'test-key',
        'MPESA_CONSUMER_SECRET' => 'test-secret',
        'MPESA_PASSKEY'         => 'test-passkey',
        'MPESA_SHORTCODE'       => '174379',
        'MPESA_API_URL'         => 'http://127.0.0.1:1',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::ENV as $name => $value) {
            putenv("{$name}={$value}");
        }
    }

    protected function tearDown(): void
    {
        foreach (array_keys(self::ENV) as $name) {
            putenv($name);
        }
        parent::tearDown();
    }

    private function orderWithAmount(string $amount): DepositOrder
    {
        $order = new DepositOrder(['order_no' => 'DEP20260922000000ABC123', 'amount' => $amount]);
        // user 关系就地注入：避免懒加载触库
        $order->setRelation('user', new class {
            public string $phone = '254712345678';
        });
        return $order;
    }

    /** 小数 KES 必须在任何网络请求之前被拒 */
    #[Test]
    #[DataProvider('fractionalAmounts')]
    public function fractionalAmountRejectedBeforeAnyRequest(string $amount): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('M-Pesa amount must be a whole number in KES');

        (new MpesaGateway())->createPayment($this->orderWithAmount($amount), new PaymentMethod());
    }

    /** @return array<string, array{string}> */
    public static function fractionalAmounts(): array
    {
        return [
            '半个先令'     => ['100.5'],
            '四位小数非整' => ['100.0001'],
            '八位小数非零' => ['100.00000001'], // 精度边界：8 位比较仍不得视作整数
        ];
    }

    /** 整数 KES 必须放行：异常若来自连接被拒（而非金额），说明已走到 OAuth 请求 */
    #[Test]
    #[DataProvider('wholeAmounts')]
    public function wholeAmountPassesTheIntegerCheck(string $amount): void
    {
        $this->expectException(ConnectException::class);

        (new MpesaGateway())->createPayment($this->orderWithAmount($amount), new PaymentMethod());
    }

    /** @return array<string, array{string}> */
    public static function wholeAmounts(): array
    {
        return [
            '四位小数形式' => ['100.0000'],
            '纯整数'       => ['100'],
        ];
    }
}
