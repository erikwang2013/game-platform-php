<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

abstract class AbstractElement implements ElementInterface
{
    protected array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function toArray(): array
    {
        return ['type' => static::class, 'options' => $this->options];
    }

    protected function resolvePlaceholders(string $text, array $variables): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($variables) {
            return $variables[$m[1]] ?? $m[0];
        }, $text);
    }
}
