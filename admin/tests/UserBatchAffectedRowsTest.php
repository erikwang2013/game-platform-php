<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\admin\v1\controller\UserController;
use app\model\AdminUser;
use common\HashidsService;
use common\SnowflakeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * 管理端两个批量端点的两处读数错误（UserController::batchStatus / batchDestroy）。
 *
 * 一、status 的 `(int)` 前置转型把白名单判空转：
 *     `(int) $request->input('status', 0)` 先做，再由 `in_array($status, [0,1], true)` 判 ——
 *     转型后恒为 int，白名单只剩 0/1 两条路，**任何**垃圾值都落成 0（=禁用）。于是
 *     `status: "banned"` 静默变成「把这批管理员禁用」，而 admin/docs/API.md:754 明写这种输入应当 422。
 *     这里钉的是「拒了 + 一行没动」，不只是「拒了」—— 旧实现同样返回 code 0 看着正常。
 *
 * 二、count 报的是**请求条数**不是受影响行数：
 *     请求 id 里混进已删除/不存在的 id 时，界面显示「成功 N 个」，实际改动的更少。
 *     batchDestroy 的文档（admin/docs/API.md:711「data.count 为实际删除数量」）早就写明了口径。
 *
 * 只打测试库：库名不含 test 直接 fail（沿用 RiskUserReleaseTest 的口径）。
 */
class UserBatchAffectedRowsTest extends TestCase
{
    private int $adminId = 0;
    /** @var int[] 本用例建的 admin 行 */
    private array $ids = [];
    private int $ghostId = 0;
    private const PASSWORD = 'batch-test-password';

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

        $this->adminId = $this->makeAdmin(1);
        $this->ids[] = $this->adminId;
        $this->ids[] = $this->makeAdmin(1);          // 被测的目标行：初始启用
        $this->ghostId = SnowflakeService::generate(); // 只编码不落库：模拟「已被别人删掉的那一个」
    }

    protected function tearDown(): void
    {
        foreach ($this->ids as $id) {
            Db::table('admin_user')->where('id', $id)->delete(); // 含软删除行，硬清
        }
        $this->ids = [];
    }

    private function makeAdmin(int $status): int
    {
        $id = SnowflakeService::generate();
        Db::table('admin_user')->insert([
            'id'       => $id,
            'username' => 'batch-test-' . $id,
            'password' => password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            'status'   => $status,
        ]);

        return $id;
    }

    private function statusOf(int $id): int
    {
        return (int) Db::table('admin_user')->where('id', $id)->value('status');
    }

    /** 用 HTTP 原文 + setPost 构造请求：`new Request('POST', '/path')` 两参形式读不到任何输入。 */
    private function post(string $path, array $post, bool $asAdmin = true): Request
    {
        $request = new Request("POST {$path} HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost($post);
        if ($asAdmin) {
            $request->adminId = $this->adminId;
        }

        return $request;
    }

    private function json(\support\Response $response): array
    {
        return json_decode((string) $response->rawBody(), true) ?? [];
    }

    #[Test]
    public function garbageStatusIsRejectedAndChangesNothing(): void
    {
        $target = $this->ids[1];
        $this->assertSame(1, $this->statusOf($target), '起手应是启用，才看得见旧实现的静默改为禁用');

        $body = $this->json((new UserController())->batchStatus($this->post(
            '/admin/v1/user/batch/status',
            ['ids' => [HashidsService::encode($target)], 'status' => 'banned']
        )));

        $this->assertSame(422, $body['code'], 'status 不是 0/1 应当 422，实际：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, $this->statusOf($target), '被拒的请求不许改动任何一行');
    }

    /**
     * `'normal'`（解封）这条比 `'banned'` 更重：它证明旧实现不是「吞掉非法输入」，
     * 而是**把两个语义相反的输入解释成同一个值** —— (int)'normal' === 0 === (int)'banned'。
     * 旧实现下「点解封」与「点封禁」都落成 0 = 禁用 ⇒ **用户点解封反而被封禁**，真实可达。
     * 所以反事实不是「旧实现拒了它」：旧实现**拒不了任何东西**，它把两者当同一个数。
     * （Angular 页的解封按钮发的正是 'normal'，见 users.ts 的 status(row, value)。）
     */
    #[Test]
    public function unbanStringNormalIsRejectedNotCoercedToBanned(): void
    {
        $target = $this->ids[1];
        $this->assertSame(1, $this->statusOf($target), '起手应是启用，才看得见旧实现把它改禁用');

        // 可执行的反事实：两个语义相反的字符串在旧实现里转型后是同一个值 ⇒ 分不开
        $this->assertSame((int) 'banned', (int) 'normal', '旧实现里「封禁」与「解封」都等于 0 = 禁用');

        $body = $this->json((new UserController())->batchStatus($this->post(
            '/admin/v1/user/batch/status',
            ['ids' => [HashidsService::encode($target)], 'status' => 'normal']
        )));

        $this->assertSame(422, $body['code'], 'status 不是 0/1 应当 422，实际：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, $this->statusOf($target), '被拒的请求不许改动任何一行');
    }

    #[Test]
    public function numericStringStatusIsStillAccepted(): void
    {
        $target = $this->ids[1];

        $body = $this->json((new UserController())->batchStatus($this->post(
            '/admin/v1/user/batch/status',
            ['ids' => [HashidsService::encode($target)], 'status' => '0']
        )));

        $this->assertSame(0, $body['code'], '表单编码的 "0" 是合法输入，不该被收口误伤');
        $this->assertSame(0, $this->statusOf($target));
    }

    #[Test]
    public function countIsAffectedRowsNotRequestCount(): void
    {
        $target = $this->ids[1];

        $body = $this->json((new UserController())->batchStatus($this->post(
            '/admin/v1/user/batch/status',
            ['ids' => [HashidsService::encode($target), HashidsService::encode($this->ghostId)], 'status' => 0]
        )));

        $this->assertSame(0, $body['code']);
        $this->assertSame(1, $body['data']['count'], '请求了 2 个 id，只有 1 个真实存在 ⇒ count 应为 1');
        $this->assertSame(0, $this->statusOf($target));
    }

    #[Test]
    public function batchDestroyCountsActuallyDeletedRows(): void
    {
        $target = $this->ids[1];

        $body = $this->json((new UserController())->batchDestroy($this->post(
            '/admin/v1/user/batch/destroy',
            [
                'ids'      => [HashidsService::encode($target), HashidsService::encode($this->ghostId)],
                'password' => self::PASSWORD,
            ]
        )));

        $this->assertSame(0, $body['code'], '密码应校验通过：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, $body['data']['count'], '文档口径是「实际删除数量」，不是请求条数');
        $this->assertNotNull(AdminUser::withTrashed()->find($target)->deleted_at, '目标行应被软删除');
    }
}
