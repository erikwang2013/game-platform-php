<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout;

use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Tappable;
use Erikwang2013\WebmanScout\Contracts\PaginatesEloquentModels;
use Erikwang2013\WebmanScout\Contracts\PaginatesEloquentModelsUsingDatabase;
use Illuminate\Database\Eloquent\Collection;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
class Builder
{
    use Conditionable, Macroable, Tappable;

    /**
     * The model instance.
     *
     * @var TModel
     */
    public $model;

    /**
     * The query expression.
     *
     * @var string
     */
    public $query;

    /**
     * Optional callback before search execution.
     *
     * @var \Closure|null
     */
    public $callback;

    /**
     * Optional callback before model query execution.
     *
     * @var \Closure|null
     */
    public $queryCallback;

    /**
     * Optional callback after raw search.
     *
     * @var \Closure|null
     */
    public $afterRawSearchCallback;

    /**
     * The custom index specified for the search.
     *
     * @var string|null
     */
    public $index;

    /**
     * The "where" constraints added to the query.
     *
     * @var array
     */
    public $wheres = [];

    /**
     * The "where in" constraints added to the query.
     *
     * @var array
     */
    public $whereIns = [];

    /**
     * The "where not in" constraints added to the query.
     *
     * @var array
     */
    public $whereNotIns = [];

    /**
     * The "limit" that should be applied to the search.
     *
     * @var int|null
     */
    public $limit;
    public $offset;
    /**
     * The "order" that should be applied to the search.
     *
     * @var array
     */
    public $orders = [];

    /**
     * Extra options that should be applied to the search.
     *
     * @var array
     */
    public $options = [];

        /**
     * 向量搜索参数
     */
    protected array $vectorSearch = [];

    /**
     * 高级查询条件
     */
    protected array $advancedWheres = [];

    /**
     * 排序配置
     */
    protected array $sorts = [];

    /**
     * 结果处理器
     */
    protected array $resultProcessors = [];

    /**
     * 聚合配置
     */
    protected array $aggregations = [];

    /**
     * 分面配置
     */
    protected array $facets = [];

    /**
     * Create a new search builder instance.
     *
     * @param  TModel  $model
     * @param  string  $query
     * @param  \Closure|null  $callback
     * @param  bool  $softDelete
     * @return void
     */
    public function __construct($model, $query, $callback = null, $softDelete = false)
    {
        $this->model = $model;
        $this->query = $query;
        $this->callback = $callback;

        if ($softDelete) {
            $this->wheres['__soft_deleted'] = 0;
        }
    }

    /**
     * Specify a custom index to perform this search on.
     *
     * @param  string  $index
     * @return $this
     */
    public function within($index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Add a constraint to the search query.
     *
     * @param  string  $field
     * @param  mixed  $value
     * @return $this
     */
    public function where($field, $value)
    {
        $this->wheres[$field] = $value;

        return $this;
    }

    /**
     * Add a "where in" constraint to the search query.
     *
     * @param  string  $field
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $values
     * @return $this
     */
    public function whereIn($field, $values)
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        $this->whereIns[$field] = $values;

        return $this;
    }

    /**
     * Add a "where not in" constraint to the search query.
     *
     * @param  string  $field
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $values
     * @return $this
     */
    public function whereNotIn($field, $values)
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        $this->whereNotIns[$field] = $values;

