<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\service\ActivityService;
use app\service\WalletScope;
use app\service\WalletService;
use common\SnowflakeService;
use common\model\Activity;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * 活动发奖的三条闸，钉在 ActivityService::grantRewards / creditWallet 上：
 *
 * 1) 锁序不变式「平台钱包先于游戏钱包」（与 ExchangeController 同一条规范序）。
 *    观测量用钱包流水的**插入顺序**——mutate 是「锁行 → 改余额 → 写流水」同一事务，
 *    落账顺序就是取锁顺序。红点：旧实现按 config 原序交错取锁 ⇒ 与兑换方向互逆 ⇒ 1213。
 *    同一用例顺带钉「stable partition 不改 reward_ref」：重排后 reward_ref 仍等于 config 位置，
 *    uk(participation_id, reward_type, reward_ref) 的幂等语义与历史行完全一致。
 *
 * 2) 单条金额上界只长在出钱那一侧（creditWallet）：超限不发钱、不抛、participation 照样进终态。
 *
 * 3) 坏配置不卡死：不支持的 reward_type 只跳过它自己，其余奖励照发，participation 进终态。
 *    旧实现抛异常 ⇒ 整个 checkin/progress 事务回滚。实测（/tmp 探针在还原成旧行为的状态下跑两轮）：
 *    **participation 行整条被回滚，两次查询都是 NULL**，reward_log 0 行 ⇒ 不是"停在 completed"，
 *    而是每次重试都从零开始再抛一次 —— 该活动对该用户永久不可达。
 *
 * 只打测试库：库名不含 test 直接 fail，绝不静默写开发库（沿用 ExchangeWalletIntegrationTest 的口径）。
 */
class ActivityRewardGuardTest extends TestCase
{
    private static bool $booted = false;

    private int $userId = 0;
    private int $gameId = 0;
    private int $currencyId = 0;

    /** @var int[] 本用例建的 activity 行，tearDown 逐个删 */
    private array $activityIds = [];

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

