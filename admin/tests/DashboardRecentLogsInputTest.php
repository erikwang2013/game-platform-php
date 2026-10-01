<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\DashboardController;
use common\SnowflakeService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Redis;
use support\Request;

/**
 * 仪表盘摘要的 `recent_logs` **不得**回吐 `operation_log.input`。
 *
 * `input` 是原始请求参数。DDL 注释写着"敏感字段已脱敏"，但脱敏词表
 * （`OperationLog::SENSITIVE_WORDS`：password/token/secret/…）只覆盖密文那一类，
 * **phone / email / real_name 一个都不在里头**；写入侧新增的 PII 脱敏只作用于**新行**，
 * 存量行里的明文照旧 ⇒ 只要这一列还从摘要端点出去，那条读取路径当下就仍然成立。
 * 而 `/admin/v1/dashboard` 只需要低权的 `get.admin/dashboard` slug。
 *
 * 为什么不在模型上挂 `$hidden`：审计页 `/admin/v1/log` **要**这一列，
 * `admin/apps/react/src/pages/logs.tsx:33` 是它唯一的渲染方 —— 挂 `$hidden` 会把审计页打掉。
 * 所以最小改法就是 `getRecentLogs()` 里那一行 `unset`。
 *
 * ⚠ 本用例**必须自己播种**：测试库的 `game_operation_log` 实测是 **0 行**，
 * 直接断言"列表里没有 input"会因为列表为空而**恒真**（假绿）。下面两条前提探针
 * 分别钉住「播种行确实带 input」与「播种行确实进了 recent_logs」，缺一条本用例就失去对象。
 */
class DashboardRecentLogsInputTest extends TestCase
{
    /** 探测用明文手机号：既进播种行，也进断言消息，便于诊断 */
    private const PROBE_PHONE = '13800000000';

    private ?int $logId = null;
    private string $probePath = '';

    protected function setUp(): void
    {
        try {
            Db::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('数据库不可用：无法播种 operation_log 行，断言会恒真');
        }

        $this->dropCache();

        $this->probePath = '/probe/dashboard-input-' . bin2hex(random_bytes(6));
        $this->logId = (int) SnowflakeService::generate();

        Db::table('operation_log')->insert([
            'id'         => $this->logId,
            'user_id'    => 0,
            'action'     => 'probe.dashboard.input',
            'method'     => 'POST',
            'path'       => $this->probePath,
            'ip'         => '127.0.0.1',
            'source'     => 'web',
            'input'      => json_encode([
                'phone' => self::PROBE_PHONE,
                'email' => 'probe@example.com',
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->logId !== null) {
            try {
                Db::table('operation_log')->where('id', $this->logId)->delete();
            } catch (\Throwable) {
            }
        }
        $this->dropCache();

        parent::tearDown();
    }

    #[Test]
    public function dashboardRecentLogsDoNotEmitRawRequestInput(): void
    {
        // 前提探针 1：播种行确实带明文 input（证明这一列真存在、真读得回来）
        $row = Db::table('operation_log')->where('id', $this->logId)->first();
        $this->assertNotNull($row, '前提：播种的 operation_log 行应当存在');
        $this->assertStringContainsString(self::PROBE_PHONE, (string) ($row->input ?? ''),
            '前提：播种行的 input 应含明文手机号，否则本用例没测到目标数据');

        $payload = json_decode((new DashboardController())->index($this->request())->rawBody(), true);
        $logs = $payload['data']['recent_logs'] ?? [];
        $this->assertIsArray($logs, '前提：dashboard 响应里应有 recent_logs 数组');

        // 前提探针 2：播种行确实进了 recent_logs（按 path 认领；否则下面的断言是恒真式）
        $mine = null;
        foreach ($logs as $entry) {
            if (($entry['path'] ?? null) === $this->probePath) {
                $mine = $entry;
                break;
            }
        }
        $this->assertNotNull($mine,
            '前提：播种行应出现在 recent_logs 里（它是 id 最大的那条），否则本用例没有对象');

        $this->assertArrayNotHasKey('input', $mine,
            'recent_logs 仍在回吐原始请求参数 —— 持有低权 get.admin/dashboard 的人可读到存量行里的明文 phone/email');

        // 兜底：整个列表一条都不许带，防"只漏了播种那条之外的"
        foreach ($logs as $entry) {
            $this->assertArrayNotHasKey('input', $entry,
                'recent_logs 里仍有条目带 input（path=' . ($entry['path'] ?? '?') . '）');
        }
    }

    #[Test]
    public function auditLogEndpointStillCarriesInput(): void
    {
        // 反向对照：掐的只是摘要端点。若有人图省事改成模型 $hidden，
        // 审计页那一列会一起消失，而上面那条断言照样是绿的 —— 这条就是拿来抓它的。
        $model = new \app\model\OperationLog();
        $this->assertNotContains('input', $model->getHidden(),
            'input 被挂进了模型 $hidden —— 那会连审计页 /admin/v1/log 的列一起打掉，'
            . '正确改法是只在 getRecentLogs() 里 unset');

        $row = \app\model\OperationLog::find($this->logId);
        $this->assertNotNull($row, '前提：模型应能读回播种行');
        $this->assertArrayHasKey('input', $row->toArray(),
            '审计页读的就是模型的 toArray()，input 必须还在');
    }

    private function request(): Request
    {
        return new Request("GET /admin/v1/dashboard HTTP/1.1\r\nHost: localhost\r\n\r\n");
    }

    /** index() 命中缓存就完全不查库、也不重算 recent_logs ⇒ 先捅掉本 locale 的键 */
    private function dropCache(): void
    {
        try {
            Redis::del('dashboard:data:' . locale());
        } catch (\Throwable) {
        }
    }
}
