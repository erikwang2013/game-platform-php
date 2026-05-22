<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Engines;

use Erikwang2013\WebmanScout\Builder as AdvancedScoutBuilder;
use support\Log;

class AdvancedTypesenseEngine extends TypesenseEngine
{
    /**
     * 获取查询字段（用于 query_by）
     */
    protected function getQueryByFields(AdvancedScoutBuilder $builder): string
    {
        // 优先使用 builder 指定的字段
        if ($builder->options['query_by'] ?? false) {
            return $builder->options['query_by'];
        }

        // 使用模型定义的搜索字段
        if (method_exists($builder->model, 'searchableFields')) {
            $fields = $builder->model->searchableFields();
            
            // 如果是键值对格式，则提取键名并添加权重
            if (array_values($fields) !== $fields) {
                $weightedFields = [];
                foreach ($fields as $field => $weight) {
                    $weightedFields[] = "{$field}^{$weight}";
                }
                return implode(',', $weightedFields);
            }
            
            return implode(',', $fields);
        }

        // 默认搜索所有字段
        return '*';
    }

    /**
     * 计算页码
     */
    protected function calculatePage(AdvancedScoutBuilder $builder): int
    {
        if ($builder->limit && $builder->offset) {
            return (int) ($builder->offset / $builder->limit) + 1;
        }
        
        return 1;
    }

    /**
     * 获取搜索字段
     */
    protected function getSearchFields(AdvancedScoutBuilder $builder): array
    {
        // 优先使用 builder 指定的字段
        if ($builder->options['fields'] ?? false) {
            return is_array($builder->options['fields']) 
                ? $builder->options['fields']
                : explode(',', $builder->options['fields']);
        }

        // 使用模型定义的搜索字段
        if (method_exists($builder->model, 'searchableFields')) {
            $fields = $builder->model->searchableFields();
            
            // 如果是键值对格式，则提取键名
            if (array_values($fields) !== $fields) {
                return array_keys($fields);
            }
            
            return $fields;
        }

        // 默认搜索所有字段
        return ['*'];
    }

    /**
     * 获取高亮字段
     */
    protected function getHighlightFields(AdvancedScoutBuilder $builder): ?string
    {
        if ($highlightFields = $builder->options['highlight_fields'] ?? $builder->options['highlight_full_fields'] ?? null) {
            return is_array($highlightFields) ? implode(',', $highlightFields) : $highlightFields;
        }

        return null;
    }

    /**
     * 获取包含字段
     */
    protected function getIncludeFields(AdvancedScoutBuilder $builder): ?string
    {
        if ($includes = $builder->options['include_fields'] ?? $builder->options['include'] ?? null) {
            return is_array($includes) ? implode(',', $includes) : $includes;
        }
        
        return null;
    }

    /**
     * 获取排除字段
     */
    protected function getExcludeFields(AdvancedScoutBuilder $builder): ?string
    {
        if ($excludes = $builder->options['exclude_fields'] ?? $builder->options['exclude'] ?? null) {
            return is_array($excludes) ? implode(',', $excludes) : $excludes;
        }
        
        return null;
    }