        // 服务端真实库名，而不是配置里的名字：写操作前的最后一道闸
        $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        $this->userId = SnowflakeService::generate();
        $this->gameId = SnowflakeService::generate();
        $this->currencyId = SnowflakeService::generate();
    }

    protected function tearDown(): void
    {
        if ($this->userId === 0) {
            return;
        }

        // 只删本用例造的行（user_id/snowflake 唯一），不 TRUNCATE、不碰他人数据
        foreach (['activity_reward_log', 'activity_participation', 'transaction', 'user_wallet', 'user_game_wallet'] as $table) {
            Db::table($table)->where('user_id', $this->userId)->delete();
        }
        foreach ($this->activityIds as $id) {
            Db::table('activity')->where('id', $id)->delete();
        }
        $this->activityIds = [];
    }

    /**
     * 锁序：config 里游戏币在前、平台币在后，实际必须平台钱包先落账（先取锁）。
     */
    #[Test]
    public function platformWalletIsCreditedBeforeGameWallet(): void
    {
        $activityId = $this->makeActivity([
            'game_id'     => $this->gameId,
            'currency_id' => $this->currencyId,
            'rewards'     => [
                ['day' => 1, 'reward' => ['type' => 'game_coin', 'amount' => '10']],
                ['day' => 1, 'reward' => ['type' => 'platform_coin', 'amount' => '5']],
            ],
        ]);

        $result = ActivityService::checkin($this->userId, $activityId);
        $this->assertSame('rewarded', $result['status'], '签到应达标并进入终态');

        $scopes = Db::table('transaction')->where('user_id', $this->userId)
            ->where('type', 'activity_reward')->orderBy('id')->pluck('scope')->all();
        $this->assertSame(
            ['platform', 'game'],
            $scopes,
            '取锁/落账顺序必须是平台钱包先于游戏钱包，实际：' . json_encode($scopes)
        );

        // 正控：顺序对了但钱没发也会让上面那条绿 —— 这里钉住两边都真到账
        $this->assertSame(0, bccomp($this->balance(WalletScope::platform()), '5', 8), '平台币应到账 5');
        $this->assertSame(0, bccomp($this->balance(WalletScope::game($this->gameId, $this->currencyId)), '10', 8), '游戏币应到账 10');
    }

    /**
     * 重排不改编号：reward_ref 仍等于 config 里的位置（1 起，含被跳过的条目不重编号）。
     * 这是「重排安全」论证的断言化——uk 幂等靠它，别的都靠它。
     */
    #[Test]
    public function reorderKeepsRewardRefEqualToConfigPosition(): void
    {
        $activityId = $this->makeActivity([
            'game_id'     => $this->gameId,
            'currency_id' => $this->currencyId,
            'rewards'     => [
                ['day' => 1, 'reward' => ['type' => 'game_coin', 'amount' => '3']],      // ref 1
                ['day' => 1, 'reward' => ['type' => 'platform_coin', 'amount' => '4']],  // ref 2
                ['day' => 1, 'reward' => ['type' => 'platform_coin', 'amount' => '6']],  // ref 3
            ],
        ]);

        ActivityService::checkin($this->userId, $activityId);

        $refs = Db::table('activity_reward_log')->where('user_id', $this->userId)
            ->orderBy('reward_ref')->get(['reward_type', 'reward_ref'])->map(
                static fn ($r) => $r->reward_type . ':' . $r->reward_ref
            )->all();
        $this->assertSame(['game_coin:1', 'platform_coin:2', 'platform_coin:3'], $refs);
    }

    /**
     * 单条奖励超上界：不发钱、不抛、participation 仍进终态，失败原因落库可查。
     */
    #[Test]
    public function overCapRewardIsSkippedWithoutStallingParticipation(): void
    {
        $activityId = $this->makeActivity([
            'rewards' => [['day' => 1, 'reward' => ['type' => 'platform_coin', 'amount' => '10001']]],
        ]);

        $result = ActivityService::checkin($this->userId, $activityId);
        $this->assertSame('rewarded', $result['status'], '超上限也必须是终态（否则该活动对该用户永久卡死）');
        $this->assertSame([], $result['reward'], '超上限的那条不应出现在已发列表里');

        $log = $this->rewardLog($activityId);
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('MAX_REWARD_PER_ENTRY', (string) $log->fail_reason);

        $this->assertSame('rewarded', $this->participationStatus($activityId));
        $this->assertSame(0, Db::table('transaction')->where('user_id', $this->userId)->count(), '超上限不得出钱');
    }

    /**
     * 坏配置不卡死：未知 reward_type 夹在两条正常奖励之间，只跳过它自己。
     * 红点（实测旧行为）：grantRewards 抛 RuntimeException ⇒ 整个事务回滚 ⇒ 三条都没发、
     * participation 行整条不存在（streak 归零，重试从零开始再抛）。
     */
    #[Test]
    public function unknownRewardTypeIsSkippedWithoutBlockingSiblingRewards(): void
    {
        $activityId = $this->makeActivity([
            'rewards' => [
                ['day' => 1, 'reward' => ['type' => 'platform_coin', 'amount' => '7']],
                ['day' => 1, 'reward' => ['type' => 'coupon', 'amount' => '5']],
                ['day' => 1, 'reward' => ['type' => 'platform_coin', 'amount' => '9']],
            ],
        ]);

        $result = ActivityService::checkin($this->userId, $activityId);

        $this->assertSame('rewarded', $result['status']);
        $this->assertSame(
            [['type' => 'platform_coin', 'amount' => '7'], ['type' => 'platform_coin', 'amount' => '9']],
            $result['reward'],
            '未知类型只应跳过它自己，两侧奖励照发'
        );
        $this->assertSame('rewarded', $this->participationStatus($activityId), '红点：旧实现回滚后停在 completed');
        $this->assertSame(0, bccomp($this->balance(WalletScope::platform()), '16', 8), '两条正常奖励应合计到账 16');

        $rows = Db::table('activity_reward_log')->where('user_id', $this->userId)
            ->orderBy('reward_ref')->get(['reward_ref', 'status'])->map(
                static fn ($r) => $r->reward_ref . ':' . $r->status
            )->all();
        $this->assertSame(['1:succeeded', '2:failed', '3:succeeded'], $rows, '三条 log 都要落库，编号仍是 config 位置');

        $failed = Db::table('activity_reward_log')->where('user_id', $this->userId)
            ->where('status', 'failed')->first();
        $this->assertStringContainsString('unsupported reward_type: coupon', (string) $failed->fail_reason);

        // 运维据此补发：error 日志必须带得动定位信息
        $this->assertLogged(
            'Activity reward skipped',
            [(string) $failed->participation_id, 'coupon', (string) $activityId]
        );
    }

    /**
     * uk_idempotent 撞键 = 这条奖励已发过 ⇒ 跳过（**收窄后仍然如此**）。
     *
     * 手工造出「进度行已在、且该档奖励流水已存在」的形状（重复投递/重放的落库形态），
     * 触发 reward_log 的 uk 冲突。断言：不抛、不重复出钱、participation 照样进终态。
     * 红点：若把收窄写成「键名不匹配也吞」，本条仍绿而主键撞号会被静默漏发 —— 故成对判据的另一半
     * 在 DuplicateKeyNarrowingTest（构造 1062 断言主键撞号**不**被吞）。
     */
    #[Test]
    public function duplicateRewardLogRowIsSkippedNotThrown(): void
    {
        $activityId = $this->makeActivity([
            'rewards' => [['day' => 1, 'reward' => ['type' => 'platform_coin', 'amount' => '7']]],
        ]);

        $participationId = SnowflakeService::generate();
        Db::table('activity_participation')->insert([
            'id'          => $participationId,
            'user_id'     => $this->userId,
            'activity_id' => $activityId,
            'period_key'  => date('Y-m-d'),
            'current'     => 1,
            'target'      => 1,
            'status'      => 'completed',
        ]);
        // 已存在的同一条奖励流水（participation_id + reward_type + reward_ref 正是 uk_idempotent）
        Db::table('activity_reward_log')->insert([
            'id'              => SnowflakeService::generate(),
            'user_id'         => $this->userId,
            'activity_id'     => $activityId,
            'participation_id' => $participationId,
            'period_key'      => date('Y-m-d'),
            'reward_type'     => 'platform_coin',
            'reward_ref'      => 1,
            'amount'          => '7',
            'status'          => 'succeeded',
        ]);

        $result = ActivityService::checkin($this->userId, $activityId);

        $this->assertSame('rewarded', $result['status'], '撞键跳过后面仍必须是终态');
        $this->assertSame([], $result['reward'], '已发过的那条不得再次出现在已发列表里');
        $this->assertSame(0, bccomp($this->balance(WalletScope::platform()), '0', 8), '不得重复出钱');
        $this->assertSame(1, Db::table('activity_reward_log')->where('user_id', $this->userId)->count(),
            '不得插入第二行 reward_log');
    }

    private function makeActivity(array $config): int
    {
        $id = SnowflakeService::generate();
        Db::table('activity')->insert([
            'id'              => $id,
            'type'            => Activity::TYPE_SIGNIN,
            'name'            => 'guard-test-' . $id,
            'game_id'         => 0,
            'config'          => json_encode($config),
            'status'          => Activity::STATUS_ENABLED,
            'rollout_percent' => 100,
        ]);
        $this->activityIds[] = $id;

        return $id;
    }

    private function balance(WalletScope $scope): string
    {
        return WalletService::balance($this->userId, $scope);
    }

    private function participationStatus(int $activityId): string
    {
        return (string) Db::table('activity_participation')
            ->where('user_id', $this->userId)->where('activity_id', $activityId)
            ->orderByDesc('id')->value('status');
    }

    private function rewardLog(int $activityId): object
    {
        $row = Db::table('activity_reward_log')
            ->where('user_id', $this->userId)->where('activity_id', $activityId)
            ->orderBy('reward_ref')->first();
        $this->assertNotNull($row, '应有 reward_log 行');

        return $row;
    }

    /**
     * 断言当天日志文件里出现了这条 error（同一行须同时带上给定的每个片段）。
     * 只读文件尾部：并发跑测试时别人也在写这个文件，但 participation_id 是本用例独有的 snowflake。
     */
    private function assertLogged(string $needle, array $contextParts): void
    {
        $file = dirname(__DIR__) . '/runtime/logs/webman-' . date('Y-m-d') . '.log';
        if (!is_file($file)) {
            $this->fail("日志文件不存在：{$file}");
        }

        $size = (int) filesize($file);
        $fh = fopen($file, 'rb');
        $tail = '';
        if ($size > 262144) {
            fseek($fh, $size - 262144);
        }
        while (!feof($fh)) {
            $tail .= (string) fread($fh, 65536);
        }
        fclose($fh);

        foreach (explode("\n", $tail) as $line) {
            if (!str_contains($line, $needle)) {
                continue;
            }
            foreach ($contextParts as $part) {
                if (!str_contains($line, $part)) {
                    continue 2;
                }
            }

            $this->addToAssertionCount(1);

            return;
        }

        $this->fail("日志尾部未找到 `{$needle}` 且同时包含 " . json_encode($contextParts));
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
        // 凭据优先取环境变量（GP_DB_USER/GP_DB_PASS，本机可用其覆盖），缺省回落到 config('database')
        // 即 .env 的口令 —— 不要硬编码空串：那会让本类及其之后的所有用例连不上库并静默 skip。
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
