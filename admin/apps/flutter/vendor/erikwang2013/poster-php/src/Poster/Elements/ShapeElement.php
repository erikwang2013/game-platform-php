<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class ShapeElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $shape = $this->options['shape'] ?? 'rect';

        if ($shape === 'circle') {
            $cx = intval($this->options['cx'] ?? $this->options['x'] ?? 0);
            $cy = intval($this->options['cy'] ?? $this->options['y'] ?? 0);
            $radius = intval($this->options['radius'] ?? intval($this->options['size'] ?? 50));
            $canvas->ellipse($cx, $cy, $radius, $radius, $this->options);
        } else {
            $x = intval($this->options['x'] ?? 0);
            $y = intval($this->options['y'] ?? 0);
            $w = intval($this->options['width'] ?? 100);
            $h = intval($this->options['height'] ?? 100);
            $canvas->rectangle($x, $y, $w, $h, $this->options);
        }
    }
}
