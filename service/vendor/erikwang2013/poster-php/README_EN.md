# poster-php

PHP image captcha & poster generation toolkit — framework-agnostic core with Laravel / ThinkPHP / Webman / Hyperf adapters.

[中文文档](README.md) | [Architecture Diagrams](docs/architecture.md)

## Features

### Captcha (3 types + random)

| Type | Description |
|------|-------------|
| Click `click` | User clicks target characters on image in order |
| Rotate `rotate` | User drags slider to rotate image back to correct orientation |
| Slider `slider` | User drags puzzle piece into the gap |
| Random `random` | Randomly picks one of the three captcha types |

### Poster Generation

Fluent Builder API, 14 element types:

| Element | Method | Description |
|---------|--------|-------------|
| Text | `addText()` | Auto line-wrap, alignment, multi-line |
| Image | `addImage()` | Resize, crop modes, border-radius, shadow |
| Avatar | `addAvatar()` | Circle crop, border |
| QR Code | `addQrcode()` | Pure PHP generation, center logo, label |
| Shape | `addShape()` | Rectangle/circle/rounded-rect, fill/stroke |
| Line | `addLine()` | Color, width |
| Watermark | `addWatermark()` | Tiled text, configurable angle & spacing |
| Table | `addTable()` | Header row, zebra stripes, column widths |
| Chart | `addChart()` | Bar / line / pie charts |
| Calendar | `addCalendar()` | Monthly calendar, date highlights, labels |
| Artistic Text | `addArtisticText()` | Stroke / shadow / gradient / neon effects |
| Emoji | `addEmoji()` | Color emoji rendering |
| Icon | `addIcon()` | FontAwesome icon rendering |
| Emoticon | `addEmoticon()` | Kaomoji / custom emoticons |

## Installation

```bash
composer require erikwang2013/poster-php
```

Requirements: PHP >= 8.0, GD extension.

Optional extensions:
- `ext-imagick`: ImageMagick driver (better performance)
- `ext-redis`: Redis captcha storage (distributed deployments)

## Usage

### Captcha

#### Click Captcha

```php
$result = captcha_create('click', [
    'difficulty' => 'medium',    // 'easy'(2 targets) | 'medium'(3) | 'hard'(4)
    'background' => null,        // custom background path, null=auto-generated
]);
// Returns: ['key' => '...', 'image' => 'data:image/png;base64,...', 'extra' => ['targets' => [...]]]

$pass = captcha_verify($result['key'], 'click', [[120, 80], [200, 150], [310, 95]]);
// tolerance: ±18px

// Full API via CaptchaManager
use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\FileStorage;

$manager = new CaptchaManager(DriverFactory::create(), new FileStorage());
$captcha = $manager->create('click')
    ->setDifficulty('hard')
    ->setTargetCount(4)
    ->setBackground('/path/to/bg.jpg');
$result = $captcha->generate();

$pass = $manager->verify($result['key'], ['type' => 'click', 'data' => [[120,80],[200,150],[310,95],[180,60]]]);
```

#### Rotate Captcha

```php
$result = captcha_create('rotate');
$pass = captcha_verify($result['key'], 'rotate', 185); // ±5° tolerance

// Custom angle range
$captcha = $manager->create('rotate')->setAngleRange(45, 315)->generate();
```

#### Slider Captcha

```php
$result = captcha_create('slider');
// $result['extra'] = ['puzzle' => '...', 'puzzle_w' => 50, 'puzzle_h' => 50]

$pass = captcha_verify($result['key'], 'slider', 173); // ±4px tolerance
```

#### Random Captcha

Randomly picks one of click / rotate / slider to increase cracking difficulty.

