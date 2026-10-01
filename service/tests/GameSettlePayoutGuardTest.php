<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\ProviderController;
use app\middleware\ProviderAuth;
use app\middleware\SdkSessionAuth;
use app\middleware\UserAuth;
use app\provider\SelfProvider;
use FastRoute\Dispatcher;
use PHPUnit\Framework\TestCase;
use Webman\Route;

/**
 * 自研/内嵌游戏结算派奖闸的判据钉（不连库 / 不连 Redis：纯反射 + 源码字面量）。
 *
 * 结算闸写歪的方式全是静默放水，功能层面看不出来：客户端照常拿到 success、照常入库，
 * 只是账上多出来的钱可以经兑换成平台币再提现：
 *   1. 金额不规范化        -> '+-100'/'abc'/'1e5' 直接拼进 bcmath 抛 ValueError，业务 500（闸变崩溃）
 *   2. round_id 允许空串    -> 幂等查询（`$roundId !== '' &&`）整体跳过，同一局可无限重放铸币；bet 侧放行则造出「扣了钱但永远无法结算」的废记录
 *   3. 没有同 round 投注匹配 -> 从没下过注的 round 也能派奖 = 客户端凭 settle 直接铸币
 *   4. 没有派奖上限        -> 投 1 块派 1 万，赔率由客户端说了算；倍数闸还挡不住「每轮按比例拿满」（换 round = 换额度），须另有单 round 绝对上限
 *   5. 幂等查询排在锁读之前  -> 并发同 round 各自读到「还没结算过」的旧快照，同一注码入账两次
 *   6. 锁序与 bet 相反      -> bet 是「先钱包行、后流水」，settle 若先流水后钱包即 AB-BA 成环，真并发下 1213 死锁（合法请求 500）
 *   7. 退款沿用派奖闸      -> bet 没有下注上限，退款却被 MAX_PAYOUT_PER_ROUND 管住时，大额下注的本金永远退不回来（实测）
 *   8. 控制器原文进 bcmath  -> 闸 1 只在 settle 内生效，bet/refund 入口对客户端原文直接 bccomp，'abc'/'1e5' 抛 ValueError ⇒ 500
 *   9. 会话令牌带写权（M0） -> GET /api/v1/game/session 用 game.api_secret 替**请求者本人**签令牌，而写端点只认这枚令牌
 *                              ⇒ 任意登录用户 bet 100 / settle 10000 即单轮净 +9900（两闸都恰好不算超），换 round 可无限重复
 *  10. 服务端令牌签发端挂错中间件（M1） -> /api/provider/session-token 的信任边界**全在那行路由挂的中间件上**：
 *                              挪出 ProviderAuth（改挂公开组 / UserAuth / SdkSessionAuth）即「匿名换写令牌」，
 *                              闸 9 的修复当场作废，而端点照常返回一枚合法令牌、功能层面看不出来。
 * 1-9 都长在方法体内，读源文件即可核对（与 WithdrawEventReconcileContractTest 同一套做法）；10 需要真实路由表。
 */
final class GameSettlePayoutGuardTest extends TestCase
{
    /**
     * route.php 的装载是「清空全部静态状态 + require_once 路由文件」⇒ 调第二次拿到的是 0 条路由的假象
     * （表现为「所有路由都没命中」的假红）。故只能类级装载一次，全进程共用；
     * 先跑到的类负责装，后面的类直接复用，别在 setUp() 里逐用例调。
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (!Route::getRoutes()) {
            Route::load([dirname(__DIR__) . '/config']);
        }
    }

    private const SELF_PROVIDER = '/app/provider/SelfProvider.php';
    private const PROVIDER_AUTH = '/app/middleware/ProviderAuth.php';
    private const SDK_SESSION_AUTH = '/app/middleware/SdkSessionAuth.php';
    private const GAME_CONTROLLER = '/app/api/v1/controller/GameController.php';
    private const GAME_SDK_CONTROLLER = '/app/api/v1/controller/GameSdkController.php';
    private const PROVIDER_CONTROLLER = '/app/api/v1/controller/ProviderController.php';

    private static function source(string $relative): string
    {
        $file = dirname(__DIR__) . $relative;
        self::assertFileExists($file, "入账/鉴权文件缺失 {$relative} :: 闸无从核对");

        // 断言前去掉注释：闸的说明文字里会引用 `->sum()` / `$roundId !== ''` 这类反面写法，
        // 留着会让「不得出现」类断言被自己的注释判红（断言要认代码，不认散文）
        return (string) preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', (string) file_get_contents($file));
    }

    /** settle 方法体切片：判据只认闸所在的方法，避免被别处同名常量、同名字面量凑巧满足 */
    private static function settleSource(): string
    {
        $src = self::source(self::SELF_PROVIDER);
        self::assertSame(1, preg_match('/public function settle\(.*?\n    \}/s', $src, $m), '未切出 settle 方法体 :: 切片判据失效');

        return $m[0];
    }

