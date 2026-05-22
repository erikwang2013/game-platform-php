# season

**文档语言 / Language:** [English](README.md) · [简体中文](README.zh-CN.md)

根据 **ISO 3166-1 alpha-2** 国家简码获取当前季节的 PHP 扩展，可作为普通 Composer 库使用，并可选集成 **Laravel 7–11**、**ThinkPHP 6 / 8**、**Hyperf 2 / 3** 与 **webman** 插件。除英文季节键名（`spring` 等）与中文名称外，还提供 **国旗 Emoji**、按 **BCP 47** 语言区域返回的 **多语言季节名称**，以及半球判断、指定日期计算等。

- 北半球：春 3–5，夏 6–8，秋 9–11，冬 12/1/2  
- 南半球：秋 3–5，冬 6–8，春 9–11，夏 12/1/2  

## 安装

```bash
composer require erikwang2013/season
```

## 在 webman 中安装插件

安装完依赖后，在 webman 项目根目录执行（需已安装 webman/console）：

```bash
php webman install erikwang2013/season
```

或手动将 `vendor/erikwang2013/season/src/config/plugin/erikwang2013/season` 复制到项目的 `config/plugin/erikwang2013/season`。

## Laravel 7–11

`composer require` 后，若未在 `composer.json` 中关闭该包的 **package discovery**，会自动注册 `Erikwang2013\Season\Laravel\CountrySeasonServiceProvider`，并向容器注册 **`SeasonService`**（默认国家码来自合并后的配置）。

可选：将默认配置发布到应用，便于修改：

```bash
php artisan vendor:publish --tag=country-season-config
```

发布后得到 `config/country_season.php`，其中 `default_country_code` 对应环境变量 **`COUNTRY_SEASON_DEFAULT`**（默认 `CN`）。在控制器或服务中注入 `Erikwang2013\Season\SeasonService` 即可。

## ThinkPHP 6 / 8

安装后由 Composer 的 **think 扩展机制** 自动发现 `Erikwang2013\Season\ThinkPHP\Service`，注册 **`SeasonService`** 并合并包内 `config/country_season.php` 到配置项 **`country_season`**。在需要处通过容器解析 `Erikwang2013\Season\SeasonService` 或依赖注入使用。

## Hyperf 2 / 3

安装后由 **Hyperf ConfigProvider** 机制合并依赖：向容器绑定 **`SeasonService`**，并从配置 **`country_season.default_country_code`** 读取默认国家码。

可选：发布配置文件到项目：

```bash
php bin/hyperf.php vendor:publish erikwang2013/season
```

生成 `config/autoload/country_season.php` 后按需修改；未发布时仍使用内置默认值 **`CN`**（可通过环境变量 **`COUNTRY_SEASON_DEFAULT`** 等在自定义配置中覆盖）。

## 使用方式

### 1. 静态方法（任意 PHP 项目）

```php
use Erikwang2013\Season\CountrySeason;

// 英文季节键名：spring | summer | autumn | winter
$season = CountrySeason::getSeason('CN');        // 中国，例如 winter
$season = CountrySeason::getSeason('AU');        // 澳大利亚，南半球，例如 summer

// 中文：春 | 夏 | 秋 | 冬
$zh = CountrySeason::getSeasonNameZh('CN');

// 指定日期
$date = new \DateTimeImmutable('2026-06-15');
$season = CountrySeason::getSeason('US', $date);  // summer

// 半球
$hemisphere = CountrySeason::getHemisphere('BR'); // south
$valid = CountrySeason::isValidCode('XX');        // true/false
```

#### 国旗 Emoji（Unicode 区域指示符）

根据国家简码生成对应旗帜，用于界面展示等场景：

```php
$flag = CountrySeason::getCountryFlagEmoji('CN');  // 🇨🇳
$flag = CountrySeason::getCountryFlagEmoji('us');  // 大小写均可 → 🇺🇸
```

无效或非两字母字母的国家代码会抛出 `InvalidArgumentException`（与 `getSeason` 等一致）。

#### 多语言季节名称（BCP 47）

`getSeasonNameLocalized` 用 **国家代码** 计算当前季节（含南北半球），用 **语言区域** 决定显示文案：

```php
// 第二个参数为 locale：zh_CN、en_US、ja、de、fr_FR 等，- 与 _ 均可
$name = CountrySeason::getSeasonNameLocalized('DE', 'de_DE');   // 如 Frühling
$name = CountrySeason::getSeasonNameLocalized('US', 'en_US');   // 秋季为 Fall（美式）
$name = CountrySeason::getSeasonNameLocalized('GB', 'en_GB');   // 秋季为 Autumn（英式）

// 可与指定日期一起使用
$date = new \DateTimeImmutable('2026-03-01');
CountrySeason::getSeasonNameLocalized('AU', 'en', $date);
```

列出库内已定义的 locale 标签（小写 + 下划线）：

```php
$locales = CountrySeason::getSupportedLocales();
```

