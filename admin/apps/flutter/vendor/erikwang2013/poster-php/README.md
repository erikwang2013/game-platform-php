# poster-php

PHP 图片验证码与海报生成工具包 —— 框架无关核心 + Laravel / ThinkPHP / Webman / Hyperf 适配。

[English Documentation](README_EN.md)

## 功能

### 验证码（三种方式）

| 类型 | 说明 |
|------|------|
| 点击验证 `click` | 用户按顺序点击图片上的目标文字 |
| 旋转验证 `rotate` | 用户拖动滑块将图片旋转回正确角度 |
| 滑块验证 `slider` | 用户拖动拼图块到缺口位置 |

### 海报生成

链式 Builder API，支持 14 种元素：

| 元素 | 方法 | 说明 |
|------|------|------|
| 文字 | `addText()` | 自动换行，对齐，多行 |
| 图片 | `addImage()` | 缩放裁剪，圆角，阴影 |
| 头像 | `addAvatar()` | 圆形裁剪，边框 |
| 二维码 | `addQrcode()` | 纯 PHP 生成，中心 Logo，底部文案 |
| 形状 | `addShape()` | 矩形/圆形/圆角，填充/描边 |
| 分割线 | `addLine()` | 颜色，宽度 |
| 水印 | `addWatermark()` | 平铺文字，角度，间距 |
| 表格 | `addTable()` | 表头，斑马纹，列宽 |
| 图表 | `addChart()` | 柱状图 / 折线图 / 饼图 |
| 日历 | `addCalendar()` | 月历，高亮日期，标注 |
| 艺术字体 | `addArtisticText()` | 描边 / 阴影 / 渐变 / 霓虹 |
| Emoji | `addEmoji()` | 彩色 emoji 表情渲染 |
| 字体图标 | `addIcon()` | FontAwesome 图标渲染 |
| 颜文字 | `addEmoticon()` | 日式颜文字 / 自定义表情 |

## 安装

```bash
composer require erikwang2013/poster-php
```

系统要求：PHP >= 8.0，GD 扩展。

可选扩展：
- `ext-imagick`：ImageMagick 图像驱动（性能更好，功能更强）
- `ext-redis`：Redis 验证码存储（分布式部署）

## 使用说明

### 一、验证码

#### 1. 点击验证码 (ClickCaptcha)

用户需要按顺序点击图片上的目标文字（如"树""鸟""花"），验证人类操作。

```php
// 通过辅助函数（框架无关）
$result = captcha_create('click', [
    'difficulty' => 'medium',    // 'easy'(2目标) | 'medium'(3目标) | 'hard'(4目标)
    'background' => null,        // 自定义背景图路径，null=自动生成
]);

// 返回结果
// $result = [
//     'key'   => 'abc123...',           // 验证唯一标识，传给前端
//     'image' => 'data:image/png;base64,...', // 图片 base64
//     'extra' => [
//         'targets' => [
//             ['order' => 1, 'text' => '树', 'x' => 120, 'y' => 80],
//             ['order' => 2, 'text' => '鸟', 'x' => 200, 'y' => 150],
//             ['order' => 3, 'text' => '花', 'x' => 310, 'y' => 95],
//         ],
//     ],
// ];

// 前端根据 targets 渲染提示文字，用户依次点击对应位置
// 前端提交用户点击坐标 [[x1,y1], [x2,y2], [x3,y3]]
$pass = captcha_verify($result['key'], 'click', [[120, 80], [200, 150], [310, 95]]);
// 返回 true / false，容差半径 18px

// 通过 CaptchaManager（完整 API）
use Erikwang2013\Poster\Captcha\CaptchaManager;
use Erikwang2013\Poster\Drivers\DriverFactory;
use Erikwang2013\Poster\Storage\FileStorage;

$manager = new CaptchaManager(DriverFactory::create(), new FileStorage());
$captcha = $manager->create('click')
    ->setDifficulty('hard')
    ->setTargetCount(4)            // 自定义目标数量 1-5
    ->setTargetType('text')        // 'text' 文字 | 'icon' 图标
    ->setBackground('/path/to/bg.jpg');
$result = $captcha->generate();

$pass = $manager->verify($result['key'], [
    'type' => 'click',
    'data' => [[120, 80], [200, 150], [310, 95], [180, 60]],
]);
```

