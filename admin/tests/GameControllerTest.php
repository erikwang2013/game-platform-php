<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\GameController;
use common\HashidsService;
use common\model\Game;
use common\model\GameCurrency;
use common\model\GamePlayLog;
use common\SnowflakeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Request;
use support\Response;

/**
 * 管理端游戏控制器：详情与试玩预览
 *
 * 管理端 HarmonyOS 客户端的游戏大厅/详情两屏调用 /admin/v1/game/*，
 * 这两个端点补在管理端，因此必须走管理端身份（adminId）。
 */
class GameControllerTest extends TestCase
{
    /** @var int[] */
    private array $gameIds = [];
    /** @var int[] */
    private array $currencyIds = [];

    protected function tearDown(): void
    {
        try {
            if ($this->currencyIds) {
                GameCurrency::whereIn('id', $this->currencyIds)->delete();
            }
            if ($this->gameIds) {
                Game::whereIn('id', $this->gameIds)->delete();
            }
        } catch (\Throwable) {
            // 数据库不可用时无需清理
        }
        parent::tearDown();
    }

    /**
     * 仅在数据库确实不可达时跳过；探针之外的异常一律上抛，
     * 避免把实现缺陷伪装成 skipped 蒙混过关。
     *
     * 用模型自身的连接探针就够：开发库/测试库的隔离由 tests/bootstrap.php 统一负责——
     * 那里抢先 include 了 webman 的 Initializer，把它文件尾部那次 init(开发库配置)
     * 提前消耗掉，此后谁 autoload support\Db 都只是空转。故这里探到的就是 game-platform-test。
     */
    private function requireDb(): void
    {
        try {
            (new Game())->getConnection()->select('SELECT 1');
        } catch (\Throwable) {
            $this->markTestSkipped('Database connection not configured in test environment');
        }
    }

    private function makeGame(int $status = 1): Game
    {
        $game = new Game();
        $game->id           = SnowflakeService::generate();
        $game->name         = '测试游戏';
        $game->slug         = 'test-game-' . uniqid();
        $game->type         = 'self';
        $game->description  = '仅测试用';
        $game->cover_image  = 'https://example.test/cover.png';
        $game->api_endpoint = 'https://example.test/api';
        $game->api_key      = '';
        $game->api_secret   = '';
        $game->status       = $status;
        $game->sort         = 0;
        $game->sdk_version  = '1.0.0';
        $game->platform     = 'h5';
        $game->region       = 'global';
        $game->save();
        $this->gameIds[] = $game->id;

        return $game;
    }

    private function makeCurrency(int $gameId): GameCurrency
    {
        $currency = new GameCurrency();
        $currency->id            = SnowflakeService::generate();
        $currency->game_id       = $gameId;
        $currency->name          = '测试币';
        $currency->symbol        = 'TC';
        $currency->exchange_rate = '1.00000000';
        $currency->spread_pct    = '0.00000000';
        $currency->min_exchange  = '0.00000000';
        $currency->max_exchange  = '0.00000000';
        $currency->save();
        $this->currencyIds[] = $currency->id;

        return $currency;
    }

    private function json(Response $response): array
    {
        return json_decode($response->rawBody(), true);
    }

    #[Test]
    public function detailReturnsGameWithCurrencies(): void
    {
        $this->requireDb();

        $game     = $this->makeGame();
        $currency = $this->makeCurrency($game->id);
        $hashid   = HashidsService::encode($game->id);

        $body = $this->json((new GameController())->detail(new Request('GET', '/admin/v1/game/' . $hashid), $hashid));

        $this->assertSame(0, $body['code']);
        $this->assertSame($hashid, $body['data']['id']);
        $this->assertSame('测试游戏', $body['data']['name']);
        $this->assertSame($game->slug, $body['data']['slug']);
        $this->assertSame('https://example.test/cover.png', $body['data']['cover_image']);
        // 客户端 GameDetailPage 靠 currencies 渲染"1 平台币 = x 游戏币"，缺了这屏就是空的
        $this->assertCount(1, $body['data']['currencies']);
        $this->assertSame(HashidsService::encode($currency->id), $body['data']['currencies'][0]['id']);
        $this->assertSame('TC', $body['data']['currencies'][0]['symbol']);
    }

    #[Test]
    public function detailReturns404ForUnknownGame(): void
    {
        $this->requireDb();

        $hashid = HashidsService::encode(999999999999);
        $body   = $this->json((new GameController())->detail(new Request('GET', '/admin/v1/game/' . $hashid), $hashid));

        $this->assertSame(404, $body['code']);
    }

    #[Test]
    public function launchReturnsPreviewAndWritesNoPlayLog(): void
    {
        $this->requireDb();

        $game   = $this->makeGame();
        $before = GamePlayLog::count();

        $request = new Request("POST /admin/v1/game/launch HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost(['game_id' => HashidsService::encode($game->id)]);
        $body = $this->json((new GameController())->launch($request));

        $this->assertSame(0, $body['code']);
        $this->assertTrue($body['data']['preview']);
        $this->assertSame($game->id, HashidsService::decode($body['data']['id']));
        $this->assertSame('测试游戏', $body['data']['name']);

        // 核心语义：管理端身份只有 adminId，没有 C 端 userId。
        // 若照搬 C 端 launch，会用 adminId 当 user_id 写 game_play_log，
        // 产生归属错误的游玩记录——管理端必须是纯预览，零副作用。
        $this->assertSame($before, GamePlayLog::count());
    }

    #[Test]
    public function launchRejectsMissingGameId(): void
    {
        $request = new Request("POST /admin/v1/game/launch HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost([]);
        $body = $this->json((new GameController())->launch($request));

        $this->assertSame(422, $body['code']);
    }

    #[Test]
    public function launchRejectsDisabledGame(): void
    {
        $this->requireDb();

        $game = $this->makeGame(0);

        $request = new Request("POST /admin/v1/game/launch HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost(['game_id' => HashidsService::encode($game->id)]);
        $body = $this->json((new GameController())->launch($request));

        $this->assertSame(403, $body['code']);
    }
}
