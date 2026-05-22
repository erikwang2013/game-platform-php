<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Engines;

use Erikwang2013\WebmanScout\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;
use OpenSearch\Client;
use support\Log;

class OpenSearchEngine extends Engine
{
    /**
     * OpenSearch 客户端
     */
    protected Client $opensearch;

    /**
     * 是否启用软删除
     */
    protected bool $softDelete;

    /**
     * 批量操作大小
     */
    protected int $bulkSize = 1000;

    /**
     * 创建新的引擎实例
     */
    public function __construct(Client $opensearch, bool $softDelete = false)
    {
        $this->opensearch = $opensearch;
        $this->softDelete = $softDelete;
    }

    /**
     * 更新索引
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $this->bulkOperation($models, 'update');
    }

    /**
     * 删除文档
     */
    public function delete($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $this->bulkOperation($models, 'delete');
    }

    /**
     * 批量操作
     */
    protected function bulkOperation($models, string $operation): void
    {
        $params = ['body' => []];
        $count = 0;

        foreach ($models as $model) {
            $index = $model->searchableAs();
            $id = $model->getScoutKey();

            switch ($operation) {
                case 'update':
                    // 处理软删除
                    if ($this->usesSoftDelete($model) && $this->softDelete) {
                        $model->pushSoftDeleteMetadata();
                    }

                    $data = $model->toSearchableArray();
                    if (empty($data)) {
                        continue 2;
                    }

                    $data = $this->sanitizeDocumentForBulk($data);
                    if ($data === [] || $data === null) {
                        continue 2;
                    }

                    $params['body'][] = [
                        'index' => [
                            '_index' => $index,
                            '_id' => $id,
                        ],
                    ];
                    $params['body'][] = $data;
                    break;

                case 'delete':
                    $params['body'][] = [
                        'delete' => [
                            '_index' => $index,
                            '_id' => $id,
                        ],
                    ];
                    break;
            }

            $count++;

            // 批量提交
            if ($count % $this->bulkSize === 0) {
                $this->executeBulk($params);
                $params['body'] = [];
            }
        }

        // 执行剩余的
        if (!empty($params['body'])) {
            $this->executeBulk($params);
        }
    }

    /**
     * 清洗可索引文档，避免 json_encode 报 JSON_ERROR_CTRL_CHAR / UTF-8 问题导致 bulk 失败。
     */
    protected function sanitizeDocumentForBulk(mixed $data): mixed
    {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $key = is_string($k) ? $this->sanitizeStringForOpenSearch($k) : $k;
                $out[$key] = $this->sanitizeDocumentForBulk($v);
            }

