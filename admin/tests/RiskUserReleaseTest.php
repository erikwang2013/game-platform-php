<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\admin\v1\controller\RiskUserController;
use app\service\WalletScope;
use app\service\WalletService;
use common\HashidsService;
use common\SnowflakeService;
use common\model\RiskLog;
use common\model\Transaction;
use common\model\UserWallet;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * 管理端解冻端点（RiskUserController::release）与 hold 配对。
 *
 * 修的是「能冻不能解」：hold 从上线起就把平台可用余额全额冻走（action=block），
 * 但全仓没有任何把 frozen 搬回 available 的入口 —— 用户申诉无处落，管理员只能改库。
 * 新增的 release 必须满足四条：
 *  1) 纯搬移、不得铸币：balance + frozen 恒等于冻结前总额（每一步都断言）；
 *  2) 释放量 ≤ frozen_balance：超过即拒；
 *  3) 重复释放被拒（第二次没有可释放的冻结）；
 *  4) 可追溯到「哪一笔」：释放流水复用被释放那笔冻结的 (ref_type, ref_id)；
 *  5) 闸之外的**意外失败**必须留痕且不回吐原始异常（DB 故障不可预测，失败路径看不见＝同一类病换个位置）。
 *
 * 直接 new 控制器调用（不经过 HTTP 层）：鉴权链在 config/route.php 与 AdminAuth/AdminPermission
 * 里，另有 AdminPermissionMatchingTest 覆盖 slug 匹配；这里钉的是**资金语义**。
 *
 * 请求用 HTTP 原文构造：`new Request('POST', '/path')` 两参形式不产生可解析数据源
 * （父类构造器只吃一个 buffer 字符串），输入会读成空 —— 那样 amount 永远缺省，断言全成假绿。
 */
class RiskUserReleaseTest extends TestCase
{
    private int $userId = 0;
    private string $hashid = '';

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

        $this->userId = SnowflakeService::generate();
        $this->hashid = HashidsService::encode($this->userId);

        Db::table('user')->insert([
            'id'       => $this->userId,
            'username' => 'release-test-' . $this->userId,
            'password' => password_hash('test', PASSWORD_BCRYPT),
        ]);