    /** 任意 public 方法体切片（同 settleSource 的取法，供 M0 的鉴权判据复用） */
    private static function methodBody(string $src, string $method, string $what): string
    {
        self::assertSame(1, preg_match('/public function ' . $method . '\(.*?\n    \}/s', $src, $m), "未切出 {$what} 方法体 :: 切片判据失效");

        return $m[0];
    }

    /** 闸 3 的倍数：必须是 > 1 的整数常量，且在合理区间内（反射读，不靠字符串匹配） */
    public function testMaxPayoutMultiplierConstantIsSane(): void
    {
        $class = new \ReflectionClass(SelfProvider::class);
        self::assertTrue($class->hasConstant('MAX_PAYOUT_MULTIPLIER'), '派奖上限倍数常量缺失 :: 上限闸无从调参，也无法被本测试钉住');

        $multiplier = $class->getReflectionConstant('MAX_PAYOUT_MULTIPLIER')->getValue();
        self::assertIsInt($multiplier, '倍数须为整数常量 :: 非整数进 bcmul 前还得再做一次类型转换');
        self::assertGreaterThan(1, $multiplier, '倍数 <= 1 :: 任何高于投注额的派奖都被拒，正常结算全挂');
        // 上限护栏：任何一次「调参」都能把比率闸静默归零，而功能层面看不出来（照常 success、照常入库）
        self::assertLessThanOrEqual(1000, $multiplier, '倍数 > 1000 :: 投 1 可派 1000+，比率闸等于不存在；改这么大必须是显式决定，别让它静默生效');
    }

    /** 闸 4：单 round 绝对上限必须存在、取值合理，且真的参与比较（比率闸挡不住「每轮按比率拿满」） */
    public function testPerRoundAbsoluteCapIsSaneAndEnforced(): void
    {
        $class = new \ReflectionClass(SelfProvider::class);
        self::assertTrue($class->hasConstant('MAX_PAYOUT_PER_ROUND'), '单 round 绝对上限常量缺失 :: 只有比率闸时，换 round_id 即新额度，铸币总量随轮次无界');

        $cap = $class->getReflectionConstant('MAX_PAYOUT_PER_ROUND')->getValue();
        self::assertIsString($cap, '绝对上限须为字符串常量 :: 整数/浮点进 bccomp 前还得再做一次转换，且 float 违反金额规则');
        self::assertSame(1, preg_match('/^\d+(\.\d+)?$/', $cap), "绝对上限须是十进制数字串 :: 非法串进 bccomp 会抛 ValueError，等于结算整体 500");
        self::assertGreaterThan(0, bccomp($cap, '0', 8), '绝对上限 <= 0 :: 任何派奖都被拒，正常结算全挂');
        self::assertLessThanOrEqual(0, bccomp($cap, '1000000', 8), '绝对上限 > 1000000 :: 单局封顶形同虚设，请显式决定后再放宽');

        self::assertSame(1, preg_match(
            '/bccomp\(\s*\$amount\s*,\s*self::MAX_PAYOUT_PER_ROUND\s*,\s*8\s*\)\s*>\s*0/',
            self::settleSource()
        ), '绝对上限未与派奖额比较 :: 常量是装饰，单局收益仍不封顶');
    }

    /** 闸 3：必须按同 round 的投注额聚合并与上限比较，且聚合值必须是字符串 */
    public function testSettleAggregatesRoundBetAndCapsPayout(): void
    {
        $src = self::settleSource();

        self::assertStringContainsString("->where('action', 'bet')", $src, '未按 action=bet 过滤 :: settle/refund 流水会被算进投注额，上限随之失真');
        self::assertStringContainsString('SUM(bet_amount)', $src, '未聚合同 round 投注额 :: 从没下注的 round 也能派奖');
        self::assertStringContainsString('CAST(', $src, '聚合值须 CAST(... AS CHAR) 返回字符串 :: 直接把驱动返回的类型丢给 bcmath，类型随列类型/驱动配置浮动');
        // 实测（scratch 库真跑）：->sum() 有行返回 string '10.0000'、无行返回 int 0；int 进 bccomp 在 strict_types 下抛 TypeError 变 500。
        // 即「无投注的 round」这条最普通的路径就会崩，而 CAST 恒为 string（无行得 '0.0000'）
        self::assertStringNotContainsString('->sum(', $src, '->sum() 无行返回 int 0 :: strict_types 下进 bccomp 抛 TypeError（实测），空 round 结算直接 500');
        // 取值落地前的类型兜底：驱动/表达式一旦给出非 string（null/int），必须归一成字符串再进 bcmath
        self::assertStringContainsString(
            "is_string(\$betTotal) && \$betTotal !== '' ? \$betTotal : '0'",
            $src,
            '聚合值未做字符串兜底 :: null/int 会直接进 bccomp，strict_types 下抛 TypeError'
        );

        // 上限比较：两者成对出现才算真用了上限（只写常量不比较 = 装饰）
        self::assertSame(1, preg_match(
            '/bccomp\(\s*\$amount\s*,\s*bcmul\(\s*\$\w+\s*,\s*\(string\)\s*self::MAX_PAYOUT_MULTIPLIER/',
            $src
        ), '派奖额未与「投注额 × MAX_PAYOUT_MULTIPLIER」比较 :: 上限是装饰，赔率仍由客户端说了算');
    }

