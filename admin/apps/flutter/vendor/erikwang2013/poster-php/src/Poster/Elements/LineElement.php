<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class LineElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $x1 = intval($this->options['x1'] ?? 0);
        $y1 = intval($this->options['y1'] ?? 0);
        $x2 = intval($this->options['x2'] ?? $this->options['x'] ?? 100);
        $y2 = intval($this->options['y2'] ?? $this->options['y'] ?? 0);
        $canvas->line($x1, $y1, $x2, $y2, $this->options);
    }
}
