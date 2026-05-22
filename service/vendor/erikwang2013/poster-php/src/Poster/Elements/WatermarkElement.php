<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class WatermarkElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $text = $this->options['text'] ?? '';
        if (empty($text)) return;

        $canvasSize = $canvas->getSize();
        if (empty($canvasSize['width']) || empty($canvasSize['height'])) return;

        $spacingX = intval($this->options['spacing_x'] ?? $this->options['spacing'] ?? 150);
        $spacingY = intval($this->options['spacing_y'] ?? $this->options['spacing'] ?? 100);
        $angle = floatval($this->options['angle'] ?? -30);

        $textOptions = $this->options;
        $textOptions['angle'] = $angle;

        for ($x = 0; $x < $canvasSize['width']; $x += $spacingX) {
            for ($y = 0; $y < $canvasSize['height']; $y += $spacingY) {
                $canvas->text($text, $x, $y, $textOptions);
            }
        }
    }

    public function resolve(array $variables): static
    {
        if (isset($this->options['text'])) {
            $this->options['text'] = $this->resolvePlaceholders($this->options['text'], $variables);
        }
        return $this;
    }
}
