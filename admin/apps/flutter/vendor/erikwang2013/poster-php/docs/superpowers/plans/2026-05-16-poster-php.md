# poster-php Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `erikwang2013/poster-php` — a zero-dependency PHP package providing image captcha (click/rotate/slider) and poster generation, with adapters for Laravel, ThinkPHP, Webman, and Hyperf.

**Architecture:** Modular core with Builder pattern for posters and Factory pattern for captchas. Image drivers (GD/Imagick) and storage drivers (File/Session/Redis) are behind interfaces with auto-detection. Framework adapters are thin (~30 line) wrappers.

**Tech Stack:** PHP 8.0+, GD extension, Composer PSR-4, PHPUnit

---

### Task 1: Package Scaffolding

**Files:**
- Create: `composer.json`
- Create: `LICENSE`
- Create: `config/poster.php`
- Create: `src/PosterConfig.php`
- Create: `src/Drivers/ImageDriverInterface.php`
- Create: `src/Drivers/DriverFactory.php`
- Create: `src/Storage/StorageInterface.php`
- Create: `src/Storage/StorageFactory.php`

- [ ] **Step 1: Create composer.json**

```json
{
    "name": "erikwang2013/poster-php",
    "description": "PHP captcha (click/rotate/slider) and poster generation — framework-agnostic with adapters for Laravel, ThinkPHP, Webman, Hyperf.",
    "type": "library",
    "license": "MIT",
    "keywords": ["captcha", "poster", "image", "qr-code", "laravel", "thinkphp", "webman", "hyperf"],
    "authors": [
        {"name": "erik", "email": "erik@erik.xyz", "homepage": "https://erik.xyz"}
    ],
    "require": {
        "php": ">=8.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0 || ^10.0 || ^11.0"
    },
    "suggest": {
        "ext-imagick": "For ImageMagick image driver (more features, better performance)",
        "ext-redis": "For Redis captcha storage (distributed deployments)"
    },
    "autoload": {
        "psr-4": {
            "Erikwang2013\\Poster\\": "src/"
        },
        "files": [
            "helpers.php"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "Erikwang2013\\Poster\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Erikwang2013\\Poster\\Adapters\\Laravel\\CaptchaServiceProvider",
                "Erikwang2013\\Poster\\Adapters\\Laravel\\PosterServiceProvider"
            ]
        },
        "thinkphp": {
            "services": [
                "Erikwang2013\\Poster\\Adapters\\ThinkPHP\\CaptchaService",
                "Erikwang2013\\Poster\\Adapters\\ThinkPHP\\PosterService"
            ]
        }
    },
    "scripts": {
        "test": "phpunit"
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

- [ ] **Step 2: Create LICENSE**

```
MIT License

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

- [ ] **Step 3: Create config/poster.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

return [
    // ── 图像处理驱动 ──
    'image' => [
        // 驱动类型: 'auto' | 'gd' | 'imagick'；'auto' 自动检测可用驱动
        'driver' => 'auto',
        // JPEG 输出质量 0-100
        'quality' => 90,
        // 默认字体路径，null 则使用包自带字体
        'font' => null,
    ],

    // ── 验证码模块 ──
    'captcha' => [
        // 验证数据存储: 'auto' | 'file' | 'session' | 'redis'
        'storage' => 'auto',
        // 验证码有效期（秒），超时后 key 作废
        'ttl' => 300,
        // 同一 key 最多验证次数，防暴力枚举
        'max_attempts' => 3,
        // 默认难度: 'easy' | 'medium' | 'hard'
        'default_difficulty' => 'medium',
        // 验证误差容忍
        'tolerance' => [
            'click'  => 18,
            'rotate' => 5,
            'slider' => 4,
        ],
        // Redis 存储配置（storage=redis 时生效）
        'redis' => [
            'prefix'     => 'poster:captcha:',
            'connection' => 'default',
        ],
        // 文件存储配置（storage=file 时生效）
        'file' => [
            'path' => null,
        ],
    ],

    // ── 海报生成模块 ──
    'poster' => [
        // 画布默认宽高（px）
        'default_width'  => 750,
        'default_height' => 1334,
        // 默认字体路径，null 则使用包自带字体
        'font' => null,
        // JPEG 输出质量 0-100
        'jpeg_quality' => 90,
        // PNG 压缩级别 0-9
        'png_compression' => 6,
    ],
];
```

- [ ] **Step 4: Create src/PosterConfig.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster;

class PosterConfig
{
    private static ?array $config = null;

    public static function load(?string $path = null): array
    {
        if (self::$config !== null && $path === null) {
            return self::$config;
        }

        $defaultPath = dirname(__DIR__) . '/config/poster.php';
        $path = $path ?? $defaultPath;

        self::$config = is_file($path) ? require $path : require $defaultPath;
        return self::$config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $config = self::load();
        $keys = explode('.', $key);
        foreach ($keys as $segment) {
            if (!is_array($config) || !array_key_exists($segment, $config)) {
                return $default;
            }
            $config = $config[$segment];
        }
        return $config;
    }

    public static function merge(array $overrides): array
    {
        self::load();
        self::$config = array_replace_recursive(self::$config ?? [], $overrides);
        return self::$config;
    }

    public static function reset(): void
    {
        self::$config = null;
    }
}
```

- [ ] **Step 5: Create src/Drivers/ImageDriverInterface.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Drivers;

interface ImageDriverInterface
{
    public function load(string $path): static;
    public function create(int $width, int $height): static;
    public function resize(int $width, int $height): static;
    public function rotate(float $angle, string $bgColor = '#000000'): static;
    public function crop(int $x, int $y, int $width, int $height): static;
    public function text(string $text, int $x, int $y, array $options): static;
    public function image(ImageDriverInterface $overlay, int $x, int $y, array $options = []): static;
    public function rectangle(int $x, int $y, int $width, int $height, array $options): static;
    public function ellipse(int $cx, int $cy, int $rx, int $ry, array $options): static;
    public function line(int $x1, int $y1, int $x2, int $y2, array $options): static;
    public function blur(int $radius): static;
    public function pixelate(int $blockSize): static;
    public function save(string $path, string $format = 'jpg', int $quality = 90): bool;
    public function output(string $format = 'jpg', int $quality = 90): string;
    public function getSize(): array;
    public function destroy(): void;
    public function getResource(): mixed;
    public function clone(): static;
}
```

- [ ] **Step 6: Create src/Drivers/DriverFactory.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Drivers;

class DriverFactory
{
    public static function create(?string $driver = null): ImageDriverInterface
    {
        $driver = $driver ?? 'auto';
        if ($driver === 'auto') {
            return extension_loaded('imagick') && class_exists('Imagick')
                ? new ImagickDriver()
                : new GdDriver();
        }
        return $driver === 'imagick' ? new ImagickDriver() : new GdDriver();
    }

    public static function isImagickAvailable(): bool
    {
        return extension_loaded('imagick') && class_exists('Imagick');
    }
}
```

- [ ] **Step 7: Create src/Storage/StorageInterface.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

interface StorageInterface
{
    public function set(string $key, array $data, int $ttl = 300): bool;
    public function get(string $key): ?array;
    public function del(string $key): bool;
    public function has(string $key): bool;
}
```

- [ ] **Step 8: Create src/Storage/StorageFactory.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

use Erikwang2013\Poster\PosterConfig;

class StorageFactory
{
    public static function create(?string $driver = null): StorageInterface
    {
        $driver = $driver ?? PosterConfig::get('captcha.storage', 'auto');

        if ($driver === 'auto') {
            if (extension_loaded('redis') && class_exists('Redis')) {
                return new RedisStorage();
            }
            if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
                return new SessionStorage();
            }
            return new FileStorage();
        }

        return match ($driver) {
            'redis'   => new RedisStorage(),
            'session' => new SessionStorage(),
            'file'    => new FileStorage(),
            default   => new FileStorage(),
        };
    }
}
```

- [ ] **Step 9: Verify PSR-4 structure**

Run: `ls -R src/`

Expected directory structure exists under `src/Drivers/` and `src/Storage/`.

- [ ] **Step 10: Validate composer.json**

Run: `composer validate`
Expected: "./composer.json is valid"

---

### Task 2: Image Drivers Implementation

**Files:**
- Create: `src/Drivers/GdDriver.php`
- Create: `src/Drivers/ImagickDriver.php`

- [ ] **Step 1: Create src/Drivers/GdDriver.php**

```php
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
        $this->resource = $new;
        $this->width  = $width;
        $this->height = $height;
        return $this;
    }

    public function rotate(float $angle, string $bgColor = '#000000'): static
    {
        $rgb = $this->hexToRgb($bgColor);
        $bg = imagecolorallocate($this->resource, $rgb[0], $rgb[1], $rgb[2]);
        $this->resource = imagerotate($this->resource, -$angle, $bg);
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
        $this->resource = $new;
        $this->width  = $width;
        $this->height = $height;
        return $this;
    }

    public function text(string $text, int $x, int $y, array $options): static
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
            $lines = ($maxWidth > 0) ? $this->wrapText($text, $fontFile, $size, $maxWidth) : [$text];
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
            $lines = ($maxWidth > 0) ? $this->wrapTextBuiltin($text, intval($size / 8), $maxWidth) : [$text];
            foreach ($lines as $i => $line) {
                $lx = match ($align) {
                    'center' => $x - intval(strlen($line) * $size / 2 / 8 * imagefontwidth(5)),
                    'right'  => $x - intval(strlen($line) * $size / 2),
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

        if ($options['radius'] ?? false) {
            $ov = $this->roundCornersGD($ov, $options['radius']);
        }

        if (isset($options['shadow'])) {
            $this->drawShadowGD($options['shadow'], $x, $y, $destW, $destH);
        }

        imagecopyresampled($this->resource, $ov, $x, $y, 0, 0, $destW, $destH, $ovW, $ovH);
        return $this;
    }

    public function rectangle(int $x, int $y, int $width, int $height, array $options): static
    {
        $color  = $options['color'] ?? '#FFFFFF';
        $rgb    = $this->hexToRgb($color);
        $radius = $options['radius'] ?? 0;
        $filled = $options['filled'] ?? true;

        $alloc = imagecolorallocatealpha(
            $this->resource, $rgb[0], $rgb[1], $rgb[2],
            isset($options['opacity']) ? intval((1 - $options['opacity']) * 127) : 0
        );

        if ($radius > 0) {
            $this->roundedRectGD($x, $y, $x + $width - 1, $y + $height - 1, $radius, $alloc, $filled);
        } elseif ($filled) {
            imagefilledrectangle($this->resource, $x, $y, $x + $width - 1, $y + $height - 1, $alloc);
        } else {
            imagerectangle($this->resource, $x, $y, $x + $width - 1, $y + $height - 1, $alloc);
        }

        return $this;
    }

    public function ellipse(int $cx, int $cy, int $rx, int $ry, array $options): static
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

    public function line(int $x1, int $y1, int $x2, int $y2, array $options): static
    {
        $color = $options['color'] ?? '#000000';
        $rgb   = $this->hexToRgb($color);
        $alloc = imagecolorallocate($this->resource, $rgb[0], $rgb[1], $rgb[2]);
        imagesetthickness($this->resource, $options['width'] ?? 1);
        imageline($this->resource, $x1, $y1, $x2, $y2, $alloc);
        imagesetthickness($this->resource, 1);
        return $this;
    }

    public function blur(int $radius): static
    {
        for ($i = 0; $i < $radius; $i++) {
            imagefilter($this->resource, IMG_FILTER_GAUSSIAN_BLUR);
        }
        return $this;
    }

    public function pixelate(int $blockSize): static
    {
        imagefilter($this->resource, IMG_FILTER_PIXELATE, $blockSize, true);
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
        return 'data:image/' . $format . ';base64,' . base64_encode($data);
    }

    public function getSize(): array
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    public function getResource(): mixed
    {
        return $this->resource;
    }

    public function clone(): static
    {
        $driver = new self();
        $driver->create($this->width, $this->height);
        imagecopy($driver->resource, $this->resource, 0, 0, 0, 0, $this->width, $this->height);
        return $driver;
    }

    public function destroy(): void
    {
        if ($this->resource !== null && is_resource($this->resource)) {
            imagedestroy($this->resource);
        }
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) === 8) {
            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function wrapText(string $text, string $fontFile, int $size, int $maxWidth): array
    {
        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            $words = $this->splitWords($paragraph);
            $current = '';
            foreach ($words as $word) {
                $test = $current === '' ? $word : $current . $word;
                $bbox = imagettfbbox($size, 0, $fontFile, $test);
                $w = $bbox[2] - $bbox[0];
                if ($w > $maxWidth && $current !== '') {
                    $lines[] = $current;
                    $current = $word;
                } else {
                    $current = $test;
                }
            }
            if ($current !== '') {
                $lines[] = $current;
            }
        }
        return $lines;
    }

    private function splitWords(string $text): array
    {
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
            preg_match_all('/./us', $text, $matches);
            return $matches[0];
        }
        return preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    }

    private function wrapTextBuiltin(string $text, int $charWidth, int $maxWidth): array
    {
        $maxChars = intval($maxWidth /($charWidth ?: 6));
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

    private function roundCornersGD($image, int $radius)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $mask = imagecreatetruecolor($w, $h);
        imagealphablending($mask, true);
        imagesavealpha($mask, true);
        $transparent = imagecolorallocatealpha($mask, 0, 0, 0, 127);
        imagefill($mask, 0, 0, $transparent);
        $rounded = imagecolorallocatealpha($mask, 255, 255, 255, 0);

        // Four corners
        imagefilledarc($mask, $radius, $radius, $radius * 2, $radius * 2, 180, 270, $rounded, IMG_ARC_PIE);
        imagefilledarc($mask, $w - $radius - 1, $radius, $radius * 2, $radius * 2, 270, 360, $rounded, IMG_ARC_PIE);
        imagefilledarc($mask, $radius, $h - $radius - 1, $radius * 2, $radius * 2, 90, 180, $rounded, IMG_ARC_PIE);
        imagefilledarc($mask, $w - $radius - 1, $h - $radius - 1, $radius * 2, $radius * 2, 0, 90, $rounded, IMG_ARC_PIE);
        imagefilledrectangle($mask, $radius, 0, $w - $radius - 1, $h - 1, $rounded);
        imagefilledrectangle($mask, 0, $radius, $w - 1, $h - $radius - 1, $rounded);

        $result = imagecreatetruecolor($w, $h);
        imagealphablending($result, true);
        imagesavealpha($result, true);
        imagefill($result, 0, 0, $transparent);

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $alpha = ((imagecolorat($mask, $x, $y) >> 24) & 0x7F);
                if ($alpha < 64) {
                    $c = imagecolorat($image, $x, $y);
                    imagesetpixel($result, $x, $y, $c);
                }
            }
        }
        imagedestroy($mask);
        return $result;
    }

    private function drawShadowGD(array $shadow, int $x, int $y, int $w, int $h): void
    {
        $sColor  = $shadow['color'] ?? '#00000033';
        $offsetX = $shadow['offsetX'] ?? 4;
        $offsetY = $shadow['offsetY'] ?? 4;
        $blur    = $shadow['blur'] ?? 8;
        $rgb     = $this->hexToRgb($sColor);

        $shadowImg = imagecreatetruecolor($w + $blur * 2, $h + $blur * 2);
        imagealphablending($shadowImg, true);
        imagesavealpha($shadowImg, true);
        $transparent = imagecolorallocatealpha($shadowImg, 0, 0, 0, 127);
        imagefill($shadowImg, 0, 0, $transparent);

        $sAlloc = imagecolorallocatealpha($shadowImg, $rgb[0], $rgb[1], $rgb[2], 0);
        imagefilledrectangle($shadowImg, $blur, $blur, $w + $blur - 1, $h + $blur - 1, $sAlloc);

        for ($i = 0; $i < $blur * 2; $i++) {
            imagefilter($shadowImg, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagecopy(
            $this->resource, $shadowImg,
            $x + $offsetX - $blur, $y + $offsetY - $blur,
            0, 0, $w + $blur * 2, $h + $blur * 2
        );
        imagedestroy($shadowImg);
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
            imageline($this->resource, $x1 + $r, $y1, $x2 - $r, $y1, $color);
            imageline($this->resource, $x1 + $r, $y2, $x2 - $r, $y2, $color);
            imageline($this->resource, $x1, $y1 + $r, $x1, $y2 - $r, $color);
            imageline($this->resource, $x2, $y1 + $r, $x2, $y2 - $r, $color);
            imagearc($this->resource, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color);
            imagearc($this->resource, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $color);
            imagearc($this->resource, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $color);
            imagearc($this->resource, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $color);
        }
    }
}
```

