<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\TwoFactorController;
use app\model\User2FA;
use common\model\User;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * 备份码必须**真的能兑付**。
 *
 * 缺陷形状（2026-10-01 由 C 端 react 那路发现、我复核并修）：
 * `enable()` 校验完 TOTP 后给用户发 **8 个 10 位字母数字备份码**，而 `verify()`（登录第二步）
 * 的校验规则是 `size:6` —— 长度先把它挡死，于是 `verify()` 里那段
 * `array_search($code, $backupCodes)` 是**不可达的死代码**。
 * 症状：**系统承诺了恢复路径却兑不了**，丢了验证器的人照着提示填备份码必然 422。
 *
 * 这条钉子同时钉两端：
 *  ① 10 位码能**走到**业务分支（长度门已撤）——只钉这一半，把规则改成 `min:1` 也能过；
 *  ② 正确的备份码**真能换到登录态、且用掉即作废**——这才是「备份码可用」的实质。
 *
 * 只打测试库：连接库名必须含 test，否则硬失败，绝不静默写开发库。
 */
final class TwoFactorBackupCodeTest extends TestCase
{
    /** 10 位字母数字，形状与 `generateBackupCode()` 一致 */
    private const BACKUP_A = 'Ab3xY9kLm2';
    private const BACKUP_B = 'Zq7wR4tNp8';

    private static bool $booted = false;

    /** 本用例造的行一律用雪花 ID（唯一）—— 固定 ID 会在两个 phpunit 进程共用测试库时互撞 */
    private int $userId = 0;
    private int $twoFaId = 0;

    /**
     * 把连接指向**测试库**（同 `ActivityRewardGuardTest`/`UserAuthPending2faTest` 的做法）。
     * 不这么做，下面的「库名必须含 test」守卫会（正确地）拒掉——本机默认库是 `game-platform`。
     */
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

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
        // 兑现备份码后走到 issueLogin() -> encodeId()，那条路径依赖 hashids 容器绑定，
        // 而绑定是 webman 插件 bootstrap 注册的，PHPUnit 下要手动起（同 ExchangeWalletIntegrationTest）
        HashidsBootstrap::start(null);
    }

    protected function setUp(): void
    {
        try {
            Db::selectOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用：' . $e->getMessage());
        }

        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        parent::tearDown();
    }

    private function seed(bool $enabled = true): void
    {
        $this->userId  = SnowflakeService::generate();
        $this->twoFaId = SnowflakeService::generate();

        $user = new User();
        $user->id = $this->userId;
        $user->username = 'lead_2fa_backup_' . $this->userId;
        $user->password = password_hash('Aa123456', PASSWORD_BCRYPT);
        $user->status = 1;
        $user->save();

        $twoFa = new User2FA();
        // 主键非自增（雪花），`setup()` 里也是显式赋的 —— 不赋会 1364 Field 'id' doesn't have a default value
        $twoFa->id = $this->twoFaId;
        $twoFa->user_id = $this->userId;
        $twoFa->secret = 'JBSWY3DPEHPK3PXP';
        $twoFa->is_enabled = $enabled ? 1 : 0;
        $twoFa->backup_codes = json_encode([self::BACKUP_A, self::BACKUP_B]);
        $twoFa->save();
    }

    private function post(array $body): Request
    {
        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE);
        $request = new Request(
            "POST /api/v1/2fa/verify HTTP/1.1\r\nHost: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($encoded) . "\r\n\r\n" . $encoded
        );
        $request->userId = $this->userId;

        return $request;
    }

    /** @return array<string,mixed> */
    private function body(\support\Response $response): array
    {
        return json_decode((string) $response->rawBody(), true) ?? [];
    }

    private function pendingToken(): string
    {
        return jwt_wrapper()->create(['sub' => $this->userId, 'scope' => 'pending_2fa'], 600);
    }

    /**
     * ① 长度门已撤：10 位码不再被校验规则挡回。
     *
     * 用**错误的** 10 位码：它应当走到「TOTP 不匹配 → 备份码也不匹配」的兜底，
     * 报的是业务话术；若仍被规则挡下，报的会是 Laravel 的长度提示（英文、且点名 6 个字符）。
     */
    #[Test]
    public function tenCharacterCodeIsNotRejectedByLengthRule(): void
    {
        $this->seed();

        $body = $this->body((new TwoFactorController())->verify($this->post([
            'pending_2fa_token' => $this->pendingToken(),
            'code'              => 'NotAReal99',
        ])));

        $this->assertSame(422, (int) $body['code']);
        // 判别点是「谁拒的」：**校验器**拒绝给的是 `validation.*` 键名，走到业务分支才给上面那条话术。
        // ⚠ 原写法断言「报文不含 '6 characters'」是**假钉子**（实测：把规则改回 size:6 它照样绿）——
        // 校验器的英文原句在译文机制下压根不出现在报文里，出现的是未命中的键名。
        $this->assertStringNotContainsString(
            'validation.',
            (string) $body['message'],
            '10 位码被长度规则挡在门外 ⇒ verify() 里的备份码分支仍是死代码'
        );
    }

    /** ② 正确的备份码真能换到登录态，且**用掉即作废**（否则一个码可以无限次重放） */
    #[Test]
    public function validBackupCodeIssuesLoginAndIsConsumed(): void
    {
        $this->seed();

        $body = $this->body((new TwoFactorController())->verify($this->post([
            'pending_2fa_token' => $this->pendingToken(),
            'code'              => self::BACKUP_A,
        ])));

        $this->assertSame(0, (int) $body['code'], '正确的备份码必须能过：' . json_encode($body));

        // User2FA::backup_codes 是 Encryptable 列，读回来经 cast 解密后再解 JSON
        $left = json_decode((string) User2FA::where('user_id', $this->userId)->value('backup_codes'), true) ?: [];
        $this->assertNotContains(self::BACKUP_A, $left, '用过的备份码必须被移除');
        $this->assertContains(self::BACKUP_B, $left, '其余备份码不得被连带清掉');
    }

    /** ③ 反例：未启用的 2FA 不该被这一步放行（别把闸口改成"什么都过"） */
    #[Test]
    public function disabledTwoFactorIsRejected(): void
    {
        $this->seed(enabled: false);

        $body = $this->body((new TwoFactorController())->verify($this->post([
            'pending_2fa_token' => $this->pendingToken(),
            'code'              => self::BACKUP_A,
        ])));

        $this->assertNotSame(0, (int) $body['code'], '未启用的 2FA 不该发登录态');
    }
}
