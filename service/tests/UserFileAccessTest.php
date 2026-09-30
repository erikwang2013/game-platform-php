<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\UserFileController;
use common\model\User;
use common\model\UserIdentity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use support\Db;
use Throwable;

/**
 * 个人件读取的授权矩阵 —— `UserFileController::readable()` 是唯一闸口。
 *
 * 为什么这条闸门重要：插件的 display 路由只验「有没有登录」，**不验这个 savedPath 属于谁**
 * （service 的 route.php 因此没注册它，个人件统一走 UserFileController）。判据三条：
 *   ① 不是你的、也不是任何人的头像 ⇒ 拒（越权读他人证件照必须被挡在这里）
 *   ② 本人的 KYC 三照 ⇒ 放行
 *   ③ 是某个用户的头像 ⇒ 放行（聊天/好友列表里本来就对别人可见）
 * ②③ 成对、①③ 成对：单看任一条都会把「所有人的文件都放行」或「谁都读不到」当成绿。
 */
class UserFileAccessTest extends TestCase
{
    private const OWNER = 990000701;
    private const OTHER = 990000702;

    /** 形如真实 savedPath（{group}_{subdir}_{md5}.{ext}），但内容 md5 是手工串，不会撞到真文件 */
    private const PHOTO = 'image_202610_leadaccess0000000000000000000.jpg';

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (Throwable $e) {
            $this->markTestSkipped('Database connection not available: ' . $e->getMessage());
        }
    }

    /** ① 既不是谁的 KYC 照、也不是谁的头像 ⇒ 拒 */
    #[Test]
    public function rejectsFileThatBelongsToNobody(): void
    {
        $this->assertFalse($this->readable(self::OTHER, self::PHOTO));
    }

    /** ② 本人的证件照 ⇒ 放行 */
    #[Test]
    public function allowsOwnIdentityPhoto(): void
    {
        Db::beginTransaction();
        try {
            $this->putIdentityPhoto(self::OWNER, self::PHOTO);
            $this->assertTrue($this->readable(self::OWNER, self::PHOTO));
        } finally {
            Db::rollBack();
        }
    }

    /** ②′ 别人的证件照 ⇒ 拒（与上一条成对：认的是归属，不是「库里有没有这条记录」） */
    #[Test]
    public function rejectsOtherUsersIdentityPhoto(): void
    {
        Db::beginTransaction();
        try {
            $this->putIdentityPhoto(self::OWNER, self::PHOTO);
            $this->assertFalse($this->readable(self::OTHER, self::PHOTO));
        } finally {
            Db::rollBack();
        }
    }

    /** ③ 是某个用户的头像 ⇒ 任何登录用户可读（与①成对：同一个 savedPath，改的是「它是不是某人的头像」） */
    #[Test]
    public function allowsAvatarOfAnyUser(): void
    {
        Db::beginTransaction();
        try {
            // 自己造两行，不依赖环境里已有的用户（全量跑时前面的用例可能在未提交事务里清过表）
            $owner = $this->makeUser(self::OWNER);
            $other = $this->makeUser(self::OTHER);

            $this->assertFalse($this->readable(self::OTHER, self::PHOTO), '设成头像之前谁都读不到（①）');

            $owner->avatar = self::PHOTO;
            $owner->save();

            $this->assertTrue($this->readable(self::OWNER, self::PHOTO), '头像本人可读');
            $this->assertTrue($this->readable(self::OTHER, self::PHOTO), '头像对别的登录用户也可读');
        } finally {
            Db::rollBack();
        }
    }

    private function readable(int $userId, string $savedPath): bool
    {
        $method = new ReflectionMethod(UserFileController::class, 'readable');
        $method->setAccessible(true);

        return (bool) $method->invoke(new UserFileController(), $userId, $savedPath);
    }

    private function putIdentityPhoto(int $userId, string $photo): void
    {
        $identity = UserIdentity::firstOrNew(['user_id' => $userId]);
        $identity->id = $userId + 1;
        $identity->real_name = 'lead-test';
        $identity->id_type = 'id_card';
        $identity->id_number = '000000';
        $identity->id_front_photo = $photo;
        $identity->save();
    }

    /** 造一个用户行（game_user 里除 id/username/password 外都有默认值），仅在事务内使用 */
    private function makeUser(int $id): User
    {
        $user = new User();
        $user->id = $id;
        $user->username = 'lead-test-' . $id;
        $user->password = 'x';
        $user->save();

        return $user;
    }
}