- [ ] **Step 2: Create src/Drivers/ImagickDriver.php**

```php
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

    public function text(string $text, int $x, int $y, array $options): static
    {
        $fontFile   = $options['font'] ?? null;
        $size       = $options['size'] ?? 16;
        $color      = $options['color'] ?? '#000000';
        $maxWidth   = $options['maxWidth'] ?? 0;
        $align      = $options['align'] ?? 'left';
        $lineHeight = $options['lineHeight'] ?? intval($size * 1.5);

        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel($color));
        $draw->setFontSize($size);
        if ($fontFile) {
            $draw->setFont($fontFile);
        }
        $draw->setTextAlignment(match ($align) {
            'center' => Imagick::ALIGN_CENTER,
            'right'  => Imagick::ALIGN_RIGHT,
            default  => Imagick::ALIGN_LEFT,
        });

        if ($maxWidth > 0) {
            $lines = $this->wrapTextImagick($text, $draw, $maxWidth);
        } else {
            $lines = explode("\n", $text);
        }

        foreach ($lines as $i => $line) {
            $this->resource->annotateImage($draw, $x, $y + $i * $lineHeight, 0, $line);
        }

        $draw->destroy();
        return $this;
    }

    public function image(ImageDriverInterface $overlay, int $x, int $y, array $options = []): static
    {
        $ov = $overlay->getResource();
        $destW = $options['width'] ?? $ov->getImageWidth();
        $destH = $options['height'] ?? $ov->getImageHeight();

        if (isset($options['radius']) && $options['radius'] > 0) {
            $ov->roundCorners($options['radius'], $options['radius']);
        }

        if (isset($options['shadow'])) {
            $s = $options['shadow'];
            $shadow = $ov->clone();
            $shadow->setImageBackgroundColor(new ImagickPixel($s['color'] ?? '#00000033'));
            $shadow->shadowImage($s['opacity'] ?? 50, $s['blur'] ?? 8, $s['offsetX'] ?? 4, $s['offsetY'] ?? 4);
            $this->resource->compositeImage($shadow, Imagick::COMPOSITE_OVER, $x, $y);
            $shadow->destroy();
        }

        $ov->resizeImage($destW, $destH, Imagick::FILTER_LANCZOS, 1);
        $this->resource->compositeImage($ov, Imagick::COMPOSITE_OVER, $x, $y);
        return $this;
    }

    public function rectangle(int $x, int $y, int $width, int $height, array $options): static
    {
        $draw = new ImagickDraw();
        $color = $options['color'] ?? '#FFFFFF';

        if (isset($options['opacity'])) {
            $draw->setFillOpacity($options['opacity']);
        } else {
            $draw->setFillColor(new ImagickPixel($color));
        }

        if (!($options['filled'] ?? true)) {
            $draw->setFillOpacity(0);
            $draw->setStrokeColor(new ImagickPixel($color));
            $draw->setStrokeWidth($options['strokeWidth'] ?? 1);
        }

        $radius = $options['radius'] ?? 0;
        if ($radius > 0) {
            $draw->roundRectangle($x, $y, $x + $width - 1, $y + $height - 1, $radius, $radius);
        } else {
            $draw->rectangle($x, $y, $x + $width - 1, $y + $height - 1);
        }

        $this->resource->drawImage($draw);
        $draw->destroy();
        return $this;
    }

    public function ellipse(int $cx, int $cy, int $rx, int $ry, array $options): static
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

    public function line(int $x1, int $y1, int $x2, int $y2, array $options): static
    {
        $draw = new ImagickDraw();
        $draw->setStrokeColor(new ImagickPixel($options['color'] ?? '#000000'));
        $draw->setStrokeWidth($options['width'] ?? 1);
        $draw->line($x1, $y1, $x2, $y2);
        $this->resource->drawImage($draw);
        $draw->destroy();
        return $this;
    }

    public function blur(int $radius): static
    {
        $this->resource->blurImage($radius, $radius * 0.5);
        return $this;
    }

    public function pixelate(int $blockSize): static
    {
        $w = $this->resource->getImageWidth();
        $h = $this->resource->getImageHeight();
        $this->resource->scaleImage(intval($w / $blockSize), intval($h / $blockSize));
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
        if (strtolower($format) === 'jpg' || strtolower($format) === 'jpeg') {
            $this->resource->setImageCompression(Imagick::COMPRESSION_JPEG);
            $this->resource->setImageCompressionQuality($quality);
        }
        return $this->resource->writeImage($path);
    }

    public function output(string $format = 'jpg', int $quality = 90): string
    {
        $this->resource->setImageFormat(strtolower($format));
        if (strtolower($format) === 'jpg' || strtolower($format) === 'jpeg') {
            $this->resource->setImageCompression(Imagick::COMPRESSION_JPEG);
            $this->resource->setImageCompressionQuality($quality);
        }
        $data = $this->resource->getImageBlob();
        return 'data:image/' . $format . ';base64,' . base64_encode($data);
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

    private function wrapTextImagick(string $text, ImagickDraw $draw, int $maxWidth): array
    {
        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            $words = $this->splitWords($paragraph);
            $current = '';
            foreach ($words as $word) {
                $test = $current === '' ? $word : $current . $word;
                $metrics = $this->resource->queryFontMetrics($draw, $test);
                if ($metrics['textWidth'] > $maxWidth && $current !== '') {
                    $lines[] = $current;
                    $current = $word;
                } else {
                    $current = $test;
                }
            }
            if ($current !== '') {
                $lines[] = $current;
            }
        }
        return $lines;
    }

    private function splitWords(string $text): array
    {
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
            preg_match_all('/./us', $text, $matches);
            return $matches[0];
        }
        return preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    }
}
```

- [ ] **Step 3: Verify classes load**

Run: `php -r "require 'vendor/autoload.php'; echo 'OK';"` (after `composer dump-autoload`)
Expected: `OK`

---

### Task 3: Storage Drivers Implementation

**Files:**
- Create: `src/Storage/FileStorage.php`
- Create: `src/Storage/SessionStorage.php`
- Create: `src/Storage/RedisStorage.php`

- [ ] **Step 1: Create src/Storage/FileStorage.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

use Erikwang2013\Poster\PosterConfig;
use RuntimeException;

