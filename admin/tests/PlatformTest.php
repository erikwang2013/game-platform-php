<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\BaseController;
use app\admin\v1\controller\GameController;
use app\admin\v1\controller\UserController;
use common\BcMath;
use common\model\Game;
use common\model\PlatformConfig;
use common\service\TranslationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Request;

/**
 * 平台核心业务逻辑测试
 *
 * 只允许测试「本项目的生产代码」：凡断言字面量等于自己、在测试里重算一遍公式、
 * 或直接测 PHP 内建函数（bcadd/preg_match/max/json_decode）的用例，已在本轮清理中
 * 删除或改指真实生产类——它们给出的是假绿：把生产实现整段改坏也不会变红。
 *
 * 无法在本文件测试的领域（兑换两侧发生额、提现手续费、KYC 流转、订单号）其真实实现
 * 位于 service 树，admin 测试无法加载；兑换侧已由 service/tests/ExchangeControllerLegsTest.php
 * 用反射直接钉住生产方法，提现手续费与其余各项仍是覆盖缺口。
 */
class PlatformTest extends TestCase
{
    /** 本文件写入 game_platform_config 的独立命名空间，避免踩到真实配置 */
    private const CFG_GROUP = 'phpunit_platform_test';

    protected function tearDown(): void
    {
        try {
            PlatformConfig::where('group', self::CFG_GROUP)->delete();
        } catch (\Throwable) {
            // 数据库不可用时无需清理
        }
        parent::tearDown();
    }

    /**
     * 仅在数据库确实不可达时跳过；探针之外的异常一律上抛，
     * 避免把实现缺陷伪装成 skipped 蒙混过关。
     */
    private function requireDb(): void
    {
        try {
            (new PlatformConfig())->getConnection()->select('SELECT 1');
        } catch (\Throwable) {
            $this->markTestSkipped('Database connection not configured in test environment');
        }
    }

    /** 写一条测试配置行。PlatformConfig 主键非自增，id 必须显式给。 */
    private function putConfig(string $key, string $value, string $type): void
    {
        PlatformConfig::where('group', self::CFG_GROUP)->where('key', $key)->delete();

        $config = new PlatformConfig();
        $config->id          = crc32(self::CFG_GROUP . ':' . $key);
        $config->group       = self::CFG_GROUP;
        $config->key         = $key;
        $config->value       = $value;
        $config->type        = $type;
        $config->description = 'phpunit';
        $config->save();
    }

    // ============================================================
    // 1. 平台配置测试
    // ============================================================

    #[Test]
    public function platformConfigGetReturnsDefaultWhenNotFound(): void
    {
        $this->requireDb();

        // 原先这里是 catch (\Throwable) → markTestSkipped：任何真实缺陷（表结构、SQL、
        // 连接串）都会被伪装成 skipped 蒙混过关，等于这条用例永不失败。改为只对连接探针跳过。
        $this->assertSame(
            'default_value',
            PlatformConfig::get(self::CFG_GROUP, 'definitely_absent_key', 'default_value')
        );
    }

    #[Test]
    public function platformConfigGetCastsBoolType(): void
    {
        $this->requireDb();

        $this->putConfig('cast_bool', '1', 'bool');
        $bool = PlatformConfig::get(self::CFG_GROUP, 'cast_bool');
        // assertIsBool 不是摆设：删掉 get() 的 'bool' 分支会落回 default 返回字符串 '1'，
        // 而 assertTrue('1') 照样通过——只有钉住类型才拦得住。
        $this->assertIsBool($bool);
        $this->assertTrue($bool);

        $this->putConfig('cast_bool', '0', 'bool');
        $this->assertSame(false, PlatformConfig::get(self::CFG_GROUP, 'cast_bool'));
    }

    #[Test]
    public function platformConfigGetCastsIntType(): void
    {
        $this->requireDb();

        $this->putConfig('cast_int', '123', 'int');
        $this->assertSame(123, PlatformConfig::get(self::CFG_GROUP, 'cast_int'));
    }

    #[Test]
    public function platformConfigGetCastsJsonType(): void
    {
        $this->requireDb();

        $this->putConfig('cast_json', '{"key":"value","n":2}', 'json');
        $this->assertSame(
            ['key' => 'value', 'n' => 2],
            PlatformConfig::get(self::CFG_GROUP, 'cast_json')
        );
    }

    #[Test]
    public function platformConfigGetCastsDecimalType(): void
    {
        $this->requireDb();

        // decimal 必须原样返回字符串：一旦在读取点被 (float) 规范化，后续 bcmath 全是浮点残值
        $this->putConfig('cast_decimal', '100.5000', 'decimal');
        $this->assertSame('100.5000', PlatformConfig::get(self::CFG_GROUP, 'cast_decimal'));
    }

