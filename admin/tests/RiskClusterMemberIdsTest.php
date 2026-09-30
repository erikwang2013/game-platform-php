<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\RiskClusterController;
use app\model\RiskCluster;
use common\HashidsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use support\Response;
use Throwable;
use Webman\Exception\BusinessException;

/**
 * 团伙 confirm 的 member_ids 口径：对外是 **hashid**。
 *
 * 原实现是 `(int) $raw` ⇒ 调用方手里那些 hashid 逐个被静默丢成 0，成员列表空着落库、不报错
 * （与 B3 修的 role.permission_ids 同一族）。两条判据：
 *   ① 非法值（裸数字等）400 fail-fast —— 「静默丢弃」这条分支必须不存在了
 *   ② 合法 hashid 解回原始 user_id 落库 —— 往返自证，防止只做到「不报错」却没真落成员
 */
class RiskClusterMemberIdsTest extends TestCase
{
    private const USER_ID = 990000501;
    private const CLUSTER_NAME = 'lead-test-member-ids';

    /** 裸数字 = 旧口径：必须被拒（decodeId 对非法 hashid 抛 400），不能静默丢弃 */
    #[Test]
    public function confirmRejectsBareNumericMemberIds(): void
    {
        // 本用例没有断言机会做清理：实现一旦退回「静默接受」，confirm 会真的建出团伙行。
        // 事务兜底让这条兜底用例自己不留残留（实测过：退化实现 + 无事务 = 测试库里多一行 [12]）。
        try {
            Db::beginTransaction();
        } catch (Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }

        try {
            $this->expectException(BusinessException::class);
            $this->controller()->confirm($this->request([
                'type'       => 'manual',
                'name'       => self::CLUSTER_NAME,
                'member_ids' => ['12'],
            ]));
        } finally {
            Db::rollBack();
        }
    }

    /** hashid → 解回原始 user_id，落库的不是 0 也不是占位值 */
    #[Test]
    public function confirmDecodesHashidMemberIdsIntoRow(): void
    {
        // 测试库只种了部分表（如 game_user_wallet），没有 risk_cluster 时如实跳过，
        // 不为了让用例跑绿去改测试库 schema
        try {
            Db::selectOne('SELECT 1');
            if (!Db::getSchemaBuilder()->hasTable('risk_cluster')) {
                $this->markTestSkipped('测试库没有 risk_cluster 表（本用例需要完整 schema）');
            }
        } catch (Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }

        Db::beginTransaction();
        try {
            $hashid = HashidsService::encode(self::USER_ID);
            $payload = $this->json($this->controller()->confirm($this->request([
                'type'       => 'manual',
                'name'       => self::CLUSTER_NAME,
                'member_ids' => [$hashid],
                'user_count' => 1,
            ])));

            $this->assertSame(0, (int) ($payload['code'] ?? -1), 'hashid 成员必须被接受：' . json_encode($payload));

            $row = RiskCluster::where('name', self::CLUSTER_NAME)->first();
            $this->assertNotNull($row, '团伙行应已落库');
            $this->assertSame(
                [self::USER_ID],
                json_decode((string) $row->member_ids, true),
                'member_ids 应存解出的原始 user_id（旧实现这里是空串）'
            );
        } finally {
            Db::rollBack();
        }
    }

    private function controller(): RiskClusterController
    {
        return new RiskClusterController();
    }

    /**
     * HTTP 原文构造：`new Request('POST', '/path')` 两参形式不产生可解析数据源，
     * 只有原文这条路径能让 post() 真的解析出 body（confirm 用的是 post 不是 input）。
     */
    private function request(array $body): Request
    {
        $encoded = http_build_query($body);

        return new Request(
            "POST /admin/v1/risk/clusters/confirm HTTP/1.1\r\n"
            . "Host: localhost\r\n"
            . "Content-Type: application/x-www-form-urlencoded\r\n"
            . "Content-Length: " . strlen($encoded) . "\r\n\r\n"
            . $encoded
        );
    }

    private function json(Response $response): array
    {
        return json_decode((string) $response->rawBody(), true) ?? [];
    }
}
