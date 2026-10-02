<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\UserController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Request;

/**
 * `PUT /api/v1/user/privacy` 的**报文契约**：只回显 + 显式 `persisted=false`，但不静默吞掉输入。
 *
 * 原实现是 `success([], …)` + 文案 "Privacy settings updated" —— 客户端拿到 code=0、
 * 空 data，只能理解为"设置已生效"。事实是两个字段**全仓零读者**、也没有任何存储
 * （见 UserController::updatePrivacy 的注释）⇒ 那是**假成功**。
 *
 * 本轮裁决（lead 选的保守做法）：不实现完整隐私设置，但把"没存"变成报文里**可判**的事实：
 *   ① `persisted === false` —— 客户端据此可以提示"未生效"，而不是被骗；
 *   ② 提交的值原样回显 —— 顺带不自作主张地补默认值（补默认值＝第二真值源）。
 *
 * 钉的就是这两条。本用例**不触库**：updatePrivacy() 全程只做校验 + 组装响应
 * （与 AuthControllerRegisterTest 同一口径，validator() 由 tests/bootstrap.php 显式载入）。
 */
final class UserPrivacyEchoTest extends TestCase
{
    #[Test]
    public function echoesSubmittedValuesAndDeclaresNothingPersisted(): void
    {
        $body = $this->put([
            'show_in_leaderboard'       => false,
            'allow_email_notifications' => true,
        ]);

        $this->assertSame(0, $body['code'] ?? -1,
            '端点未成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertFalse($body['data']['persisted'] ?? true,
            '必须显式 persisted=false：这是"设置没有生效"在报文里唯一的可判信号');
        $this->assertSame(false, $body['data']['show_in_leaderboard'] ?? 'missing',
            '提交的值必须原样回显（false 不能被吞成 null/默认值）');
        $this->assertSame(true, $body['data']['allow_email_notifications'] ?? 'missing',
            '提交的值必须原样回显（true 不能被吞成 null/默认值）');
    }

    /**
     * 未提交的字段回显 null —— 不许"顺手补默认值"。
     *
     * 补默认值等于凭空生产一个用户从未表达过的偏好（第二真值源）：客户端把它当已生效设置展示，
     * 而服务端依旧零读者 ⇒ 比原来的空 data 更难发现。
     */
    #[Test]
    public function omittedFieldsEchoNullRatherThanAFabricatedDefault(): void
    {
        $body = $this->put(['show_in_leaderboard' => true]);

        $this->assertSame(0, $body['code'] ?? -1,
            '端点未成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertSame(true, $body['data']['show_in_leaderboard'] ?? 'missing',
            '提交的字段仍要回显');
        $this->assertArrayHasKey('allow_email_notifications', $body['data'] ?? [],
            '被提交的字段集合是契约的一部分：未提交也要出键（值为 null），否则客户端分不清"没传"和"没存"');
        $this->assertNull($body['data']['allow_email_notifications'],
            '未提交的字段必须回显 null；补一个默认值＝凭空造出用户没表达过的偏好');
    }

    /** @param array<string, mixed> $body */
    private function put(array $body): array
    {
        $payload = (string) json_encode($body);
        $request = new Request(
            "PUT /api/v1/user/privacy HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($payload) . "\r\n\r\n{$payload}"
        );
        // 生产环境由 UserAuth 中间件注入，PHPUnit 下手工放上（与 FriendGateOrPrecedenceTest 同）
        $request->userId = 1;

        return json_decode((string) (new UserController())->updatePrivacy($request)->rawBody(), true) ?? [];
    }
}