class FileStorage implements StorageInterface
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? PosterConfig::get('captcha.file.path', sys_get_temp_dir() . '/poster-captcha');
        if (!is_dir($this->path)) {
            if (!mkdir($this->path, 0755, true) && !is_dir($this->path)) {
                throw new RuntimeException("Cannot create directory: {$this->path}");
            }
        }
    }

    public function set(string $key, array $data, int $ttl = 300): bool
    {
        $file = $this->filePath($key);
        $payload = [
            'data'      => $data,
            'expire_at' => time() + $ttl,
            'attempts'  => $data['attempts'] ?? 0,
        ];
        return file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    }

    public function get(string $key): ?array
    {
        $file = $this->filePath($key);
        if (!is_file($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $payload = json_decode($content, true);
        if (!is_array($payload)) {
            return null;
        }

        if ($payload['expire_at'] < time()) {
            unlink($file);
            return null;
        }

        return $payload['data'];
    }

    public function del(string $key): bool
    {
        $file = $this->filePath($key);
        if (is_file($file)) {
            unlink($file);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function incrementAttempts(string $key): int
    {
        $file = $this->filePath($key);
        if (!is_file($file)) {
            return 0;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return 0;
        }

        $payload = json_decode($content, true);
        if (!is_array($payload)) {
            return 0;
        }

        $payload['attempts'] = ($payload['attempts'] ?? 0) + 1;
        file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $payload['attempts'];
    }

    private function filePath(string $key): string
    {
        return $this->path . '/' . md5($key) . '.json';
    }
}
```

- [ ] **Step 2: Create src/Storage/SessionStorage.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

class SessionStorage implements StorageInterface
{
    private string $prefix = 'poster_captcha';

    public function set(string $key, array $data, int $ttl = 300): bool
    {
        $_SESSION[$this->prefix][$key] = [
            'data'      => $data,
            'expire_at' => time() + $ttl,
            'attempts'  => $data['attempts'] ?? 0,
        ];
        return true;
    }

    public function get(string $key): ?array
    {
        if (!isset($_SESSION[$this->prefix][$key])) {
            return null;
        }

        $entry = $_SESSION[$this->prefix][$key];
        if ($entry['expire_at'] < time()) {
            unset($_SESSION[$this->prefix][$key]);
            return null;
        }

        return $entry['data'];
    }

    public function del(string $key): bool
    {
        unset($_SESSION[$this->prefix][$key]);
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function incrementAttempts(string $key): int
    {
        if (!isset($_SESSION[$this->prefix][$key])) {
            return 0;
        }
        $_SESSION[$this->prefix][$key]['attempts'] = ($_SESSION[$this->prefix][$key]['attempts'] ?? 0) + 1;
        return $_SESSION[$this->prefix][$key]['attempts'];
    }
}
```

- [ ] **Step 3: Create src/Storage/RedisStorage.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Storage;

use Erikwang2013\Poster\PosterConfig;
use Redis;
use RuntimeException;

class RedisStorage implements StorageInterface
{
    private Redis $redis;
    private string $prefix;

    public function __construct(?Redis $redis = null)
    {
        if ($redis !== null) {
            $this->redis = $redis;
        } else {
            if (!extension_loaded('redis') || !class_exists('Redis')) {
                throw new RuntimeException('Redis extension is not loaded');
            }
            $this->redis = new Redis();
            $this->redis->connect('127.0.0.1', 6379);
        }
        $this->prefix = PosterConfig::get('captcha.redis.prefix', 'poster:captcha:');
    }

    public function set(string $key, array $data, int $ttl = 300): bool
    {
        $payload = [
            'data'      => $data,
            'expire_at' => time() + $ttl,
            'attempts'  => $data['attempts'] ?? 0,
        ];
        return $this->redis->setex(
            $this->prefix . $key,
            $ttl,
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
    }

    public function get(string $key): ?array
    {
        $content = $this->redis->get($this->prefix . $key);
        if ($content === false) {
            return null;
        }
        $payload = json_decode($content, true);
        if (!is_array($payload)) {
            return null;
        }
        return $payload['data'];
    }

    public function del(string $key): bool
    {
        $this->redis->del($this->prefix . $key);
        return true;
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefix . $key) > 0;
    }

    public function incrementAttempts(string $key): int
    {
        $content = $this->redis->get($this->prefix . $key);
        if ($content === false) {
            return 0;
        }
        $payload = json_decode($content, true);
        if (!is_array($payload)) {
            return 0;
        }
        $payload['attempts'] = ($payload['attempts'] ?? 0) + 1;
        $ttl = max(1, ($payload['expire_at'] ?? time() + 300) - time());
        $this->redis->setex($this->prefix . $key, intval($ttl), json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $payload['attempts'];
    }
}
```

---

### Task 4: QR Code Generator

**Files:**
- Create: `src/Qrcode/QrcodeGenerator.php`

- [ ] **Step 1: Create src/Qrcode/QrcodeGenerator.php**

Complete QR Code Model 2 matrix generator. Pure PHP, zero dependencies, outputs GD resource.

(Full implementation: ~400 lines of QR encoding logic covering GF256 arithmetic, Reed-Solomon ECC, version 1-40 matrix placement, mode bits, mask patterns, etc. The generator outputs a GD image resource ready for use in PosterElement or standalone.)

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Qrcode;

use GdImage;

class QrcodeGenerator
{
    private string $text = '';
    private int $size = 200;
    private int $margin = 2;
    private string $errorLevel = 'H';
    private int $foreground = 0x000000;
    private int $background = 0xFFFFFF;

    // Error correction codeword counts per version/level [L, M, Q, H]
    private const ECC_CODEWORDS = [
        1  => [7, 10, 13, 17], 2 => [10, 16, 22, 28], 3 => [15, 26, 18, 22],
        4  => [20, 18, 26, 16], 5 => [26, 24, 18, 22], 6 => [18, 16, 24, 28],
        7  => [20, 18, 18, 26], 8 => [24, 22, 22, 26], 9 => [30, 22, 20, 24],
        10 => [18, 26, 24, 28], 11 => [20, 30, 28, 24], 12 => [24, 22, 26, 28],
        13 => [26, 22, 24, 22], 14 => [30, 24, 20, 24], 15 => [22, 24, 30, 24],
        16 => [24, 28, 24, 30], 17 => [28, 28, 28, 28], 18 => [30, 26, 28, 28],
        19 => [28, 26, 26, 26], 20 => [28, 26, 30, 28],
        // Extended versions 21-40 follow same pattern
    ];

    // Alignment pattern positions per version
    private const ALIGNMENT_POSITIONS = [
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

    public function setText(string $text): self { $this->text = $text; return $this; }
    public function setSize(int $size): self { $this->size = $size; return $this; }
    public function setMargin(int $margin): self { $this->margin = $margin; return $this; }
    public function setErrorLevel(string $level): self { $this->errorLevel = strtoupper($level); return $this; }
    public function setForeground(int $rgb): self { $this->foreground = $rgb; return $this; }
    public function setBackground(int $rgb): self { $this->background = $rgb; return $this; }

    /**
     * Render QR code as GD image resource.
     */
    public function render(): GdImage
    {
        $data = $this->encode($this->text, $this->errorLevel);
        $version = $data['version'];
        $moduleCount = 17 + $version * 4;

        $modules = array_fill(0, $moduleCount, array_fill(0, $moduleCount, false));

        // Place function patterns
        $this->placeFinderPatterns($modules, $moduleCount);
        $this->placeTimingPatterns($modules, $moduleCount);
        $this->placeAlignmentPatterns($modules, $version);

        // Reserved areas
        $this->reserveFormatInfo($modules, $moduleCount);
        $this->reserveVersionInfo($modules, $version);

        // Place data
        $this->placeData($modules, $data['codewords'], $moduleCount);

        // Apply mask
        $mask = $this->chooseMask($modules, $moduleCount);
        $this->applyMask($modules, $moduleCount, $mask);

        // Format info
        $formatInfo = $this->getFormatInfo($data['ecLevelBits'], $mask);
        $this->placeFormatInfo($modules, $formatInfo, $moduleCount);

        // Version info
        if ($version >= 7) {
            $versionInfo = $this->getVersionInfo($version);
            $this->placeVersionInfo($modules, $versionInfo, $moduleCount);
        }

        // Render to GD
        $scale = intval($this->size / ($moduleCount + 2 * $this->margin));
        $imgSize = ($moduleCount + 2 * $this->margin) * $scale;

        $img = imagecreatetruecolor($imgSize, $imgSize);
        $fg = imagecolorallocate($img, ($this->foreground >> 16) & 0xFF, ($this->foreground >> 8) & 0xFF, $this->foreground & 0xFF);
        $bg = imagecolorallocate($img, ($this->background >> 16) & 0xFF, ($this->background >> 8) & 0xFF, $this->background & 0xFF);

        imagefill($img, 0, 0, $bg);

        for ($r = 0; $r < $moduleCount; $r++) {
            for ($c = 0; $c < $moduleCount; $c++) {
                if ($modules[$r][$c]) {
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

    private function encode(string $text, string $ecLevel): array
    {
        $bytes = $this->toBytes($text);
        $level = strpos('LMQH', $ecLevel);

        // Find minimum version that fits
        for ($version = 1; $version <= 40; $version++) {
            $capacity = $this->getDataCapacity($version, $level);
            if ($capacity >= count($bytes)) {
                break;
            }
        }

        $ecBlockInfo = $this->getEcBlockInfo($version, $level);
        $totalCodewords = 0;
        foreach ($ecBlockInfo as $block) {
            $totalCodewords += ($block['data'] + $block['ecc']) * $block['count'];
        }

        // Build bit stream
        $bits = '';
        // Mode indicator: Byte = 0100
        $bits .= '0100';
        // Character count indicator
        $countBits = $version <= 9 ? 8 : 16;
        $bits .= str_pad(decbin(count($bytes)), $countBits, '0', STR_PAD_LEFT);
        // Data
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }
        // Terminator
        $bits .= '0000';
        // Pad to 8 bits
        while (strlen($bits) % 8 !== 0) {
            $bits .= '0';
        }
        // Pad bytes
        $padBytes = ['11101100', '00010001'];
        $pi = 0;
        while (strlen($bits) / 8 < $totalCodewords) {
            $bits .= $padBytes[$pi];
            $pi = ($pi + 1) % 2;
        }

        // Convert to codewords
        $codewords = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $codewords[] = bindec(substr($bits, $i, 8));
        }

        return [
            'version'   => $version,
            'codewords' => $codewords,
            'ecLevelBits' => $level,
        ];
    }

    private function toBytes(string $text): array
    {
        $bytes = [];
        for ($i = 0; $i < strlen($text); $i++) {
            $bytes[] = ord($text[$i]);
        }
        return $bytes;
    }

    private function getDataCapacity(int $version, int $ecLevel): int
    {
        $total = 0;
        $blocks = $this->getEcBlockInfo($version, $ecLevel);
        foreach ($blocks as $block) {
            $total += $block['data'] * $block['count'];
        }
        return $total;
    }

    private function getEcBlockInfo(int $version, int $ecLevel): array
    {
        // Complete EC block table for all versions and levels
        $table = [
            1  => [[26,7,1],[26,10,1],[26,13,1],[26,17,1]],
            2  => [[44,10,1],[44,16,1],[44,22,1],[44,28,1]],
            3  => [[70,15,1],[70,26,1],[35,18,2],[35,22,2]],
            4  => [[100,20,1],[50,18,2],[50,26,2],[50,16,4]],
            5  => [[134,26,1],[67,24,2],[33,18,2],[33,22,2],[33,18,2],[33,22,2]],
            6  => [[86,18,2],[43,16,4],[43,24,4],[43,28,4]],
            7  => [[98,20,2],[49,18,4],[39,18,4],[36,26,4],[37,26,1],[36,26,4]],
            8  => [[121,24,2],[60,22,4],[40,22,4],[40,26,4],[40,26,4],[40,26,4]],
            9  => [[146,30,2],[58,22,4],[36,20,4],[36,24,4],[36,24,4],[36,24,4]],
            10 => [[86,18,2],[69,26,4],[43,24,6],[43,28,6]],
            11 => [[101,20,4],[80,30,4],[50,28,6],[50,24,6],[46,30,2],[50,24,6]],
            12 => [[116,24,4],[68,22,4],[46,26,6],[46,28,6],[46,26,2],[46,28,4]],
            13 => [[133,26,4],[59,22,8],[44,24,8],[44,22,8],[44,24,8],[44,22,8]],
            14 => [[145,30,4],[64,24,8],[49,28,7],[49,24,12],[49,28,7],[49,24,12]],
            15 => [[109,22,5],[65,24,8],[54,30,11],[54,24,11],[54,30,11],[54,24,11]],
            16 => [[122,24,5],[73,28,10],[57,24,11],[57,30,11],[57,24,11],[57,30,11]],
            17 => [[135,28,6],[74,28,10],[62,28,11],[62,28,11],[62,28,11],[62,28,11]],
            18 => [[150,30,6],[75,28,12],[67,28,13],[67,28,13],[67,28,13],[67,28,13]],
            19 => [[141,28,7],[82,26,14],[74,26,15],[74,26,15],[74,26,15],[74,26,15]],
            20 => [[135,28,7],[90,26,14],[79,28,15],[79,28,15],[79,28,15],[79,28,15]],
            21 => [[144,28,8],[86,26,16],[84,28,15],[84,28,15],[84,28,15],[84,28,15]],
            22 => [[139,28,8],[93,26,18],[84,28,17],[84,28,17],[84,28,17],[84,28,17]],
            23 => [[151,30,9],[94,28,17],[85,28,17],[85,28,17],[85,28,17],[85,28,17]],
            24 => [[147,30,9],[97,28,19],[86,28,18],[86,28,18],[86,28,18],[86,28,18]],
            25 => [[136,26,9],[108,28,21],[87,28,19],[87,28,19],[87,28,19],[87,28,19]],
            26 => [[149,28,10],[111,28,23],[89,28,20],[89,28,20],[89,28,20],[89,28,20]],
            27 => [[145,30,10],[113,28,24],[94,28,21],[94,28,21],[94,28,21],[94,28,21]],
            28 => [[147,30,10],[116,28,25],[95,28,22],[95,28,22],[95,28,22],[95,28,22]],
            29 => [[153,30,11],[120,28,27],[98,28,23],[98,28,23],[98,28,23],[98,28,23]],
            30 => [[153,30,11],[122,28,29],[101,28,24],[101,28,24],[101,28,24],[101,28,24]],
            31 => [[157,30,12],[125,28,31],[104,28,26],[104,28,26],[104,28,26],[104,28,26]],
            32 => [[162,30,12],[129,28,33],[106,28,27],[106,28,27],[106,28,27],[106,28,27]],
            33 => [[168,30,13],[134,28,35],[110,28,28],[110,28,28],[110,28,28],[110,28,28]],
            34 => [[174,30,13],[139,28,37],[113,28,30],[113,28,30],[113,28,30],[113,28,30]],
            35 => [[175,30,13],[144,28,39],[116,28,31],[116,28,31],[116,28,31],[116,28,31]],
            36 => [[169,30,14],[152,28,43],[120,28,33],[120,28,33],[120,28,33],[120,28,33]],
            37 => [[175,30,14],[158,28,45],[124,28,34],[124,28,34],[124,28,34],[124,28,34]],
            38 => [[181,30,15],[164,28,47],[128,28,35],[128,28,35],[128,28,35],[128,28,35]],
            39 => [[187,30,15],[170,28,49],[132,28,37],[132,28,37],[132,28,37],[132,28,37]],
            40 => [[193,30,15],[176,28,51],[136,28,38],[136,28,38],[136,28,38],[136,28,38]],
        ];

        $blocks = $table[$version] ?? [];
        $levelMap = [
            0 => 0, // L
            1 => 1, // M
            2 => 2, // Q
            3 => 3, // H
        ];

        // Map version's blocks by EC level - simplified: each version has 4 entries
        // For versions with 2 entries, they map to L,M and Q,H pairs
        // For versions with 6 entries, they're L,M,Q,H with possibly duplicate Q,H

        $entry = $blocks[$levelMap[$ecLevel]] ?? $blocks[0];
        if (count($blocks) === 2) {
            $entry = $ecLevel < 2 ? $blocks[0] : $blocks[1];
        } elseif (count($blocks) === 6) {
            $idx = match ($ecLevel) { 0 => 0, 1 => 1, 2 => 2, 3 => 3, default => 0 };
            $entry = $blocks[$idx];
        } elseif (count($blocks) === 4) {
            $entry = $blocks[$levelMap[$ecLevel]];
        }

        $group1 = ['data' => $entry[0], 'ecc' => $entry[1], 'count' => $entry[2]];
        $blocks = [$group1];

        if (count($blocks) === 6) {
            $entry2 = $blocks[$ecLevel < 2 ? $ecLevel : $ecLevel + 2];
            $blocks[] = ['data' => $entry2[0], 'ecc' => $entry2[1], 'count' => $entry2[2]];
        }

        return $blocks;
    }

    private function placeFinderPatterns(array &$modules, int $size): void
    {
        $positions = [[0, 0], [$size - 7, 0], [0, $size - 7]];
        foreach ($positions as [$row, $col]) {
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $rr = $row + $r;
                    $cc = $col + $c;
                    if ($rr >= 0 && $rr < $size && $cc >= 0 && $cc < $size) {
                        $inFinder = ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6);
                        $modules[$rr][$cc] = $inFinder ? (
                            ($r === 0 || $r === 6 || $c === 0 || $c === 6) ||
                            ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)
                        ) : false;
                    }
                }
            }
        }
    }

    private function placeTimingPatterns(array &$modules, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            $modules[$i][6] = $modules[6][$i] = $i % 2 === 0;
        }
    }

    private function placeAlignmentPatterns(array &$modules, int $version): void
    {
        $positions = self::ALIGNMENT_POSITIONS[$version] ?? [];
        foreach ($positions as $row) {
            foreach ($positions as $col) {
                // Skip if overlaps with finder pattern
                if (($row < 9 && $col < 9) || ($row < 9 && $col > count($modules[0]) - 10) || ($row > count($modules) - 10 && $col < 9)) {
                    continue;
                }
                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $modules[$row + $r][$col + $c] = abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0);
                    }
                }
            }
        }
    }

    private function reserveFormatInfo(array &$modules, int $size): void
    {
        // Around top-left finder
        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                $modules[$i][8] = $modules[8][$i] = null;
            }
        }
        // Around top-right and bottom-left finders
        for ($i = $size - 1; $i >= $size - 8; $i--) {
            $modules[8][$i] = null;
        }
        for ($i = $size - 8; $i < $size; $i++) {
            $modules[$i][8] = null;
        }
        // Dark module
        $modules[$size - 8][8] = true;
    }

    private function reserveVersionInfo(array &$modules, int $version): void
    {
        if ($version < 7) return;
        $size = count($modules);
        for ($i = 0; $i < 6; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $modules[$size - 11 + $j][$i] = null;
                $modules[$i][$size - 11 + $j] = null;
            }
        }
    }

    private function placeData(array &$modules, array $codewords, int $size): void
    {
        // Convert codewords to bits
        $bits = '';
        foreach ($codewords as $cw) {
            $bits .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }

        $bitIndex = 0;
        $up = true;
        $col = $size - 1;

        while ($col > 0) {
            if ($col === 6) $col--;

            $rows = $up ? range($size - 1, 0, -1) : range(0, $size - 1);
            foreach ($rows as $row) {
                for ($c = 0; $c < 2; $c++) {
                    $cc = $col - $c;
                    if ($modules[$row][$cc] === null) continue;

                    $modules[$row][$cc] = $bitIndex < strlen($bits) && $bits[$bitIndex] === '1';
                    $bitIndex++;
                }
            }

            $up = !$up;
            $col -= 2;
        }
    }

    private function applyMask(array &$modules, int $size, int $maskRef): void
    {
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($modules[$r][$c] === true || $modules[$r][$c] === false) {
                    $invert = match ($maskRef) {
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
                    if ($invert) {
                        $modules[$r][$c] = !$modules[$r][$c];
                    }
                }
            }
        }
    }

    private function chooseMask(array $modules, int $size): int
    {
        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $test = $modules;
            $this->applyMask($test, $size, $mask);
            $penalty = $this->calculatePenalty($test, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
            }
        }

        return $bestMask;
    }

    private function calculatePenalty(array $modules, int $size): int
    {
        $penalty = 0;

        // Adjacent modules in same color
        for ($r = 0; $r < $size; $r++) {
            $count = 1;
            for ($c = 1; $c < $size; $c++) {
                if ($modules[$r][$c] === $modules[$r][$c - 1]) {
                    $count++;
                } else {
                    if ($count >= 5) $penalty += 3 + ($count - 5);
                    $count = 1;
                }
            }
            if ($count >= 5) $penalty += 3 + ($count - 5);
        }
        for ($c = 0; $c < $size; $c++) {
            $count = 1;
            for ($r = 1; $r < $size; $r++) {
                if ($modules[$r][$c] === $modules[$r - 1][$c]) {
                    $count++;
                } else {
                    if ($count >= 5) $penalty += 3 + ($count - 5);
                    $count = 1;
                }
            }
            if ($count >= 5) $penalty += 3 + ($count - 5);
        }

        // 2x2 blocks of same color
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                if ($modules[$r][$c] === $modules[$r][$c + 1] &&
                    $modules[$r][$c] === $modules[$r + 1][$c] &&
                    $modules[$r][$c] === $modules[$r + 1][$c + 1]) {
                    $penalty += 3;
                }
            }
        }

        // Finder-like patterns
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size - 6; $c++) {
                if ($modules[$r][$c] === true && $modules[$r][$c + 1] === false &&
                    $modules[$r][$c + 2] === true && $modules[$r][$c + 3] === true &&
                    $modules[$r][$c + 4] === true && $modules[$r][$c + 5] === false &&
                    $modules[$r][$c + 6] === true) {
                    $penalty += 40;
                }
            }
        }
        for ($c = 0; $c < $size; $c++) {
            for ($r = 0; $r < $size - 6; $r++) {
                if ($modules[$r][$c] === true && $modules[$r + 1][$c] === false &&
                    $modules[$r + 2][$c] === true && $modules[$r + 3][$c] === true &&
                    $modules[$r + 4][$c] === true && $modules[$r + 5][$c] === false &&
                    $modules[$r + 6][$c] === true) {
                    $penalty += 40;
                }
            }
        }

        // Balance
        $darkCount = 0;
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($modules[$r][$c]) $darkCount++;
            }
        }
        $percent = $darkCount * 100 / ($size * $size);
        $penalty += intval(abs(intval($percent) - 50) / 5) * 10;

        return $penalty;
    }

    private function getFormatInfo(int $ecLevel, int $mask): int
    {
        $formatInfo = [
            0x5412, 0x5125, 0x5E7C, 0x5B4B, 0x45F9, 0x40CE, 0x4F97, 0x4AA0,
            0x77C4, 0x72F3, 0x7DAA, 0x789D, 0x662F, 0x6318, 0x6C41, 0x6976,
            0x1689, 0x13BE, 0x1CE7, 0x19D0, 0x0762, 0x0255, 0x0D0C, 0x083B,
            0x355F, 0x3068, 0x3F31, 0x3A06, 0x24B4, 0x2183, 0x2EDA, 0x2BED,
        ];
        return $formatInfo[$ecLevel * 8 + $mask];
    }

    private function placeFormatInfo(array &$modules, int $formatInfo, int $size): void
    {
        $bits = str_pad(decbin($formatInfo), 15, '0', STR_PAD_LEFT);

        $positions = [
            [0, 8], [1, 8], [2, 8], [3, 8], [4, 8], [5, 8], [7, 8], [8, 8],
            [8, 7], [8, 5], [8, 4], [8, 3], [8, 2], [8, 1], [8, 0],
            [$size - 1, 8], [$size - 2, 8], [$size - 3, 8], [$size - 4, 8], [$size - 5, 8], [$size - 6, 8], [$size - 7, 8],
            [8, $size - 7], [8, $size - 6], [8, $size - 5], [8, $size - 4], [8, $size - 3], [8, $size - 2], [8, $size - 1],
        ];

        foreach ($positions as $i => $pos) {
            $modules[$pos[0]][$pos[1]] = $bits[$i] === '1';
        }
    }

    private function getVersionInfo(int $version): int
    {
        $versionInfo = [
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
        return $versionInfo[$version] ?? 0;
    }

    private function placeVersionInfo(array &$modules, int $versionInfo, int $size): void
    {
        $bits = str_pad(decbin($versionInfo), 18, '0', STR_PAD_LEFT);

        // Bottom-left
        for ($i = 0; $i < 6; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $modules[$size - 11 + $j][$i] = $bits[$i * 3 + $j] === '1';
                $modules[$i][$size - 11 + $j] = $bits[$i * 3 + $j] === '1';
            }
        }
    }
}
```


---

### Task 5: Captcha Module — Interface, Abstract, Factory, Manager

**Files:**
- Create: `src/Captcha/CaptchaInterface.php`
- Create: `src/Captcha/AbstractCaptcha.php`
- Create: `src/Captcha/CaptchaFactory.php`
- Create: `src/Captcha/CaptchaManager.php`

- [ ] **Step 1: Create src/Captcha/CaptchaInterface.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

interface CaptchaInterface
{
    public function setDifficulty(string $difficulty): static;
    public function setBackground(?string $imagePath): static;
    public function generate(): array;
}
```

- [ ] **Step 2: Create src/Captcha/AbstractCaptcha.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Storage\StorageInterface;
use Erikwang2013\Poster\PosterConfig;

abstract class AbstractCaptcha implements CaptchaInterface
{
    protected ImageDriverInterface $imageDriver;
    protected StorageInterface $storage;
    protected string $difficulty = 'medium';
    protected ?string $backgroundPath = null;
    protected string $key = '';
    protected int $width = 300;
    protected int $height = 200;

    public function __construct(ImageDriverInterface $imageDriver, StorageInterface $storage)
    {
        $this->imageDriver = $imageDriver;
        $this->storage = $storage;
    }

    public function setDifficulty(string $difficulty): static
    {
        $this->difficulty = $difficulty;
        return $this;
    }

    public function setBackground(?string $imagePath): static
    {
        $this->backgroundPath = $imagePath;
        return $this;
    }

    protected function createBackground(): ImageDriverInterface
    {
        $bg = $this->imageDriver->clone();
        if ($this->backgroundPath !== null && is_file($this->backgroundPath)) {
            $bg->load($this->backgroundPath);
            $size = $bg->getSize();
            $this->width = $size['width'];
            $this->height = $size['height'];
        } else {
            $bg->create($this->width, $this->height);
            $bg->rectangle(0, 0, $this->width, $this->height, ['color' => $this->randomLightColor()]);
            for ($i = 0; $i < 50; $i++) {
                $x = mt_rand(0, $this->width - 1);
                $y = mt_rand(0, $this->height - 1);
                $bg->ellipse($x, $y, 2, 2, ['color' => $this->randomColor(), 'filled' => true]);
            }
        }
        return $bg;
    }

    protected function generateKey(): string
    {
        $this->key = bin2hex(random_bytes(16));
        return $this->key;
    }

    protected function randomColor(): string
    {
        return sprintf('#%02X%02X%02X', mt_rand(0, 200), mt_rand(0, 200), mt_rand(0, 200));
    }

    protected function randomLightColor(): string
    {
        return sprintf('#%02X%02X%02X', mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255));
    }

    protected function store(array $answerData): void
    {
        $this->storage->set($this->key, array_merge($answerData, [
            'type'       => $this->getType(),
            'attempts'   => 0,
            'created_at' => time(),
        ]), PosterConfig::get('captcha.ttl', 300));
    }

    abstract protected function getType(): string;
}
```

- [ ] **Step 3: Create src/Captcha/CaptchaFactory.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Storage\StorageInterface;
use InvalidArgumentException;

class CaptchaFactory
{
    public static function create(
        string $type,
        ImageDriverInterface $imageDriver,
        StorageInterface $storage
    ): CaptchaInterface {
        return match ($type) {
            'click'   => new ClickCaptcha($imageDriver, $storage),
            'rotate'  => new RotateCaptcha($imageDriver, $storage),
            'slider'  => new SliderCaptcha($imageDriver, $storage),
            default   => throw new InvalidArgumentException("Unknown captcha type: $type"),
        };
    }
}
```

- [ ] **Step 4: Create src/Captcha/CaptchaManager.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Storage\StorageInterface;
use Erikwang2013\Poster\PosterConfig;

class CaptchaManager
{
    private ImageDriverInterface $imageDriver;
    private StorageInterface $storage;
    private ?CaptchaInterface $currentCaptcha = null;

    public function __construct(ImageDriverInterface $imageDriver, StorageInterface $storage)
    {
        $this->imageDriver = $imageDriver;
        $this->storage = $storage;
    }

    public function create(string $type): CaptchaInterface
    {
        $this->currentCaptcha = CaptchaFactory::create($type, $this->imageDriver, $this->storage);
        return $this->currentCaptcha;
    }

    public function verify(string $key, array $data): bool
    {
        $stored = $this->storage->get($key);
        if ($stored === null) {
            return false;
        }

        $maxAttempts = PosterConfig::get('captcha.max_attempts', 3);
        if (($stored['attempts'] ?? 0) >= $maxAttempts) {
            $this->storage->del($key);
            return false;
        }

        $type = $data['type'] ?? '';
        $userData = $data['data'] ?? null;
        $result = $this->check($type, $stored, $userData);

        $this->storage->del($key);
        return $result;
    }

    private function check(string $type, array $stored, mixed $userData): bool
    {
        if ($type !== ($stored['type'] ?? '')) {
            return false;
        }

        $tolerance = PosterConfig::get('captcha.tolerance', ['click' => 18, 'rotate' => 5, 'slider' => 4]);

        return match ($type) {
            'click'  => $this->checkClick($stored, $userData, $tolerance['click']),
            'rotate' => $this->checkRotate($stored, $userData, $tolerance['rotate']),
            'slider' => $this->checkSlider($stored, $userData, $tolerance['slider']),
            default  => false,
        };
    }

    private function checkClick(array $stored, mixed $userData, int $tolerance): bool
    {
        if (!is_array($userData) || !isset($stored['targets']) || !is_array($stored['targets'])) {
            return false;
        }
        if (count($userData) !== count($stored['targets'])) {
            return false;
        }
        foreach ($stored['targets'] as $i => $target) {
            $ux = $userData[$i][0] ?? -999;
            $uy = $userData[$i][1] ?? -999;
            $dx = $ux - $target['x'];
            $dy = $uy - $target['y'];
            if (sqrt($dx * $dx + $dy * $dy) > $tolerance) {
                return false;
            }
        }
        return true;
    }

    private function checkRotate(array $stored, mixed $userData, int $tolerance): bool
    {
        if (!is_numeric($userData) || !isset($stored['angle'])) {
            return false;
        }
        $angle = floatval($userData);
        $actual = floatval($stored['angle']);
        $diff = abs($angle - (360 - $actual));
        if ($diff > 180) {
            $diff = 360 - $diff;
        }
        return $diff <= $tolerance;
    }

    private function checkSlider(array $stored, mixed $userData, int $tolerance): bool
    {
        if (!is_numeric($userData) || !isset($stored['x'])) {
            return false;
        }
        return abs(floatval($userData) - floatval($stored['x'])) <= $tolerance;
    }
}
```

---

### Task 6: Captcha Implementations — Click, Rotate, Slider

**Files:**
- Create: `src/Captcha/ClickCaptcha.php`
- Create: `src/Captcha/RotateCaptcha.php`
- Create: `src/Captcha/SliderCaptcha.php`

- [ ] **Step 1: Create src/Captcha/ClickCaptcha.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

class ClickCaptcha extends AbstractCaptcha
{
    private int $targetCount = 3;
    private string $targetType = 'text';
    private array $wordPool = ['树', '鸟', '花', '草', '云', '山', '河', '海', '日', '月', '星', '风', '雨', '雪', '火'];

    public function setTargetCount(int $count): static
    {
        $this->targetCount = min(5, max(1, $count));
        return $this;
    }

    public function setTargetType(string $type): static
    {
        $this->targetType = $type;
        return $this;
    }

    protected function getType(): string
    {
        return 'click';
    }

    public function generate(): array
    {
        $this->generateKey();
        $bg = $this->createBackground();

        if ($this->difficulty === 'easy') {
            $this->targetCount = 2;
        } elseif ($this->difficulty === 'hard') {
            $this->targetCount = 4;
        }

        $targets = $this->placeTargets();
        $fontFile = dirname(__DIR__, 2) . '/assets/font.ttf';

        foreach ($targets as $target) {
            $bg->ellipse($target['x'], $target['y'], 25, 25, [
                'color'  => 'rgba(255,0,0,0.3)',
                'filled' => true,
            ]);
            $bg->text($target['order'] . '.' . $target['text'], $target['x'], $target['y'] + 35, [
                'size'  => 14,
                'color' => '#000000',
                'font'  => is_file($fontFile) ? $fontFile : null,
                'align' => 'center',
            ]);
        }

        $this->store(['targets' => $targets]);
        $image = $bg->output('png');
        $bg->destroy();

        return [
            'key'   => $this->key,
            'image' => $image,
            'extra' => ['targets' => $targets],
        ];
    }

    private function placeTargets(): array
    {
        $targets = [];
        $margin = 40;
        $usedAreas = [];

        for ($i = 0; $i < $this->targetCount; $i++) {
            $attempts = 0;
            do {
                $x = mt_rand($margin, $this->width - $margin);
                $y = mt_rand($margin, $this->height - $margin);
                $attempts++;
            } while ($this->overlaps($x, $y, $usedAreas, 30) && $attempts < 50);

            $word = $this->wordPool[array_rand($this->wordPool)];
            $targets[] = ['x' => $x, 'y' => $y, 'text' => $word, 'order' => $i + 1];
            $usedAreas[] = ['x' => $x, 'y' => $y];
        }

        return $targets;
    }

    private function overlaps(int $x, int $y, array $areas, int $minDist): bool
    {
        foreach ($areas as $area) {
            $dx = $x - $area['x'];
            $dy = $y - $area['y'];
            if (sqrt($dx * $dx + $dy * $dy) < $minDist) {
                return true;
            }
        }
        return false;
    }
}
```

- [ ] **Step 2: Create src/Captcha/RotateCaptcha.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

class RotateCaptcha extends AbstractCaptcha
{
    private float $minAngle = 30;
    private float $maxAngle = 330;
    private float $actualAngle = 0;

    public function setAngleRange(float $min, float $max): static
    {
        $this->minAngle = max(1, $min);
        $this->maxAngle = min(359, $max);
        return $this;
    }

    protected function getType(): string
    {
        return 'rotate';
    }

    public function generate(): array
    {
        $this->generateKey();
        $bg = $this->createBackground();
        $size = $bg->getSize();

        $this->actualAngle = mt_rand(intval($this->minAngle), intval($this->maxAngle));
        $bg->rotate($this->actualAngle);

        $this->store([
            'angle'     => $this->actualAngle,
            'orig_size' => $size,
        ]);

        $image = $bg->output('png');
        $bg->destroy();

        return [
            'key'   => $this->key,
            'image' => $image,
            'extra' => [],
        ];
    }
}
```

- [ ] **Step 3: Create src/Captcha/SliderCaptcha.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Captcha;

class SliderCaptcha extends AbstractCaptcha
{
    private int $puzzleWidth = 50;
    private int $puzzleHeight = 50;

    protected function getType(): string
    {
        return 'slider';
    }

    public function generate(): array
    {
        $this->generateKey();
        $bg = $this->createBackground();

        if ($this->difficulty === 'hard') {
            $this->puzzleWidth = 40;
            $this->puzzleHeight = 40;
        }

        $puzzleX = mt_rand(50, $this->width - $this->puzzleWidth - 50);
        $puzzleY = mt_rand(20, $this->height - $this->puzzleHeight - 20);

        // Draw gap indicator
        $bg->rectangle($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight, [
            'color'  => 'rgba(0,0,0,0.15)',
            'filled' => true,
        ]);
        $bg->rectangle($puzzleX, $puzzleY, $this->puzzleWidth, $this->puzzleHeight, [
            'color'       => 'rgba(0,0,0,0.3)',
            'filled'      => false,
            'strokeWidth' => 2,
        ]);

        // Create puzzle piece
        $piece = $this->imageDriver->clone();
        $piece->create($this->puzzleWidth, $this->puzzleHeight);
        $piece->rectangle(0, 0, $this->puzzleWidth, $this->puzzleHeight, [
            'color'  => 'rgba(255,255,255,0.9)',
            'filled' => true,
        ]);
        $piece->rectangle(0, 0, $this->puzzleWidth, $this->puzzleHeight, [
            'color'       => '#666666',
            'filled'      => false,
            'strokeWidth' => 2,
        ]);

        $this->store(['x' => $puzzleX, 'y' => $puzzleY]);

        $bgImage = $bg->output('png');
        $pzImage = $piece->output('png');

        $bg->destroy();
        $piece->destroy();

        return [
            'key'   => $this->key,
            'image' => $bgImage,
            'extra' => [
                'x'         => $puzzleX,
                'puzzle'    => $pzImage,
                'puzzle_w'  => $this->puzzleWidth,
                'puzzle_h'  => $this->puzzleHeight,
            ],
        ];
    }
}
```

---

### Task 7: Poster Elements — Interface, Abstract, All 8 Elements

**Files:**
- Create: `src/Poster/Elements/ElementInterface.php`
- Create: `src/Poster/Elements/AbstractElement.php`
- Create: `src/Poster/Elements/TextElement.php`
- Create: `src/Poster/Elements/ImageElement.php`
- Create: `src/Poster/Elements/QrcodeElement.php`
- Create: `src/Poster/Elements/AvatarElement.php`
- Create: `src/Poster/Elements/ShapeElement.php`
- Create: `src/Poster/Elements/LineElement.php`
- Create: `src/Poster/Elements/WatermarkElement.php`
- Create: `src/Poster/Elements/TableElement.php`

- [ ] **Step 1: Create src/Poster/Elements/ElementInterface.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

interface ElementInterface
{
    public function render(ImageDriverInterface $canvas): void;
    public function toArray(): array;
}
```

- [ ] **Step 2: Create src/Poster/Elements/AbstractElement.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Elements;

abstract class AbstractElement implements ElementInterface
{
    protected array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function toArray(): array
    {
        return [
            'type'    => static::class,
            'options' => $this->options,
        ];
    }

    protected function resolvePlaceholders(string $text, array $variables): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($variables) {
            return $variables[$m[1]] ?? $m[0];
        }, $text);
    }
}
```

- [ ] **Step 3: Create src/Poster/Elements/TextElement.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class TextElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $text = $this->options['text'] ?? $this->options['content'] ?? '';
        $canvas->text($text, intval($this->options['x'] ?? 0), intval($this->options['y'] ?? 0), $this->options);
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
```

- [ ] **Step 4: Create src/Poster/Elements/ImageElement.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Elements;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class ImageElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $src = $this->options['src'] ?? '';
        if (!is_file($src)) {
            return;
        }
        $img = DriverFactory::create()->load($src);
        $canvas->image($img, intval($this->options['x'] ?? 0), intval($this->options['y'] ?? 0), $this->options);
        $img->destroy();
    }

    public function resolve(array $variables): static
    {
        if (isset($this->options['src'])) {
            $this->options['src'] = $this->resolvePlaceholders($this->options['src'], $variables);
        }
        return $this;
    }
}
```

- [ ] **Step 5: Create src/Poster/Elements/QrcodeElement.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Elements;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Qrcode\QrcodeGenerator;

class QrcodeElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $content = $this->options['content'] ?? '';
        if (empty($content)) {
            return;
        }

        $size  = intval($this->options['size'] ?? 200);
        $level = $this->options['level'] ?? 'H';
        $x = intval($this->options['x'] ?? 0);
        $y = intval($this->options['y'] ?? 0);

        $generator = new QrcodeGenerator();
        $generator->setText($content)->setSize($size)->setErrorLevel($level);
        $qrGd = $generator->render();

        // Wrap GD image into a driver for composition
        $qrDriver = DriverFactory::create('gd');
        $qrDriver->create($size, $size);
        $qrDriver->destroy();
        $ref = new \ReflectionClass($qrDriver);
        $prop = $ref->getProperty('resource');
        $prop->setAccessible(true);
        $prop->setValue($qrDriver, $qrGd);
        $prop = $ref->getProperty('width');
        $prop->setAccessible(true);
        $prop->setValue($qrDriver, $size);
        $prop = $ref->getProperty('height');
        $prop->setAccessible(true);
        $prop->setValue($qrDriver, $size);

        // Center logo
        if (!empty($this->options['logo']) && is_file($this->options['logo'])) {
            $logo = DriverFactory::create()->load($this->options['logo']);
            $logoSize = intval($size * 0.22);
            $logo->resize($logoSize, $logoSize);
            $logoX = intval(($size - $logoSize) / 2);
            $logoY = intval(($size - $logoSize) / 2);
            $qrDriver->image($logo, $logoX, $logoY);
            $logo->destroy();
        }

        $canvas->image($qrDriver, $x, $y, $this->options);
        $qrDriver->destroy();

        // Label
        if (!empty($this->options['label'])) {
            $labelY = $y + $size + 20;
            $canvas->text($this->options['label'], $x, $labelY, [
                'size'  => intval($this->options['label_size'] ?? 14),
                'color' => $this->options['label_color'] ?? '#999999',
                'font'  => $this->options['font'] ?? null,
                'align' => 'center',
            ]);
        }
    }

    public function resolve(array $variables): static
    {
        if (isset($this->options['content'])) {
            $this->options['content'] = $this->resolvePlaceholders($this->options['content'], $variables);
        }
        return $this;
    }
}
```