    /** 闸 2：round_id 为空必须拒绝，不能让幂等查询整体跳过 */
    public function testEmptyRoundIdIsRejectedInsteadOfSkippingIdempotency(): void
    {
        $src = self::settleSource();

        self::assertStringNotContainsString(
            "\$roundId !== ''",
            $src,
            "旧的 `\$roundId !== '' &&` 前置条件会让空 round 完全绕过幂等 :: 同一局可无限重放铸币"
        );
        self::assertStringContainsString(
            "if (\$roundId === '') {",
            $src,
            '缺少 round_id 空值拒绝分支 :: 空 round 会被当成合法结算'
        );
        self::assertLessThan(
            strpos($src, 'Db::transaction'),
            strpos($src, "\$roundId === ''"),
            '空 round 的拒绝排在事务/幂等查询之后 :: 幂等仍会被跳过'
        );

        // 幂等本身不得被顺手删掉（重放路径仍要存在，只是不再对空 round 失效）
        self::assertStringContainsString("->whereIn('action', ['settle', 'refund'])", $src, '幂等查询被删 :: 同一 round 可重复结算');
        self::assertStringContainsString("'already_processed' => true", $src, '重复结算的幂等回包被删 :: 重放会走正常入账');
    }

    /** 下注边界：空 round_id 必须在扣款之前拒绝（否则扣了钱却永远结算不了 = 给用户造废数据） */
    public function testBetRejectsEmptyRoundIdBeforeDebit(): void
    {
        $src = self::source(self::SELF_PROVIDER);
        self::assertSame(1, preg_match('/public function bet\(.*?\n    \}/s', $src, $m), '未切出 bet 方法体 :: 切片判据失效');
        $bet = $m[0];

        self::assertStringContainsString(
            "if (\$roundId === '') {",
            $bet,
            'bet 未拒绝空 round_id :: 该笔投注永远无法结算（settle 要求非空且能匹配投注行），等于给用户造废数据'
        );
        self::assertStringContainsString(
            "'round_id required'",
            $bet,
            'bet 的空 round 拒绝未给明确原因 :: 客户端只能看到「失败」，无法定位是参数缺失'
        );
        self::assertLessThan(
            strpos($bet, 'WalletService::mutate'),
            strpos($bet, "\$roundId === ''"),
            'bet 的空 round 校验排在扣款之后 :: 钱已经扣了才拒绝，仍要人工冲正'
        );
    }

    /**
     * 上限策略（闸 7）：refund 与 settle 共用同一条入账路径，但上限必须是「本局投注总额」而不是派奖闸。
     * bet 无下注上限 ⇒ 若退款也被 MAX_PAYOUT_PER_ROUND 卡住，一笔 20000 的合法下注本金永远退不回来。
     */
    public function testRefundCapsAtRoundBetAndIsExemptFromPayoutGates(): void
    {
        $src = self::settleSource();

        // 策略切换必须在共用路径上按入参分支（单点），refund 不得复制一份 settle
        self::assertSame(
            1,
            preg_match('/if \(\$refundPrincipal\) \{(.*?)\n            \} else \{/s', $src, $m),
            '上限策略未按入参分支 :: 退款要么又落回派奖闸（大额本金退不回），要么复制了第二份闸序'
        );
        $refundBranch = $m[1];

        self::assertStringContainsString(
            'bccomp($amount, $betTotal, 8) > 0',
            $refundBranch,
            '退款上限未取本局投注总额 :: 退款额失去上限，或又被派奖闸管住'
        );
        self::assertStringNotContainsString(
            'MAX_PAYOUT',
            $refundBranch,
            '退款分支内出现派奖闸常量 :: 下注 20000 的退款会被绝对闸拒（本金卡死，实测过）'
        );
        self::assertStringContainsString(
            "'Refund exceeds round bet'",
            $refundBranch,
            '退款超限未给明确原因 :: 客户端只看到失败，无法区分「超过本金」与「被派奖闸拦」'
        );

        // 派奖侧两个闸必须仍在（退款豁免不是把派奖闸一起删了）
        self::assertSame(
            1,
            preg_match('/bccomp\(\s*\$amount\s*,\s*self::MAX_PAYOUT_PER_ROUND\s*,\s*8\s*\)\s*>\s*0/', $src),
            '绝对闸被退款改动顺手删掉 :: 派奖重新变成无上限'
        );
        self::assertSame(
            1,
            preg_match('/bccomp\(\s*\$amount\s*,\s*bcmul\(/', $src),
            '比率闸被退款改动顺手删掉 :: 派奖赔率重新由客户端说了算'
        );
        // 退款侧必须显式传策略：默认值是派奖策略，靠默认值等于没改
        self::assertSame(
            1,
            preg_match('/refundPrincipal:\s*true/', self::source(self::SELF_PROVIDER)),
            'refund() 未显式启用「退回本金」策略 :: 默认值仍是派奖闸，大额本金卡死'
        );
    }

