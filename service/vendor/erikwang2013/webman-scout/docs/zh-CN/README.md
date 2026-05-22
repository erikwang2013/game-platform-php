<!-- doc-nav：同步更新顶部导航与 `<details>`。左侧目录为 `position:fixed`；`doc-body` 用 `padding-left` 包住标题、简介与正文，避免被目录遮挡。github.com 可能去掉样式。 -->

<div style="position:fixed;left:12px;top:88px;width:min(288px,calc(100vw - 36px));max-height:min(520px,calc(100vh - 112px));overflow-y:auto;overflow-x:hidden;z-index:9998;padding:12px 14px;background:#f6f8fa;border:1px solid #d0d7de;border-radius:10px;font-size:12.5px;line-height:1.45;box-shadow:0 2px 14px rgba(31,35,40,.12)">

**顶部导航：** [顶部](#webman-scout) · [特性](#特性) · [框架与版本](#框架与版本) · [环境要求](#环境要求) · [安装](#安装) · [各框架使用说明](#各框架使用说明) · [配置项（节选）](#配置项节选) · [模型配置](#模型配置) · [基础使用](#基础使用) · [高级构建](#高级构建面向-opensearch--elasticsearch) · [Artisan / Webman 命令](#artisan--webman-命令) · [队列](#队列) · [构建器参考](#构建器参考扩展) · [参考链接](#参考链接) · [许可证](#许可证)

<details open>
<summary><strong>左侧目录（大纲）</strong></summary>

- [特性](#特性)
- [框架与版本](#框架与版本)
- [环境要求](#环境要求)
- [安装](#安装)
  - [多框架配置根键](#多框架配置根键)
- [各框架使用说明](#各框架使用说明)
  - [Webman（1.x / 2.x）](#webman1x--2x)
  - [Laravel（7.x – 11.x）](#laravel7x--11x)
  - [Hyperf（2.x – 3.x）](#hyperf2x--3x)
  - [ThinkPHP（6.x / 8.x）](#thinkphp6x--8x)
  - [对照简表](#对照简表)
- [配置项（节选）](#配置项节选)
  - [OpenSearch 配置示例](#opensearch-配置示例)
- [模型配置](#模型配置)
- [基础使用](#基础使用)
  - [搜索与索引](#搜索与索引)
  - [Laravel 7 兼容性说明](#laravel-7-兼容性说明)
- [高级构建（面向 OpenSearch / Elasticsearch）](#高级构建面向-opensearch--elasticsearch)
- [Artisan / Webman 命令](#artisan--webman-命令)
- [队列](#队列)
- [构建器参考（扩展）](#构建器参考扩展)
- [参考链接](#参考链接)
- [许可证](#许可证)

</details>

</div>

<a href="#webman-scout" title="返回顶部" aria-label="返回顶部" style="position:fixed;bottom:1.25rem;right:1.25rem;z-index:9999;padding:0.45rem 0.85rem;background:#0969da;color:#fff!important;border-radius:999px;font-weight:700;line-height:1;text-decoration:none;box-shadow:0 2px 12px rgba(31,35,40,.25)">↑</a>

<div style="padding-left:min(312px,max(8px,calc(100vw - 120px)));box-sizing:border-box;max-width:100%">

# webman-scout

基于 [Laravel Scout](https://laravel.com/docs/scout) 并参考 [shopwwi/webman-scout](https://github.com/shopwwi/webman-scout) 的全文搜索扩展；在兼容 Webman 的同时，支持在 **Laravel 7–11、Hyperf 2/3、ThinkPHP 6/8（限定场景）** 中与 **Eloquent** 一起使用。

**默认文档语言为英文：** [README.md（English）](../../README.md)

因 shopwwi/webman-scout 更新停滞，为满足 **时间范围查询、数据聚合** 以及 **OpenSearch** 等需求，本包在相近 API 下增加了高级查询、聚合、分面、向量检索、地理距离等能力。

## 特性

- 与 Laravel Scout / shopwwi webman-scout 用法接近，便于迁移
- 引擎：**OpenSearch**、Elasticsearch、Meilisearch、Typesense、Algolia、XunSearch、Database、Collection 等
- 默认面向 **OpenSearch**，支持复杂查询、聚合、KNN、地理检索
- 可选队列同步索引（Webman Redis Queue）
- 索引设置同步、软删除、分块导入


## 框架与版本

需要应用提供 **`config()`** 与 **`app()`（Illuminate 容器）**，模型为 **`Illuminate\Database\Eloquent\Model`**（或兼容实现，如 Hyperf Database Model）。

| 框架 | 版本 | 说明 |
|------|------|------|
| **Webman** | 常用 1.x / 2.x | 默认使用插件配置 `config/plugin/erikwang2013/webman-scout/`。 |
| **Laravel** | 7.x – 11.x | 将包内 `app.php` 配置数组放到 `config/scout.php`（或自建文件名），并设置环境变量 **`SCOUT_CONFIG_KEY=scout`**。需 **PHP ≥ 8.0**（Laravel 7 请使用支持 PHP 8 的 7.x 小版本）。 |
| **Hyperf** | 2.x – 3.x | 使用 Hyperf 配置与 DI；在非标插件路径下通过 **`SCOUT_CONFIG_KEY`** 指向你的配置根键。 |
| **ThinkPHP** | 6.x / 8.x | 适用于已集成 **Illuminate 配置/容器** 或使用 **`illuminate/database` Eloquent** 的项目。原生 **`think\Model`** 未接入本包 `Searchable` trait，需自行调引擎 API 或改用 Eloquent 模型承载索引数据。 |

Composer 依赖 **`illuminate/*` ^7.0–^11.0**、**`symfony/console` ^5.4–^7.0**，与上述框架的传递依赖对齐。


## 环境要求

- PHP ^8.0
- Eloquent 模型（使用 `Searchable` trait 时）


## 安装

```bash
composer require erikwang2013/webman-scout
```

安装后（Webman）执行插件安装，会复制配置与队列消费者：

- 配置目录：`config/plugin/erikwang2013/webman-scout/`
- 队列消费者：`app/queue/redis/search/`

### 多框架配置根键

包内所有业务配置通过 **`scout_config('键名')`** 读取。解析规则：

1. 若设置 **`SCOUT_CONFIG_KEY`**（例如 `scout`），则使用 `config('scout.xxx')`。
2. 否则若存在 `config('plugin.erikwang2013.webman-scout.app')`，使用 Webman 插件路径。
3. 否则尝试 `config('scout')` 或 `config('erikwang2013.webman-scout')`（需为含 `driver` / `prefix` 等字段的数组）。

Laravel / Hyperf / ThinkPHP 非插件布局：请将 `src/config/plugin/erikwang2013/webman-scout/app.php` 的**返回数组**合并到你的配置文件，并设置 **`SCOUT_CONFIG_KEY=scout`**（与 `config/scout.php` 对应）。


## 各框架使用说明

以下默认已执行 `composer require erikwang2013/webman-scout`。

### Webman（1.x / 2.x）

1. **启用插件**：按 [Webman 插件文档](https://www.workerman.net/doc/webman/plugin.html) 安装/启用本插件，使 `config/plugin/erikwang2013/webman-scout/` 出现在项目中。若安装流程会执行包内 `Install`，会自动复制配置与队列消费者；否则可从 `vendor/erikwang2013/webman-scout/src/config/plugin/erikwang2013/webman-scout/` 手工拷贝。
2. **配置**：主配置为 `config/plugin/erikwang2013/webman-scout/app.php`。一般**不必**设置 `SCOUT_CONFIG_KEY`，`scout_config()` 会自动走插件路径。
3. **命令行**：通过 `config/plugin/erikwang2013/webman-scout/command.php` 注册，示例：`php webman scout:import "App\\Model\\Product"`（按实际命名空间修改）。
4. **模型**：通常继承 `support\Model`，并 `use Searchable`。
5. **队列（可选）**：安装 [webman/redis-queue](https://www.workerman.net/doc/webman/components/redis-queue.html)，在 Scout 配置中设 `'queue' => true`，并运行 `app/queue/redis/search/` 下消费者（如 `scout_make`、`scout_remove`）。未安装 Redis 队列或 `queue` 为 false 时，索引在**当前进程内同步**写入。

### Laravel（7.x – 11.x）

1. **配置文件**：新增 `config/scout.php`，`return` 与包内 `src/config/plugin/erikwang2013/webman-scout/app.php` **相同结构的数组**（`driver`、`prefix`、`opensearch` 等）。若同时使用官方 **`laravel/scout`**，注意避免配置键与启动流程冲突；本包可独立使用，不必再装官方 Scout。
2. **环境变量**：在 `.env` 中设置 **`SCOUT_CONFIG_KEY=scout`**，使 `scout_config('driver')` 对应 `config('scout.driver')`。
3. **容器**：`helpers.php` 会在 `app()` 上注册 `EngineManager`（及已安装时的 Meilisearch 客户端）。若你的应用在 Composer `files` 加载 `helpers` 之前尚未就绪，可在 `AppServiceProvider::register()` 中手动绑定：

   ```php
   $this->app->singleton(\Erikwang2013\WebmanScout\EngineManager::class, function ($app) {
       return new \Erikwang2013\WebmanScout\EngineManager($app);
   });
   ```

4. **Artisan 命令**：包内命令为 Symfony `Command`，名称如 `scout:import`。需向框架注册：
   - **Laravel 10 及以下**：在 `app/Console/Kernel.php` 的 `$commands` 中加入：

     ```php
     protected $commands = [
         \Erikwang2013\WebmanScout\Command\ImportCommand::class,
         \Erikwang2013\WebmanScout\Command\FlushCommand::class,
         \Erikwang2013\WebmanScout\Command\IndexCommand::class,
         \Erikwang2013\WebmanScout\Command\DeleteIndexCommand::class,
         \Erikwang2013\WebmanScout\Command\DeleteAllIndexesCommand::class,
         \Erikwang2013\WebmanScout\Command\QueueImportCommand::class,
         \Erikwang2013\WebmanScout\Command\SyncIndexSettingsCommand::class,
     ];
     ```

   - **Laravel 11+**：在 `bootstrap/app.php` 使用 `->withCommands([...])` 传入上述类名列表（参见 [Laravel 结构说明](https://laravel.com/docs/11.x/structure)）。

5. **模型**：继承 `Illuminate\Database\Eloquent\Model`（或项目基类）并 `use Searchable`。
6. **队列**：包内异步索引与 **`Webman\RedisQueue`** 集成；标准 Laravel 环境若无该类，请将配置中 **`queue` 设为 `false`** 使用同步索引，或自行在观察者/任务中调用引擎 `update()` 实现异步（可参考 `syncMakeSearchable` 逻辑）。

### Hyperf（2.x – 3.x）

1. **配置**：将 Scout 数组放到 Hyperf 配置中，例如 `config/autoload/scout.php`，内容与包内 `app.php` 一致。在 Hyperf 读取的环境变量中设置 **`SCOUT_CONFIG_KEY=scout`**，保证 `config('scout')` 为根配置。
2. **`config()` / `app()`**：需保证 `scout_config()` 最终能读到上述配置；若 `helpers.php` 中的 `app()` 与 Hyperf 容器不一致，请在 `ConfigProvider` 或 DI 配置中为 **`EngineManager`** 做显式绑定。
3. **模型**：`Hyperf\Database\Model` 与 Eloquent 兼容，在数据库组件配置正确的前提下可同样使用 `Searchable`。
4. **命令行**：将上述与 Laravel 相同的 Command 类注册到 [Hyperf 命令](https://hyperf.wiki/zh-cn/command.html)，或通过自定义入口挂载 Symfony Console。
5. **队列**：与 Laravel 相同，无 Webman Redis 队列时建议 **`queue` => false** 或自建异步任务。

### ThinkPHP（6.x / 8.x）

1. **适用范围**：`Searchable` 依赖 Eloquent 的观察者、集合等，**不能**直接挂在原生 `think\Model` 上。
2. **可用场景**：已使用 **`illuminate/database`** 与 Eloquent 模型，或中间层提供了 Laravel 风格 **`config()` + `app()` + Illuminate 容器** 时，可按 **Laravel** 小节配置 `config/scout.php`、`SCOUT_CONFIG_KEY` 与 `EngineManager`。
3. **纯 ThinkPHP 模型**：可解析 `app(EngineManager::class)->engine()` 后对文档数组调用 `update` / `delete` / `search`；或单独建一张表/模型用 Eloquent 仅做搜索同步。
4. **配置位置**：按 ThinkPHP 版本将 `scout` 数组写入 `config/scout.php` 等，并保证 `config('scout.xxx')` 可读。

### 对照简表

| 步骤 | Webman | Laravel | Hyperf | ThinkPHP（Eloquent/混合） |
|------|--------|---------|--------|---------------------------|
| 配置文件 | `config/plugin/.../app.php` | `config/scout.php` | `config/autoload/scout.php` | `config/scout.php`（或版本对应路径） |
| `SCOUT_CONFIG_KEY` | 一般不设 | `scout` | `scout` | 非插件路径时设 `scout` |
| 命令 | `php webman scout:*` | `php artisan scout:*`（需注册） | 按 Hyperf 注册 | 按项目控制台 |
| 异步索引 | Redis Queue + 消费者 | `queue` 为 false 或自建 Job | 同左 | 同左 |


## 配置项（节选）

| 配置项 | 说明 |
|--------|------|
| `driver` | 默认引擎：`opensearch`、`elasticsearch`、`meilisearch` 等 |
| `prefix` | 索引前缀 |
| `queue` | 是否使用队列同步 |
| `chunk.searchable` / `chunk.unsearchable` | 批量分块大小 |
| `soft_delete` | 是否在索引中保留软删记录 |

### OpenSearch 配置示例

```php
'opensearch' => [
    'host' => getenv('OPENSEARCH_HTTP_HOST', 'https://127.0.0.1:6205'),
    'username' => getenv('OPENSEARCH_USERNAME', 'admin'),
    'password' => getenv('OPENSEARCH_PASSWORD', 'admin'),
    'prefix' => getenv('OPENSEARCH_INDEX_PREFIX'),
    'ssl_verification' => (bool) getenv('OPENSEARCH_SSL_VERIFICATION', false),
    'indices' => [
        'products' => [
            'settings' => [ /* index settings */ ],
            'mappings' => [
                'properties' => [
                    'vector' => ['type' => 'knn_vector', 'dimension' => 1536],
                    'location' => ['type' => 'geo_point'],
                ],
            ],
        ],
    ],
],
```


## 模型配置

```php
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class Product extends Model
{
    use Searchable;

    public function searchableAs(): string
    {
        return 'products';
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'price' => $this->price,
            'created_at' => $this->created_at?->timestamp,
            'location' => ['lat' => $this->lat, 'lon' => $this->lng],
            'vector' => $this->embedding ?? [],
        ];
    }

    public function searchableFields(): array
    {
        return ['title', 'content'];
    }
}
```


## 基础使用

### 搜索与索引

```php
// 关键词搜索
$products = Product::search('手机')->get();

// 带回调，约束查询构造器
$products = Product::search('手机', function ($builder) {
    $builder->where('status', 1);
})->get();

// 分页
$paginator = Product::search('手机')->paginate(15);

Product::search('关键词')
    ->where('status', 1)
    ->whereIn('category_id', [1, 2, 3])
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

// 单条写入/移除索引（是否走队列取决于配置）
$product->searchableSync();
$product->searchable();
$product->unsearchable();

// 全量导入 / 清空该模型索引
Product::makeAllSearchable();
Product::removeAllFromSearch();

// 临时关闭同步
Product::withoutSyncingToSearch(function () {
    Product::query()->where('id', 1)->update(['title' => '新标题']);
});
```

### Laravel 7 兼容性说明

- 查询主键列表时，若当前 Eloquent 版本无 `whereIntegerInRaw`，会自动回退为 `whereIn`。
- `HasManyThrough::chunkById` 在较早版本不存在时，不会注册对应的 `searchable` / `unsearchable` 宏（关系批量导入需升级框架或使用普通查询构造器分块）。

## 高级构建（面向 OpenSearch / Elasticsearch）

以下链式方法主要面向 OpenSearch / Elasticsearch 等引擎（以实际引擎支持为准）。

```php
Product::search('')
    ->whereRange('created_at', ['gte' => 1609459200, 'lte' => 1640995200], true)
    ->whereRange('price', ['gte' => 100, 'lt' => 500])
    ->get();

Product::search('')
    ->whereGeoDistance('location', 31.23, 121.47, 10.0)
    ->get();

Product::search('')
    ->fulltextSearch('关键词', ['title', 'content'], ['operator' => 'and'])
    ->get();

Product::search('')
    ->orderByVectorSimilarity([0.1, -0.2 /* ... */], 'vector')
    ->get();

$builder = Product::search('关键词')
    ->aggregate('price_ranges', 'range', 'price', ['ranges' => [
        ['from' => 0, 'to' => 100],
        ['from' => 100, 'to' => 500],
    ]])
    ->facet('category_id', ['size' => 10]);

$results = $builder->get();
$aggregations = $builder->getAggregations();
$facets = $builder->getFacets();

$engine = app(\Erikwang2013\WebmanScout\EngineManager::class)->engine();
$engine->updateIndexMappings('products', [
    'properties' => [
        'new_field' => ['type' => 'keyword'],
    ],
]);

$builder = Product::search('关键词');
$builder->whereRange('created_at', $range)->get();
$builder->clearAdvancedConditions();
```

## Artisan / Webman commands

**Webman** 下使用 `php webman …`。**Laravel** 需在项目中注册包内 Symfony 命令类后，再使用 `php artisan scout:import` 等（参见上文「各框架使用说明」中的 Laravel 小节）。

| 命令 | 说明 |
|------|------|
| `php webman scout:import [Model]` | 全量导入；支持 `--chunk`、`--fresh` |
| `php webman scout:flush [Model]` | 从索引中清空该模型数据 |
| `php webman scout:delete-index [Model]` | 删除该模型对应索引 |
| `php webman scout:index` | 列出/创建索引（依引擎实现） |
| `php webman scout:queue-import` | 通过队列导入 |
| `php webman scout:sync-index-settings` | 同步索引设置 |
| `php webman scout:delete-all-indexes` | 删除所有托管索引（慎用） |

具体参数以各命令 `--help` 为准。

## 队列

在配置中开启 `queue` 后，模型的 `searchable()` / `unsearchable()` 在存在 `Webman\RedisQueue\Redis` 时会进入 Redis 队列；请保证 `app/queue/redis/search` 下消费者已运行（如 `scout_make`、`scout_remove`）。未使用 Webman Redis 队列时，请将配置中 **`queue` 设为 `false`** 或自行实现异步任务。

## 构建器参考（扩展）

| 方法 | 说明 |
|------|------|
| `whereRange($field, array $range, bool $inclusive = true)` | 范围过滤 |
| `whereGeoDistance($field, $lat, $lng, $radius)` | 地理距离 |
| `fulltextSearch($query, array $fields = [], array $options = [])` | 全文检索 |
| `orderByVectorSimilarity(array $vector, ?string $vectorField = null)` | 向量排序 |
| `aggregate(...)` / `facet(...)` | 聚合 / 分面 |
| `addResultProcessor(callable $processor)` | 结果后处理 |
| `getAggregations()` / `getFacets()` | 读取聚合与分面结果 |
| `clearAdvancedConditions()` | 清空高级条件 |

OpenSearch 等引擎通常还提供 `updateIndexMappings(string $index, array $mappings)` 用于更新映射。

## 参考链接

- [Laravel Scout 文档](https://laravel.com/docs/scout)
- [shopwwi/webman-scout](https://github.com/shopwwi/webman-scout)


## 许可证

MIT

---

© erik <erik@erik.xyz> · https://erik.xyz

</div>
