<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class TextElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $text = $this->options['text'] ?? $this->options['content'] ?? '';
        $canvas->text($text, intval($this->options['x'] ?? 0), intval($this->options['y'] ?? 0), $this->options);
    }

    public function resolve(array $variables): static
    {
        $key = isset($this->options['text']) ? 'text' : 'content';
        if (isset($this->options[$key])) {
            $this->options[$key] = $this->resolvePlaceholders($this->options[$key], $variables);
        }
        return $this;
    }
}
