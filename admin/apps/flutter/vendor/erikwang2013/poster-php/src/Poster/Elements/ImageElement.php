<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class ImageElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $src = $this->options['src'] ?? '';
        if (!is_file($src)) return;
        $img = DriverFactory::create()->load($src);
        $canvas->image($img, intval($this->options['x'] ?? 0), intval($this->options['y'] ?? 0), $this->options);
        $img->destroy();
    }

    public function resolve(array $variables): static
    {
        if (isset($this->options['src'])) {
            $this->options['src'] = $this->resolvePlaceholders($this->options['src'], $variables);
        }
        return $this;
    }
}