```php
// Generate random captcha type
$result = captcha_create('random');
// $result['type'] = 'click' | 'rotate' | 'slider'

// Frontend reads $result['type'] to render the matching component
switch ($result['type']) {
    case 'click':  /* render click targets */  break;
    case 'rotate': /* render rotation slider */ break;
    case 'slider': /* render puzzle piece */    break;
}

// Verify with the actual type from the result
$pass = captcha_verify($result['key'], $result['type'], $userData);

// Or via CaptchaManager
$captcha = $manager->create('random')->generate();
$pass = $manager->verify($captcha['key'], ['type' => $captcha['type'], 'data' => $userData]);
```

#### Security

| Feature | Description |
|---------|-------------|
| One-time use | Key deleted after successful verification or max attempts |
| Brute-force protection | Max 3 attempts per key (configurable) |
| TTL | 300 seconds default (configurable) |
| Randomization | Background colors, noise, positions randomly generated |

### Poster

#### Basic Usage

```php
// Via helper
$builder = poster_create(750, 1334);
// Or directly
$builder = new PosterBuilder(DriverFactory::create());
$builder->width(750)->height(1334);

// Background
$builder->background('#FFFFFF');                            // solid color
$builder->background('/path/to/bg.jpg');                    // image (auto-resized)
$builder->backgroundGradient('#FF6B6B', '#FF8E53', 'vertical'); // gradient

// Output
$builder->save('/output/poster.jpg', 90);  // save to file (path, quality 0-100)
$dataUrl = $builder->output('png', 90);    // base64 data URL
```

#### All Elements Quick Reference

```php
// Text — auto-wrap, alignment
$builder->addText('Hello', ['x'=>80,'y'=>120,'size'=>48,'color'=>'#333','align'=>'center','maxWidth'=>600]);

// Image — resize, rounded corners, shadow
$builder->addImage('product.jpg', ['x'=>75,'y'=>280,'width'=>600,'height'=>600,'radius'=>12,'shadow'=>['color'=>'#00000033','offsetX'=>4,'offsetY'=>4,'blur'=>10]]);

// Avatar — circular crop, border
$builder->addAvatar('avatar.jpg', ['x'=>80,'y'=>60,'size'=>120,'border'=>'#FF6B6B']);

// QR Code — pure PHP, optional center logo + label
$builder->addQrcode('https://example.com', ['x'=>275,'y'=>1050,'size'=>200,'level'=>'H','logo'=>'icon.png','label'=>'Scan me']);

// Shape — rectangle / circle, filled / stroked, rounded corners
$builder->addShape('rect', ['x'=>0,'y'=>0,'width'=>750,'height'=>60,'color'=>'#FF6B6B','filled'=>true,'radius'=>8]);
$builder->addShape('circle', ['x'=>100,'y'=>100,'width'=>80,'height'=>80,'color'=>'#4ECDC4']);

// Line
$builder->addLine(['x1'=>75,'y1'=>800,'x2'=>675,'y2'=>800,'color'=>'#EEE','width'=>1]);

// Watermark — tiled, angled
$builder->addWatermark('CONFIDENTIAL', ['size'=>24,'color'=>'#00000020','angle'=>30,'spacing'=>200]);

// Table — header, zebra stripes
$builder->addTable(['x'=>50,'y'=>800,'width'=>650,'columns'=>[150,350,150],'header'=>['No.','Item','Price'],'rows'=>[['1','Product A','¥99'],['2','Product B','¥199']],'headerBg'=>'#333','rowBg'=>['#FFF','#F5F5F5']]);

// Chart — bar / line / pie
$builder->addChart('bar', [['label'=>'A','value'=>30],['label'=>'B','value'=>60]], ['x'=>50,'y'=>100,'width'=>650,'height'=>400,'colors'=>['#FF6B6B','#4ECDC4']]);
$builder->addChart('line', [['label'=>'Mon','value'=>10],['label'=>'Tue','value'=>35]], ['x'=>50,'y'=>100,'width'=>650,'height'=>400,'colors'=>['#FF6B6B']]);
$builder->addChart('pie', [['label'=>'A','value'=>45],['label'=>'B','value'=>25]], ['x'=>75,'y'=>100,'width'=>600,'height'=>600,'colors'=>['#FF6B6B','#4ECDC4','#45B7D1']]);

// Calendar — month view with highlights
$builder->addCalendar(['x'=>50,'y'=>200,'year'=>2026,'month'=>5,'cellSize'=>60,'highlights'=>['2026-05-16'=>['bg'=>'#FFEAA7','text'=>'Today']]]);

// Artistic Text — stroke / shadow / gradient / neon
$builder->addArtisticText('SALE', 'stroke', ['x'=>80,'y'=>120,'size'=>72,'color'=>'#FF6B6B','strokeColor'=>'#000','strokeWidth'=>3]);
$builder->addArtisticText('NEW', 'shadow', ['x'=>80,'y'=>120,'size'=>48,'color'=>'#333','shadowColor'=>'#00000033']);
$builder->addArtisticText('VIP', 'gradient', ['x'=>80,'y'=>120,'size'=>60,'color'=>'#FF6B6B','color2'=>'#FF8E53']);
$builder->addArtisticText('HOT', 'neon', ['x'=>80,'y'=>120,'size'=>56,'color'=>'#FF1493']);

// Emoji — auto-detects system emoji font
$builder->addEmoji('😀', ['x'=>100,'y'=>100,'size'=>64]);
$builder->addEmoji('🎉', ['x'=>180,'y'=>100,'size'=>64]);
// or by codepoint: 'codepoint'=>'U+1F600'

// Icon — requires FontAwesome TTF font file
$builder->addIcon('heart', ['x'=>20,'y'=>40,'size'=>32,'color'=>'#E74C3C','font'=>'/path/to/fa-solid-900.ttf']);
// Built-in icon names: heart, star, user, clock, home, cog, check, times, search, etc.

// Emoticon — kaomoji expressions
$builder->addEmoticon('happy', ['x'=>20,'y'=>40,'size'=>24]);  // (｡•̀ᴗ-)✧
$builder->addEmoticon('cry', ['x'=>20,'y'=>80,'size'=>24]);    // (╥﹏╥)
// Expressions: happy, love, cry, angry, surprised, cool, sleepy, wave, think, shrug, tableflip, lenny
```

