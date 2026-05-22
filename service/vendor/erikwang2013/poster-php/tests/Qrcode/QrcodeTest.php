<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Qrcode;

use Erikwang2013\Poster\Qrcode\QrcodeGenerator;
use PHPUnit\Framework\TestCase;

class QrcodeTest extends TestCase
{
    public function testGenerateReturnsGdImageWithCorrectDimensions(): void
    {
        $qr = new QrcodeGenerator();
        $qr->setText('https://erik.xyz');
        $qr->setSize(200);
        $image = $qr->render();
        $this->assertInstanceOf(\GdImage::class, $image);
        $this->assertGreaterThanOrEqual(100, imagesx($image));
        $this->assertGreaterThanOrEqual(100, imagesy($image));
        imagedestroy($image);
    }

    public function testOutputReturnsNonEmptyPngData(): void
    {
        $qr = new QrcodeGenerator();
        $qr->setText('Hello World');
        $qr->setSize(150);
        $image = $qr->render();
        ob_start();
        imagepng($image);
        $pngData = ob_get_clean();
        $this->assertIsString($pngData);
        $this->assertGreaterThan(500, strlen($pngData));
        imagedestroy($image);
    }
}