    /**
     * 构建 Typesense 搜索参数（增强版）
     */
    protected function buildTypesenseSearchParams(AdvancedScoutBuilder $builder): array
    {
        $params = [
            'q' => $builder->query ?? '*',
            'query_by' => $this->getQueryByFields($builder),
            'per_page' => $builder->limit ?: 20,
            'page' => $this->calculatePage($builder),
            'include_fields' => $this->getIncludeFields($builder),
            'exclude_fields' => $this->getExcludeFields($builder),
            'highlight_full_fields' => $this->getHighlightFields($builder),
            'prefix' => $builder->options['prefix'] ?? true,
            'filter_by' => $this->buildTypesenseFilters($builder),
            'sort_by' => $this->buildTypesenseSorts($builder->getSorts()),
            'group_by' => $builder->options['group_by'] ?? null,
            'group_limit' => $builder->options['group_limit'] ?? 3,
            'facet_by' => $this->buildFacetBy($builder),
            'max_facet_values' => $builder->options['max_facet_values'] ?? 10,
            'highlight_start_tag' => $builder->options['highlight_start_tag'] ?? '<mark>',
            'highlight_end_tag' => $builder->options['highlight_end_tag'] ?? '</mark>',
            'snippet_threshold' => $builder->options['snippet_threshold'] ?? 30,
            'num_typos' => $builder->options['num_typos'] ?? 2,
            'typo_tokens_threshold' => $builder->options['typo_tokens_threshold'] ?? 10,
            'drop_tokens_threshold' => $builder->options['drop_tokens_threshold'] ?? 10,
            'exhaustive_search' => $builder->options['exhaustive_search'] ?? false,
            'search_cutoff_ms' => $builder->options['search_cutoff_ms'] ?? null,
            'use_cache' => $builder->options['use_cache'] ?? true,
            'cache_ttl' => $builder->options['cache_ttl'] ?? 60,
            'min_len_1typo' => $builder->options['min_len_1typo'] ?? 4,
            'min_len_2typo' => $builder->options['min_len_2typo'] ?? 8,
            'prioritize_token_position' => $builder->options['prioritize_token_position'] ?? false,
            'prioritize_num_matching_fields' => $builder->options['prioritize_num_matching_fields'] ?? false,
            'enable_overrides' => $builder->options['enable_overrides'] ?? true,
        ];

        // 向量搜索
        if ($vectorSearch = $builder->getVectorSearch()) {
            $params = $this->addTypesenseVectorSearch($params, $vectorSearch);
        }

        // 地理位置搜索
        if ($geoSearch = $this->extractTypesenseGeoSearch($builder)) {
            $params = array_merge($params, $geoSearch);
        }

        // 语义搜索
        if ($builder->options['enable_semantic_search'] ?? false) {
            $params['enable_semantic_search'] = true;
        }

        // 多搜索查询（如果需要）
        if ($builder->options['multi_search'] ?? false) {
            $params['multi_search'] = $builder->options['multi_search'];
        }

        return $params;
    }

    /**
     * 构建 Typesense 筛选条件（增强版）
     */
    protected function buildTypesenseFilters(AdvancedScoutBuilder $builder): string
    {
        $filters = [];

        // 基本 where 条件
        foreach ($builder->wheres as $field => $value) {
            if (is_array($value)) {
                $filters[] = $field . ':[' . implode(', ', $this->formatTypesenseValues($value)) . ']';
            } else {
                $filters[] = $field . ':=' . $this->formatTypesenseValue($value);
            }
        }

        // 高级 where 条件
        foreach ($builder->getAdvancedWheres() as $condition) {
            $filters[] = $this->buildTypesenseFilter($condition);
        }

        // whereIn 条件
        foreach ($builder->whereIns as $field => $values) {
            $filters[] = $field . ':[' . implode(', ', $this->formatTypesenseValues($values)) . ']';
        }

        // whereNotIn 条件
        foreach ($builder->whereNotIns as $field => $values) {
            $filters[] = $field . ':![' . implode(', ', $this->formatTypesenseValues($values)) . ']';
        }

        return implode(' && ', $filters);
    }

    /**
     * 格式化 Typesense 值
     */
    protected function formatTypesenseValue($value): string
    {
        if (is_string($value)) {
            return '"' . addslashes($value) . '"';
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            return 'null';
        } else {
            return (string) $value;
        }
    }

    /**
     * 格式化 Typesense 值数组
     */
    protected function formatTypesenseValues(array $values): array
    {
        return array_map([$this, 'formatTypesenseValue'], $values);
    }

    /**
     * 构建 Typesense 筛选条件（增强版）
     */
    protected function buildTypesenseFilter(array $condition): string
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        $options = $condition['options'] ?? [];

