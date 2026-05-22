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

`erikwang2013/poster-php` — PHP package providing image captcha (click/rotate/slider + random switching) and poster generation (14 element types). Framework-agnostic core with adapters for Laravel, ThinkPHP, Webman, and Hyperf.

## Technology Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Image driver | GD + Imagick dual, auto-detect | GD is built-in; Imagick for better performance when available |
| Storage driver | File / Session / Redis, configurable | Single-server to distributed deployments |
| QR code | Pure PHP self-implemented | Zero external dependencies; package stays lightweight |
| Architecture | Modular + Builder pattern | Clean separation, testable, chainable API |
| PHP baseline | 8.0+ | Union types, named arguments, attributes available |
| PHP extensions | ext-gd, ext-mbstring | Required; declared in composer.json |
| Bundled font | None yet; planned | Captcha and Poster text use GD built-in fallback when no custom font |
| Captcha backgrounds | Auto-generated (noise + random colors/patterns) when no custom background is set | No external assets needed |

## Directory Structure

```
erikwang2013/poster-php/
├── composer.json
├── config/
│   └── poster.php                          # Default config (bilingual CN/EN comments)
├── .env.example                            # Environment variable template (bilingual)
├── helpers.php                             # Framework-agnostic helpers
├── README.md                               # Chinese documentation
├── README_EN.md                            # English documentation
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
│   │       ├── TableElement.php
│   │       ├── ChartElement.php            # Bar / line / pie charts
│   │       ├── CalendarElement.php         # Monthly calendar
│   │       ├── ArtisticTextElement.php     # Stroke / shadow / gradient / neon
│   │       ├── EmojiElement.php            # Color emoji rendering
│   │       ├── IconElement.php             # FontAwesome icon rendering
│   │       └── EmoticonElement.php         # Kaomoji / custom emoticons
│   ├── Drivers/
│   │   ├── ImageDriverInterface.php
│   │   ├── GdDriver.php
│   │   └── ImagickDriver.php
│   ├── Qrcode/
│   │   └── QrcodeGenerator.php             # Pure PHP QR Code Model 2
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
    └── poster-basic.php
```

## Captcha Module

### Unified API

```php
$manager = new CaptchaManager($storageDriver, $imageDriver);

// Create any type including random
$result = $manager->create('click')    // 'click' | 'rotate' | 'slider' | 'random'
    ->setDifficulty('easy')
    ->setBackground('path/to/bg.jpg')
    ->generate();
// Returns: ['key' => 'uniq-id', 'type' => 'click', 'image' => 'base64...', 'extra' => [...]]

$pass = $manager->verify('key-xxx', ['type' => 'click', 'data' => [[120,80], ...]]);
// Returns: bool
```

### Random Captcha

`create('random')` randomly picks from click/rotate/slider using `array_rand()`. The result includes the `type` field so the frontend knows which component to render. Verification uses the same type returned in the result.

### ClickCaptcha

- Randomly places N targets on background image
- Target types: 'text' (Chinese characters)
- User must click targets in displayed order
- Tolerance: 18px radius from target center
- Methods: `setTargetCount()`, `setTargetType()`

### RotateCaptcha

- Randomly rotates image by 30°–330°
- Returns rotated image; user drags slider to rotate back
- Tolerance: ±5° from correct angle
- Methods: `setAngleRange(min, max)`

### SliderCaptcha

- Cuts a puzzle piece from background, shifts it horizontally
- User slides piece into gap
- Tolerance: ±4px from correct X position

### Security

- One-time use: key deleted after successful verification
- Max 3 attempts per key (prevent brute-force), attempts increment on failure
- Default TTL: 300 seconds
- Random order for click targets; random angle/position for rotate/slider

## Poster Module

### Builder API (chainable)

