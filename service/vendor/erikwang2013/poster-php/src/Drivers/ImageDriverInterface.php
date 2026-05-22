<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Drivers;

interface ImageDriverInterface
{
    public function load(string $path): self;

    public function create(int $width, int $height): self;

    public function resize(int $width, int $height): self;

    public function rotate(float $angle, string $bgColor = '#000000'): self;

    public function crop(int $x, int $y, int $width, int $height): self;

    public function text(string $text, int $x, int $y, array $options = []): self;

    public function image(self $overlay, int $x, int $y, array $options = []): self;

    public function rectangle(int $x, int $y, int $width, int $height, array $options = []): self;

    public function ellipse(int $cx, int $cy, int $rx, int $ry, array $options = []): self;

    public function line(int $x1, int $y1, int $x2, int $y2, array $options = []): self;

    public function blur(int $radius = 1): self;

    public function pixelate(int $blockSize = 3): self;

    public function save(string $path, string $format = 'jpg', int $quality = 90): bool;

    public function output(string $format = 'jpg', int $quality = 90): string;

    public function getSize(): array;

    public function getResource(): mixed;

    public function clone(): self;

    public function destroy(): void;
}