#### 2. 旋转验证码 (RotateCaptcha)

系统随机旋转图片 30°~330°，用户拖动滑块将图片旋转回正。

```php
// 通过辅助函数
$result = captcha_create('rotate');
// $result['extra'] 不含角度（验证答案），前端只展示旋转后的图片

$pass = captcha_verify($result['key'], 'rotate', 185);  // 用户旋转角度，±5° 容差

// 通过 CaptchaManager
$captcha = $manager->create('rotate')
    ->setAngleRange(45, 315)       // 自定义旋转角度范围
    ->generate();
```

#### 3. 滑块验证码 (SliderCaptcha)

系统从背景切出拼图块并偏移，用户拖动拼图到缺口位置。

```php
// 通过辅助函数
$result = captcha_create('slider');
// $result = [
//     'image' => '...',              // 带缺口的背景图
//     'extra' => [
//         'puzzle'   => '...',        // 拼图块图片
//         'puzzle_w' => 50,           // 拼图宽度
//         'puzzle_h' => 50,           // 拼图高度
//     ],
// ];

$pass = captcha_verify($result['key'], 'slider', 173);  // 用户滑动的 x 像素，±4px 容差
```

#### 验证安全特性

| 特性 | 说明 |
|------|------|
| 一次性 | 验证成功/超过最大次数后 key 删除 |
| 防暴力 | 默认最多验证 3 次（可配置） |
| 有效期 | 默认 300 秒（可配置） |
| 随机性 | 每次生成的背景颜色、噪声、目标位置均随机 |

### 二、海报生成

#### 基础用法

```php
use Erikwang2013\Poster\Poster\PosterBuilder;
use Erikwang2013\Poster\Drivers\DriverFactory;

// 通过辅助函数
$builder = poster_create(750, 1334);  // 宽×高

// 或直接实例化
$builder = new PosterBuilder(DriverFactory::create());
$builder->width(750)->height(1334);

// 设置背景
$builder->background('#FFFFFF');                            // 纯色背景
$builder->background('/path/to/bg.jpg');                    // 图片背景（自动缩放）
$builder->backgroundGradient('#FF6B6B', '#FF8E53', 'vertical'); // 渐变背景
                                                            // 方向: vertical | horizontal

// 输出
$builder->save('/output/poster.jpg', 90);  // 保存到文件（路径, 质量 0-100）
$dataUrl = $builder->output('png', 90);    // 获取 base64 data URL
```

#### 文字 `addText()`

```php
$builder->addText('新品首发', [
    'x'        => 80,              // 横坐标
    'y'        => 120,             // 纵坐标（基线位置）
    'size'     => 48,              // 字号
    'color'    => '#333333',       // 颜色
    'font'     => '/path/to/font.ttf', // 字体文件，null=GD 内置
    'align'    => 'center',        // left | center | right
    'maxWidth' => 600,             // 最大宽度（自动换行）
    'lineHeight' => 72,            // 行高
    'angle'    => 0,               // 旋转角度
]);
```

#### 图片 `addImage()`

```php
$builder->addImage('/path/to/product.jpg', [
    'x'      => 75,
    'y'      => 280,
    'width'  => 600,              // 渲染宽度（自动缩放）
    'height' => 600,              // 渲染高度
    'radius' => 12,               // 圆角半径
    'shadow' => [                 // 阴影（可选）
        'color'    => '#00000033',
        'offsetX'  => 4,
        'offsetY'  => 4,
        'blur'     => 10,
    ],
]);
```

#### 头像 `addAvatar()`

```php
$builder->addAvatar('/path/to/avatar.jpg', [
    'x'      => 80,
    'y'      => 60,
    'size'   => 120,              // 头像尺寸（正方形）
    'border' => '#FF6B6B',        // 边框颜色（可选）
]);
```

