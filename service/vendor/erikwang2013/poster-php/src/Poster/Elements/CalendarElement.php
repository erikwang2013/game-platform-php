<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class CalendarElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $year     = intval($this->options['year'] ?? date('Y'));
        $month    = intval($this->options['month'] ?? date('m'));
        $x        = intval($this->options['x'] ?? 0);
        $y        = intval($this->options['y'] ?? 0);
        $cellSize = intval($this->options['cellSize'] ?? 60);
        $startDay = intval($this->options['startDay'] ?? 0); // 0=Sun, 1=Mon
        $font     = $this->options['font'] ?? null;
        $highlights = $this->options['highlights'] ?? [];

        $headerBg   = $this->options['headerBg'] ?? '#333333';
        $headerColor = $this->options['headerColor'] ?? '#FFFFFF';
        $cellBg     = $this->options['cellBg'] ?? '#FFFFFF';
        $cellBorder = $this->options['cellBorder'] ?? '#DDDDDD';
        $todayBg    = $this->options['todayBg'] ?? '#FF6B6B';
        $highlightBg = $this->options['highlightBg'] ?? '#FFEAA7';
        $textColor  = $this->options['textColor'] ?? '#333333';
        $dimColor   = $this->options['dimColor'] ?? '#CCCCCC';

        $dayNames = $startDay === 0
            ? ['日', '一', '二', '三', '四', '五', '六']
            : ['一', '二', '三', '四', '五', '六', '日'];
        $dayNamesEn = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        $daysInMonth = intval(date('t', mktime(0, 0, 0, $month, 1, $year)));
        $firstDow    = intval(date('w', mktime(0, 0, 0, $month, 1, $year)));
        $adjustedDow = $startDay === 0 ? $firstDow : (($firstDow + 6) % 7);

        $today = date('Y-m-d');
        $width = $cellSize * 7;

        // Month/year title
        $title = $this->options['title'] ?? ($year . '年' . $month . '月');
        $canvas->rectangle($x, $y, $width, $cellSize, ['color' => $headerBg, 'filled' => true]);
        $canvas->text($title, $x + intval($width / 2), $y + intval($cellSize * 0.65), [
            'size' => 18, 'color' => $headerColor, 'font' => $font, 'align' => 'center',
        ]);

        // Day name headers
        $headerFontSize = intval($cellSize * 0.22);
        for ($d = 0; $d < 7; $d++) {
            $dx = $x + $d * $cellSize;
            $dy = $y + $cellSize;
            $canvas->rectangle($dx, $dy, $cellSize, intval($cellSize * 0.6), [
                'color' => '#F5F5F5', 'filled' => true,
            ]);
            $canvas->text($dayNames[$d], $dx + intval($cellSize / 2), $dy + intval($cellSize * 0.42), [
                'size' => $headerFontSize, 'color' => '#666666', 'font' => $font, 'align' => 'center',
            ]);
        }

        // Day cells
        $rowY = $y + $cellSize + intval($cellSize * 0.6);
        $day  = 1;
        for ($row = 0; $row < 6; $row++) {
            for ($col = 0; $col < 7; $col++) {
                $cx = $x + $col * $cellSize;

                if (($row === 0 && $col < $adjustedDow) || $day > $daysInMonth) {
                    // Empty cell
                    $canvas->rectangle($cx, $rowY, $cellSize, $cellSize, [
                        'color' => $cellBg, 'filled' => true,
                    ]);
                    $canvas->rectangle($cx, $rowY, $cellSize, $cellSize, [
                        'color' => $cellBorder, 'filled' => false, 'strokeWidth' => 1,
                    ]);
                    continue;
                }

                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);

                // Cell background
                $bg = $cellBg;
                if ($dateStr === $today) {
                    $bg = $todayBg;
                } elseif (isset($highlights[$dateStr])) {
                    $bg = is_array($highlights[$dateStr]) ? ($highlights[$dateStr]['bg'] ?? $highlightBg) : $highlightBg;
                }

                $canvas->rectangle($cx, $rowY, $cellSize, $cellSize, ['color' => $bg, 'filled' => true]);
                $canvas->rectangle($cx, $rowY, $cellSize, $cellSize, [
                    'color' => $cellBorder, 'filled' => false, 'strokeWidth' => 1,
                ]);

                $weekendCols = ($startDay === 0) ? [0, 6] : [5, 6];
                $dayColor = in_array($col, $weekendCols, true) ? '#E74C3C' : $textColor;
                if ($dateStr === $today) $dayColor = '#FFFFFF';

                $canvas->text((string)$day, $cx + 8, $rowY + intval($cellSize * 0.3), [
                    'size' => intval($cellSize * 0.28), 'color' => $dayColor, 'font' => $font,
                ]);

                // Highlight text
                if (isset($highlights[$dateStr])) {
                    $ht = is_array($highlights[$dateStr]) ? ($highlights[$dateStr]['text'] ?? '') : $highlights[$dateStr];
                    if ($ht !== '') {
                        $canvas->text($ht, $cx + intval($cellSize / 2), $rowY + intval($cellSize * 0.75), [
                            'size' => intval($cellSize * 0.16), 'color' => '#666666', 'font' => $font, 'align' => 'center',
                        ]);
                    }
                }

                $day++;
            }
            $rowY += $cellSize;
            if ($day > $daysInMonth) break;
        }
    }
}