    // ============================================================
    // 2. 金额运算（common\BcMath）
    //    原生 bcadd/bcmul/bcdiv 是截断不进位，项目统一经 BcMath::round 半进位、
    //    百分比统一走 BcMath::percent（见 CLAUDE.md 金额运算规范）。
    //    下面钉的是本项目的舍入/百分比契约，不是 PHP 扩展本身。
    // ============================================================

    #[Test]
    public function bcMathRoundRoundsHalfUpAtScale(): void
    {
        $this->assertSame('1.01', BcMath::round('1.005', 2));
        $this->assertSame('2.34', BcMath::round('2.3449', 2));
    }

    #[Test]
    public function bcMathRoundIsNotTruncation(): void
    {
        // 同一输入：原生 bcadd 截断得 '1.00'，BcMath::round 半进位得 '1.01'。
        // 这正是本类存在的理由——少了它，分档手续费的末位会系统性少收。
        $this->assertSame('1.00', bcadd('1.005', '0', 2));
        $this->assertSame('1.01', BcMath::round('1.005', 2));
    }

    #[Test]
    public function bcMathRoundNegativeGoesAwayFromZero(): void
    {
        $this->assertSame('-1.01', BcMath::round('-1.005', 2));
        $this->assertSame('-3', BcMath::round('-2.5', 0));
    }

    #[Test]
    public function bcMathRoundCarriesAtScaleZero(): void
    {
        $this->assertSame('3', BcMath::round('2.5', 0));
        $this->assertSame('2', BcMath::round('1.999', 0));
    }

    #[Test]
    public function bcMathPercentIsRatioTimesHundred(): void
    {
        $this->assertSame('50.00', BcMath::percent('1', '2'));
        $this->assertSame('5.00', BcMath::percent('50', '1000'));
    }

    #[Test]
    public function bcMathPercentRoundsHalfUp(): void
    {
        // 1/3 = 33.3333… → 33.33；2/3 = 66.6666… → 66.67
        $this->assertSame('33.33', BcMath::percent('1', '3'));
        $this->assertSame('66.67', BcMath::percent('2', '3'));
    }

    // ============================================================
    // 3~7. 兑换/提现费用/限额/层级/订单号 —— 已整体删除
    //
    // 这些用例断言的是「测试里自己写的一遍公式 + 自造的字面量数组」，一行生产代码都不碰：
    // 把 ExchangeController::exchangeLegs 或提现手续费上限整段改坏，它们照样全绿。
    // 真实实现位于 service 树（exchangeLegs 为 private static，手续费为
    // WithdrawController::withdraw 内联代码），admin 测试无法加载，故无法在此处改指生产类。
    // 兑换侧无损失：service/tests/ExchangeControllerLegsTest.php 已用反射直接钉住该方法。
    // 提现手续费/限额/层级限额/订单号仍是覆盖缺口（见本轮报告）。
    // ============================================================

    // ============================================================
    // 8. 国际化测试
    // ============================================================

