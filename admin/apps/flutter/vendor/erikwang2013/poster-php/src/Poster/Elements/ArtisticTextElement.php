<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\GdDriver;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class ArtisticTextElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $text  = $this->options['text'] ?? $this->options['content'] ?? '';
        $x     = intval($this->options['x'] ?? 0);
        $y     = intval($this->options['y'] ?? 0);
        $size  = intval($this->options['size'] ?? 48);
        $font  = $this->options['font'] ?? null;
        $style = $this->options['style'] ?? 'stroke';

        $angle    = intval($this->options['angle'] ?? 0);
        $color    = $this->options['color'] ?? '#333333';
        $maxWidth = intval($this->options['maxWidth'] ?? 0);
        $align    = $this->options['align'] ?? 'left';

        $baseOpts = [
            'font'   => $font,
            'angle'  => $angle,
            'align'  => $align,
            'maxWidth' => $maxWidth,
        ];

        switch ($style) {
            case 'stroke':
                // Text outline/stroke effect
                $strokeColor = $this->options['strokeColor'] ?? '#000000';
                $strokeWidth = intval($this->options['strokeWidth'] ?? 1);
                for ($ox = -$strokeWidth; $ox <= $strokeWidth; $ox++) {
                    for ($oy = -$strokeWidth; $oy <= $strokeWidth; $oy++) {
                        if ($ox === 0 && $oy === 0) continue;
                        $canvas->text($text, $x + $ox, $y + $oy, $baseOpts + [
                            'size' => $size, 'color' => $strokeColor,
                        ]);
                    }
                }
                $canvas->text($text, $x, $y, $baseOpts + ['size' => $size, 'color' => $color]);
                break;

            case 'shadow':
                // Drop shadow effect
                $shadowColor   = $this->options['shadowColor'] ?? '#00000033';
                $shadowOffsetX = intval($this->options['shadowOffsetX'] ?? 3);
                $shadowOffsetY = intval($this->options['shadowOffsetY'] ?? 3);
                $canvas->text($text, $x + $shadowOffsetX, $y + $shadowOffsetY, $baseOpts + [
                    'size' => $size, 'color' => $shadowColor,
                ]);
                $canvas->text($text, $x, $y, $baseOpts + ['size' => $size, 'color' => $color]);
                break;

            case 'gradient':
                // True vertical gradient via per-pixel coloring on a temp mask
                $color2 = $this->options['color2'] ?? '#FF6B6B';
                $r1 = hexdec(substr($color, 1, 2)); $g1 = hexdec(substr($color, 3, 2)); $b1 = hexdec(substr($color, 5, 2));
                $r2 = hexdec(substr($color2, 1, 2)); $g2 = hexdec(substr($color2, 3, 2)); $b2 = hexdec(substr($color2, 5, 2));

                if ($font === null || !is_file($font)) {
                    $canvas->text($text, $x, $y, $baseOpts + ['size' => $size, 'color' => $color]);
                    break;
                }

                $bbox = imagettfbbox($size, 0, $font, $text);
                if ($bbox === false) {
                    $canvas->text($text, $x, $y, $baseOpts + ['size' => $size, 'color' => $color]);
                    break;
                }

                $tw = $bbox[2] - $bbox[0] + 8;
                $th = abs($bbox[7] - $bbox[1]) + 8;
                $ox = -$bbox[0] + 4;
                $oy = abs($bbox[7]) + 4;

                // Render white text mask on transparent bg
                $mask = imagecreatetruecolor(intval($tw), intval($th));
                imagesavealpha($mask, true);
                $tbg = imagecolorallocatealpha($mask, 0, 0, 0, 127);
                imagefill($mask, 0, 0, $tbg);
                $white = imagecolorallocate($mask, 255, 255, 255);
                imagettftext($mask, $size, 0, intval($ox), intval($oy), $white, $font, $text);

                // Color each pixel by Y-position gradient
                for ($py = 0; $py < $th; $py++) {
                    $ratio = $py / max(1, intval($th) - 1);
                    $cr = intval($r1 + ($r2 - $r1) * $ratio);
                    $cg = intval($g1 + ($g2 - $g1) * $ratio);
                    $cb = intval($b1 + ($b2 - $b1) * $ratio);
                    for ($px = 0; $px < $tw; $px++) {
                        $pix = imagecolorat($mask, $px, $py);
                        $a = ($pix >> 24) & 0x7F;
                        if ($a < 64) {
                            $col = imagecolorallocatealpha($mask, $cr, $cg, $cb, $a);
                            imagesetpixel($mask, $px, $py, $col);
                        }
                    }
                }

                // Composite gradient text onto canvas
                $temp = new GdDriver();
                $temp->setGdResource($mask);
                $canvas->image($temp, $x + $bbox[0] - 4, $y + $bbox[7] - 4);
                $temp->destroy();
                break;

            case 'neon':
                // Neon glow effect (multiple blurred layers)
                $glowColor = $this->options['glowColor'] ?? $color;
                for ($i = 3; $i >= 1; $i--) {
                    $alpha = dechex(20 * $i);
                    $canvas->text($text, $x, $y, $baseOpts + [
                        'size' => $size + $i * 2, 'color' => $glowColor . $alpha,
                    ]);
                }
                $canvas->text($text, $x, $y, $baseOpts + ['size' => $size, 'color' => '#FFFFFF']);
                break;

            default:
                $canvas->text($text, $x, $y, $baseOpts + ['size' => $size, 'color' => $color]);
        }
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
