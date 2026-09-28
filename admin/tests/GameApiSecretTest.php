<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\GameController;
use common\HashidsService;
use common\model\Game;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use support\Response;

/**
 * 游戏 api_secret 的生成与保命规则（管理端写入路径）
 *
 * 背景：install.sql 里 api_secret 的列默认值是空串，而空密钥下
 * hash_hmac('sha256', $str, '') 是任何人都能算的 —— service 侧的
 * ProviderAuth（第三方回调）与 SdkSessionAuth（自研 SDK 令牌）会双双形同虚设，
 * ProviderController 的 user_id 又取自请求体，等于未登录者可给任意用户铸币。
 * service 侧已对空密钥 fail-closed 401，因此这两条写入规则是它的配套：
 * 新建必须产出密钥，编辑不得把正在运行的密钥清空。
 *
 * 三条规则各对应一条断言：自研/内嵌自动生成、第三方不代生成、空入参不覆盖非空。
 * 真库用例（不是恒 skip）：库不可达时按 GameControllerTest 同款探针跳过，
 * 但本机与 CI 的 admin 套件都在真库上跑，读数为 0 skip。
 */
class GameApiSecretTest extends TestCase
{
    /** @var int[] 本用例自建、tearDown 自清的游戏 ID */
    private array $gameIds = [];

    protected function tearDown(): void
    {
        try {
            if ($this->gameIds) {
                Game::whereIn('id', $this->gameIds)->delete();
            }
        } catch (\Throwable) {
            // 数据库不可用时无需清理
        }
        parent::tearDown();
    }

    /**
     * 仅在数据库确实不可达时跳过；探针之外的异常一律上抛，不把实现缺陷伪装成 skipped。
     */
    private function requireDb(): void
    {
        try {
            (new Game())->getConnection()->select('SELECT 1');
        } catch (\Throwable) {
            $this->markTestSkipped('Database connection not configured in test environment');
        }
    }