            return $out;
        }
        if (is_string($data)) {
            return $this->sanitizeStringForOpenSearch($data);
        }
        if (is_float($data) && (is_nan($data) || is_infinite($data))) {
            return null;
        }

        return $data;
    }

    /**
     * 去掉 C0 控制字符（保留 \t \n \r），并尽量修复非法 UTF-8。
     */
    protected function sanitizeStringForOpenSearch(string $s): string
    {
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    }

    /**
     * 执行批量操作
     */
    protected function executeBulk(array $params): void
    {
        try {
            $response = $this->opensearch->bulk($params);
            
            if ($response['errors'] ?? false) {
                $this->handleBulkErrors($response);
            }
        } catch (\Exception $e) {
            Log::error('OpenSearch bulk operation failed', [
                'error' => $e->getMessage(),
                'params_count' => count($params['body'] ?? []),
            ]);
            
            if (config('app.debug')) {
                throw $e;
            }
        }
    }

    /**
     * 处理批量操作错误
     */
    protected function handleBulkErrors(array $response): void
    {
        $errors = [];

        foreach ($response['items'] ?? [] as $item) {
            $operation = array_keys($item)[0];
            $result = $item[$operation];
            
            if (isset($result['error'])) {
                $errors[] = [
                    'operation' => $operation,
                    'id' => $result['_id'] ?? 'unknown',
                    'error' => is_array($result['error']) 
                        ? json_encode($result['error'])
                        : $result['error'],
                    'index' => $result['_index'] ?? 'unknown',
                ];
            }
        }

        if (!empty($errors)) {
            Log::error('OpenSearch bulk operation partial errors', [
                'total_operations' => count($response['items'] ?? []),
                'error_count' => count($errors),
                'errors' => $errors,
            ]);
        }
    }

    /**
     * 执行搜索
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder);
    }

    /**
     * 分页搜索
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $result = $this->performSearch($builder, [
            'from' => ($page - 1) * $perPage,
            'size' => $perPage,
        ]);
        
        return $result;
    }

    /**
     * 执行搜索
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $params = [
            'index' => $builder->index ?? $builder->model->searchableAs(),
            'body' => [
                'query' => $this->buildQuery($builder),
            ],
        ];

        // 添加排序
        if ($sort = $this->buildSort($builder)) {
            $params['body']['sort'] = $sort;
        }

        // 添加分页
        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        } elseif ($builder->limit) {
            $params['body']['size'] = $builder->limit;
        }

        // 添加过滤条件
        $filters = $this->buildFilters($builder);
        if (!empty($filters)) {
            if (!isset($params['body']['query']['bool'])) {
                $params['body']['query'] = ['bool' => ['must' => $params['body']['query']]];
            }
            $params['body']['query']['bool']['filter'] = $filters;
        }

        // 自定义回调
        if ($builder->callback) {
            return call_user_func($builder->callback, $this->opensearch, $builder, $params);
        }

        return $this->opensearch->search($params);
    }

    /**
     * 构建查询
     */
    protected function buildQuery(Builder $builder): array
    {
        if (empty($builder->query)) {
            return ['match_all' => new \stdClass()];
        }

        $fields = $this->getSearchableFields($builder);

        if (count($fields) === 1) {
            return [
                'match' => [
                    $fields[0] => [
                        'query' => $builder->query,
                        'operator' => 'and',
                    ],
                ],
            ];
        }

        return [
            'multi_match' => [
                'query' => $builder->query,
                'fields' => $fields,
                'type' => 'best_fields',
                'operator' => 'and',
                'fuzziness' => 'AUTO',
            ],
        ];
    }

    /**
     * 获取可搜索字段
     */
    protected function getSearchableFields(Builder $builder): array
    {
        // 使用模型定义的搜索字段
        if (method_exists($builder->model, 'searchableFields')) {
            $fields = $builder->model->searchableFields();
            
            // 如果是键值对格式，提取字段名
            if (array_values($fields) !== $fields) {
                return array_keys($fields);
            }
            
            return $fields;
        }

        // 默认返回所有字段
        return ['*'];
    }

    /**
     * 构建过滤器
     */
    protected function buildFilters(Builder $builder): array
    {
        $filters = [];

        // 基本 where 条件
        foreach ($builder->wheres as $field => $value) {
            $filters[] = is_array($value)
                ? ['terms' => [$field => $value]]
                : ['term' => [$field => $value]];
        }

        // whereIn 条件
        foreach ($builder->whereIns as $field => $values) {
            $filters[] = ['terms' => [$field => $values]];
        }

        // whereNotIn 条件
        foreach ($builder->whereNotIns as $field => $values) {
            $filters[] = [
                'bool' => [
                    'must_not' => [
                        ['terms' => [$field => $values]],
                    ],
                ],
            ];
        }

        return $filters;
    }

    /**
     * 构建排序（优先 orders，无则从 getSorts 转换，兼容高级 Builder）
     */
    protected function buildSort(Builder $builder): ?array
    {
        if (!empty($builder->orders)) {
            $sorts = [];
            foreach ($builder->orders as $order) {
                $sorts[] = [
                    $order['column'] => [
                        'order' => $order['direction'] ?? 'asc',
                    ],
                ];
            }
            return $sorts;
        }

        if (method_exists($builder, 'getSorts')) {
            $sorts = $builder->getSorts();
            if (!empty($sorts)) {
                $out = [];
                foreach ($sorts as $s) {
                    $field = $s['field'] ?? null;
                    $dir = isset($s['direction']) ? strtolower($s['direction']) : 'asc';
                    if ($field !== null) {
                        $out[] = [$field => ['order' => $dir === 'desc' ? 'desc' : 'asc']];
                    }
                }
                return $out ?: null;
            }
        }

        return null;
    }

    /**
     * 映射 ID
     */
    public function mapIds($results)
    {
        return Collection::make($results['hits']['hits'] ?? [])
            ->pluck('_id')
            ->values();
    }

    /**
     * 映射结果到模型
     */
    public function map(Builder $builder, $results, $model)
    {
        if (($results['hits']['total']['value'] ?? 0) === 0) {
            return $model->newCollection();
        }

        $objectIds = $this->mapIds($results)->all();
        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds($builder, $objectIds)
            ->filter(fn($model) => in_array($model->getScoutKey(), $objectIds))
            ->sortBy(fn($model) => $objectIdPositions[$model->getScoutKey()])
            ->values();
    }

    /**
     * 懒加载映射
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if (($results['hits']['total']['value'] ?? 0) === 0) {
            return LazyCollection::make();
        }

        $objectIds = $this->mapIds($results)->all();

        return LazyCollection::make(function () use ($builder, $model, $objectIds) {
            $models = $model->getScoutModelsByIds($builder, $objectIds);
            
            foreach ($models as $model) {
                if (in_array($model->getScoutKey(), $objectIds)) {
                    yield $model;
                }
            }
        });
    }

    /**
     * 获取总数（兼容 OpenSearch/ES 2.x：total 可为 int 或 ['value' => n, 'relation' => 'eq']）
     */
    public function getTotalCount($results)
    {
        $total = $results['hits']['total'] ?? 0;
        if (is_array($total) && isset($total['value'])) {
            return (int) $total['value'];
        }
        return (int) $total;
    }

    /**
     * 清空索引
     */
    public function flush($model)
    {
        $index = $model->searchableAs();
        
        try {
            // 删除并重新创建索引
            $this->deleteIndex($index);
            $this->createIndex($index);
            
            Log::info('OpenSearch index flushed', ['index' => $index]);
        } catch (\Exception $e) {
            Log::error('Failed to flush OpenSearch index', [
                'index' => $index,
                'error' => $e->getMessage(),
            ]);
            
            if (config('app.debug')) {
                throw $e;
            }
        }
    }

    /**
     * 创建索引
     */
    public function createIndex($name, array $options = [])
    {
        try {
            // 检查索引是否存在
            if ($this->opensearch->indices()->exists(['index' => $name])) {
                return;
            }

            $params = ['index' => $name];

            // 合并配置选项
            if (!empty($options)) {
                $params['body'] = $options;
            } else {
                // 尝试从配置加载
                $config = scout_config("opensearch.indices.{$name}", []);
                if (!empty($config)) {
                    $params['body'] = $config;
                } else {
                    // 默认配置
                    $params['body'] = [
                        'settings' => [
                            'index' => [
                                'number_of_shards' => 1,
                                'number_of_replicas' => 1,
                                'refresh_interval' => '1s',
                            ],
                        ],
                        'mappings' => [
                            'properties' => [
                                'created_at' => ['type' => 'date'],
                                'updated_at' => ['type' => 'date'],
                            ],
                        ],
                    ];
                }
            }

            $this->opensearch->indices()->create($params);
            
            // 设置别名
            $this->setupAliases($name, $options['aliases'] ?? []);

            Log::info('OpenSearch index created', ['index' => $name]);

        } catch (\Exception $e) {
            Log::error('Failed to create OpenSearch index', [
                'index' => $name,
                'error' => $e->getMessage(),
            ]);
            
            if (config('app.debug')) {
                throw $e;
            }
        }
    }

    /**
     * 设置别名
     */
    protected function setupAliases(string $index, array $aliases): void
    {
        if (empty($aliases)) {
            return;
        }

        $actions = [];
        
        foreach ($aliases as $alias) {
            $actions[] = [
                'add' => [
                    'index' => $index,
                    'alias' => $alias,
                ],
            ];
        }

        try {
            $this->opensearch->indices()->updateAliases(['body' => ['actions' => $actions]]);
        } catch (\Exception $e) {
            Log::warning('Failed to set OpenSearch aliases', [
                'index' => $index,
                'aliases' => $aliases,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 删除索引
     */
    public function deleteIndex($name)
    {
        try {
            if ($this->opensearch->indices()->exists(['index' => $name])) {
                $this->opensearch->indices()->delete(['index' => $name]);
                Log::info('OpenSearch index deleted', ['index' => $name]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete OpenSearch index', [
                'index' => $name,
                'error' => $e->getMessage(),
            ]);
            
            if (config('app.debug')) {
                throw $e;
            }
        }
    }

    /**
     * 获取索引信息
     */
    public function getIndexInfo(string $index): array
    {
        try {
            return $this->opensearch->indices()->get(['index' => $index]);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 检查索引是否存在
     */
    public function indexExists(string $index): bool
    {
        try {
            return $this->opensearch->indices()->exists(['index' => $index]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 更新索引设置
     */
    public function updateIndexSettings(string $index, array $settings): bool
    {
        try {
            $this->opensearch->indices()->putSettings([
                'index' => $index,
                'body' => $settings,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update OpenSearch index settings', [
                'index' => $index,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 更新映射
     */
    public function updateIndexMappings(string $index, array $mappings): bool
    {
        try {
            $this->opensearch->indices()->putMapping([
                'index' => $index,
                'body' => $mappings,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update OpenSearch index mappings', [
                'index' => $index,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 检查是否使用软删除
     */
    protected function usesSoftDelete($model): bool
    {
        return in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model));
    }

    /**
     * 获取 OpenSearch 客户端
     */
    public function getClient(): Client
    {
        return $this->opensearch;
    }

    /**
     * 设置批量操作大小
     */
    public function setBulkSize(int $size): self
    {
        $this->bulkSize = $size;
        return $this;
    }


    
    /**
     * 动态调用 OpenSearch 客户端方法
     */
    public function __call($method, $parameters)
    {
        return $this->opensearch->$method(...$parameters);
    }
}