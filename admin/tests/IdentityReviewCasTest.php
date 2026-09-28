<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\admin\v1\controller\IdentityController;
use common\HashidsService;
use common\SnowflakeService;
use common\model\UserIdentity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * KYC 审核的状态翻转必须是 CAS（IdentityController::review）。
 *
 * 旧实现是「读出实体 → 看 status !== pending → 改属性 → save()」：两次判定之间没有原子性。
 * 两个管理员同时点通过/拒绝（或同一个人双击）时：
 *   - 两次都会通过那道 `!== 'pending'` 检查（都读到 pending）；
 *   - 后写的那次覆盖先写的结论 —— 通过/拒绝互相改写，reviewer_id 与 review_note 也跟着变；
 *   - 两条通知都发出去，用户收到「通过」又收到「拒绝」。
 * 现在翻转与判定是**同一个条件 UPDATE**（WHERE id = ? AND status = 'pending'），
 * affected rows = 0 的那次直接 422，不覆盖、不通知。
 *
 * 只打测试库：库名不含 test 直接 fail（沿用 RiskUserReleaseTest 的口径）。
 */
class IdentityReviewCasTest extends TestCase
{
    private int $identityId = 0;
    private int $userA = 0;
    private int $userB = 0;

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

        $this->userA = SnowflakeService::generate();
        $this->userB = SnowflakeService::generate();
        $this->identityId = $this->makePendingIdentity();
    }

    protected function tearDown(): void
    {
        if ($this->identityId !== 0) {
            Db::table('user_identity')->where('id', $this->identityId)->delete();
        }
    }

    private function makePendingIdentity(): int
    {
        // 走模型而不是 Db::table()：real_name / id_number 是 Encryptable 列，
        // 裸 insert 会写进明文、读回时按密文解，后面的断言就变味了。
        $row = new UserIdentity();
        $row->id        = SnowflakeService::generate();
        $row->user_id   = SnowflakeService::generate();
        $row->real_name = '张三';
        $row->id_type   = 'id_card';
        $row->id_number = '110101199001011234';
        $row->status    = 'pending';
        $row->save();

        return (int) $row->id;
    }

    private function review(int $adminId, string $action, string $note): array
    {
        $request = new Request("PUT /admin/v1/identity/review HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost([
            'id'     => HashidsService::encode($this->identityId),
            'action' => $action,
            'note'   => $note,
        ]);
        $request->adminId = $adminId;

        return json_decode((string) (new IdentityController())->review($request)->rawBody(), true) ?? [];
    }

    private function row(): UserIdentity
    {
        return UserIdentity::find($this->identityId);
    }

    /**
     * 真钉子：**交错**执行，而不是顺序执行两次。
     *
     * 顺序调用两次挡不住任何实现 —— 控制器开头那次 `find()` 就会读到『已审核』而提前 422，
     * 旧实现（读出实体 → 判 status → save()）同样能过。必须把「后手的写」塞进
     * 「先手的读」与「先手的写」之间，CAS 与读后写才分得开。
     *
     * 手法：挂 UserIdentity 的 retrieved 事件 —— 先手那次 `find()` 取到实体的瞬间触发，
     * 在回调里跑完整个后手审核（reject），然后再让先手继续往下走它的 UPDATE。
     */
    #[Test]
    public function interleavedSecondReviewCannotOverwriteTheFirst(): void
    {
        $inner = null;
        $reentered = false;
        UserIdentity::retrieved(function (UserIdentity $model) use (&$inner, &$reentered): void {
            if ($reentered || (int) $model->id !== $this->identityId) {
                return; // 后手自己那次 find() 不再触发，避免递归
            }
            $reentered = true;
            $inner = $this->review($this->userB, 'reject', '证件模糊');
        });

        // 先手：它会在 find() 之后、UPDATE 之前被上面那个回调插队
        $outer = $this->review($this->userA, 'approve', '资料齐全');

        $this->assertNotNull($inner, '交错没有生效（retrieved 回调未触发），这条用例失去意义');
        $this->assertSame(0, $inner['code'], '后手应拿到这一单：' . json_encode($inner, JSON_UNESCAPED_UNICODE));
        $this->assertSame(422, $outer['code'], '先手的 UPDATE 落空后必须干净失败，实际：' . json_encode($outer, JSON_UNESCAPED_UNICODE));

        $row = $this->row();
        $this->assertSame('rejected', $row->status, '结论应停在真正写进去的那一次（后手），先手不得覆盖');
        $this->assertSame($this->userB, (int) $row->reviewer_id);
        $this->assertSame('证件模糊', (string) $row->review_note);
    }

    #[Test]
    public function reReviewOfAnAlreadyDecidedRecordIsRejected(): void
    {
        $this->assertSame(0, $this->review($this->userA, 'approve', 'ok')['code']);

        // 同一个管理员再点一次（双击/重放）：同样是 0 行 affected ⇒ 422
        $again = $this->review($this->userA, 'approve', 'ok');
        $this->assertSame(422, $again['code']);
        $this->assertSame('approved', $this->row()->status);
    }

    #[Test]
    public function validationStillRejectsUnknownAction(): void
    {
        $body = $this->review($this->userA, 'maybe', '');
        $this->assertSame(422, $body['code']);
        $this->assertSame('pending', $this->row()->status, '校验失败不许碰这一行');
    }
}
