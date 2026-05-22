<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Drivers;

use Erikwang2013\Poster\Drivers\GdDriver;
use PHPUnit\Framework\TestCase;

class DriverTest extends TestCase
{
    public function testCreateReturnsCorrectSize(): void
    {
        $driver = new GdDriver();
        $driver->create(300, 200);
        $size = $driver->getSize();
        $this->assertSame(300, $size['width']);
        $this->assertSame(200, $size['height']);
        $driver->destroy();
    }

    public function testRectangleDrawsOnCanvas(): void
    {
        $driver = new GdDriver();
        $driver->create(100, 100);
        $driver->rectangle(10, 10, 80, 80, ['color' => '#FF0000', 'filled' => true]);
        $resource = $driver->getResource();
        $this->assertNotNull($resource);
        $this->assertInstanceOf(\GdImage::class, $resource);
        $driver->destroy();
    }

    public function testTextDrawsWithoutError(): void
    {
        $driver = new GdDriver();
        $driver->create(200, 100);
        $driver->text('Hello World', 10, 50, ['size' => 16, 'color' => '#000000']);
        $resource = $driver->getResource();
        $this->assertNotNull($resource);
        $driver->destroy();
    }

    public function testSaveCreatesFile(): void
    {
        $driver = new GdDriver();
        $driver->create(50, 50);
        $path = sys_get_temp_dir() . '/poster-test-save-' . uniqid() . '.jpg';
        $result = $driver->save($path);
        $this->assertTrue($result);
        $this->assertFileExists($path);
        unlink($path);
        $driver->destroy();
    }

    public function testOutputReturnsDataUrl(): void
    {
        $driver = new GdDriver();
        $driver->create(50, 50);
        $output = $driver->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $output);
        $decoded = base64_decode(substr($output, strpos($output, ',') + 1));
        $this->assertNotFalse($decoded);
        $this->assertGreaterThan(100, strlen($decoded));
        $driver->destroy();
    }
}