**locale 解析规则**：先匹配完整标签（如 `zh_CN`），再尝试仅语言码（如 `zh`），都不命中则回退到 **`en`**。

内置语言覆盖常见语种（英/中/日/韩、德法西意葡、俄荷波瑞乌、阿印泰越印尼土、捷丹芬挪、罗/希腊/希伯来/匈等）；完整列表以 `getSupportedLocales()` 为准。未单独列出的方言可先试语言码（如 `es`），仍不满足可在外层自行映射或扩展。

### 2. 全局助手函数（autoload 已加载）

```php
country_season('JP');       // 日本当前季节，如 spring
country_season_zh('AU');    // 澳大利亚当前季节中文，如 秋

country_season_flag('FR');  // 🇫🇷
country_season_locale('IT', 'it_IT');  // 意大利语季节名，如 Primavera
country_season_locale('KR', 'ko', $date);  // 可传日期
```

### 3. 在 Laravel / ThinkPHP / Hyperf 中使用 SeasonService

安装对应集成后，容器中的 **`SeasonService`** 已与框架配置绑定（键名 **`country_season.default_country_code`**，与包内 `config/country_season.php` 一致）。`getSeasonForDefault()` 使用上述默认国家码。

### 4. 在 webman 中使用 SeasonService（安装插件后）

webman 需自行注册一次（例如在 `config/bootstrap.php`），再按类名从容器取出：

```php
use Erikwang2013\Season\SeasonService;
use support\Container;

Container::singleton(SeasonService::class, function () {
    $code = config('plugin.erikwang2013.season.app.default_country_code', 'CN');

    return new SeasonService(\is_string($code) ? $code : 'CN');
});
```

```php
use support\Container;
use Erikwang2013\Season\SeasonService;

/** @var SeasonService $seasonService */
$seasonService = Container::get(SeasonService::class);

$seasonService->getSeason('CN');
$seasonService->getSeasonNameZh('AU');
$seasonService->getCountryFlagEmoji('JP');
$seasonService->getSeasonNameLocalized('FR', 'fr_FR');
$seasonService->getSeasonForDefault();  // 使用配置中的 default_country_code
$seasonService->getHemisphere('NZ');
$seasonService->isValidCode('AU');          // true
$seasonService->getSupportedLocales();       // ['ar', 'cs', 'da', ...]
```

### 5. 配置（webman）

配置文件：`config/plugin/erikwang2013/season/app.php`

```php
return [
    'enable' => true,
    'default_country_code' => 'CN',  // 或 env('COUNTRY_SEASON_DEFAULT', 'CN')
];
```

## 国家代码说明

- 使用 **ISO 3166-1 alpha-2** 两字母代码（如 CN、US、JP、AU）。
- 南半球国家（如澳大利亚 AU、阿根廷 AR、新西兰 NZ、巴西 BR 等）已内置映射，其余按北半球处理。

## API 速览

| 方法 / 函数 | 说明 |
|-------------|------|
| `CountrySeason::getSeason` / `country_season` | 季节英文键名 |
| `CountrySeason::getSeasonNameZh` / `country_season_zh` | 中文季节名 |
| `CountrySeason::getCountryFlagEmoji` / `country_season_flag` | 国旗 Emoji |
| `CountrySeason::getSeasonNameLocalized` / `country_season_locale` | 按 locale 的季节名 |
| `CountrySeason::getSupportedLocales` | 内置 locale 列表 |
| `CountrySeason::getHemisphere` | north / south |
| `SeasonService::getSeasonForDefault` | 使用配置的默认国家 |
| `SeasonService::isValidCode` | 校验代码格式 |
| `SeasonService::getSupportedLocales` | 内置 locale 列表 |

### 异常与校验

- 国家代码须为 **两字母 A–Z**（大小写不敏感）；否则 `getSeason`、`getCountryFlagEmoji` 等会抛出 **`InvalidArgumentException`**。
- `isValidCode()` 仅校验格式是否为两字母字母，**不校验**是否为真实 ISO 国家码。

### 扩展 CountrySeason

`resolveSeasonNamesForLocale()` 和 `seasonToNameZh()` 已改为 `protected static` — 可通过继承扩展 locale 数据，无需 fork 源码。

### `setDefaultCountryCode()` 即时校验

`SeasonService::setDefaultCountryCode()` 在设置无效代码时立刻抛出 `InvalidArgumentException`，不再延迟到后续调用才报错。

## 测试

```bash
composer test
```

44 个测试用例，覆盖季节映射、半球判断、国旗 Emoji、locale 回退、`SeasonService` 默认值以及错误处理。

## 要求

- PHP >= 8.1
- 扩展 **mbstring**（旗帜 Emoji 依赖 `mb_chr`）
- 可选：`workerman/webman-framework`（webman 插件）、`illuminate/support`（Laravel）、`topthink/framework`（ThinkPHP）、`hyperf/framework`（Hyperf）

## License

MIT
