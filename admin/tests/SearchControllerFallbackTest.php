<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\admin\v1\controller\SearchController;
use common\HashidsService;
use common\model\User;
use common\SnowflakeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * GET /admin/v1/search —— 管理端全局搜索（game / user 两分支）。
 *
 * 2026-10-01 把这里的 `try { Game::search($q) / User::search($q) } catch (\Throwable)` 删了：
 * 那两个方法全仓不存在（无任何模型 `use Searchable`）⇒ 每次都必抛 BadMethodCallException 被吞掉，
 * **catch 体才是唯一在跑的路**。所以本文件钉的是「删掉死分支后行为不变」= 这两条 LIKE 路照跑。
 *
 * ⚠ 同批修掉一处**本批之前就存在**的 500（非删 try 引入，删 try 前后都是 500）：
 *   `?type=user` 原先**不分分支**地对两边都跑 `where('name', …)`，而 `game_user` **没有 `name` 列**
 *   （DDL 只有 username/nickname/email/phone）⇒ SQLSTATE 42S22 ⇒ 500，react 管理端搜索页的
 *   user 页签点了就报错。（旧代码把它放在 catch 体里，但 catch 体抛的异常不被同一个 try 捕获
 *   ⇒ 那条路一直是真 500，从不曾被兜住。）
 *   现改为 user 分支搜 **nickname + username**，两个 LIKE **包进同一层闭包**（否则 AND 优先于 OR，
 *   `(nickname LIKE ?) OR (username LIKE ? AND deleted_at IS NULL)` 会让**已软删用户**漏出来），
 *   且**不含 email/phone**（Encryptable 存的是密文，LIKE 明文必匹配不到，加了只会造假象）。
 *   该分支在管理端是**有意保留**的用户检索（带 AdminAuth + AdminPermission），不是 C 端那种公开端。
 *
 * 只打测试库：库名不含 test 直接 fail（沿用 PlatformUserTransactionsTest 口径）。
 */
final class SearchControllerFallbackTest extends TestCase
{
    private int $gameId = 0;
    private int $userId = 0;
    /** nickname 命中（username 不含 token）—— 证明 user 分支搜的是 nickname 而非 name */
    private int $nicknameUser = 0;
    /** 已软删且 nickname 命中 —— 闭包没包对时它会漏出来 */
    private int $trashedUser = 0;

    /** 本用例专用探针串，避开库里其它数据的命中 */
    private const TOKEN = 'zzadmsfl5e21';

    /**
     * 串行锁名：本文件的探针值里带**固定** TOKEN（断言整篇引用它，动不得），
     * 所以两个并发进程会互相看见对方的行、还会撞 `uk_username`。
     * 拿 MySQL 的命名锁把本文件串行化：后到的那个等前面跑完再进。
     */
    private const LOCK_NAME = 'admin_test_search_fallback';

    /** 等锁上限（秒）：单次跑约 2-5s，60s 足够，等不到就是有进程卡死了 */
    private const LOCK_TIMEOUT = 60;

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

        // 进临界区：拿不到就 fail（**不是** markTestSkipped —— 静默跳过会把真红吞掉）
        $got = (int) (Db::selectOne('SELECT GET_LOCK(?, ?) AS l', [self::LOCK_NAME, self::LOCK_TIMEOUT])->l ?? 0);
        if ($got !== 1) {
            $this->fail(self::LOCK_TIMEOUT . 's 内没拿到串行锁 ' . self::LOCK_NAME . '：有别的进程卡在这个文件里？');
        }

        // 上台前先按 TOKEN 前缀清一次场：上一次跑被 SIGKILL / 超时打断时，探针行会永久留在库里
        // （固定 TOKEN ⇒ 之后每次跑都会被它污染）。这一步才是「残留」那半个缺陷的解药，
        // 并发那半个靠上面那把锁。
        self::purgeProbes();

        $this->gameId       = SnowflakeService::generate();
        $this->userId       = SnowflakeService::generate();
        $this->nicknameUser = SnowflakeService::generate();
        $this->trashedUser  = SnowflakeService::generate();

        Db::table('game')->insert([
            'id'          => $this->gameId,
            'name'        => 'Admin Search ' . self::TOKEN,
            'slug'        => 'admin-search-' . $this->gameId,
            'description' => '',
            'status'      => 1,
        ]);