    /**
     * 并发重试（裁定）：钱包行不存在时那把「钱包锁」只拿到间隙锁，与隐式建户的 INSERT 意向锁成环 ⇒ 1213。
     * Laravel 原生只在并发错误时重试，所以要显式给尝试次数；**只有最外层那层生效**（嵌套事务的并发错误
     * Laravel 直接抛出、不重试）⇒ provider 层写了也是死代码，判据反过来钉：出现重试次数即判红。
     */
    public function testTransactionRetriesAreConfigured(): void
    {
        $settle = self::settleSource();

        // 反向判据：provider 层不得带重试次数（它在生产里总被外层事务包着，写了也一次不生效）
        self::assertSame(
            0,
            preg_match_all('/^\s*\}, 3\);/m', $settle),
            'SelfProvider::settle 又带上了重试次数 :: 嵌套事务里它一次也不会生效（死代码），重试只准留在最外层 controller'
        );
        self::assertSame(
            1,
            preg_match('/Db::transaction\(function \(\) use \([^)]*\) \{.*?\}\s*\);/s', $settle),
            'SelfProvider::settle 的 Db::transaction 不见了 :: 切片判据失效'
        );

        foreach (
            [
                self::GAME_SDK_CONTROLLER   => 'GameSdkController',
                self::PROVIDER_CONTROLLER   => 'ProviderController',
            ] as $relative => $label
        ) {
            $src = self::source($relative);
            self::assertSame(3, preg_match_all('/Db::transaction\(/', $src), "{$label} 的事务数不是 3 :: 切片判据失效");
            self::assertSame(
                3,
                preg_match_all('/^\s*\}, 3\);/m', $src),
                "{$label} 有事务未配重试次数 :: 生效的是最外层事务，缺一个都会让 1213 冒成 500"
            );
        }
    }

    /**
     * 控制器边界（闸 8）：'abc'/'1e5'/'+-100' 在入口就 fail-loud 422，不是 500；且不得改变 bccomp<=0 的既有语义。
     */
    public function testControllersRejectMalformedAmountBeforeBcmath(): void
    {
        foreach (
            [
                self::GAME_SDK_CONTROLLER   => 'GameSdkController',
                self::PROVIDER_CONTROLLER   => 'ProviderController',
            ] as $relative => $label
        ) {
            $src = self::source($relative);
            self::assertStringContainsString('use app\provider\SelfProvider;', $src, "{$label} 未引入 SelfProvider :: 语法闸调用会解析到本命名空间，运行时 Error");
            self::assertSame(
                3,
                substr_count($src, 'SelfProvider::isAmountSyntaxValid($amount)'),
                "{$label} 未在三个入口（bet/settle/refund）都加语法闸 :: 漏掉的那个入口仍会把客户端原文喂给 bccomp，抛 ValueError 变 500"
            );
            self::assertStringContainsString(
                "return \$this->fail(trans('Invalid amount'), 422);",
                $src,
                "{$label} 的非法金额不是 fail-loud 422 :: 闸形同虚设或仍走 500"
            );

            foreach (['bet', 'refund'] as $method) {
                self::assertSame(1, preg_match('/public function ' . $method . '\(.*?\n    \}/s', $src, $m), "未切出 {$label}::{$method} :: 顺序判据失效");
                $body = $m[0];
                // 既有语义必须原样保留：合法但 <= 0 仍是原来的 Invalid params
                self::assertStringContainsString("bccomp(\$amount, '0', 8) <= 0", $body, "{$label}::{$method} 丢了 bccomp<=0 的既有语义 :: 合法但非正的金额行为被改变");
                self::assertLessThan(
                    strpos($body, "bccomp(\$amount, '0', 8)"),
                    strpos($body, 'SelfProvider::isAmountSyntaxValid($amount)'),
                    "{$label}::{$method} 的语法闸排在 bccomp 之后 :: ValueError 在闸之前就已经抛了，仍是 500"
                );
            }
        }
    }

