<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\admin\v1\controller\PlatformUserController;
use common\HashidsService;
use common\SnowflakeService;
use common\model\User;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use Webman\Route;

/**
 * DELETE /admin/v1/platform/user/{hashid}（PlatformUserController::destroy）—— 平台用户注销。
 *
 * **证据 vs 正控**（两族都要，但证的东西不一样）：
 *  - 守禁路径 = 证据：`balanceRefusesDeactivation` / `frozenBalanceRefusesDeactivation`
 *    —— 它们才是本端点存在的理由（不许默默把余额孤儿化），红了就是钱会被丢。
 *    断言里必须钉住「被拒时一个字都没写」（username/deleted_at/钱包余额三处）。
 *  - 允许路径 = 正控：`zeroBalanceUserIsDeactivatedAndAnonymized` —— 证明这把锁不是
 *    一律拒绝（守禁用例全绿而端点恒 422 也是"绿"的）。
 *  - 幂等 = 两侧都有：第二次必须 count=0/already_deleted=true 且**不再写库**（ageRow 让这一点可见）。
 *
 * 语义对齐 C 端自助注销（service/app/api/v1/controller/UserController.php:192-220）：
 * 匿名化 + 软删除 + 清 OAuth/会话；差别只在多查一列 frozen_balance（在途提现）。
 *
 * 只打测试库：库名不含 test 直接 fail（沿用 UserBatchAffectedRowsTest 的口径）。
 */
class PlatformUserDestroyTest extends TestCase
{
    private int $userId = 0;
    private const AGED_AT = '2020-01-01 00:00:00';

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

        $this->userId = SnowflakeService::generate();

