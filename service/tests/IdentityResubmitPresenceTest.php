<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\IdentityController;
use common\SnowflakeService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * /user/identity/apply 重交分支的三组判据：可选字段的「未提交 vs 提交空」语义、
 * 必需字段的缺参行为、以及四个有列长的字段「超长 ⇒ 422 而不是 500」。
 *
 * ① 数据丢失（真实缺陷）：resubmit 分支原写 `input('country', '')` / `input('id_back_photo', '')` ——
 * 无条件覆盖。而两棵 web 树的提交体对空值是 `...(v ? {k: v} : {})`（**根本不发这个键**）⇒ 上次填过、
 * 驳回后重交时没再填，已存值就被 '' 悄悄抹掉（无声、无日志）。
 * 现语义（判据 support\Request::has()，口径同 UserController::updateProfile「只送改动的字段」）：
 *   带值 ⇒ 覆盖；不带该键 ⇒ 保留原值；显式空串 ⇒ 有意清空（flutter 树就是这么发空值的）。
 *
 * ⚠ 本文件同时是「resubmit 分支真能落库」的验收判据：该分支原把 reviewer_id 写 null，撞
 * `bigint unsigned NOT NULL DEFAULT 0` 列 ⇒ save() 抛 1048、整条 UPDATE 不提交、端点恒 500
 * （自 2026-05-22 起）。修复前本文件的红是**异常**而非断言失败 —— 所以每条都先断言「UPDATE 真的
 * 执行了」（status 被重置为 pending）再看字段值，否则「保留原值」会因为「什么都没写」而假绿。
 *
 * ② 超长 ⇒ 500：四个字段原先没有 `max:`，而列分别是 varchar(255)/(255)/(255)/(500) ⇒
 * 超长值一路写库、抛 1406 ⇒ 500（该 422 的给了 500）。id_number 列存的还是**密文**，
 * 明文上限不是 500 —— 实测（走模型 cast 真写库）密文 = 4*ceil((明文+17)/3)+76 ⇒ 明文 301 ⇒ 500、
 * 302 ⇒ 1406 ⇒ 校验取 256（密文 440，留余量）。去掉任一处 `max:` 时，对应的超长格子会以
 * QueryException(1406) 的形态变红。
 *
 * 只打测试库：库名不含 test 直接 fail。用例自建自删，残留 0 行。
 */
class IdentityResubmitPresenceTest extends TestCase
{
    private static bool $booted = false;

    private int $userId = 0;

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

    /**
     * 六个格子：两个可选字段 × {带值 / 不带该键 / 显式空串}。
     *
     * @return array<string, array{string, string, array<string, mixed>, string}>
     */
    public static function presenceTable(): array
    {
        $oldPhoto = 'https://old.test/back.jpg';
        $newPhoto = 'https://new.test/back.jpg';

        return [
            'country 带值 ⇒ 覆盖'          => ['country', 'CN', ['country' => 'JP'], 'JP'],
            'country 不带键 ⇒ 保留旧值'    => ['country', 'CN', [], 'CN'],
            'country 显式空串 ⇒ 清空'      => ['country', 'CN', ['country' => ''], ''],
            '背面照 带值 ⇒ 覆盖'           => ['id_back_photo', $oldPhoto, ['id_back_photo' => $newPhoto], $newPhoto],
            '背面照 不带键 ⇒ 保留旧值'     => ['id_back_photo', $oldPhoto, [], $oldPhoto],
            '背面照 显式空串 ⇒ 清空'       => ['id_back_photo', $oldPhoto, ['id_back_photo' => ''], ''],
        ];
    }

    /** @param array<string, mixed> $overrides */
    #[Test]
    #[DataProvider('presenceTable')]
    public function optionalFieldFollowsPresenceSemantics(string $field, string $stored, array $overrides, string $expected): void
    {
        $body = $this->runResubmit($overrides, [$field => $stored]);
        $this->assertSame(0, $body['code'] ?? null, '重交应成功，实际：' . json_encode($body));

        $row = $this->row([$field, 'status']);
        // 反假绿：字段断言只有在「回写真的落库」时才有意义（早退、或整条 UPDATE 被约束打回，
        // 都会让「保留原值」凭空成立）—— status 被重置为 pending 即证明 save() 执行了。
        $this->assertSame('pending', (string) $row->status, '重交必须真的落库，否则下面的字段断言恒真');

        $this->assertSame($expected, (string) $row->{$field});
    }

    /**
     * 三个必需字段 × {带值 / 不带该键}：带值 ⇒ 落库；不带键 ⇒ 422（required）且既有行原样。
     *
     * @return array<string, array{string, ?string, int}>
     */
    public static function requiredFieldTable(): array
    {
        return [
            'id_number 带值 ⇒ 写入'       => ['id_number', 'ID-1234567', 0],
            'id_number 不带键 ⇒ 422'      => ['id_number', null, 422],
            'id_front_photo 带值 ⇒ 写入'  => ['id_front_photo', 'https://example.test/front.jpg', 0],
            'id_front_photo 不带键 ⇒ 422' => ['id_front_photo', null, 422],
            'selfie_photo 带值 ⇒ 写入'    => ['selfie_photo', 'https://example.test/selfie.jpg', 0],
            'selfie_photo 不带键 ⇒ 422'   => ['selfie_photo', null, 422],
        ];
    }