#### 二维码 `addQrcode()`

```php
$builder->addQrcode('https://example.com/page/123', [
    'x'     => 275,
    'y'     => 1050,
    'size'  => 200,               // 二维码尺寸
    'level' => 'H',               // 容错级别 L | M | Q | H
    'logo'  => '/path/to/logo.png', // 中心 Logo（可选）
    'label' => '扫码查看详情',      // 底部文案（可选）
    'label_size'  => 14,
    'label_color' => '#999999',
]);
```

#### 形状 `addShape()`

```php
// 矩形
$builder->addShape('rect', [
    'x' => 0, 'y' => 0, 'width' => 750, 'height' => 60,
    'color'  => '#FF6B6B',
    'filled' => true,             // true=填充 false=描边
    'radius' => 8,                // 圆角半径
    'opacity' => 0.8,             // 透明度 0-1
]);

// 圆形
$builder->addShape('circle', [
    'x' => 100, 'y' => 100, 'width' => 80, 'height' => 80,
    'color' => '#4ECDC4',
]);
```

#### 分割线 `addLine()`

```php
$builder->addLine([
    'x1' => 75, 'y1' => 800,
    'x2' => 675, 'y2' => 800,
    'color' => '#EEEEEE',
    'width' => 1,
]);
```

#### 水印 `addWatermark()`

```php
$builder->addWatermark('CONFIDENTIAL', [
    'size'    => 24,
    'color'   => '#00000020',     // 半透明
    'font'    => '/font.ttf',
    'angle'   => 30,              // 倾斜角度
    'spacing' => 200,             // 间距
]);
```

#### 表格 `addTable()`

```php
$builder->addTable([
    'x'      => 50,
    'y'      => 800,
    'width'  => 650,
    'columns' => [150, 350, 150], // 列宽
    'header'  => ['序号', '项目', '价格'],
    'rows'    => [
        ['1', '商品A', '¥99'],
        ['2', '商品B', '¥199'],
        ['3', '商品C', '¥299'],
    ],
    'headerBg'     => '#333333',
    'headerColor'  => '#FFFFFF',
    'rowBg'        => ['#FFFFFF', '#F5F5F5'], // 斑马纹
    'rowColor'     => '#333333',
    'fontSize'     => 24,
    'cellPadding'  => 10,
]);
```

#### 图表 `addChart()`

```php
// 柱状图
$builder->addChart('bar', [
    ['label' => '一月', 'value' => 120],
    ['label' => '二月', 'value' => 200],
    ['label' => '三月', 'value' => 150],
    ['label' => '四月', 'value' => 300],
], [
    'x' => 50, 'y' => 100, 'width' => 650, 'height' => 400,
    'colors' => ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4'],
]);

// 折线图
$builder->addChart('line', [
    ['label' => '周一', 'value' => 10],
    ['label' => '周二', 'value' => 35],
    ['label' => '周三', 'value' => 25],
    ['label' => '周四', 'value' => 45],
    ['label' => '周五', 'value' => 30],
], [
    'x' => 50, 'y' => 100, 'width' => 650, 'height' => 400,
    'colors' => ['#FF6B6B'],
]);

// 饼图
$builder->addChart('pie', [
    ['label' => '电商', 'value' => 45],
    ['label' => '社交', 'value' => 25],
    ['label' => '搜索', 'value' => 15],
    ['label' => '其他', 'value' => 15],
], [
    'x' => 75, 'y' => 100, 'width' => 600, 'height' => 600,
    'colors' => ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4'],
]);
```

#### 日历 `addCalendar()`

