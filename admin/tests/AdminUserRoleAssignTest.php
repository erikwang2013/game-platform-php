<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\UserController;
use app\model\AdminRole;
use app\model\AdminUser;
use common\HashidsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Redis;
use support\Request;
use Throwable;
use Webman\Exception\BusinessException;

/**
 * 管理员「分配角色」的写路径，以及两个配套语义：**权限缓存必须被清**、**改密码要操作者二次确认**。
 *
 * 背景：`UserController` 管的是 `game_admin_user`（后台账号），但在此之前**全仓没有一处
 * 把管理员挂到角色上**（`AdminUser::roles()` 只有装机器用过一次）⇒ 前端做「分配角色」时
 * 没有任何端点可打。这套钉子钉的是新加的那条路：
 *   ① `role_ids` 收 **hashid 数组**，落 `game_admin_user_role`，语义是 **sync（全量替换）**；
 *   ② 裸数字 id 必须 **400 fail-fast**（不能静默丢成 0 —— 与 `role.permission_ids` 同一族）；
 *   ③ 改完角色**必须清 `perm:{adminId}`**（`AdminPermission` 缓存 60 秒，不清就「当场不生效」）；
 *   ④ `password` 改动要**当前操作者**的密码（字段名 `admin_password`，与 DELETE 同款确认）。
 */
class AdminUserRoleAssignTest extends TestCase
{
    private const ADMIN_ID = 990000801;
    private const OPERATOR_ID = 990000802;
    private const ROLE_A = 990000811;
    private const ROLE_B = 990000812;
    private const OPERATOR_PASSWORD = 'Aa123456';

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    /** ① sync 语义：先挂两个、再只留一个（全量替换、不是追加）、最后清空 */
    #[Test]
    public function roleIdsAreSyncedIntoPivotAsWholeReplacement(): void
    {
        Db::beginTransaction();
        try {
            $this->seed();
            $controller = new UserController();

            $controller->update($this->put(['role_ids' => [$this->hash(self::ROLE_A), $this->hash(self::ROLE_B)]]), $this->hash(self::ADMIN_ID));
            $this->assertSame(2, $this->pivotCount(), '两个角色都应落中间表');

            $controller->update($this->put(['role_ids' => [$this->hash(self::ROLE_B)]]), $this->hash(self::ADMIN_ID));
            $this->assertSame([self::ROLE_B], $this->pivotRoleIds(), 'sync 是全量替换：A 应被摘掉');

            $controller->update($this->put(['role_ids' => []]), $this->hash(self::ADMIN_ID));
            $this->assertSame(0, $this->pivotCount(), '空数组 = 解绑全部');
        } finally {
            Db::rollBack();
        }
    }

    /** ② 裸数字 id（旧口径）必须 400：decodeId 对非法 hashid 抛 BusinessException */
    #[Test]
    public function bareNumericRoleIdIsRejected(): void
    {
        Db::beginTransaction();
        try {
            $this->seed();
            $this->expectException(BusinessException::class);
            (new UserController())->update($this->put(['role_ids' => ['12345']]), $this->hash(self::ADMIN_ID));
        } finally {
            Db::rollBack();
        }
    }

    /** ③ 分配角色后 `perm:{adminId}` 必须被清（否则界面/接口 60 秒内看不到新权限） */
    #[Test]
    public function roleAssignmentClearsPermissionCache(): void
    {
        Db::beginTransaction();
        try {
            $this->seed();
            $cacheKey = 'perm:' . self::ADMIN_ID;
            Redis::setex($cacheKey, 60, '["*"]');

            (new UserController())->update($this->put(['role_ids' => [$this->hash(self::ROLE_A)]]), $this->hash(self::ADMIN_ID));

            $this->assertFalse((bool) Redis::get($cacheKey), '分配角色后必须清权限缓存');
        } finally {
            Db::rollBack();
            try {
                Redis::del('perm:' . self::ADMIN_ID);
            } catch (Throwable) {
                // Redis 不可用时本用例已经失败在断言上，不再掩盖
            }
        }
    }