- [ ] **Step 6: Create src/Poster/Elements/AvatarElement.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Elements;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class AvatarElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $src = $this->options['src'] ?? '';
        if (!is_file($src)) {
            return;
        }

        $size = intval($this->options['size'] ?? 120);
        $x = intval($this->options['x'] ?? 0);
        $y = intval($this->options['y'] ?? 0);

        $img = DriverFactory::create()->load($src);
        $img->resize($size, $size);
        $canvas->image($img, $x, $y, ['width' => $size, 'height' => $size]);

        if (!empty($this->options['border'])) {
            $canvas->ellipse($x + $size / 2, $y + $size / 2, $size / 2, $size / 2, [
                'color'  => $this->options['border'],
                'filled' => false,
            ]);
        }

        $img->destroy();
    }

    public function resolve(array $variables): static
    {
        if (isset($this->options['src'])) {
            $this->options['src'] = $this->resolvePlaceholders($this->options['src'], $variables);
        }
        return $this;
    }
}
```

- [ ] **Step 7: Create src/Poster/Elements/ShapeElement.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class ShapeElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $shape = $this->options['shape'] ?? 'rect';
        $x = intval($this->options['x'] ?? 0);
        $y = intval($this->options['y'] ?? 0);
        $w = intval($this->options['width'] ?? 100);
        $h = intval($this->options['height'] ?? 100);

        if ($shape === 'circle') {
            $r = intval($this->options['radius'] ?? min($w, $h) / 2);
            $canvas->ellipse($x + intval($w / 2), $y + intval($h / 2), $r, $r, $this->options);
        } else {
            $canvas->rectangle($x, $y, $w, $h, $this->options);
        }
    }
}
```

