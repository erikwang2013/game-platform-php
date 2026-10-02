<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\LeaderboardController;
use common\HashidsService;
use common\SnowflakeService;
use common\service\LeaderboardService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * `/api/v1/leaderboard/{hashid}` 的 `ranking[].user_id` 必须与同一数组里的 `id` / `game_id` 同口径
 * ——都是 hashid，不能有一个裸 BIGINT。
 *
 * 原先只有 `user_id` 是 `LeaderboardService:89/110` 的 `selectRaw('user_id, SUM(...)')` 直出，
 * 而本端点在**公开组**（config/route.php，无 UserAuth）⇒ 匿名可拿到全站用户的自增 id，
 * 与全仓「ID 出网一律 hashid」的约定不一致（前端 types.ts 里那句「user_id 是未编码的原始 BIGINT，
 * 所以榜单页不展示它」就是在解释这件事）。
 *
 * ⚠ 修在**控制器出网处**、不在 Service：同一份数组还喂 WS，且 `LeaderboardService:119` 的
 * `setex 3600` 缓存里存的也是裸 id —— 在服务层改要连缓存一起处理。本用例钉的就是出网这一层：
 * 它只看 HTTP 回包，服务层与缓存怎么存不关心。
 *
 * ⚠ 用**独占的 game_id** 造榜单（`$board->game_id > 0` 时 computeRanking 会 `where game_id`）：
 * 真库里其他用户/其他用例的历史兑换记录不会混进来，排行里必然只有本用例这一行。
 * Redis 缓存按榜单 id 建键（雪花 id，随机）故不会串场，tearDown 主动清掉不留 3600s 垃圾。
 */
final class LeaderboardUserIdEncodeTest extends TestCase
{
    private static bool $booted = false;

    private int $userId = 0;
    private int $boardId = 0;
    private int $gameId = 0;

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
        HashidsBootstrap::start(null);
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

        $this->userId  = (int) SnowflakeService::generate();
        $this->boardId = (int) SnowflakeService::generate();
        $this->gameId  = (int) SnowflakeService::generate();

        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (Db::transactionLevel() > 0) {
            Db::rollBack();
        }

        // 缓存键与榜单 id 一一对应，事务回滚不回 Redis ⇒ 显式清（否则留 3600s 垃圾键）
        if ($this->boardId > 0) {
            LeaderboardService::clearCache($this->boardId);
        }

        parent::tearDown();
    }

    #[Test]
    public function rankingUserIdIsEncodedOnTheWayOut(): void
    {
        Db::table('leaderboard')->insert([
            'id' => $this->boardId, 'game_id' => $this->gameId,
            'name' => 'LB IT ' . $this->boardId, 'type' => 'alltime', 'metric' => 'earned',
            'status' => 1, 'sort' => 0,
        ]);
        Db::table('exchange_record')->insert([
            'id' => SnowflakeService::generate(), 'user_id' => $this->userId,
            'game_id' => $this->gameId, 'currency_id' => SnowflakeService::generate(),
            'direction' => 'in', 'platform_amount' => '12.3400', 'game_amount' => '12.3400',
            'rate' => '1.00000000',
        ]);

        $request = new Request("GET /api/v1/leaderboard/x HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $body = json_decode(
            (string) (new LeaderboardController())->ranking($request, HashidsService::encode($this->boardId))->rawBody(),
            true
        ) ?? [];

        $this->assertSame(0, $body['code'] ?? -1, '端点未成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $this->assertCount(1, $body['data']['ranking'] ?? [],
            '本次榜单应恰好命中本用例播的那一行（game_id 独占）：' . json_encode($body['data']['ranking'] ?? []));

        $this->assertSame(HashidsService::encode($this->userId), $body['data']['ranking'][0]['user_id'],
            'ranking[].user_id 是裸 BIGINT 出网（同数组里 id / game_id 都编过）：公开端点匿名即可拿到'
            . '全站用户的自增 id。实际：' . json_encode($body['data']['ranking'][0]));
        $this->assertSame(HashidsService::encode($this->boardId), $body['data']['leaderboard']['id'] ?? null,
            'leaderboard.id 应保持 hashid 口径（回归用）');
    }

    /** 与 14 个真库用例同一口径：先烧掉开发库那次 init 的守卫，再由测试库 Capsule 最后 setAsGlobal() 落笔 */
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
