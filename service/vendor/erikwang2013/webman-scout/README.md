<!-- doc-nav: Update Navigate + `<details>` when headings change. Left TOC is `position:fixed`; the `doc-body` div uses `padding-left` for title + intro + main sections so nothing sits under the panel. github.com may strip styles. -->

<div style="position:fixed;left:12px;top:88px;width:min(288px,calc(100vw - 36px));max-height:min(520px,calc(100vh - 112px));overflow-y:auto;overflow-x:hidden;z-index:9998;padding:12px 14px;background:#f6f8fa;border:1px solid #d0d7de;border-radius:10px;font-size:12.5px;line-height:1.45;box-shadow:0 2px 14px rgba(31,35,40,.12)">

**Navigate (top):** [Top](#webman-scout) · [Features](#features) · [Framework support](#framework-support) · [Requirements](#requirements) · [Installation](#installation) · [Framework-specific setup](#framework-specific-setup) · [Configuration](#configuration) · [Model setup](#model-setup) · [Basic usage](#basic-usage) · [Advanced builder](#advanced-builder-opensearch--elasticsearch-oriented) · [Artisan / Webman commands](#artisan--webman-commands) · [Queues](#queues) · [Builder reference](#builder-reference-extensions) · [References](#references) · [License](#license)

<details open>
<summary><strong>Left navigation (outline)</strong></summary>

- [Features](#features)
- [Framework support](#framework-support)
- [Requirements](#requirements)
- [Installation](#installation)
  - [Webman](#webman)
  - [Laravel / Hyperf / ThinkPHP (non-plugin layout)](#laravel--hyperf--thinkphp-non-plugin-layout)
- [Framework-specific setup](#framework-specific-setup)
  - [Webman (1.x / 2.x)](#webman-1x--2x)
  - [Laravel (7.x – 11.x)](#laravel-7x--11x)
  - [Hyperf (2.x – 3.x)](#hyperf-2x--3x)
  - [ThinkPHP (6.x / 8.x)](#thinkphp-6x--8x)
  - [Quick checklist](#quick-checklist)
- [Configuration](#configuration)
  - [OpenSearch example](#opensearch-example)
- [Model setup](#model-setup)
- [Basic usage](#basic-usage)
- [Advanced builder (OpenSearch / Elasticsearch-oriented)](#advanced-builder-opensearch--elasticsearch-oriented)
- [Artisan / Webman commands](#artisan--webman-commands)
- [Queues](#queues)
- [Builder reference (extensions)](#builder-reference-extensions)
- [References](#references)
- [License](#license)

</details>

</div>

<a href="#webman-scout" title="Back to top" aria-label="Back to top" style="position:fixed;bottom:1.25rem;right:1.25rem;z-index:9999;padding:0.45rem 0.85rem;background:#0969da;color:#fff!important;border-radius:999px;font-weight:700;line-height:1;text-decoration:none;box-shadow:0 2px 12px rgba(31,35,40,.25)">↑</a>

<div style="padding-left:min(312px,max(8px,calc(100vw - 120px)));box-sizing:border-box;max-width:100%">

# webman-scout

Driver-based full-text search for **Eloquent** models, inspired by [Laravel Scout](https://laravel.com/docs/scout) and [shopwwi/webman-scout](https://github.com/shopwwi/webman-scout). This fork adds **time ranges, aggregations, OpenSearch**, vector / geo helpers, and clearer multi-framework configuration.

**Languages:** this file is English (default). [简体中文](docs/zh-CN/README.md)

## Features

- Scout-like API for easy migration from Laravel Scout / shopwwi webman-scout
- Engines: **OpenSearch**, Elasticsearch, Meilisearch, Typesense, Algolia, XunSearch, Database, Collection, Null
- OpenSearch-first advanced queries: aggregations, facets, KNN, geo distance
- Optional queue-driven indexing (Webman Redis Queue when available)
- Index settings sync, soft deletes, chunked import


## Framework support

Runtime integration targets applications that expose Laravel’s **`config()` helper** and an **Illuminate container** (`app()`), with **Eloquent** (`Illuminate\Database\Eloquent\Model`) models.

| Framework | Versions | Notes |
|-----------|----------|--------|
| **Webman** | 1.x / 2.x | Default install: plugin config under `config/plugin/erikwang2013/webman-scout/`. |
| **Laravel** | 7.x – 11.x | Copy the plugin `app.php` array into `config/scout.php` (or `config/erikwang2013.webman-scout.php`) and set `SCOUT_CONFIG_KEY` (see below). Requires **PHP 8.0+** (Laravel 7 on PHP 8 is supported in recent 7.x releases). |
| **Hyperf** | 2.x – 3.x | Use Hyperf’s config + DI; `Hyperf\Database\Model` is Eloquent-compatible. Map Scout options into config and set `SCOUT_CONFIG_KEY` if not using the Webman plugin path. |
| **ThinkPHP** | 6.x / 8.x | Use when the app loads **Illuminate** `config` / `app()` (e.g. hybrid setups or `illuminate/database` Eloquent models). Native `think\Model` is **not** wired to the `Searchable` trait; call engine APIs manually or use Eloquent models for indexed entities. |

Composer **requires** `illuminate/*` **^7.0 – ^11.0** and `symfony/console` **^5.4 – ^7.0** so dependency resolution matches your framework stack.


## Requirements

- PHP **^8.0**
- Eloquent models for the `Searchable` trait
- `illuminate/bus`, `contracts`, `database`, `http`, `pagination`, `queue`, `support` (versions aligned with your Laravel / Hyperf / ThinkPHP stack)


## Installation

```bash
composer require erikwang2013/webman-scout
```

The Composer **autoload `files`** entry loads `helpers.php`, which defines `app()`, `event()`, and `scout_config()` when needed and registers `EngineManager`.

### Webman

After install, run the plugin installer (copies config and queue consumers):

- Config: `config/plugin/erikwang2013/webman-scout/`
- Consumers: `app/queue/redis/search/`

### Laravel / Hyperf / ThinkPHP (non-plugin layout)

1. Copy the contents of `src/config/plugin/erikwang2013/webman-scout/app.php` into your application config, e.g. `config/scout.php`, returning the **same associative array** (keys: `driver`, `prefix`, `opensearch`, `meilisearch`, …).
2. Set environment variable **`SCOUT_CONFIG_KEY=scout`** (no trailing dot) so lookups use `config('scout.driver')`, etc., instead of the Webman plugin path.
3. Ensure your bootstrap registers **Illuminate’s `config` repository** and **container** so `config()` and `app()` resolve `EngineManager` and engine clients.

If `SCOUT_CONFIG_KEY` is unset, the package prefers `config('plugin.erikwang2013.webman-scout.app')` when that array exists; otherwise it tries `scout` or `erikwang2013.webman-scout`.


## Framework-specific setup

The following sections assume `composer require erikwang2013/webman-scout` is already done.

### Webman (1.x / 2.x)

1. **Enable the plugin** in your Webman project (per [Webman plugins](https://www.workerman.net/doc/webman/plugin.html)) so that `config/plugin/erikwang2013/webman-scout/` is published. If your stack runs the package `Install` step, it copies plugin config and queue consumers; otherwise copy from `vendor/erikwang2013/webman-scout/src/config/plugin/erikwang2013/webman-scout/` into your project.
2. **Config** lives at `config/plugin/erikwang2013/webman-scout/app.php`. You normally **do not** set `SCOUT_CONFIG_KEY` so `scout_config()` resolves this path automatically.
3. **Console**: commands are registered via `config/plugin/erikwang2013/webman-scout/command.php` (e.g. `php webman scout:import "App\\Model\\Product"` — adjust namespace to your app).
4. **Models** usually extend `support\Model` (Eloquent-based) and `use Searchable`.
5. **Queues** (optional): install/configure [webman/redis-queue](https://www.workerman.net/doc/webman/components/redis-queue.html), set `'queue' => true` in Scout config, and run consumers under `app/queue/redis/search/` (`scout_make`, `scout_remove`). If Redis Queue is missing or `queue` is false, indexing runs **synchronously** in the request/process.

### Laravel (7.x – 11.x)

1. **Config file**: add `config/scout.php` that `return`s the same structure as this package’s `src/config/plugin/erikwang2013/webman-scout/app.php` (keys: `driver`, `prefix`, `opensearch`, `meilisearch`, `queue`, …).  
   - Avoid loading **both** `laravel/scout` and this package under the same `config/scout.php` unless you know how to separate them; this package is a standalone Scout-style implementation.
2. **Environment**: in `.env` set `SCOUT_CONFIG_KEY=scout` so `scout_config('driver')` reads `config('scout.driver')`.
3. **Container**: `helpers.php` registers `EngineManager` (and Meilisearch client when installed) on the active `app()` container. If you bootstrap before Composer’s `files` autoload, register the same bindings in `AppServiceProvider::register()`:

   ```php
   $this->app->singleton(\Erikwang2013\WebmanScout\EngineManager::class, function ($app) {
       return new \Erikwang2013\WebmanScout\EngineManager($app);
   });
   ```

4. **Artisan commands**: commands are Symfony `Command` classes with names like `scout:import`. Register them with the framework:
   - **Laravel 10 and below** — in `app/Console/Kernel.php`:

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

   - **Laravel 11+** — in `bootstrap/app.php` use `->withCommands([...])` with the same class list (see [Laravel 11 structure](https://laravel.com/docs/11.x/structure)).

5. **Models** extend `Illuminate\Database\Eloquent\Model` (or your base model) and `use Searchable`.
6. **Queues**: async indexing in this package is wired to **Webman\RedisQueue** when that class exists. On stock Laravel, keep **`'queue' => false`** in Scout config so changes are applied **synchronously**, or implement your own pipeline (e.g. dispatch a Laravel job from model observers) using `syncMakeSearchable` / engine `update()` as a reference.

### Hyperf (2.x – 3.x)

1. **Config**: place the Scout array under a Hyperf config file, e.g. `config/autoload/scout.php`, returning the same keys as the package `app.php`. Set `SCOUT_CONFIG_KEY=scout` in the environment Hyperf reads (so `config('scout')` is the root array).
2. **`config()` / `app()`**: Hyperf provides `config()`; ensure the **Illuminate** `Container` is the one returned by `app()` if you rely on package `helpers.php`, or bind `EngineManager` in a Hyperf `ConfigProvider` / dependency injection config pointing at your container bridge.
3. **Models**: `Hyperf\Database\Model` is Eloquent-compatible — use `Searchable` the same way as on Laravel when the database component is configured.
4. **Console**: register the same command classes as Laravel with [Hyperf’s command system](https://hyperf.wiki/en/command.html) (or invoke Symfony `Application` with these commands in a custom entry script).
5. **Queues**: same as Laravel — without `Webman\RedisQueue`, prefer **`queue` => false** or custom async jobs.

### ThinkPHP (6.x / 8.x)

1. **Scope**: the `Searchable` trait expects **Eloquent** (`Illuminate\Database\Eloquent\Model`) observers and collections. It does **not** attach to `think\Model` out of the box.
2. **When it works**: projects that already use **`illuminate/database`** (or another stack) with real Eloquent models, **or** a bridge that exposes Laravel-style `config()` and `app()` with the Illuminate container, can follow the **Laravel** steps: Scout config file + `SCOUT_CONFIG_KEY` + `EngineManager` binding.
3. **Pure ThinkPHP models**: index data by calling **`app(EngineManager::class)->engine()`** (or the concrete engine class) `update` / `delete` / `search` with arrays you build yourself, or maintain a thin Eloquent model mapped to the same table for search-only usage.
4. **Config**: ThinkPHP’s `config('scout.driver')` works if you define `config/scout.php` (or the version your major version uses) with the same array shape as this package’s `app.php`.

### Quick checklist

| Step | Webman | Laravel | Hyperf | ThinkPHP (Eloquent/hybrid) |
|------|--------|---------|--------|-----------------------------|
| Scout config file | `config/plugin/.../app.php` | `config/scout.php` | `config/autoload/scout.php` | `config/scout.php` (or equivalent) |
| `SCOUT_CONFIG_KEY` | Usually omit | `scout` | `scout` | `scout` (if not using plugin path) |
| Console | `php webman scout:*` | `php artisan scout:*` (after registration) | Hyperf command registration | Per your console setup |
| Async indexing | Redis Queue + consumers | `queue` false or custom jobs | `queue` false or custom jobs | `queue` false or custom jobs |


## Configuration

All Scout options are read via **`scout_config('key')`**, which respects the resolved config root above.

| Key | Purpose |
|-----|---------|
| `driver` | Default engine: `opensearch`, `elasticsearch`, `meilisearch`, `typesense`, `algolia`, `database`, `collection`, `null`, … |
| `prefix` | Index name prefix |
| `queue` | Enable async indexing (Webman Redis Queue when installed) |
| `chunk.searchable` / `chunk.unsearchable` | Chunk sizes for bulk import/remove |
| `soft_delete` | Keep soft-deleted rows in the index |

### OpenSearch example

```php
'opensearch' => [
    'host' => getenv('OPENSEARCH_HTTP_HOST') ?: 'https://127.0.0.1:6205',
    'username' => getenv('OPENSEARCH_USERNAME') ?: 'admin',
    'password' => getenv('OPENSEARCH_PASSWORD') ?: 'admin',
    'prefix' => getenv('OPENSEARCH_INDEX_PREFIX') ?: '',
    'ssl_verification' => (bool) (getenv('OPENSEARCH_SSL_VERIFICATION') ?: false),
    'indices' => [
        'products' => [
            'settings' => [ /* ... */ ],
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


## Model setup

```php
use Erikwang2013\WebmanScout\Searchable;
use support\Model; // Webman; use your Eloquent base otherwise

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


## Basic usage

```php
// Search
$products = Product::search('phone')->get();

$products = Product::search('phone', function ($builder) {
    $builder->where('status', 1);
})->get();

$paginator = Product::search('phone')->paginate(15);

Product::search('keyword')
    ->where('status', 1)
    ->whereIn('category_id', [1, 2, 3])
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

// Indexing
$product->searchableSync();
$product->searchable();
$product->unsearchable();
Product::makeAllSearchable();
Product::removeAllFromSearch();

Product::withoutSyncingToSearch(function () {
    Product::query()->where('id', 1)->update(['title' => 'New title']);
});
```


## Advanced builder (OpenSearch / Elasticsearch-oriented)

```php
Product::search('')
    ->whereRange('created_at', ['gte' => 1609459200, 'lte' => 1640995200], true)
    ->whereRange('price', ['gte' => 100, 'lt' => 500])
    ->get();

Product::search('')
    ->whereGeoDistance('location', 31.23, 121.47, 10.0)
    ->get();

Product::search('')
    ->fulltextSearch('keyword', ['title', 'content'], ['operator' => 'and'])
    ->get();

Product::search('')
    ->orderByVectorSimilarity([0.1, -0.2 /* ... */], 'vector')
    ->get();

$builder = Product::search('keyword')
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

$builder = Product::search('keyword');
$builder->whereRange('created_at', $range)->get();
$builder->clearAdvancedConditions();
```


## Artisan / Webman commands

On **Webman**, use `php webman …`. On **Laravel**, register the command classes (see **Laravel** under [Framework-specific setup](#framework-specific-setup)) and run `php artisan scout:import`, etc.

| Command | Description |
|---------|-------------|
| `php webman scout:import [Model]` | Full import; `--chunk`, `--fresh` |
| `php webman scout:flush [Model]` | Clear model data from the index |
| `php webman scout:delete-index [Model]` | Drop index for the model |
| `php webman scout:index` | List / create indexes (engine-dependent) |
| `php webman scout:queue-import` | Queue-based import |
| `php webman scout:sync-index-settings` | Sync index settings |
| `php webman scout:delete-all-indexes` | Delete all managed indexes (dangerous) |

Use `--help` on each command for options.


## Queues

With `queue` enabled, `searchable()` / `unsearchable()` dispatch to **Webman Redis Queue** when `Webman\RedisQueue\Redis` is available. Ensure consumers under `app/queue/redis/search` are running (e.g. `scout_make`, `scout_remove`).


## Builder reference (extensions)

| Method | Description |
|--------|-------------|
| `whereRange($field, array $range, bool $inclusive = true)` | Range filter |
| `whereGeoDistance($field, $lat, $lng, $radius)` | Geo distance |
| `fulltextSearch($query, array $fields = [], array $options = [])` | Full-text |
| `orderByVectorSimilarity(array $vector, ?string $vectorField = null)` | Vector sort |
| `aggregate(...)` / `facet(...)` | Aggregations / facets |
| `addResultProcessor(callable $processor)` | Post-process hits |
| `getAggregations()` / `getFacets()` | Read facet/agg results |
| `clearAdvancedConditions()` | Reset advanced state |

Engines such as OpenSearch expose `updateIndexMappings(string $index, array $mappings)`.


## References

- [Laravel Scout](https://laravel.com/docs/scout)
- [shopwwi/webman-scout](https://github.com/shopwwi/webman-scout)


## License

MIT

---

© erik <erik@erik.xyz> · https://erik.xyz

</div>