    /**
     * 语法闸的行为钉子（闸 8 的另一半）：只判语法，不判正负 —— '-1' 必须判「语法合法」，
     * 否则控制器会把它从「原来的 Invalid params」改判成非法金额，等于顺手改了既有语义。
     */
    public function testAmountSyntaxPredicateDistinguishesMalformedFromNonPositive(): void
    {
        self::assertTrue(
            (new \ReflectionClass(SelfProvider::class))->hasMethod('isAmountSyntaxValid'),
            '语法闸方法缺失 :: 两个控制器只能各写一份（第三份实现），判定口径必然漂移'
        );

        // 实测（PHP 8.3.7）：这些原文经 bcadd 抛 ValueError，而控制器原来直接把它们喂给 bccomp ⇒ 500
        foreach (['abc', '1e5', '+-100', '--1', '1,5', '0x10', ' 1'] as $bad) {
            self::assertFalse(
                SelfProvider::isAmountSyntaxValid($bad),
                '非法金额 ' . var_export($bad, true) . ' 被判语法合法 :: 会进 bccomp 抛 ValueError 变 500'
            );
        }
        // 语法合法的取值一律放行（含 '' 与负数）：正负由各自的既有语义处理，语法闸不管
        foreach (['10', '0', '-1', '-0.00000001', '1.5', '5.', '.5', '+5', ''] as $ok) {
            self::assertTrue(
                SelfProvider::isAmountSyntaxValid($ok),
                '语法合法金额 ' . var_export($ok, true) . ' 被判非法 :: 会把原本的 Invalid params / Invalid amount 语义改成 422 非法金额'
            );
        }
        // 非字符串（JSON 数字/数组/null/布尔）一律判非法：接口契约是「精确字符串」，且非字符串进 bcmath 抛 TypeError
        foreach ([10, 1.5, null, ['10'], true] as $notString) {
            self::assertFalse(
                SelfProvider::isAmountSyntaxValid($notString),
                '非字符串金额未判非法 :: strict_types 下进 bcmath 抛 TypeError 变 500'
            );
        }

        // 单点实现：全文件只允许一处 ValueError 捕获（normalizeAmount 必须复用语法闸，不得各catch一份）
        self::assertSame(
            1,
            preg_match_all('/catch \(\\\\ValueError/', self::source(self::SELF_PROVIDER)),
            'ValueError 捕获不止一处 :: 语法判定出现了第二份实现，口径会漂移'
        );
        self::assertStringContainsString(
            'self::isAmountSyntaxValid($amount)',
            self::source(self::SELF_PROVIDER),
            'normalizeAmount 未复用语法闸 :: 单点实现被拆成两份'
        );
    }

    /** 在切片里定位锚点的字节偏移；找不到直接判红——免得 strpos 的 false 被后续顺序断言当成「排在前面」混过去 */
    private static function offset(string $src, string $pattern, string $what): int
    {
        self::assertSame(1, preg_match($pattern, $src, $m, PREG_OFFSET_CAPTURE), "未定位到{$what} :: 顺序判据失效");

        return $m[0][1];
    }

    /** 并发闸：锁序必须是「钱包行 -> 投注行」，且投注行锁读排在幂等查询之前、两把锁都在事务内 */
    public function testBetRowsAreLockedBeforeIdempotencyCheck(): void
    {
        $src = self::settleSource();
        $idempotency = "->whereIn('action', ['settle', 'refund'])";

        self::assertStringContainsString('->lockForUpdate()', $src, '未对同 round 投注行加锁 :: 并发 settle 各自读到「还没结算过」的旧快照，同一注码双倍入账');
        self::assertStringContainsString($idempotency, $src, '幂等查询被删 :: 同一 round 可重复结算');

        $tx = self::offset($src, '/Db::transaction/', '事务起点');
        $walletLock = self::offset($src, '/UserGameWallet::where\(.user_id.,\s*\$userId\)\s*->where\(.game_id.,\s*\$gameId\)\s*->where\(.currency_id.,\s*\$currencyId\)\s*->lockForUpdate\(\)/', '钱包行锁读');
        // 必须锚在「action=bet 紧跟 lockForUpdate」这一处：整切片里 lockForUpdate 有两处，用 strpos 取首个会取到钱包锁，
        // 那样投注行锁读被挪到幂等之后也照样「通过」（本用例自己踩过这个坑）
        $betLock = self::offset($src, '/->where\(.action.,\s*.bet.\)\s*->lockForUpdate\(\)/', '投注行锁读');
        self::assertLessThan($betLock, $tx, '投注行锁读排在事务之外 :: 锁随语句结束即释放，起不到串行化作用');
        self::assertLessThan($walletLock, $tx, '钱包行锁读排在事务之外 :: 锁随语句结束即释放，起不到串行化作用');
        // 锁序：bet 是「先钱包行、后流水」，settle 必须同序，否则 AB-BA 成环 -> MySQL 1213 死锁（两进程实测过）
        self::assertLessThan(
            $betLock,
            $walletLock,
            '钱包行锁读排在投注行锁读之后 :: 与 bet 的锁序相反，真并发下 settle x bet 会 AB-BA 死锁（1213）'
        );
        // 顺序是要害：REPEATABLE READ 的读视图由事务内第一条一致性读建立，锁读（当前读）不建立读视图。
        // 先锁后查，对手提交后这里才建视图，才看得到对手写的 settle 流水；反过来则读视图提前固定，幂等形同虚设。
        self::assertLessThan(
            strpos($src, $idempotency),
            $betLock,
            '投注行锁读排在幂等查询之后 :: 读视图在对手提交前就固定了，幂等看不到对手的 settle 流水，同 round 并发仍双倍入账'
        );
    }