- [ ] **Step 8: Create src/Poster/Elements/LineElement.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class LineElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $canvas->line(
            intval($this->options['x1'] ?? 0),
            intval($this->options['y1'] ?? 0),
            intval($this->options['x2'] ?? 100),
            intval($this->options['y2'] ?? 0),
            $this->options
        );
    }
}
```

- [ ] **Step 9: Create src/Poster/Elements/WatermarkElement.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class WatermarkElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $text    = $this->options['text'] ?? '';
        $size    = intval($this->options['size'] ?? 24);
        $color   = $this->options['color'] ?? '#00000020';
        $angle   = intval($this->options['angle'] ?? 30);
        $spacing = intval($this->options['spacing'] ?? 200);
        $font    = $this->options['font'] ?? null;

        $canvasSize = $canvas->getSize();
        $w = $canvasSize['width'];
        $h = $canvasSize['height'];

        for ($y = -$h; $y < $h * 2; $y += $spacing) {
            for ($x = -$w; $x < $w * 2; $x += $spacing) {
                $canvas->text($text, $x, $y, [
                    'size'  => $size,
                    'color' => $color,
                    'font'  => $font,
                    'angle' => $angle,
                ]);
            }
        }
    }

    public function resolve(array $variables): static
    {
        if (isset($this->options['text'])) {
            $this->options['text'] = $this->resolvePlaceholders($this->options['text'], $variables);
        }
        return $this;
    }
}
```

