<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Drivers;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use InvalidArgumentException;
use RuntimeException;

class ImagickDriver implements ImageDriverInterface
{
    private Imagick $resource;

    public function load(string $path): static
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("File not found: $path");
        }
        $this->resource = new Imagick($path);
        return $this;
    }

    public function create(int $width, int $height): static
    {
        $this->resource = new Imagick();
        $this->resource->newImage($width, $height, new ImagickPixel('transparent'));
        $this->resource->setImageFormat('png');
        return $this;
    }

    public function resize(int $width, int $height): static
    {
        $this->resource->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
        return $this;
    }

    public function rotate(float $angle, string $bgColor = '#000000'): static
    {
        $this->resource->rotateImage(new ImagickPixel($bgColor), -$angle);
        return $this;
    }

    public function crop(int $x, int $y, int $width, int $height): static
    {
        $this->resource->cropImage($width, $height, $x, $y);
        $this->resource->setImagePage(0, 0, 0, 0);
        return $this;
    }

    public function text(string $text, int $x, int $y, array $options = []): static
    {
        $fontFile   = $options['font'] ?? null;
        $size       = $options['size'] ?? 16;
        $color      = $options['color'] ?? '#000000';
        $maxWidth   = $options['maxWidth'] ?? 0;
        $align      = $options['align'] ?? 'left';
        $lineHeight = $options['lineHeight'] ?? intval($size * 1.5);
        $angle      = $options['angle'] ?? 0;

        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel($color));
        $draw->setFontSize($size);
        if ($fontFile && is_file($fontFile)) {
            $draw->setFont($fontFile);
        }
        $draw->setTextAlignment(match ($align) {
            'center' => Imagick::ALIGN_CENTER,
            'right'  => Imagick::ALIGN_RIGHT,
            default  => Imagick::ALIGN_LEFT,
        });

        if ($maxWidth > 0 && $fontFile) {
            $lines = $this->wrapTextImagick($text, $draw, $maxWidth);
        } else {
            $lines = explode("\n", $text);
        }

        foreach ($lines as $i => $line) {
            $this->resource->annotateImage($draw, $x, $y + $i * $lineHeight, $angle, $line);
        }

        $draw->destroy();
        return $this;
    }

    public function image(ImageDriverInterface $overlay, int $x, int $y, array $options = []): static
    {
        $ov = $overlay->getResource();
        $destW = $options['width'] ?? $ov->getImageWidth();
        $destH = $options['height'] ?? $ov->getImageHeight();

        $ov->resizeImage($destW, $destH, Imagick::FILTER_LANCZOS, 1);

        if (($options['radius'] ?? 0) > 0) {
            $ov->roundCorners($options['radius'], $options['radius']);
        }

        if (isset($options['shadow'])) {
            $s = $options['shadow'];
            $shadow = clone $ov;
            $shadow->setImageBackgroundColor(new ImagickPixel($s['color'] ?? '#00000033'));
            $shadow->shadowImage($s['opacity'] ?? 50, $s['blur'] ?? 8, $s['offsetX'] ?? 4, $s['offsetY'] ?? 4);
            $this->resource->compositeImage($shadow, Imagick::COMPOSITE_OVER, $x, $y);
            $shadow->destroy();
        }

        $this->resource->compositeImage($ov, Imagick::COMPOSITE_OVER, $x, $y);
        return $this;
    }

    public function rectangle(int $x, int $y, int $width, int $height, array $options = []): static
    {
        $draw = new ImagickDraw();
        $color = $options['color'] ?? '#FFFFFF';

        if (isset($options['opacity'])) {
            $draw->setFillOpacity(floatval($options['opacity']));
        } else {
            $draw->setFillColor(new ImagickPixel($color));
        }

        if (!($options['filled'] ?? true)) {
            $draw->setFillOpacity(0);
            $draw->setStrokeColor(new ImagickPixel($color));
            $draw->setStrokeWidth(intval($options['strokeWidth'] ?? 1));
        }

        $radius = intval($options['radius'] ?? 0);
        if ($radius > 0) {
            $draw->roundRectangle($x, $y, $x + $width - 1, $y + $height - 1, $radius, $radius);
        } else {
            $draw->rectangle($x, $y, $x + $width - 1, $y + $height - 1);
        }

        $this->resource->drawImage($draw);
        $draw->destroy();
        return $this;
    }

    public function ellipse(int $cx, int $cy, int $rx, int $ry, array $options = []): static
    {
        $draw = new ImagickDraw();
        $color = $options['color'] ?? '#FFFFFF';
        $draw->setFillColor(new ImagickPixel($color));

        if (!($options['filled'] ?? true)) {
            $draw->setFillOpacity(0);
            $draw->setStrokeColor(new ImagickPixel($color));
            $draw->setStrokeWidth(1);
        }

        $draw->ellipse($cx, $cy, $rx, $ry, 0, 360);
        $this->resource->drawImage($draw);
        $draw->destroy();
        return $this;
    }

    public function line(int $x1, int $y1, int $x2, int $y2, array $options = []): static
    {
        $draw = new ImagickDraw();
        $draw->setStrokeColor(new ImagickPixel($options['color'] ?? '#000000'));
        $draw->setStrokeWidth(max(1, intval($options['width'] ?? 1)));
        $draw->line($x1, $y1, $x2, $y2);
        $this->resource->drawImage($draw);
        $draw->destroy();
        return $this;
    }

    public function blur(int $radius = 1): static
    {
        $this->resource->blurImage($radius, max(1, $radius * 0.5));
        return $this;
    }

    public function pixelate(int $blockSize = 3): static
    {
        $w = $this->resource->getImageWidth();
        $h = $this->resource->getImageHeight();
        $bs = max(1, $blockSize);
        $this->resource->scaleImage(max(1, intval($w / $bs)), max(1, intval($h / $bs)));
        $this->resource->scaleImage($w, $h);
        return $this;
    }

    public function save(string $path, string $format = 'jpg', int $quality = 90): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->resource->setImageFormat(strtolower($format));
        if (in_array(strtolower($format), ['jpg', 'jpeg'])) {
            $this->resource->setImageCompression(Imagick::COMPRESSION_JPEG);
            $this->resource->setImageCompressionQuality($quality);
        }
        return $this->resource->writeImage($path);
    }

    public function output(string $format = 'jpg', int $quality = 90): string
    {
        $this->resource->setImageFormat(strtolower($format));
        if (in_array(strtolower($format), ['jpg', 'jpeg'])) {
            $this->resource->setImageCompression(Imagick::COMPRESSION_JPEG);
            $this->resource->setImageCompressionQuality($quality);
        }
        $data = $this->resource->getImageBlob();
        return 'data:image/' . strtolower($format) . ';base64,' . base64_encode($data);
    }

    public function getSize(): array
    {
        return [
            'width'  => $this->resource->getImageWidth(),
            'height' => $this->resource->getImageHeight(),
        ];
    }

    public function getResource(): mixed
    {
        return $this->resource;
    }

    public function clone(): static
    {
        $driver = new self();
        $driver->resource = clone $this->resource;
        return $driver;
    }

    public function destroy(): void
    {
        if (isset($this->resource)) {
            $this->resource->destroy();
        }
    }

    public function __destruct()
    {
        $this->destroy();
    }

    private function wrapTextImagick(string $text, ImagickDraw $draw, int $maxWidth): array
    {
        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            $chars = $this->splitText($paragraph);
            $current = '';
            foreach ($chars as $char) {
                $test = $current . $char;
                $metrics = $this->resource->queryFontMetrics($draw, $test);
                if ($metrics['textWidth'] > $maxWidth && $current !== '') {
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

    private function splitText(string $text): array
    {
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
            preg_match_all('/./us', $text, $matches);
            return $matches[0];
        }
        return preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    }
}
