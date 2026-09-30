<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use common\HashidsService;

class HashidsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // 确保 .env 已加载
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = \Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }
    }

    #[Test]
    public function encode_returns_non_empty_string(): void
    {
        $result = HashidsService::encode(1);
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    #[Test]
    public function encode_different_ids_produce_different_hashes(): void
    {
        $hash1 = HashidsService::encode(1);
        $hash2 = HashidsService::encode(2);
        $this->assertNotEquals($hash1, $hash2);
    }

    #[Test]
    public function encode_decode_roundtrip(): void
    {
        $ids = [1, 42, 999, 1750123456789];
        foreach ($ids as $id) {
            $hash = HashidsService::encode($id);
            $decoded = HashidsService::decode($hash);
            $this->assertEquals($id, $decoded, "往返失败: id=$id");
        }
    }

    #[Test]
    public function decode_invalid_hash_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        HashidsService::decode('not-a-valid-hash-xxx');
    }

    #[Test]
    public function encodeIds_batch_encodes_id_fields(): void
    {
        $data = ['id' => 123, 'name' => 'test'];
        $result = HashidsService::encodeIds($data);
        $this->assertNotEquals(123, $result['id']);
        $this->assertIsString($result['id']);
        $this->assertEquals('test', $result['name']); // 非ID字段不变
    }

    #[Test]
    public function encodeIds_custom_fields(): void
    {
        $data = ['user_id' => 456, 'role_id' => 789];
        $result = HashidsService::encodeIds($data, ['user_id', 'role_id']);
        $this->assertNotEquals(456, $result['user_id']);
        $this->assertNotEquals(789, $result['role_id']);
        // 解码验证
        $this->assertEquals(456, HashidsService::decode($result['user_id']));
        $this->assertEquals(789, HashidsService::decode($result['role_id']));
    }

    /**
     * 多行列表必须逐行编。
     *
     * 这就是 /admin/v1/risk/rule/list 报的那个缺陷：只处理顶层键时 isset($rows['id']) 恒假，
     * 整张表传进来会静默一个都不编，而 update/toggle 走的是 {hashid} 路径，回填必然对不上。
     */
    #[Test]
    public function encodeIds_encodes_every_row_of_a_list(): void
    {
        $rows = [
            ['id' => 111, 'name' => 'a'],
            ['id' => 222, 'name' => 'b'],
        ];
        $out = HashidsService::encodeIds($rows);

        $this->assertIsString($out[0]['id'], '列表第 1 行的 id 必须被编码');
        $this->assertIsString($out[1]['id'], '列表第 2 行的 id 必须被编码');
        $this->assertSame(111, HashidsService::decode($out[0]['id']));
        $this->assertSame(222, HashidsService::decode($out[1]['id']));
        $this->assertSame('a', $out[0]['name'], '非 ID 字段不得改动');
        $this->assertSame('b', $out[1]['name']);
    }

    /** 列表 + 自定义字段名（analytics 的 game-ranking / conversion 就是这种形状） */
    #[Test]
    public function encodeIds_list_with_custom_field(): void
    {
        $rows = [['game_id' => 333, 'plays' => 5], ['game_id' => 444, 'plays' => 9]];
        $out = HashidsService::encodeIds($rows, ['game_id']);

        $this->assertSame(333, HashidsService::decode($out[0]['game_id']));
        $this->assertSame(444, HashidsService::decode($out[1]['game_id']));
        $this->assertSame(5, $out[0]['plays'], '非 ID 字段不得改动');
    }

    /** 空列表与非数组元素不得炸 */
    #[Test]
    public function encodeIds_handles_empty_and_scalar_rows(): void
    {
        $this->assertSame([], HashidsService::encodeIds([]));
        $this->assertSame([1, 2], HashidsService::encodeIds([1, 2]));
    }
}