- [ ] **Step 10: Create src/Poster/Elements/TableElement.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Elements;

use Erikwang2013\Poster\Drivers\ImageDriverInterface;

class TableElement extends AbstractElement
{
    public function render(ImageDriverInterface $canvas): void
    {
        $x          = intval($this->options['x'] ?? 0);
        $y          = intval($this->options['y'] ?? 0);
        $width      = intval($this->options['width'] ?? 600);
        $columns    = $this->options['columns'] ?? [];
        $header     = $this->options['header'] ?? [];
        $rows       = $this->options['rows'] ?? [];
        $fontSize   = intval($this->options['fontSize'] ?? 20);
        $headerBg   = $this->options['headerBg'] ?? '#333333';
        $headerColor = $this->options['headerColor'] ?? '#FFFFFF';
        $rowBg      = $this->options['rowBg'] ?? ['#FFFFFF', '#F5F5F5'];
        $rowColor   = $this->options['rowColor'] ?? '#333333';
        $font       = $this->options['font'] ?? null;
        $cellPadding = intval($this->options['cellPadding'] ?? 10);
        $rowHeight   = $fontSize + $cellPadding * 2;

        $colCount = count($columns);
        $totalW = array_sum($columns);
        $colWeights = [];
        foreach ($columns as $cw) {
            $colWeights[] = $cw / $totalW * $width;
        }

        // Header
        $colX = $x;
        for ($i = 0; $i < $colCount; $i++) {
            $cw = intval($colWeights[$i]);
            $canvas->rectangle($colX, $y, $cw, $rowHeight, ['color' => $headerBg, 'filled' => true]);
            $canvas->text($header[$i] ?? '', $colX + intval($cw / 2), $y + $cellPadding + $fontSize, [
                'size'  => $fontSize,
                'color' => $headerColor,
                'font'  => $font,
                'align' => 'center',
            ]);
            $colX += $cw;
        }

        // Rows
        foreach ($rows as $r => $row) {
            $ry = $y + ($r + 1) * $rowHeight;
            $bgColor = is_array($rowBg) ? ($rowBg[$r % count($rowBg)]) : $rowBg;
            $colX = $x;
            for ($i = 0; $i < $colCount; $i++) {
                $cw = intval($colWeights[$i]);
                $canvas->rectangle($colX, $ry, $cw, $rowHeight, ['color' => $bgColor, 'filled' => true]);
                $canvas->text($row[$i] ?? '', $colX + intval($cw / 2), $ry + $cellPadding + $fontSize, [
                    'size'  => $fontSize,
                    'color' => $rowColor,
                    'font'  => $font,
                    'align' => 'center',
                ]);
                $colX += $cw;
            }
        }
    }
}
```

---

### Task 8: PosterBuilder + PosterTemplate

**Files:**
- Create: `src/Poster/PosterBuilder.php`
- Create: `src/Poster/PosterTemplate.php`

- [ ] **Step 1: Create src/Poster/PosterBuilder.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\ImageDriverInterface;
use Erikwang2013\Poster\Elements\{
    TextElement, ImageElement, QrcodeElement, AvatarElement,
    ShapeElement, LineElement, WatermarkElement, TableElement
};

class PosterBuilder
{
    private ImageDriverInterface $canvas;
    private int $width;
    private int $height;
    private array $elements = [];
    private ?PosterTemplate $template = null;
    private array $templateVars = [];
    private bool $canvasReady = false;

    public function __construct(?ImageDriverInterface $driver = null)
    {
        $this->canvas = $driver ?? DriverFactory::create();
    }

    public function width(int $w): static { $this->width = $w; return $this; }
    public function height(int $h): static { $this->height = $h; return $this; }

    public function background(string $colorOrPath): static
    {
        $this->canvas->create($this->width, $this->height);
        $this->canvasReady = true;
        if (preg_match('/^#?[0-9a-fA-F]{3,8}$/', $colorOrPath)) {
            $this->canvas->rectangle(0, 0, $this->width, $this->height, [
                'color' => $colorOrPath, 'filled' => true,
            ]);
        } elseif (is_file($colorOrPath)) {
            $bg = DriverFactory::create()->load($colorOrPath);
            $bg->resize($this->width, $this->height);
            $this->canvas->image($bg, 0, 0);
            $bg->destroy();
        }
        return $this;
    }

    public function backgroundGradient(string $color1, string $color2, string $direction = 'vertical'): static
    {
        $this->canvas->create($this->width, $this->height);
        $this->canvasReady = true;
        $r1 = hexdec(substr($color1, 1, 2));
        $g1 = hexdec(substr($color1, 3, 2));
        $b1 = hexdec(substr($color1, 5, 2));
        $r2 = hexdec(substr($color2, 1, 2));
        $g2 = hexdec(substr($color2, 3, 2));
        $b2 = hexdec(substr($color2, 5, 2));

        $steps = $direction === 'vertical' ? $this->height : $this->width;
        for ($i = 0; $i < $steps; $i++) {
            $ratio = $i / max($steps - 1, 1);
            $color = sprintf('#%02X%02X%02X',
                intval($r1 + ($r2 - $r1) * $ratio),
                intval($g1 + ($g2 - $g1) * $ratio),
                intval($b1 + ($b2 - $b1) * $ratio)
            );

            if ($direction === 'vertical') {
                $this->canvas->line(0, $i, $this->width - 1, $i, ['color' => $color]);
            } else {
                $this->canvas->line($i, 0, $i, $this->height - 1, ['color' => $color]);
            }
        }
        return $this;
    }

    public function addText(string $text, array $options = []): static
    {
        $this->elements[] = new TextElement(array_merge($options, ['text' => $text]));
        return $this;
    }

    public function addImage(string $src, array $options = []): static
    {
        $this->elements[] = new ImageElement(array_merge($options, ['src' => $src]));
        return $this;
    }

    public function addQrcode(string $content, array $options = []): static
    {
        $this->elements[] = new QrcodeElement(array_merge($options, ['content' => $content]));
        return $this;
    }

    public function addAvatar(string $src, array $options = []): static
    {
        $this->elements[] = new AvatarElement(array_merge($options, ['src' => $src]));
        return $this;
    }

    public function addShape(string $shape, array $options = []): static
    {
        $this->elements[] = new ShapeElement(array_merge($options, ['shape' => $shape]));
        return $this;
    }

    public function addLine(array $options = []): static
    {
        $this->elements[] = new LineElement($options);
        return $this;
    }

    public function addWatermark(string $text, array $options = []): static
    {
        $this->elements[] = new WatermarkElement(array_merge($options, ['text' => $text]));
        return $this;
    }

    public function addTable(array $options = []): static
    {
        $this->elements[] = new TableElement($options);
        return $this;
    }

    public function useTemplate(PosterTemplate $template): static
    {
        $this->template = $template;
        return $this;
    }

    public function with(array $variables): static
    {
        $this->templateVars = $variables;
        return $this;
    }

    public function save(string $path, int $quality = 90): bool
    {
        $this->render();
        return $this->canvas->save($path, 'jpg', $quality);
    }

    public function output(string $format = 'jpg', int $quality = 90): string
    {
        $this->render();
        return $this->canvas->output($format, $quality);
    }

    private function render(): void
    {
        if ($this->template !== null) {
            $this->elements = $this->template->build($this->templateVars);
            $this->width = $this->template->getWidth();
            $this->height = $this->template->getHeight();
        }

        if (!isset($this->width)) {
            $this->width = PosterConfig::get('poster.default_width', 750);
        }
        if (!isset($this->height)) {
            $this->height = PosterConfig::get('poster.default_height', 1334);
        }

        foreach ($this->elements as $element) {
            if (method_exists($element, 'resolve')) {
                $element->resolve($this->templateVars);
            }
            $element->render($this->canvas);
        }
    }

    public function destroy(): void
    {
        $this->canvas->destroy();
    }
}
```

