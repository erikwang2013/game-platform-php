<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class IconElement extends AbstractElement
{
    // FontAwesome 5/6 common icon unicode mappings
    private const FA_ICONS = [
        'heart'       => '\u{F004}',
        'star'        => '\u{F005}',
        'user'        => '\u{F007}',
        'clock'       => '\u{F017}',
        'home'        => '\u{F015}',
        'cog'         => '\u{F013}',
        'check'       => '\u{F00C}',
        'times'       => '\u{F00D}',
        'search'      => '\u{F002}',
        'envelope'    => '\u{F0E0}',
        'phone'       => '\u{F095}',
        'camera'      => '\u{F030}',
        'play'        => '\u{F04B}',
        'pause'       => '\u{F04C}',
        'shopping-cart' => '\u{F07A}',
        'tag'         => '\u{F02B}',
        'map-marker'  => '\u{F3C5}',
        'calendar'    => '\u{F133}',
        'comment'     => '\u{F075}',
        'share'       => '\u{F064}',
        'download'    => '\u{F019}',
        'upload'      => '\u{F093}',
        'lock'        => '\u{F023}',
        'globe'       => '\u{F0AC}',
        'link'        => '\u{F0C1}',
        'image'       => '\u{F03E}',
        'music'       => '\u{F001}',
        'video'       => '\u{F008}',
        'bell'        => '\u{F0F3}',
        'bookmark'    => '\u{F02E}',
        'thumbs-up'   => '\u{F164}',
        'eye'         => '\u{F06E}',
        'trash'       => '\u{F1F8}',
        'edit'        => '\u{F044}',
        'plus'        => '\u{F067}',
        'minus'       => '\u{F068}',
        'arrow-right' => '\u{F061}',
        'arrow-left'  => '\u{F060}',
        'arrow-up'    => '\u{F062}',
        'arrow-down'  => '\u{F063}',
        'location-dot' => '\u{F3C5}',
        'fire'        => '\u{F06D}',
        'gift'        => '\u{F06B}',
        'rocket'      => '\u{F135}',
    ];

    public function render(ImageDriverInterface $canvas): void
    {
        $icon = $this->options['icon'] ?? '';
        $codepoint = $this->options['codepoint'] ?? null;
        $x = intval($this->options['x'] ?? 0);
        $y = intval($this->options['y'] ?? 0);
        $size = intval($this->options['size'] ?? 32);
        $color = $this->options['color'] ?? '#333333';
        $font = $this->options['font'] ?? null;

        // Resolve icon character
        $char = $this->resolveIcon($icon, $codepoint);
        if ($char === '') return;

        if ($font !== null && is_file($font)) {
            $canvas->text($char, $x, $y, [
                'size'  => $size,
                'color' => $color,
                'font'  => $font,
            ]);
        } else {
            // Fallback: render as text placeholder
            $canvas->text($icon !== '' ? "[{$icon}]" : '?', $x, $y, [
                'size'  => intval($size * 0.6),
                'color' => $color,
            ]);
        }
    }

    private function resolveIcon(string $icon, ?string $codepoint): string
    {
        if ($codepoint !== null) {
            return $this->codepointToChar($codepoint);
        }

        if ($icon !== '' && isset(self::FA_ICONS[$icon])) {
            return $this->codepointToChar(self::FA_ICONS[$icon]);
        }

        return '';
    }

    private function codepointToChar(string $codepoint): string
    {
        // Handle \u{XXXX} format
        $hex = str_replace(['\\u{', '\\u', '}'], '', $codepoint);
        $cp = hexdec(trim($hex));
        return mb_chr($cp, 'UTF-8') ?: '';
    }
}
