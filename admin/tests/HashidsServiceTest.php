<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use app\common\HashidsService;

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
}