```php
$builder->addCalendar([
    'x'     => 50,
    'y'     => 200,
    'year'  => 2026,
    'month' => 5,                   // 1-12
    'cellSize'    => 60,            // 格子大小
    'startDay'    => 0,             // 0=周日 1=周一
    'title'       => '2026年5月',    // 标题（默认自动生成）
    'highlights'  => [              // 高亮日期
        '2026-05-01' => ['bg' => '#FF6B6B', 'text' => '劳动节'],
        '2026-05-16' => ['bg' => '#FFEAA7', 'text' => '今天'],
    ],
    'headerBg'    => '#333333',     // 标题栏背景
    'headerColor' => '#FFFFFF',     // 标题栏文字颜色
    'cellBg'      => '#FFFFFF',     // 格子背景
    'cellBorder'  => '#DDDDDD',     // 格子边框
    'todayBg'     => '#FF6B6B',     // 今天背景色
    'highlightBg' => '#FFF3CD',    // 高亮默认背景色
    'textColor'   => '#333333',     // 日期文字颜色
    'dimColor'    => '#CCCCCC',     // 非本月/空白颜色
]);
```

#### 艺术字体 `addArtisticText()`

```php
// 描边效果
$builder->addArtisticText('SALE', 'stroke', [
    'x' => 80, 'y' => 120, 'size' => 72,
    'color'       => '#FF6B6B',    // 填充颜色
    'strokeColor' => '#000000',    // 描边颜色
    'strokeWidth' => 3,            // 描边宽度
]);

// 阴影效果
$builder->addArtisticText('新品', 'shadow', [
    'x' => 80, 'y' => 120, 'size' => 48,
    'color'         => '#333333',
    'shadowColor'   => '#00000033',
    'shadowOffsetX' => 4,
    'shadowOffsetY' => 4,
]);

// 渐变效果
$builder->addArtisticText('VIP', 'gradient', [
    'x' => 80, 'y' => 120, 'size' => 60,
    'color'  => '#FF6B6B',         // 顶部颜色
    'color2' => '#FF8E53',         // 底部颜色
]);

// 霓虹发光效果
$builder->addArtisticText('HOT', 'neon', [
    'x' => 80, 'y' => 120, 'size' => 56,
    'color'     => '#FF1493',
    'glowColor' => '#FF1493',
]);
```

#### Emoji `addEmoji()`

```php
// 直接使用 emoji 字符
$builder->addEmoji('😀', ['x' => 100, 'y' => 100, 'size' => 64]);
$builder->addEmoji('🎉', ['x' => 180, 'y' => 100, 'size' => 64]);

// 使用 unicode 码点
$builder->addEmoji('', [
    'x' => 100, 'y' => 100, 'size' => 64,
    'codepoint' => 'U+1F600',      // 等同于 😀
]);

// 指定 emoji 字体（需系统支持彩色字体）
$builder->addEmoji('😀', [
    'x' => 100, 'y' => 100, 'size' => 64,
    'font' => '/System/Library/Fonts/Apple Color Emoji.ttc',
]);
```

系统会自动检测 macOS / Linux / Windows 上的 emoji 字体路径。

#### 字体图标 `addIcon()`

```php
// 使用内置 FontAwesome 图标名（需提供图标字体文件）
$builder->addIcon('heart', [
    'x' => 20, 'y' => 40, 'size' => 32,
    'color' => '#E74C3C',
    'font'  => '/path/to/fa-solid-900.ttf',  // 必须提供 FontAwesome TTF 字体
]);

$builder->addIcon('star',  ['x' => 60, 'y' => 40, 'color' => '#F39C12', 'font' => '/path/to/fa-solid-900.ttf']);
$builder->addIcon('check', ['x' => 100, 'y' => 40, 'color' => '#27AE60', 'font' => '/path/to/fa-solid-900.ttf']);

// 使用自定义 unicode 码点
$builder->addIcon('', [
    'x' => 20, 'y' => 40, 'size' => 32,
    'codepoint' => '\\u{F3C5}',    // map-marker
    'color' => '#E74C3C',
    'font' => '/path/to/fa-solid-900.ttf',
]);

// 内置图标名列表
// heart, star, user, clock, home, cog, check, times, search,
// envelope, phone, camera, play, pause, shopping-cart, tag,
// map-marker, calendar, comment, share, download, upload,
// lock, globe, link, image, music, video, bell, bookmark,
// thumbs-up, eye, trash, edit, plus, minus, arrow-*,
// location-dot, fire, gift, rocket
```

