<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class ChartElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $type  = $this->options['type'] ?? 'bar';
        $data  = $this->options['data'] ?? [];
        $x     = intval($this->options['x'] ?? 0);
        $y     = intval($this->options['y'] ?? 0);
        $w     = intval($this->options['width'] ?? 600);
        $h     = intval($this->options['height'] ?? 400);

        match ($type) {
            'pie'  => $this->drawPie($canvas, $data, $x, $y, $w, $h),
            'line' => $this->drawLineChart($canvas, $data, $x, $y, $w, $h),
            default => $this->drawBar($canvas, $data, $x, $y, $w, $h),
        };
    }

    private function drawBar(ImageDriverInterface $canvas, array $data, int $x, int $y, int $w, int $h): void
    {
        $colors  = $this->options['colors'] ?? ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD'];
        $padding = intval($this->options['padding'] ?? 40);
        $count   = count($data);
        if ($count === 0) return;

        $barW    = intval(($w - $padding * 2) / $count - 10);
        $maxVal  = max(array_map(fn($v) => is_array($v) ? ($v['value'] ?? 0) : $v, $data)) ?: 1;
        $chartH  = $h - $padding * 2;
        $axisY   = $y + $h - $padding;

        // Y-axis
        $canvas->line($x + $padding, $y + $padding, $x + $padding, $axisY, ['color' => '#CCCCCC']);
        // X-axis
        $canvas->line($x + $padding, $axisY, $x + $w - $padding, $axisY, ['color' => '#CCCCCC']);

        for ($i = 0; $i < $count; $i++) {
            $item  = $data[$i];
            $label = is_array($item) ? ($item['label'] ?? '') : '';
            $val   = is_array($item) ? ($item['value'] ?? 0) : $item;
            $barH  = intval(($val / $maxVal) * $chartH);
            $bx    = $x + $padding + $i * intval(($w - $padding * 2) / $count) + 5;
            $by    = $axisY - $barH;
            $color = $colors[$i % count($colors)];

            $canvas->rectangle($bx, $by, $barW, $barH, ['color' => $color, 'filled' => true]);

            // Value label
            $canvas->text((string)$val, $bx + intval($barW / 2), $by - 5, [
                'size' => 12, 'color' => '#333333', 'align' => 'center',
            ]);
            // Axis label
            if ($label !== '') {
                $canvas->text($label, $bx + intval($barW / 2), $axisY + 18, [
                    'size' => 11, 'color' => '#666666', 'align' => 'center',
                ]);
            }
        }
    }

    private function drawLineChart(ImageDriverInterface $canvas, array $data, int $x, int $y, int $w, int $h): void
    {
        $colors  = $this->options['colors'] ?? ['#FF6B6B'];
        $lineColor = $colors[0];
        $padding = intval($this->options['padding'] ?? 40);
        $count   = count($data);
        if ($count < 2) return;

        $maxVal  = max(array_map(fn($v) => is_array($v) ? ($v['value'] ?? 0) : $v, $data)) ?: 1;
        $chartH  = $h - $padding * 2;
        $chartW  = $w - $padding * 2;
        $axisY   = $y + $h - $padding;
        $stepX   = intval($chartW / ($count - 1));

        // Axes
        $canvas->line($x + $padding, $y + $padding, $x + $padding, $axisY, ['color' => '#CCCCCC']);
        $canvas->line($x + $padding, $axisY, $x + $w - $padding, $axisY, ['color' => '#CCCCCC']);

        // Grid lines
        for ($g = 1; $g <= 4; $g++) {
            $gy = $axisY - intval(($g / 4) * $chartH);
            $canvas->line($x + $padding, $gy, $x + $w - $padding, $gy, ['color' => '#EEEEEE']);
        }

        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $item = $data[$i];
            $val  = is_array($item) ? ($item['value'] ?? 0) : $item;
            $px   = $x + $padding + $i * $stepX;
            $py   = $axisY - intval(($val / $maxVal) * $chartH);
            $points[] = [$px, $py];

            // Dot
            $canvas->ellipse($px, $py, 4, 4, ['color' => $lineColor, 'filled' => true]);

            $label = is_array($item) ? ($item['label'] ?? '') : '';
            if ($label !== '') {
                $canvas->text($label, $px, $axisY + 18, ['size' => 11, 'color' => '#666666', 'align' => 'center']);
            }
        }

        // Connect points
        for ($i = 1; $i < count($points); $i++) {
            $canvas->line($points[$i-1][0], $points[$i-1][1], $points[$i][0], $points[$i][1], [
                'color' => $lineColor, 'width' => 2,
            ]);
        }
    }

    private function drawPie(ImageDriverInterface $canvas, array $data, int $x, int $y, int $w, int $h): void
    {
        $colors = $this->options['colors'] ?? ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD'];
        $total  = array_sum(array_map(fn($v) => is_array($v) ? ($v['value'] ?? 0) : $v, $data));
        if ($total <= 0) return;

        $cx     = $x + intval($w / 2);
        $cy     = $y + intval($h / 2);
        $radius = intval(min($w, $h) / 2) - 10;
        $start  = -90;

        // Track assigned degrees; last slice gets remainder
        $assigned = 0;
        $count = count($data);

        foreach ($data as $idx => $item) {
            $val   = is_array($item) ? ($item['value'] ?? 0) : $item;
            $label = is_array($item) ? ($item['label'] ?? '') : '';

            if ($idx === $count - 1) {
                $slice = 360 - $assigned;
            } else {
                $slice = intval(round(($val / $total) * 360));
            }
            $assigned += $slice;
            if ($slice <= 0) { $i = ($i ?? 0) + 1; continue; }
            $i = $idx;
            $color = $colors[$i % count($colors)];
            $rgb = $this->hexToRgb($color);

            // Draw filled pie slice using native GD filled arc
            $res = $canvas->getResource();
            if ($res instanceof \GdImage) {
                $alloc = imagecolorallocate($res, $rgb[0], $rgb[1], $rgb[2]);
                imagefilledarc($res, $cx, $cy, $radius * 2, $radius * 2, $start, $start + $slice, $alloc, IMG_ARC_PIE);
            } else {
                // Imagick fallback: draw solid wedge via polygon fan
                $diameter = $radius * 2;
                $steps = max($slice * 2, 4);
                for ($d = 0; $d < $steps; $d++) {
                    $ang = deg2rad($start + ($d / $steps) * $slice);
                    $canvas->line($cx, $cy, $cx + intval(cos($ang) * $radius), $cy + intval(sin($ang) * $radius), ['color' => $color]);
                }
            }

            // Label at middle angle
            $midAng = deg2rad($start + $slice / 2);
            $lx = $cx + intval(cos($midAng) * ($radius + 25));
            $ly = $cy + intval(sin($midAng) * ($radius + 25));
            if ($label !== '') {
                $canvas->text($label, $lx, $ly, ['size' => 10, 'color' => '#333333', 'align' => 'center']);
            }

            $start += $slice;
        }
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
