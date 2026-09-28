<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\CouponController;
use common\HashidsService;
use common\SnowflakeService;
use Erikwang2013\Hashids\Webman\Bootstrap as HashidsBootstrap;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use support\Response;

/**
 * 优惠券 conditions 的口径钉：available() 的「可领」列表必须与 claim() 的准入一致。
 *
 * 修复前 available() 只过滤 status/时间/库存/user_limit，**全函数零处引用 conditions**，
 * 而 conditions 的校验只长在 claim() 里 ⇒ 用户能在「可领」列表里看见一张自己领不了的券，
 * 点领取才被 400 拒（docs/API.md 与 PLATFORM-AUDIT-REPORT.md 却都声称两处双重校验）。
 * 本类的第一个用例在修复前必红（拦人的券出现在列表里），修复后绿。
 *
 * 第二个用例是重构对照：抽取 conditionsBlockReason() 后 claim() 的**拒绝文案逐字不变**，
 * 它在修复前后都必须绿 —— 红了说明抽取改了行为，而不是修好了缺陷。
 *
 * 只打测试库：连接库名必须含 test，否则硬失败，绝不静默写开发库。
 */
final class CouponAvailableConditionsTest extends TestCase
{
    private static bool $booted = false;

    private int $userId = 0;
    /** @var int[] 本用例造的定义券，tearDown 精确删除 */
    private array $couponIds = [];
    /** 用户【没】玩过的游戏（条件不满足） */
    private int $unplayedGameId = 0;
    /** 用户【玩过】的游戏（条件满足） */
    private int $playedGameId = 0;

    /** 用户没玩过该游戏 + 已有一笔 confirmed 充值（10 平台币） */
    private int $minDepositCoupon = 0;
    private int $firstOnlyCoupon = 0;
    private int $unplayedGameCoupon = 0;
    private int $noCondition = 0;
    private int $conditionMet = 0;
    /** @var array<string, int> label => couponId，conditions 拦人的那几张 */
    private array $blocked = [];
    /** 本用例注入的 insert 失败触发器名（装了就必须在 tearDown 摘掉） */
    private ?string $failTrigger = null;

    public static function setUpBeforeClass(): void
    {
        self::bootTargetDatabase();
        HashidsBootstrap::start(null);
    }

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        if ($this->userId === 0) {
            return;
        }

        // 注入的触发器必须先摘（留着会拦下后续用例的 insert）
        if ($this->failTrigger !== null) {
            Db::statement('DROP TRIGGER IF EXISTS ' . $this->failTrigger);
            $this->failTrigger = null;
        }