    #[Test]
    public function translationServiceReturnsKeyWhenNoTranslation(): void
    {
        $result = TranslationService::trans('nonexistent.group.key');
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function translationServiceSetAndGetLocale(): void
    {
        TranslationService::setLocale('zh-CN');
        $this->assertSame('zh-CN', TranslationService::getLocale());

        TranslationService::setLocale('en-US');
        $this->assertSame('en-US', TranslationService::getLocale());
    }

    #[Test]
    public function availableLanguagesHasFourEntries(): void
    {
        $langs = TranslationService::getAvailableLanguages();
        $this->assertCount(4, $langs);
        $this->assertArrayHasKey('en-US', $langs);
        $this->assertArrayHasKey('zh-CN', $langs);
        $this->assertArrayHasKey('ja-JP', $langs);
        $this->assertArrayHasKey('ko-KR', $langs);
    }

    // ============================================================
    // 9. 管理端入参校验（走真实校验器）
    //
    // 旧用例在测试里自己写正则再 preg_match，测的是 PHP 的 preg_match；
    // 这里改为把请求喂给真实控制器，由生产 validator 规则判定。
    // ============================================================

    private static function post(string $uri, array $data): Request
    {
        // 必须给完整原始报文：两参形式 new Request('POST', $uri) 不会构造出可解析的
        // get()/post() 数据源，$request->all() 会在 Workerman 解析层抛 TypeError。
        $request = new Request("POST {$uri} HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost($data);

        return $request;
    }

    private static function bodyOf(\support\Response $response): array
    {
        return json_decode($response->rawBody(), true) ?? [];
    }

    #[Test]
    public function userStoreRejectsTooShortUsername(): void
    {
        // 生产规则: username => required|string|min:3|max:50
        $body = self::bodyOf((new UserController())->store(self::post('/admin/v1/user', [
            'username'  => 'ab',
            'password'  => 'Abcdefg1',
            'real_name' => '测试',
        ])));

        $this->assertSame(422, $body['code']);
    }

    #[Test]
    public function userStoreRejectsWeakPassword(): void
    {
        // 生产规则: password => min:8|max:32|regex 大小写字母+数字。
        // 旧用例断言「strlen('123456') >= 6」即视为合格，把门槛写低两档且丢了复杂度要求。
        // 'Abcdef1' 是关键样本：7 位且复杂度达标，只由 min:8 拦下——
        // 少了它，把 min:8 改成 min:6 会被复杂度规则掩盖而不报错。
        $base = ['username' => 'phpunit_user', 'real_name' => '测试'];

        foreach ([
            'Abcdef1'  => '7 位但复杂度达标（只由 min:8 拦下）',
            '1234567'  => '仅 7 位',
            'abcdefgh' => '无大写无数字',
            'Abcdefgh' => '无数字',
            'abcdefg1' => '无大写',
        ] as $weak => $why) {
            $body = self::bodyOf((new UserController())->store(
                self::post('/admin/v1/user', $base + ['password' => $weak])
            ));
            $this->assertSame(422, $body['code'], "弱密码应被拒（{$why}）: {$weak}");
        }
    }

    #[Test]
    public function gameCreateRejectsUnknownType(): void
    {
        // 生产规则: type => required|string|in:self,embedded,third_party
        $body = self::bodyOf((new GameController())->create(self::post('/admin/v1/game/create', [
            'name' => '测试游戏',
            'slug' => 'phpunit-game-' . uniqid(),
            'type' => 'invalid',
        ])));

        $this->assertSame(422, $body['code']);
    }

    #[Test]
    public function gameCreateRejectsUppercaseSlug(): void
    {
        // 生产规则: slug => required|string|max:50|regex:/^[a-z0-9_-]+$/
        $body = self::bodyOf((new GameController())->create(self::post('/admin/v1/game/create', [
            'name' => '测试游戏',
            'slug' => 'My-Game',
            'type' => 'self',
        ])));

        $this->assertSame(422, $body['code']);
    }

    #[Test]
    public function gameCreateAcceptsEmbeddedType(): void
    {
        $this->requireDb();

        $slug = 'phpunit-game-' . uniqid();
        $body = self::bodyOf((new GameController())->create(self::post('/admin/v1/game/create', [
            'name' => '测试内嵌游戏',
            'slug' => $slug,
            'type' => 'embedded',
        ])));

        try {
            // 旧用例的白名单写作 ['self','third_party']，漏掉 embedded，与生产 in: 规则不符；
            // 这条钉住真值白名单，删掉 embedded 即变红。
            $this->assertSame(0, $body['code'], 'embedded 必须是合法游戏类型');
            $this->assertSame('embedded', Game::where('slug', $slug)->value('type'));
        } finally {
            Game::where('slug', $slug)->delete();
        }
    }

    // ============================================================
    // 10~15. 枚举/状态流转/乐观锁/优惠券/会话号/分页 —— 已整体删除
    //
    // 共同点是：断言对象是测试内自造的字面量数组或自增变量，没有一行生产代码参与，
    // 因此无论生产实现怎么改都不会变红。例如:
    //   optimisticLockRetryCount 断言局部变量 $maxRetries=5，注释称
    //     「UserWallet::addBalance 最多重试5次」——而该方法实际委托 WalletService::mutate，
    //     全仓并无任何重试循环，「重试 5 次」这个前提本身已不存在。
    //   validGameTypes / validWithdrawStatuses 等自造白名单，与真值漂移也不会报错。
    // 真实枚举与流转逻辑在 service 树（IdentityController 的 KYC 流转、CouponController），
    // admin 测试无法加载；这是覆盖缺口，已记入本轮报告。
    // ============================================================

    // ============================================================
    // 16. 响应信封（BaseController::success / fail）
    //
    // 旧用例断言的是测试里自己 new 出来的 ['code'=>401,...] 数组，等于断言字面量等于自己。
    // 这里改为调用真实 BaseController，钉住信封的键集合与取值语义。
    // 401/403/404 是 fail() 的透传参数，其真实产生点在中间件与各端点，
    // 已由 GameControllerTest（403 禁用游戏 / 404 未知游戏）等端点级用例覆盖。
    // ============================================================

    /** @param array<int, mixed> $args */
    private static function envelope(string $method, array $args): array
    {
        $reflection = new \ReflectionMethod(BaseController::class, $method);
        $reflection->setAccessible(true);

        return self::bodyOf($reflection->invoke(new UserController(), ...$args));
    }

    #[Test]
    public function responseEnvelopeIsCodeMessageData(): void
    {
        $ok = self::envelope('success', [[], 'success', 0]);
        $this->assertSame(['code', 'message', 'data'], array_keys($ok));
        $this->assertSame(0, $ok['code']);
        $this->assertSame('success', $ok['message']);
        $this->assertSame([], $ok['data']);

        $err = self::envelope('fail', ['验证失败', 422, []]);
        $this->assertSame(['code', 'message', 'data'], array_keys($err));
        $this->assertSame(422, $err['code']);
        $this->assertSame('验证失败', $err['message']);
        $this->assertSame([], $err['data']);
    }
}
