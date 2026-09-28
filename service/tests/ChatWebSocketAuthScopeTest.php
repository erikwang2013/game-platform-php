<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\process\ChatWebSocket;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Workerman\Connection\TcpConnection;

/**
 * 聊天 WS 的 auth 分支必须与 UserAuth 用同一条判据：带 scope 的半成品登录态
 * （登录遇 2FA 时签发的 pending_2fa 票据）不得把自己认证成受害者。
 *
 * 与 UserAuth 的差别在后果：WS 认证一次即长期有效（连接不断就不过期），
 * 用 pending_2fa 票据接上即可持续收取 deliverToUser 投递给该 user_id 的消息。
 *
 * 测法：onMessage 的依赖只有 TcpConnection（send + 动态属性 userId），用 PHPUnit 的
 * createMock 替身即可 —— 不连库、不连 Redis、不起真 socket，故不受并发测试影响。
 */
final class ChatWebSocketAuthScopeTest extends TestCase
{
    private const USER_ID = 8675309;

    /** 缺陷复现点：2FA 待验证票据不得通过 WS auth */
    #[Test]
    public function pendingTwoFactorTicketCannotAuthenticate(): void
    {
        $connection = $this->createMock(TcpConnection::class);

        $sent = $this->auth($connection, jwt_wrapper()->create(['sub' => self::USER_ID, 'scope' => 'pending_2fa'], 600));

        $this->assertSame(
            'error',
            $sent['type'] ?? '',
            '待验证票据必须被拒（否则等于绕过第二因子接上聊天）：' . json_encode($sent)
        );
        $this->assertSame(0, $connection->userId ?? 0, '被拒的连接不得带上受害者身份');
    }

    /** 负例：正式 access 令牌必须照旧认证成功，且 userId 与响应帧逐字不变 */
    #[Test]
    public function accessTokenStillAuthenticates(): void
    {
        $connection = $this->createMock(TcpConnection::class);

        $sent = $this->auth($connection, jwt_wrapper()->create(['sub' => self::USER_ID, 'username' => 'ws_user']));

        $this->assertSame(
            ['type' => 'authenticated', 'user_id' => self::USER_ID],
            $sent,
            'access 令牌的握手行为必须与改前完全一致'
        );
        $this->assertSame(self::USER_ID, $connection->userId ?? 0, '认证成功应绑定 userId');
    }

    /** 既有失败路径不受影响：无效令牌仍回 error/Invalid token */
    #[Test]
    public function invalidTokenStillRejected(): void
    {
        $connection = $this->createMock(TcpConnection::class);

        $sent = $this->auth($connection, 'not-a-jwt');

        $this->assertSame(['type' => 'error', 'message' => 'Invalid token'], $sent);
        $this->assertSame(0, $connection->userId ?? 0, '无效令牌不得绑定 userId');
    }

    /** @return array<string, mixed> auth 分支发出的那一帧 */
    private function auth(TcpConnection $connection, string $token): array
    {
        $sent = [];
        $connection->method('send')->willReturnCallback(static function ($payload) use (&$sent) {
            $sent[] = json_decode((string) $payload, true);
            return true;
        });

        (new ChatWebSocket())->onMessage($connection, (string) json_encode(['action' => 'auth', 'token' => $token]));

        $this->assertCount(1, $sent, 'auth 分支应恰好回一帧：' . json_encode($sent));

        return $sent[0] ?? [];
    }
}