```php
$builder = new PosterBuilder($imageDriver);

// 14 element types available
$builder
    ->width(750)->height(1334)
    ->background('#FFFFFF')                    // hex color or image path
    ->backgroundGradient('#FF6B6B', '#FF8E53', 'vertical')
    // Basic elements
    ->addText('Hello', ['x'=>80,'y'=>120,'size'=>48,'color'=>'#333','font'=>'/path.ttf'])
    ->addImage('product.jpg', ['x'=>75,'y'=>280,'width'=>600,'height'=>600,'radius'=>12])
    ->addAvatar('avatar.jpg', ['x'=>80,'y'=>60,'size'=>120])
    ->addQrcode('https://example.com', ['x'=>275,'y'=>1100,'size'=>200,'logo'=>'icon.png'])
    ->addShape('rect', ['x'=>0,'y'=>0,'width'=>750,'height'=>60,'color'=>'#FF6B6B'])
    ->addLine(['x1'=>75,'y1'=>800,'x2'=>675,'y2'=>800,'color'=>'#EEE'])
    ->addWatermark('CONFIDENTIAL', ['font'=>'/path.ttf','size'=>24,'color'=>'#00000020','angle'=>45])
    ->addTable(['x'=>50,'y'=>800,'width'=>650,'columns'=>[150,350,150],'header'=>[...],'rows'=>[...]])
    // Extended elements
    ->addChart('bar', [['label'=>'A','value'=>30],...], ['x'=>50,'y'=>100,'width'=>650,'height'=>400])
    ->addChart('line', [...], ['x'=>50,'y'=>100,'width'=>650,'height'=>400,'colors'=>['#FF6B6B']])
    ->addChart('pie', [...], ['x'=>75,'y'=>100,'width'=>600,'height'=>600])
    ->addCalendar(['x'=>50,'y'=>200,'year'=>2026,'month'=>5,'highlights'=>['2026-05-16'=>'Today']])
    ->addArtisticText('SALE', 'stroke', ['x'=>80,'y'=>120,'size'=>72,'color'=>'#FF6B6B','strokeColor'=>'#000'])
    ->addArtisticText('VIP', 'gradient', ['x'=>80,'y'=>200,'size'=>60,'color'=>'#FF6B6B','color2'=>'#FF8E53'])
    ->addArtisticText('HOT', 'neon', ['x'=>80,'y'=>300,'size'=>56,'color'=>'#FF1493'])
    ->addEmoji('😀', ['x'=>100,'y'=>100,'size'=>64])
    ->addIcon('heart', ['x'=>20,'y'=>40,'size'=>32,'color'=>'#E74C3C','font'=>'/path/fa-solid-900.ttf'])
    ->addEmoticon('happy', ['x'=>20,'y'=>40,'size'=>24])
    ->save('/output/poster.jpg', 90);
```

### Element Types (14 total)

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
| ChartElement | Bar / line / pie charts with colors, labels, axis/grid |
| CalendarElement | Monthly calendar grid, highlight dates, bilingual day names |
| ArtisticTextElement | 4 styles: stroke outline, drop shadow, vertical gradient, neon glow |
| EmojiElement | Renders emoji characters via system or custom color font |
| IconElement | FontAwesome (or custom) icon font rendering by name or codepoint |
| EmoticonElement | 12 built-in kaomoji expressions (happy, love, cry, etc.) + custom text |

### Template System

```php
$template = PosterTemplate::fromConfig([...]);
$builder->useTemplate($template)->with(['title'=>'My Title', 'url'=>'...'])->save('out.jpg');
```

Template supports all 14 element types. Element definitions use JSON-serializable arrays with `{{placeholder}}` values. `->with()` replaces placeholders before rendering.

## Image Driver Layer

