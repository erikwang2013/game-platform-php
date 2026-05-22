<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Drivers;

use InvalidArgumentException;
use RuntimeException;

class GdDriver implements ImageDriverInterface
{
    private $resource;
    private int $width = 0;
    private int $height = 0;

    public function load(string $path): static
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("File not found: $path");
        }
        $info = getimagesize($path);
        if ($info === false) {
            throw new RuntimeException("Cannot read image: $path");
        }
        $this->resource = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => imagecreatefrompng($path),
            IMAGETYPE_GIF  => imagecreatefromgif($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default        => throw new RuntimeException("Unsupported image type: " . $info[2]),
        };
        $this->width  = imagesx($this->resource);
        $this->height = imagesy($this->resource);
        return $this;
    }

    public function create(int $width, int $height): static
    {
        $this->resource = imagecreatetruecolor($width, $height);
        imagealphablending($this->resource, true);
        imagesavealpha($this->resource, true);
        $this->width  = $width;
        $this->height = $height;
        $transparent = imagecolorallocatealpha($this->resource, 0, 0, 0, 127);
        imagefill($this->resource, 0, 0, $transparent);
        return $this;
    }

    public function resize(int $width, int $height): static
    {
        $new = imagecreatetruecolor($width, $height);
        imagealphablending($new, true);
        imagesavealpha($new, true);
        $transparent = imagecolorallocatealpha($new, 0, 0, 0, 127);
        imagefill($new, 0, 0, $transparent);
        imagecopyresampled($new, $this->resource, 0, 0, 0, 0, $width, $height, $this->width, $this->height);
        imagedestroy($this->resource);
        $this->resource = $new;
        $this->width  = $width;
        $this->height = $height;
        return $this;
    }

    public function rotate(float $angle, string $bgColor = '#000000'): static
    {
        $rgb = $this->hexToRgb($bgColor);
        $bg = imagecolorallocate($this->resource, $rgb[0], $rgb[1], $rgb[2]);
        $rotated = imagerotate($this->resource, -$angle, $bg);
        if ($rotated === false) {
            throw new RuntimeException("Image rotation failed");
        }
        imagedestroy($this->resource);
        $this->resource = $rotated;
        imagesavealpha($this->resource, true);
        $this->width  = imagesx($this->resource);
        $this->height = imagesy($this->resource);
        return $this;
    }

    public function crop(int $x, int $y, int $width, int $height): static
    {
        $new = imagecreatetruecolor($width, $height);
        imagealphablending($new, true);
        imagesavealpha($new, true);
        $transparent = imagecolorallocatealpha($new, 0, 0, 0, 127);
        imagefill($new, 0, 0, $transparent);
        imagecopy($new, $this->resource, 0, 0, $x, $y, $width, $height);
        imagedestroy($this->resource);
        $this->resource = $new;
        $this->width  = $width;
        $this->height = $height;
        return $this;
    }

    public function text(string $text, int $x, int $y, array $options = []): static
    {
        $fontFile = $options['font'] ?? null;
        $size     = $options['size'] ?? 16;
        $color    = $options['color'] ?? '#000000';
        $rgb      = $this->hexToRgb($color);
        $angle    = $options['angle'] ?? 0;
        $maxWidth = $options['maxWidth'] ?? 0;
        $align    = $options['align'] ?? 'left';
        $lineHeight = $options['lineHeight'] ?? intval($size * 1.5);

        $alloc = imagecolorallocate($this->resource, $rgb[0], $rgb[1], $rgb[2]);

        if ($fontFile && is_file($fontFile)) {
            $lines = ($maxWidth > 0) ? $this->wrapTextTtf($text, $fontFile, $size, $maxWidth) : explode("\n", $text);
            foreach ($lines as $i => $line) {
                $bbox = imagettfbbox($size, $angle, $fontFile, $line);
                $lineW = $bbox[2] - $bbox[0];
                $lx = match ($align) {
                    'center' => $x - intval($lineW / 2),
                    'right'  => $x - $lineW,
                    default  => $x,
                };
                imagettftext($this->resource, $size, $angle, $lx, $y + $i * $lineHeight, $alloc, $fontFile, $line);
            }
        } else {
            $lines = ($maxWidth > 0) ? $this->wrapTextBuiltin($text, $maxWidth) : explode("\n", $text);
            foreach ($lines as $i => $line) {
                $lx = match ($align) {
                    'center' => $x - intval(strlen($line) * imagefontwidth(5) / 2),
                    'right'  => $x - intval(strlen($line) * imagefontwidth(5)),
                    default  => $x,
                };
                imagestring($this->resource, 5, $lx, $y + $i * $lineHeight, $line, $alloc);
            }
        }

        return $this;
    }

    public function image(ImageDriverInterface $overlay, int $x, int $y, array $options = []): static
    {
        $ov = $overlay->getResource();
        $ovW = imagesx($ov);
        $ovH = imagesy($ov);

        $destW = $options['width'] ?? $ovW;
        $destH = $options['height'] ?? $ovH;

        if (($options['radius'] ?? 0) > 0) {
            $ov = $this->roundCornersGD($ov, intval($options['radius']));
        }

        if (isset($options['shadow'])) {
            $this->drawShadowGD($options['shadow'], $x, $y, $destW, $destH);
        }

        imagecopyresampled($this->resource, $ov, $x, $y, 0, 0, $destW, $destH, $ovW, $ovH);
        return $this;
    }

    public function rectangle(int $x, int $y, int $width, int $height, array $options = []): static
    {
        $color  = $options['color'] ?? '#FFFFFF';
        $rgb    = $this->hexToRgb($color);
        $radius = intval($options['radius'] ?? 0);
        $filled = $options['filled'] ?? true;

        $alpha = isset($options['opacity']) ? intval((1 - $options['opacity']) * 127) : 0;
        if (strlen(ltrim($color, '#')) === 8) {
            $alpha = 127 - intval(hexdec(substr(ltrim($color, '#'), 6, 2)) / 2);
        }
        $alloc = imagecolorallocatealpha($this->resource, $rgb[0], $rgb[1], $rgb[2], $alpha);

        if ($radius > 0) {
            $this->roundedRectGD($x, $y, $x + $width - 1, $y + $height - 1, $radius, $alloc, $filled);
        } elseif ($filled) {
            imagefilledrectangle($this->resource, $x, $y, $x + $width - 1, $y + $height - 1, $alloc);
        } else {
            imagerectangle($this->resource, $x, $y, $x + $width - 1, $y + $height - 1, $alloc);
        }

        return $this;
    }

    public function ellipse(int $cx, int $cy, int $rx, int $ry, array $options = []): static
    {
        $color  = $options['color'] ?? '#FFFFFF';
        $rgb    = $this->hexToRgb($color);
        $filled = $options['filled'] ?? true;
        $alloc  = imagecolorallocate($this->resource, $rgb[0], $rgb[1], $rgb[2]);

        if ($filled) {
            imagefilledellipse($this->resource, $cx, $cy, $rx * 2, $ry * 2, $alloc);
        } else {
            imageellipse($this->resource, $cx, $cy, $rx * 2, $ry * 2, $alloc);
        }

        return $this;
    }

    public function line(int $x1, int $y1, int $x2, int $y2, array $options = []): static
    {
        $color = $options['color'] ?? '#000000';
        $rgb   = $this->hexToRgb($color);
        $alloc = imagecolorallocate($this->resource, $rgb[0], $rgb[1], $rgb[2]);
        imagesetthickness($this->resource, max(1, intval($options['width'] ?? 1)));
        imageline($this->resource, $x1, $y1, $x2, $y2, $alloc);
        imagesetthickness($this->resource, 1);
        return $this;
    }

    public function blur(int $radius = 1): static
    {
        for ($i = 0; $i < min($radius, 10); $i++) {
            imagefilter($this->resource, IMG_FILTER_GAUSSIAN_BLUR);
        }
        return $this;
    }

    public function pixelate(int $blockSize = 3): static
    {
        imagefilter($this->resource, IMG_FILTER_PIXELATE, max(1, $blockSize), true);
        return $this;
    }

    public function save(string $path, string $format = 'jpg', int $quality = 90): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return match (strtolower($format)) {
            'png'  => imagepng($this->resource, $path, intval(9 - min(9, max(0, intval($quality / 10))))),
            'gif'  => imagegif($this->resource, $path),
            'webp' => imagewebp($this->resource, $path, $quality),
            default => imagejpeg($this->resource, $path, $quality),
        };
    }

    public function output(string $format = 'jpg', int $quality = 90): string
    {
        ob_start();
        match (strtolower($format)) {
            'png'  => imagepng($this->resource, null, intval(9 - min(9, max(0, intval($quality / 10))))),
            'gif'  => imagegif($this->resource),
            'webp' => imagewebp($this->resource, null, $quality),
            default => imagejpeg($this->resource, null, $quality),
        };
        $data = ob_get_clean();
        return 'data:image/' . strtolower($format) . ';base64,' . base64_encode($data);
    }

    public function getSize(): array
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    public function getResource(): mixed
    {
        return $this->resource;
    }

    public function setGdResource(\GdImage $gd): void
    {
        $this->destroy();
        $this->resource = $gd;
        $this->width  = imagesx($gd);
        $this->height = imagesy($gd);
    }

    public function clone(): static
    {
        $driver = new self();
        if ($this->resource !== null && $this->width > 0 && $this->height > 0) {
            $driver->create($this->width, $this->height);
            imagecopy($driver->resource, $this->resource, 0, 0, 0, 0, $this->width, $this->height);
        }
        return $driver;
    }

    public function destroy(): void
    {
        if ($this->resource !== null) {
            imagedestroy($this->resource);
            $this->resource = null;
        }
    }

    public function __destruct()
    {
        $this->destroy();
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function wrapTextTtf(string $text, string $fontFile, int $size, int $maxWidth): array
    {
        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            $chars = $this->splitText($paragraph);
            $current = '';
            foreach ($chars as $char) {
                $test = $current . $char;
                $bbox = imagettfbbox($size, 0, $fontFile, $test);
                $w = $bbox[2] - $bbox[0];
                if ($w > $maxWidth && $current !== '') {
                    $lines[] = $current;
                    $current = $char;
                } else {
                    $current = $test;
                }
            }
            if ($current !== '') {
                $lines[] = $current;
            }
        }
        return $lines ?: explode("\n", $text);
    }

    private function wrapTextBuiltin(string $text, int $maxWidth): array
    {
        $charWidth = imagefontwidth(5);
        $maxChars = max(1, intval($maxWidth / $charWidth));
        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            $start = 0;
            $len = mb_strlen($paragraph);
            while ($start < $len) {
                $lines[] = mb_substr($paragraph, $start, $maxChars);
                $start += $maxChars;
            }
        }
        return $lines ?: [$text];
    }

    private function splitText(string $text): array
    {
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
            preg_match_all('/./us', $text, $matches);
            return $matches[0];
        }
        return preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    }

    private function roundCornersGD($image, int $radius)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $mask = imagecreatetruecolor($w, $h);
        imagealphablending($mask, false);
        imagesavealpha($mask, true);
        $transparent = imagecolorallocatealpha($mask, 0, 0, 0, 127);
        imagefill($mask, 0, 0, $transparent);
        $color = imagecolorallocatealpha($mask, 0, 0, 0, 0);

        imagefilledarc($mask, $radius - 1, $radius - 1, $radius * 2, $radius * 2, 180, 270, $color, IMG_ARC_PIE);
        imagefilledarc($mask, $w - $radius, $radius - 1, $radius * 2, $radius * 2, 270, 360, $color, IMG_ARC_PIE);
        imagefilledarc($mask, $radius - 1, $h - $radius, $radius * 2, $radius * 2, 90, 180, $color, IMG_ARC_PIE);
        imagefilledarc($mask, $w - $radius, $h - $radius, $radius * 2, $radius * 2, 0, 90, $color, IMG_ARC_PIE);
        imagefilledrectangle($mask, $radius, 0, $w - $radius - 1, $h - 1, $color);
        imagefilledrectangle($mask, 0, $radius, $w - 1, $h - $radius - 1, $color);

        $result = imagecreatetruecolor($w, $h);
        imagealphablending($result, false);
        imagesavealpha($result, true);
        imagefill($result, 0, 0, $transparent);

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $alpha = ((imagecolorat($mask, $x, $y) >> 24) & 0x7F);
                if ($alpha < 64) {
                    imagesetpixel($result, $x, $y, imagecolorat($image, $x, $y));
                }
            }
        }
        imagedestroy($mask);
        return $result;
    }

    private function roundedRectGD(int $x1, int $y1, int $x2, int $y2, int $r, int $color, bool $filled): void
    {
        if ($filled) {
            imagefilledrectangle($this->resource, $x1 + $r, $y1, $x2 - $r, $y2, $color);
            imagefilledrectangle($this->resource, $x1, $y1 + $r, $x2, $y2 - $r, $color);
            imagefilledarc($this->resource, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color, IMG_ARC_PIE);
            imagefilledarc($this->resource, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $color, IMG_ARC_PIE);
            imagefilledarc($this->resource, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $color, IMG_ARC_PIE);
            imagefilledarc($this->resource, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $color, IMG_ARC_PIE);
        } else {
            imagerectangle($this->resource, $x1, $y1, $x2, $y2, $color);
        }
    }

    private function drawShadowGD(array $shadow, int $x, int $y, int $w, int $h): void
    {
        $sColor  = $shadow['color'] ?? '#00000033';
        $offsetX = intval($shadow['offsetX'] ?? 4);
        $offsetY = intval($shadow['offsetY'] ?? 4);
        $blur    = intval($shadow['blur'] ?? 8);
        $rgb     = $this->hexToRgb($sColor);

        $pad = $blur * 2;
        $sw = $w + $pad;
        $sh = $h + $pad;

        $shadowImg = imagecreatetruecolor($sw, $sh);
        imagealphablending($shadowImg, false);
        imagesavealpha($shadowImg, true);
        $transparent = imagecolorallocatealpha($shadowImg, 0, 0, 0, 127);
        imagefill($shadowImg, 0, 0, $transparent);

        $sAlloc = imagecolorallocatealpha($shadowImg, $rgb[0], $rgb[1], $rgb[2], 0);
        imagefilledrectangle($shadowImg, $blur, $blur, $blur + $w - 1, $blur + $h - 1, $sAlloc);

        for ($i = 0; $i < min($blur * 2, 20); $i++) {
            imagefilter($shadowImg, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagecopy(
            $this->resource, $shadowImg,
            $x + $offsetX - $blur, $y + $offsetY - $blur,
            0, 0, $sw, $sh
        );
        imagedestroy($shadowImg);
    }
}
