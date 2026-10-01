<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\SearchController;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * `/api/v1/search` 的 LIKE 兜底里有**两个真缺陷**（2026-10-01 由 C 端 react 那路读源码报出、我复核并修）。
 *
 * ① **orWhere 绕过 status 过滤**。平铺写法生成
 *    `status=1 AND name LIKE ? OR description LIKE ?`，SQL 里 AND 优先于 OR ⇒
 *    实际是 `(status=1 AND name LIKE ?) OR (description LIKE ?)` —— 描述命中的那一支
 *    **绕过 status=1**，于是**已下架**的游戏（`game_game.status` 注释：0=下架）只要简介命中就会
 *    出现在 C 端搜索结果里。
 * ② **`game_user` 没有 `name` 列**（只有 username/nickname）。非 game 分支写 `where('name', …)`
 *    必然 SQLSTATE 42S22 Unknown column ⇒ 500；而 `/api/v1/search` 挂在**公开组**，
 *    `?type=user` 不带任何令牌就能触发。
 * ③ **该分支一旦打通就是未鉴权的用户批量导出**：结果行只 `unset($data['password'])`，
 *    `email`/`phone` 随整行返回（Encryptable 读回是明文）⇒ `?type=user&q=a` 可拉联系方式。
 *    **②与③是同一处代码的两种状态**：修好②就打开③（这正是我修完必须立刻补③的原因）。
 *    终态是公开端只认 game，user 检索只留在管理端自己的 SearchController。
 *
 * 为什么这条兜底就是**唯一在跑**的路径：try 分支调 `Game::search()` / `User::search()`，
 * 而这两个方法全仓不存在（`Builder::macro` 唯一一处在 vendor 的测试文件里）⇒ 必抛
 * BadMethodCallException 被 `catch (\Throwable)` 吞掉。别把它当"ES 不可用时的降级"。
 *
 * 只打测试库：连接库名必须含 test，否则硬失败，绝不静默写开发库。
 */
final class SearchFallbackTest extends TestCase
{
    /** 三个互不重叠的探针串，避免互相命中把断言变成恒真 */
    private const NAME_TOKEN = 'zzqhname7f3a';
    private const DESC_TOKEN = 'zzqhdesc9c1e';
    private const USER_TOKEN = 'zzqhuser9182';

    private static bool $booted = false;

    private int $listedGameId = 0;
    private int $delistedGameId = 0;
    private int $probeGameId = 0;
    private int $userId = 0;

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
        // 命中的行要过 encodeId()，那条路径依赖 hashids 容器绑定（webman 插件 bootstrap 注册的，
        // PHPUnit 下要手动起；同 ExchangeWalletIntegrationTest）
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

        // 上架游戏：名字命中探针（正控用）
        $this->listedGameId = SnowflakeService::generate();
        Db::table('game')->insert([
            'id'          => $this->listedGameId,
            'name'        => 'Listed ' . self::NAME_TOKEN,
            'slug'        => 'listed-' . $this->listedGameId,
            'description' => '',
            'status'      => 1,
        ]);

        // 下架游戏：**只有简介**命中探针 —— 缺陷存在时它会漏进搜索结果
        $this->delistedGameId = SnowflakeService::generate();
        Db::table('game')->insert([
            'id'          => $this->delistedGameId,
            'name'        => 'Delisted ' . $this->delistedGameId,
            'slug'        => 'delisted-' . $this->delistedGameId,
            'description' => 'Contains ' . self::DESC_TOKEN,
            'status'      => 0,
        ]);

        $this->userId = SnowflakeService::generate();
        Db::table('user')->insert([
            'id'       => $this->userId,
            'username' => self::USER_TOKEN,
            'password' => password_hash('Aa123456', PASSWORD_BCRYPT),
            'nickname' => 'nick-' . $this->userId,
            // 明文的邮箱/手机：公开搜索一旦真去查 user，它们就会随整行吐出去。
            // ⚠ 但**不对它们做断言** —— 这两列在模型上是 Encryptable，测试里塞明文、读回可能被
            // 加解密层弄花，`assertStringNotContainsString` 会因此**恒真**（假钉子）。
            // 判别力由**明文列 `username`** 承担（见 userTypeCannotHarvestAccounts）。
            'email'    => 'zzqh-pii@example.test',
            'phone'    => '13900001234',
        ]);

