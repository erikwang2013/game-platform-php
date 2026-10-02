<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\ExportController;
use app\model\AdminUser;
use common\SnowflakeService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

/**
 * Excel 导出里「列字母自增」在 Z→AA 边界的正确性。
 *
 * 旧写法是 `$colIndex++`；PHP 8.5 起「非数字串自增」已弃用（PHP 9 移除），
 * 换成换算函数后最容易改坏的就是这个边界：`chr(ord('Z') + 1)` 得到的是 '['，不是 'AA'。
 *
 * 列数正好取 27：A..Y 是表里不存在的填充列，第 26 列 Z 放 username、第 27 列 AA 放 real_name
 * （都取真实数据）。表头循环与数据行循环两处的自增因此都被覆盖到。
 *
 * 变异读数：把 nextColumn 的换算换成 `chr(ord($col) + 1)` ⇒ 只本用例红；
 * 其余导出用例列数 ≤ 9、不跨 Z，不受影响。
 */
final class ExportColumnLetterTest extends TestCase
{
    /** 放在第 26/27 列的真实列，跨 Z 那一步才有真数据可断言 */
    private const REAL_COLUMNS = ['username', 'real_name'];

    /** A..Y 共 25 个填充列，让真实列正好落在 Z 与 AA 上 */
    private const PADDING = 25;

    private ?int $adminId = null;
    private string $username = '';

    /** @var string[] 用例产出的导出文件，tearDown 清掉 */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        try {
            $database = (string) Db::selectOne('SELECT DATABASE() AS d')->d;
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL 不可用（跳过真库集成测试）：' . $e->getMessage());

            return;
        }
        if (stripos($database, 'test') === false) {
            $this->fail("拒绝在非测试库 `{$database}` 上执行写操作（库名必须含 test）");
        }

        $this->username = 'export_col_' . bin2hex(random_bytes(5));
        $user = new AdminUser();
        $user->id        = SnowflakeService::generate();
        $user->username  = $this->username;
        $user->password  = password_hash('Probe1234', PASSWORD_BCRYPT);
        $user->real_name = 'export_col_probe';
        $user->status    = 1;
        $user->save();
        $this->adminId = (int) $user->id;
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tmpFiles = [];

        if ($this->adminId !== null) {
            try {
                AdminUser::withTrashed()->where('id', $this->adminId)->forceDelete();
            } catch (\Throwable) {
            }
            $this->adminId = null;
        }
    }

    #[Test]
    public function columnLettersCrossZToAaInHeaderAndDataRows(): void
    {
        $columns = [];
        for ($i = 1; $i <= self::PADDING; $i++) {
            $columns[] = 'pad' . $i;
        }
        $columns = array_merge($columns, self::REAL_COLUMNS);

        $encoded = json_encode(
            ['table' => 'admin_user', 'columns' => $columns, 'title' => 'probe'],
            JSON_UNESCAPED_UNICODE
        );
        $request = new Request(
            "POST /admin/v1/export/excel HTTP/1.1\r\nHost: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($encoded) . "\r\n\r\n" . $encoded
        );
        $response = (new ExportController())->excel($request);

        // 响应是 response()->download() ⇒ 正文走文件流，产物落在 runtime/tmp
        $disposition = (string) $response->getHeader('Content-Disposition');
        $this->assertSame(1, preg_match('/filename="([^"]+)"/', $disposition, $m), '响应不是下载形状：' . $disposition);
        $path = runtime_path() . '/tmp/' . $m[1];
        $this->tmpFiles[] = $path;
        $this->assertFileExists($path, '响应说在下载，产物却不存在');

        $sheet = IOFactory::load($path)->getActiveSheet();

        // 表头循环：第 26 列落在 Z、第 27 列落在 AA（写错时这里是空的，或直接抛异常）
        $this->assertSame('pad1', (string) $sheet->getCell('A1')->getValue(), '第 1 列表头不在 A');
        $this->assertSame('pad25', (string) $sheet->getCell('Y1')->getValue(), '第 25 列表头不在 Y');
        $this->assertSame(trans('Username'), (string) $sheet->getCell('Z1')->getValue(), '第 26 列表头不在 Z');
        $this->assertSame(trans('Real name'), (string) $sheet->getCell('AA1')->getValue(), '第 27 列表头不在 AA');

        // 数据行循环：同一行里 Z 放 username、AA 放 real_name
        $row = $this->rowWithUsername($sheet, $this->username);
        $this->assertSame($this->username, (string) $sheet->getCell('Z' . $row)->getValue(), 'Z 列不是 username');
        $this->assertSame('export_col_probe', (string) $sheet->getCell('AA' . $row)->getValue(), 'AA 列不是 real_name');
    }

    /** 在 Z 列按用户名找探针那一行的行号（找不到即 fail）。 */
    private function rowWithUsername(Worksheet $sheet, string $username): int
    {
        $highestRow = $sheet->getHighestDataRow();
        for ($row = 2; $row <= $highestRow; $row++) {
            if ((string) $sheet->getCell('Z' . $row)->getValue() === $username) {
                return $row;
            }
        }
        $this->fail("导出产物里在 Z 列找不到探针管理员 {$username}（最高行 {$highestRow}）");
    }
}