### Template System

```php
$template = PosterTemplate::fromConfig([
    'width'  => 750, 'height' => 1334,
    'elements' => [
        ['type' => 'text',   'text' => '{{title}}', 'x' => 80, 'y' => 100, 'size' => 48],
        ['type' => 'image',  'src'  => '{{cover}}',  'x' => 75, 'y' => 350, 'width' => 600, 'height' => 600],
        ['type' => 'qrcode', 'content' => '{{url}}',  'x' => 275, 'y' => 1050, 'size' => 200],
    ],
]);

$builder->useTemplate($template)->with([
    'title' => 'New Arrival', 'cover' => 'product.jpg', 'url' => 'https://example.com/123',
])->save('poster.jpg');
```

## Framework Integration

### Laravel

```php
use Erikwang2013\Poster\Adapters\Laravel\Facades\Captcha;
use Erikwang2013\Poster\Adapters\Laravel\Facades\Poster;

$result = Captcha::create('click')->generate();
Poster::width(750)->height(1334)->background('#FFF')->save('poster.jpg');
```

### ThinkPHP

Register services in `config/web.php`:
```php
'services' => [
    Erikwang2013\Poster\Adapters\ThinkPHP\CaptchaService::class,
    Erikwang2013\Poster\Adapters\ThinkPHP\PosterService::class,
],
```

### Webman

Register in `config/bootstrap.php`:
```php
return [
    Erikwang2013\Poster\Adapters\Webman\CaptchaPlugin::class,
    Erikwang2013\Poster\Adapters\Webman\PosterPlugin::class,
];
```

### Hyperf

Auto-registered via ConfigProvider.

## Configuration

See `config/poster.php`. Environment overrides in `.env.example`.

## License

MIT License — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
