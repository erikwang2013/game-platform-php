<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class EmojiElement extends AbstractElement
{
    // Common emoji font paths by OS
    private const EMOJI_FONT_PATHS = [
        '/System/Library/Fonts/Apple Color Emoji.ttc',       // macOS
        '/usr/share/fonts/truetype/noto/NotoColorEmoji.ttf', // Linux
        'C:\Windows\Fonts\seguiemj.ttf',                      // Windows
    ];

    public function render(ImageDriverInterface $canvas): void
    {
        $emoji  = $this->options['emoji'] ?? '';
        $codepoint = $this->options['codepoint'] ?? null;
        $x      = intval($this->options['x'] ?? 0);
        $y      = intval($this->options['y'] ?? 0);
        $size   = intval($this->options['size'] ?? 64);
        $font   = $this->options['font'] ?? null;

        // Resolve emoji character
        $char = $emoji;
        if ($char === '' && $codepoint !== null) {
            $char = $this->codepointToChar($codepoint);
        }
        if ($char === '') return;

        // Find emoji font
        $emojiFont = $font;
        if ($emojiFont === null || !is_file($emojiFont)) {
            $emojiFont = $this->findEmojiFont();
        }
        if ($emojiFont === null) {
            // Fallback: render as plain text
            $canvas->text($char, $x, $y, [
                'size' => $size, 'color' => '#000000',
            ]);
            return;
        }

        // Render emoji using the color font
        $canvas->text($char, $x, $y, [
            'size'  => $size,
            'color' => $this->options['color'] ?? '#000000',
            'font'  => $emojiFont,
        ]);
    }

    public function resolve(array $variables): static
    {
        if (isset($this->options['emoji'])) {
            $this->options['emoji'] = $this->resolvePlaceholders($this->options['emoji'], $variables);
        }
        return $this;
    }

    private function findEmojiFont(): ?string
    {
        foreach (self::EMOJI_FONT_PATHS as $path) {
            if (is_file($path)) return $path;
        }
        return null;
    }

    private function codepointToChar(string|int $codepoint): string
    {
        if (is_int($codepoint)) {
            return mb_chr($codepoint, 'UTF-8') ?: '';
        }
        // Support formats: "U+1F600", "1F600", "0x1F600"
        $hex = str_replace(['U+', 'u+', '0x', '0X'], '', trim($codepoint));
        $cp = hexdec($hex);
        return mb_chr($cp, 'UTF-8') ?: '';
    }
}
