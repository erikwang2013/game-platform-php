<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * 回归护栏：app/ 目录自身的结构完整性（纯文件系统判据，不连 DB/Redis，不依赖 autoloader）。
 *
 * 两个真实缺陷各对应一条断言：
 * ① 4f83f2f 删掉 admin/app/event/EventBus.php，WalletService.php:283 的 EventBus::emit
 *    调用即抛 Class not found（风控冻结接口恒失败），而 CI 全树 php -l 与 admin 套件全绿；
 * ② decba5f 抽走了 admin/app/activity/ActivityHandlerFactory.php 首行 `<?php`，文件变成
 *    「纯文本」，include 时只把源码吐进输出流、类永不定义，php -l 同样报「无语法错误」。
 * 两条缺陷的共同点＝文件在、名字对、但类根本加载不了，只能靠「文件必须可解析 + import 必须落地」兜住。
 */
class AppNamespaceIntegrityTest extends TestCase
{
    /** @return string[] 相对 app/ 的路径 */
    private function phpFiles(string $root): array
    {
        $out = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[] = substr($f->getPathname(), strlen($root) + 1);
            }
        }
        sort($out);

        return $out;
    }

    public function testEveryPhpFileStartsWithOpenTag(): void
    {
        $root = dirname(__DIR__) . '/app';
        $bad = [];
        foreach ($this->phpFiles($root) as $rel) {
            $head = ltrim((string) file_get_contents($root . '/' . $rel, false, null, 0, 8), "\xEF\xBB\xBF \t\r\n");
            if (!str_starts_with($head, '<?php')) {
                $bad[] = $rel;
            }
        }
        $this->assertCount(0, $bad, "以下文件缺 <?php 开标签（内容不会被当 PHP 解析）：\n" . implode("\n", $bad));
    }

    public function testEveryAppNamespaceImportResolvesToAFile(): void
    {
        $root = dirname(__DIR__) . '/app';
        $bad = [];
        foreach ($this->phpFiles($root) as $rel) {
            $src = (string) file_get_contents($root . '/' . $rel);
            if (!preg_match_all('/^use\s+(app\\\\[A-Za-z0-9_\\\\]+)\s*;/m', $src, $m)) {
                continue;
            }
            foreach ($m[1] as $fqcn) {
                $target = $root . '/' . str_replace('\\', '/', substr($fqcn, 4)) . '.php';
                if (!is_file($target)) {
                    $bad[] = "$rel :: $fqcn :: missing $target";
                }
            }
        }
        $this->assertCount(0, $bad, "以下 import 指向不存在的类文件：\n" . implode("\n", $bad));
    }
}
