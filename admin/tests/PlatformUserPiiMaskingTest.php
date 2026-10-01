<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\PlatformUserController;
use app\admin\v1\controller\SearchController;
use common\model\Game;
use common\model\User;
use common\HashidsService;
use common\SnowflakeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use Throwable;

/**
 * 平台用户（C 端 `game_user`）联系方式与登录 IP 的下发脱敏。
 *
 * 缺陷形状：`game_user` 的 phone/email 是 **Encryptable** 列 —— tail 里存的是密文，
 * 但 Eloquent 取值那一刻**已经解过密**，所以 `toArray()` 拿到的就是明文。
 * `User::$hidden` 只有 `['password']`（packages/platform-common/src/model/User.php:37），
 * 挡不住 phone/email/last_login_ip ⇒ 列表、详情、全局搜索三处都在原样外发
 * 「手机号 + 邮箱 + 常用登录网段」这一整组可用于撞库/画像的资料。
 *
 * 钉子分四段：
 *  ① 列表下发的是掩码（明文串在响应里一次都不出现）；
 *  ② 详情同理（`UserController::show` 有"详情不脱敏"的先例，这里**特意反着来**，
 *    因为本树 update() 只收 status/nickname，没有任何「拿脱敏串回写」的路径）；
 *  ③ 全局搜索 `type=user` 同理 —— 它是**唯一一处不在 PlatformUserController 里**的泄漏点；
 *  ④ 正控：同一行里的非 PII 字段（username/nickname/country）与 `type=game` 分支**原样**下发，
 *    证明上面三条不是「整行被吞掉」「分支被写死成空」这类假绿。
 *  另有一条守住 empty 语义：没填过联系方式的用户，phone/email 仍是空串，不会被填成 `***`。
 *
 * 掩码形状钉的是 `EncryptionService::maskPhone/maskEmail` 的**字面输出**
 * （packages/platform-common/src/EncryptionService.php:55-70），不是自己写的正则：
 * 换成 `^(\d{3})\d+(\d{4})$` 那种写法时，带国家码的号会**原样返回**（等于没脱敏），
 * 本条用例会立刻变红。
 *
 * 变异读数：删掉 PlatformUserController::list 的 maskUserContact ⇒ ①② 红；
 * 删掉 SearchController 的 ⇒ ③ 红；把 `$type === 'user'` 改成 `false` ⇒ ③ 红而 ④ 绿。
 */
final class PlatformUserPiiMaskingTest extends TestCase
{
    private const PHONE = '13812345678';
    private const EMAIL = 'alice@example.com';
    private const IP    = '203.0.113.45';

    /** EncryptionService::maskPhone 的字面输出 */
    private const MASKED_PHONE = '138****5678';
    /** EncryptionService::maskEmail 的字面输出 */
    private const MASKED_EMAIL = 'a***@example.com';
    /** maskUserContact 的 IP 口径：抹掉最后一段，保留网段 */
    private const MASKED_IP = '203.0.113.*';

    private ?int $userId = null;
    /** 没填过联系方式的用户：守「空值保持空值」 */
    private ?int $bareUserId = null;
    /** 脏数据用户：掩码函数对它原样返回，必须走兜底整串打掉 */
    private ?int $dirtyUserId = null;
    private ?int $gameId = null;
    private string $dirtyUsername = '';

    private string $rand = '';
    private string $prefix = '';
    private string $username = '';
    private string $nickname = '';
    private string $bareUsername = '';
    private string $gameName = '';
    private string $gameSlug = '';

    protected function setUp(): void
    {
        try {
            $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        } catch (Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());

            return;
        }
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        $this->rand    = bin2hex(random_bytes(5));
        $this->prefix  = 'piiprobe' . $this->rand;
        $this->nickname = '尼可_' . $this->rand;
        $this->username     = $this->prefix . '_a';
        $this->bareUsername = $this->prefix . '_b';
        $this->gameName     = 'PiiProbe ' . $this->rand;
        $this->gameSlug     = 'piiprobe-' . $this->rand;

        $user = new User();
        $user->id            = SnowflakeService::generate();
        $user->username      = $this->username;
        $user->password      = password_hash('Probe1234', PASSWORD_BCRYPT);
        $user->nickname      = $this->nickname;
        $user->country       = 'CN';
        $user->email         = self::EMAIL;   // Encryptable：库里落密文，模型取回明文
        $user->phone         = self::PHONE;
        $user->last_login_ip = self::IP;
        $user->status        = 1;
        $user->save();
        $this->userId = (int) $user->id;

