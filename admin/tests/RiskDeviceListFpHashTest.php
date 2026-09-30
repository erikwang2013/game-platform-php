<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\RiskDeviceController;
use common\RiskDeviceBlock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use support\Response;
use Throwable;

/**
 * 设备页要拿得到**能提交的标识**：列表回完整 fp_hash，且 blocked 与共享键一致。
 *
 * 背景：列表原来只回 `fp_masked`（前 8 位+****），而 block/unblock 走 `fpHash()` 强校验
 * `/^[0-9a-f]{64}$/` ⇒ 管理端手里没有任何值能提交，设备拉黑/解封在界面上做不出来
 * （HEAD 里那两个按钮点了静默不发请求）。这里钉住两件事：
 *   ① `list` 回完整 `fp_hash`（行内动作的入参）
 *   ② `block()` 写的与 `list` 读的是**同一个键**（共享类 `common\RiskDeviceBlock`）
 */
class RiskDeviceListFpHashTest extends TestCase
{
    private const FP = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';
    private const ROW_ID = 990000601;

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
            if (!Db::getSchemaBuilder()->hasTable('device_fingerprint')) {
                $this->markTestSkipped('测试库没有 device_fingerprint 表');
            }
        } catch (Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    #[Test]
    public function listExposesFullFpHashAndBlockedState(): void
    {
        Db::beginTransaction();
        try {
            Db::table('device_fingerprint')->insert([
                'id'               => self::ROW_ID,
                'fp_hash'          => self::FP,
                'ip_c_segment'     => '203.0.113',
                'user_agent_hash'  => '',
                'accept_lang_hash' => '',
                'first_seen_at'    => '2026-09-01 00:00:00',
                'last_seen_at'     => '2026-09-30 00:00:00',
                'account_count'    => 3,
            ]);

            $row = $this->findRow($this->json($this->controller()->list($this->listRequest())));
            $this->assertNotNull($row, '按 fp_hash 前缀过滤应能查到本行');
            $this->assertSame(self::FP, $row['fp_hash'], '必须回完整 fp_hash —— 掩码提交给 block 会被 400');
            $this->assertSame('a1b2c3d4****', $row['fp_masked'], '展示用的掩码仍在');
            $this->assertFalse($row['blocked'], '未拉黑时为 false');
        } finally {
            Db::rollBack();
        }
    }

    /** 写读同键：block() 落的键必须被 list 的 blocked 读到（这正是「按钮亮着却没人消费」那个缺陷的反面） */
    #[Test]
    public function blockAndUnblockAreVisibleInList(): void
    {
        Db::beginTransaction();
        try {
            Db::table('device_fingerprint')->insert([
                'id'               => self::ROW_ID,
                'fp_hash'          => self::FP,
                'ip_c_segment'     => '203.0.113',
                'user_agent_hash'  => '',
                'accept_lang_hash' => '',
                'first_seen_at'    => '2026-09-01 00:00:00',
                'last_seen_at'     => '2026-09-30 00:00:00',
                'account_count'    => 3,
            ]);

            $controller = $this->controller();
            $blocked = $this->json($controller->block($this->postRequest(['fp_hash' => self::FP])));
            $this->assertSame(0, (int) ($blocked['code'] ?? -1), '拉黑必须成功：' . json_encode($blocked));

            $row = $this->findRow($this->json($controller->list($this->listRequest())));
            $this->assertTrue($row['blocked'] ?? false, '拉黑后列表应显示已拉黑（写读同一个键）');

            $controller->unblock($this->postRequest(['fp_hash' => self::FP]));
            $row = $this->findRow($this->json($controller->list($this->listRequest())));
            $this->assertFalse($row['blocked'] ?? true, '解封后应回到未拉黑');
        } finally {
            RiskDeviceBlock::unblock(self::FP); // Redis 不在事务里，显式清
            Db::rollBack();
        }
    }

    private function controller(): RiskDeviceController
    {
        return new RiskDeviceController();
    }

    /** 列表按 `fp_hash` 前缀过滤，故请求带查询串（get 即可，list 读的是 get） */
    private function listRequest(): Request
    {
        return new Request(
            'GET /admin/v1/risk/device/list?fp_hash=' . substr(self::FP, 0, 12) . " HTTP/1.1\r\nHost: localhost\r\n\r\n"
        );
    }

    /**
     * block/unblock 读的是 post（不是 input）：必须带 body 的 POST 原文，
     * 用 GET 查询串构造的话 `post('fp_hash')` 恒为空 ⇒ 400、键根本没落（本用例先踩过一次）。
     */
    private function postRequest(array $body): Request
    {
        $encoded = http_build_query($body);

        return new Request(
            "POST /admin/v1/risk/device/block HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "Content-Type: application/x-www-form-urlencoded\r\n"
            . "Content-Length: " . strlen($encoded) . "\r\n\r\n"
            . $encoded
        );
    }

    private function findRow(array $payload): ?array
    {
        foreach ((array) ($payload['data']['items'] ?? []) as $row) {
            if (($row['fp_hash'] ?? '') === self::FP) {
                return $row;
            }
        }

        return null;
    }

    private function json(Response $response): array
    {
        return json_decode((string) $response->rawBody(), true) ?? [];
    }
}
