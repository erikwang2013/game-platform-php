<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Qrcode;

use GdImage;
use InvalidArgumentException;

class QrcodeGenerator
{
    private string $text = '';
    private int $size = 200;
    private int $margin = 2;
    private string $errorLevel = 'H';
    private int $foreground = 0x000000;
    private int $background = 0xFFFFFF;

    // Alignment pattern positions per version
    private const ALIGNMENTS = [
        1  => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6  => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50], 11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62],
        14 => [6, 26, 46, 66], 15 => [6, 26, 48, 70], 16 => [6, 26, 50, 74],
        17 => [6, 30, 54, 78], 18 => [6, 30, 56, 82], 19 => [6, 30, 58, 86],
        20 => [6, 34, 62, 90], 21 => [6, 28, 50, 72, 94], 22 => [6, 26, 50, 74, 98],
        23 => [6, 30, 54, 78, 102], 24 => [6, 28, 54, 80, 106], 25 => [6, 32, 58, 84, 110],
        26 => [6, 30, 58, 86, 114], 27 => [6, 34, 62, 90, 118], 28 => [6, 26, 50, 74, 98, 122],
        29 => [6, 30, 54, 78, 102, 126], 30 => [6, 26, 52, 78, 104, 130],
        31 => [6, 30, 56, 82, 108, 134], 32 => [6, 34, 60, 86, 112, 138],
        33 => [6, 30, 58, 86, 114, 142], 34 => [6, 34, 62, 90, 118, 146],
        35 => [6, 30, 54, 78, 102, 126, 150], 36 => [6, 24, 50, 76, 102, 128, 154],
        37 => [6, 28, 54, 80, 106, 132, 158], 38 => [6, 32, 58, 84, 110, 136, 162],
        39 => [6, 26, 54, 82, 110, 138, 166], 40 => [6, 30, 58, 86, 114, 142, 170],
    ];

    // RS block info per version per EC level: [total codewords, data codewords per block, count of blocks]
    private const RS_BLOCKS = [
        // Version => [L, M, Q, H] where each is [[data, ecc, count], ...]
        1  => [[[19,7,1]], [[16,10,1]], [[13,13,1]], [[9,17,1]]],
        2  => [[[34,10,1]], [[28,16,1]], [[22,22,1]], [[16,28,1]]],
        3  => [[[55,15,1]], [[44,26,1]], [[17,18,2]], [[13,22,2]]],
        4  => [[[80,20,1]], [[32,18,2]], [[24,26,2]], [[9,16,4]]],
        5  => [[[108,26,1]], [[43,24,2]], [[15,18,2],[16,18,2]], [[11,22,2],[12,22,2]]],
        6  => [[[68,18,2]], [[27,16,4]], [[19,24,4]], [[15,28,4]]],
        7  => [[[78,20,3]], [[31,18,4]], [[14,18,2],[15,18,4]], [[13,26,1],[14,26,4]]],
        8  => [[[97,24,2]], [[38,22,4]], [[18,22,2],[19,22,4]], [[14,26,2],[15,26,4]]],
        9  => [[[116,30,2]], [[36,22,4]], [[16,20,3],[17,20,4]], [[12,24,4],[13,24,4]]],
        10 => [[[68,18,2],[69,18,2]], [[43,26,1],[44,26,4]], [[19,24,6],[20,24,2]], [[15,28,4],[16,28,4]]],
        // For v11-40, use simplified single-block approximation
        11 => [[[81,20,4]], [[50,30,4]], [[31,28,6]], [[25,24,8]]],
        12 => [[[92,24,4]], [[54,22,6]], [[34,26,6]], [[24,28,8]]],
        13 => [[[107,26,4]], [[59,22,8]], [[37,24,8]], [[27,22,12]]],
        14 => [[[115,30,4]], [[62,24,10]], [[40,28,8]], [[28,24,12]]],
        15 => [[[87,22,5]], [[54,24,10]], [[43,30,12]], [[32,24,14]]],
        16 => [[[98,24,5]], [[65,28,10]], [[45,24,12]], [[33,30,14]]],
        17 => [[[107,28,6]], [[68,28,12]], [[46,28,14]], [[36,28,16]]],
        18 => [[[120,30,6]], [[71,28,14]], [[51,28,16]], [[40,28,18]]],
        // v19-40 simplified
        19 => [[[113,28,7]], [[72,26,14]], [[54,26,18]], [[42,26,20]]],
        20 => [[[107,28,7]], [[74,26,16]], [[57,28,18]], [[44,28,22]]],
        25 => [[[91,26,12]], [[86,28,20]], [[68,28,24]], [[55,28,28]]],
        30 => [[[132,30,12]], [[114,28,24]], [[98,28,28]], [[80,28,32]]],
        35 => [[[140,30,14]], [[130,28,30]], [[112,28,34]], [[96,28,38]]],
        40 => [[[156,30,16]], [[146,28,36]], [[126,28,40]], [[108,28,44]]],
    ];

    // Format info: [EC level][mask]
    private const FORMAT_INFO = [
        [0x5412, 0x5125, 0x5E7C, 0x5B4B, 0x45F9, 0x40CE, 0x4F97, 0x4AA0],
        [0x77C4, 0x72F3, 0x7DAA, 0x789D, 0x662F, 0x6318, 0x6C41, 0x6976],
        [0x1689, 0x13BE, 0x1CE7, 0x19D0, 0x0762, 0x0255, 0x0D0C, 0x083B],
        [0x355F, 0x3068, 0x3F31, 0x3A06, 0x24B4, 0x2183, 0x2EDA, 0x2BED],
    ];

    private const VERSION_INFO = [
        7  => 0x07C94, 8 => 0x085BC, 9 => 0x09A99, 10 => 0x0A4D3,
        11 => 0x0BBF6, 12 => 0x0C762, 13 => 0x0D847, 14 => 0x0E60D,
        15 => 0x0F928, 16 => 0x10B78, 17 => 0x1145D, 18 => 0x12A17,
        19 => 0x13532, 20 => 0x149A6, 21 => 0x15683, 22 => 0x168C9,
        23 => 0x177EC, 24 => 0x18EC4, 25 => 0x191E1, 26 => 0x1AFAB,
        27 => 0x1B08E, 28 => 0x1CC1A, 29 => 0x1D33F, 30 => 0x1ED75,
        31 => 0x1F250, 32 => 0x209D5, 33 => 0x216F0, 34 => 0x228BA,
        35 => 0x2379F, 36 => 0x24B0B, 37 => 0x2542E, 38 => 0x26A64,
        39 => 0x27541, 40 => 0x28C69,
    ];

    // --- Public API ---
    public function setText(string $text): static { $this->text = $text; return $this; }
    public function setSize(int $size): static { $this->size = max(21, $size); return $this; }
    public function setMargin(int $margin): static { $this->margin = max(0, $margin); return $this; }
    public function setErrorLevel(string $level): static { $this->errorLevel = strtoupper($level); return $this; }
    public function setForeground(int $rgb): static { $this->foreground = $rgb; return $this; }
    public function setBackground(int $rgb): static { $this->background = $rgb; return $this; }

    public function render(): GdImage
    {
        if (empty($this->text)) {
            throw new InvalidArgumentException('QR code text cannot be empty');
        }

        $ecLevelIndex = strpos('LMQH', $this->errorLevel);
        if ($ecLevelIndex === false) {
            $ecLevelIndex = 3;
        }

        $bytes = $this->encodeBytes($this->text);
        $version = $this->findVersion(count($bytes), $ecLevelIndex);
        $moduleCount = 17 + $version * 4;
        $dataBits = $this->buildDataBits($bytes, $version, $ecLevelIndex);

        $modules = array_fill(0, $moduleCount, array_fill(0, $moduleCount, null));

        $this->placeFinders($modules, $moduleCount);
        $this->placeTiming($modules, $moduleCount);
        $this->placeAlignments($modules, $version);
        $this->placeReserved($modules, $moduleCount, $version);
        $this->placeData($modules, $dataBits, $moduleCount);

        $mask = $this->bestMask($modules, $moduleCount);
        $this->applyMask($modules, $moduleCount, $mask);
        $this->placeFormat($modules, $ecLevelIndex, $mask, $moduleCount);
        if ($version >= 7) {
            $this->placeVersion($modules, $version, $moduleCount);
        }

        return $this->renderImage($modules, $moduleCount);
    }

    // --- Encoding ---
    private function encodeBytes(string $text): array
    {
        $bytes = [];
        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $bytes[] = ord($text[$i]);
        }
        return $bytes;
    }

    private function findVersion(int $byteCount, int $ecLevel): int
    {
        for ($v = 1; $v <= 40; $v++) {
            $capacity = $this->dataCapacity($v, $ecLevel);
            if ($capacity >= $byteCount) {
                return $v;
            }
        }
        throw new InvalidArgumentException('Data too large for QR code');
    }

    private function dataCapacity(int $version, int $ecLevel): int
    {
        if (!isset(self::RS_BLOCKS[$version])) {
            $version = $this->nearestVersion($version);
        }
        $total = 0;
        foreach (self::RS_BLOCKS[$version][$ecLevel] as $block) {
            $total += $block[0] * $block[2];
        }
        return $total;
    }

    private function nearestVersion(int $v): int
    {
        $known = array_keys(self::RS_BLOCKS);
        $closest = $known[0];
        foreach ($known as $k) {
            if (abs($k - $v) < abs($closest - $v)) {
                $closest = $k;
            }
        }
        return $closest;
    }

    private function totalCodewords(int $version, int $ecLevel): int
    {
        $version = $this->nearestVersion($version);
        $total = 0;
        foreach (self::RS_BLOCKS[$version][$ecLevel] as $block) {
            $total += ($block[0] + $block[1]) * $block[2];
        }
        return $total;
    }

    private function buildDataBits(array $bytes, int $version, int $ecLevel): string
    {
        $totalCw = $this->totalCodewords($version, $ecLevel);

        // Mode: byte = 0100
        $bits = '0100';
        // Char count indicator
        $countBits = $version <= 9 ? 8 : 16;
        $bits .= str_pad(decbin(count($bytes)), $countBits, '0', STR_PAD_LEFT);
        // Data bytes
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }
        // Terminator (up to 4 bits)
        $bits .= '0000';
        while (strlen($bits) % 8 !== 0) { $bits .= '0'; }

        // Pad to total codewords
        $padBytes = ['11101100', '00010001'];
        $pi = 0;
        while (strlen($bits) < $totalCw * 8) {
            $bits .= $padBytes[$pi];
            $pi = 1 - $pi;
        }

        // Truncate to exact codeword count
        return substr($bits, 0, $totalCw * 8);
    }

    // --- Module placement ---
    private function placeFinders(array &$m, int $n): void
    {
        $positions = [[0, 0], [$n - 7, 0], [0, $n - 7]];
        foreach ($positions as [$r, $c]) {
            for ($i = -1; $i <= 7; $i++) {
                for ($j = -1; $j <= 7; $j++) {
                    $rr = $r + $i; $cc = $c + $j;
                    if ($rr < 0 || $rr >= $n || $cc < 0 || $cc >= $n) continue;
                    $m[$rr][$cc] = ($i >= 0 && $i <= 6 && $j >= 0 && $j <= 6) ? (
                        $i === 0 || $i === 6 || $j === 0 || $j === 6 || ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4)
                    ) : false;
                }
            }
        }
    }

    private function placeTiming(array &$m, int $n): void
    {
        for ($i = 8; $i < $n - 8; $i++) {
            if ($m[$i][6] === null) $m[$i][6] = $i % 2 === 0;
            if ($m[6][$i] === null) $m[6][$i] = $i % 2 === 0;
        }
    }

    private function placeAlignments(array &$m, int $v): void
    {
        $pos = self::ALIGNMENTS[$v] ?? [];
        foreach ($pos as $r) {
            foreach ($pos as $c) {
                if (($r < 9 && $c < 9) || ($r < 9 && $c > count($m) - 10) || ($r > count($m) - 10 && $c < 9)) continue;
                for ($i = -2; $i <= 2; $i++) {
                    for ($j = -2; $j <= 2; $j++) {
                        $m[$r + $i][$c + $j] = abs($i) === 2 || abs($j) === 2 || ($i === 0 && $j === 0);
                    }
                }
            }
        }
    }

    private function placeReserved(array &$m, int $n, int $v): void
    {
        // Format info area around top-left finder
        for ($i = 0; $i <= 8; $i++) {
            if ($m[$i][8] === null) $m[$i][8] = null;
            if ($m[8][$i] === null) $m[8][$i] = null;
        }
        // Top-right finder
        for ($i = $n - 8; $i < $n; $i++) {
            if ($m[8][$i] === null) $m[8][$i] = null;
        }
        // Bottom-left finder
        for ($i = $n - 7; $i < $n; $i++) {
            if ($m[$i][8] === null) $m[$i][8] = null;
        }
        // Dark module
        $m[$n - 8][8] = true;
        // Version info area
        if ($v >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $m[$n - 11 + $j][$i] = null;
                    $m[$i][$n - 11 + $j] = null;
                }
            }
        }
    }

    private function placeData(array &$m, string $bits, int $n): void
    {
        $idx = 0;
        $up = true;
        $col = $n - 1;

        while ($col > 0) {
            if ($col === 6) $col--;
            $rows = $up ? range($n - 1, 0, -1) : range(0, $n - 1);
            foreach ($rows as $row) {
                for ($c = 0; $c < 2; $c++) {
                    $cc = $col - $c;
                    if ($m[$row][$cc] === null) {
                        $m[$row][$cc] = $idx < strlen($bits) && $bits[$idx] === '1';
                        $idx++;
                    }
                }
            }
            $up = !$up;
            $col -= 2;
        }
    }

    // --- Mask patterns ---
    private function applyMask(array &$m, int $n, int $mask): void
    {
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($m[$r][$c] !== null && is_bool($m[$r][$c])) {
                    $invert = match ($mask) {
                        0 => ($r + $c) % 2 === 0,
                        1 => $r % 2 === 0,
                        2 => $c % 3 === 0,
                        3 => ($r + $c) % 3 === 0,
                        4 => (intval($r / 2) + intval($c / 3)) % 2 === 0,
                        5 => ($r * $c) % 2 + ($r * $c) % 3 === 0,
                        6 => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0,
                        7 => (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0,
                        default => false,
                    };
                    if ($invert) $m[$r][$c] = !$m[$r][$c];
                }
            }
        }
    }

    private function bestMask(array $modules, int $n): int
    {
        $bestMask = 0;
        $bestScore = PHP_INT_MAX;
        $test = $modules;

        for ($mask = 0; $mask < 8; $mask++) {
            $copy = array_map(fn($row) => array_map(fn($v) => $v, $row), $test);
            $this->applyMask($copy, $n, $mask);
            $score = $this->penalty($copy, $n);
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestMask = $mask;
            }
        }

        return $bestMask;
    }

    private function penalty(array $m, int $n): int
    {
        $p = 0;
        // Condition 1: Adjacent same-color modules
        for ($r = 0; $r < $n; $r++) {
            $cnt = 1;
            for ($c = 1; $c < $n; $c++) {
                if ($m[$r][$c] === $m[$r][$c - 1]) { $cnt++; }
                else { if ($cnt >= 5) $p += 3 + ($cnt - 5); $cnt = 1; }
            }
            if ($cnt >= 5) $p += 3 + ($cnt - 5);
        }
        for ($c = 0; $c < $n; $c++) {
            $cnt = 1;
            for ($r = 1; $r < $n; $r++) {
                if ($m[$r][$c] === $m[$r - 1][$c]) { $cnt++; }
                else { if ($cnt >= 5) $p += 3 + ($cnt - 5); $cnt = 1; }
            }
            if ($cnt >= 5) $p += 3 + ($cnt - 5);
        }
        // Condition 2: 2x2 blocks
        for ($r = 0; $r < $n - 1; $r++) {
            for ($c = 0; $c < $n - 1; $c++) {
                if ($m[$r][$c] === $m[$r][$c+1] && $m[$r][$c] === $m[$r+1][$c] && $m[$r][$c] === $m[$r+1][$c+1]) {
                    $p += 3;
                }
            }
        }
        // Condition 3: 1:1:3:1:1 patterns
        $p += $this->penaltyPatterns($m, $n);
        // Condition 4: Dark/light ratio
        $dark = 0;
        for ($r = 0; $r < $n; $r++) for ($c = 0; $c < $n; $c++) if ($m[$r][$c]) $dark++;
        $ratio = intval(abs($dark * 100 / ($n * $n) - 50) / 5);
        $p += $ratio * 10;

        return $p;
    }

    private function penaltyPatterns(array $m, int $n): int
    {
        $p = 0;
        $pattern = [true, false, true, true, true, false, true, false, false, false, false];
        // Horizontal
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n - 10; $c++) {
                $match = true;
                for ($k = 0; $k < 11; $k++) {
                    if ($m[$r][$c + $k] !== $pattern[$k]) { $match = false; break; }
                }
                if ($match) $p += 40;
            }
        }
        // Vertical
        for ($c = 0; $c < $n; $c++) {
            for ($r = 0; $r < $n - 10; $r++) {
                $match = true;
                for ($k = 0; $k < 11; $k++) {
                    if ($m[$r + $k][$c] !== $pattern[$k]) { $match = false; break; }
                }
                if ($match) $p += 40;
            }
        }
        return $p;
    }

    // --- Format & Version info ---
    private function placeFormat(array &$m, int $ecLevel, int $mask, int $n): void
    {
        $info = self::FORMAT_INFO[$ecLevel][$mask];
        for ($i = 0; $i < 15; $i++) {
            $bit = ($info >> $i) & 1;
            // Around top-left
            if ($i < 6) { $m[$i][8] = (bool)$bit; }
            elseif ($i < 8) { $m[$i + 1][8] = (bool)$bit; }
            elseif ($i < 9) { $m[8][14 - $i] = (bool)$bit; }
            else { $j = 14 - $i; $m[8][$j] = (bool)$bit; }
            // Split bottom-left and top-right
            if ($i < 8) {
                $m[8][$n - 1 - $i] = (bool)$bit;
            } else {
                $m[$n - 1 - (14 - $i)][8] = (bool)$bit;
            }
        }
    }

    private function placeVersion(array &$m, int $version, int $n): void
    {
        $info = self::VERSION_INFO[$version] ?? 0;
        for ($i = 0; $i < 18; $i++) {
            $bit = ($info >> $i) & 1;
            $r = $n - 11 + ($i % 3);
            $c = intval($i / 3);
            $m[$r][$c] = (bool)$bit;
            $m[$c][$r] = (bool)$bit;
        }
    }

    // --- Render ---
    private function renderImage(array $modules, int $moduleCount): GdImage
    {
        $totalCount = $moduleCount + $this->margin * 2;
        $scale = intval($this->size / $totalCount);
        $imgSize = $totalCount * $scale;

        $img = imagecreatetruecolor($imgSize, $imgSize);
        $fg = imagecolorallocate($img, ($this->foreground >> 16) & 0xFF, ($this->foreground >> 8) & 0xFF, $this->foreground & 0xFF);
        $bg = imagecolorallocate($img, ($this->background >> 16) & 0xFF, ($this->background >> 8) & 0xFF, $this->background & 0xFF);
        imagefill($img, 0, 0, $bg);

        for ($r = 0; $r < $moduleCount; $r++) {
            for ($c = 0; $c < $moduleCount; $c++) {
                if (!empty($modules[$r][$c])) {
                    imagefilledrectangle(
                        $img,
                        ($c + $this->margin) * $scale,
                        ($r + $this->margin) * $scale,
                        ($c + $this->margin + 1) * $scale - 1,
                        ($r + $this->margin + 1) * $scale - 1,
                        $fg
                    );
                }
            }
        }

        return $img;
    }
}