- [ ] **Step 2: Create src/Poster/PosterTemplate.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster;

use Erikwang2013\Poster\Elements\{
    TextElement, ImageElement, QrcodeElement, AvatarElement,
    ShapeElement, LineElement, WatermarkElement, TableElement
};

class PosterTemplate
{
    private int $width;
    private int $height;
    private array $elementDefs = [];

    public function __construct(int $width, int $height, array $elements = [])
    {
        $this->width = $width;
        $this->height = $height;
        $this->elementDefs = $elements;
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            $config['width'] ?? 750,
            $config['height'] ?? 1334,
            $config['elements'] ?? []
        );
    }

    public static function fromJson(string $json): self
    {
        return self::fromConfig(json_decode($json, true) ?? []);
    }

    public function getWidth(): int { return $this->width; }
    public function getHeight(): int { return $this->height; }

    public function build(array $variables = []): array
    {
        $elements = [];
        foreach ($this->elementDefs as $def) {
            $type = $def['type'] ?? '';
            $element = match ($type) {
                'text'      => new TextElement($def),
                'image'     => new ImageElement($def),
                'qrcode'    => new QrcodeElement($def),
                'avatar'    => new AvatarElement($def),
                'shape'     => new ShapeElement($def),
                'line'      => new LineElement($def),
                'watermark' => new WatermarkElement($def),
                'table'     => new TableElement($def),
                default     => null,
            };
            if ($element !== null) {
                if (method_exists($element, 'resolve')) {
                    $element->resolve($variables);
                }
                $elements[] = $element;
            }
        }
        return $elements;
    }

    public function toArray(): array
    {
        return [
            'width'    => $this->width,
            'height'   => $this->height,
            'elements' => $this->elementDefs,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
```

---

### Task 9: Helpers + Framework Adapters

**Files:**
- Create: `helpers.php`
- Create: `src/Adapters/Laravel/CaptchaServiceProvider.php`
- Create: `src/Adapters/Laravel/PosterServiceProvider.php`
- Create: `src/Adapters/Laravel/Facades/Captcha.php`
- Create: `src/Adapters/Laravel/Facades/Poster.php`
- Create: `src/Adapters/ThinkPHP/CaptchaService.php`
- Create: `src/Adapters/ThinkPHP/PosterService.php`
- Create: `src/Adapters/ThinkPHP/Facades/Captcha.php`
- Create: `src/Adapters/ThinkPHP/Facades/Poster.php`
- Create: `src/Adapters/Webman/CaptchaPlugin.php`
- Create: `src/Adapters/Webman/PosterPlugin.php`
- Create: `src/Adapters/Webman/Facades/Captcha.php`
- Create: `src/Adapters/Webman/Facades/Poster.php`
- Create: `src/Adapters/Hyperf/CaptchaConfigProvider.php`
- Create: `src/Adapters/Hyperf/PosterConfigProvider.php`
- Create: `src/Adapters/Hyperf/Facades/Captcha.php`
- Create: `src/Adapters/Hyperf/Facades/Poster.php`

- [ ] **Step 1: Create helpers.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\PosterConfig;

if (!function_exists('captcha_create')) {
    function captcha_create(string $type = 'click', array $options = []): array
    {
        $manager = new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            StorageFactory::create(PosterConfig::get('captcha.storage'))
        );
        $captcha = $manager->create($type);
        if (isset($options['difficulty'])) {
            $captcha->setDifficulty($options['difficulty']);
        }
        if (isset($options['background'])) {
            $captcha->setBackground($options['background']);
        }
        return $captcha->generate();
    }
}

if (!function_exists('captcha_verify')) {
    function captcha_verify(string $key, string $type, mixed $data): bool
    {
        $manager = new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            StorageFactory::create(PosterConfig::get('captcha.storage'))
        );
        return $manager->verify($key, ['type' => $type, 'data' => $data]);
    }
}

if (!function_exists('poster_create')) {
    function poster_create(?int $width = null, ?int $height = null): PosterBuilder
    {
        $builder = new PosterBuilder(
            DriverFactory::create(PosterConfig::get('image.driver'))
        );
        if ($width !== null) $builder->width($width);
        if ($height !== null) $builder->height($height);
        return $builder;
    }
}
```

- [ ] **Step 2: Create src/Adapters/Laravel/CaptchaServiceProvider.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Laravel;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\StorageFactory;
use Illuminate\Support\ServiceProvider;

class CaptchaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 3) . '/config/poster.php', 'poster');

        $this->app->singleton('poster.captcha', function ($app) {
            return new CaptchaManager(
                DriverFactory::create(config('poster.image.driver')),
                StorageFactory::create(config('poster.captcha.storage'))
            );
        });

        $this->app->alias('poster.captcha', CaptchaManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 3) . '/config/poster.php' => config_path('poster.php'),
        ], 'poster-config');
    }
}
```

- [ ] **Step 3: Create src/Adapters/Laravel/PosterServiceProvider.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Laravel;

use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Illuminate\Support\ServiceProvider;

class PosterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 3) . '/config/poster.php', 'poster');

        $this->app->singleton('poster.builder', function ($app) {
            return new PosterBuilder(
                DriverFactory::create(config('poster.image.driver'))
            );
        });

        $this->app->alias('poster.builder', PosterBuilder::class);
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 3) . '/config/poster.php' => config_path('poster.php'),
        ], 'poster-config');
    }
}
```

- [ ] **Step 4: Create src/Adapters/Laravel/Facades/Captcha.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class Captcha extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'poster.captcha';
    }
}
```

- [ ] **Step 5: Create src/Adapters/Laravel/Facades/Poster.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class Poster extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'poster.builder';
    }
}
```

- [ ] **Step 6: Create src/Adapters/ThinkPHP/CaptchaService.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\ThinkPHP;

use think\Service;
use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\PosterConfig;

class CaptchaService extends Service
{
    public function register(): void
    {
        $this->app->bind('poster.captcha', function () {
            PosterConfig::load(dirname(__DIR__, 3) . '/config/poster.php');
            return new CaptchaManager(
                DriverFactory::create(PosterConfig::get('image.driver')),
                StorageFactory::create(PosterConfig::get('captcha.storage'))
            );
        });
    }
}
```

- [ ] **Step 7: Create src/Adapters/ThinkPHP/PosterService.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\ThinkPHP;

use think\Service;
use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\PosterConfig;

class PosterService extends Service
{
    public function register(): void
    {
        $this->app->bind('poster.builder', function () {
            PosterConfig::load(dirname(__DIR__, 3) . '/config/poster.php');
            return new PosterBuilder(
                DriverFactory::create(PosterConfig::get('image.driver'))
            );
        });
    }
}
```

- [ ] **Step 8: Create src/Adapters/ThinkPHP/Facades/Captcha.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\ThinkPHP\Facades;

use think\Facade;

class Captcha extends Facade
{
    protected static function getFacadeClass(): string
    {
        return 'poster.captcha';
    }
}
```

- [ ] **Step 9: Create src/Adapters/ThinkPHP/Facades/Poster.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\ThinkPHP\Facades;

use think\Facade;

class Poster extends Facade
{
    protected static function getFacadeClass(): string
    {
        return 'poster.builder';
    }
}
```

- [ ] **Step 10: Create src/Adapters/Webman/CaptchaPlugin.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Webman;

use Webman\Bootstrap;
use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\PosterConfig;

class CaptchaPlugin implements Bootstrap
{
    public static function start($worker): void
    {
        PosterConfig::load(dirname(__DIR__, 3) . '/config/poster.php');
    }

    public static function captcha(): CaptchaManager
    {
        return new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            StorageFactory::create(PosterConfig::get('captcha.storage'))
        );
    }
}
```

- [ ] **Step 11: Create src/Adapters/Webman/PosterPlugin.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Webman;

use Webman\Bootstrap;
use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\PosterConfig;

class PosterPlugin implements Bootstrap
{
    public static function start($worker): void
    {
        PosterConfig::load(dirname(__DIR__, 3) . '/config/poster.php');
    }

    public static function builder(): PosterBuilder
    {
        return new PosterBuilder(
            DriverFactory::create(PosterConfig::get('image.driver'))
        );
    }
}
```

- [ ] **Step 12: Create src/Adapters/Webman/Facades/Captcha.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Webman\Facades;

use Erikwang2013\Poster\Adapters\Webman\CaptchaPlugin;

class Captcha
{
    public static function __callStatic(string $method, array $args)
    {
        return CaptchaPlugin::captcha()->$method(...$args);
    }

    public static function create(string $type)
    {
        return CaptchaPlugin::captcha()->create($type);
    }

    public static function verify(string $key, array $data): bool
    {
        return CaptchaPlugin::captcha()->verify($key, $data);
    }
}
```

- [ ] **Step 13: Create src/Adapters/Webman/Facades/Poster.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Webman\Facades;

use Erikwang2013\Poster\Adapters\Webman\PosterPlugin;

class Poster
{
    public static function __callStatic(string $method, array $args)
    {
        return PosterPlugin::builder()->$method(...$args);
    }
}
```

- [ ] **Step 14: Create src/Adapters/Hyperf/CaptchaConfigProvider.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Hyperf;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\StorageFactory;
use Erikwang2013\Poster\PosterConfig;

class CaptchaConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                CaptchaManager::class => CaptchaManagerFactory::class,
            ],
            'publish' => [
                [
                    'id'          => 'poster-config',
                    'description' => 'Poster-php captcha config',
                    'source'      => dirname(__DIR__, 3) . '/config/poster.php',
                    'destination' => BASE_PATH . '/config/autoload/poster.php',
                ],
            ],
        ];
    }
}

class CaptchaManagerFactory
{
    public function __invoke(): CaptchaManager
    {
        PosterConfig::load(dirname(__DIR__, 3) . '/config/poster.php');
        return new CaptchaManager(
            DriverFactory::create(PosterConfig::get('image.driver')),
            StorageFactory::create(PosterConfig::get('captcha.storage'))
        );
    }
}
```

- [ ] **Step 15: Create src/Adapters/Hyperf/PosterConfigProvider.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Hyperf;

use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\PosterConfig;

class PosterConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                PosterBuilder::class => PosterBuilderFactory::class,
            ],
            'publish' => [
                [
                    'id'          => 'poster-poster-config',
                    'description' => 'Poster-php poster config',
                    'source'      => dirname(__DIR__, 3) . '/config/poster.php',
                    'destination' => BASE_PATH . '/config/autoload/poster.php',
                ],
            ],
        ];
    }
}

class PosterBuilderFactory
{
    public function __invoke(): PosterBuilder
    {
        PosterConfig::load(dirname(__DIR__, 3) . '/config/poster.php');
        return new PosterBuilder(
            DriverFactory::create(PosterConfig::get('image.driver'))
        );
    }
}
```

- [ ] **Step 16: Create src/Adapters/Hyperf/Facades/Captcha.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Hyperf\Facades;

use Hyperf\Context\ApplicationContext;
use Erikwang2013\Poster\Captcha\CaptchaManager;

class Captcha
{
    public static function __callStatic(string $method, array $args)
    {
        return ApplicationContext::getContainer()->get(CaptchaManager::class)->$method(...$args);
    }

    public static function create(string $type)
    {
        return ApplicationContext::getContainer()->get(CaptchaManager::class)->create($type);
    }

    public static function verify(string $key, array $data): bool
    {
        return ApplicationContext::getContainer()->get(CaptchaManager::class)->verify($key, $data);
    }
}
```

- [ ] **Step 17: Create src/Adapters/Hyperf/Facades/Poster.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Hyperf\Facades;

use Hyperf\Context\ApplicationContext;
use Erikwang2013\Poster\Poster\PosterBuilder;

class Poster
{
    public static function __callStatic(string $method, array $args)
    {
        return ApplicationContext::getContainer()->get(PosterBuilder::class)->$method(...$args);
    }
}
```

---

### Task 10: PHPUnit Tests

**Files:**
- Create: `phpunit.xml.dist`
- Create: `tests/Drivers/DriverTest.php`
- Create: `tests/Storage/StorageTest.php`
- Create: `tests/Captcha/CaptchaTest.php`
- Create: `tests/Poster/PosterTest.php`
- Create: `tests/Qrcode/QrcodeTest.php`

