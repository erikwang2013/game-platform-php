<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\VipLevelController;
use common\HashidsService;
use common\SnowflakeService;
use common\model\VipLevel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * DELETE /admin/v1/vip/level/{hashid} 的「等级被占用则不可删」守卫。
 *
 * 旧实现查的是 `UserVip::where('vip_level', $vl->level)`，而 `game_user_vip` 的列是
 * `id / user_id / level / exp / total_exp`（install.sql:1797-1801）—— **没有 `vip_level`**。
 * MySQL 抛 SQLSTATE 42S22 ⇒ 整个端点**永远 500**（不是"返回 422 之外的错"，是根本没走到守卫），
 * 于是下面那条「该等级下还有 N 个用户，不可删除」成了**死代码**，谁也走不到。
 *
 * 本文件钉两件事：
 *  1. 有用户在用该等级 ⇒ **422 + 那条文案**（不是 500 / 不是异常），且等级**没被删掉**；
 *  2. 反向正控：没人用的等级照常删得掉 —— 否则"永远拒绝删除"也能让第 1 条全绿。
 *
 * 变异读数：把 `where('level', …)` 改回 `where('vip_level', …)` ⇒ 第 1 条红（QueryException）。
 *
 * 只打测试库：库名不含 test 直接 fail（沿用 PlatformUserTransactionsTest 口径）。
 */
final class VipLevelDeleteGuardTest extends TestCase
{
    /** @var int[] tearDown 要删的 vip_level.id */
    private array $levelIds = [];

    /** @var int[] tearDown 要删的 user_vip.id */
    private array $userVipIds = [];

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
    }

    protected function tearDown(): void
    {
        foreach ($this->userVipIds as $id) {
            Db::table('user_vip')->where('id', $id)->delete();
        }
        foreach ($this->levelIds as $id) {
            Db::table('vip_level')->where('id', $id)->delete();
        }
        $this->userVipIds = [];
        $this->levelIds   = [];
    }

    // ============================================================
    // 播种
    // ============================================================

    /**
     * 建一个 VIP 等级。level 用调用方给的确定值，但调用方必须保证它在 uk_level 上不与
     * 库内既有行冲突 —— 故这里先探一次占用，占用则换一个高位随机值。
     */
    private function seedLevel(int $level, string $name = 'probe'): VipLevel
    {
        while (Db::table('vip_level')->where('level', $level)->exists()) {
            $level = random_int(100000, 999999);
        }

        $id = SnowflakeService::generate();
        Db::table('vip_level')->insert([
            'id'           => $id,
            'level'        => $level,
            'name'         => $name,
            'required_exp' => 0,
            'benefits'     => '{}',
        ]);
        $this->levelIds[] = $id;

        return VipLevel::find($id);
    }

    /**
     * 往 game_user_vip 插一行占用该等级。
     *
     * 直插而不建 game_user 行：`game_user_vip` 在 DDL 上**没有外键**（只有 uk_user），
     * 而守卫只做 `where('level', …)->count()`，不 join 用户表 —— 建多余的用户行只会
     * 让清理面变大。user_id 用不冲突的雪花值即可。
     */
    private function occupyLevel(int $level): void
    {
        $id = SnowflakeService::generate();
        Db::table('user_vip')->insert([
            'id'        => $id,
            'user_id'   => SnowflakeService::generate(),
            'level'     => $level,
            'exp'       => 0,
            'total_exp' => 0,
        ]);
        $this->userVipIds[] = $id;
    }

    /**
     * 调端点。
     *
     * ⚠ 取的是**信封里的 code**，不是 HTTP 状态码：本仓（/metrics 除外）失败一律
     * `HTTP 200 + {code:422}`，`fail()` 只把语义写在信封里（config/route.php 无异常→状态码映射）。
     * 列名写错那条路更直接：QueryException 会从 destroy() 抛出来，用例以 error 变红。
     *
     * @return array<string,mixed> 响应信封
     */
    private function destroy(VipLevel $level): array
    {
        $hashid   = HashidsService::encode((int) $level->id);
        $request  = new Request("DELETE /admin/v1/vip/level/{$hashid} HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $response = (new VipLevelController())->destroy($request, $hashid);

        return json_decode((string) $response->rawBody(), true) ?? [];
    }

    // ============================================================
    // 一、守卫：等级被占用 ⇒ 422（不是 500）
    // ============================================================

    #[Test]
    public function levelWithUsersIsRefusedWith422InsteadOfBlowingUp(): void
    {
        $level = $this->seedLevel(7);
        $this->occupyLevel((int) $level->level);
        $this->occupyLevel((int) $level->level);

        // 旧实现到这里会抛 QueryException: SQLSTATE[42S22] Unknown column 'vip_level'（用例 error）
        $body = $this->destroy($level);

        $this->assertSame(
            422,
            $body['code'] ?? null,
            '等级被占用必须回 422 那条守卫，实际：' . json_encode($body, JSON_UNESCAPED_UNICODE)
        );
        $this->assertStringContainsString(
            '2',
            (string) ($body['message'] ?? ''),
            '文案里要带上真实占用人数（这条守卫的全部意义就是让运营知道删不掉的原因）'
        );

        // 守卫的**目的**是"不删"，光看状态码不够：真把行删了也算事故
        $this->assertTrue(
            VipLevel::find((int) $level->id) !== null,
            '守卫说的是"不可删除"，返回 422 却照样删掉 = 更严重的缺陷'
        );
    }

    // ============================================================
    // 二、反向正控：没人用 ⇒ 删得掉
    // ============================================================

    #[Test]
    public function levelWithoutUsersIsStillDeletable(): void
    {
        $level = $this->seedLevel(8);

        $body = $this->destroy($level);

        $this->assertSame(0, $body['code'] ?? null, '没人用的等级必须删成功，实际：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertNull(VipLevel::find((int) $level->id), '没人用的等级必须删得掉，否则第 1 条是"永远拒绝"的假绿');
    }

    // ============================================================
    // 三、计数只认"该等级"，不是"任意等级"
    // ============================================================

    #[Test]
    public function occupancyIsCountedPerLevelNotGlobally(): void
    {
        // 别处的占用不得把本等级判成"有人用"（否则任何等级只要库里有一个 VIP 用户就都删不掉）
        $other = $this->seedLevel(9);
        $this->occupyLevel((int) $other->level);

        $target = $this->seedLevel(10);

        $body = $this->destroy($target);

        $this->assertSame(0, $body['code'] ?? null, '别的等级有人用，不该拦本等级');
        $this->assertNull(VipLevel::find((int) $target->id));
    }
}
