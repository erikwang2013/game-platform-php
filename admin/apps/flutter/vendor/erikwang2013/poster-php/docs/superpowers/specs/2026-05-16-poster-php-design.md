# poster-php Design Spec

## License

Every source file MUST include this header, immutable and irreversible:

```php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */
```

## Overview

`erikwang2013/poster-php` — PHP package providing image captcha (click/rotate/slider) and poster generation. Framework-agnostic core with adapters for Laravel, ThinkPHP, Webman, and Hyperf.

## Technology Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Image driver | GD + Imagick dual, auto-detect | GD is built-in; Imagick for better performance when available |
| Storage driver | File / Session / Redis, configurable | Single-server to distributed deployments |
| QR code | Pure PHP self-implemented | Zero external dependencies; package stays lightweight |
| Architecture | Modular + Builder pattern | Clean separation, testable, chainable API |
| PHP baseline | 8.0+ | Union types, named arguments, attributes available |
| Dependencies | Zero required (GD/Imagick are PHP extensions, not composer packages) | `composer require` pulls nothing but this package |
| Bundled font | Package bundles a free TTF font (e.g. SourceHanSansSC) for Chinese/ASCII text rendering | Captcha and Poster text work out of the box |
| Captcha backgrounds | Auto-generated (noise + random colors/patterns) when no custom background is set | No external assets needed |

## Directory Structure

```
erikwang2013/poster-php/
├── composer.json
├── config/
│   └── poster.php                          # Default config
├── helpers.php                             # Framework-agnostic helpers
├── src/
│   ├── Captcha/
│   │   ├── CaptchaInterface.php
│   │   ├── AbstractCaptcha.php
│   │   ├── ClickCaptcha.php
│   │   ├── RotateCaptcha.php
│   │   ├── SliderCaptcha.php
│   │   ├── CaptchaFactory.php
│   │   └── CaptchaManager.php
│   ├── Poster/
│   │   ├── PosterBuilder.php
│   │   ├── PosterTemplate.php
│   │   └── Elements/
│   │       ├── ElementInterface.php
│   │       ├── AbstractElement.php
│   │       ├── TextElement.php
│   │       ├── ImageElement.php
│   │       ├── QrcodeElement.php
│   │       ├── AvatarElement.php
│   │       ├── ShapeElement.php
│   │       ├── LineElement.php
│   │       ├── WatermarkElement.php
│   │       └── TableElement.php
│   ├── Drivers/
│   │   ├── ImageDriverInterface.php
│   │   ├── GdDriver.php
│   │   └── ImagickDriver.php
│   ├── Qrcode/
│   │   └── QrcodeGenerator.php             # Pure PHP QR implementation
│   ├── Storage/
│   │   ├── StorageInterface.php
│   │   ├── FileStorage.php
│   │   ├── SessionStorage.php
│   │   └── RedisStorage.php
│   └── Adapters/
│       ├── Laravel/
│       │   ├── CaptchaServiceProvider.php
│       │   ├── PosterServiceProvider.php
│       │   └── Facades/
│       │       ├── Captcha.php
│       │       └── Poster.php
│       ├── ThinkPHP/
│       │   ├── CaptchaService.php
│       │   ├── PosterService.php
│       │   └── Facades/
│       │       ├── Captcha.php
│       │       └── Poster.php
│       ├── Webman/
│       │   ├── CaptchaPlugin.php
│       │   ├── PosterPlugin.php
│       │   └── Facades/
│       │       ├── Captcha.php
│       │       └── Poster.php
│       └── Hyperf/
│           ├── CaptchaConfigProvider.php
│           ├── PosterConfigProvider.php
│           └── Facades/
│               ├── Captcha.php
│               └── Poster.php
├── tests/
│   ├── Captcha/
│   ├── Poster/
│   ├── Drivers/
│   ├── Storage/
│   └── Qrcode/
└── examples/
    ├── captcha-click.php
    ├── captcha-rotate.php
    ├── captcha-slider.php
    ├── poster-basic.php
    ├── poster-template.php
    └── poster-full.php
```

## Captcha Module

### Unified API

```php
$manager = new CaptchaManager($storageDriver, $imageDriver);

$result = $manager->create('click')    // 'click' | 'rotate' | 'slider'
    ->setDifficulty('easy')
    ->setBackground('path/to/bg.jpg')
    ->generate();
// Returns: ['key' => 'uniq-id', 'image' => 'base64...', 'extra' => [...]]

$pass = $manager->verify('key-xxx', ['type' => 'click', 'data' => [[120,80], ...]]);
// Returns: bool
```

