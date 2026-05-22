# erikwang2013/hashids

PHP 多框架 Hashids 集成：**Laravel**、**Webman**、**ThinkPHP**、**Hyperf**。参考来源、配置与用法对齐 [**vinkla/hashids**](https://github.com/vinkla/laravel-hashids)（多连接、默认连接、`HashidsManager` + 工厂）。

底层依赖：[`hashids/hashids`](https://github.com/vinkla/hashids) v5。

## 项目说明

**Hashids** 是一款短 ID 生成器，可将数字 ID（如数据库主键）编码为短小、唯一且不可猜测的字符串。它不同于 UUID 或雪花 ID——Hashids 更适合用于面向用户的场景（URL、分享码、订单号等），在保持短小可读的同时隐藏原始数字。

本包 `erikwang2013/hashids` 是 Hashids 的 **PHP 多框架集成层**，在设计上参考并对齐了 [vinkla/hashids](https://github.com/vinkla/laravel-hashids) 的 API 风格（多连接、默认连接、Manager + Factory 模式），并扩展支持了国内常用的其他 PHP 框架。

**核心特性：**

- **多框架兼容**：同一套 API 同时支持 Laravel、Webman、ThinkPHP、Hyperf，迁移成本极低。
- **多连接支持**：一个应用可同时配置多套 Salt/Length 组合（如用户 ID 与订单 ID 使用不同盐值），通过 `connection('xxx')` 切换。
- **无框架依赖**：不依赖任何特定框架即可独立使用，直接 `new HashidsManager($config, $factory)` 即可工作。
- **对齐 vinkla/hashids**：Laravel 下 Facade、容器绑定、`config/hashids.php` 格式均与 vinkla/hashids 一致，可平滑替换。
- **框架原生风格**：各框架集成遵循各自的惯用法——Laravel 用 ServiceProvider + Facade，Webman 用 Plugin + Bootstrap，ThinkPHP 用 Service，Hyperf 用 ConfigProvider。

**适用场景：**

| 场景 | 说明 |
|------|------|
| 隐藏数据库自增 ID | 将 `user_id=100` 映射为 `/user/3kTMd`，避免暴露业务规模 |
| 生成短链接/分享码 | 比 UUID 更短，比随机字符串可控 |
| 订单号/流水号 | 可读性好，便于客服沟通与日志排查 |
| 多租户/多模块隔离 | 不同连接使用不同 Salt，确保编码空间相互独立 |

**注意事项：**

- Hashids 是 **编码（encode/decode）而非加密**。Salt 仅增加猜测难度，不可用于安全敏感场景（如 token、密码）。
- 一旦上线后修改 Salt 或 Length，所有已编码的 ID 将变为无效，请提前规划并固定配置。

## 安装

```bash
composer require erikwang2013/hashids
```

## 配置结构（Laravel / Webman / ThinkPHP）

与仓库 `config/hashids.php` 一致：

- `default`：默认连接名（如 `main`）。
- `connections`：连接名 => `salt`、`length`、可选 `alphabet`。

> **Hyperf** 使用单独的配置文件格式（外层键为 `hashids`），见下文 Hyperf 小节。

## 无框架用法

可直接实例化管理器：

```php
use Erikwang2013\Hashids\HashidsFactory;
use Erikwang2013\Hashids\HashidsManager;

$manager = new HashidsManager(
    require __DIR__ . '/config/hashids.php',
    new HashidsFactory()
);

$hash = $manager->encode(1, 2, 3);
$ids = $manager->decode($hash);
```

---

## Laravel

与 [vinkla/hashids](https://github.com/vinkla/laravel-hashids) 类似：容器注册 `HashidsManager`，多连接；默认连接支持 Facade 与方法转发。

Laravel 5.5+ 会读取本包 `composer.json` 的 `extra.laravel`，自动注册 `HashidsServiceProvider` 与 Facade 别名 `Hashids`。

**发布配置（可选）**

```bash
php artisan vendor:publish --tag=hashids-config
```

生成 `config/hashids.php`。若不发布，扩展包会在注册阶段合并内置默认配置。

**Facade（默认连接）**

```php
use Erikwang2013\Hashids\Laravel\Facades\Hashids;

$hash = Hashids::encode(1, 2, 3);
$numbers = Hashids::decode($hash);
```

**指定连接**

```php
use Erikwang2013\Hashids\Laravel\Facades\Hashids;

$hash = Hashids::connection('alternative')->encode(100);
```

**依赖注入 `HashidsManager`**

```php
use Erikwang2013\Hashids\HashidsManager;

public function __construct(private HashidsManager $hashids) {}

$this->hashids->encode(1);
$this->hashids->connection('alternative')->encode(2);
```

**注入底层 `Hashids\Hashids`（默认连接）**

```php
use Hashids\Hashids;

public function __construct(private Hashids $hashids) {}
```

运行 Laravel 集成需要项目已安装 `laravel/framework`（含 `illuminate/support` 等）。本包将 `illuminate/*` 列为 **require-dev**，仅供包自身测试。

---

## Webman

通过 **`Install`** 在安装时拷贝 `config/plugin/erikwang2013/hashids` 与根目录 **`config/hashids.php`**，并由插件 **bootstrap** 向 Webman 容器注册 `HashidsManager`。

项目的 `composer.json` 中若已有 `support\Plugin::install` / `update` / `uninstall` 钩子，安装本包时会自动执行安装脚本（`WEBMAN_PLUGIN = true`）。

**安装后文件**

- `config/plugin/erikwang2013/hashids/app.php`：`enable` 开关。
- `config/plugin/erikwang2013/hashids/bootstrap.php`：注册 `Erikwang2013\Hashids\Webman\Bootstrap`。
- `config/hashids.php`：多连接配置（首次安装或确认覆盖时写入）。

若自动拷贝未执行，可从扩展包内手动复制上述路径的示例配置。

**关闭插件**（`config/plugin/erikwang2013/hashids/app.php`）

```php
<?php
return [
    'enable' => false,
];
```

**容器绑定**

- `Erikwang2013\Hashids\HashidsManager`
- `'hashids'`
- `Hashids\Hashids`（默认连接实例）

**控制器示例**

```php
use support\Request;
use Erikwang2013\Hashids\HashidsManager;
use Hashids\Hashids;

class DemoController
{
    public function index(Request $request, HashidsManager $manager)
    {
        $hash = $manager->encode(1, 2, 3);

        $client = \support\Container::instance()->get(Hashids::class);
        $hash2 = $client->encode(4);

        return json(['hash' => $hash, 'hash2' => $hash2]);
    }
}
```

**指定连接**

```php
$manager->connection('alternative')->encode(99);
```

Composer 卸载包时会触发 `Plugin::uninstall`，移除 `config/plugin/erikwang2013/hashids`；**不会**删除 `config/hashids.php`，是否保留由你决定。

---

## ThinkPHP

通过 **自定义服务类** 注册 `HashidsManager`；配置仍为顶层含 `default` 与 `connections` 的 **`config/hashids.php`**。

**注册服务**

在应用 **`config/service.php`**（具体路径随 TP 版本可能不同）的 `services` 中加入：

```php
<?php

return [
    // ...
    \Erikwang2013\Hashids\ThinkPHP\HashidsService::class,
];
```

若使用应用级 `app/AppService.php`，也可在 `register()` 中写入等价绑定。

**配置文件**

将扩展包内 `config/hashids.php` 复制到应用 `config/hashids.php`（或自行合并同名配置）。

```php
<?php
return [
    'default' => 'main',
    'connections' => [
        'main' => [
            'salt' => env('HASHIDS_SALT', ''),
            'length' => (int) env('HASHIDS_LENGTH', 0),
        ],
    ],
];
```

**使用示例**

```php
use Erikwang2013\Hashids\HashidsManager;
use think\facade\App;

$manager = App::make(HashidsManager::class);
$hash = $manager->encode(10, 20);
```

```php
$manager = app('hashids');
```

```php
use Hashids\Hashids;

$client = app(Hashids::class);
$hash = $client->encode(1);
```

```php
app(HashidsManager::class)->connection('alternative')->encode(100);
```

ThinkPHP 集成继承 `think\Service`，需在 **`topthink/framework`** 环境中使用（本包列为 suggest）。

---

## Hyperf

Composer **`extra.hyperf.config`** 会载入 `ConfigProvider`，向容器注册 `HashidsFactory`、`HashidsManager`、默认连接的 `Hashids\Hashids`。

将扩展包内 **`config/autoload/hashids.php`** 复制到项目 **`config/autoload/hashids.php`**（或使用项目的配置发布命令）。

该文件须满足：**顶层键 `hashids`**，供 `ConfigInterface::get('hashids')` 读取。

```php
<?php

declare(strict_types=1);

return [
    'hashids' => [
        'default' => 'main',
        'connections' => [
            'main' => [
                'salt' => env('HASHIDS_SALT', ''),
                'length' => (int) env('HASHIDS_LENGTH', 0),
            ],
        ],
    ],
];
```

**容器绑定**

| 抽象 | 实现 |
|------|------|
| `Erikwang2013\Hashids\HashidsFactory` | 默认构造 |
| `Erikwang2013\Hashids\HashidsManager` | `HashidsManagerFactory` |
| `Hashids\Hashids` | `HashidsClientFactory`（默认连接） |

**Controller / 构造函数注入**

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use Erikwang2013\Hashids\HashidsManager;
use Hashids\Hashids;

class DemoController
{
    public function index(HashidsManager $manager, Hashids $hashids)
    {
        $h1 = $manager->encode(1, 2, 3);
        $h2 = $hashids->encode(4);
        $alt = $manager->connection('alternative')->encode(99);

        return compact('h1', 'h2', 'alt');
    }
}
```

```php
$manager = \Hyperf\Context\ApplicationContext::getContainer()->get(
    \Erikwang2013\Hashids\HashidsManager::class
);
```

Hyperf 使用 **`config/autoload/hashids.php`** 且配置套在 **`hashids`** 键下；Laravel / Webman / ThinkPHP 使用扁平结构（根级 `default` + `connections`）。请勿混用格式。

---

## License

MIT. See [LICENSE](LICENSE).
