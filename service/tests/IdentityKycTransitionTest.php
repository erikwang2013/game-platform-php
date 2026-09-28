<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\IdentityController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * KYC 提交状态流转（IdentityController::kycSubmitAction）。
 *
 * 覆盖背景：状态机原先内联在 apply() 里（全仓零测试，删掉假用例后暴露）。三条分支的
 * 后果差异很大，写错一条都是真事故：
 *   - reject   写成 create → 同一 user_id 反复提交，绕过「已有待审/已通过记录不得重复提交」
 *   - resubmit 写成 create → 驳回后重交走新建，旧记录残留 + user_id 唯一键冲突（500）
 *   - pending/approved 判定放宽 → 审核中或已认证的用户可再提交，审核队列被刷
 *
 * 断言逐状态精确匹配（含大小写与空串边界），不触库：入参就是「已有记录的状态」。
 */
class IdentityKycTransitionTest extends TestCase
{
    private static function action(?string $existingStatus): string
    {
        return (new ReflectionMethod(IdentityController::class, 'kycSubmitAction'))
            ->invoke(null, $existingStatus);
    }

    /** @return array<string, array{?string, string}> */
    public static function statusTable(): array
    {
        return [
            // —— 已有记录（$existing !== null）——
            '审核中'            => ['pending', 'reject'],
            '已通过'            => ['approved', 'reject'],
            '已驳回'            => ['rejected', 'resubmit'],
            '过期'              => ['expired', 'create'],
            '未知状态'          => ['canceled', 'create'],
            '空串状态'          => ['', 'create'],
            '大小写不同'        => ['PENDING', 'create'],
            '尾随空格'          => ['approved ', 'create'],
            '字符串零'          => ['0', 'create'],
            // —— 无记录（$existing === null）——
            '从未提交'          => [null, 'create'],
        ];
    }

    #[Test]
    #[DataProvider('statusTable')]
    public function transitionIsExactMatchOnStatus(?string $existingStatus, string $expected): void
    {
        $this->assertSame($expected, self::action($existingStatus));
    }

    /**
     * null 状态但「有记录」这一形状（状态列未写/被清空）在调用方与「无记录」同路 —— 走新建。
     * 这是既有行为：调用方传 `$existing?->status`，null 无法区分两者。此处钉住以免被人
     * 顺手改成「有记录即拒绝」而改变线上语义。
     */
    #[Test]
    public function nullStatusIsIndistinguishableFromNoRecord(): void
    {
        $this->assertSame('create', self::action(null));
    }

    /** 除 rejected 外任何非 pending/approved 状态都走新建（新增状态值时不会静默变成拒绝） */
    #[Test]
    public function onlyRejectedResubmits(): void
    {
        foreach (['rejected', 'REJECTED', 'reject', 'resubmit'] as $status) {
            $expected = $status === 'rejected' ? 'resubmit' : 'create';
            $this->assertSame($expected, self::action($status), var_export($status, true));
        }
    }
}