### ClickCaptcha

- Randomly places N targets on background image
- Target types: 'text' (Chinese characters) or 'icon' (small embedded icons)
- User must click targets in displayed order
- Tolerance: 18px radius from target center

### RotateCaptcha

- Randomly rotates image by 30°–330°
- Returns rotated image; user drags slider to rotate back
- Tolerance: ±5° from correct angle

### SliderCaptcha

- Cuts a puzzle piece from background, shifts it horizontally
- User slides piece into gap
- Tolerance: ±4px from correct X position

### Security

- One-time use: key deleted after verification (pass or fail)
- Max 3 attempts per key (prevent brute-force)
- Default TTL: 300 seconds
- Random order for click targets; random angle/position for rotate/slider

## Poster Module

### Builder API (chainable)

```php
$builder = new PosterBuilder($imageDriver);

$builder
    ->width(750)->height(1334)
    ->background('#FFFFFF')                    // hex color or image path
    ->backgroundGradient('#FF6B6B', '#FF8E53', 'vertical')
    ->addText('Hello', ['x'=>80,'y'=>120,'size'=>48,'color'=>'#333','font'=>'/path.ttf'])
    ->addImage('product.jpg', ['x'=>75,'y'=>280,'width'=>600,'height'=>600,'radius'=>12])
    ->addAvatar('avatar.jpg', ['x'=>80,'y'=>60,'size'=>120])
    ->addQrcode('https://example.com', ['x'=>275,'y'=>1100,'size'=>200,'logo'=>'icon.png'])
    ->addShape('rect', ['x'=>0,'y'=>0,'width'=>750,'height'=>60,'color'=>'#FF6B6B'])
    ->addLine(['x1'=>75,'y1'=>800,'x2'=>675,'y2'=>800,'color'=>'#EEE'])
    ->addWatermark('CONFIDENTIAL', ['font'=>'/path.ttf','size'=>24,'color'=>'#00000020','angle'=>45])
    ->addTable(['x'=>50,'y'=>800,'width'=>650,'columns'=>[150,350,150],'header'=>[...],'rows'=>[...]])
    ->save('/output/poster.jpg', 90);
```

### Element Types

| Element | Key Feature |
|---------|-------------|
| TextElement | Auto line-wrap at maxWidth, alignment, multi-line |
| ImageElement | Resize, crop modes (fill/fit/cover), border-radius, shadow |
| AvatarElement | Auto circle-crop, border |
| QrcodeElement | Pure PHP generation, optional center logo, bottom label |
| ShapeElement | Rectangle, circle, rounded-rect; fill or stroke |
| LineElement | Single line with color/width |
| WatermarkElement | Tiled text across entire canvas |
| TableElement | Header row, zebra stripes, column widths, text alignment per column |

### Template System

```php
$template = PosterTemplate::fromConfig([...]);
$builder->useTemplate($template)->with(['title'=>'My Title', 'url'=>'...'])->save('out.jpg');
```

Template is a JSON-serializable array of element definitions with `{{placeholder}}` values. `->with()` replaces placeholders before rendering.

## Image Driver Layer

```php
interface ImageDriverInterface
{
    public function load(string $path): self;
    public function create(int $width, int $height): self;
    public function resize(int $width, int $height): self;
    public function rotate(float $angle, string $bgColor = '#000000'): self;
    public function crop(int $x, int $y, int $width, int $height): self;
    public function text(string $text, int $x, int $y, array $options): self;
    public function image(self $overlay, int $x, int $y, array $options): self;
    public function rectangle(int $x, int $y, int $width, int $height, array $options): self;
    public function ellipse(int $cx, int $cy, int $rx, int $ry, array $options): self;
    public function line(int $x1, int $y1, int $x2, int $y2, array $options): self;
    public function blur(int $radius): self;
    public function pixelate(int $blockSize): self;
    public function save(string $path, string $format = 'jpg', int $quality = 90): bool;
    public function output(string $format = 'jpg', int $quality = 90): string;
    public function getSize(): array;
    public function destroy(): void;
}

class DriverFactory
{
    public static function create(?string $driver = null): ImageDriverInterface
    {
        $driver ??= 'auto';
        if ($driver === 'auto') {
            return extension_loaded('imagick') ? new ImagickDriver() : new GdDriver();
        }
        return $driver === 'imagick' ? new ImagickDriver() : new GdDriver();
    }
}
```