    private function post(array $post): Request
    {
        $request = new Request("POST /admin/v1/game HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost($post);

        return $request;
    }

    private function json(Response $response): array
    {
        return json_decode($response->rawBody(), true);
    }

    /**
     * 走真实控制器新建游戏，返回 [自增前的主键, 响应体]。
     */
    private function createGame(string $type, array $extra = []): array
    {
        $body = $this->json((new GameController())->create($this->post(array_merge([
            'name' => '密钥探针游戏',
            'slug' => 'secret-probe-' . uniqid(),
            'type' => $type,
        ], $extra))));

        $this->assertSame(0, $body['code'] ?? null, '创建应成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));
        $id = HashidsService::decode($body['data']['id']);
        $this->gameIds[] = $id;

        return [$id, $body];
    }

    private function updateGame(int $id, array $post): void
    {
        $body = $this->json((new GameController())->update($this->post($post), HashidsService::encode($id)));
        $this->assertSame(0, $body['code'] ?? null, '更新应成功：' . json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    #[Test]
    public function createGeneratesSecretForSelfAndEmbedded(): void
    {
        $this->requireDb();

        foreach (['self', 'embedded'] as $type) {
            [$id] = $this->createGame($type);

            $secret = (string) Game::find($id)->api_secret;
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{64}$/',
                $secret,
                "type={$type} 在未提供密钥时应自动生成 64 位十六进制密钥，实际为 " . var_export($secret, true)
            );

            // 生成的是 64 位 hex 还不够：必须确认落库时经 Encryptable cast 加密，
            // 而不是把明文原样写进列里（列注释承诺「加密存储」）。
            $raw = (string) Db::table('game')->where('id', $id)->value('api_secret');
            $this->assertNotSame('', $raw, "type={$type} 库内原始列值不应为空");
            $this->assertNotSame($secret, $raw, "type={$type} 库内必须是密文，不能与明文相同");
        }
    }

    #[Test]
    public function createKeepsCallerSuppliedSecretForSelfGame(): void
    {
        $this->requireDb();

        [$id] = $this->createGame('self', ['api_secret' => 'operator-chosen-secret']);
        $this->assertSame('operator-chosen-secret', (string) Game::find($id)->api_secret);
    }

    #[Test]
    public function createDoesNotGenerateSecretForThirdParty(): void
    {
        $this->requireDb();

        // 第三方密钥由对方签发，平台不能代生成（代生成会让平台侧的签名与对方对不上）
        [$id] = $this->createGame('third_party');
        $this->assertSame('', (string) Game::find($id)->api_secret);

        // 显式传入时必须原样保留
        [$id2] = $this->createGame('third_party', ['api_secret' => 'provider-issued-xyz']);
        $this->assertSame('provider-issued-xyz', (string) Game::find($id2)->api_secret);
    }

    #[Test]
    public function updateWithEmptySecretDoesNotClearExistingOne(): void
    {
        $this->requireDb();

        // 最要命的场景：线上第三方游戏正在用这个密钥对接，管理员编辑一次表单
        // （该字段是 hidden、回显不出来，提交时正好是空串）就把对接打断了。
        [$id] = $this->createGame('third_party', ['api_secret' => 'live-provider-key']);

        $this->updateGame($id, ['api_secret' => '', 'description' => '改了简介']);

        $game = Game::find($id);
        $this->assertSame('live-provider-key', (string) $game->api_secret, '空串入参不得清空已有密钥');
        $this->assertSame('改了简介', (string) $game->description, '同一请求里其它字段仍须正常更新');
    }

    #[Test]
    public function updateWithOmittedSecretFieldDoesNotClearExistingOne(): void
    {
        $this->requireDb();

        // 判据来源：support\Request::only() 用 array_key_exists，字段不在请求里就不进 $data，
        // 因此「完全不传」这条路径本就不会清空。钉住它，防止有人改成「总是带上默认空值」。
        [$id] = $this->createGame('self');

        $secret = (string) Game::find($id)->api_secret;
        $this->updateGame($id, ['name' => '只改名字']);

        $game = Game::find($id);
        $this->assertSame($secret, (string) $game->api_secret, '未传该字段时不得改动密钥');
        $this->assertSame('只改名字', (string) $game->name);
    }

    #[Test]
    public function updateStillAllowsExplicitRotation(): void
    {
        $this->requireDb();

        // 反向护栏：不覆盖规则只针对「空值」，显式传新密钥必须能轮换，
        // 否则会把「不静默清空」写成「永远改不了」。
        [$id] = $this->createGame('self');

        $this->updateGame($id, ['api_secret' => 'rotated-secret-abc']);
        $this->assertSame('rotated-secret-abc', (string) Game::find($id)->api_secret);

        $this->updateGame($id, ['api_secret' => 'rotated-secret-def']);
        $this->assertSame('rotated-secret-def', (string) Game::find($id)->api_secret, '再次轮换同样应生效');
    }

    #[Test]
    public function updateSwitchingTypeToSelfGeneratesMissingSecret(): void
    {
        $this->requireDb();

        // 第三方游戏的密钥允许为空（由对方签发），可一旦 PUT 成 self/embedded，这条空密钥
        // 就成了一条「永远用不了」的自研游戏：service 侧 ProviderAuth/SdkSessionAuth 对空密钥
        // fail-closed 401，令牌与回调签名都发不出来，事后也没有任何路径能让它自愈。
        // 判据必须取**落库后**的 type —— 「不拿空值覆盖」那条 guard 只看当前库里的值，
        // 正好漏掉「本次请求把 type 改掉」这一步。
        [$id] = $this->createGame('third_party');
        $this->assertSame('', (string) Game::find($id)->api_secret, '前置：第三方空密钥应允许为空');

        // 编辑表单该字段是 hidden、回显为空，提交时正好带一个空串
        $this->updateGame($id, ['type' => 'self', 'api_secret' => '']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            (string) Game::find($id)->api_secret,
            'third_party 空密钥改成 self 后必须补生成密钥'
        );

        // 另一条入口：请求里根本不带 api_secret 字段
        [$id2] = $this->createGame('third_party');
        $this->updateGame($id2, ['type' => 'embedded']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            (string) Game::find($id2)->api_secret,
            'third_party 空密钥改成 embedded（未传该字段）同样必须补生成'
        );

        // 边界（防止修过头）：改类型时密钥非空就原样保留，不能被重新生成覆盖掉
        [$id3] = $this->createGame('third_party', ['api_secret' => 'provider-issued-abc']);
        $this->updateGame($id3, ['type' => 'self']);
        $this->assertSame(
            'provider-issued-abc',
            (string) Game::find($id3)->api_secret,
            '改类型不是轮换：非空密钥必须原样保留'
        );
    }
}