```php
interface ImageDriverInterface
{
    public function load(string $path): static;
    public function create(int $width, int $height): static;
    public function resize(int $width, int $height): static;
    public function rotate(float $angle, string $bgColor = '#000000'): static;
    public function crop(int $x, int $y, int $width, int $height): static;
    public function text(string $text, int $x, int $y, array $options): static;
    public function image(self $overlay, int $x, int $y, array $options = []): static;
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

**GdDriver additions:**
- `setGdResource(\GdImage $gd): void` — Set GD image directly (used by QrcodeElement and ArtisticTextElement for clean compositing without reflection)

### DriverFactory

```php
class DriverFactory
{
    public static function create(?string $driver = null): ImageDriverInterface
    public static function isImagickAvailable(): bool
}
```

Auto-detection: `extension_loaded('imagick') && class_exists('Imagick')` → ImagickDriver, else GdDriver.

## Storage Layer

```php
interface StorageInterface
{
    public function set(string $key, array $data, int $ttl = 300): bool;
    public function get(string $key): ?array;
    public function del(string $key): bool;
    public function has(string $key): bool;
    public function incrementAttempts(string $key): int;
}
```

- **FileStorage**: JSON files in configurable directory, filename = md5(key)
- **SessionStorage**: `$_SESSION['poster_captcha'][$key]`
- **RedisStorage**: `SETEX poster:captcha:{$key} {ttl} {json}`

### StorageFactory

Auto-detection: Redis (with try/catch fallback) → Session (non-CLI, active session) → File.

## QR Code Generator

Pure PHP implementation in `src/Qrcode/QrcodeGenerator.php`:

- QR Code Model 2, versions 1–40
- Error correction levels L, M, Q, H
- Byte mode encoding
- Output as GD resource (compatible with GdDriver::setGdResource)
- API: `(new QrcodeGenerator())->setText($url)->setSize($px)->setMargin($px)->setErrorLevel('H')->render()`

## Framework Adapters

Four frameworks, 16 adapter files total. Each adapter is ~30 lines: register services + publish config.

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

- **Laravel**: ServiceProvider with singleton bindings + Facade
- **ThinkPHP**: Service with `$this->app->bind()` + Facade
- **Webman**: Bootstrap plugin with static factory methods + static proxy
- **Hyperf**: ConfigProvider with DI factories + Facade via ApplicationContext

### Adapter Responsibilities

1. Publish `config/poster.php` to framework's config directory
2. Bind `CaptchaManager` and `PosterBuilder` into DI container
3. Register Facade aliases
4. Nothing else — all logic stays in `src/`

## Config

```php
return [
    'image' => [
        'driver'  => 'auto',    // 'auto' | 'gd' | 'imagick'
        'quality' => 90,        // JPEG output quality 0-100
        'font'    => null,      // Custom font path, null=GD built-in
    ],
    'captcha' => [
        'storage'            => 'auto',   // 'auto' | 'file' | 'session' | 'redis'
        'ttl'                => 300,       // Expiry in seconds
        'max_attempts'       => 3,         // Max attempts per key
        'default_difficulty'  => 'medium', // 'easy' | 'medium' | 'hard'
        'tolerance' => [
            'click'  => 18,   // Pixel radius
            'rotate' => 5,    // Degrees
            'slider' => 4,    // Pixels
        ],
        'redis' => ['prefix' => 'poster:captcha:', 'connection' => 'default'],
        'file'  => ['path' => null],
    ],
    'poster' => [
        'default_width'   => 750,
        'default_height'  => 1334,
        'font'            => null,
        'jpeg_quality'    => 90,
        'png_compression' => 6,
    ],
];
```

Environment variable overrides in `.env.example`.

## Coding Rules

- **Copyright**: Every `.php` file MUST contain: `Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz`
- **Global variables**: Never prefix with `\`. Use `$_SESSION`, `$_SERVER` — not `\$_SESSION`.
- **Namespace root**: `Erikwang2013\Poster` — no leading `\` in namespace declarations.

## Naming convention

- Namespace root: `Erikwang2013\Poster`
- PSR-4 mapping: `Erikwang2013\Poster\` → `src/`
- Adapters: `Erikwang2013\Poster\Adapters\{Framework}\`
- Framework-agnostic helpers in `helpers.php`:
  - `captcha_create($type, $options)` — supports 'click' | 'rotate' | 'slider' | 'random'
  - `captcha_verify($key, $type, $data)`
  - `poster_create($width, $height)`

## Testing Strategy

- PHPUnit 11.x, no external services needed
- Image tests: compare pixel samples, not full image binary diffs
- Storage tests: test each driver with interface contract
- Captcha tests: generate → verify pass, wrong data → verify fail, expired → verify fail, retry on failure, random type
- Poster tests: build with each element type, verify output dimensions and format
- Driver tests: create, rectangle, text, save, output
- QR code tests: verify GdImage output dimensions and non-empty PNG