    #[Test]
    #[DataProvider('requiredFieldTable')]
    public function requiredFieldIsWrittenOrRejected(string $field, ?string $value, int $expectCode): void
    {
        $body = $this->runResubmit([$field => $value]);

        if ($expectCode !== 0) {
            $this->assertSame(422, $body['code'] ?? null, "缺 {$field} 应回 422；实际：" . json_encode($body));
            $this->assertNotSame('', (string) ($body['message'] ?? ''), '校验失败要给信封文案');
            // 校验在写库之前返回：既有行仍是 rejected（与「重交成功 ⇒ pending」互为对照）
            $this->assertSame('rejected', (string) $this->row(['status'])->status, '校验失败不得动既有行');
            return;
        }

        $this->assertSame(0, $body['code'] ?? null, "提交应成功，实际：" . json_encode($body));
        $row = $this->row([$field, 'status']);
        $this->assertSame('pending', (string) $row->status, '重交必须真的落库，否则下面的字段断言恒真');

        if ($field === 'id_number') {
            // 该列有 Encryptable cast：库里是密文且 IV 随机 ⇒ 只能断言「不是明文」，
            // 不能拿密文跟任何期望值比（同值两次写入密文都不同）。
            $this->assertNotSame($value, (string) $row->id_number, 'id_number 应加密落库');
        } else {
            $this->assertSame($value, (string) $row->{$field});
        }
    }

    /**
     * 四个有列长的字段 × 超长值：必须 422（校验拦下），不能写库撞 1406 ⇒ 500。
     *
     * 值密度按列算：三个 photo 列 varchar(255)（id_back_photo 也在同一条 validator 里 ⇒ 一起钉）；
     * id_number 列 varchar(500) 存密文，400 字符的密文是 632 > 500。
     *
     * @return array<string, array{string, string}>
     */
    public static function overlongValueTable(): array
    {
        // 15 + 290 = 305 > 255
        $longUrl = 'https://example.test/' . str_repeat('a', 290);

        return [
            'id_number 400 字符（密文 632 > 500）' => ['id_number', str_repeat('9', 400)],
            'id_front_photo 305 字符（列 255）'    => ['id_front_photo', $longUrl],
            'id_back_photo 305 字符（列 255）'     => ['id_back_photo', $longUrl],
            'selfie_photo 305 字符（列 255）'      => ['selfie_photo', $longUrl],
        ];
    }

    #[Test]
    #[DataProvider('overlongValueTable')]
    public function overlongValueIs422NotServerError(string $field, string $tooLong): void
    {
        $body = $this->runResubmit([$field => $tooLong]);

        // 去掉该字段的 max: 后，这里不再是断言失败而是 QueryException(1406) ⇒ 用例 error：
        // 两种红都拦得住，且报错文本里能直接读到 1406。
        $this->assertSame(422, $body['code'] ?? null, "超长 {$field} 应回 422；实际：" . json_encode($body));
        $this->assertNotSame('', (string) ($body['message'] ?? ''), '校验失败要给信封文案');
        $this->assertSame('rejected', (string) $this->row(['status'])->status, '校验失败不得动既有行（更不该抛 1406）');
    }

    /**
     * 造一行「已驳回」记录并提交重审，返回响应信封。
     *
     * @param array<string, mixed> $overrides 覆盖提交体字段；值为 null ⇒ **不发这个键**（模拟客户端不发空值）
     * @param array<string, mixed> $seed      覆盖驳回行的已存值
     * @return array<string, mixed>
     */
    private function runResubmit(array $overrides, array $seed = []): array
    {
        Db::table('user_identity')->insert(array_merge([
            'id'          => SnowflakeService::generate(),
            'user_id'     => $this->userId,
            'real_name'   => 'Existing',
            'id_number'   => 'ID-EXISTING',
            'status'      => 'rejected',
            // 生产形状：驳回行由 admin 审核写入（admin/app/admin/v1/controller/IdentityController.php:112-115
            // 写 status=rejected + reviewer_id=adminId + review_note），不是列默认的 0。
            'reviewer_id' => 123456789,
            'review_note' => 'ID 照片模糊',
        ], $seed));

        $post = [
            'real_name'      => 'Test User',
            'id_type'        => 'id_card',
            'id_number'      => 'ID-1234567',
            'id_front_photo' => 'https://example.test/front.jpg',
            'selfie_photo'   => 'https://example.test/selfie.jpg',
        ];
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($post[$key]);
            } else {
                $post[$key] = $value;
            }
        }

        $request = new Request("POST /api/v1/user/identity/apply HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost($post);
        $request->userId = $this->userId; // 生产由 UserAuth 中间件注入

        $response = (new IdentityController())->apply($request);

        return json_decode((string) $response->rawBody(), true) ?? [];
    }

    /** @param list<string> $columns */
    private function row(array $columns): object
    {
        return Db::table('user_identity')->where('user_id', $this->userId)->first($columns);
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