        // A：**username** 命中（nickname 不含 token）
        Db::table('user')->insert([
            'id'       => $this->userId,
            'username' => self::TOKEN . '_user',
            'password' => password_hash('Aa123456', PASSWORD_BCRYPT),
            'nickname' => 'nick-' . $this->userId,
            'status'   => 1,
        ]);
        // B：**只有 nickname** 命中 —— 证明 user 分支搜的是 nickname（原来搜的是并不存在的 name）
        Db::table('user')->insert([
            'id'       => $this->nicknameUser,
            'username' => 'nicksearch_' . $this->nicknameUser,
            'password' => password_hash('Aa123456', PASSWORD_BCRYPT),
            'nickname' => 'Nick ' . self::TOKEN,
            'status'   => 1,
        ]);
        // C：已软删（deleted_at 非空）且 nickname 命中 —— 两个 LIKE 没包进同一层闭包时它会漏出来
        Db::table('user')->insert([
            'id'         => $this->trashedUser,
            'username'   => 'trashed_' . $this->trashedUser,
            'password'   => password_hash('Aa123456', PASSWORD_BCRYPT),
            'nickname'   => 'Ghost ' . self::TOKEN,
            'status'     => 1,
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->gameId = $this->userId = $this->nicknameUser = $this->trashedUser = 0;

        // 按 TOKEN 前缀清，而不是按本次生成的雪花 id：一行残留就足以毒掉后续每一次跑
        // （读到的 total 会比期望多），而按 id 清只认得住"本次这一跑自己插的行"。
        // 探针也是被 SIGKILL 打断过的那次留下的 —— 那次的 id 谁都记不得了。
        self::purgeProbes();

        Db::select('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
    }

    /**
     * 删掉所有带 TOKEN 的探针行（game 看 name，user 看 username/nickname）。
     *
     * 覆盖度：game `'Admin Search ' . TOKEN`、user A `TOKEN . '_user'`、
     * B `nickname 'Nick ' . TOKEN`、C `nickname 'Ghost ' . TOKEN` —— 四条全在。
     */
    public static function purgeProbes(): void
    {
        $like = '%' . self::TOKEN . '%';

        Db::table('game')->where('name', 'like', $like)->delete();
        Db::table('user')->where(function ($w) use ($like) {
            $w->where('username', 'like', $like)->orWhere('nickname', 'like', $like);
        })->delete();
    }

    /** @return array<string,mixed> 响应信封的 data 段 */
    private function search(array $query): array
    {
        $request = new Request(
            'GET /admin/v1/search?' . http_build_query($query) . " HTTP/1.1\r\nHost: localhost\r\n\r\n"
        );
        $response = (new SearchController())->search($request);
        $body     = json_decode((string) $response->rawBody(), true) ?? [];

        $this->assertSame(0, $body['code'] ?? -1, '端点未成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));

        return $body['data'];
    }

    // ============================================================
    // 〇、探针残留清理（本文件自身的基础设施，不是端点行为）
    // ============================================================

    /**
     * 上一次跑被 SIGKILL / 超时打断留下的探针行，必须能被按 TOKEN 前缀清掉。
     *
     * 为什么单独立一条：按**本次生成的雪花 id** 清是清不掉这种残留的 —— 那一跑的 id
     * 谁都记不得了，而 TOKEN 是固定的 ⇒ 那一行会永久污染之后每一次跑（读到的 total 恒多一条）。
     * 去掉 purgeProbes() 的实现（改成按 id 删）⇒ 本用例红。
     */
    #[Test]
    public function purgeRemovesLeftoverProbeRows(): void
    {
        $leftoverUser = SnowflakeService::generate();
        Db::table('user')->insert([
            'id'       => $leftoverUser,
            'username' => 'leftover_' . $leftoverUser,
            'password' => password_hash('Aa123456', PASSWORD_BCRYPT),
            'nickname' => 'Leftover ' . self::TOKEN,
            'status'   => 1,
        ]);
        $leftoverGame = SnowflakeService::generate();
        Db::table('game')->insert([
            'id'          => $leftoverGame,
            'name'        => 'Leftover ' . self::TOKEN,
            'slug'        => 'leftover-' . $leftoverGame,
            'description' => '',
            'status'      => 1,
        ]);

        self::purgeProbes();

        $this->assertSame(0, Db::table('user')->where('id', $leftoverUser)->count(), '探针残留没被清掉');
        $this->assertSame(0, Db::table('game')->where('id', $leftoverGame)->count(), '探针残留没被清掉');
    }

    // ============================================================
    // 一、game 分支：删掉死 try 后照跑（行为不变）
    // ============================================================

    /** 正控：按名字 LIKE 能搜到，且 id 是能 decode 回原值的 hashid（裸 BIGINT 会让前端对不上）。 */
    #[Test]
    public function gameSearchStillMatchesByName(): void
    {
        $data = $this->search(['q' => self::TOKEN, 'type' => 'game']);

        $this->assertSame(1, $data['total'], 'game 分支没搜到那条游戏：' . json_encode($data, JSON_UNESCAPED_UNICODE));
        $this->assertCount(1, $data['list'], '管理端响应的列表键是 list：' . json_encode($data, JSON_UNESCAPED_UNICODE));

        $item = $data['list'][0];
        $this->assertSame('Admin Search ' . self::TOKEN, $item['name']);
        $this->assertIsString($item['id'], 'id 不是字符串（裸 BIGINT 的典型形状）');
        $this->assertSame($this->gameId, HashidsService::decode($item['id']), 'id 解不回原值');
        $this->assertArrayNotHasKey('api_secret', $item, '带密钥的列不得出现在搜索结果里（模型 $hidden）');
    }

    /** 默认 type=game，且搜不到时是空页不是报错。 */
    #[Test]
    public function defaultsToGameAndUnknownKeywordIsEmpty(): void
    {
        $this->assertSame(1, $this->search(['q' => self::TOKEN])['total'], '省略 type 时默认应走 game');

        $none = $this->search(['q' => self::TOKEN . '_no_such_thing', 'type' => 'game']);
        $this->assertSame(0, $none['total']);
        $this->assertSame([], $none['list']);
        $this->assertSame(1, $none['page']);
        $this->assertSame(20, $none['per_page']);
    }

    /** 空关键词短路：不查库、直接空列表（code 仍为成功）。 */
    #[Test]
    public function blankQueryShortCircuitsToEmptyList(): void
    {
        $data = $this->search(['q' => '   ', 'type' => 'game']);

        $this->assertSame(0, $data['total']);
        $this->assertSame([], $data['list']);
    }

    // ============================================================
    // 二、user 分支：钉住一处**既存**缺陷（本批未改其行为：改前改后都是 500）
    // ============================================================

    /**
     * `?type=user` 必须按 **nickname + username** 搜到人，且**不吐已软删用户**。
     *
     * 前身是 `userSearchHitsMissingNameColumn`（当时钉的是「必抛 SQLSTATE 42S22」的既存 500，
     * docblock 里写明"修完该把断言改成 total≥1"）。**现在就是修完的时刻**，按那条指示翻过来。
     * 三条断言各钉一件事：
     *  - `total === 2`：A 靠 username 命中、B 靠 nickname 命中 ⇒ 少一条就是漏搜了某个字段；
     *  - id 是能 decode 回原值的 hashid（裸 BIGINT 前端拿去查必然对不上）+ password 被 unset；
     *  - **软删用户 C 不出现**：这是**结果契约**（管理端搜索永不吐已软删用户），
     *    **不是**「闭包没包对」的钉子 —— 实测把闭包摊平后本用例**照样全绿**：User 带 SoftDeletes
     *    全局作用域，Eloquent 会自动把已有 wheres 归组，两种写法生成的 SQL 一模一样。
     *    （真正会栽的是没有全局作用域的模型：Game 平铺就是 `status=? and name like ? or description like ?`。）
     *    为了让这条不恒真，用例内另有非空真守卫：C 确实在库里、且 nickname 确实命中。
     */
    #[Test]
    public function userSearchFindsByNicknameAndUsername(): void
    {
        // 非空真守卫：先证明 C 真的在库里、nickname 真的命中（withTrashed 才查得到）。
        // 少了它，"C 没出现"也可能是因为压根没这行 ⇒ 断言恒真。
        $this->assertSame(
            1,
            User::withTrashed()
                ->where('nickname', 'like', '%' . self::TOKEN . '%')
                ->whereNotNull('deleted_at')
                ->count(),
            '软删探针 C 不在库里或 nickname 不命中 ⇒ 下面「C 没出现」就是恒真的假绿'
        );
        // 再证明这条断言**能红**：同样的条件去掉软删作用域后应命中 3 条（A+B+C）。
        // ⇒ 唯一把 C 挡在外面的就是 SoftDeletes 作用域；作用域一旦失效，端点的 total 会变成 3，
        // 下面那条 assertSame(2, …) 必然红。这才把「C 没出现」从恒真式变成可证伪的读数。
        $this->assertSame(
            3,
            User::withTrashed()
                ->where(function ($w) {
                    $w->where('nickname', 'like', '%' . self::TOKEN . '%')
                        ->orWhere('username', 'like', '%' . self::TOKEN . '%');
                })
                ->count(),
            '去掉软删作用域后应命中 3 条（A+B+C）；若这里不是 3，说明 C 没被软删或没命中 ⇒ 守卫失效'
        );

        $data = $this->search(['q' => self::TOKEN, 'type' => 'user']);
        $dump = json_encode($data, JSON_UNESCAPED_UNICODE);

        $this->assertSame(2, $data['total'], '应当只搜到 A(username) + B(nickname) 两条：' . $dump);

        $ids = array_map(static fn ($row) => HashidsService::decode($row['id']), $data['list']);
        sort($ids);
        $expected = [$this->userId, $this->nicknameUser];
        sort($expected);
        $this->assertSame($expected, $ids, '搜到的不是那两条（软删用户 C 不得出现）：' . $dump);

        $this->assertIsString($data['list'][0]['id'], 'id 不是字符串（裸 BIGINT 的典型形状）');
        $this->assertArrayNotHasKey('password', $data['list'][0], 'password 必须被 unset');
    }
}
