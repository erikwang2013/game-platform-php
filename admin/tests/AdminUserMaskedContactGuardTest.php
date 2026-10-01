<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\UserController;
use app\model\AdminUser;
use common\HashidsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use Throwable;

/**
 * 管理员 phone/email 的**读模型回环**防护。
 *
 * 缺陷形状（做管理端「管理员」模块时发现）：`index` 把 phone 脱敏成 `138****8888`、
 * email 脱敏成 `a***@x.com` 才下发，而编辑表单是**拿列表行预填**的 ⇒ 管理员在那个串上
 * 接着改字，回传的就是脱敏串本身。phone/email 是 encryptable 加密列，写进去等于把
 * **原值不可恢复地覆盖**（`update` 的规则只有 `string|max:500`，拦不住 `***`）。
 *
 * 钉子分四段，缺一不可：
 *   ① 列表确实下发了脱敏串（前提为真，否则这套防护是给不存在的问题写的）；
 *   ② 脱敏串回写被当成「没改」——原值原样保留；
 *   ③ email 同理；
 *   ④ **正控**：真正的新值照样写得进去（证明②不是「phone 字段根本写不动」的假绿）。
 */
class AdminUserMaskedContactGuardTest extends TestCase
{
    private const ADMIN_ID = 990000901;
    private const REAL_PHONE = '13812345678';
    private const REAL_EMAIL = 'alice@example.com';
    private const MASKED_PHONE = '138****5678';
    private const MASKED_EMAIL = 'a***@example.com';

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    /** ① 前提：列表下发的 phone/email 确实是脱敏串（`index` 的 preg_replace / mb_substr） */
    #[Test]
    public function indexReturnsMaskedContactFields(): void
    {
        Db::beginTransaction();
        try {
            $this->seed();

            $row = $this->findRow();

            $this->assertSame(self::MASKED_PHONE, (string) ($row['phone'] ?? ''), '列表必须下发脱敏后的 phone');
            $this->assertSame(self::MASKED_EMAIL, (string) ($row['email'] ?? ''), '列表必须下发脱敏后的 email');
        } finally {
            Db::rollBack();
        }
    }

    /** ② 把列表给的脱敏串原样回写 ⇒ 按「没改」处理，库里仍是真号码 */
    #[Test]
    public function maskedPhoneIsTreatedAsUnchanged(): void
    {
        Db::beginTransaction();
        try {
            $this->seed();

            $body = $this->json((new UserController())->update(
                $this->put(['real_name' => '改过名', 'phone' => self::MASKED_PHONE]),
                $this->hash(self::ADMIN_ID)
            ));

            $this->assertSame(0, (int) $body['code'], '回写脱敏串不该报错（用户意图是「没改」）：' . json_encode($body));
            $this->assertSame(self::REAL_PHONE, (string) AdminUser::find(self::ADMIN_ID)->phone, '掩码不得覆盖真实号码');
            $this->assertSame('改过名', (string) AdminUser::find(self::ADMIN_ID)->real_name, '同一请求里的其它字段照常生效');
        } finally {
            Db::rollBack();
        }
    }

    /** ③ email 同理（脱敏形状是 `a***@domain`，与 phone 的中段掩码不同形） */
    #[Test]
    public function maskedEmailIsTreatedAsUnchanged(): void
    {
        Db::beginTransaction();
        try {
            $this->seed();

            $body = $this->json((new UserController())->update(
                $this->put(['email' => self::MASKED_EMAIL]),
                $this->hash(self::ADMIN_ID)
            ));

            $this->assertSame(0, (int) $body['code'], json_encode($body));
            $this->assertSame(self::REAL_EMAIL, (string) AdminUser::find(self::ADMIN_ID)->email, '掩码不得覆盖真实邮箱');
        } finally {
            Db::rollBack();
        }
    }

    /**
     * ④ 正控：不含 `***` 的新值必须照常写入。
     * 少了这条，把 update 里的 phone/email 赋值整段删掉也能让②③全绿——那是假绿。
     */
    #[Test]
    public function genuineNewContactValuesStillPersist(): void
    {
        Db::beginTransaction();
        try {
            $this->seed();

            $body = $this->json((new UserController())->update(
                $this->put(['phone' => '13900001111', 'email' => 'bob@example.org']),
                $this->hash(self::ADMIN_ID)
            ));

            $this->assertSame(0, (int) $body['code'], json_encode($body));
            $this->assertSame('13900001111', (string) AdminUser::find(self::ADMIN_ID)->phone);
            $this->assertSame('bob@example.org', (string) AdminUser::find(self::ADMIN_ID)->email);
        } finally {
            Db::rollBack();
        }
    }

    /* ---------------------------------------------------------------- 工具 */

    private function seed(): void
    {
        $user = new AdminUser();
        $user->id = self::ADMIN_ID;
        $user->username = 'lead-mask-test-admin';
        $user->password = password_hash('Aa123456', PASSWORD_BCRYPT);
        $user->real_name = 'lead-mask-test';
        $user->phone = self::REAL_PHONE;
        $user->email = self::REAL_EMAIL;
        $user->status = 1;
        $user->save();
    }

    /** @return array<string,mixed> 列表里本用例那行 */
    private function findRow(): array
    {
        $payload = $this->json((new UserController())->index($this->get(['keyword' => 'lead-mask-test-admin'])));
        foreach ($payload['data']['list'] ?? [] as $item) {
            if (($item['id'] ?? '') === $this->hash(self::ADMIN_ID)) {
                return $item;
            }
        }

        $this->fail('列表里找不到测试管理员（keyword 过滤或分页行为变了？）');
    }

    private function hash(int $id): string
    {
        return HashidsService::encode($id);
    }

    /** 与真实前端同款：body 走 JSON（urlencoded 表达不了空数组，本文件虽无 role_ids，保持口径一致） */
    private function put(array $body): Request
    {
        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE);
        $request = new Request(
            "PUT /admin/v1/user/x HTTP/1.1\r\nHost: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($encoded) . "\r\n\r\n" . $encoded
        );
        $request->adminId = self::ADMIN_ID;

        return $request;
    }

    private function get(array $query): Request
    {
        $request = new Request('GET /admin/v1/user?' . http_build_query($query) . " HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->adminId = self::ADMIN_ID;

        return $request;
    }

    private function json(\support\Response $response): array
    {
        return json_decode((string) $response->rawBody(), true) ?? [];
    }
}