        $dirty = new User();
        $dirty->id            = SnowflakeService::generate();
        $dirty->username      = $this->dirtyUsername = $this->prefix . '_c';
        $dirty->password      = password_hash('Probe1234', PASSWORD_BCRYPT);
        $dirty->nickname      = '脏_' . $this->rand;
        $dirty->email         = 'not-an-email';   // 不含 @ ⇒ maskEmail 原样返回
        $dirty->phone         = '12345';          // 短于 7 位 ⇒ maskPhone 原样返回
        $dirty->last_login_ip = '2001:db8::1';    // IPv6 ⇒ 无从保留网段
        $dirty->status        = 1;
        $dirty->save();
        $this->dirtyUserId = (int) $dirty->id;

        $bare = new User();
        $bare->id       = SnowflakeService::generate();
        $bare->username = $this->bareUsername;
        $bare->password = password_hash('Probe1234', PASSWORD_BCRYPT);
        $bare->nickname = '裸_' . $this->rand;
        $bare->status   = 1;
        $bare->save();
        $this->bareUserId = (int) $bare->id;

        $game = new Game();
        $game->id          = SnowflakeService::generate();
        $game->name        = $this->gameName;
        $game->slug        = $this->gameSlug;
        $game->description = '搜索正控用，不该被动过';
        $game->status      = 1;
        $game->save();
        $this->gameId = (int) $game->id;
    }

    protected function tearDown(): void
    {
        foreach ([$this->userId, $this->bareUserId, $this->dirtyUserId] as $id) {
            if ($id !== null) {
                try {
                    User::withTrashed()->where('id', $id)->forceDelete();
                } catch (Throwable) {
                }
            }
        }
        if ($this->gameId !== null) {
            try {
                Game::where('id', $this->gameId)->forceDelete();
            } catch (Throwable) {
            }
        }
        $this->userId = $this->bareUserId = $this->dirtyUserId = $this->gameId = null;
    }

    // ============================================================
    // ① 列表
    // ============================================================

    #[Test]
    public function listMasksContactFieldsAndLoginIp(): void
    {
        $row = $this->rowOf($this->json((new PlatformUserController())->list($this->get([
            'keyword' => $this->prefix,
        ])))['data']['list'] ?? [], $this->username);

        $this->assertSame(self::MASKED_PHONE, (string) $row['phone'], '列表必须下发掩码手机号');
        $this->assertSame(self::MASKED_EMAIL, (string) $row['email'], '列表必须下发掩码邮箱');
        $this->assertSame(self::MASKED_IP, (string) $row['last_login_ip'], '登录 IP 必须保留网段、抹掉末段');
        $this->assertNoPlaintext($row);
    }

    /** 空值语义：没填过就是空串，不能被掩码函数"造"出一个值来 */
    #[Test]
    public function emptyContactFieldsStayEmpty(): void
    {
        $list = $this->json((new PlatformUserController())->list($this->get([
            'keyword' => $this->prefix,
        ])))['data']['list'] ?? [];
        $row  = $this->rowOf($list, $this->bareUsername);

        $this->assertSame('', (string) $row['phone']);
        $this->assertSame('', (string) $row['email']);
        $this->assertSame('', (string) $row['last_login_ip']);
    }

    /**
     * 脏数据兜底：`maskPhone` 对短于 7 位的号、`maskEmail` 对不含 `@` 的串都是**原样返回**，
     * 而"原样返回"在这里等于把 PII 整串漏出去。BaseController 因此按「掩完没变化 ⇒ 整串打掉」兜底。
     * 少了本文，那段兜底改不改都没人知道。
     */
    #[Test]
    public function malformedContactValuesAreFullyMasked(): void
    {
        $row = $this->rowOf($this->json((new PlatformUserController())->list($this->get([
            'keyword' => $this->prefix,
        ])))['data']['list'] ?? [], $this->dirtyUsername);

        $this->assertSame('***', (string) $row['phone'], '短号脱不开身，必须整串打掉');
        $this->assertSame('***', (string) $row['email'], '不含 @ 的邮箱必须整串打掉');
        $this->assertSame('***', (string) $row['last_login_ip'], 'IPv6 没有可保留的网段，整串打掉');
        $this->assertStringNotContainsString('12345', (string) json_encode($row));
        $this->assertStringNotContainsString('not-an-email', (string) json_encode($row));
        $this->assertStringNotContainsString('2001:db8', (string) json_encode($row));
    }

    // ============================================================
    // ② 详情
    // ============================================================

    #[Test]
    public function detailMasksContactFieldsAndLoginIp(): void
    {
        $data = $this->json((new PlatformUserController())->detail(
            $this->get([]),
            $this->hash((int) $this->userId)
        ))['data'];

        $this->assertSame(self::MASKED_PHONE, (string) $data['phone'], '详情必须下发掩码手机号');
        $this->assertSame(self::MASKED_EMAIL, (string) $data['email'], '详情必须下发掩码邮箱');
        $this->assertSame(self::MASKED_IP, (string) $data['last_login_ip']);
        $this->assertNoPlaintext($data);
    }

    // ============================================================
    // ③ 全局搜索（唯一的控制器外泄漏点）
    // ============================================================

    #[Test]
    public function searchUserTypeMasksContactFieldsAndLoginIp(): void
    {
        $row = $this->rowOf($this->json((new SearchController())->search($this->get([
            'q'    => $this->username,
            'type' => 'user',
        ])))['data']['list'] ?? [], $this->username);

        $this->assertSame(self::MASKED_PHONE, (string) $row['phone'], 'type=user 搜索必须下发掩码手机号');
        $this->assertSame(self::MASKED_EMAIL, (string) $row['email'], 'type=user 搜索必须下发掩码邮箱');
        $this->assertSame(self::MASKED_IP, (string) $row['last_login_ip']);
        $this->assertNoPlaintext($row);
    }

    // ============================================================
    // ④ 正控：非 PII 字段与 game 分支原样
    // ============================================================

    /** 脱敏是**字段级**的，不是把整行吞掉/清空 */
    #[Test]
    public function nonPiiFieldsAreLeftVerbatim(): void
    {
        $row = $this->rowOf($this->json((new SearchController())->search($this->get([
            'q'    => $this->username,
            'type' => 'user',
        ])))['data']['list'] ?? [], $this->username);

        $this->assertSame($this->username, (string) $row['username'], '搜索页要靠 username 认人，不能被一起打掉');
        $this->assertSame($this->nickname, (string) $row['nickname']);
        $this->assertSame('CN', (string) $row['country']);
        $this->assertArrayNotHasKey('password', $row, 'password 仍必须被 unset');
    }

    /** `type=game` 分支没有 phone/email 列，行为必须与改动前一致 */
    #[Test]
    public function searchGameTypeIsUnaffected(): void
    {
        $row = $this->rowOf($this->json((new SearchController())->search($this->get([
            'q'    => $this->gameName,
            'type' => 'game',
        ])))['data']['list'] ?? [], $this->gameName, 'name');

        $this->assertSame($this->gameName, (string) $row['name']);
        $this->assertSame('搜索正控用，不该被动过', (string) $row['description']);
    }

    /* ---------------------------------------------------------------- 工具 */

    /**
     * @param array<string,mixed> $data
     */
    private function assertNoPlaintext(array $data): void
    {
        $flat = json_encode($data, JSON_UNESCAPED_UNICODE) ?: '';
        $this->assertStringNotContainsString(self::PHONE, $flat, '响应里有明文手机号');
        $this->assertStringNotContainsString(self::EMAIL, $flat, '响应里有明文邮箱');
        $this->assertStringNotContainsString(self::IP, $flat, '响应里有完整登录 IP');
    }

    /**
     * @param array<int,array<string,mixed>> $list
     * @return array<string,mixed>
     */
    private function rowOf(array $list, string $needle, string $field = 'username'): array
    {
        foreach ($list as $row) {
            if (($row[$field] ?? null) === $needle) {
                return $row;
            }
        }

        $this->fail("响应里找不到 {$field}={$needle} 那行（过滤或分页行为变了？共 " . count($list) . ' 行）');
    }

    private function hash(int $id): string
    {
        return HashidsService::encode($id);
    }

    /** @param array<string,mixed> $query */
    private function get(array $query): Request
    {
        return new Request('GET /admin/v1/x?' . http_build_query($query) . " HTTP/1.1\r\nHost: localhost\r\n\r\n");
    }

    /** @return array<string,mixed> */
    private function json(\support\Response $response): array
    {
        return json_decode((string) $response->rawBody(), true) ?? [];
    }
}