    /** 闸 1：非法金额要落到拒绝分支，不能抛 ValueError 变 500 */
    public function testMalformedAmountIsRejectedBeforeBcmath(): void
    {
        $src = self::settleSource();

        self::assertSame(1, preg_match('/\$amount\s*=\s*\$this->normalizeAmount\(\$amount\)/', $src), 'settle 未先规范化金额 :: 客户端传非法字符串直接进 bcmath');

        // 规范化与拒绝信封都在私有方法里（不在 settle 切片内），按整份源文件核对
        $file = self::source(self::SELF_PROVIDER);
        self::assertStringContainsString('catch (\\ValueError', $file, "非法金额（'abc'/'1e5'/'+-100'）未被捕获 :: bcadd 抛 ValueError 变 500");
        self::assertStringContainsString("bccomp(\$normalized, '0', 8) > 0 ? \$normalized : null", $file, '规范化后未拒绝 <= 0 / 非法值 :: 负数金额会被当成派奖');
        self::assertStringContainsString("'success'        => false", $file, '拒绝路径未按现有失败信封形状返回');
        self::assertStringContainsString("'win_amount'     => '0'", $file, '拒绝信封缺 win_amount=0 :: 与 bet() 的失败分支形状不一致');

        // 拒绝要留痕：controller 只在 success 时写流水，闸挡下的铸币尝试否则对运维完全不可见
        self::assertStringContainsString('Log::warning', $file, '拒绝路径无日志 :: 被闸挡下的铸币尝试不留任何痕迹，攻击探测不可见');
        self::assertStringContainsString("'reason'      => \$error", $file, '拒绝日志未带拒绝原因 :: 只知「被拒」不知「为何被拒」');
        self::assertStringContainsString('substr($amount, 0, 32)', $file, '拒绝日志未截断客户端入参 :: 可用超长 amount 灌日志');
    }

    /** 闸 1 的行为钉子：规范化必须拒非法/非正金额，并把合法值定到与钱包一致的 8 位精度（纯 bcmath，不连库） */
    public function testNormalizeAmountRejectsMalformedAndNonPositiveValues(): void
    {
        $provider = (new \ReflectionClass(SelfProvider::class))->newInstanceWithoutConstructor();
        $normalize = new \ReflectionMethod(SelfProvider::class, 'normalizeAmount');

        foreach (['abc', '1e5', '+-100', '--1', '  1', '1,5', '0', '-0.00000001', ''] as $bad) {
            self::assertNull($normalize->invoke($provider, $bad), "非法/非正金额 " . var_export($bad, true) . ' 未被拒绝 :: bcadd 抛 ValueError 变 500，或负数被当成派奖');
        }
        self::assertSame('1.50000000', $normalize->invoke($provider, '1.5'), '合法金额未规范化到 8 位 :: 与钱包 SCALE 不一致');
    }

    /** 闸 5：密钥为空的游戏必须 fail-closed，且校验要排在签名计算之前 */
    public function testAuthMiddlewaresFailClosedOnEmptyGameSecret(): void
    {
        $guard = "(string) \$game->api_secret === ''";
        $cases = [
            self::PROVIDER_AUTH    => ['ProviderAuth', '$expected = $this->computeSignature('],
            self::SDK_SESSION_AUTH => ['SdkSessionAuth', "\$expected = hash_hmac('sha256'"],
        ];

        foreach ($cases as $relative => [$label, $signNeedle]) {
            self::assertStringContainsString($guard, self::source($relative), "{$label} 缺少空 api_secret 拒绝 :: hash_hmac(..., '') 人人可算，签名校验形同虚设");

            // 只在 process() 切片内比位置：ProviderAuth 的 hash_hmac 在私有方法 computeSignature 里，
            // 拿整份文件比会被那个方法里的出现位置骗过（校验后置也照样"通过"）
            self::assertSame(1, preg_match('/public function process\(.*?\n    \}/s', self::source($relative), $m), "未切出 {$label}::process 方法体 :: 顺序判据失效");
            $process = $m[0];
            self::assertStringContainsString($signNeedle, $process, "{$label}::process 内未找到签名计算行 :: 顺序判据失效");
            self::assertLessThan(
                strpos($process, $signNeedle),
                strpos($process, $guard),
                "{$label} 的空密钥校验排在签名计算之后 :: 等于没校验（密钥仍是空串）"
            );
        }
    }

    /** 会话签发端：密钥为空时给明确失败，而不是签一个永远验不过的令牌；M0 起该端点也不再签写令牌 */
    public function testSessionEndpointRefusesToSignWithEmptySecret(): void
    {
        $session = self::methodBody(self::source(self::GAME_CONTROLLER), 'session', 'GameController::session');

        self::assertStringContainsString("(string) \$game->api_secret === ''", $session, 'session() 未拒绝空密钥游戏 :: 客户端拿到永远验不过的 token，且掩盖了后端未配置');
        // 顺序仍在：空密钥的拒绝必须排在签发之前（签发落点由下面的 M0 用例单独钉）
        self::assertLessThan(
            strpos($session, 'issueReadSessionToken('),
            strpos($session, "\$game->api_secret === ''"),
            'session() 的密钥校验排在签发之后 :: 空密钥仍会签出令牌'
        );
        self::assertStringNotContainsString(
            "hash_hmac('sha256', \$payload",
            $session,
            'session() 又内联签 payload :: 请求者路径上仍能签出游戏服务端令牌'
        );
    }

