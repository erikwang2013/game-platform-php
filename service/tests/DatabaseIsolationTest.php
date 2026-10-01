<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use common\model\WithdrawOrder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * tests/bootstrap.php 的数据库隔离**必须**成立：整套用例只许落在测试库上。
 *
 * 为什么值得单开一条钉子：这条隔离在 2026-10-02 之前**一直是假的**，而且是**静默**假 ——
 * 用例照跑、断言照绿，只是读写的是开发库。链条：bootstrap 只改了 $dbConfig 这个局部副本
 * （Webman\Config 没有 set()，碰不到 config('database')），而 support\Db 是**懒加载**的，
 * 第一个碰它的用例发生在 bootstrap **之后**，那时 Initializer.php 文件尾那句裸露的
 * `Initializer::init(config('database', []))` 才执行，用**未改写**的开发库配置再
 * setAsGlobal() 一次 ⇒ 谁最后谁是赢家，上面建好的测试库 Capsule 白建。
 * 修法＝抢在测试库 Capsule **之前** include 掉 Initializer.php，让开发库那次 init 先烧掉
 * 一次性 $initialized 守卫（见 bootstrap 里那段注释）。
 *
 * ⚠ 判据**必须开新进程**：本进程里全局解析器早被前面某个调过 bootTargetDatabase() 的用例类
 * 钉在测试库上了，即使 bootstrap 的处理顺序被破坏，进程内读也照样是测试库 —— 那条钉子就会
 * 在**它最该红的时候绿**。顺序相关的缺陷只有"从零开始的进程"看得见（这也是本类不调
 * bootTargetDatabase() 的原因：调了就等于自己把被测的那件事做掉了）。
 */
class DatabaseIsolationTest extends TestCase
{
    /** 开发库名：取自**未改写**的 config('database')，不硬编码 */
    private static function devDatabaseName(): string
    {
        $conf = config('database');

        return (string) $conf['connections'][$conf['default']]['database'];
    }

    /** bootstrap 承诺的落点（与它用同一个表达式，不另立第二真值源） */
    private static function expectedTestDatabaseName(): string
    {
        return getenv('DB_DATABASE_TEST') ?: 'game-platform-test';
    }

    /**
     * 从零起的进程里，bootstrap 之后再触碰 support\Db，解析到的必须是测试库。
     *
     * 这条就是「把 require_once 挪到 Capsule 之后」那个变异的杀手：挪了之后开发库的 init
     * 在 bootstrap 内就落笔，子进程读回来是 game-platform ⇒ 这里红。
     */
    #[Test]
    public function bootstrapLeavesAFreshProcessOnTheTestDatabase(): void
    {
        $bootstrap = dirname(__DIR__) . '/tests/bootstrap.php';
        $code = 'require ' . var_export($bootstrap, true) . ';'
            . ' echo "\nDBNAME=" . support\Db::connection()->getDatabaseName();';

        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1', $out, $status);

        $this->assertSame(0, $status, "子进程引导失败：\n" . implode("\n", $out));

        $resolved = null;
        foreach ($out as $line) {
            if (str_starts_with($line, 'DBNAME=')) {
                $resolved = substr($line, 7);
            }
        }
        $this->assertNotNull($resolved, "子进程没打出 DBNAME，读不到库名：\n" . implode("\n", $out));

        $this->assertSame(self::expectedTestDatabaseName(), $resolved,
            'bootstrap 之后首次触碰 support\Db 时解析到的不是测试库 ⇒ Initializer 那次 init(开发库) 赢了，'
            . '说明处理顺序被破坏（那条 require_once 必须在测试库 Capsule **之前**）。'
            . "实际：{$resolved}");
    }

    /**
     * 全量跑里也成立的那条：本进程的门面与模型两条解析路径都落在测试库。
     *
     * 与上一条互补 —— 上一条钉 bootstrap 的**顺序**（开新进程），这条钉本进程当前**落点**
     * （用例读写的就是它，落错库＝直接污染开发数据）。
     */
    #[Test]
    public function liveConnectionAndModelBothResolveToTheTestDatabase(): void
    {
        $resolved = Db::connection()->getDatabaseName();

        $this->assertSame(self::expectedTestDatabaseName(), $resolved,
            "Db 门面解析到的不是测试库，整套用例都在读写错误的库。实际：{$resolved}");
        $this->assertNotSame(self::devDatabaseName(), $resolved,
            "Db 门面解析到了开发库 `{$resolved}` —— 连库用例（含清理逻辑）会直接写开发数据");

        $modelResolved = (new WithdrawOrder())->getConnection()->getDatabaseName();
        $this->assertSame($resolved, $modelResolved,
            "Db 门面与 Eloquent 模型解析到了不同的库：门面 `{$resolved}` / 模型 `{$modelResolved}`");
    }
}