        // 只删本用例造的行（snowflake ID 唯一），不 TRUNCATE、不碰他人数据
        Db::table('user_coupon')->where('user_id', $this->userId)->delete();
        Db::table('deposit_order')->where('user_id', $this->userId)->delete();
        Db::table('game_play_log')->where('user_id', $this->userId)->delete();
        Db::table('coupon')->whereIn('id', $this->couponIds)->delete();
    }

    /**
     * 三种条件各造一张「用户不满足」的券，外加两张对照券（无条件 / 条件已满足）。
     * 修复前：三张拦人的券会全部出现在 available() 里 ⇒ 断言红。
     */
    #[Test]
    public function availableHidesCouponsWhoseConditionsTheUserFails(): void
    {
        $ids = $this->availableIds();

        foreach ($this->blocked as $label => $couponId) {
            $this->assertNotContains(
                $this->encoded($couponId),
                $ids,
                "conditions 不满足的券（{$label}）不得出现在可领列表 —— 否则用户看得到却领不走"
            );
        }

        // 反向对照：修过头（例如把整批券都过滤掉）会在这里红
        $this->assertContains($this->encoded($this->noCondition), $ids, '无 conditions 的券必须照常可领');
        $this->assertContains($this->encoded($this->conditionMet), $ids, '条件已满足的券必须可领');
    }

    /**
     * 抽取 conditionsBlockReason() 前后 claim() 的拒绝文案必须逐字一致（本用例修复前后都绿）。
     */
    #[Test]
    public function claimRejectionMessagesAreUnchanged(): void
    {
        $cases = [
            'min_deposit 不足' => ['Minimum deposit of 100.0000 not met', $this->minDepositCoupon],
            'first_user_only'  => ['This coupon is for new users only', $this->firstOnlyCoupon],
            'game_id 未玩过'    => ['Must play the required game first', $this->unplayedGameCoupon],
        ];

        foreach ($cases as $label => [$message, $couponId]) {
            $body = $this->claim($couponId);
            $this->assertSame(400, $body['code'], "claim 应拒绝：{$message}（实际 " . json_encode($body) . '）');
            $this->assertSame($message, $body['message'], '拒绝文案必须与抽取前逐字一致');
        }

        // 拒绝路径不得留下 user_coupon（券没领走）
        $this->assertSame(
            0,
            Db::table('user_coupon')->where('user_id', $this->userId)->count(),
            '被 conditions 拒的 claim 不得落 user_coupon'
        );
    }

    /**
     * 零充值用户的 min_deposit 校验。
     *
     * sum() 在零行时返回 **int 0**（不是 null），`?? '0'` 因此不生效，bccomp 收 int 直接抛
     * TypeError ⇒ 500 —— 而「零充值」恰是 min_deposit 最常命中的场景（新用户）。
     * 这条在修复前是红的：claim 返回 code=500（异常）而非 400。
     */
    #[Test]
    public function minDepositCheckHandlesUserWithNoDepositAtAll(): void
    {
        $freshUser = SnowflakeService::generate(); // 一行 confirmed 充值都没有

        $body = $this->claim($this->minDepositCoupon, $freshUser);
        $this->assertSame(400, $body['code'], '零充值用户应被 400 拒，不是 500 TypeError：' . json_encode($body));
        $this->assertSame('Minimum deposit of 100.0000 not met', $body['message']);

        // 列表侧同样不得 500，且这张券不出现
        $this->assertNotContains(
            $this->encoded($this->minDepositCoupon),
            $this->availableIds($freshUser),
            '零充值用户也不该看见需要充值的券'
        );
    }

    /**
     * 坏 conditions 只影响它自己那张券。
     *
     * conditions 是直写库的 JSON（admin 的 store/update 都不收这个字段），值可能是 JSON 数字、
     * 数组或 'abc'/'1e5' 这类 bcmath 读不了的字符串。修复前 bccomp 直接抛 TypeError/ValueError，
     * 而 available() 是按**整批券**过滤的 ⇒ 一张坏券把整条列表对所有用户打成 500（claim 侧只坏它自己）。
     * 要求：坏券按「本人不满足」处理 —— 自己看不见，**其余好券照常在列表里**，claim 回 400 不是 500。
     */
    #[Test]
    public function malformedConditionHidesOnlyItsOwnCoupon(): void
    {
        $badCases = [
            '非数值字符串'            => $this->coupon(['min_deposit' => 'abc']),
            'bcmath 读不了的指数记法'  => $this->coupon(['min_deposit' => '1e5']),
            'JSON 数字（非字符串）'    => $this->coupon(['min_deposit' => 100]),
            '数组'                   => $this->coupon(['min_deposit' => ['100']]),
        ];

        $ids = $this->availableIds();

        foreach ($badCases as $label => $couponId) {
            $this->assertNotContains(
                $this->encoded($couponId),
                $ids,
                "conditions 坏数据（{$label}）的券不得出现在列表里"
            );

            $body = $this->claim($couponId);
            $this->assertSame(
                400,
                $body['code'],
                "claim 坏数据券（{$label}）应回 400 而不是 500：" . json_encode($body)
            );
            $this->assertSame('Invalid coupon condition', $body['message'], "拒绝原因（{$label}）");
        }

        // 关键反证：不是靠「整批过滤」糊过去的 —— 好券必须还在列表里
        $this->assertContains($this->encoded($this->noCondition), $ids, '无 conditions 的好券必须仍在列表里');
        $this->assertContains($this->encoded($this->conditionMet), $ids, '条件已满足的好券必须仍在列表里');
    }

    /**
     * conditions.game_id 非标量（JSON 数组 / 数字前缀尾随垃圾）必须 fail-closed。
     *
     * 与 min_deposit 那处不同，这里坏掉的形态**不抛异常**：`(int)` 对数组和数字前缀字符串是
     * **静默退化**（实测 [123]→1、'<大数>abc'→<大数>，PHP 连个 warning 都不给），而 `> 0` 对数组
     * 也是 true ⇒ 分支照进不误。退化出的 id 一旦恰好是用户玩过的游戏，这张**条件根本读不出来**
     * 的券就被放行 —— red 态实测 claim 会真把它领走（code=0 且落 user_coupon）。
     *
     * 所以本用例**故意**给用户造一条 game_id=1 的记录：那正是 [123] 退化后撞上的 id。
     * 少了这一步，红态会因为「没玩过 1 号游戏」被无关原因挡住，红读数就退化成「总是被拒」而不是
     * 「放行了不该放的」—— fail-open 的红必须长得像放行。
     */
    #[Test]
    public function malformedGameIdConditionFailsClosed(): void
    {
        // 退化后的 id 必须真的「玩过」，否则红态是假红（见方法注释）
        Db::table('game_play_log')->insert([
            'id'      => SnowflakeService::generate(),
            'user_id' => $this->userId,
            'game_id' => 1,
            'action'  => 'start',
        ]);

        $badCases = [
            'JSON 数组'          => $this->coupon(['game_id' => [123]]),
            '数字前缀 + 尾随垃圾'  => $this->coupon(['game_id' => $this->playedGameId . 'abc']),
        ];

        $ids = $this->availableIds();

        $observed = [];
        foreach ($badCases as $label => $couponId) {
            // $ids 快照在上方、任何写操作之前取。
            // PHPUnit 下控制器异常会直接抛出（生产里才是 500 响应），任它冒泡就只能看到「抛了」，
            // 看不到列表那条读数 —— 而「券出现在列表里」才是这条缺陷的红。故把异常也收成读数。
            try {
                $body = $this->claim($couponId);
            } catch (\Throwable $e) {
                $body = ['code' => 500, 'message' => get_class($e) . ': ' . $e->getMessage()];
            }

            $observed[$label] = [
                'in_list'    => in_array($this->encoded($couponId), $ids, true),
                'claim_code' => $body['code'] ?? null,
                'claim_msg'  => $body['message'] ?? null,
            ];
        }

        // 每种坏形状的读数一次断言、逐个列出：红态会打印完整差异，不会因第一条失败就把其余藏起来
        $this->assertSame(
            array_fill_keys(
                array_keys($badCases),
                ['in_list' => false, 'claim_code' => 400, 'claim_msg' => 'Invalid coupon condition']
            ),
            $observed,
            'conditions.game_id 读不出来的券必须 fail-closed：出现在列表里、或 claim 没回 400，都算被放行'
        );

        // 关键反证：同一次快照上 —— 证明不是靠「整批过滤」糊过去的
        $this->assertContains($this->encoded($this->noCondition), $ids, '无 conditions 的好券必须仍在列表里');
        $this->assertContains($this->encoded($this->conditionMet), $ids, '条件已满足的好券必须仍在列表里');

        $this->assertSame(
            0,
            Db::table('user_coupon')->where('user_id', $this->userId)->count(),
            '被拒的 claim 不得落 user_coupon（fail-open 时这里会 >0）'
        );
    }

    /**
     * claim() 的成功路径必须真的落库（本类的验收核心）。
     *
     * `CouponController.php:154` 是全仓**唯一**写 user_coupon 的地方，而 UserCoupon 没关
     * `$timestamps` ⇒ Eloquent 必写 updated_at，可 install.sql 的 game_user_coupon 只有 created_at
     * ⇒ 恒 `Unknown column 'updated_at'` ⇒ **凡是通过校验的领取都是 500**。此前没有任何用例走过
     * 成功路径（拒绝路径都在写库之前 return），所以「测试不红」证明不了这个功能好使。
     *
     * 修法是给模型加 `public $timestamps = false;`（全仓 28 个模型同款惯例），不动 DDL：
     * created_at 由 DDL 的 DEFAULT CURRENT_TIMESTAMP 兜住，所以第 4 项要专门盯它有没有丢。
     */
    #[Test]
    public function successfulClaimPersistsUserCoupon(): void
    {
        // 列表侧零副作用：available() 不写库，先前置确认一次（免得把它的写入算到 claim 头上）
        $this->assertContains(
            $this->encoded($this->conditionMet),
            $this->availableIds(),
            '条件已满足的券应在可领列表里（列表本身不写库）'
        );
        $this->assertSame(
            0,
            Db::table('user_coupon')->where('user_id', $this->userId)->count(),
            'available() 不得写 user_coupon'
        );

        try {
            $body = $this->claim($this->conditionMet);
        } catch (\Throwable $e) {
            $body = ['code' => 500, 'message' => get_class($e) . ': ' . $e->getMessage()];
        }

        $row = Db::table('user_coupon')->where('user_id', $this->userId)->first();

        // 五项读数一次断言：红态（撤掉 $timestamps）会打印完整差异，含 Unknown column 原文
        $this->assertSame(
            ['code' => 0, 'rows' => 1, 'used_qty' => 1, 'coupon_id_ok' => true, 'status' => 'unused', 'used_in_order' => '', 'created_at_set' => true],
            [
                'code'           => $body['code'] ?? null,
                'rows'           => $row === null ? 0 : 1,
                'used_qty'       => (int) Db::table('coupon')->where('id', $this->conditionMet)->value('used_qty'),
                'coupon_id_ok'   => $row !== null && (int) $row->coupon_id === $this->conditionMet,
                'status'         => $row->status ?? null,
                'used_in_order'  => $row->used_in_order ?? null,
                'created_at_set' => $row !== null && !empty($row->created_at),
            ],
            'claim() 的成功路径必须真的落库并回 code=0：' . json_encode($body)
        );
    }

    /**
     * insert 失败不得留下「库存 -1、券行 0」的半写。
     *
     * `claim()` 的 `increment('used_qty')` 与落券行是两步、中间无事务，第二步失败就把库存白扣了。
     * 这里用**临时触发器**只把 insert 打失败 —— 失败面必须落在 insert 本身，不能是校验早退
     * （否则测的是另一回事，故断言里专门检查注入的报错原文出现没出现）。
     */
    #[Test]
    public function failedInsertRollsBackUsedQty(): void
    {
        $this->installFailTrigger();

        try {
            $body = $this->claim($this->conditionMet);
        } catch (\Throwable $e) {
            $body = ['code' => 500, 'message' => get_class($e) . ': ' . $e->getMessage()];
        }

        $this->assertSame(
            ['used_qty' => 0, 'rows' => 0, 'is_insert_failure' => true],
            [
                'used_qty'          => (int) Db::table('coupon')->where('id', $this->conditionMet)->value('used_qty'),
                'rows'              => Db::table('user_coupon')->where('user_id', $this->userId)->count(),
                // 只有看到注入的报错，才能证明失败发生在 insert，而不是被校验提前挡住
                'is_insert_failure' => str_contains((string) ($body['message'] ?? ''), 'injected insert failure'),
            ],
            'insert 失败必须整体回滚，库存不得被吞：' . json_encode($body)
        );
    }

    // ---------------------------------------------------------------- 夹具

    /**
     * 装一个只对本用例 user_id 生效的 insert 失败触发器（tearDown 摘掉）。
     *
     * 选触发器而不是改表结构/加唯一索引：失败面精确落在 `INSERT INTO game_user_coupon` 上，
     * 不碰表定义、不影响他人数据（探针实测：他人 user_id 的 insert 照常通过）。
     */
    private function installFailTrigger(): void
    {
        $this->failTrigger = 'cl_fail_ins_' . $this->userId;
        Db::statement(
            "CREATE TRIGGER {$this->failTrigger} BEFORE INSERT ON game_user_coupon FOR EACH ROW "
            . "BEGIN IF NEW.user_id = {$this->userId} THEN SIGNAL SQLSTATE '45000' "
            . "SET MESSAGE_TEXT = 'injected insert failure (test)'; END IF; END"
        );
    }

    private function seedFixtures(): void
    {
        $this->userId         = SnowflakeService::generate();
        $this->unplayedGameId = SnowflakeService::generate();
        $this->playedGameId   = SnowflakeService::generate();

        // 已充值 10 平台币（confirmed）：min_deposit=100 不满足、first_user_only 不满足
        Db::table('deposit_order')->insert([
            'id'              => SnowflakeService::generate(),
            'order_no'        => 'cpn_it_' . $this->userId,
            'user_id'         => $this->userId,
            'amount'          => '10.0000',
            'platform_amount' => '10.0000',
            'status'          => 'confirmed',
        ]);

        // 玩过 playedGameId，没玩过 unplayedGameId
        Db::table('game_play_log')->insert([
            'id'      => SnowflakeService::generate(),
            'user_id' => $this->userId,
            'game_id' => $this->playedGameId,
            'action'  => 'start',
        ]);

        $this->minDepositCoupon   = $this->coupon(['min_deposit' => '100.0000']);
        $this->firstOnlyCoupon    = $this->coupon(['first_user_only' => true]);
        $this->unplayedGameCoupon = $this->coupon(['game_id' => $this->unplayedGameId]);
        $this->noCondition        = $this->coupon([]);
        $this->conditionMet       = $this->coupon(['game_id' => $this->playedGameId]);

        $this->blocked = [
            'min_deposit'     => $this->minDepositCoupon,
            'first_user_only' => $this->firstOnlyCoupon,
            'game_id'         => $this->unplayedGameCoupon,
        ];
    }

    /** @param array<string, mixed> $conditions */
    private function coupon(array $conditions): int
    {
        $id = SnowflakeService::generate();
        $this->couponIds[] = $id;

        Db::table('coupon')->insert([
            'id'         => $id,
            'name'       => 'Coupon IT ' . $id,
            'type'       => 'fixed',
            'value'      => '5.0000',
            'conditions' => $conditions === [] ? null : json_encode($conditions),
            'total_qty'  => 0,
            'used_qty'   => 0,
            'user_limit' => 1,
            'status'     => 1,
        ]);

        return $id;
    }

    // ---------------------------------------------------------------- 调用

    /**
     * 列表返回的券 ID 集合。
     *
     * 刻意对信封两种形态都吃（裸数组 / {list:[...]}）：本用例要证明的是 conditions 口径，
     * 不该因为同期「信封对齐成 {list:...}」的改动而红/绿 —— 那样红绿都归因错了。
     *
     * @return string[]
     */
    private function availableIds(?int $userId = null): array
    {
        $body = $this->json((new CouponController())->available($this->request('GET', '/api/v1/coupon/available', $userId)));
        $data = $body['data'] ?? [];
        if (isset($data['list']) && is_array($data['list'])) {
            $data = $data['list'];
        } elseif (isset($data['items']) && is_array($data['items'])) {
            $data = $data['items'];
        }

        return array_map(static fn ($row) => (string) ($row['id'] ?? ''), $data);
    }

    /** @return array<string, mixed> */
    private function claim(int $couponId, ?int $userId = null): array
    {
        $request = $this->request('POST', '/api/v1/coupon/claim', $userId);
        $request->setPost(['coupon_id' => $this->encoded($couponId)]);

        return $this->json((new CouponController())->claim($request));
    }

    private function request(string $method, string $path, ?int $userId = null): Request
    {
        $request = new Request("{$method} {$path} HTTP/1.1\r\nHost: localhost\r\n\r\n");
        // 生产环境由 UserAuth 中间件注入，PHPUnit 下手工放上
        $request->userId = $userId ?? $this->userId;

        return $request;
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        return json_decode($response->rawBody(), true) ?: [];
    }

    private function encoded(int $id): string
    {
        return HashidsService::encode($id);
    }

    /**
     * 让 support\Db 指向测试库（与 ExchangeWalletIntegrationTest 同一套做法：
     * support\Db 的 autoload 会先烧掉一次性 Initializer 并指向开发库，必须自己再 setAsGlobal）。
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
}