        return match($operator) {
            'range' => sprintf(
                '%s:>=%s && %s:<=%s',
                $field,
                $this->formatTypesenseValue($value['range'][0] ?? null),
                $field,
                $this->formatTypesenseValue($value['range'][1] ?? null)
            ),
            'date_range' => sprintf(
                '%s:>=%s && %s:<=%s',
                $field,
                $this->formatTypesenseValue($value['range'][0] ?? null),
                $field,
                $this->formatTypesenseValue($value['range'][1] ?? null)
            ),
            '>' => sprintf('%s:>%s', $field, $this->formatTypesenseValue($value)),
            '>=' => sprintf('%s:>=%s', $field, $this->formatTypesenseValue($value)),
            '<' => sprintf('%s:<%s', $field, $this->formatTypesenseValue($value)),
            '<=' => sprintf('%s:<=%s', $field, $this->formatTypesenseValue($value)),
            '!=' => sprintf('%s:!=%s', $field, $this->formatTypesenseValue($value)),
            'geo_radius' => sprintf(
                '%s:(%s, %s, %s km)',
                $field,
                $value['lat'],
                $value['lng'],
                $value['radius']
            ),
            'geo_bounding_box' => sprintf(
                '%s:([%s, %s], [%s, %s])',
                $field,
                $value['top_left']['lat'],
                $value['top_left']['lng'],
                $value['bottom_right']['lat'],
                $value['bottom_right']['lng']
            ),
            'exists' => $field . ':*',
            'missing' => '!' . $field . ':*',
            'contains' => $field . ':*' . $value . '*',
            'starts_with' => $field . ':' . $value . '*',
            'ends_with' => $field . ':*' . $value,
            'regex' => $field . ':/' . $value . '/',
            'match' => $field . ':= ' . $this->formatTypesenseValue($value),
            'in' => $field . ':[' . implode(', ', $this->formatTypesenseValues((array)$value)) . ']',
            'not_in' => $field . ':![' . implode(', ', $this->formatTypesenseValues((array)$value)) . ']',
            'null' => $field . ':= null',
            'not_null' => $field . ':!= null',
            'empty' => $field . ':= ""',
            'not_empty' => $field . ':!= ""',
            default => $field . ':' . $operator . $this->formatTypesenseValue($value),
        };
    }

    /**
     * 构建 Typesense 排序（增强版）
     */
    protected function buildTypesenseSorts(array $sorts): string
    {
        $typesenseSorts = [];

        foreach ($sorts as $sort) {
            $field = $sort['field'] ?? null;
            $type = $sort['type'] ?? null;
            $direction = $sort['direction'] ?? 'asc';
            $options = $sort['options'] ?? [];

            if ($type === 'vector_similarity') {
                $vector = $sort['vector'] ?? [];
                $vectorField = $sort['field'] ?? 'embedding';
                $typesenseSorts[] = sprintf(
                    'embedding(%s):asc',
                    implode(',', $vector)
                );
            } elseif ($type === 'geo_distance') {
                $typesenseSorts[] = sprintf(
                    'location(%s,%s):asc',
                    $sort['location']['lat'] ?? 0,
                    $sort['location']['lng'] ?? 0
                );
            } elseif ($type === 'random') {
                $typesenseSorts[] = '_random_score:asc';
            } elseif ($field === '_text_match') {
                $typesenseSorts[] = '_text_match:desc';
            } elseif ($field === '_vector_distance') {
                $typesenseSorts[] = '_vector_distance:asc';
            } else {
                $direction = $direction === 'desc' ? ':desc' : ':asc';
                $typesenseSorts[] = $field . $direction;
            }
        }

        return implode(',', $typesenseSorts);
    }

    /**
     * 添加 Typesense 向量搜索（增强版）
     */
    protected function addTypesenseVectorSearch(array $params, array $vectorSearch): array
    {
        $vector = $vectorSearch['vector'] ?? null;
        $field = $vectorSearch['field'] ?? 'embedding';
        $options = $vectorSearch['options'] ?? [];
        
        if (!$vector) {
            return $params;
        }

        $params['vector_query'] = $field . ':[' . implode(',', $vector) . ']';
        $params['k'] = $options['top_k'] ?? 10;
        
        if (isset($options['distance_threshold'])) {
            $params['distance_threshold'] = $options['distance_threshold'];
        }
        
        if (isset($options['metric'])) {
            $params['vector_distance_metric'] = $options['metric'];
        }
        
        if (isset($options['include_vector_distance'])) {
            $params['include_vector_distance'] = $options['include_vector_distance'];
        }
        
        if (isset($options['include_vector'])) {
            $params['include_vector'] = $options['include_vector'];
        }

        return $params;
    }

    /**
     * 构建分面字段
     */
    protected function buildFacetBy(AdvancedScoutBuilder $builder): string
    {
        $facets = array_merge(
            array_keys($builder->getFacetConfig()),
            $builder->options['facet_by'] ?? []
        );

        return implode(',', array_unique($facets));
    }

    /**
     * 提取 Typesense 地理位置搜索（增强版）
     */
    protected function extractTypesenseGeoSearch(AdvancedScoutBuilder $builder): array
    {
        $geoParams = [];

        foreach ($builder->getAdvancedWheres() as $condition) {
            if ($condition['operator'] === 'geo_radius') {
                $value = $condition['value'];
                $geoParams = array_merge($geoParams, [
                    'location_field' => $condition['field'],
                    'location_value' => sprintf(
                        '%s,%s,%skm',
                        $value['lat'],
                        $value['lng'],
                        $value['radius']
                    ),
                ]);
            } elseif ($condition['operator'] === 'geo_bounding_box') {
                $value = $condition['value'];
                $geoParams = array_merge($geoParams, [
                    'location_field' => $condition['field'],
                    'location_bounding_box' => sprintf(
                        '[%s,%s],[%s,%s]',
                        $value['top_left']['lat'],
                        $value['top_left']['lng'],
                        $value['bottom_right']['lat'],
                        $value['bottom_right']['lng']
                    ),
                ]);
            } elseif ($condition['operator'] === 'geo_polygon') {
                $value = $condition['value'];
                $points = array_map(function ($point) {
                    return sprintf('[%s,%s]', $point['lat'], $point['lng']);
                }, $value['points']);
                
                $geoParams = array_merge($geoParams, [
                    'location_field' => $condition['field'],
                    'location_polygon' => implode(',', $points),
                ]);
            }
        }

        return $geoParams;
    }

    /**
     * 处理 Typesense 结果（增强版）
     */
    protected function processTypesenseResults(array $result, AdvancedScoutBuilder $builder): array
    {
        $processedResults = [
            'hits' => array_map(function ($hit) {
                $document = $hit['document'] ?? $hit;
                $document['_score'] = $hit['text_match'] ?? 0;
                $document['_id'] = $hit['document']['id'] ?? $hit['id'] ?? null;
                
                if (isset($hit['highlights'])) {
                    $document['_highlight'] = $hit['highlights'];
                }
                
                if (isset($hit['vector_distance'])) {
                    $document['_vector_distance'] = $hit['vector_distance'];
                }
                
                if (isset($hit['snippet'])) {
                    $document['_snippet'] = $hit['snippet'];
                }
                
                return $document;
            }, $result['hits'] ?? []),
            'total' => $result['found'] ?? 0,
            'out_of' => $result['out_of'] ?? $result['found'] ?? 0,
            'page' => $result['page'] ?? 1,
            'per_page' => $result['per_page'] ?? 20,
            'facets' => $result['facet_counts'] ?? [],
            'search_time_ms' => $result['search_time_ms'] ?? 0,
            'request_params' => $result['request_params'] ?? null,
            'grouped_hits' => $result['grouped_hits'] ?? [],
        ];

        // 应用结果处理器
        foreach ($builder->getResultProcessors() as $processor) {
            $processedResults = $processor($processedResults);
        }

        return $processedResults;
    }

    /**
     * 获取聚合结果（增强版）
     */
    public function getAggregations(AdvancedScoutBuilder $builder): array
    {
        $searchParams = $this->buildTypesenseSearchParams($builder);
        
        $result = $this->typesense->getCollections()
            ->{$this->getIndexName($builder->model)}
            ->getDocuments()
            ->search($searchParams);

        return [
            'facets' => $result['facet_counts'] ?? [],
            'total' => $result['found'] ?? 0,
            'groups' => $result['grouped_hits'] ?? [],
        ];
    }

    /**
     * 获取分面结果
     */
    public function getFacets(AdvancedScoutBuilder $builder): array
    {
        return $this->getAggregations($builder);
    }

    /**
     * 更新向量（增强版）
     */
    public function updateVectors($models, array $vectors): void
    {
        $collection = $this->typesense->getCollections()
            ->{$this->getIndexName($models->first())};

        $documents = [];

        foreach ($models as $i => $model) {
            $document = array_merge(
                $model->toSearchableArray(),
                ['id' => $model->getScoutKey()]
            );

            if (isset($vectors[$i])) {
                $document['embedding'] = $vectors[$i];
                $document['vector'] = $vectors[$i]; // 兼容字段
            }

            $documents[] = $document;
        }

        try {
            $collection->getDocuments()->import($documents, ['action' => 'upsert']);
        } catch (\Exception $e) {
            Log::error('Failed to update vectors in Typesense', [
                'error' => $e->getMessage(),
                'collection' => $this->getIndexName($models->first()),
                'count' => count($documents),
            ]);
            throw $e;
        }
    }

    /**
     * 创建向量索引（增强版）
     */
    public function createVectorIndex(string $index, int $dimensions = 1536, array $settings = []): bool
    {
        try {
            $schema = [
                'name' => $index,
                'fields' => [
                    ['name' => 'id', 'type' => 'string'],
                    ['name' => 'embedding', 'type' => 'float[]', 'num_dim' => $dimensions],
                    ['name' => '.*', 'type' => 'auto'],
                ],
                'default_sorting_field' => $settings['default_sorting_field'] ?? 'id',
            ];

            // 添加自定义字段
            if (isset($settings['fields'])) {
                $schema['fields'] = array_merge($schema['fields'], $settings['fields']);
            }

            // 添加向量搜索配置
            $schema['fields'][] = [
                'name' => 'embedding',
                'type' => 'float[]',
                'num_dim' => $dimensions,
                'optional' => $settings['optional'] ?? true,
                'index' => $settings['index'] ?? true,
            ];

            $this->typesense->getCollections()->create($schema);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create Typesense vector index', [
                'error' => $e->getMessage(),
                'index' => $index,
                'dimensions' => $dimensions,
            ]);
            return false;
        }
    }

    /**
     * 获取索引名称
     */
    protected function getIndexName($model): string
    {
        $indexName = $model->searchableAs();
        
        if ($this->prefix) {
            $indexName = $this->prefix . $indexName;
        }
        
        return $indexName;
    }

    /**
     * 获取引擎信息
     */
    public function getEngineInfo(): array
    {
        try {
            $collections = $this->typesense->getCollections()->retrieve();
            $health = $this->typesense->getOperations()->health();
            
            return [
                'type' => 'typesense',
                'version' => $this->typesense->getConfiguration()->getVersion(),
                'collections' => count($collections),
                'isHealthy' => $health['ok'] ?? false,
                'supportsVectors' => true,
                'memoryUsage' => $this->typesense->getMetrics()->retrieve()['memory_usage_bytes'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'type' => 'typesense',
                'error' => $e->getMessage(),
                'isHealthy' => false,
            ];
        }
    }

    /**
     * 执行原始搜索
     */
    public function rawSearch(array $params): array
    {
        $collection = $this->typesense->getCollections()->{$params['collection']};
        
        unset($params['collection']);
        
        return $collection->getDocuments()->search($params);
    }

    /**
     * 获取文档
     */
    public function getDocument(string $collectionName, string $id): array
    {
        $collection = $this->typesense->getCollections()->{$collectionName};
        
        try {
            return $collection->getDocuments()[$id];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 批量获取文档
     */
    public function getDocuments(string $collectionName, array $ids): array
    {
        $collection = $this->typesense->getCollections()->{$collectionName};
        
        try {
            $filter = 'id:[' . implode(', ', array_map(fn($id) => '"' . $id . '"', $ids)) . ']';
            return $collection->getDocuments()->search(['filter_by' => $filter]);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 批量删除文档
     */
    public function deleteDocuments(array $ids, string $collectionName): void
    {
        $collection = $this->typesense->getCollections()->{$collectionName};
        
        foreach ($ids as $id) {
            try {
                $collection->getDocuments()[$id]->delete();
            } catch (\Exception $e) {
                Log::warning('Failed to delete document from Typesense', [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 清空索引
     */
    public function truncateIndex(string $collectionName): void
    {
        $collection = $this->typesense->getCollections()->{$collectionName};
        
        try {
            // Typesense 没有直接的清空方法，需要删除并重建
            $schema = $collection->retrieve();
            $this->typesense->getCollections()->{$collectionName}->delete();
            
            // 等待删除完成
            sleep(1);
            
            // 重新创建
            $this->typesense->getCollections()->create($schema);
        } catch (\Exception $e) {
            Log::error('Failed to truncate Typesense collection', [
                'collection' => $collectionName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 创建同义词
     */
    public function createSynonym(string $collectionName, string $id, array $synonyms): bool
    {
        $collection = $this->typesense->getCollections()->{$collectionName};
        
        try {
            $collection->getSynonyms()->upsert($id, [
                'synonyms' => $synonyms,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create synonym in Typesense', [
                'collection' => $collectionName,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 创建重写规则
     */
    public function createOverride(string $collectionName, string $id, array $override): bool
    {
        $collection = $this->typesense->getCollections()->{$collectionName};
        
        try {
            $collection->getOverrides()->upsert($id, $override);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create override in Typesense', [
                'collection' => $collectionName,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}