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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;
use support\Response;

/**
 * POST /admin/v1/export/excel 对管理员联系方式（phone/email）的脱敏。
 *
 * 旧实现拿到 `AdminUser::get()->toArray()` 的行之后，对敏感列又跑了一次
 * `EncryptionService::decrypt()` —— 但 phone/email/id_card 是 **Encryptable cast**
 * （app/model/AdminUser.php:37-39），Eloquent 取值那一刻**已经解过密**，toArray() 拿到的就是明文。
 * 对明文再解一次 ⇒ `EncryptionException: Invalid ciphertext prefix for AES-256-CBC`。
 * 空值会走 EncryptionService::decrypt 的 early-return，所以**只在有值时炸**：
 * 表里只要有任意一个管理员填过手机号或邮箱，这个端点就必 500。
 *
 * 触发面正是**默认参数**：`table` 默认 admin_user、`columns` 默认取全部、
 * phone/email 都在 sensitiveFields 里 —— 前端什么都不传就是这个形状。
 *
 * 本文件钉三件事：
 *  1. 默认参数（不带 columns）下有联系方式的管理员存在 ⇒ 导出**成功**（旧代码在这里抛异常）；
 *  2. 手机号/邮箱在产物里是**掩码**，不是明文，也不是密文；
 *  3. 掩码形状就是 EncryptionService 那两个函数的口径（掩错位置 = 等于没脱敏）。
 *
 * 变异读数：把 decrypt 那段加回去 ⇒ 第 1 条红（EncryptionException）。
 */
final class ExportAdminUserContactMaskingTest extends TestCase
{
    private const PHONE = '13800138000';
    private const EMAIL = 'probe@example.com';

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

        $this->username = 'export_probe_' . bin2hex(random_bytes(5));
        $user = new AdminUser();
        $user->id        = SnowflakeService::generate();
        $user->username  = $this->username;
        $user->password  = password_hash('Probe1234', PASSWORD_BCRYPT);
        $user->real_name = 'export_probe';
        $user->email     = self::EMAIL;   // Encryptable cast：库里落密文，模型取回明文
        $user->phone     = self::PHONE;
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
                // Encryptable + SoftDeletes：forceDelete 保证不留下带密文的软删行
                AdminUser::withTrashed()->where('id', $this->adminId)->forceDelete();
            } catch (\Throwable) {
            }
            $this->adminId = null;
        }
    }

    // ============================================================
    // 请求与产物
    // ============================================================

    /** @param array<string,mixed> $payload */
    private function export(array $payload): Response
    {
        $encoded  = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $request  = new Request(
            "POST /admin/v1/export/excel HTTP/1.1\r\nHost: localhost\r\n"
            . "Content-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($encoded) . "\r\n\r\n" . $encoded
        );

        return (new ExportController())->excel($request);
    }

    /**
     * 从下载响应里取出产物路径并交给回调。
     *
     * 响应是 `response()->download()` ⇒ 正文走**文件流**，rawBody() 拿不到 xlsx 字节；
     * 文件名在 Content-Disposition 里，产物落在 runtime/tmp。
     */
    private function withProduct(Response $response, callable $assert): void
    {
        $disposition = (string) $response->getHeader('Content-Disposition');
        $this->assertSame(1, preg_match('/filename="([^"]+)"/', $disposition, $m), '响应不是下载形状：' . $disposition);

        $path = runtime_path() . '/tmp/' . $m[1];
        $this->tmpFiles[] = $path;
        $this->assertFileExists($path, '响应说在下载，产物却不存在');

        $assert(IOFactory::load($path));
    }

    /** 在表里按用户名找到那一行的行号（找不到即 fail）。 */
    private function rowOf(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $username): int
    {
        $highestRow = $sheet->getHighestDataRow();
        for ($row = 2; $row <= $highestRow; $row++) {
            if ((string) $sheet->getCell('A' . $row)->getValue() === $username
                || (string) $sheet->getCell('B' . $row)->getValue() === $username) {
                return $row;
            }
        }
        $this->fail("导出产物里找不到探针管理员 {$username}（最高行 {$highestRow}）");
    }

    // ============================================================
    // 一、默认参数下必须导得出来（旧代码在这里抛 EncryptionException）
    // ============================================================

    #[Test]
    public function defaultColumnsWithContactInfoNoLongerBlowsUp(): void
    {
        // 不传 columns ⇒ 走 table 默认值 admin_user + 全部列，phone/email 落在 sensitiveFields 里。
        // 这正是端点对外承诺的默认形状，也是旧代码 500 的形状。
        $response = $this->export(['table' => 'admin_user', 'title' => 'probe']);

        $this->assertSame(200, $response->getStatusCode(), '导出必须成功返回，而不是抛 EncryptionException');
        $this->withProduct($response, function ($spreadsheet): void {
            // rowOf 找不到就 fail：默认列导出里必须有探针管理员那一行
            $this->assertGreaterThanOrEqual(2, $this->rowOf($spreadsheet->getActiveSheet(), $this->username));
        });
    }

    // ============================================================
    // 二、掩码：既不是明文，也不是密文
    // ============================================================

    #[Test]
    public function contactColumnsAreMaskedNotRawAndNotCiphertext(): void
    {
        $response = $this->export([
            'table'   => 'admin_user',
            'columns' => ['username', 'phone', 'email'],
            'title'   => 'probe',
        ]);

        $this->withProduct($response, function ($spreadsheet): void {
            $sheet = $spreadsheet->getActiveSheet();
            $row = $this->rowOf($sheet, $this->username);

            $phone = (string) $sheet->getCell('B' . $row)->getValue();
            $email = (string) $sheet->getCell('C' . $row)->getValue();

            // 掩码形状 = EncryptionService::maskPhone / maskEmail 的口径（掩错位置等于没脱敏）
            $this->assertSame('138****8000', $phone, '手机号掩码形状不对：' . $phone);
            $this->assertSame('p***@example.com', $email, '邮箱掩码形状不对：' . $email);

            // 明文泄漏是这条用例存在的全部理由
            $this->assertStringNotContainsString(self::PHONE, $phone, '导出产物里有明文手机号');
            $this->assertStringNotContainsString('probe@', $email, '导出产物里有明文邮箱');
            // 密文也不行：产物是给人看的，吐密文等于这一列不可用
            $this->assertStringNotContainsString('eyJ', $phone . $email, '导出产物里是密文，不是掩码');
        });
    }

    // ============================================================
    // 三、反向正控：没有联系方式的列照常原样导出
    // ============================================================

    #[Test]
    public function nonSensitiveColumnsAreLeftVerbatim(): void
    {
        $response = $this->export([
            'table'   => 'admin_user',
            'columns' => ['username', 'real_name'],
            'title'   => 'probe',
        ]);

        $this->withProduct($response, function ($spreadsheet): void {
            $sheet = $spreadsheet->getActiveSheet();
            $row = $this->rowOf($sheet, $this->username);

            $this->assertSame($this->username, (string) $sheet->getCell('A' . $row)->getValue());
            $this->assertSame('export_probe', (string) $sheet->getCell('B' . $row)->getValue());
        });
    }
}