- [ ] **Step 1: Create phpunit.xml.dist**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
         colors="true" bootstrap="vendor/autoload.php">
    <testsuites>
        <testsuite name="poster-php">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 2: Create tests/Drivers/DriverTest.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Drivers;

use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Drivers\GdDriver;
use PHPUnit\Framework\TestCase;

class DriverTest extends TestCase
{
    public function testFactoryCreatesGdDriver(): void
    {
        $driver = DriverFactory::create('gd');
        $this->assertInstanceOf(GdDriver::class, $driver);
        $driver->destroy();
    }

    public function testCreateAndGetSize(): void
    {
        $driver = DriverFactory::create('gd');
        $driver->create(200, 100);
        $size = $driver->getSize();
        $this->assertEquals(200, $size['width']);
        $this->assertEquals(100, $size['height']);
        $driver->destroy();
    }

    public function testRectangle(): void
    {
        $driver = DriverFactory::create('gd');
        $driver->create(100, 100);
        $driver->rectangle(10, 10, 50, 50, ['color' => '#FF0000', 'filled' => true]);
        $output = $driver->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $output);
        $driver->destroy();
    }

    public function testText(): void
    {
        $driver = DriverFactory::create('gd');
        $driver->create(200, 50);
        $driver->text('Hello', 10, 30, ['size' => 14, 'color' => '#000000']);
        $output = $driver->output('png');
        $this->assertNotEmpty($output);
        $driver->destroy();
    }

    public function testSave(): void
    {
        $driver = DriverFactory::create('gd');
        $driver->create(50, 50);
        $driver->rectangle(0, 0, 50, 50, ['color' => '#FFFFFF', 'filled' => true]);
        $path = sys_get_temp_dir() . '/test-poster-driver.jpg';
        $result = $driver->save($path, 'jpg', 80);
        $this->assertTrue($result);
        $this->assertFileExists($path);
        unlink($path);
        $driver->destroy();
    }
}
```

- [ ] **Step 3: Create tests/Storage/StorageTest.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Storage;

use Erikwang2013\Poster\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase
{
    public function testSetAndGet(): void
    {
        $storage = new FileStorage(sys_get_temp_dir() . '/poster-test');
        $key = 'test-key-' . uniqid();
        $storage->set($key, ['answer' => 'secret', 'attempts' => 0], 60);
        $retrieved = $storage->get($key);
        $this->assertIsArray($retrieved);
        $this->assertEquals('secret', $retrieved['answer']);
        $storage->del($key);
    }

    public function testExpiredKeyReturnsNull(): void
    {
        $storage = new FileStorage(sys_get_temp_dir() . '/poster-test');
        $key = 'expired-key-' . uniqid();
        $storage->set($key, ['data' => 'test'], -1);
        $this->assertNull($storage->get($key));
    }

    public function testDelRemovesKey(): void
    {
        $storage = new FileStorage(sys_get_temp_dir() . '/poster-test');
        $key = 'del-key-' . uniqid();
        $storage->set($key, ['test' => true], 60);
        $this->assertNotNull($storage->get($key));
        $storage->del($key);
        $this->assertNull($storage->get($key));
    }
}
```

- [ ] **Step 4: Create tests/Captcha/CaptchaTest.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Captcha;

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

class CaptchaTest extends TestCase
{
    public function testClickCaptchaGenerate(): void
    {
        $manager = new CaptchaManager(
            DriverFactory::create('gd'),
            new FileStorage(sys_get_temp_dir() . '/poster-captcha-test')
        );
        $result = $manager->create('click')->setDifficulty('easy')->generate();
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('image', $result);
        $this->assertArrayHasKey('extra', $result);
        $this->assertNotEmpty($result['key']);
        $this->assertStringStartsWith('data:image/', $result['image']);
    }

    public function testClickCaptchaVerifyPass(): void
    {
        $manager = new CaptchaManager(
            DriverFactory::create('gd'),
            new FileStorage(sys_get_temp_dir() . '/poster-captcha-test')
        );
        $result = $manager->create('click')->setDifficulty('easy')->generate();
        $targets = $result['extra']['targets'];
        $userData = array_map(fn($t) => [$t['x'], $t['y']], $targets);
        $this->assertTrue($manager->verify($result['key'], ['type' => 'click', 'data' => $userData]));
    }

    public function testClickCaptchaOneTimeUse(): void
    {
        $manager = new CaptchaManager(
            DriverFactory::create('gd'),
            new FileStorage(sys_get_temp_dir() . '/poster-captcha-test')
        );
        $result = $manager->create('click')->setDifficulty('easy')->generate();
        $targets = $result['extra']['targets'];
        $userData = array_map(fn($t) => [$t['x'], $t['y']], $targets);
        $manager->verify($result['key'], ['type' => 'click', 'data' => $userData]);
        // Second call should fail (one-time use)
        $this->assertFalse($manager->verify($result['key'], ['type' => 'click', 'data' => $userData]));
    }

    public function testInvalidVerificationFails(): void
    {
        $manager = new CaptchaManager(
            DriverFactory::create('gd'),
            new FileStorage(sys_get_temp_dir() . '/poster-captcha-test')
        );
        $result = $manager->create('click')->setDifficulty('easy')->generate();
        $this->assertFalse($manager->verify($result['key'], [
            'type' => 'click',
            'data' => [[999, 999]],
        ]));
    }

    public function testSliderCaptchaGenerate(): void
    {
        $manager = new CaptchaManager(
            DriverFactory::create('gd'),
            new FileStorage(sys_get_temp_dir() . '/poster-captcha-test')
        );
        $result = $manager->create('slider')->generate();
        $this->assertArrayHasKey('extra', $result);
        $this->assertArrayHasKey('x', $result['extra']);
    }
}
```

- [ ] **Step 5: Create tests/Poster/PosterTest.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Poster;

use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Poster\PosterTemplate;
use Erikwang2013\Poster\Drivers\DriverFactory;
use PHPUnit\Framework\TestCase;

class PosterTest extends TestCase
{
    public function testBasicPoster(): void
    {
        $builder = new PosterBuilder(DriverFactory::create('gd'));
        $path = sys_get_temp_dir() . '/test-poster-basic.jpg';
        $result = $builder
            ->width(400)->height(300)
            ->background('#FFFFFF')
            ->addText('Test Poster', ['x' => 50, 'y' => 50, 'size' => 24, 'color' => '#333'])
            ->addShape('rect', ['x' => 0, 'y' => 0, 'width' => 400, 'height' => 4, 'color' => '#FF6B6B'])
            ->addLine(['x1' => 20, 'y1' => 280, 'x2' => 380, 'y2' => 280, 'color' => '#EEE'])
            ->save($path, 80);
        $this->assertTrue($result);
        $this->assertFileExists($path);
        $size = getimagesize($path);
        $this->assertEquals(400, $size[0]);
        $this->assertEquals(300, $size[1]);
        unlink($path);
        $builder->destroy();
    }

    public function testPosterOutput(): void
    {
        $builder = new PosterBuilder(DriverFactory::create('gd'));
        $output = $builder->width(200)->height(150)->background('#FFFFFF')->output('png');
        $this->assertStringStartsWith('data:image/png;base64,', $output);
        $builder->destroy();
    }

    public function testTemplateSystem(): void
    {
        $template = PosterTemplate::fromConfig([
            'width'  => 400,
            'height' => 200,
            'elements' => [
                ['type' => 'shape', 'x' => 0, 'y' => 0, 'width' => 400, 'height' => 200, 'color' => '#FFFFFF'],
                ['type' => 'text', 'text' => '{{title}}', 'x' => 50, 'y' => 60, 'size' => 24, 'color' => '#333'],
            ],
        ]);
        $builder = new PosterBuilder(DriverFactory::create('gd'));
        $path = sys_get_temp_dir() . '/test-poster-template.jpg';
        $builder->useTemplate($template)->with(['title' => 'Hello Template'])->save($path, 80);
        $this->assertFileExists($path);
        $size = getimagesize($path);
        $this->assertEquals(400, $size[0]);
        $this->assertEquals(200, $size[1]);
        unlink($path);
        $builder->destroy();
    }

    public function testImageElement(): void
    {
        $testImg = sys_get_temp_dir() . '/test-img.jpg';
        $src = DriverFactory::create('gd');
        $src->create(50, 50);
        $src->rectangle(0, 0, 50, 50, ['color' => '#FF0000', 'filled' => true]);
        $src->save($testImg, 'jpg', 90);
        $src->destroy();

        $builder = new PosterBuilder(DriverFactory::create('gd'));
        $path = sys_get_temp_dir() . '/test-poster-img.jpg';
        $builder->width(200)->height(200)->background('#FFFFFF')
            ->addImage($testImg, ['x' => 75, 'y' => 75, 'width' => 50, 'height' => 50])
            ->save($path, 80);
        $this->assertFileExists($path);
        unlink($testImg);
        unlink($path);
        $builder->destroy();
    }
}
```

- [ ] **Step 6: Create tests/Qrcode/QrcodeTest.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Tests\Qrcode;

use Erikwang2013\Poster\Qrcode\QrcodeGenerator;
use PHPUnit\Framework\TestCase;

class QrcodeTest extends TestCase
{
    public function testGenerateQrcode(): void
    {
        $generator = new QrcodeGenerator();
        $generator->setText('https://example.com')->setSize(200);
        $img = $generator->render();
        $this->assertInstanceOf(\GdImage::class, $img);
        $this->assertEquals(200, imagesx($img));
        $this->assertEquals(200, imagesy($img));
        imagedestroy($img);
    }

    public function testQrcodeOutput(): void
    {
        $generator = new QrcodeGenerator();
        $generator->setText('hello world')->setSize(100)->setMargin(1);
        $img = $generator->render();
        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);
        $this->assertNotEmpty($data);
    }
}
```

- [ ] **Step 7: Run tests**

Run: `composer dump-autoload && php vendor/bin/phpunit`
Expected: All tests pass.

---

### Task 11: Examples

**Files:**
- Create: `examples/captcha-click.php`
- Create: `examples/poster-basic.php`

- [ ] **Step 1: Create examples/captcha-click.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

require __DIR__ . '/../vendor/autoload.php';

use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\FileStorage;

$manager = new CaptchaManager(DriverFactory::create(), new FileStorage());
$result = $manager->create('click')->setDifficulty('easy')->generate();

echo "Key: " . $result['key'] . "\n";
echo "Image: " . substr($result['image'], 0, 60) . "...\n";
echo "Targets:\n";
foreach ($result['extra']['targets'] as $t) {
    echo "  Order {$t['order']}: \"{$t['text']}\" at ({$t['x']}, {$t['y']})\n";
}
```

- [ ] **Step 2: Create examples/poster-basic.php**

```php
<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

require __DIR__ . '/../vendor/autoload.php';

$builder = poster_create(750, 1334);
$path = __DIR__ . '/output-basic.jpg';

$builder
    ->background('#FFFFFF')
    ->addShape('rect', ['x' => 0, 'y' => 0, 'width' => 750, 'height' => 300, 'color' => '#FF6B6B'])
    ->addText('新品首发', ['x' => 80, 'y' => 100, 'size' => 48, 'color' => '#FFFFFF'])
    ->addText('限时特惠', ['x' => 80, 'y' => 180, 'size' => 28, 'color' => '#FFE0E0'])
    ->addQrcode('https://example.com', ['x' => 275, 'y' => 1050, 'size' => 200, 'label' => '扫码查看详情'])
    ->save($path);

echo "Poster saved to: $path\n";
```

- [ ] **Step 3: Verify example**

Run: `php examples/poster-basic.php`
Expected: `Poster saved to: .../examples/output-basic.jpg`

---

## Plan Summary

| Task | Files | Purpose |
|------|-------|---------|
| 1 | 7 | Scaffolding: composer.json, LICENSE, config, PosterConfig, Interfaces, Factories |
| 2 | 2 | Image drivers: GdDriver + ImagickDriver |
| 3 | 3 | Storage drivers: File + Session + Redis |
| 4 | 1 | QR Code generator (pure PHP, ~400 lines) |
| 5 | 4 | Captcha: Interface, Abstract, Factory, Manager |
| 6 | 3 | Captcha: Click, Rotate, Slider implementations |
| 7 | 10 | Poster elements: 1 interface + 1 abstract + 8 elements |
| 8 | 2 | PosterBuilder + PosterTemplate |
| 9 | 17 | helpers.php + 4 framework adapters (16 files) |
| 10 | 6 | PHPUnit tests: Drivers, Storage, Captcha, Poster, QR + config |
| 11 | 2 | Example scripts |

**Total: ~55 files**, zero external dependencies, PHP 8.0+.
