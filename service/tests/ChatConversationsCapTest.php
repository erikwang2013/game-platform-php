<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\ChatController;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * `/api/v1/chat/conversations` 的候选集硬上限（ChatController::CONVERSATION_LIMIT = 200）。
 *
 * 原先两次 `groupBy(...)->pluck()` 把**全部** peer 取回内存再 `whereIn` —— 会话数随使用单调
 * 增长、没有上限，一个重度用户的一次请求就会把整张 `game_message` 的 peer 维度拉进来。
 * 现在每个方向各 `order by MAX(id) desc limit 200`（两侧各 200 是最终 200 条的**超集**），
 * 合并双向最大值后再 `array_slice` 切一次。
 *
 * 量的是**行为**而不是源码：**两个方向各播 205 条**（id 递增、peer 互不重叠），断
 *   ① 取 peer 的那两条 groupBy 查询带 `limit 200`；
 *   ② 下游 `where id in (...)` 的实参只有 200 个，且**恰是最大的 200 个**
 *      （即收到的那一侧：id ∈ (base+205, base+410]）。
 *
 * ⚠ 为什么必须**双向都播**（实测教训）：只播单向时 SQL 的 `limit 200` 已经把候选切到 200 条，
 * PHP 那次 `array_slice` 成了空操作 —— 把 `arsort` 改成 `asort` 的变异**照样全绿**。
 * 双向各 205 条时两侧各 200、合并 400，PHP 切片才承重：反向切会留下 sent 侧（id ≤ base+205）⇒ ② 红。
 *
 * ⚠ peer 不需要在 game_user 里存在：本用例只观察**取数**口径（响应会跳过查不到用户的会话），
 * 故不播种用户行 —— 也因此断言落在 SQL/绑定上而不是响应列表长度上。
 */
final class ChatConversationsCapTest extends TestCase
{
    private const LIMIT = 200;
    private const PEERS = 205;

    private static bool $booted = false;

    private int $userId = 0;
    private int $baseId = 0;

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

        $this->userId = (int) SnowflakeService::generate();
        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (Db::transactionLevel() > 0) {
            Db::rollBack();
        }

        parent::tearDown();
    }

    #[Test]
    public function candidatePeersAreCappedAtTheMostRecentTwoHundred(): void
    {
        // 两个方向各 205 条、peer 互不重叠、id 全局递增：
        // sent 侧 = base+1..base+205（对方 id 700000xxx），received 侧 = base+206..base+410（800000xxx）
        $this->baseId = (int) SnowflakeService::generate();
        for ($i = 1; $i <= self::PEERS; $i++) {
            Db::table('message')->insert([
                'id'           => $this->baseId + $i,
                'from_user_id' => $this->userId,
                'to_user_id'   => 700000000 + $i,
                'content'      => 'cap-test',
            ]);
        }
        for ($i = 1; $i <= self::PEERS; $i++) {
            Db::table('message')->insert([
                'id'           => $this->baseId + self::PEERS + $i,
                'from_user_id' => 800000000 + $i,
                'to_user_id'   => $this->userId,
                'content'      => 'cap-test',
            ]);
        }

        $request = new Request("GET /api/v1/chat/conversations HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->userId = $this->userId;

        $connection = Db::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $body = json_decode((string) (new ChatController())->conversations($request)->rawBody(), true) ?? [];
            $log  = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
        }

        $this->assertSame(0, $body['code'] ?? -1,
            'conversations() 未成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));

        // ① 取 peer 的两条 groupBy 查询都必须带上限
        // ⚠ 按 `MAX(id)` 认这两条：未读数那条也带 `group by from_user_id`（实测三条 group by）
        $groupBy = array_values(array_filter(
            array_column($log, 'query'),
            static fn (string $sql): bool => stripos($sql, 'group by') !== false
                && stripos($sql, 'MAX(id)') !== false
        ));
        $this->assertCount(2, $groupBy, '没抓到两条 groupBy 取 peer 的查询：' . json_encode(array_column($log, 'query')));
        foreach ($groupBy as $sql) {
            $this->assertMatchesRegularExpression('/\blimit ' . self::LIMIT . '\b/i', $sql,
                "取 peer 的查询没有硬上限（会话数随使用单调增长、无界）。实际 SQL：{$sql}");
        }

        // ② 下游 whereIn 的实参＝被切出来的 last_msg_id 集合，必须恰是最大的 200 个
        $msgs = array_values(array_filter(
            $log,
            static fn (array $q): bool => str_contains($q['query'], 'from `game_message`')
                && stripos($q['query'], 'in (') !== false
        ));
        $this->assertNotSame([], $msgs, '没抓到下游 `where id in (...)` 的取消息查询');
        $ids = array_map('intval', $msgs[0]['bindings']);

        // received 侧是最新的 200 条（base+206..base+410）；合并后按最大 id 降序切，留下的应全是它
        $this->assertCount(self::LIMIT, $ids,
            '取消息的 id 集合大小应等于上限 ' . self::LIMIT . '（合并双向后必须再切一次）');
        // 下界＝base+211，不是 base+206：received 侧自己也有 205 条，它那条 `limit 200` 先丢掉了
        // 该侧最旧的 5 条（base+206..base+210）。两侧各 200 合并成 400 ⇒ 剩下的 sent 侧 200 条
        // 必须由 PHP 那次切片丢掉，这里量的就是它。
        $this->assertSame($this->baseId + self::PEERS + 6, min($ids),
            '切下来的应是**最近**的 200 个会话（按 last_msg_id 降序）：最小值应为 received 侧第一条的 id，'
            . "实际最小值 " . min($ids) . "、baseId={$this->baseId}（反向切＝留下 sent 侧那些更旧的会话）");
        $this->assertSame($this->baseId + self::PEERS * 2, max($ids),
            '最大值应是最新那条私信（received 侧末尾）的 id');
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