    /**
     * M0：会话签发端的信任边界 —— 请求者能拿到的令牌只能读，写令牌在签发端就签不出来。
     *
     * 旧写法（原 :227-236）由请求者自己的登录态触发、却用 game.api_secret 签出可写令牌，
     * 而 /api/game/{bet,settle,refund} 只认这枚令牌 ⇒ 任意登录用户自铸写权限。
     */
    public function testSessionEndpointIssuesReadOnlyTokens(): void
    {
        $src = self::source(self::GAME_CONTROLLER);
        $session = self::methodBody($src, 'session', 'GameController::session');

        self::assertStringNotContainsString(
            "hash_hmac('sha256', \$payload",
            $session,
            'session() 仍内联签 payload :: 签发与「请求者是谁」绑在一起，写令牌又可自助领取'
        );
        self::assertSame(1, preg_match('/issueReadSessionToken\(/', $session), 'session() 未走唯一签发落点 :: 签发出现第二处实现，判据锚不住');

        self::assertSame(
            1,
            preg_match('/private function issueReadSessionToken\([^)]*\)\s*:\s*string\s*\{(.*?)\n    \}/s', $src, $h),
            '未切出 issueReadSessionToken 方法体 :: 签发落点判据失效'
        );
        $signer = $h[1];
        self::assertStringContainsString("hash_hmac('sha256', \$payload", $signer, '签发落点内未见签名 :: 判据锚到了别的方法');
        self::assertSame(1, preg_match("/'role'\s*=>\s*'read'/", $signer), "签发落点的 role 不是写死的 'read' :: 请求者路径仍能铸出写令牌");
        // 入参里出现 $role 就等于把角色交回调用方决定，闸只剩在控制器外层摆样子
        self::assertStringNotContainsString('$role', $signer, '签发落点收 role 入参 :: 签发者可以自选角色，等于把信任边界挪出了签发端');
    }

    /** M0：令牌角色必须来自**验签通过后**的 claims，缺省/未知一律降级只读（旧令牌自动 fail-closed） */
    public function testSdkSessionRoleComesFromVerifiedClaims(): void
    {
        $process = self::methodBody(self::source(self::SDK_SESSION_AUTH), 'process', 'SdkSessionAuth');

        self::assertSame(1, preg_match('/\$request->sdkRole\s*=/', $process), 'SdkSessionAuth 未注入 sdkRole :: 写端点的角色闸会恒取到缺省值（或报未定义属性）');
        self::assertSame(1, preg_match("/\\\$claims\['role'\]/", $process), 'sdkRole 未取自 claims.role :: 角色来源不是签名覆盖的那份 claims');
        self::assertSame(1, preg_match("/\\\$claims\['role'\]\s*\?\?\s*'read'/", $process), "claims.role 缺省值不是 'read' :: 旧令牌（M0 前签发，claims 无 role）会落到别的角色");
        // 顺序：claims 在验签前就已解出来，角色必须在验签之后才注入，否则未签名/伪造令牌也能决定角色
        self::assertGreaterThan(
            self::offset($process, '/hash_equals\(/', '签名比对'),
            self::offset($process, '/\$request->sdkRole\s*=/', 'sdkRole 注入'),
            'sdkRole 在验签之前注入 :: 伪造 claims 的角色也生效'
        );
    }

    /** M0：三个写端点必须按 role=server 放行，且判据要排在建 provider 之前（否则闸只是摆设，钱已经动了） */
    public function testSdkWriteEndpointsRequireServerRoleBeforeProvider(): void
    {
        $src = self::source(self::GAME_SDK_CONTROLLER);

        foreach (['bet', 'settle', 'refund'] as $method) {
            $body = self::methodBody($src, $method, "GameSdkController::{$method}");
            $gate = self::offset($body, "/\\\$request->sdkRole\s*!==\s*'server'/", "{$method} 的角色判据");
            $create = self::offset($body, '/ProviderFactory::create\(/', "{$method} 的 provider 构建");

            self::assertLessThan(
                $create,
                $gate,
                "{$method} 的角色判据排在 ProviderFactory::create 之后 :: 闸只挡在回包上，余额/流水已经动过"
            );
            self::assertSame(
                1,
                preg_match("/\\\$request->sdkRole\s*!==\s*'server'\)\s*\{\s*return \\\$this->fail\([^;]*?,\s*403\);/s", $body),
                "{$method} 的角色判据没有落到 403 :: 非服务端令牌要么放行、要么被打成 5xx"
            );
        }

        // 读端点（balance）不得被同一道写闸误伤：只读令牌连余额都读不到的话，客户端连自检都做不了
        self::assertStringNotContainsString('sdkRole', self::methodBody($src, 'balance', 'GameSdkController::balance'), '读端点被写闸误伤 :: 只读令牌读不到余额');
    }

    /** 真实路由表上的中间件链（RouteObject 挂着注册时声明的那串）；未命中直接判红，免得假红混过去 */
    private static function routeMiddleware(string $method, string $path): array
    {
        $info = Route::dispatch($method, $path);
        self::assertSame(Dispatcher::FOUND, $info[0], "{$method} {$path} 未命中路由 :: 路由没接线");

        return array_map(
            static fn ($m) => is_string($m) ? $m : get_class($m),
            $info[1]['route']->getMiddleware()
        );
    }