        return $this;
    }

    /**
     * Include soft deleted records in the results.
     *
     * @return $this
     */
    public function withTrashed()
    {
        unset($this->wheres['__soft_deleted']);

        return $this;
    }

    /**
     * Include only soft deleted records in the results.
     *
     * @return $this
     */
    public function onlyTrashed()
    {
        return tap($this->withTrashed(), function () {
            $this->wheres['__soft_deleted'] = 1;
        });
    }

    /**
     * Set the "limit" for the search query.
     *
     * @param  int  $limit
     * @return $this
     */
    public function take($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Add a descending "order by" clause to the search query.
     *
     * @param  string  $column
     * @return $this
     */
    public function orderByDesc($column)
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function latest($column = null)
    {
        if (is_null($column)) {
            $column = $this->model->getCreatedAtColumn() ?? 'created_at';
        }

        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function oldest($column = null)
    {
        if (is_null($column)) {
            $column = $this->model->getCreatedAtColumn() ?? 'created_at';
        }

        return $this->orderBy($column, 'asc');
    }

    /**
     * Set extra options for the search query.
     *
     * @param  array  $options
     * @return $this
     */
    public function options(array $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Set the callback that should have an opportunity to modify the database query.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function query($callback)
    {
        $this->queryCallback = $callback;

        return $this;
    }


    /**
     * Set the callback that should have an opportunity to inspect and modify the raw result returned by the search engine.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function withRawResults($callback)
    {
        $this->afterRawSearchCallback = $callback;

        return $this;
    }

    /**
     * Get the keys of search results.
     *
     * @return \Illuminate\Support\Collection
     */
    public function keys()
    {
        return $this->engine()->keys($this);
    }

    /**
     * Get the first result from the search.
     *
     * @return TModel
     */
    public function first()
    {
        return $this->get()->first();
    }


    /**
     * Get the results of the search as a "lazy collection" instance.
     *
     * @return \Illuminate\Support\LazyCollection<int, TModel>
     */
    public function cursor()
    {
        return $this->engine()->cursor($this);
    }

    /**
     * Paginate the given query into a simple paginator.
     *
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public function simplePaginate($perPage = null, $pageName = 'page', $page = null)
    {
        $engine = $this->engine();

        if ($engine instanceof PaginatesEloquentModels) {
            return $engine->simplePaginate($this, $perPage, $page)->appends('query', $this->query);
        } elseif ($engine instanceof PaginatesEloquentModelsUsingDatabase) {
            return $engine->simplePaginateUsingDatabase($this, $perPage, $pageName, $page)->appends('query', $this->query);
        }

        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $results = $this->model->newCollection($engine->map(
            $this,
            $this->applyAfterRawSearchCallback($rawResults = $engine->paginate($this, $perPage, $page)),
            $this->model
        )->all());

        $paginator = Container::getInstance()->makeWith(Paginator::class, [
            'items' => $results,
            'perPage' => $perPage,
            'currentPage' => $page,
            'options' => [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        ])->hasMorePagesWhen(($perPage * $page) < $engine->getTotalCount($rawResults));

        return $paginator->appends('query', $this->query);
    }

    /**
     * Paginate the given query into a simple paginator with raw data.
     *
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public function simplePaginateRaw($perPage = null, $pageName = 'page', $page = null)
    {
        $engine = $this->engine();

        if ($engine instanceof PaginatesEloquentModels) {
            return $engine->simplePaginate($this, $perPage, $page)->appends('query', $this->query);
        } elseif ($engine instanceof PaginatesEloquentModelsUsingDatabase) {
            return $engine->simplePaginateUsingDatabase($this, $perPage, $pageName, $page)->appends('query', $this->query);
        }

        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $results = $this->applyAfterRawSearchCallback($engine->paginate($this, $perPage, $page));

        $paginator = Container::getInstance()->makeWith(Paginator::class, [
            'items' => $results,
            'perPage' => $perPage,
            'currentPage' => $page,
            'options' => [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        ])->hasMorePagesWhen(($perPage * $page) < $engine->getTotalCount($results));

        return $paginator->appends('query', $this->query);
    }

    /**
     * Paginate the given query into a paginator.
     *
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage = null, $pageName = 'page', $page = null)
    {
        $engine = $this->engine();

        if ($engine instanceof PaginatesEloquentModels) {
            return $engine->paginate($this, $perPage, $page)->appends('query', $this->query);
        } elseif ($engine instanceof PaginatesEloquentModelsUsingDatabase) {
            return $engine->paginateUsingDatabase($this, $perPage, $pageName, $page)->appends('query', $this->query);
        }

        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $results = $this->model->newCollection($engine->map(
            $this,
            $this->applyAfterRawSearchCallback($rawResults = $engine->paginate($this, $perPage, $page)),
            $this->model
        )->all());

        return Container::getInstance()->makeWith(LengthAwarePaginator::class, [
            'items' => $results,
            'total' => $this->getTotalCount($rawResults),
            'perPage' => $perPage,
            'currentPage' => $page,
            'options' => [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        ])->appends('query', $this->query);
    }

    /**
     * Paginate the given query into a paginator with raw data.
     *
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginateRaw($perPage = null, $pageName = 'page', $page = null)
    {
        $engine = $this->engine();

        if ($engine instanceof PaginatesEloquentModels) {
            return $engine->paginate($this, $perPage, $page)->appends('query', $this->query);
        } elseif ($engine instanceof PaginatesEloquentModelsUsingDatabase) {
            return $engine->paginateUsingDatabase($this, $perPage, $pageName, $page)->appends('query', $this->query);
        }

        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $results = $this->applyAfterRawSearchCallback($engine->paginate($this, $perPage, $page));

        return Container::getInstance()->makeWith(LengthAwarePaginator::class, [
            'items' => $results,
            'total' => $this->getTotalCount($results),
            'perPage' => $perPage,
            'currentPage' => $page,
            'options' => [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        ])->appends('query', $this->query);
    }

    /**
     * Get the total number of results from the Scout engine, or fallback to query builder.
     *
     * @param  mixed  $results
     * @return int
     */
    protected function getTotalCount($results)
    {
        $engine = $this->engine();

        $totalCount = $engine->getTotalCount($results);

        if (is_null($this->queryCallback)) {
            return $totalCount;
        }

        $ids = $engine->mapIdsFrom($results, $this->model->getScoutKeyName())->all();

        if (count($ids) < $totalCount) {
            $ids = $engine->keys(tap(clone $this, function ($builder) use ($totalCount) {
                $builder->take(
                    is_null($this->limit) ? $totalCount : min($this->limit, $totalCount)
                );
            }))->all();
        }

        return $this->model->queryScoutModelsByIds(
            $this, $ids
        )->toBase()->getCountForPagination();
    }

    /**
     * Invoke the "after raw search" callback.
     *
     * @param  mixed  $results
     * @return mixed
     */
    public function applyAfterRawSearchCallback($results)
    {
        if ($this->afterRawSearchCallback) {
            $results = call_user_func($this->afterRawSearchCallback, $results) ?: $results;
        }

        return $results;
    }

    /**
     * Get the engine that should handle the query.
     *
     * @return mixed
     */
    protected function engine()
    {
        return $this->model->searchableUsing();
    }

    /**
     * Get the connection type for the underlying model.
     */
    public function modelConnectionType(): string
    {
        return $this->model->getConnection()->getDriverName();
    }


    /**
     * 启用向量相似度搜索
     */
    public function vectorSearch($vector, ?string $vectorField = null, array $options = []): self
    {
        if (is_array($vector)) {
            $this->vectorSearch = [
                'vector' => $vector,
                'field' => $vectorField,
                'options' => array_merge([
                    'metric' => 'cosine',
                    'top_k' => 10,
                    'threshold' => 0.7,
                ], $options),
            ];
        } else {
            $this->vectorSearch = [
                'field' => $vector,
                'options' => $options,
            ];
        }

        return $this;
    }


    /**
     * 添加嵌套/复杂查询条件
     */
    public function whereAdvanced(
        string $field,
        string $operator,
        $value,
        string $boolean = 'and',
        bool $nested = false
    ): self {
        $this->advancedWheres[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
            'nested' => $nested,
        ];

        return $this;
    }

    /**
     * 范围查询
     */
    public function whereRange(string $field, array $range, bool $inclusive = true): self
    {
        return $this->whereAdvanced($field, 'range', [
            'range' => $range,
            'inclusive' => $inclusive,
        ]);
    }

    /**
     * 地理位置查询
     */
    public function whereGeoDistance(string $field, float $lat, float $lng, float $radius): self
    {
        return $this->whereAdvanced($field, 'geo_distance', [
            'lat' => $lat,
            'lng' => $lng,
            'radius' => $radius,
        ]);
    }

    /**
     * 全文搜索增强
     */
    public function fulltextSearch(string $query, array $fields = [], array $options = []): self
    {
        $searchFields = $fields ?: (method_exists($this->model, 'searchableFields') ? $this->model->searchableFields() : []);
        $this->advancedWheres[] = [
            'type' => 'fulltext',
            'query' => $query,
            'fields' => $searchFields,
            'options' => array_merge([
                'operator' => 'and',
                'fuzziness' => 'auto',
                'boost' => 1.0,
            ], $options),
        ];

        return $this;
    }

    /**
     * 添加排序（同时写入 orders 与 sorts，兼容基础引擎与高级引擎）
     */
    public function orderBy(string $field, string $direction = 'asc', array $options = []): self
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $this->orders[] = [
            'column' => $field,
            'direction' => $direction,
        ];
        $this->sorts[] = [
            'field' => $field,
            'direction' => $direction,
            'options' => $options,
        ];

        return $this;
    }

    /**
     * 按向量相似度排序
     */
    public function orderByVectorSimilarity(array $vector, ?string $vectorField = null): self
    {
        $this->sorts[] = [
            'type' => 'vector_similarity',
            'vector' => $vector,
            'field' => $vectorField,
        ];

        return $this;
    }

    /**
     * 按地理位置距离排序
     */
    public function orderByGeoDistance(string $field, float $lat, float $lng, string $direction = 'asc'): self
    {
        $this->sorts[] = [
            'type' => 'geo_distance',
            'field' => $field,
            'location' => ['lat' => $lat, 'lng' => $lng],
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * 添加结果处理器
     */
    public function addResultProcessor(callable $processor): self
    {
        $this->resultProcessors[] = $processor;

        return $this;
    }

    /**
     * 添加聚合查询
     */
    public function aggregate(string $name, string $type, string $field, array $options = []): self
    {
        $this->aggregations[$name] = [
            'type' => $type,
            'field' => $field,
            'options' => $options,
        ];

        return $this;
    }

    /**
     * 添加分面搜索
     */
    public function facet(string $field, array $options = []): self
    {
        $this->facets[$field] = $options;

        return $this;
    }

    /**
     * 执行搜索并获取结果
     */
    public function get(): Collection
    {
        $engine = $this->engine();

        // 检查引擎是否支持高级搜索
        if (method_exists($engine, 'advancedSearch')) {
            $results = $engine->advancedSearch($this);
            
            // 应用结果处理器
            foreach ($this->resultProcessors as $processor) {
                $results = $processor($results);
            }
            
            return $this->mapResults($results);
        }

        // 回退到基础搜索
        return $this->engine()->get($this);
    }

    /**
     * 执行搜索并获取原始结果
     */
    public function raw(): array
    {
        $engine = $this->engine();

        if (method_exists($engine, 'advancedSearch')) {
            return $engine->advancedSearch($this);
        }

        return $this->engine()->search($this);
    }

    /**
     * 获取聚合结果
     */
    public function getAggregations(): array
    {
        $engine = $this->engine();

        if (method_exists($engine, 'getAggregations')) {
            return $engine->getAggregations($this);
        }

        return [];
    }

    /**
     * 获取分面结果
     */
    public function getFacets(): array
    {
        $engine = $this->engine();

        if (method_exists($engine, 'getFacets')) {
            return $engine->getFacets($this);
        }

        return [];
    }

    /**
     * 映射搜索结果
     */
    protected function mapResults(array $results): Collection
    {
        if (empty($results['hits'])) {
            return $this->model->newCollection();
        }

        $objectIds = collect($results['hits'])->pluck('_id')->all();
        
        if (empty($objectIds)) {
            return $this->model->newCollection();
        }

        $objectIdPositions = array_flip($objectIds);

        $models = $this->model->getScoutModelsByIds($this, $objectIds)
            ->filter(fn($model) => in_array($model->getScoutKey(), $objectIds))
            ->sortBy(fn($model) => $objectIdPositions[$model->getScoutKey()])
            ->values();

        // 添加元数据
        foreach ($models as $index => $model) {
            if (isset($results['hits'][$index])) {
                $hit = $results['hits'][$index];
                $model->_score = $hit['_score'] ?? null;
                $model->_highlight = $hit['_highlight'] ?? null;
                $model->_vector_score = $hit['_vector_score'] ?? null;
            }
        }

        return $models;
    }

    /**
     * Getter 方法
     */

    public function getVectorSearch(): array
    {
        return $this->vectorSearch;
    }

    public function getAdvancedWheres(): array
    {
        return $this->advancedWheres;
    }

    public function getSorts(): array
    {
        return $this->sorts;
    }

    public function getResultProcessors(): array
    {
        return $this->resultProcessors;
    }

    public function getAggregationConfig(): array
    {
        return $this->aggregations;
    }

    public function getFacetConfig(): array
    {
        return $this->facets;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Setter 方法
     */

    public function setOption(string $key, $value): self
    {
        $this->options[$key] = $value;
        return $this;
    }

    public function setOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * 清空高级查询条件
     */
    public function clearAdvancedConditions(): self
    {
        $this->vectorSearch = [];
        $this->advancedWheres = [];
        $this->sorts = [];
        $this->aggregations = [];
        $this->facets = [];
        $this->resultProcessors = [];

        return $this;
    }

    /**
     * 条件构造辅助方法
     */

/*     public function when($condition, callable $callback, ?callable $default = null): self
    {
        if ($condition) {
            return $callback($this, $condition) ?? $this;
        } elseif ($default) {
            return $default($this, $condition) ?? $this;
        }

        return $this;
    }

    public function unless($condition, callable $callback, ?callable $default = null): self
    {
        return $this->when(!$condition, $callback, $default);
    } */

    /**
     * 调试方法
     */
    public function debug(): array
    {
        return [
            'model' => get_class($this->model),
            'query' => $this->query,
            'index' => $this->index,
            'wheres' => $this->wheres,
            'whereIns' => $this->whereIns,
            'whereNotIns' => $this->whereNotIns,
            'advancedWheres' => $this->advancedWheres,
            'vectorSearch' => $this->vectorSearch,
            'sorts' => $this->sorts,
            'aggregations' => $this->aggregations,
            'facets' => $this->facets,
            'options' => $this->options,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];
    }
}
