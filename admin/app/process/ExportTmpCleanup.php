<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use support\Log;
use Throwable;
use Workerman\Timer;

/**
 * 导出临时文件清理 —— **兜底**。
 *
 * 缺陷形状：ExportController 的 5 个导出端点都把产物写进 runtime/tmp/ 再
 * `response()->download()`，而**下载完之后从来没有谁删过它**。实测盘上积了
 * 85 个文件 / 916K，时间跨度 2026-08-27 → 09-16（全部由本控制器产出）。
 *
 * 正常路径的删除在 ExportController::downloadTemp（连接关闭时 unlink），本进程管的是
 * 那条路径覆盖不到的情况：worker 被杀、连接一直 keep-alive 不关、进程重启时钩子丢失。
 * 所以这里是**按时间兜底**，而不是主路径。
 *
 * 只删本控制器写得出的产物（`export_*` / `receipt_*` 的 xlsx/pdf），
 * runtime/tmp 里的其它文件一律不碰 —— 目录以后可能被别的功能复用。
 *
 * ponytail: 保留期 1 小时，远大于任何一次下载的耗时；若将来出现 GB 级导出 + 慢客户端
 *           超过 1 小时的场景，调大 MAX_AGE 或改成按"最近访问时间"（atime）判定。
 */
class ExportTmpCleanup
{
    /** 扫描间隔（秒）：30 分钟，同 RiskIpCron 的节奏 */
    private const CHECK_INTERVAL = 1800;

    /** 保留期（秒）：1 小时 */
    public const MAX_AGE = 3600;

    /** 产物文件名：ExportController 的 5 个写入点全部落在这个形状里 */
    private const ARTIFACT_PATTERN = '/^(export_|receipt_).*\.(xlsx|pdf)$/';

    public function onWorkerStart(): void
    {
        Log::info('ExportTmpCleanup started (sweep runtime/tmp every 30min, keep 1h)');

        // 与 RiskIpCron 同一处置：onWorkerStart 必须尽快返回，用 Timer 而不是 while+sleep，
        // 否则 workerman 的 graceful stop（信号派发在 onWorkerStart 返回之后才装）会失效。
        // 首轮立即扫一次：进程刚起来时盘上可能已经堆了一批（比如上次是 SIGKILL 走的）。
        Timer::add(1, [$this, 'sweep'], [], false);
        Timer::add(self::CHECK_INTERVAL, [$this, 'sweep']);
    }

    public function sweep(): void
    {
        try {
            $deleted = self::sweepDir(self::dir(), self::MAX_AGE);
            if ($deleted > 0) {
                Log::info("ExportTmpCleanup: removed {$deleted} stale export artifact(s)");
            }
        } catch (Throwable $e) {
            Log::error('ExportTmpCleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * 删掉 $dir 下超过 $maxAge 秒的导出产物，返回删除个数。
     *
     * 目录与保留期都做成参数，只为一件事：测试要能指向一个临时目录。
     * **不要**拿 runtime/tmp 真跑一遍来"验证" —— 盘上那 85 个历史文件一次就会被清空，
     * 而它们是不是谁的证据还没定（见交付说明里的"存量还在"）。
     *
     * @return int 实际删掉的个数
     */
    public static function sweepDir(string $dir, int $maxAge): int
    {
        $now     = time();
        $deleted = 0;

        foreach (glob($dir . '/*') ?: [] as $file) {
            if (!is_file($file) || !preg_match(self::ARTIFACT_PATTERN, basename($file))) {
                continue;
            }

            $mtime = filemtime($file);
            // mtime 读不到（并发被删）就当没过期，跳过即可，不值得为它抛错
            if ($mtime === false || $now - $mtime < $maxAge) {
                continue;
            }

            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /** 扫描目录：与 ExportController 的写入点同一个 runtime_path()/tmp */
    public static function dir(): string
    {
        return runtime_path() . '/tmp';
    }
}
