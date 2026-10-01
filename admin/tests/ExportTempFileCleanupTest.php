<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\ExportController;
use app\process\ExportTmpCleanup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use Throwable;
use Workerman\Connection\TcpConnection;

/**
 * 导出临时产物**不再越积越多**的两条机制。
 *
 * 缺陷形状：ExportController 的 5 个导出端点都把产物写进 runtime/tmp/ 再
 * `response()->download()`，下载完之后**没有任何一处 unlink**。实测盘上积了
 * 85 个文件 / 916K（2026-08-27 → 09-16）。
 *
 * 钉子分三段：
 *  ① **下载后清理**：产物在响应交出去时**必须还在**（提前 unlink 会把下载变成 0 字节），
 *     而连接关闭时必须被删掉；且原有的 onClose 钩子不能被覆盖掉；
 *  ② **时间兜底**：ExportTmpCleanup::sweepDir 只删「超龄 + 本控制器产物」——
 *     新鲜的不动、`runtime/tmp` 里别的文件（非 export_/receipt_ 前缀）不动；
 *  ③ **接线**：兜底扫的就是 runtime/tmp（写错目录 ⇒ 机制在跑但永远删不到东西，
 *     这种"绿着的空转"靠 ② 那类行为用例抓不到）。
 *
 * ⚠ 本文件**不会**对真的 runtime/tmp 跑 sweepDir：盘上那 85 个历史文件一次就会被清空，
 * 而它们是不是谁的证据还没定（见交付说明）。②用的是自建临时目录。
 *
 * 变异读数：删掉 downloadTemp 里的 onClose 注册 ⇒ ①红；sweepDir 去掉 ARTIFACT_PATTERN
 * 判断 ⇒ ②红；dir() 改成别的目录 ⇒ ③红。
 */
final class ExportTempFileCleanupTest extends TestCase
{
    /** @var string[] 用例产出的产物，tearDown 清掉 */
    private array $tmpFiles = [];

    /** @var string[] 自建的扫描目录 */
    private array $dirs = [];

    protected function setUp(): void
    {
        try {
            $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        } catch (Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());

            return;
        }
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tmpFiles = [];

        foreach ($this->dirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        $this->dirs = [];
    }

    // ============================================================
    // ① 下载后清理
    // ============================================================

    #[Test]
    public function connectionCloseRemovesTheArtifactAfterItWasServed(): void
    {
        $connection = new class extends TcpConnection {
            public function __construct()
            {
            }
        };

        // 预置一个别人的 onClose：实现若直接覆盖，这条断言会红
        $previousCalled = false;
        $connection->onClose = static function () use (&$previousCalled): void {
            $previousCalled = true;
        };

        $path = $this->productOf($this->export('admin_role', $connection));

        $this->assertFileExists($path, '响应已经交出去了，产物必须还在——提前 unlink 会把下载变成 0 字节');
        $this->assertIsCallable($connection->onClose, '没在连接关闭时挂清理钩子');

        // 模拟 TcpConnection::destroy() 的派发（vendor/.../TcpConnection.php:1177-1180）
        ($connection->onClose)($connection);

        $this->assertFileDoesNotExist($path, '连接关了，产物还在盘上');
        $this->assertTrue($previousCalled, '把原本挂在连接上的 onClose 覆盖掉了');
    }

    /** 单测/CLI 形状：`$request->connection` 为 null，既不能炸，也不能提前删（下载还没发生） */
    #[Test]
    public function nullConnectionStillDownloadsAndKeepsTheArtifact(): void
    {
        $path = $this->productOf($this->export('system_config', null));

        $this->assertFileExists($path, 'CLI 形状下产物必须留着（真正删它的是兜底进程）');
    }

    // ============================================================
    // ② 时间兜底：只删「超龄 + 自己的产物」
    // ============================================================

    #[Test]
    public function sweepDirRemovesOnlyStaleArtifacts(): void
    {
        $dir = $this->makeDir();
        $old = time() - ExportTmpCleanup::MAX_AGE - 600;
        $new = time() - 60;

        $staleXlsx   = $this->put($dir, 'export_users_20260827192051.xlsx', $old);
        $stalePdf    = $this->put($dir, 'receipt_ORD123.pdf', $old);
        $freshXlsx   = $this->put($dir, 'export_admin_user_20990101000000.xlsx', $new);
        $otherPrefix = $this->put($dir, 'backup_2026.xlsx', $old);      // 不是导出产物
        $plainText   = $this->put($dir, 'export_notes.txt', $old);      // 后缀不对
        $noExtension = $this->put($dir, 'export_users_20260827192051', $old);

        $deleted = ExportTmpCleanup::sweepDir($dir, ExportTmpCleanup::MAX_AGE);

        $this->assertSame(2, $deleted, '删除个数不对（多删＝碰到别人的文件，少删＝兜底失效）');
        $this->assertFileDoesNotExist($staleXlsx);
        $this->assertFileDoesNotExist($stalePdf);
        $this->assertFileExists($freshXlsx, '没超龄的产物不能删');
        $this->assertFileExists($otherPrefix, 'runtime/tmp 里非导出产物不能碰');
        $this->assertFileExists($plainText);
        $this->assertFileExists($noExtension);
    }

    // ============================================================
    // ③ 接线：兜底扫的是 ExportController 写文件的那个目录
    // ============================================================

    #[Test]
    public function sweeperPointsAtTheSameDirectoryTheControllerWritesTo(): void
    {
        $this->assertSame(runtime_path() . '/tmp', ExportTmpCleanup::dir());

        $path = $this->productOf($this->export('admin_role', null));
        $this->assertSame(ExportTmpCleanup::dir(), dirname($path), '产物落在别处 ⇒ 兜底扫了个寂寞');
    }

    // ============================================================ 工具

    /** @return string 产物绝对路径 */
    private function productOf(\support\Response $response): string
    {
        $disposition = (string) $response->getHeader('Content-Disposition');
        $this->assertSame(1, preg_match('/filename="([^"]+)"/', $disposition, $m), '响应不是下载形状：' . $disposition);

        $path = ExportTmpCleanup::dir() . '/' . $m[1];
        $this->tmpFiles[] = $path;

        return $path;
    }

    /** 跑一次真导出；$connection 为 null 时即 CLI/单测形状 */
    private function export(string $table, ?TcpConnection $connection): \support\Response
    {
        $payload = json_encode(['table' => $table, 'title' => 'probe'], JSON_UNESCAPED_UNICODE);
        $request = new Request(
            "POST /admin/v1/export/excel HTTP/1.1\r\nHost: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($payload) . "\r\n\r\n" . $payload
        );
        $request->connection = $connection;

        return (new ExportController())->excel($request);
    }

    private function makeDir(): string
    {
        $dir = sys_get_temp_dir() . '/export_cleanup_' . bin2hex(random_bytes(5));
        mkdir($dir, 0755, true);
        $this->dirs[] = $dir;

        return $dir;
    }

    private function put(string $dir, string $name, int $mtime): string
    {
        $path = $dir . '/' . $name;
        file_put_contents($path, 'probe');
        touch($path, $mtime);

        return $path;
    }
}
