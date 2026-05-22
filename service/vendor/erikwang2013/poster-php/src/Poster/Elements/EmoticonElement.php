<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class EmoticonElement extends AbstractElement
{
    // Common kaomoji / emoticons
    private const KAOMOJI = [
        'happy'    => '(｡•̀ᴗ-)✧',
        'love'     => '(♡°▽°♡)',
        'cry'      => '(╥﹏╥)',
        'angry'    => '(╬ Ò﹏Ó)',
        'surprised' => '(⊙_⊙)',
        'cool'     => '(⌐■_■)',
        'sleepy'   => '(－_－) zzZ',
        'wave'     => '(・∀・)ノ',
        'think'    => '(ー_ーゞ',
        'shrug'    => '¯\_(ツ)_/¯',
        'tableflip' => '(╯°□°）╯︵ ┻━┻',
        'lenny'    => '( ͡° ͜ʖ ͡°)',
    ];

    public function render(ImageDriverInterface $canvas): void
    {
        $expression = $this->options['expression'] ?? '';
        $text  = $this->options['text'] ?? '';
        $x     = intval($this->options['x'] ?? 0);
        $y     = intval($this->options['y'] ?? 0);
        $size  = intval($this->options['size'] ?? 24);
        $color = $this->options['color'] ?? '#333333';
        $font  = $this->options['font'] ?? null;

        // Resolve content
        $content = $text;
        if ($content === '' && $expression !== '' && isset(self::KAOMOJI[$expression])) {
            $content = self::KAOMOJI[$expression];
        }
        if ($content === '') return;

        $opts = [
            'size'  => $size,
            'color' => $color,
        ];
        if ($font !== null && is_file($font)) {
            $opts['font'] = $font;
        }

        $canvas->text($content, $x, $y, $opts);
    }

    public function resolve(array $variables): static
    {
        if (isset($this->options['text'])) {
            $this->options['text'] = $this->resolvePlaceholders($this->options['text'], $variables);
        }
        return $this;
    }

    /**
     * Get available kaomoji expressions.
     */
    public static function expressions(): array
    {
        return array_keys(self::KAOMOJI);
    }
}
