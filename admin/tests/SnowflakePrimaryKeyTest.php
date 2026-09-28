<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use common\model\ExpLog;
use common\model\Notification;
use common\model\UserVip;
use common\service\NotificationService;
use common\service\VipService;
use Erikwang2013\Snowflake\Snowflake;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * 主键必须落在 snowflake 取值域（admin/CLAUDE.md：主键 id 由 snowflake 生成）。
 *
 * 回归对象是手搓的 (int)(date('YmdHis') . random_int(10000, 99999))：同秒只有 90000 个取值，
 * n 笔/秒时单秒撞号概率约 n²/180000（n=300 时约 50%），而拼接出来的 19 位数按 snowflake
 * 布局解不回当前时刻（实测解成 2039-03-07）——所以「解出的时间戳是否贴近现在」就是红绿判据，
 * 改回手搓时必然转红。
 */
class SnowflakePrimaryKeyTest extends TestCase
{
    private const NOTIF_USER_ID = 990000302;
    private const VIP_USER_ID   = 990000301;

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    /**
     * @param int $id 待检主键
     * @param string $what 失败信息里的来源描述
     */
    private function assertSnowflakeId(int $id, string $what): void
    {
        $epoch = (int) (config('snowflake.start_timestamp') ?: 1700000000000);
        $tsMs  = Snowflake::parse($id, $epoch)['timestamp_ms'];
        $nowMs = (int) round(microtime(true) * 1000);

        $this->assertLessThan(
            5000,
            abs($tsMs - $nowMs),
            sprintf(
                '%s 的 id=%d 不在 snowflake 取值域：解出时间戳 %d（%s），当前 %d。'
                . '手搓的 (int)(date(\'YmdHis\') . random_int(10000,99999)) 会解到 2039 年。',
                $what,
                $id,
                $tsMs,
                date('Y-m-d H:i:s', (int) ($tsMs / 1000)),
                $nowMs
            )
        );
    }

    #[Test]
    public function notification_id_is_snowflake(): void
    {
        Db::connection()->transaction(function () {
            Notification::where('user_id', self::NOTIF_USER_ID)->delete();

            NotificationService::send(self::NOTIF_USER_ID, 'system', 'id-domain', 'body');

            $row = Notification::where('user_id', self::NOTIF_USER_ID)->first();
            // send() 把整段包在 catch (\Throwable) 里静默吞掉，落库失败在这里是唯一信号
            $this->assertNotNull($row, '通知必须落库');
            $this->assertSnowflakeId((int) $row->id, 'NotificationService::send()');
        });
    }

    #[Test]
    public function vip_ids_are_snowflake(): void
    {
        Db::connection()->transaction(function () {
            UserVip::where('user_id', self::VIP_USER_ID)->delete();
            ExpLog::where('user_id', self::VIP_USER_ID)->delete();

            VipService::addExp(self::VIP_USER_ID, 10, 'test', 0, 'test');

            $vip = UserVip::where('user_id', self::VIP_USER_ID)->first();
            $this->assertNotNull($vip, 'VIP 行必须建出来');
            $this->assertSnowflakeId((int) $vip->id, 'VipService::addExp() user_vip');

            $log = ExpLog::where('user_id', self::VIP_USER_ID)->first();
            $this->assertNotNull($log, '经验流水必须落库');
            $this->assertSnowflakeId((int) $log->id, 'VipService::addExp() exp_log');
        });
    }
}
