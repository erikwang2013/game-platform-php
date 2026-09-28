<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\IdentityController;
use common\SnowflakeService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * KYC 双提交撞 uk_user_id：必须是干净的 422，不是 500。
 *
 * 这条把「实测」钉成「回归」：apply() 的 create 分支捕获 1062 转 422，依赖两件会随依赖升级
 * 变化的事实 —— `Illuminate\Database\UniqueConstraintViolationException` 是 PDOException 子类、
 * 且 message 里带**限定键名**（`game_user_identity.uk_user_id`）。所以这里**真去插一条重复键**，
 * 而不是断言我们以为的异常形状。
 *
 * 怎么在不并发的情况下真撞上：预检走 `kycSubmitAction($existing?->status)`，
 * 只有 pending/approved 判 reject、rejected 判 resubmit，**其余状态一律落到 create 分支**。
 * 于是造一行 status='draft' 的既有记录，即可让预检放行、插入真撞 uk_user_id。
 * （这也顺带记录一个既存取舍：未知状态会被当成「从未提交」；本用例只钉 1062 的处置。）
 *
 * 只打测试库：库名不含 test 直接 fail。用例自建自删，残留 0 行。
 */
class KycDuplicateSubmitTest extends TestCase
{
    private static bool $booted = false;

    private int $userId = 0;
    private string $message = '';

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
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

        $this->userId = SnowflakeService::generate();
    }

    protected function tearDown(): void
    {
        if ($this->userId > 0) {
            Db::table('user_identity')->where('user_id', $this->userId)->delete();
        }
    }

    #[Test]
    public function duplicateInsertOnUniqueKeyReturnsClean422(): void
    {
        // 既有行：status='draft' ⇒ 预检不拦（既不是 pending/approved，也不是 rejected）
        Db::table('user_identity')->insert([
            'id'        => SnowflakeService::generate(),
            'user_id'   => $this->userId,
            'real_name' => 'Existing',
            'id_number' => 'ID-EXISTING',
            'status'    => 'draft',
        ]);

        $request = new Request("POST /api/v1/identity/apply HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost([
            'real_name'      => 'Test User',
            'id_type'        => 'id_card',
            'id_number'      => 'ID-1234567',
            'id_front_photo' => 'https://example.test/front.jpg',
            'selfie_photo'   => 'https://example.test/selfie.jpg',
        ]);
        $request->userId = $this->userId; // 生产由 UserAuth 中间件注入

        $response = (new IdentityController())->apply($request);
        $body = json_decode((string) $response->rawBody(), true) ?? [];

        $this->assertSame(422, $body['code'] ?? null,
            '撞 uk_user_id 必须转成业务 422，实际：' . json_encode($body));
        $this->assertSame('You already have a pending or approved KYC submission', (string) ($body['message'] ?? ''));

        // 撞键后不得留下半写状态：既有行原样、没有第二行
        $rows = Db::table('user_identity')->where('user_id', $this->userId)->get(['status', 'id_number'])->all();
        $this->assertCount(1, $rows, '失败路径不得多插一行');
        $this->assertSame('draft', (string) $rows[0]->status, '失败路径不得改动既有行');
        $this->assertSame('ID-EXISTING', (string) $rows[0]->id_number);
    }

    private static function bootTargetDatabase(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        class_exists(Db::class);

        $conf = config('database');
        $name = $conf['default'];
        $conn = $conf['connections'][$name];

        $conn['database'] = getenv('DB_DATABASE_TEST') ?: 'game-platform-test';
        $user = getenv('GP_DB_USER');
        $pass = getenv('GP_DB_PASS');
        $conn['username'] = $user !== false && $user !== '' ? $user : $conn['username'];
        $conn['password'] = $pass !== false && $pass !== '' ? $pass : (string) $conn['password'];

        $capsule = new Capsule();
        $capsule->addConnection($conn, $name);
        $capsule->getDatabaseManager()->setDefaultConnection($name);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
