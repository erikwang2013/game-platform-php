<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\ChatController;
use app\api\v1\controller\FriendController;
use common\HashidsService;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * 好友门（`status='accepted'` 才放行）的 OR 优先级绕过。
 *
 * 缺陷形状：两个 OR 分支写成 `where(闭包A)->orWhere(闭包B)->where('status','accepted')`，
 * 而 SQL 里 AND 比 OR 结合更紧 ⇒ 实际是 `(A) OR (B AND status)`，**A 分支不受 status 约束**。
 * A 分支是 `user_id=<调用方> and friend_id=<对方>`，而 `friend/request` 落的正是
 * `(user_id=申请人, friend_id=目标, status='pending')` ⇒ 发一条好友申请即可给任意用户发私信
 * （`chat/send` 是这条链上唯一的反骚扰闸门），也可把 pending 申请直接 delete 掉。
 *
 * 判据是**行为级**的：真的插一行 pending 行再走控制器真身，不比对 toSql() 文本
 * （文本比对只钉形状，钉不住"这一行到底放不放行"）。
 *
 * 库名必须含 test；全部写入包在外层事务里，tearDown 整笔回滚。
 */
final class FriendGateOrPrecedenceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // 控制器要走 encodeId()/decodeId()，依赖 hashids 容器绑定（webman 插件 bootstrap 注册的，
        // PHPUnit 下要手动起）。未显式建测试库 Capsule —— tests/bootstrap.php 已钉在测试库上，
        // 下面 setUp 里的库名断言就是这条前提的哨兵（前提破了要炸，不能静默 skip）。
        HashidsBootstrap::start(null);
    }

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());
        }

        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (Db::transactionLevel() > 0) {
            Db::rollBack();
        }

        parent::tearDown();
    }

    // ============================================================
    // 一、真钉子：发一条好友申请（pending）不得打开私信闸门
    // ============================================================

    #[Test]
    public function aPendingFriendRequestDoesNotOpenTheMessageGate(): void
    {
        $sender   = (int) SnowflakeService::generate();
        $receiver = (int) SnowflakeService::generate();

        // 这就是 friend/request 落的那一行：申请人=发件人，目标=收件人，status=pending
        $this->seedRelation($sender, $receiver, 'pending');

        $body = $this->send($sender, $receiver, 'gate probe');
        $this->assertSame(403, $body['code'] ?? -1,
            '仅"好友申请中"（pending，不是 accepted）时不得发私信 —— 否则任何人都能靠一次 request 给任意用户发私信，'
            . '这正是 `(A) OR (B AND status)` 里 A 分支不受 status 约束的后果。'
            . '实际：' . json_encode($body, JSON_UNESCAPED_UNICODE));

        $this->assertSame(0, $this->messageCount($sender, $receiver),
            '被闸门挡下的请求不得落库（回 403 但消息已写＝闸门只挡了半个）');
    }

    #[Test]
    public function aPendingRowCannotBeRemovedAsAFriend(): void
    {
        $sender   = (int) SnowflakeService::generate();
        $receiver = (int) SnowflakeService::generate();

        $this->seedRelation($sender, $receiver, 'pending');

        (new FriendController())->remove($this->request('/api/v1/friend/remove', ['friend_id' => HashidsService::encode($receiver)], $sender));

        $this->assertSame(1, $this->relationCount($sender, $receiver),
            'remove() 只该删 accepted 的行：pending 申请被删掉后，收件人的申请列表里凭空消失、'
            . '对它的 accept 变成 404（同一条 OR 优先级缺陷的第二处）。实际行数：'
            . $this->relationCount($sender, $receiver));
    }

    // ============================================================
    // 二、反向：门不能被焊死 —— accepted 仍要放行/可删（两个方向都测，
    //     防修 OR 组时把闭包里的 userId/peerId 写反）
    // ============================================================

    #[Test]
    public function anAcceptedFriendStillPassesTheGateInBothDirections(): void
    {
        foreach ([['out' => true], ['out' => false]] as $case) {
            $sender   = (int) SnowflakeService::generate();
            $receiver = (int) SnowflakeService::generate();

            // out=true：关系行由发送者发起；out=false：由接收者发起（另一条 OR 分支）
            $case['out']
                ? $this->seedRelation($sender, $receiver, 'accepted')
                : $this->seedRelation($receiver, $sender, 'accepted');

            $body = $this->send($sender, $receiver, 'accepted probe');
            $this->assertSame(0, $body['code'] ?? -1,
                'accepted 好友必须照旧放行（关系行方向：' . ($case['out'] ? '发送者发起' : '接收者发起') . '）。'
                . '实际：' . json_encode($body, JSON_UNESCAPED_UNICODE));
            $this->assertSame(1, $this->messageCount($sender, $receiver),
                '放行后消息必须真的落库（方向：' . ($case['out'] ? '发送者发起' : '接收者发起') . '）');
        }
    }

    #[Test]
    public function anAcceptedRowIsStillRemovableInBothDirections(): void
    {
        foreach ([['out' => true], ['out' => false]] as $case) {
            $sender   = (int) SnowflakeService::generate();
            $receiver = (int) SnowflakeService::generate();

            // 关系行的落库方向：out=true 由调用方发起，out=false 由对方发起（走另一条 OR 分支）
            $owner = $case['out'] ? $sender : $receiver;
            $other = $case['out'] ? $receiver : $sender;
            $this->seedRelation($owner, $other, 'accepted');

            (new FriendController())->remove($this->request('/api/v1/friend/remove', ['friend_id' => HashidsService::encode($receiver)], $sender));

            $this->assertSame(0, $this->relationCount($owner, $other),
                'accepted 好友必须仍能被 remove，两个方向都算（只把 pending 挡在门外，别把正常删除一起挡了）。'
                . '关系行方向：' . ($case['out'] ? '发送者发起' : '接收者发起'));
        }
    }

    // ============================================================
    // helpers
    // ============================================================

    /** @return array<string, mixed> ChatController::send 的响应信封 */
    private function send(int $from, int $to, string $content): array
    {
        $response = (new ChatController())->send($this->request(
            '/api/v1/chat/send',
            ['to_user_id' => HashidsService::encode($to), 'content' => $content],
            $from
        ));

        return json_decode((string) $response->rawBody(), true) ?? [];
    }

    private function seedRelation(int $userId, int $friendId, string $status): void
    {
        Db::table('friend')->insert([
            'id'         => (int) SnowflakeService::generate(),
            'user_id'    => $userId,
            'friend_id'  => $friendId,
            'status'     => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** **按落库方向**数行：user_id=$userId 且 friend_id=$friendId（每对用户只种一行） */
    private function relationCount(int $userId, int $friendId): int
    {
        return (int) Db::table('friend')
            ->where('user_id', $userId)->where('friend_id', $friendId)->count();
    }

    private function messageCount(int $from, int $to): int
    {
        return (int) Db::table('message')
            ->where('from_user_id', $from)->where('to_user_id', $to)->count();
    }

    private function request(string $path, array $body, int $userId): Request
    {
        $payload = (string) json_encode($body);
        $request = new Request(
            "POST {$path} HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($payload) . "\r\n\r\n{$payload}"
        );
        // 生产环境由 UserAuth 中间件注入，PHPUnit 下手工放上
        $request->userId = $userId;

        return $request;
    }
}
