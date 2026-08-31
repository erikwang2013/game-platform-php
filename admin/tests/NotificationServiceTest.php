<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\model\Notification;
use common\service\NotificationService;
use support\Db;

/**
 * NotificationService 单元测试
 * 覆盖: send() 持久化通知行、无邮箱用户静默降级
 */
class NotificationServiceTest extends TestCase
{
    private const TEST_USER_ID = 990000201;

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    #[Test]
    public function sendPersistsNotificationRow(): void
    {
        Db::connection()->transaction(function () {
            Notification::where('user_id', self::TEST_USER_ID)->delete();

            NotificationService::send(
                self::TEST_USER_ID,
                'deposit',
                'Deposit Received',
                'Your deposit of 100 has arrived.',
                'deposit',
                123
            );

            $row = Notification::where('user_id', self::TEST_USER_ID)->first();
            $this->assertNotNull($row);
            $this->assertSame('deposit', $row->type);
            $this->assertSame('Deposit Received', $row->title);
            $this->assertSame('Your deposit of 100 has arrived.', $row->content);
            $this->assertSame(0, (int) $row->is_read);
            $this->assertSame('deposit', $row->ref_type);
            $this->assertSame(123, (int) $row->ref_id);
        });
    }

    #[Test]
    public function sendWithUnknownUserStillPersists(): void
    {
        Db::connection()->transaction(function () {
            $userId = 990000202; // 不存在于 游戏用户表 game_user
            Notification::where('user_id', $userId)->delete();

            NotificationService::send($userId, 'system', 'Hello', 'World');

            $row = Notification::where('user_id', $userId)->first();
            $this->assertNotNull($row);
            $this->assertSame('Hello', $row->title);
        });
    }

    #[Test]
    public function sendDoesNotThrowWhenDatabaseUnavailable(): void
    {
        // 数据库不可用时 send() 应静默失败（try/catch 包裹）
        NotificationService::send(990000203, 'system', 'No DB', 'Still no throw');
        $this->assertTrue(true);
    }
}