    /**
     * M1（闸 10）：服务端令牌签发端的信任边界钉在**路由中间件**上，不在控制器里、也不在注释里。
     *
     * M1 的全部安全性 = 「换得到 role=server 令牌」≡「持有 game.api_secret」，而这只由 ProviderAuth 保证。
     * 端点一旦挪出这个 group（公开组 / UserAuth / SdkSessionAuth），匿名或任意登录用户就能换到写令牌。
     */
    public function testServerTokenIssuerIsWiredUnderProviderAuth(): void
    {
        $path = '/api/provider/session-token';
        $middlewares = self::routeMiddleware('POST', $path);

        self::assertContains(ProviderAuth::class, $middlewares, "POST {$path} 不在 ProviderAuth 下 :: 匿名即可换到 role=server 令牌，M0 的角色闸当场作废");
        self::assertNotContains(UserAuth::class, $middlewares, "POST {$path} 挂了 UserAuth :: 请求者路径又能自助领写令牌，正是 M0 修掉的那条路");
        self::assertNotContains(SdkSessionAuth::class, $middlewares, "POST {$path} 挂了 SdkSessionAuth :: 拿只读令牌换写令牌，写权的边界塌成闭环");

        // 反向：不得同时出现在无鉴权的 /api/v1 公开组
        self::assertSame(
            Dispatcher::NOT_FOUND,
            Route::dispatch('POST', '/api/v1' . $path)[0],
            "POST /api/v1{$path} 也存在 :: 公开路径上同样换得到令牌"
        );

        // 写端点的链路没被顺手改动：三个写端点必须仍走 SdkSessionAuth（sdkRole 由它注入）
        foreach (['/api/game/bet', '/api/game/settle', '/api/game/refund'] as $writePath) {
            self::assertContains(
                SdkSessionAuth::class,
                self::routeMiddleware('POST', $writePath),
                "POST {$writePath} 缺 SdkSessionAuth :: sdkRole 无从注入，写端点的角色闸退化成恒 read"
            );
        }
    }

    /**
     * M1：签发端的 claims 形状。四个字段各有理由，写歪任一个都静默失效：
     *   role=server -> 过写端点的角色闸（写死，不给入参）
     *   user_id     -> SdkSessionAuth 把「claims 缺 user_id」判成 401 Invalid token claims，请求根本到不了控制器（实测）。
     *                  且写端点的 user_id 只取自会话 ⇒ 令牌与「哪个用户」绑定，比 /api/provider/* 的请求体语义更紧
     *   game_id     -> 必须取**验签通过的那个 game**，不能取请求体，否则可跨 game 铸令牌
     *   exp         -> 统一走 TTL 常量（见下方 TTL 用例）
     */
    public function testServerTokenIssuerClaimsShape(): void
    {
        $body = self::methodBody(self::source(self::PROVIDER_CONTROLLER), 'sessionToken', 'ProviderController::sessionToken');

        self::assertSame(1, preg_match("/\\\$request->input\('user_id'/", $body), '签发端未从请求取 user_id :: 令牌无从绑定到某个用户');
        self::assertSame(1, preg_match("/'user_id'\s*=>\s*\\\$userId/", $body), 'claims 里没写 user_id :: SdkSessionAuth 一律判 401 Invalid token claims（实测，请求根本到不了控制器），三个写端点永远进不去');
        self::assertSame(1, preg_match("/'role'\s*=>\s*'server'/", $body), "签发端的 role 没写死成 'server' :: 换出来的令牌过不了写端点的角色闸");
        self::assertSame(1, preg_match("/'game_id'\s*=>\s*\(int\) \\\$game->id/", $body), '签发端的 game_id 不是取自验签通过的 $game :: 令牌的 game 归属可被请求体指定');
        self::assertSame(1, preg_match("/'exp'\s*=>\s*time\(\)\s*\+\s*self::SERVER_TOKEN_TTL/", $body), '签发端未统一走 TTL 常量 :: 写令牌有效期失控且无从核对');
        self::assertSame(1, preg_match("/hash_hmac\('sha256', \\\$payload, \(string\) \\\$game->api_secret\)/", $body), '签发端未用 game.api_secret 签名 :: 与 SdkSessionAuth 的校验口径不一致，令牌验不过');
    }

    /** M1：写令牌 TTL 必须短于读令牌（GameController::issueReadSessionToken 的 300s）—— 写令牌能移钱，暴露窗口不该更长 */
    public function testServerTokenTtlIsShorterThanReadToken(): void
    {
        $class = new \ReflectionClass(ProviderController::class);
        self::assertTrue($class->hasConstant('SERVER_TOKEN_TTL'), '写令牌 TTL 常量缺失 :: 有效期散落在签发行里，无从调参也无从核对');

        $ttl = $class->getReflectionConstant('SERVER_TOKEN_TTL')->getValue();
        self::assertIsInt($ttl, 'TTL 须为整数秒 :: 非整数进 time() 前还得再做一次类型转换');
        self::assertGreaterThan(0, $ttl, 'TTL <= 0 :: 签出来的令牌立即过期，写端点全挂');
        self::assertLessThan(300, $ttl, '写令牌 TTL >= 读令牌的 300s :: 写令牌能移钱，暴露窗口反而比只读令牌更长');
    }
}
