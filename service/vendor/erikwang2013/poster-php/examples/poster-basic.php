<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

require __DIR__ . '/../vendor/autoload.php';

$builder = poster_create(750, 1334);
$path = __DIR__ . '/output-basic.jpg';

$builder
    ->background('#FFFFFF')
    ->addShape('rect', ['x' => 0, 'y' => 0, 'width' => 750, 'height' => 300, 'color' => '#FF6B6B'])
    ->addText('新品首发', ['x' => 80, 'y' => 100, 'size' => 48, 'color' => '#FFFFFF'])
    ->addText('限时特惠', ['x' => 80, 'y' => 180, 'size' => 28, 'color' => '#FFE0E0'])
    ->addQrcode('https://example.com', ['x' => 275, 'y' => 1050, 'size' => 200, 'label' => '扫码查看详情'])
    ->save($path);

echo "Poster saved to: $path\n";
