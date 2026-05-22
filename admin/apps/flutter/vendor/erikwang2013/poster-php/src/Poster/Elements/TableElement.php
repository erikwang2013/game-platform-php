<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class TableElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $headers = $this->options['headers'] ?? [];
        $rows = $this->options['rows'] ?? [];
        if (empty($headers) || empty($rows)) return;

        $x = intval($this->options['x'] ?? 0);
        $y = intval($this->options['y'] ?? 0);
        $colWidths = $this->options['col_widths'] ?? [];
        $headerHeight = intval($this->options['header_height'] ?? 40);
        $rowHeight = intval($this->options['row_height'] ?? 35);
        $headerBg = $this->options['header_bg'] ?? '#F5F5F5';
        $evenBg = $this->options['even_bg'] ?? '#FAFAFA';
        $oddBg = $this->options['odd_bg'] ?? '#FFFFFF';
        $fontSize = intval($this->options['font_size'] ?? 14);
        $font = $this->options['font'] ?? null;
        $headerColor = $this->options['header_color'] ?? '#333333';
        $rowColor = $this->options['row_color'] ?? '#666666';
        $borderColor = $this->options['border_color'] ?? '#EEEEEE';
        $alignments = $this->options['alignments'] ?? [];

        if (empty($colWidths)) {
            $colW = intval(($this->options['width'] ?? 600) / count($headers));
            $colWidths = array_fill(0, count($headers), $colW);
        }

        $totalWidth = array_sum($colWidths);

        // Calculate column X positions
        $colXs = [$x];
        foreach ($colWidths as $i => $w) {
            $colXs[$i + 1] = $colXs[$i] + $w;
        }

        // Draw header background
        $canvas->rectangle($x, $y, $totalWidth, $headerHeight, ['color' => $headerBg, 'filled' => true]);

        // Draw header text
        foreach ($headers as $i => $header) {
            $align = $alignments[$i] ?? 'left';
            $cx = match ($align) {
                'center' => $colXs[$i] + intval($colWidths[$i] / 2),
                'right'  => $colXs[$i] + $colWidths[$i] - 10,
                default  => $colXs[$i] + 10,
            };
            $canvas->text((string)$header, $cx, $y + intval(($headerHeight - $fontSize) / 2), [
                'size' => $fontSize, 'color' => $headerColor, 'font' => $font, 'align' => $align,
            ]);
        }

        // Draw rows with zebra stripes
        $currentY = $y + $headerHeight;
        foreach ($rows as $ri => $row) {
            $bg = $ri % 2 === 0 ? $evenBg : $oddBg;
            $canvas->rectangle($x, $currentY, $totalWidth, $rowHeight, ['color' => $bg, 'filled' => true]);

            foreach ($row as $ci => $cell) {
                if (!isset($colWidths[$ci])) continue;
                $align = $alignments[$ci] ?? 'left';
                $cx = match ($align) {
                    'center' => $colXs[$ci] + intval($colWidths[$ci] / 2),
                    'right'  => $colXs[$ci] + $colWidths[$ci] - 10,
                    default  => $colXs[$ci] + 10,
                };
                $canvas->text((string)$cell, $cx, $currentY + intval(($rowHeight - $fontSize) / 2), [
                    'size' => $fontSize, 'color' => $rowColor, 'font' => $font, 'align' => $align,
                ]);
            }

            // Draw row bottom border
            $canvas->line($x, $currentY + $rowHeight - 1, $x + $totalWidth - 1, $currentY + $rowHeight - 1, ['color' => $borderColor]);

            $currentY += $rowHeight;
        }
    }
}
