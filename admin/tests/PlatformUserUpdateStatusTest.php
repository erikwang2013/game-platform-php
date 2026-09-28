<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\admin\v1\controller\PlatformUserController;
use common\HashidsService;
use common\SnowflakeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * PUT /admin/v1/platform/user/{hashid}（PlatformUserController::update）的校验与读数。
 *
 * 修的是与 UserController::batchStatus **同形**的两处：
 *
 * 一、status 无校验 ⇒ fill() 进 User::$casts 的 int 强转，'banned'/'normal'/[] 被**静默解释**成 int。
 *     其中 'normal' 最重：(int)'normal' === 0 === (int)'banned' ⇒ 「点解封」落成禁用，真实可达。
 *     （Angular 页的解封按钮发的正是 'normal'。）
 *
 * 二、返回 `success([], '更新成功')` 不带行数 ⇒ 提交与当前相同的值时界面照样显示成功。
 *     修法**不能**只靠 MySQL 的 changed-rows：Eloquent 的 Builder::update() 会顺手写
 *     `updated_at = now()`（实测生成 `set status = ?, updated_at = ?`），同值提交也会被算成 1 行。
 *     所以控制器先比一遍：同值 ⇒ 不发 UPDATE、直接 count 0。
 *     下面的同值用例把 updated_at 钉到 2020 年，正是为了让「有没有真的发过 UPDATE」可见 ——
 *     否则同秒内的写入会让断言恒真（假钉子）。
 *
 * 只打测试库：库名不含 test 直接 fail（沿用 UserBatchAffectedRowsTest 的口径）。
 */
class PlatformUserUpdateStatusTest extends TestCase
{
    private int $userId = 0;
    private const PASSWORD = 'platform-update-test';
    private const AGED_AT  = '2020-01-01 00:00:00';

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
        Db::table('user')->insert([
            'id'       => $this->userId,
            'username' => 'platform-update-' . $this->userId,
            'password' => password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            'nickname' => 'alice',
            'status'   => 1,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->userId > 0) {
            Db::table('user')->where('id', $this->userId)->delete();
        }
        $this->userId = 0;
    }

    /** 用 HTTP 原文 + setPost 构造请求：`new Request('PUT', '/path')` 两参形式读不到任何输入。 */
    private function put(array $body): array
    {
        $request = new Request("PUT /admin/v1/platform/user/x HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost($body);

        $response = (new PlatformUserController())->update($request, HashidsService::encode($this->userId));

        return json_decode((string) $response->rawBody(), true) ?? [];
    }

    private function statusOf(): int
    {
        return (int) Db::table('user')->where('id', $this->userId)->value('status');
    }

    private function updatedAtOf(): string
    {
        return (string) Db::table('user')->where('id', $this->userId)->value('updated_at');
    }

    /** 把 updated_at 推到过去：让「同值提交到底有没有发 UPDATE」变可见（同秒写入会让断言恒真）。 */
    private function ageRow(): void
    {
        Db::table('user')->where('id', $this->userId)->update(['updated_at' => self::AGED_AT]);
    }

    // ============================================================
    // 一、非法输入被**拒**，不是被解释
    // ============================================================

    #[Test]
    public function bannedStringIsRejectedNotInterpretedAsDisable(): void
    {
        $this->ageRow();

        $body = $this->put(['status' => 'banned']);

        $this->assertSame(422, $body['code'], 'status 不是 0/1 应当 422，实际：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, $this->statusOf(), '被拒的请求不许改动任何一行');
        $this->assertSame(self::AGED_AT, $this->updatedAtOf(), '被拒的请求不该写库');
    }

    #[Test]
    public function normalStringIsRejectedNotCoercedToDisable(): void
    {
        $this->ageRow();

        // 可执行的反事实：两个语义相反的字符串在旧实现里转型后是同一个值 ⇒ 分不开
        $this->assertSame((int) 'banned', (int) 'normal', '旧实现里「封禁」与「解封」都等于 0 = 禁用');

        $body = $this->put(['status' => 'normal']);

        $this->assertSame(422, $body['code'], '解封按钮发的 "normal" 也必须被拒，实际：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, $this->statusOf(), '点解封不该把用户禁用');
        $this->assertSame(self::AGED_AT, $this->updatedAtOf());
    }

    #[Test]
    public function arrayStatusIsRejected(): void
    {
        $this->ageRow();

        $body = $this->put(['status' => [1]]);

        $this->assertSame(422, $body['code']);
        $this->assertSame(1, $this->statusOf());
    }

    #[Test]
    public function tooLongNicknameIsRejectedAnd50IsAccepted(): void
    {
        // 列宽口径：game_user.nickname = VARCHAR(50)
        $body = $this->put(['nickname' => str_repeat('x', 51)]);
        $this->assertSame(422, $body['code'], '51 字符超出 VARCHAR(50)，应当 422');

        $body = $this->put(['nickname' => str_repeat('y', 50)]);
        $this->assertSame(0, $body['code'], '50 字符是边界内，应当收下：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, $body['data']['count']);
        $this->assertSame(str_repeat('y', 50), (string) Db::table('user')->where('id', $this->userId)->value('nickname'));
    }

    #[Test]
    public function emptyPayloadIsRejected(): void
    {
        $body = $this->put([]);

        $this->assertSame(422, $body['code'], '两个字段都没有 ⇒ 没有可更新的字段，不该回「更新成功」');
    }

    // ============================================================
    // 二、count = 真正改动的行数：同值提交必须是 0
    // ============================================================

    #[Test]
    public function sameValueSubmitReportsZeroAffectedRowsAndWritesNothing(): void
    {
        $this->ageRow();

        $body = $this->put(['status' => 1]);            // 当前就是 1 = 启用

        $this->assertSame(0, $body['code']);
        $this->assertSame(0, $body['data']['count'], '同值提交应报 0 行，而不是「更新成功、改了 1 行」');
        $this->assertSame(self::AGED_AT, $this->updatedAtOf(), '同值提交不该发出 UPDATE（updated_at 必须原封不动）');
    }

    #[Test]
    public function realChangeReportsOneAffectedRow(): void
    {
        $this->ageRow();

        $body = $this->put(['status' => 0]);

        $this->assertSame(0, $body['code']);
        $this->assertSame(1, $body['data']['count'], '真改动应报 1 行');
        $this->assertSame(0, $this->statusOf(), '禁用应真的落库');
        $this->assertNotSame(self::AGED_AT, $this->updatedAtOf(), '真改动必须刷新 updated_at');
    }

    #[Test]
    public function numericStringStatusIsStillAccepted(): void
    {
        $body = $this->put(['status' => '0']);          // form 编码路径

        $this->assertSame(0, $body['code'], '表单编码的 "0" 是合法输入，不该被收口误伤');
        $this->assertSame(1, $body['data']['count']);
        $this->assertSame(0, $this->statusOf());
    }

    #[Test]
    public function unknownUserIsNotFound(): void
    {
        $request = new Request("PUT /admin/v1/platform/user/x HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost(['status' => 0]);

        $response = (new PlatformUserController())->update($request, HashidsService::encode(SnowflakeService::generate()));
        $body = json_decode((string) $response->rawBody(), true) ?? [];

        $this->assertSame(404, $body['code'], '不存在的用户应 404');
    }
}
