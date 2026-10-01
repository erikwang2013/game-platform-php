<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\DashboardController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Redis;
use support\Request;

/**
 * 仪表盘缓存键必须**按 locale 分桶**。
 *
 * `DashboardController::index()` 缓存进 Redis 的 payload 里有 9 处 trans()，而 locale 是
 * 进程级静态的（LanguageMiddleware 每请求 locale() 一次）。键写成常量 `dashboard:data` 时，
 * 300 秒 TTL 内**首个请求者的语言**会决定所有管理员的仪表盘文案：先来一个英文请求，
 * 之后的中文管理员也读英文。
 *
 * 判据取「预置两份不同 locale 的缓存载荷，各自必须读回自己那份」——
 * 这样**不碰数据库**（命中缓存即 return），而缺陷的行为面恰好就是这个：
 * 常量键会让两种语言都读第一份载荷（或都读不到而回落查库）。
 *
 * 同批的 `ReportController:46/:111` 缓存的是纯数值行、没有 trans()，键**不该**带 locale，
 * 别照本用例去改它。
 */
class DashboardCacheLocaleTest extends TestCase
{
    private const KEY_ZH = 'dashboard:data:zh';
    private const KEY_EN = 'dashboard:data:en';

    private ?string $originalLocale = null;

    protected function setUp(): void
    {
        $this->originalLocale = locale();

        try {
            Redis::ping();
        } catch (\Throwable) {
            $this->markTestSkipped('Redis 不可用：本用例的判据依赖真实的缓存读写，跳过而不是假装通过');
        }
    }

    protected function tearDown(): void
    {
        if ($this->originalLocale !== null) {
            locale($this->originalLocale);
        }
        try {
            Redis::del(self::KEY_ZH, self::KEY_EN);
        } catch (\Throwable) {
        }

        parent::tearDown();
    }

    #[Test]
    public function eachLocaleReadsBackItsOwnCachedPayload(): void
    {
        $payloadZh = ['stats' => [['label' => '用户总数', 'value' => '7']], 'probe' => 'zh'];
        $payloadEn = ['stats' => [['label' => 'Total users', 'value' => '7']], 'probe' => 'en'];

        Redis::setex(self::KEY_ZH, 300, json_encode($payloadZh, JSON_UNESCAPED_UNICODE));
        Redis::setex(self::KEY_EN, 300, json_encode($payloadEn, JSON_UNESCAPED_UNICODE));

        // 前提探针：两份载荷必须真的不同，否则下面的断言恒真 —— 假绿
        $this->assertNotSame($payloadZh, $payloadEn);

        $this->assertSame('zh', $this->dashboardProbe('zh'),
            'zh 请求没读回 zh 的缓存载荷 —— 缓存键很可能没按 locale 分桶');
        $this->assertSame('en', $this->dashboardProbe('en'),
            'en 请求没读回 en 的缓存载荷 —— 缓存键很可能没按 locale 分桶');
    }

    /** 在指定 locale 下发一次 GET /admin/v1/dashboard，取回缓存载荷里的探针值 */
    private function dashboardProbe(string $locale): mixed
    {
        locale($locale);
        $request = new Request("GET /admin/v1/dashboard HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $body = json_decode((new DashboardController())->index($request)->rawBody(), true);

        return $body['data']['probe'] ?? null;
    }
}