## Storage Layer

```php
interface StorageInterface
{
    public function set(string $key, array $data, int $ttl = 300): bool;
    public function get(string $key): ?array;
    public function del(string $key): bool;
    public function has(string $key): bool;
}
```

- **FileStorage**: JSON files in configurable directory, filename = md5(key)
- **SessionStorage**: `$_SESSION['poster_captcha'][$key]`
- **RedisStorage**: `SETEX poster:captcha:{$key} {ttl} {json}`

Auto-detection: Redis if extension loaded, otherwise Session.

## Framework Adapters

Each adapter is ~30 lines: register services + publish config. Framework auto-discovery via `composer.json` extra sections:

```json
{
    "extra": {
        "laravel": {
            "providers": [
                "Erikwang2013\\Poster\\Adapters\\Laravel\\CaptchaServiceProvider",
                "Erikwang2013\\Poster\\Adapters\\Laravel\\PosterServiceProvider"
            ]
        }
    }
}
```

ThinkPHP uses `src/Adapters/ThinkPHP/Service.php` registered via composer.extra.
Webman uses `src/Adapters/Webman/Plugin.php`.
Hyperf uses ConfigProvider with `dependencies` and `annotations` keys.

### Adapter Responsibilities

1. Publish `config/poster.php` to framework's config directory
2. Bind `CaptchaManager` and `PosterBuilder` into DI container
3. Register Facade aliases
4. Nothing else — all logic stays in `src/`

## QR Code Generator

Pure PHP implementation in `src/Qrcode/QrcodeGenerator.php`:

- QR Code Model 2, versions 1–40
- Error correction levels L, M, Q, H
- Byte mode encoding
- Output as GD resource (compatible with ImageDriverInterface)
- API: `(new QrcodeGenerator())->setText($url)->setSize($px)->setMargin($px)->setErrorLevel('H')->render()`

## Config Loader

Package provides `PosterConfig` class for framework-agnostic config loading. Framework adapters merge package defaults with framework's own config:

```php
// Framework-agnostic: reads config/poster.php
$config = PosterConfig::load();
$config = PosterConfig::load('/custom/path/poster.php');

// Framework-managed: adapter merges framework config with package defaults
// e.g., Laravel: config('poster.captcha.storage') merges with package defaults
```

Helper functions (`captcha_create`, `poster_create`, etc.) use `PosterConfig::load()` internally to find defaults when called standalone.

## Coding Rules

- **Global variables**: Never prefix with `\`. Use `$_SESSION`, `$_SERVER`, `$_GET`, `$_POST` — not `\$_SESSION`, `\$_SERVER`.
- **Namespace root**: `Erikwang2013\Poster` — no leading `\` in namespace declarations or `use` statements at the top of files.
- **File header**: Every `.php` file MUST contain the copyright header above.

## Config Structure

```php
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
            'click'  => 18,   // 点击验证: 像素半径
            'rotate' => 5,    // 旋转验证: 角度
            'slider' => 4,    // 滑块验证: 像素
        ],
        // Redis 存储配置（storage=redis 时生效）
        'redis' => [
            'prefix'     => 'poster:captcha:',
            'connection' => 'default',
        ],
        // 文件存储配置（storage=file 时生效）
        'file' => [
            'path' => null,   // null 则使用系统临时目录
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

### Naming convention

- Namespace root: `Erikwang2013\Poster`
- PSR-4 mapping: `Erikwang2013\Poster\` → `src/`
- Adapters: `Erikwang2013\Poster\Adapters\{Framework}\`
- Framework-agnostic helpers in `helpers.php`:
  - `captcha_create($type, $options)`
  - `captcha_verify($key, $type, $data)`
  - `poster_create($width, $height)`

## Testing Strategy

- PHPUnit, no external services needed
- Image tests: compare pixel samples, not full image binary diffs
- Storage tests: test each driver with interface contract
- Captcha tests: generate → verify pass, wrong data → verify fail, expired → verify fail
- Poster tests: build with each element type, verify output dimensions and format
- Driver tests: run same test suite against both GdDriver and ImagickDriver