        // 正控用的游戏：名字里植入了与用户**同一个** token。
        // 作用：证明该关键词本来就有结果 ⇒「响应里没有那个用户」不是因为端点返回了空。
        $this->probeGameId = SnowflakeService::generate();
        Db::table('game')->insert([
            'id'          => $this->probeGameId,
            'name'        => 'Probe ' . self::USER_TOKEN,
            'slug'        => 'probe-' . $this->probeGameId,
            'description' => '',
            'status'      => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        parent::tearDown();
    }

    private function get(array $query): Request
    {
        return new Request(
            'GET /api/v1/search?' . http_build_query($query) . " HTTP/1.1\r\nHost: localhost\r\n\r\n"
        );
    }

    /** @return array<string,mixed> */
    private function body(array $query): array
    {
        $response = (new SearchController())->search($this->get($query));
        $decoded = json_decode((string) $response->rawBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * ① 下架游戏不得因**简介**命中而漏进结果。
     *
     * 两条断言缺一不可：只断言「搜不到下架游戏」的话，把端点写成恒返回空也会绿 ——
     * 所以同时断言**上架游戏搜得到**（正控）。
     */
    #[Test]
    public function delistedGameDoesNotLeakThroughDescriptionMatch(): void
    {
        $leak = $this->body(['q' => self::DESC_TOKEN, 'type' => 'game']);
        $this->assertSame(0, (int) ($leak['data']['total'] ?? -1), '下架游戏经简介命中漏进了搜索结果：' . json_encode($leak));

        $ok = $this->body(['q' => self::NAME_TOKEN, 'type' => 'game']);
        $this->assertSame(1, (int) ($ok['data']['total'] ?? -1), '正控失败：上架游戏按名字都搜不到 ⇒ 端点本身坏了：' . json_encode($ok));
    }

    /**
     * ② **`?type=user` 不得成为未鉴权的用户批量导出**。
     *
     * 这个端点在**公开组**（`config/route.php:37-73`，无 `UserAuth`），而结果行只 `unset($data['password'])`
     * ⇒ `email`/`phone` 会随整行原样返回。历史三态值得记牢：
     *   改前 —— `where('name', …)` 查不存在的列 ⇒ SQLSTATE 42S22 ⇒ **500**（拿不到数据，但也不泄露）；
     *   我修完 500 —— 分支打通 ⇒ **不带令牌 `?type=user&q=a` 即可按关键字拉取联系方式**（我引入的）；
     *   现在 —— 公开端**只认 game**（`$type` 在入口被强制），user 检索只留在管理端自己的 SearchController。
     *
     * 判别力落在**明文列 `username`** 上，不落在 `email`/`phone` 上（那两列是 Encryptable，
     * 断言它们可能恒真）。正控 = 同一关键词能搜到那条游戏。
     */
    #[Test]
    public function userTypeCannotHarvestAccounts(): void
    {
        $body = $this->body(['q' => self::USER_TOKEN, 'type' => 'user']);
        $raw = json_encode($body, JSON_UNESCAPED_UNICODE);

        $usernames = array_column((array) ($body['data']['list'] ?? []), 'username');
        $this->assertNotContains(
            self::USER_TOKEN,
            $usernames,
            '公开搜索按 type=user 把用户行吐出来了（未鉴权可批量拉取）：' . $raw
        );

        // 正控：该 token 在 game 里也命中一条（探针游戏的名字植入了同一 token）
        // ⇒ 若这里是 0，说明端点整个坏了，上面那条"没有该用户"就是假绿。
        $this->assertSame(1, (int) ($body['data']['total'] ?? -1), '正控失败：端点没按 game 检索：' . $raw);
    }
}
