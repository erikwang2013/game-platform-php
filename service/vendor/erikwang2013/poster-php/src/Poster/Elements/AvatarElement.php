<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class AvatarElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $src = $this->options['src'] ?? '';
        if (!is_file($src)) return;

        $img = DriverFactory::create()->load($src);
        $size = intval($this->options['size'] ?? 80);
        $img->resize($size, $size);

        $options = $this->options;
        if (!empty($this->options['circle'])) {
            $options['radius'] = intval($size / 2);
        }

        $canvas->image($img, intval($this->options['x'] ?? 0), intval($this->options['y'] ?? 0), $options);
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
