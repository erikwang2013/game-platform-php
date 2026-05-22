<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Storage;

use Erikwang2013\Poster\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    private string $tempDir;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/poster-test-storage-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->storage = new FileStorage($this->tempDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*.json'));
        rmdir($this->tempDir);
    }

    public function testSetAndGet(): void
    {
        $key = 'test-key';
        $data = ['type' => 'click', 'targets' => [['x' => 100, 'y' => 200]]];
        $result = $this->storage->set($key, $data, 60);
        $this->assertTrue($result);

        $retrieved = $this->storage->get($key);
        $this->assertIsArray($retrieved);
        $this->assertSame('click', $retrieved['type']);
        $this->assertSame(100, $retrieved['targets'][0]['x']);
    }

    public function testExpiredKeyReturnsNull(): void
    {
        $key = 'expired-key';
        $data = ['type' => 'click', 'targets' => []];
        $this->storage->set($key, $data, -3600);
        $this->assertNull($this->storage->get($key));
    }

    public function testDelRemovesKey(): void
    {
        $key = 'delete-key';
        $this->storage->set($key, ['type' => 'click', 'targets' => []], 60);
        $this->assertNotNull($this->storage->get($key));

        $this->assertTrue($this->storage->del($key));
        $this->assertNull($this->storage->get($key));
    }
}