#### 颜文字 `addEmoticon()`

```php
// 使用内置颜文字
$builder->addEmoticon('happy', ['x' => 20, 'y' => 40, 'size' => 24]);
// 渲染: (｡•̀ᴗ-)✧

$builder->addEmoticon('love',  ['x' => 20, 'y' => 80, 'size' => 24]);
// 渲染: (♡°▽°♡)

$builder->addEmoticon('cry',   ['x' => 20, 'y' => 120, 'size' => 24]);
// 渲染: (╥﹏╥)

// 自定义表情文字
$builder->addEmoticon('', [
    'x' => 20, 'y' => 40, 'size' => 24,
    'text' => '(╯°□°）╯︵ ┻━┻',    // 自定义文字
    'color' => '#333333',
]);

// 内置颜文字表达式
// happy, love, cry, angry, surprised, cool, sleepy,
// wave, think, shrug, tableflip, lenny
```

### 三、模板系统

```php
use Erikwang2013\Poster\Poster\PosterTemplate;

// 定义模板（JSON 可序列化）
$template = PosterTemplate::fromConfig([
    'width'  => 750,
    'height' => 1334,
    'elements' => [
        ['type' => 'shape', 'color' => '#FF6B6B', 'x' => 0, 'y' => 0, 'width' => 750, 'height' => 300],
        ['type' => 'text', 'text' => '{{title}}', 'x' => 80, 'y' => 100, 'size' => 48, 'color' => '#FFFFFF'],
        ['type' => 'text', 'text' => '{{subtitle}}', 'x' => 80, 'y' => 180, 'size' => 28, 'color' => '#FFE0E0'],
        ['type' => 'image', 'src' => '{{cover}}', 'x' => 75, 'y' => 350, 'width' => 600, 'height' => 600, 'radius' => 12],
        ['type' => 'qrcode', 'content' => '{{url}}', 'x' => 275, 'y' => 1050, 'size' => 200, 'label' => '扫码查看详情'],
    ],
]);

// 使用模板 + 变量渲染
$builder->useTemplate($template)->with([
    'title'    => '新品首发',
    'subtitle' => '限时特惠 · 买一送一',
    'cover'    => '/path/to/product.jpg',
    'url'      => 'https://m.example.com/product/123',
])->save('/output/poster.jpg');

// 模板支持的元素类型: text, image, qrcode, avatar, shape, line, watermark, table,
//                      chart, calendar, artistic-text, emoji, icon, emoticon
```

## 框架集成

### Laravel

```php
use Erikwang2013\Poster\Adapters\Laravel\Facades\Captcha;
use Erikwang2013\Poster\Adapters\Laravel\Facades\Poster;

$result = Captcha::create('click')->generate();
Poster::width(750)->height(1334)->background('#FFF')->save('poster.jpg');
```

```bash
php artisan vendor:publish --tag=poster-config
```

### ThinkPHP

`config/web.php`:
```php
'services' => [
    Erikwang2013\Poster\Adapters\ThinkPHP\CaptchaService::class,
    Erikwang2013\Poster\Adapters\ThinkPHP\PosterService::class,
],
```

### Webman

`config/bootstrap.php`:
```php
return [
    Erikwang2013\Poster\Adapters\Webman\CaptchaPlugin::class,
    Erikwang2013\Poster\Adapters\Webman\PosterPlugin::class,
];
```

### Hyperf

通过 ConfigProvider 自动注册。

## 配置

见 `config/poster.php`，支持 `.env` 覆盖（参考 `.env.example`）。

## 目录结构

```
src/
├── Captcha/        # 验证码模块
├── Poster/         # 海报模块
│   └── Elements/   # 14 种渲染元素
├── Drivers/        # 图像驱动（GD / ImageMagick）
├── Qrcode/         # 纯 PHP 二维码生成器
├── Storage/        # 验证数据存储（File / Session / Redis）
└── Adapters/       # 框架适配层
```

## License

MIT License — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