    /** ④ 只给新密码、不给操作者密码 ⇒ 422；给对 ⇒ 通过 */
    #[Test]
    public function passwordChangeRequiresOperatorConfirmation(): void
    {
        Db::beginTransaction();
        try {
            $this->seed();
            $controller = new UserController();

            $rejected = $this->json($controller->update($this->put(['password' => 'Bb123456']), $this->hash(self::ADMIN_ID)));
            $this->assertSame(422, (int) $rejected['code']);
            $this->assertSame('敏感操作需要输入密码确认', (string) $rejected['message']);

            $ok = $this->json($controller->update($this->put([
                'password'       => 'Bb123456',
                'admin_password' => self::OPERATOR_PASSWORD,
            ]), $this->hash(self::ADMIN_ID)));
            $this->assertSame(0, (int) $ok['code'], '带对操作者密码应通过：' . json_encode($ok));
            $this->assertTrue(password_verify('Bb123456', (string) AdminUser::find(self::ADMIN_ID)->password), '新密码应已生效');
        } finally {
            Db::rollBack();
        }
    }

    /** ⑤ 列表回传 role_ids（hashid）且不夹带裸 roles */
    #[Test]
    public function indexReturnsHashidRoleIdsWithoutRawRoles(): void
    {
        Db::beginTransaction();
        try {
            $this->seed();
            (new UserController())->update($this->put(['role_ids' => [$this->hash(self::ROLE_A)]]), $this->hash(self::ADMIN_ID));

            $payload = $this->json((new UserController())->index($this->get(['keyword' => 'lead-role-test'])));
            $row = null;
            foreach ($payload['data']['list'] ?? [] as $item) {
                if (($item['id'] ?? '') === $this->hash(self::ADMIN_ID)) {
                    $row = $item;
                }
            }
            $this->assertNotNull($row, '应能按 keyword 找到测试管理员');
            $this->assertSame([$this->hash(self::ROLE_A)], $row['role_ids'] ?? null, 'role_ids 必须是 hashid 数组');
            $this->assertArrayNotHasKey('roles', $row, '裸 roles 不外泄');
        } finally {
            Db::rollBack();
        }
    }

    /* ---------------------------------------------------------------- 工具 */

    private function seed(): void
    {
        foreach ([[self::ADMIN_ID, 'lead-role-test-admin', 'x'], [self::OPERATOR_ID, 'lead-role-test-operator', self::OPERATOR_PASSWORD]] as [$id, $name, $plain]) {
            $user = new AdminUser();
            $user->id = $id;
            $user->username = $name;
            $user->password = password_hash($plain, PASSWORD_BCRYPT);
            $user->real_name = $name;
            $user->status = 1;
            $user->save();
        }

        foreach ([[self::ROLE_A, 'lead-role-test-a'], [self::ROLE_B, 'lead-role-test-b']] as [$id, $slug]) {
            $role = new AdminRole();
            $role->id = $id;
            $role->name = $slug;
            $role->slug = $slug;
            $role->status = 1;
            $role->save();
        }
    }

    private function pivotCount(): int
    {
        return (int) Db::table('admin_user_role')->where('user_id', self::ADMIN_ID)->count();
    }

    /** @return int[] */
    private function pivotRoleIds(): array
    {
        return array_map('intval', Db::table('admin_user_role')->where('user_id', self::ADMIN_ID)->orderBy('role_id')->pluck('role_id')->all());
    }

    private function hash(int $id): string
    {
        return HashidsService::encode($id);
    }

    /**
     * PUT 请求：**body 走 JSON**（真实前端就是这么发的，webman 见 json content-type 即 json_decode）。
     * ⚠ 不能用 urlencoded：`http_build_query(['role_ids' => []])` 会退化成空串 ⇒ 键整个消失，
     * 「清空全部角色」这条路径就测不到（实测踩过）。
     */
    private function put(array $body): Request
    {
        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE);
        $request = new Request(
            "PUT /admin/v1/user/x HTTP/1.1\r\nHost: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($encoded) . "\r\n\r\n" . $encoded
        );
        $request->adminId = self::OPERATOR_ID;

        return $request;
    }

    private function get(array $query): Request
    {
        $request = new Request('GET /admin/v1/user?' . http_build_query($query) . " HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->adminId = self::OPERATOR_ID;

        return $request;
    }

    private function json(\support\Response $response): array
    {
        return json_decode((string) $response->rawBody(), true) ?? [];
    }
}