        $this->assertTrue(
            WalletService::mutate($this->userId, WalletScope::platform(), '+100', 'deposit', 'test', 0),
            '备款 100 应成功'
        );
    }

    protected function tearDown(): void
    {
        if ($this->userId === 0) {
            return;
        }

        // 钱包写路径自 2026-09-28 起落 wallet.mutated 事件行（Outbox）：按本用户流水派生的 event_id
        // 精确删除，不按全表计数/全表删（并发跑测试时别人也在写这张表）
        $txIds = Db::table('transaction')->where('user_id', $this->userId)->pluck('id')->all();
        if ($txIds !== []) {
            Db::table('event_outbox')->whereIn('event_id', array_map(
                static fn ($id) => 'wallet.mutated:' . $id,
                $txIds
            ))->delete();
        }

        foreach (['transaction', 'wallet_hold', 'user_wallet', 'risk_log'] as $table) {
            Db::table($table)->where('user_id', $this->userId)->delete();
        }
        Db::table('user')->where('id', $this->userId)->delete();
    }

    /**
     * 冻结 → 部分释放 → 全额释放：每一步都是桶间搬移，总额恒定；释放流水能配回到原冻结那一笔。
     */
    #[Test]
    public function releaseMovesFrozenBackWithoutMinting(): void
    {
        // hold：全额冻结可用余额（既有端点，freeze 100）
        $held = $this->json($this->controller()->hold($this->request(), $this->hashid));
        $this->assertSame(0, $held['code'], '冻结应成功：' . $held['message']);
        $this->assertMoney('0', '100');

        // 部分释放 40
        $released = $this->json($this->controller()->release($this->request(['amount' => '40']), $this->hashid));
        $this->assertSame(0, $released['code'], '部分释放应成功：' . $released['message']);
        $this->assertSame('40.00000000', (string) $released['data']['released_amount']);
        $this->assertMoney('40', '60');

        // 缺省全额释放剩下的 60
        $rest = $this->json($this->controller()->release($this->request(), $this->hashid));
        $this->assertSame(0, $rest['code'], '全额释放应成功：' . $rest['message']);
        $this->assertSame('60.00000000', (string) $rest['data']['released_amount']);
        $this->assertMoney('100', '0');

        // 可追溯「哪一笔」：两条流水 (ref_type, ref_id) 相同，靠 type 分辨方向
        $rows = Db::table('transaction')->where('user_id', $this->userId)
            ->whereIn('type', [WalletService::TYPE_LOCK, WalletService::TYPE_UNLOCK])
            ->orderBy('id')
            ->get(['type', 'ref_type', 'ref_id', 'amount', 'scope'])->all();
        $this->assertSame(3, count($rows), '应恰好 1 条 lock + 2 条 unlock');
        $this->assertSame(WalletService::TYPE_LOCK, $rows[0]->type);
        $this->assertSame('risk_hold', $rows[0]->ref_type, '冻结方写的是 risk_hold + risk_log 主键');
        $this->assertSame(WalletScope::PLATFORM, $rows[0]->scope, '解冻只搬平台钱包');

        $holdRefId = (int) $rows[0]->ref_id;
        $this->assertGreaterThan(0, $holdRefId);
        foreach ([1, 2] as $i) {
            $this->assertSame(WalletService::TYPE_UNLOCK, $rows[$i]->type);
            $this->assertSame('risk_hold', $rows[$i]->ref_type, '释放行必须复用原冻结的 ref_type');
            $this->assertSame($holdRefId, (int) $rows[$i]->ref_id, '释放行必须指向被释放的那一笔冻结');
        }

        // 管理端留痕：hold(action=block) 与 release(action=unblock) 都能在时间线上看到
        $logs = RiskLog::where('user_id', $this->userId)->orderBy('created_at')->orderBy('id')->get(['type', 'action', 'result', 'context'])->all();
        $this->assertSame(['manual_hold', 'manual_release', 'manual_release'], array_map(static fn ($r) => $r->type, $logs));
        $this->assertSame(['block', 'unblock', 'unblock'], array_map(static fn ($r) => $r->action, $logs));
        $this->assertSame(
            (int) $holdRefId,
            (int) (json_decode((string) $logs[1]->context, true)['hold_ref_id'] ?? 0),
            '解冻日志要写明释放的是哪一笔冻结'
        );
    }

    /**
     * 按笔消费（per-hold 子台账）：释放先把「被指定的那笔冻结」（= 最新一笔 risk_hold）吃光，
     * 不足部分才按 FIFO 继续 —— 纯 FIFO 会先吃掉较老那笔，故两种口径在本用例里可区分。
     * 同时钉住不变量 frozen_balance == Σhold.remaining 与「释放不铸币」。
     */
    #[Test]
    public function releaseConsumesTheTargetedHoldBeforeFallingBackToFifo(): void
    {
        $scope = WalletScope::platform();

        $first = $this->json($this->controller()->hold($this->request(), $this->hashid));
        $this->assertSame(0, $first['code'], '第一笔冻结应成功：' . $first['message']);

        // 再充 100 并再全额冻结 ⇒ 同一钱包两笔活的冻结
        $this->assertTrue(WalletService::mutate($this->userId, $scope, '+100', 'deposit', 'test', 0), '再备款应成功');
        $second = $this->json($this->controller()->hold($this->request(), $this->hashid));
        $this->assertSame(0, $second['code'], '第二笔冻结应成功：' . $second['message']);

        $holds = $this->holdRows();
        $this->assertCount(2, $holds, '两笔冻结必须两行台账（修复前一行都没有）');
        $this->assertSame('100.00000000', (string) $holds[0]->remaining, '第一行 = 第一笔冻结额');
        $this->assertSame('100.00000000', (string) $holds[1]->remaining, '第二行 = 第二笔冻结额');
        $this->assertSame('risk_hold', (string) $holds[1]->ref_type, '台账行必须记住来源单据类型');

        // 释放 150：应吃光较新那笔 100 + 较老那笔的 50（纯 FIFO 会反过来先吃光较老那笔）
        $released = $this->json($this->controller()->release($this->request(['amount' => '150']), $this->hashid));
        $this->assertSame(0, $released['code'], '释放应成功：' . $released['message']);
        $this->assertSame('150.00000000', (string) $released['data']['released_amount']);

        $holds = $this->holdRows();
        $this->assertSame('50.00000000', (string) $holds[0]->remaining, '较老那笔只被吃了 50（纯 FIFO 会先把它吃光）');
        $this->assertSame('0.00000000', (string) $holds[1]->remaining, '被指定的较新那笔先被吃光');
        $this->assertSame(WalletService::HOLD_ACTIVE, (int) $holds[0]->status, '还剩 50 ⇒ 仍冻结中');
        $this->assertSame(WalletService::HOLD_RELEASED, (int) $holds[1]->status, '吃光 ⇒ 已释放');
        $this->assertNotNull($holds[1]->released_at, '吃光 ⇒ 盖释放时间戳');
        $this->assertNull($holds[0]->released_at, '没吃光 ⇒ released_at 保持 NULL');

        // 逐笔归因写进流水 remark（顺序 = 实际消费顺序），台账之外还有流水可查
        $remark = (string) Db::table('transaction')->where('user_id', $this->userId)
            ->where('type', WalletService::TYPE_UNLOCK)->orderByDesc('id')->value('remark');
        $this->assertSame(
            '解冻余额 hold:' . (int) $holds[1]->id . ',' . (int) $holds[0]->id,
            $remark,
            'remark 必须按实际消费顺序点名 hold 行'
        );

        // 不变量 + 不铸币：两次备款共 200，释放只在桶间搬
        $sum = '0';
        foreach (Db::table('wallet_hold')->where('user_id', $this->userId)->pluck('remaining') as $remaining) {
            $sum = bcadd($sum, (string) $remaining, WalletService::SCALE);
        }
        $wallet = UserWallet::where('user_id', $this->userId)->first();
        $this->assertSame(0, bccomp((string) $wallet->frozen_balance, $sum, WalletService::SCALE),
            '不变量破了：frozen=' . $wallet->frozen_balance . ' vs Σremaining=' . $sum);
        $this->assertSame(0, bccomp(
            bcadd((string) $wallet->balance, (string) $wallet->frozen_balance, WalletService::SCALE),
            '200',
            WalletService::SCALE
        ), '净额应恒为 200（备款 2×100）：释放既不铸币也不吞钱');
    }

    /** @return array<int,object> 本用户的台账行，按 id 升序 = 冻结先后 */
    private function holdRows(): array
    {
        return Db::table('wallet_hold')->where('user_id', $this->userId)->orderBy('id')
            ->get(['id', 'remaining', 'status', 'released_at', 'ref_type', 'ref_id'])->all();
    }

    /** 重复释放被拒：全额释放后再来一次，冻结已是 0，不得再搬（也不得双记流水/日志） */
    #[Test]
    public function releaseOnEmptyFrozenBalanceIsRejected(): void
    {
        $this->json($this->controller()->hold($this->request(), $this->hashid));
        $this->json($this->controller()->release($this->request(), $this->hashid));
        $this->assertMoney('100', '0');

        $txBefore = $this->unlockCount();
        $logBefore = $this->releaseLogCount();

        $again = $this->json($this->controller()->release($this->request(), $this->hashid));
        $this->assertSame(500, (int) $again['code'], '无冻结余额时第二次释放必须被拒');
        $this->assertSame('用户无冻结余额', (string) $again['message']);

        $this->assertMoney('100', '0', '被拒的释放不得改动任何余额');
        $this->assertSame($txBefore, $this->unlockCount(), '被拒的释放不得落 unlock 流水（双记）');
        $this->assertSame($logBefore, $this->releaseLogCount(), '被拒的释放不得落 manual_release 日志');
    }

    /** 释放量超过冻结余额被拒：frozen 列是 UNSIGNED，这里靠闸先挡（否则 DB 侧报错也是 500，但语义不同） */
    #[Test]
    public function releaseBeyondFrozenBalanceIsRejected(): void
    {
        $this->json($this->controller()->hold($this->request(), $this->hashid));
        $this->assertMoney('0', '100');

        $txBefore = $this->unlockCount();
        $over = $this->json($this->controller()->release($this->request(['amount' => '100.00000001']), $this->hashid));
        $this->assertSame(500, (int) $over['code'], '超过冻结余额必须被拒');
        $this->assertSame('释放金额超过冻结余额', (string) $over['message']);

        $this->assertMoney('0', '100', '被拒的释放不得改动任何余额');
        $this->assertSame($txBefore, $this->unlockCount());

        // 边界：恰好等于冻结额必须放行（不然「超过」实际被写成了「大于等于」）
        $exact = $this->json($this->controller()->release($this->request(['amount' => '100']), $this->hashid));
        $this->assertSame(0, (int) $exact['code'], '恰好等于冻结额应放行：' . $exact['message']);
        $this->assertMoney('100', '0');
    }

    /** 金额语法闸：非标量/非数字不得把 bcmath 的 ValueError 冒成 500 之外的语义（数组进不来、'1e5' 被拒） */
    #[Test]
    public function malformedAmountIsRejectedWithoutTouchingBalances(): void
    {
        $this->json($this->controller()->hold($this->request(), $this->hashid));

        foreach ([['amount' => ['1e5']], ['amount' => 'abc'], ['amount' => '1e5']] as $payload) {
            $bad = $this->json($this->controller()->release($this->request($payload), $this->hashid));
            $this->assertSame(500, (int) $bad['code'], '非法金额必须被拒：' . json_encode($payload));
            $this->assertSame('释放金额格式非法', (string) $bad['message']);
        }

        $this->assertMoney('0', '100', '非法金额不得改动余额');
        $this->assertSame(0, $this->unlockCount());
    }

    /**
     * catch \Throwable 分支的契约：意外失败必须留痕、且不回吐原始异常文本。
     *
     * 这条分支正常条件下不可达（闸做得再全也拦不住 DB 故障），所以**真造一个 DB 故障**而不是注入假异常：
     * 第二个连接把该用户的钱包行 FOR UPDATE 按住，再把测试连接的 innodb_lock_wait_timeout 压到 1 秒
     * ⇒ release() 事务里的锁行读超时，抛 PDOException(1205)，走的正是这条 catch。
     *
     * 成对断言：① 响应是笼统文案、**不含**原始异常文本；② 服务端日志有这条失败 + 定位上下文。
     * 只有①会把「吞掉异常且不留痕」放过，只有②会把「原样回吐 SQL 片段」放过。
     */
    #[Test]
    public function releaseFailureIsLoggedWithoutLeakingRawExceptionText(): void
    {
        $this->json($this->controller()->hold($this->request(), $this->hashid)); // frozen=100
        $this->assertMoney('0', '100');

        $conf = config('database');
        $conn = $conf['connections'][$conf['default']];
        $db = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        $this->assertStringContainsString('test', $db, '第二个连接也必须打测试库');
        $holder = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $conn['host'], (int) $conn['port'], $db),
            (string) $conn['username'],
            (string) $conn['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $holder->beginTransaction();
        $holder->query('SELECT id FROM game_user_wallet WHERE user_id = ' . $this->userId . ' FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);

        $appPdo = Db::connection()->getPdo();
        $timeoutBefore = (int) $appPdo->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn();
        $appPdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            $result = $this->json($this->controller()->release($this->request(), $this->hashid));
        } finally {
            $appPdo->exec('SET SESSION innodb_lock_wait_timeout = ' . $timeoutBefore);
            $holder->rollBack();
        }

        $this->assertSame(500, (int) $result['code'], '锁超时必须走 catch 分支（笼统 500），不是框架层裸 500');
        $this->assertSame('解冻失败，请稍后重试', (string) $result['message'], '必须是笼统文案');
        foreach (['SQLSTATE', '1205', 'Lock wait', 'game_user_wallet', 'SELECT'] as $leak) {
            $this->assertStringNotContainsString($leak, (string) $result['message'], "不得回吐原始异常文本（{$leak}）");
        }

        // 失败不留半截：事务整体回滚，桶不动、无 unlock 流水、无 manual_release 日志
        $this->assertMoney('0', '100', '锁超时后事务必须整体回滚');
        $this->assertSame(0, $this->unlockCount());
        $this->assertSame(0, $this->releaseLogCount());

        // 另一半：服务端留痕，且带得动定位信息（user_id + 追溯到的 ref + 异常文本本身）
        $this->assertLogged('Risk release failed', [(string) $this->userId, 'risk_hold', '1205']);
    }

    private function controller(): RiskUserController
    {
        return new RiskUserController();
    }

    /**
     * HTTP 原文构造：只有这条路径能让 get()/post() 真的解析出内容。
     * amount 走查询串（Request::input() 先查 get 再查 post，两者等价）。
     */
    private function request(array $query = []): Request
    {
        $qs = $query === [] ? '' : '?' . http_build_query($query);

        return new Request("POST /admin/v1/risk/users/{$this->hashid}/release{$qs} HTTP/1.1\r\nHost: localhost\r\n\r\n");
    }

    private function json(\support\Response $response): array
    {
        return json_decode((string) $response->rawBody(), true) ?? [];
    }

    /** 可用余额 + 冻结必须恒等于冻结前总额：任何一步铸币/吞钱都会在这里露出来 */
    private function assertMoney(string $available, string $frozen, string $message = ''): void
    {
        $wallet = UserWallet::where('user_id', $this->userId)->first();
        $this->assertNotNull($wallet, '钱包行应存在');
        $this->assertSame(0, bccomp((string) $wallet->balance, $available, WalletService::SCALE),
            '可用余额应为 ' . $available . '，实际 ' . $wallet->balance . ($message !== '' ? "（{$message}）" : ''));
        $this->assertSame(0, bccomp((string) $wallet->frozen_balance, $frozen, WalletService::SCALE),
            '冻结余额应为 ' . $frozen . '，实际 ' . $wallet->frozen_balance . ($message !== '' ? "（{$message}）" : ''));
        $this->assertSame(0, bccomp(bcadd((string) $wallet->balance, (string) $wallet->frozen_balance, WalletService::SCALE), '100', WalletService::SCALE),
            '桶间搬移不改总额：可用 + 冻结必须恒为 100');
    }

    private function unlockCount(): int
    {
        return Transaction::where('user_id', $this->userId)->where('type', WalletService::TYPE_UNLOCK)->count();
    }

    private function releaseLogCount(): int
    {
        return RiskLog::where('user_id', $this->userId)->where('type', 'manual_release')->count();
    }

    /**
     * 断言当天 admin 日志里出现这条 error，且同一行带上给定的每个片段
     * （webman 的 context 数组与消息同在一行：[ts] default.ERROR: msg {"k":"v"} []）。
     * 只读文件尾部：并发跑测试时别人也在写这个文件，但 user_id 是本用例独有的 snowflake。
     */
    private function assertLogged(string $needle, array $contextParts): void
    {
        $file = dirname(__DIR__) . '/runtime/logs/webman-' . date('Y-m-d') . '.log';
        if (!is_file($file)) {
            $this->fail("日志文件不存在：{$file}");
        }

        $size = (int) filesize($file);
        $fh = fopen($file, 'rb');
        if ($size > 262144) {
            fseek($fh, $size - 262144);
        }
        $tail = '';
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
}