        // 经模型写入：email/phone 是 Encryptable 列，只有走模型的 set() 才是生产的写入路径
        $user = new User();
        $user->id          = $this->userId;
        $user->username    = 'platform-destroy-' . $this->userId;
        $user->password    = password_hash('platform-destroy-test', PASSWORD_BCRYPT);
        $user->nickname    = 'bob';
        $user->avatar      = 'https://cdn.example.com/a.png';
        $user->email       = 'bob@example.com';
        $user->phone       = '13800000000';
        $user->status      = 1;
        $user->last_login_ip = '203.0.113.7';
        $user->save();
    }

    protected function tearDown(): void
    {
        if ($this->userId > 0) {
            Db::table('user_oauth')->where('user_id', $this->userId)->delete();
            Db::table('user_session')->where('user_id', $this->userId)->delete();
            Db::table('user_wallet')->where('user_id', $this->userId)->delete();
            Db::table('user')->where('id', $this->userId)->delete();
        }
        $this->userId = 0;
    }

    private function wallet(string $balance, string $frozen = '0.00000000'): void
    {
        Db::table('user_wallet')->insert([
            'id'             => SnowflakeService::generate(),
            'user_id'        => $this->userId,
            'balance'        => $balance,
            'frozen_balance' => $frozen,
        ]);
    }

    /** 注销请求。DELETE 无请求体，用 HTTP 原文构造（`new Request('DELETE','/p')` 两参形式读不到路由）。 */
    private function destroy(?int $id = null): array
    {
        $request  = new Request("DELETE /admin/v1/platform/user/x HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $response = (new PlatformUserController())->destroy($request, HashidsService::encode($id ?? $this->userId));

        return json_decode((string) $response->rawBody(), true) ?? [];
    }

    private function raw(string $column): mixed
    {
        return Db::table('user')->where('id', $this->userId)->value($column);
    }

    private function ageRow(): void
    {
        Db::table('user')->where('id', $this->userId)->update(['updated_at' => self::AGED_AT]);
    }

    // ============================================================
    // 一、守禁路径（证据）：资金未结清一律拒，且一个字都不写
    // ============================================================

    #[Test]
    public function balanceRefusesDeactivation(): void
    {
        $this->wallet('0.00000001');            // 1 个最小单位，不是 0
        $this->ageRow();

        $body = $this->destroy();

        $this->assertSame(422, $body['code'], '有余额必须拒，实际：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        // 被拒就是完全没发生：三处都钉住，避免"拒了但半写"
        $this->assertNull($this->raw('deleted_at'), '被拒的注销不许软删除');
        $this->assertSame('platform-destroy-' . $this->userId, $this->raw('username'), '被拒的注销不许改 username');
        $this->assertSame(self::AGED_AT, (string) $this->raw('updated_at'), '被拒的注销不许写库');
        $this->assertSame('0.00000001', (string) Db::table('user_wallet')->where('user_id', $this->userId)->value('balance'), '余额必须原封不动');
    }

    #[Test]
    public function frozenBalanceRefusesDeactivation(): void
    {
        // 冻结额是在途提现的钱：C 端自助注销只查 balance，管理端补上这一列
        $this->wallet('0.00000000', '1.00000000');
        $this->ageRow();

        $body = $this->destroy();

        $this->assertSame(422, $body['code'], '冻结额非 0 也必须拒，实际：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertNull($this->raw('deleted_at'));
        $this->assertSame(self::AGED_AT, (string) $this->raw('updated_at'));
    }

    #[Test]
    public function unknownUserIsNotFound(): void
    {
        $body = $this->destroy(SnowflakeService::generate());

        $this->assertSame(404, $body['code'], '不存在的用户应 404');
    }

    // ============================================================
    // 二、允许路径（正控）：资金为 0 ⇒ 注销真的发生
    // ============================================================

    #[Test]
    public function zeroBalanceUserIsDeactivatedAndAnonymized(): void
    {
        $this->wallet('0.00000000', '0.00000000');

        // 先挂上 OAuth 绑定与会话：证明注销会连带清掉这两张表
        Db::table('user_oauth')->insert([
            'id' => SnowflakeService::generate(), 'user_id' => $this->userId,
            'provider' => 'google', 'open_id' => 'openid-' . $this->userId,
        ]);
        Db::table('user_session')->insert([
            'id' => SnowflakeService::generate(), 'user_id' => $this->userId,
            'token_id' => 'jti-' . $this->userId, 'expired_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $body = $this->destroy();

        $this->assertSame(0, $body['code'], '余额为 0 应当注销成功，实际：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, $body['data']['count']);
        $this->assertFalse($body['data']['already_deleted']);

        $this->assertNotNull($this->raw('deleted_at'), '注销必须落成软删除');
        $this->assertSame('deleted_' . $this->userId, $this->raw('username'), 'username 必须匿名化（uk_username 唯一键要求可重复注销不撞键）');
        $this->assertSame('', (string) $this->raw('nickname'));
        $this->assertSame('', (string) $this->raw('avatar'));
        $this->assertSame(1, (int) $this->raw('status'), '注销不改 status：失活由软删除承担（UserAuth.php:45 的 User::find 查不到人 ⇒ 401）');

        // email/phone 是加密码：只有经模型 cast 读回 '' 才能证明「写进去的是空值」，
        // 直接读原始列只能看到密文（那是另一回事）。
        $reloaded = User::withTrashed()->find($this->userId);
        $this->assertNotNull($reloaded, 'withTrashed 必须还能读到注销行（幂等判据靠它）');
        $this->assertSame('', $reloaded->email, '邮箱必须清空');
        $this->assertSame('', $reloaded->phone, '手机号必须清空');

        $this->assertSame(0, Db::table('user_oauth')->where('user_id', $this->userId)->count(), 'OAuth 绑定必须清掉');
        $this->assertSame(0, Db::table('user_session')->where('user_id', $this->userId)->count(), '会话必须清掉');
    }

    #[Test]
    public function missingWalletRowStillAllowsDeactivation(): void
    {
        // 没有钱包行 = 没有钱。这条钉住"闸门不是靠行数判断的"，避免把无钱包用户也拒掉
        $body = $this->destroy();

        $this->assertSame(0, $body['code'], '无钱包行的用户应当能注销，实际：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, $body['data']['count']);
    }

    // ============================================================
    // 三、幂等：重复注销给可分辨的干净响应，且不再写库
    // ============================================================

    #[Test]
    public function secondDeactivationIsIdempotentAndWritesNothing(): void
    {
        $this->wallet('0.00000000');

        $first = $this->destroy();
        $this->assertSame(0, $first['code']);
        $this->assertSame(1, $first['data']['count']);

        $this->ageRow();                        // 把 updated_at 推到过去，让"第二次有没有写库"可见

        $second = $this->destroy();

        $this->assertSame(0, $second['code'], '重复注销不该是错误，实际：' . json_encode($second, JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, $second['data']['count'], '第二次没有改动任何行');
        $this->assertTrue($second['data']['already_deleted'], '必须能分辨「已注销」而不是再报一次成功');
        $this->assertSame(self::AGED_AT, (string) $this->raw('updated_at'), '第二次不许再发 UPDATE');
        $this->assertSame('deleted_' . $this->userId, $this->raw('username'), 'username 不该被二次匿名化');
    }

    #[Test]
    public function alreadyDeactivatedUserWithBalanceIsStillReportedAsDeactivated(): void
    {
        // 注销后又有钱落进来（充值回调解后到达）也不改变「已注销」这一事实：不该 500、也不该重跑资金闸
        $this->wallet('10.00000000');
        Db::table('user')->where('id', $this->userId)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->ageRow();

        $body = $this->destroy();

        $this->assertSame(0, $body['code'], '已注销用户的重复调用必须干净返回，实际：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertTrue($body['data']['already_deleted']);
        $this->assertSame(self::AGED_AT, (string) $this->raw('updated_at'), '已注销分支不许写库');
    }

    // ============================================================
    // 四、路由契约：客户端四端打的就是这条 DELETE
    // ============================================================

    /**
     * 路由 dump 要与种子比对，必须独占进程：Route::load() 每次都清空 dispatcher 静态状态，
     * 同进程第二次调用拿回 0 条路由（PermissionSeedParityTest 同一处置）。
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function deleteVerbIsWiredToDestroyInsideTheAdminAuthGroup(): void
    {
        Route::load([__DIR__ . '/../config']);

        $info = Route::dispatch('DELETE', '/admin/v1/platform/user/AbCd1234');
        $this->assertSame(\FastRoute\Dispatcher::FOUND, $info[0], 'DELETE /admin/v1/platform/user/{hashid} 未命中路由');
        $this->assertSame([PlatformUserController::class, 'destroy'], $info[1]['callback']);

        $middlewares = $info[1]['route']->getMiddleware();
        foreach ([
            \app\middleware\AdminAuth::class,
            \app\middleware\AdminPermission::class,
            \app\middleware\OperationLog::class,
        ] as $middleware) {
            $this->assertContains($middleware, $middlewares, "注销端点缺 {$middleware}（必须继承 /admin/v1 组的三层）");
        }

        // 公开组不得有它：注销是管理动作，落在 /api/v1 等于裸奔
        $this->assertSame(
            \FastRoute\Dispatcher::NOT_FOUND,
            Route::dispatch('DELETE', '/api/v1/platform/user/AbCd1234')[0],
            '注销端点不得出现在无鉴权的 /api/v1 公开组'
        );
    }
}
