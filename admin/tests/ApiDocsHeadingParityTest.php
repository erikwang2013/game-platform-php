<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 13 语言文档族的章号一致性钉子（2026-10-01）。
 *
 * 钉的就是「改族只改一半」：admin/docs/API*.md = zh 基线 + 12 份译文，正文各语言不同，
 * 但 `### N.M` 的编号序列必须逐份相同。删/移/加一节时漏改任何一份，这条立刻红。
 * 只比编号不比译名（译名本来就该不同）。这族此前没有任何观察者 —— 少改一份没人会发现。
 */
class ApiDocsHeadingParityTest extends TestCase
{
    #[Test]
    public function docsFamilyHeadingNumbersAreIdenticalAcrossLanguages(): void
    {
        $files = glob(__DIR__ . '/../docs/API*.md') ?: [];
        sort($files);
        $this->assertCount(13, $files, 'admin/docs/API*.md 文档族应为 13 份（zh 基线 + 12 译）');

        $baseName = basename($files[0]);
        $base = $this->headingNumbers($files[0]);
        $this->assertNotEmpty($base, $baseName . ' 里一个 ### N.M 标题都没读到');

        foreach (array_slice($files, 1) as $file) {
            $this->assertSame(
                $base,
                $this->headingNumbers($file),
                basename($file) . ' 的 ### 章号序列与 ' . $baseName . ' 不一致 —— 改族只改了一半'
            );
        }
    }

    /** @return string[] */
    private function headingNumbers(string $file): array
    {
        preg_match_all('/^### (\d+\.\d+)/m', (string) file_get_contents($file), $m);

        return $m[1];
    }
}
